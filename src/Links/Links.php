<?php
/**
 * Internal link graph.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Links;

use WPCustomSeo\Database\Tables;
use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

/*
 * Every query below reads or writes the plugin's own link table, for which
 * there is no WordPress API. Table names come from constants, never from
 * input, and every value is bound through a placeholder — including the
 * variable-length IN() lists, which are built from generated `%d` tokens and
 * passed as separate arguments. Stated once here rather than repeated as a
 * comment above fifteen queries.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
 */

/**
 * Stores and queries which content links to which.
 *
 * A link is recorded as one of three kinds, and the distinction matters:
 *
 * - `internal`   resolves to a post on this site
 * - `unresolved` points at this site but not at any post
 * - `external`   points somewhere else
 *
 * An unresolved link is not called broken. It may be a category archive, a
 * date archive or a page served by something other than a post, all of which
 * are perfectly valid. Calling it broken without checking would be a claim
 * the plugin cannot support.
 */
final class Links {

	public const INTERNAL = 'internal';

	public const UNRESOLVED = 'unresolved';

	public const EXTERNAL = 'external';

	/**
	 * Replace every link recorded for a post.
	 *
	 * @param int                                                                          $source_id Post id.
	 * @param array<int, array{url: string, anchor: string, target_id: int, type: string}> $links   Parsed links.
	 */
	public static function store( int $source_id, array $links ): void {
		global $wpdb;

		self::delete_for( $source_id );

		foreach ( $links as $link ) {
			$wpdb->insert(
				Tables::links(),
				array(
					'source_id' => $source_id,
					'target_id' => (int) $link['target_id'],
					'url'       => mb_substr( (string) $link['url'], 0, 255 ),
					'anchor'    => mb_substr( (string) $link['anchor'], 0, 255 ),
					'type'      => (string) $link['type'],
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Forget everything recorded for a post.
	 *
	 * @param int $source_id Post id.
	 */
	public static function delete_for( int $source_id ): void {
		global $wpdb;

		$wpdb->delete( Tables::links(), array( 'source_id' => $source_id ), array( '%d' ) );
	}

	/**
	 * Forget a post entirely, as source and as target.
	 *
	 * @param int $post_id Post id.
	 */
	public static function forget( int $post_id ): void {
		global $wpdb;

		self::delete_for( $post_id );

		$wpdb->delete( Tables::links(), array( 'target_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Links pointing at a post.
	 *
	 * @param int $target_id Post id.
	 *
	 * @return object[]
	 */
	public static function incoming( int $target_id ): array {
		global $wpdb;

		$table = Tables::links();
		$posts = $wpdb->posts;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, p.post_title, p.post_type
				FROM {$table} l
				INNER JOIN {$posts} p ON p.ID = l.source_id
				WHERE l.target_id = %d AND p.post_status = 'publish'
				ORDER BY p.post_title ASC",
				$target_id
			)
		);
	}

	/**
	 * Links leaving a post.
	 *
	 * @param int $source_id Post id.
	 *
	 * @return object[]
	 */
	public static function outgoing( int $source_id ): array {
		global $wpdb;

		$table = Tables::links();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE source_id = %d ORDER BY type ASC, url ASC",
				$source_id
			)
		);
	}

	/**
	 * How many published posts link to each of a set of posts.
	 *
	 * @param int[] $post_ids Post ids.
	 *
	 * @return array<int, int> Counts keyed by post id.
	 */
	public static function incoming_counts( array $post_ids ): array {
		global $wpdb;

		$post_ids = array_values( array_filter( array_map( 'intval', $post_ids ) ) );

		if ( ! $post_ids ) {
			return array();
		}

		$table  = Tables::links();
		$posts  = $wpdb->posts;
		$tokens = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.target_id, COUNT(DISTINCT l.source_id) AS total
				FROM {$table} l
				INNER JOIN {$posts} p ON p.ID = l.source_id
				WHERE p.post_status = 'publish' AND l.source_id <> l.target_id AND l.target_id IN ({$tokens})
				GROUP BY l.target_id",
				...$post_ids
			)
		);

		$counts = array_fill_keys( $post_ids, 0 );

		foreach ( $rows as $row ) {
			$counts[ (int) $row->target_id ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * The most linked-to posts.
	 *
	 * @param int $limit Rows to return.
	 *
	 * @return object[]
	 */
	public static function most_linked( int $limit = 10 ): array {
		global $wpdb;

		$table = Tables::links();
		$posts = $wpdb->posts;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.target_id, COUNT(DISTINCT l.source_id) AS total, t.post_title
				FROM {$table} l
				INNER JOIN {$posts} p ON p.ID = l.source_id AND p.post_status = 'publish'
				INNER JOIN {$posts} t ON t.ID = l.target_id AND t.post_status = 'publish'
				WHERE l.target_id > 0 AND l.source_id <> l.target_id
				GROUP BY l.target_id, t.post_title
				ORDER BY total DESC
				LIMIT %d",
				max( 1, $limit )
			)
		);
	}

	/**
	 * Internal links that do not resolve to a post.
	 *
	 * @param int $limit Rows to return.
	 *
	 * @return object[]
	 */
	public static function unresolved( int $limit = 50 ): array {
		global $wpdb;

		$table = Tables::links();
		$posts = $wpdb->posts;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, p.post_title
				FROM {$table} l
				INNER JOIN {$posts} p ON p.ID = l.source_id AND p.post_status = 'publish'
				WHERE l.type = %s
				ORDER BY l.url ASC
				LIMIT %d",
				self::UNRESOLVED,
				max( 1, $limit )
			)
		);
	}

	/**
	 * Post ids that appear in a navigation menu.
	 *
	 * A page reachable from the main menu is discoverable however few posts
	 * link to it in prose, so it must not be reported as orphaned.
	 *
	 * @return int[]
	 */
	public static function menu_linked_ids(): array {
		global $wpdb;

		$ids = (array) $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_object_id'"
		);

		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Published posts with fewer incoming links than a threshold.
	 *
	 * @param int      $threshold  Maximum incoming links to qualify.
	 * @param string[] $post_types Post types to consider.
	 * @param int      $limit      Rows to return.
	 *
	 * @return object[]
	 */
	public static function under_linked( int $threshold, array $post_types, int $limit = 100 ): array {
		global $wpdb;

		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );

		if ( ! $post_types ) {
			return array();
		}

		$table    = Tables::links();
		$posts    = $wpdb->posts;
		$types    = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$menu_ids = self::menu_linked_ids();
		$front    = (int) get_option( 'page_on_front' );
		$blog     = (int) get_option( 'page_for_posts' );

		$excluded = array_values( array_unique( array_filter( array_merge( $menu_ids, array( $front, $blog ) ) ) ) );
		$exclude  = $excluded ? ' AND p.ID NOT IN (' . implode( ',', array_fill( 0, count( $excluded ), '%d' ) ) . ')' : '';

		$args = array_merge( $post_types, $excluded, array( $threshold, max( 1, $limit ) ) );

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type, COUNT(DISTINCT l.source_id) AS incoming
				FROM {$posts} p
				LEFT JOIN {$table} l
					ON l.target_id = p.ID
					AND l.source_id <> p.ID
					AND l.source_id IN ( SELECT ID FROM {$posts} WHERE post_status = 'publish' )
				WHERE p.post_status = 'publish'
					AND p.post_type IN ({$types})
					{$exclude}
				GROUP BY p.ID, p.post_title, p.post_type
				HAVING incoming <= %d
				ORDER BY incoming ASC, p.post_title ASC
				LIMIT %d",
				...$args
			)
		);
	}

	/**
	 * How many links have been recorded in total.
	 */
	public static function total(): int {
		global $wpdb;

		$table = Tables::links();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Post types the link graph covers.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		/**
		 * Filters the post types included in the link graph.
		 *
		 * @param string[] $types Post type slugs.
		 */
		return array_values( (array) apply_filters( 'wpcseo_link_post_types', Meta::post_types() ) );
	}
}
