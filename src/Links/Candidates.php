<?php
/**
 * Internal link candidate finder.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Links;

use WPCustomSeo\Audit\Cannibalization;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

/*
 * One grouped query over core tables rather than N per-post lookups.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */

/**
 * Finds pages on this site that a given page could reasonably link to.
 *
 * This exists so a model is never asked which pages exist. It does not know,
 * and a suggestion to link to a page that was never written is worse than no
 * suggestion at all — an editor would have to check every one. Candidates are
 * selected here, from the site's own content, by word overlap; the model is
 * only asked to judge which of them genuinely fit and how to phrase the link.
 *
 * Pages the source already links to are excluded, so a suggestion is never
 * something already done.
 */
final class Candidates {

	/**
	 * How many published posts to consider.
	 *
	 * Titles are scored in PHP over a capped set, which holds to several
	 * thousand posts. A site larger than that wants a real index — switch to a
	 * FULLTEXT match if this ceiling is ever reached in practice.
	 */
	private const POOL = 1000;

	/**
	 * Pages the given post could link to, best match first.
	 *
	 * @param int    $post_id Source post.
	 * @param string $content Source content, so unsaved edits are considered.
	 * @param int    $limit   Maximum candidates.
	 *
	 * @return array<int, array{id: int, title: string, url: string, score: int, shared: string[]}>
	 */
	public static function for_post( int $post_id, string $content = '', int $limit = 12 ): array {
		global $wpdb;

		$source = self::tokens_for( $post_id, $content );

		if ( count( $source ) < 2 ) {
			return array();
		}

		$types = Meta::post_types();

		if ( ! $types ) {
			return array();
		}

		$tokens = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		$rows = (array) $wpdb->get_results(
			// The placeholder count is built from the post type list, so it is
			// correct at run time even though it cannot be counted statically.
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare(
				"SELECT ID, post_title, post_excerpt
				FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_type IN ({$tokens}) AND ID <> %d
				ORDER BY post_modified DESC
				LIMIT %d",
				...array_merge( $types, array( $post_id, self::POOL ) )
			)
		);

		$already = self::already_linked( $post_id );
		$scored  = array();

		foreach ( $rows as $row ) {
			$id = (int) $row->ID;

			if ( in_array( $id, $already, true ) ) {
				continue;
			}

			$shared = array_values( array_intersect( $source, Cannibalization::tokens( (string) $row->post_title ) ) );

			// A single shared word is usually coincidence.
			if ( count( $shared ) < 2 ) {
				continue;
			}

			$scored[] = array(
				'id'      => $id,
				'title'   => (string) $row->post_title,
				'url'     => (string) get_permalink( $id ),
				'excerpt' => Analyzer::to_text( (string) $row->post_excerpt ),
				'score'   => count( $shared ),
				'shared'  => $shared,
			);
		}

		usort(
			$scored,
			static fn ( array $a, array $b ): int => $b['score'] <=> $a['score']
		);

		return array_slice( $scored, 0, max( 1, $limit ) );
	}

	/**
	 * Significant words describing a post.
	 *
	 * The title and focus keyphrase are weighted by being included at all; the
	 * body only contributes its most frequent words, so a passing mention does
	 * not make a page look relevant to everything.
	 *
	 * @param int    $post_id Post id.
	 * @param string $content Content override.
	 *
	 * @return string[]
	 */
	public static function tokens_for( int $post_id, string $content = '' ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$body = '' !== $content ? $content : (string) $post->post_content;

		$tokens = array_merge(
			Cannibalization::tokens( (string) $post->post_title ),
			Cannibalization::tokens( (string) Meta::get( $post_id, Meta::FOCUS_KEYWORD ) ),
			self::frequent( Analyzer::to_text( $body ) )
		);

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * The most repeated significant words in a block of text.
	 *
	 * @param string $text  Plain text.
	 * @param int    $limit How many to keep.
	 *
	 * @return string[]
	 */
	private static function frequent( string $text, int $limit = 15 ): array {
		$counts = array();

		foreach ( Cannibalization::tokens_all( $text ) as $word ) {
			$counts[ $word ] = ( $counts[ $word ] ?? 0 ) + 1;
		}

		// A word used once is not what the page is about.
		$counts = array_filter( $counts, static fn ( int $n ): bool => $n > 1 );

		arsort( $counts );

		return array_slice( array_keys( $counts ), 0, $limit );
	}

	/**
	 * Post ids the source already links to.
	 *
	 * @param int $post_id Source post.
	 *
	 * @return int[]
	 */
	private static function already_linked( int $post_id ): array {
		$ids = array();

		foreach ( Links::outgoing( $post_id ) as $link ) {
			$target = (int) $link->target_id;

			if ( $target > 0 ) {
				$ids[] = $target;
			}
		}

		return $ids;
	}
}
