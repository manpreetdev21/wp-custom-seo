<?php
/**
 * Copying SEO data in from another plugin.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Transfer;

use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

/*
 * Selects the posts holding source data with one query per batch. WP_Query
 * cannot express "any of these twelve meta keys, non-empty" without a dozen
 * joins, so the id list is fetched directly and the meta cache primed from it.
 *
 * Every value is still passed through prepare(); what is interpolated is the
 * list of %s placeholders itself, built from the source's key list, which the
 * placeholder sniffs cannot count statically.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders
 */

/**
 * Copies another plugin's post meta into this one's, a batch at a time.
 *
 * Three rules, all of which exist because an import runs over content someone
 * spent years writing:
 *
 * 1. The source is never modified or deleted. If the result is wrong, the old
 *    plugin's data is still there to try again from.
 * 2. A value this plugin already holds is never overwritten unless the person
 *    running the import asks for it explicitly.
 * 3. Anything that could not be carried across is counted and reported, rather
 *    than dropped quietly.
 *
 * Work is done in batches so a large site does not need a background process
 * or a raised time limit, and so a failure loses one batch rather than a run.
 */
final class Import {

	/**
	 * Posts per batch.
	 *
	 * Small enough to finish inside a normal request on modest hosting, large
	 * enough that a few thousand posts is a handful of presses.
	 */
	public const BATCH = 200;

	/**
	 * Copy one batch.
	 *
	 * @param string $slug      Source slug.
	 * @param int    $offset    Posts already processed.
	 * @param bool   $overwrite Replace values this plugin already holds.
	 * @param int    $batch     Batch size.
	 *
	 * @return array{processed: int, posts: int, fields: int, skipped: int, dropped: string[], total: int, done: bool}
	 */
	public static function run( string $slug, int $offset = 0, bool $overwrite = false, int $batch = self::BATCH ): array {
		$source = Sources::get( $slug );

		$result = array(
			'processed' => 0,
			'posts'     => 0,
			'fields'    => 0,
			'skipped'   => 0,
			'dropped'   => array(),
			'total'     => 0,
			'done'      => true,
		);

		if ( null === $source ) {
			return $result;
		}

		$keys            = array_values( array_unique( array_merge( array_values( $source['fields'] ), array_values( $source['flags'] ) ) ) );
		$result['total'] = self::total( $keys );

		$ids = self::ids( $keys, max( 0, $offset ), max( 1, $batch ) );

		if ( ! $ids ) {
			return $result;
		}

		// One query for every meta value in the batch, after which get_post_meta
		// is served from cache.
		update_meta_cache( 'post', $ids );

		foreach ( $ids as $post_id ) {
			$outcome = self::post( $post_id, $slug, $source, $overwrite );

			++$result['processed'];
			$result['fields']  += $outcome['fields'];
			$result['skipped'] += $outcome['skipped'];
			$result['dropped']  = array_merge( $result['dropped'], $outcome['dropped'] );

			if ( $outcome['fields'] > 0 ) {
				++$result['posts'];
			}
		}

		$result['dropped'] = array_values( array_unique( $result['dropped'] ) );
		$result['done']    = ( $offset + $result['processed'] ) >= $result['total'];

		return $result;
	}

	/**
	 * Copy one post's fields.
	 *
	 * @param int                  $post_id   Post id.
	 * @param string               $slug      Source slug.
	 * @param array<string, mixed> $source    Source definition.
	 * @param bool                 $overwrite Replace existing values.
	 *
	 * @return array{fields: int, skipped: int, dropped: string[]}
	 */
	private static function post( int $post_id, string $slug, array $source, bool $overwrite ): array {
		$written = 0;
		$skipped = 0;
		$dropped = array();

		foreach ( (array) $source['fields'] as $ours => $theirs ) {
			$read = Sources::text( $slug, $ours, get_post_meta( $post_id, (string) $theirs, true ) );

			if ( '' === $read['value'] ) {
				continue;
			}

			$dropped = array_merge( $dropped, $read['dropped'] );

			if ( ! $overwrite && '' !== (string) get_post_meta( $post_id, (string) $ours, true ) ) {
				++$skipped;

				continue;
			}

			$sanitize = Meta::keys()[ $ours ]['sanitize'] ?? 'sanitize_text_field';
			$value    = call_user_func( $sanitize, $read['value'] );

			if ( '' === (string) $value ) {
				// A canonical that is not a URL, or a schema type this plugin
				// does not offer, sanitizes away to nothing. Writing an empty
				// value would look like a successful import of a blank field.
				++$skipped;

				continue;
			}

			update_post_meta( $post_id, (string) $ours, $value );
			++$written;
		}

		foreach ( (array) $source['flags'] as $ours => $theirs ) {
			$raw = get_post_meta( $post_id, (string) $theirs, true );

			if ( '' === $raw || array() === $raw ) {
				continue;
			}

			if ( ! Sources::flag( $slug, (string) $ours, $raw ) ) {
				// Off is this plugin's default, so there is nothing to write —
				// and writing it would make an untouched post look imported.
				continue;
			}

			if ( ! $overwrite && '' !== (string) get_post_meta( $post_id, (string) $ours, true ) ) {
				++$skipped;

				continue;
			}

			update_post_meta( $post_id, (string) $ours, true );
			++$written;
		}

		return array(
			'fields'  => $written,
			'skipped' => $skipped,
			'dropped' => $dropped,
		);
	}

	/**
	 * How many posts hold data for these keys.
	 *
	 * @param string[] $keys Source meta keys.
	 */
	private static function total( array $keys ): int {
		global $wpdb;

		$tokens = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT post_id ) FROM {$wpdb->postmeta} WHERE meta_key IN ({$tokens}) AND meta_value <> ''",
				...$keys
			)
		);
	}

	/**
	 * One batch of post ids holding source data.
	 *
	 * Ordered by id so paging with an offset is stable. It stays stable across
	 * batches because the import only ever writes this plugin's own keys, so
	 * the set of posts matching the source keys does not move under it.
	 *
	 * @param string[] $keys   Source meta keys.
	 * @param int      $offset Offset.
	 * @param int      $batch  Batch size.
	 *
	 * @return int[]
	 */
	private static function ids( array $keys, int $offset, int $batch ): array {
		global $wpdb;

		$tokens = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		$rows = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key IN ({$tokens}) AND meta_value <> ''
				ORDER BY post_id ASC
				LIMIT %d OFFSET %d",
				...array_merge( $keys, array( $batch, $offset ) )
			)
		);

		return array_map( 'intval', $rows );
	}
}
