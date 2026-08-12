<?php
/**
 * Link extraction and rebuilding.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Links;

use WPCustomSeo\Core\Settings;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Parses content for links and keeps the graph current.
 *
 * A post is scanned when it is saved, which is the only moment its links can
 * change. A full rebuild runs in cron batches rather than in one request,
 * because resolving a URL to a post costs a query and a large site has tens of
 * thousands of links.
 */
final class Scanner {

	public const REBUILD_HOOK = 'wpcseo_rebuild_links';

	public const PROGRESS_OPTION = 'wpcseo_link_rebuild_offset';

	private const BATCH = 50;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( self::REBUILD_HOOK, array( self::class, 'run_batch' ) );

		if ( ! Settings::enabled( 'enable_link_graph' ) ) {
			return;
		}

		add_action( 'save_post', array( self::class, 'on_save' ), 20, 2 );
		add_action( 'deleted_post', array( self::class, 'on_delete' ) );
	}

	/**
	 * Rescan a post when it is saved.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 */
	public static function on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, Links::post_types(), true ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			Links::delete_for( $post_id );

			return;
		}

		self::scan( $post );
	}

	/**
	 * Drop a deleted post from the graph.
	 *
	 * @param int $post_id Post id.
	 */
	public static function on_delete( int $post_id ): void {
		Links::forget( $post_id );
	}

	/**
	 * Parse and store the links in one post.
	 *
	 * @param WP_Post $post Post to scan.
	 *
	 * @return int Number of links recorded.
	 */
	public static function scan( WP_Post $post ): int {
		$links = self::extract( (string) $post->post_content, (int) $post->ID );

		Links::store( (int) $post->ID, $links );

		return count( $links );
	}

	/**
	 * Pull the links out of a block of HTML.
	 *
	 * @param string $content   Post content.
	 * @param int    $source_id Post the content belongs to.
	 *
	 * @return array<int, array{url: string, anchor: string, target_id: int, type: string}>
	 */
	public static function extract( string $content, int $source_id = 0 ): array {
		$content = do_shortcode( $content );

		preg_match_all(
			'#<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		$home  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$links = array();
		$seen  = array();

		foreach ( $matches as $match ) {
			$url = trim( html_entity_decode( $match[1], ENT_QUOTES ) );

			if ( '' === $url || preg_match( '#^(\#|mailto:|tel:|javascript:|data:)#i', $url ) ) {
				continue;
			}

			$host     = (string) wp_parse_url( $url, PHP_URL_HOST );
			$internal = '' === $host || self::same_host( $host, $home );

			if ( $internal ) {
				// Rebuild against the site's own scheme and host, keeping the
				// URL's own path. This normalises a www variant to the host
				// WordPress actually runs on, and reads a root-relative href
				// as relative to the domain root, which is what a browser does.
				// The path must not be passed through home_url(), which would
				// prepend the install directory a second time.
				$parts     = (array) wp_parse_url( $url );
				$path      = (string) ( $parts['path'] ?? '/' );
				$query     = isset( $parts['query'] ) ? '?' . (string) $parts['query'] : '';
				$target_id = (int) url_to_postid( self::site_root() . $path . $query );
				$type      = $target_id > 0 ? Links::INTERNAL : Links::UNRESOLVED;
			} else {
				$target_id = 0;
				$type      = Links::EXTERNAL;
			}

			if ( $target_id > 0 && $target_id === $source_id ) {
				continue;
			}

			// A resolved link is deduplicated by the post it reaches, so the
			// same destination written three different ways is recorded once.
			$key = $target_id > 0 ? 'post:' . $target_id : $type . '|' . $url;

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;

			$links[] = array(
				'url'       => $url,
				'anchor'    => trim( wp_strip_all_tags( $match[2] ) ),
				'target_id' => $target_id,
				'type'      => $type,
			);
		}

		/**
		 * Filters the links extracted from a post.
		 *
		 * @param array  $links     Extracted links.
		 * @param string $content   Post content.
		 * @param int    $source_id Post id.
		 */
		return (array) apply_filters( 'wpcseo_extracted_links', $links, $content, $source_id );
	}

	/**
	 * Scheme and host of this site, with no trailing slash.
	 */
	private static function site_root(): string {
		$parts = (array) wp_parse_url( home_url( '/' ) );

		return ( (string) ( $parts['scheme'] ?? 'https' ) ) . '://' . (string) ( $parts['host'] ?? '' );
	}

	/**
	 * Compare hosts, ignoring a leading www.
	 *
	 * @param string $a First host.
	 * @param string $b Second host.
	 */
	private static function same_host( string $a, string $b ): bool {
		$strip = static fn ( string $host ): string => (string) preg_replace( '/^www\./i', '', strtolower( $host ) );

		return $strip( $a ) === $strip( $b );
	}

	/**
	 * Start a full rebuild.
	 */
	public static function start_rebuild(): void {
		update_option( self::PROGRESS_OPTION, 0, false );

		if ( ! wp_next_scheduled( self::REBUILD_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::REBUILD_HOOK );
		}
	}

	/**
	 * Whether a rebuild is under way.
	 */
	public static function is_rebuilding(): bool {
		return false !== get_option( self::PROGRESS_OPTION, false );
	}

	/**
	 * How far a rebuild has progressed.
	 *
	 * @return array{done: int, total: int}
	 */
	public static function progress(): array {
		return array(
			'done'  => (int) get_option( self::PROGRESS_OPTION, 0 ),
			'total' => self::countable(),
		);
	}

	/**
	 * How many published posts the rebuild will visit.
	 */
	public static function countable(): int {
		$query = new WP_Query(
			array(
				'post_type'      => Links::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Scan one batch, then queue the next.
	 *
	 * Kept to a fixed batch so a large site rebuilds over several cron runs
	 * instead of exhausting one request.
	 */
	public static function run_batch(): void {
		$offset = (int) get_option( self::PROGRESS_OPTION, 0 );

		$query = new WP_Query(
			array(
				'post_type'              => Links::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => self::BATCH,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'no_found_rows'          => true,
			)
		);

		if ( ! $query->posts ) {
			delete_option( self::PROGRESS_OPTION );

			/**
			 * Fires when a full link rebuild finishes.
			 */
			do_action( 'wpcseo_links_rebuilt' );

			return;
		}

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				self::scan( $post );
			}
		}

		update_option( self::PROGRESS_OPTION, $offset + count( $query->posts ), false );

		wp_schedule_single_event( time() + 5, self::REBUILD_HOOK );
	}
}
