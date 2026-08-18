<?php
/**
 * XML sitemap.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Sitemap;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\SEO\Meta;
use WP_Post;
use WP_Sitemaps_Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Extends the sitemap WordPress already ships rather than publishing a second one.
 *
 * Core has generated `wp-sitemap.xml` since 5.5, with an index, per-post-type
 * and per-taxonomy pages, pagination and a complete filter API. Emitting a
 * rival sitemap from the same site would mean two sets of URLs disagreeing
 * with each other, so this module shapes the core one instead: it removes
 * URLs that should not be indexed, adds the `lastmod` core omits, and honours
 * the plugin's settings.
 */
final class Sitemap {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wp_sitemaps_enabled', array( self::class, 'enabled' ) );

		if ( ! Settings::enabled( 'enable_sitemap' ) ) {
			return;
		}

		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( self::class, 'taxonomy_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_entry', array( self::class, 'post_entry' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( self::class, 'providers' ), 10, 2 );
	}

	/**
	 * Master switch.
	 *
	 * @param bool $enabled Whether core would serve a sitemap.
	 */
	public static function enabled( bool $enabled ): bool {
		if ( ! Settings::enabled( 'enable_seo' ) || ! Settings::enabled( 'enable_sitemap' ) ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * The sitemap index URL, or an empty string when disabled.
	 */
	public static function index_url(): string {
		if ( ! Settings::enabled( 'enable_seo' ) || ! Settings::enabled( 'enable_sitemap' ) ) {
			return '';
		}

		$sitemaps = wp_sitemaps_get_server();

		return $sitemaps ? (string) $sitemaps->index->get_index_url() : '';
	}

	/**
	 * Keep noindexed content out of the sitemap.
	 *
	 * @param array<string, mixed> $args      Query arguments.
	 * @param string               $post_type Post type being listed.
	 *
	 * @return array<string, mixed>
	 */
	public static function query_args( array $args, string $post_type ): array {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed meta key; core caches the sitemap query.
		$args['meta_query'] = Meta::exclude_noindex_clause();

		/**
		 * Filters the sitemap query arguments for a post type.
		 *
		 * @param array  $args      Query arguments.
		 * @param string $post_type Post type slug.
		 */
		return (array) apply_filters( 'wpcseo_sitemap_query_args', $args, $post_type );
	}

	/**
	 * Keep noindexed terms out of the taxonomy sitemap.
	 *
	 * The same reasoning as for posts: an archive the site asks search engines
	 * to ignore should not also be submitted to them for crawling.
	 *
	 * @param array<string, mixed> $args     Term query arguments.
	 * @param string               $taxonomy Taxonomy being listed.
	 *
	 * @return array<string, mixed>
	 */
	public static function taxonomy_query_args( array $args, string $taxonomy ): array {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed meta key; core caches the sitemap query.
		$args['meta_query'] = Meta::exclude_noindex_clause();

		/**
		 * Filters the sitemap term query arguments for a taxonomy.
		 *
		 * @param array  $args     Term query arguments.
		 * @param string $taxonomy Taxonomy slug.
		 */
		return (array) apply_filters( 'wpcseo_sitemap_taxonomy_query_args', $args, $taxonomy );
	}

	/**
	 * Add `lastmod`, which core does not output.
	 *
	 * @param array<string, mixed> $entry Sitemap entry.
	 * @param WP_Post              $post  Post being listed.
	 *
	 * @return array<string, mixed>
	 */
	public static function post_entry( array $entry, WP_Post $post ): array {
		$modified = get_post_modified_time( DATE_W3C, true, $post );

		if ( is_string( $modified ) && '' !== $modified ) {
			$entry['lastmod'] = $modified;
		}

		/**
		 * Filters one sitemap entry.
		 *
		 * @param array   $entry Sitemap entry.
		 * @param WP_Post $post  Post being listed.
		 */
		return (array) apply_filters( 'wpcseo_sitemap_entry', $entry, $post );
	}

	/**
	 * Post types included in the sitemap.
	 *
	 * @param array<string, \WP_Post_Type> $post_types Post types keyed by slug.
	 *
	 * @return array<string, \WP_Post_Type>
	 */
	public static function post_types( array $post_types ): array {
		/**
		 * Filters the post types listed in the sitemap.
		 *
		 * Unset a key to leave that post type out entirely.
		 *
		 * @param array $post_types Post types keyed by slug.
		 */
		return (array) apply_filters( 'wpcseo_sitemap_post_types', $post_types );
	}

	/**
	 * Taxonomies included in the sitemap.
	 *
	 * @param array<string, \WP_Taxonomy> $taxonomies Taxonomies keyed by slug.
	 *
	 * @return array<string, \WP_Taxonomy>
	 */
	public static function taxonomies( array $taxonomies ): array {
		if ( ! Settings::enabled( 'sitemap_taxonomies' ) ) {
			return array();
		}

		/**
		 * Filters the taxonomies listed in the sitemap.
		 *
		 * @param array $taxonomies Taxonomies keyed by slug.
		 */
		return (array) apply_filters( 'wpcseo_sitemap_taxonomies', $taxonomies );
	}

	/**
	 * Drop the author sitemap when it is switched off.
	 *
	 * @param WP_Sitemaps_Provider|false $provider Provider instance.
	 * @param string                     $name     Provider name.
	 *
	 * @return WP_Sitemaps_Provider|false
	 */
	public static function providers( $provider, string $name ) {
		if ( 'users' === $name && ! Settings::enabled( 'sitemap_authors' ) ) {
			return false;
		}

		return $provider;
	}
}
