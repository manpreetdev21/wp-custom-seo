<?php
/**
 * CSV export and import of post SEO fields.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Transfer;

use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Round-trips the editable SEO fields through a spreadsheet.
 *
 * The export is the import's own format: what comes out can be edited and put
 * straight back. That is the point of it — bulk work on hundreds of pages is
 * done in a spreadsheet, not in a browser, and an export that cannot be
 * re-imported is just a report.
 *
 * A row is matched on `post_id` alone. The `url`, `post_type` and `post_title`
 * columns are there so a human can tell which row is which; they are never
 * written back, so a stale export cannot rename anything.
 */
final class Csv {

	/**
	 * Columns carrying data, as column name to meta key.
	 *
	 * @return array<string, string>
	 */
	public static function columns(): array {
		return array(
			'seo_title'           => Meta::TITLE,
			'meta_description'    => Meta::DESCRIPTION,
			'focus_keyword'       => Meta::FOCUS_KEYWORD,
			'canonical'           => Meta::CANONICAL,
			'noindex'             => Meta::NOINDEX,
			'nofollow'            => Meta::NOFOLLOW,
			'schema_type'         => Meta::SCHEMA_TYPE,
			'breadcrumb_title'    => Meta::BREADCRUMB_TITLE,
			'search_intent'       => Meta::SEARCH_INTENT,
			'og_title'            => Meta::OG_TITLE,
			'og_description'      => Meta::OG_DESCRIPTION,
			'og_image'            => Meta::OG_IMAGE,
			'twitter_title'       => Meta::TWITTER_TITLE,
			'twitter_description' => Meta::TWITTER_DESCRIPTION,
			'twitter_image'       => Meta::TWITTER_IMAGE,
		);
	}

	/**
	 * Columns shown for orientation and ignored on import.
	 *
	 * @return string[]
	 */
	public static function reference_columns(): array {
		return array( 'post_id', 'post_type', 'post_title', 'url' );
	}

	/**
	 * The full header row.
	 *
	 * @return string[]
	 */
	public static function header(): array {
		return array_merge( self::reference_columns(), array_keys( self::columns() ) );
	}

	/**
	 * Write every post's SEO fields to an open stream.
	 *
	 * Rows are fetched in pages and the stream is flushed as it goes, so memory
	 * does not grow with the size of the site.
	 *
	 * @param resource $handle Open write stream.
	 * @param int      $page   Posts per query.
	 *
	 * @return int Rows written.
	 */
	public static function write( $handle, int $page = 500 ): int {
		fputcsv( $handle, self::header() );

		$written = 0;
		$paged   = 1;
		$fetched = 0;

		do {
			$posts = get_posts(
				array(
					'post_type'        => Meta::post_types(),
					'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
					'posts_per_page'   => $page,
					'paged'            => $paged,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);

			$fetched = count( $posts );

			if ( $posts ) {
				update_meta_cache( 'post', wp_list_pluck( $posts, 'ID' ) );
			}

			foreach ( $posts as $post ) {
				fputcsv( $handle, self::row( $post ) );
				++$written;
			}

			++$paged;
		} while ( $fetched === $page );

		return $written;
	}

	/**
	 * One post as a row.
	 *
	 * @param \WP_Post $post Post.
	 *
	 * @return string[]
	 */
	public static function row( \WP_Post $post ): array {
		$row = array(
			(string) $post->ID,
			(string) $post->post_type,
			(string) $post->post_title,
			(string) get_permalink( $post ),
		);

		foreach ( self::columns() as $key ) {
			$value = Meta::get( (int) $post->ID, $key );
			$row[] = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value;
		}

		return $row;
	}

	/**
	 * Read a CSV file into rows keyed by column name.
	 *
	 * @param string $path File path.
	 *
	 * @return array{rows: array<int, array<string, string>>, error: string}
	 */
	public static function read( string $path ): array {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return array(
				'rows'  => array(),
				'error' => __( 'That file could not be opened.', 'wp-custom-seo' ),
			);
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			return array(
				'rows'  => array(),
				'error' => __( 'That file is empty.', 'wp-custom-seo' ),
			);
		}

		// A file saved from Excel often starts with a byte order mark, which
		// would otherwise turn the first column name into something unmatchable.
		$header = array_map(
			static fn ( $name ): string => strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $name ) ?? '' ) ),
			$header
		);

		if ( ! in_array( 'post_id', $header, true ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			return array(
				'rows'  => array(),
				'error' => __( 'That file has no post_id column, so there is no way to tell which post each row belongs to. Export a file first and edit that.', 'wp-custom-seo' ),
			);
		}

		$rows = array();

		for ( ; ; ) {
			$line = fgetcsv( $handle );

			if ( false === $line ) {
				break;
			}

			// A blank line comes back as a single null cell.
			if ( ! is_array( $line ) || array( null ) === $line ) {
				continue;
			}

			$row = array();

			foreach ( $header as $index => $name ) {
				$row[ $name ] = isset( $line[ $index ] ) ? (string) $line[ $index ] : '';
			}

			$rows[] = $row;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'rows'  => $rows,
			'error' => '',
		);
	}

	/**
	 * Apply rows to their posts.
	 *
	 * A dry run reports exactly what a real run would do and writes nothing,
	 * because the alternative is finding out afterwards.
	 *
	 * @param array<int, array<string, string>> $rows    Parsed rows.
	 * @param bool                              $dry_run Report only.
	 *
	 * @return array{rows: int, posts: int, fields: int, unchanged: int, problems: array<int, string>}
	 */
	public static function apply( array $rows, bool $dry_run = true ): array {
		$columns  = self::columns();
		$result   = array(
			'rows'      => 0,
			'posts'     => 0,
			'fields'    => 0,
			'unchanged' => 0,
			'problems'  => array(),
		);
		$reported = 0;

		foreach ( $rows as $index => $row ) {
			++$result['rows'];

			$line    = $index + 2;
			$post_id = (int) ( $row['post_id'] ?? 0 );
			$post    = $post_id > 0 ? get_post( $post_id ) : null;

			if ( ! $post ) {
				if ( $reported < 20 ) {
					/* translators: 1: CSV line number, 2: post id. */
					$result['problems'][] = sprintf( __( 'Line %1$d: no post with id %2$d. Row skipped.', 'wp-custom-seo' ), $line, $post_id );
					++$reported;
				}

				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				if ( $reported < 20 ) {
					/* translators: 1: CSV line number, 2: post id. */
					$result['problems'][] = sprintf( __( 'Line %1$d: you cannot edit post %2$d. Row skipped.', 'wp-custom-seo' ), $line, $post_id );
					++$reported;
				}

				continue;
			}

			$changed = 0;

			foreach ( $columns as $column => $key ) {
				if ( ! array_key_exists( $column, $row ) ) {
					continue;
				}

				$definition = Meta::keys()[ $key ];
				$raw        = trim( $row[ $column ] );

				if ( 'boolean' === $definition['type'] ) {
					$value = self::boolean( $raw );
				} else {
					$value = call_user_func( $definition['sanitize'], $raw );

					if ( '' !== $raw && '' === (string) $value ) {
						if ( $reported < 20 ) {
							/* translators: 1: CSV line number, 2: column name, 3: offending value. */
							$result['problems'][] = sprintf( __( 'Line %1$d: "%2$s" would not accept "%3$s". Field left alone.', 'wp-custom-seo' ), $line, $column, $raw );
							++$reported;
						}

						continue;
					}
				}

				$current = Meta::get( $post_id, $key );

				if ( $current === $value ) {
					++$result['unchanged'];

					continue;
				}

				if ( ! $dry_run ) {
					if ( '' === $value || false === $value ) {
						// An emptied cell means "no value", which is a deletion
						// rather than a stored empty string.
						delete_post_meta( $post_id, $key );
					} else {
						update_post_meta( $post_id, $key, $value );
					}
				}

				++$changed;
			}

			$result['fields'] += $changed;

			if ( $changed > 0 ) {
				++$result['posts'];
			}
		}

		return $result;
	}

	/**
	 * Read a spreadsheet's idea of yes or no.
	 *
	 * @param string $value Cell value.
	 */
	public static function boolean( string $value ): bool {
		return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'y', 'on' ), true );
	}
}
