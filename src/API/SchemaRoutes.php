<?php
/**
 * Public schema aggregation endpoints.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\API;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Schema\Aggregator;
use WP_Error;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes aggregated structured data for public content.
 *
 * These routes are unauthenticated by design: they republish schema that is
 * already present in the HTML of publicly readable pages. Nothing private
 * passes through them — the aggregator filters to published, unprotected,
 * indexable content in viewable post types before a graph is ever built.
 *
 * More specific patterns are registered first so a post type slug cannot
 * shadow `entity` or `sitemap`.
 */
final class SchemaRoutes {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register' ) );
	}

	/**
	 * Register routes.
	 */
	public static function register(): void {
		$readable = WP_REST_Server::READABLE;
		$allowed  = array( self::class, 'is_enabled' );

		register_rest_route(
			Routes::NAMESPACE,
			'/schema',
			array(
				'methods'             => $readable,
				'callback'            => array( self::class, 'index' ),
				'permission_callback' => $allowed,
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/schema/sitemap',
			array(
				'methods'             => $readable,
				'callback'            => array( self::class, 'sitemap' ),
				'permission_callback' => $allowed,
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/schema/entity/(?P<entity>[a-z]+(?:/\d+)?)',
			array(
				'methods'             => $readable,
				'callback'            => array( self::class, 'entity' ),
				'permission_callback' => $allowed,
				'args'                => array(
					'entity' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static fn ( $value ): string => preg_replace( '#[^a-z0-9/]#', '', (string) $value ) ?? '',
					),
				),
			)
		);

		register_rest_route(
			Routes::NAMESPACE,
			'/schema/(?P<post_type>[a-z][a-z0-9_-]*)(?:/(?P<page>\d+))?',
			array(
				'methods'             => $readable,
				'callback'            => array( self::class, 'collection' ),
				'permission_callback' => $allowed,
				'args'                => array(
					'post_type' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'page'      => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Whether the aggregation API is switched on.
	 *
	 * @return true|WP_Error
	 */
	public static function is_enabled(): bool|WP_Error {
		if ( ! Settings::enabled( 'enable_schema' ) || ! Settings::enabled( 'schema_api_enabled' ) ) {
			return new WP_Error(
				'wpcseo_schema_api_disabled',
				__( 'The schema API is turned off in the plugin settings.', 'wp-custom-seo' ),
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/**
	 * The API index.
	 */
	public static function index(): WP_REST_Response {
		return self::respond( Aggregator::index() );
	}

	/**
	 * A flat listing of every aggregation page.
	 */
	public static function sitemap(): WP_REST_Response {
		return self::respond( Aggregator::sitemap() );
	}

	/**
	 * One site entity.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function entity( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$node = Aggregator::entity( (string) $request['entity'] );

		if ( null === $node ) {
			return new WP_Error(
				'wpcseo_entity_not_found',
				__( 'No such entity.', 'wp-custom-seo' ),
				array( 'status' => 404 )
			);
		}

		return self::respond(
			array(
				'@context' => \WPCustomSeo\Schema\Graph\Graph::CONTEXT,
				'@graph'   => array( $node ),
			)
		);
	}

	/**
	 * One page of a post type.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function collection( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_type = (string) $request['post_type'];

		if ( ! in_array( $post_type, Aggregator::post_types(), true ) ) {
			return new WP_Error(
				'wpcseo_post_type_not_found',
				__( 'That post type is not exposed by the schema API.', 'wp-custom-seo' ),
				array( 'status' => 404 )
			);
		}

		$page   = max( 1, (int) $request['page'] );
		$counts = Aggregator::counts( $post_type );

		if ( $page > $counts['pages'] ) {
			return new WP_Error(
				'wpcseo_page_out_of_range',
				__( 'That page does not exist.', 'wp-custom-seo' ),
				array( 'status' => 404 )
			);
		}

		$result   = Aggregator::page( $post_type, $page );
		$response = self::respond( $result );

		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		if ( $page < $result['pages'] ) {
			$response->link_header( 'next', rest_url( Routes::NAMESPACE . '/schema/' . $post_type . '/' . ( $page + 1 ) ) );
		}

		if ( $page > 1 ) {
			$response->link_header( 'prev', rest_url( Routes::NAMESPACE . '/schema/' . $post_type . '/' . ( $page - 1 ) ) );
		}

		return $response;
	}

	/**
	 * Wrap a payload, letting caches reuse it for a short while.
	 *
	 * @param array<string, mixed> $payload Response body.
	 */
	private static function respond( array $payload ): WP_REST_Response {
		$response = new WP_REST_Response( $payload );

		$response->header( 'Cache-Control', 'public, max-age=600' );

		return $response;
	}
}
