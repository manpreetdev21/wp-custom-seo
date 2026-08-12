<?php
/**
 * Schema validator screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Schema\Conflicts;
use WPCustomSeo\Schema\Graph\Pieces;
use WPCustomSeo\Schema\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Schema: validate the generated graph and list other emitters.
 */
final class SchemaPage {

	public const SLUG = 'wp-custom-seo-schema';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
	}

	/**
	 * Add the screen to the menu registry.
	 *
	 * @param array<string, array<string, mixed>> $pages Registered pages.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function register( array $pages ): array {
		$pages[ self::SLUG ] = array(
			'title'      => __( 'Schema', 'wp-custom-seo' ),
			'menu_title' => __( 'Schema', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		// Read-only screen; the post id only selects which graph to display.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['wpcseo_post'] ) ? absint( wp_unslash( $_GET['wpcseo_post'] ) ) : 0;

		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}

		$graph  = $post_id > 0 ? Pieces::for_post( $post_id ) : self::front_page_graph();
		$issues = Validator::validate( $graph );

		$vars = array(
			'post_id'   => $post_id,
			'graph'     => $graph,
			'issues'    => $issues,
			'conflicts' => Conflicts::detect(),
			'posts'     => get_posts(
				array(
					'numberposts'      => 20,
					'post_type'        => 'any',
					'post_status'      => 'publish',
					'suppress_filters' => false,
				)
			),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/schema.php';
	}

	/**
	 * Graph for the site front page.
	 */
	private static function front_page_graph(): \WPCustomSeo\Schema\Graph\Graph {
		$front_id = (int) get_option( 'page_on_front' );

		return $front_id > 0 ? Pieces::for_post( $front_id ) : Pieces::for_post( 0 );
	}
}
