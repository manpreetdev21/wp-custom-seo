<?php
/**
 * REST API routes.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\API;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Core\Settings;
use WPCustomSeo\Links\Links;
use WPCustomSeo\Redirects\NotFound;
use WPCustomSeo\Redirects\Redirects;
use WPCustomSeo\Schema\Graph\Pieces;
use WPCustomSeo\SearchConsole\Account;
use WPCustomSeo\SearchConsole\Performance;
use WPCustomSeo\Schema\Validator;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Frontend;
use WPCustomSeo\SEO\Meta;
use WPCustomSeo\SEO\Templates;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's REST endpoints.
 */
final class Routes {

	public const NAMESPACE = 'wp-custom-seo/v1';

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
		register_rest_route(
			self::NAMESPACE,
			'/analysis/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'analysis' ),
					'permission_callback' => array( self::class, 'can_edit_post' ),
					'args'                => self::analysis_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'analysis' ),
					'permission_callback' => array( self::class, 'can_edit_post' ),
					'args'                => self::analysis_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/schema/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'schema' ),
				'permission_callback' => array( self::class, 'can_edit_post' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/redirects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'redirects' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => self::list_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/performance/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'performance' ),
				'permission_callback' => array( self::class, 'can_edit_post' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'days'    => array(
						'type'              => 'integer',
						'default'           => 28,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/links/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'links' ),
				'permission_callback' => array( self::class, 'can_edit_post' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/404s',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'not_found' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => self::list_args(),
			)
		);
	}

	/**
	 * Return the schema graph and its validation report for one post.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function schema( WP_REST_Request $request ): WP_REST_Response {
		$graph = Pieces::for_post( (int) $request['post_id'] );

		return new WP_REST_Response(
			array(
				'graph'  => $graph->to_array(),
				'issues' => Validator::validate( $graph ),
			)
		);
	}

	/**
	 * Shared paging and search arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function list_args(): array {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Only users who can manage the plugin may read these lists.
	 *
	 * @return true|WP_Error
	 */
	public static function can_manage(): bool|WP_Error {
		if ( ! Capabilities::can_manage() ) {
			return new WP_Error(
				'wpcseo_forbidden',
				__( 'You are not allowed to do that.', 'wp-custom-seo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * List redirect rules.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function redirects( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'page'     => (int) $request['page'],
			'per_page' => (int) $request['per_page'],
			'search'   => (string) $request['search'],
		);

		$response = new WP_REST_Response(
			array(
				'total'     => Redirects::count( $args['search'] ),
				'redirects' => Redirects::all( $args ),
			)
		);

		$response->header( 'X-WP-Total', (string) Redirects::count( $args['search'] ) );

		return $response;
	}

	/**
	 * Incoming and outgoing links for one post.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function links( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['post_id'];

		return new WP_REST_Response(
			array(
				'incoming' => Links::incoming( $post_id ),
				'outgoing' => Links::outgoing( $post_id ),
			)
		);
	}

	/**
	 * What Search Console reports for one post.
	 *
	 * Every unavailable case returns 200 with a reason rather than an error
	 * status. "Not connected" and "this page has no data yet" are ordinary
	 * answers about a page, not failures of the request, and an editor panel
	 * that shows a red error for them would be lying about what went wrong.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function performance( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request['post_id'];
		$post    = get_post( $post_id );
		$days    = in_array( (int) $request['days'], Performance::PERIODS, true ) ? (int) $request['days'] : 28;

		$unavailable = static fn ( string $reason ): WP_REST_Response => new WP_REST_Response(
			array(
				'available' => false,
				'reason'    => $reason,
				'rows'      => array(),
			)
		);

		if ( ! Account::is_connected() || '' === Performance::property() ) {
			return $unavailable( __( 'Search Console is not connected, so there is nothing to show here. An administrator can connect it under SEO → Search Performance.', 'wp-custom-seo' ) );
		}

		if ( ! $post || 'publish' !== $post->post_status ) {
			// An unpublished page has no URL in the index, so asking about it
			// would return an empty result that looks like "no traffic".
			return $unavailable( __( 'This content is not published yet, so Google has nothing to report about it.', 'wp-custom-seo' ) );
		}

		$rows = Performance::for_url( (string) get_permalink( $post ), $days );

		if ( $rows instanceof WP_Error ) {
			return $unavailable( $rows->get_error_message() );
		}

		$keyword = trim( (string) Meta::get( $post_id, Meta::FOCUS_KEYWORD ) );

		return new WP_REST_Response(
			array(
				'available' => true,
				'days'      => $days,
				'range'     => Performance::range( $days ),
				'url'       => (string) get_permalink( $post ),
				'keyword'   => $keyword,
				// Whether the phrase this page targets is among the phrases it
				// is actually shown for. Reported as an observation: the query
				// list is the top rows only, so its absence is not proof.
				'matched'   => '' !== $keyword && self::mentions( $rows, $keyword ),
				'rows'      => $rows,
			)
		);
	}

	/**
	 * Whether any reported query contains the focus keyphrase.
	 *
	 * @param array<int, array<string, mixed>> $rows    Reported rows.
	 * @param string                           $keyword Focus keyphrase.
	 */
	private static function mentions( array $rows, string $keyword ): bool {
		$needle = mb_strtolower( $keyword );

		foreach ( $rows as $row ) {
			if ( str_contains( mb_strtolower( (string) $row['key'] ), $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * List logged 404s.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function not_found( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'page'     => (int) $request['page'],
			'per_page' => (int) $request['per_page'],
			'search'   => (string) $request['search'],
		);

		$total = NotFound::count( $args['search'] );

		$response = new WP_REST_Response(
			array(
				'total' => $total,
				'items' => NotFound::all( $args ),
			)
		);

		$response->header( 'X-WP-Total', (string) $total );

		return $response;
	}

	/**
	 * Argument schema shared by both analysis methods.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function analysis_args(): array {
		return array(
			'post_id'     => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'title'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'keyword'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'slug'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
			),
			'content'     => array(
				'type'              => 'string',
				'sanitize_callback' => static fn ( $value ): string => wp_kses_post( (string) $value ),
			),
		);
	}

	/**
	 * Only users who may edit the post may analyse it.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public static function can_edit_post( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['post_id'];

		if ( ! get_post( $post_id ) ) {
			return new WP_Error(
				'wpcseo_post_not_found',
				__( 'That post does not exist.', 'wp-custom-seo' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'wpcseo_forbidden',
				__( 'You are not allowed to analyse this post.', 'wp-custom-seo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Analyse a post, using any unsaved editor values supplied in the request.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function analysis( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Settings::enabled( 'enable_analysis' ) ) {
			return new WP_Error(
				'wpcseo_analysis_disabled',
				__( 'Content analysis is turned off in the plugin settings.', 'wp-custom-seo' ),
				array( 'status' => 403 )
			);
		}

		$post_id = (int) $request['post_id'];

		// Stored values are the starting point; the editor sends what it has
		// unsaved on top, so the panel judges what is on screen.
		$stored = Meta::analysis_input( $post_id );

		foreach ( array( 'title', 'description', 'keyword', 'content', 'slug' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$stored[ $field ] = (string) $request[ $field ];
			}
		}

		$title       = '' !== trim( $stored['title'] ) ? $stored['title'] : (string) get_the_title( $post_id );
		$description = $stored['description'];

		$result = Analyzer::analyze(
			array_merge(
				$stored,
				array(
					'title'   => $title,
					'content' => do_shortcode( $stored['content'] ),
				)
			)
		);

		// What a search result would show, using the same fallbacks as the front end.
		if ( '' === trim( $description ) && Settings::enabled( 'description_excerpt' ) ) {
			$description = Frontend::fallback_description( $post_id );
		}

		$result['preview'] = array(
			'title'       => Templates::truncate( $title, 60 ),
			'description' => Templates::truncate( $description, 160 ),
			'url'         => (string) get_permalink( $post_id ),
		);

		return new WP_REST_Response( $result );
	}
}
