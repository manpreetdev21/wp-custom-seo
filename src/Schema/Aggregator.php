<?php
/**
 * Site-wide schema aggregation.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Entities\Registry;
use WPCustomSeo\Schema\Graph\Pieces;
use WPCustomSeo\SEO\Meta;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Builds paginated schema for public content.
 *
 * Only content that is already publicly readable is included: published, not
 * password protected, in a viewable post type, and not marked noindex. A page
 * the site asks search engines to ignore is not republished here either.
 */
final class Aggregator {

	private const MAX_BATCH = 500;

	/**
	 * Post types exposed by the API.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name || ! is_post_type_viewable( $type ) ) {
				continue;
			}

			$types[] = $type->name;
		}

		/**
		 * Filters the post types exposed by the schema API.
		 *
		 * @param string[] $types Post type slugs.
		 */
		return array_values( (array) apply_filters( 'wpcseo_schema_api_post_types', $types ) );
	}

	/**
	 * How many posts belong on one page for a post type.
	 *
	 * @param string $post_type Post type slug.
	 */
	public static function batch( string $post_type ): int {
		$batch = (int) Settings::get( 'schema_api_batch', 100 );

		/**
		 * Filters the batch size for a post type.
		 *
		 * Large catalogues such as products usually want a smaller batch than
		 * a blog does; return a different number for those post types here.
		 *
		 * @param int    $batch     Configured batch size.
		 * @param string $post_type Post type slug.
		 */
		$batch = (int) apply_filters( 'wpcseo_schema_api_batch', $batch, $post_type );

		return max( 1, min( self::MAX_BATCH, $batch ) );
	}

	/**
	 * Query arguments for the public subset of a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $page      Page number, from 1.
	 *
	 * @return array<string, mixed>
	 */
	private static function query_args( string $post_type, int $page ): array {
		return array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => self::batch( $post_type ),
			'paged'                  => max( 1, $page ),
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed meta key, and the result is cached.
			'meta_query'             => Meta::exclude_noindex_clause(),
		);
	}

	/**
	 * One page of aggregated schema.
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $page      Page number, from 1.
	 *
	 * @return array<string, mixed>
	 */
	public static function page( string $post_type, int $page ): array {
		$page = max( 1, $page );
		$key  = Cache::key( 'page', $post_type, (string) $page, (string) self::batch( $post_type ) );

		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			$cached['cached'] = true;

			return $cached;
		}

		$query = new WP_Query( self::query_args( $post_type, $page ) );
		$graph = Pieces::for_posts( $query->posts );

		$result = array(
			'post_type'  => $post_type,
			'page'       => $page,
			'pages'      => max( 1, (int) $query->max_num_pages ),
			'total'      => (int) $query->found_posts,
			'batch'      => self::batch( $post_type ),
			'cached'     => false,
			'schema'     => $graph->to_array(),
			'validation' => Validator::validate_nodes( $graph->nodes() ),
		);

		Cache::set( $key, $result );

		return $result;
	}

	/**
	 * How many posts and pages a post type has.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array{total: int, pages: int, batch: int}
	 */
	public static function counts( string $post_type ): array {
		$key    = Cache::key( 'count', $post_type, (string) self::batch( $post_type ) );
		$cached = Cache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$args                   = self::query_args( $post_type, 1 );
		$args['fields']         = 'ids';
		$args['posts_per_page'] = 1;

		$query = new WP_Query( $args );
		$batch = self::batch( $post_type );
		$total = (int) $query->found_posts;

		$counts = array(
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / $batch ) ),
			'batch' => $batch,
		);

		Cache::set( $key, $counts );

		return $counts;
	}

	/**
	 * The API index.
	 *
	 * @return array<string, mixed>
	 */
	public static function index(): array {
		$types = array();

		foreach ( self::post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );
			$counts = self::counts( $post_type );

			$types[] = array(
				'post_type' => $post_type,
				'label'     => $object ? (string) $object->labels->name : $post_type,
				'total'     => $counts['total'],
				'pages'     => $counts['pages'],
				'batch'     => $counts['batch'],
				'href'      => rest_url( 'wp-custom-seo/v1/schema/' . $post_type ),
			);
		}

		return array(
			'site'       => array(
				'url'      => home_url( '/' ),
				'name'     => (string) get_bloginfo( 'name' ),
				'language' => Registry::language(),
			),
			'entities'   => array(
				'organization' => rest_url( 'wp-custom-seo/v1/schema/entity/organization' ),
				'website'      => rest_url( 'wp-custom-seo/v1/schema/entity/website' ),
			),
			'post_types' => $types,
			'sitemap'    => rest_url( 'wp-custom-seo/v1/schema/sitemap' ),
		);
	}

	/**
	 * A flat listing of every aggregation page.
	 *
	 * @return array<string, mixed>
	 */
	public static function sitemap(): array {
		$pages = array();

		foreach ( self::post_types() as $post_type ) {
			$counts = self::counts( $post_type );

			for ( $page = 1; $page <= $counts['pages']; $page++ ) {
				$pages[] = array(
					'post_type' => $post_type,
					'page'      => $page,
					'href'      => rest_url( 'wp-custom-seo/v1/schema/' . $post_type . '/' . $page ),
				);
			}
		}

		return array(
			'generated' => gmdate( DATE_W3C ),
			'pages'     => $pages,
		);
	}

	/**
	 * Resolve a site entity by its identifier path.
	 *
	 * Person entities are exposed only for users who have published content,
	 * so the endpoint cannot be used to enumerate accounts.
	 *
	 * @param string $path Identifier path, e.g. `organization` or `person/12`.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function entity( string $path ): ?array {
		$path = trim( $path, '/' );

		if ( 'organization' === $path ) {
			return Registry::organization();
		}

		if ( 'website' === $path ) {
			return Registry::website();
		}

		if ( 'logo' === $path ) {
			return Registry::image( (string) Settings::get( 'schema_org_logo', '' ), Registry::id( 'logo' ) );
		}

		if ( preg_match( '#^person/(\d+)$#', $path, $matches ) ) {
			$user_id = (int) $matches[1];

			return self::has_public_posts( $user_id ) ? Registry::person( $user_id ) : null;
		}

		return null;
	}

	/**
	 * Whether a user has published content in any exposed post type.
	 *
	 * @param int $user_id User id.
	 */
	private static function has_public_posts( int $user_id ): bool {
		foreach ( self::post_types() as $post_type ) {
			if ( (int) count_user_posts( $user_id, $post_type, true ) > 0 ) {
				return true;
			}
		}

		return false;
	}
}
