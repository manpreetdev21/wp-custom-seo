<?php
/**
 * Internal links screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Core\Settings;
use WPCustomSeo\Links\Links;
use WPCustomSeo\Links\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Internal Links: the link graph, orphans and under-linked content.
 */
final class LinksPage {

	public const SLUG = 'wp-custom-seo-links';

	private const REBUILD_ACTION = 'wpcseo_rebuild_links_now';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
		add_action( 'admin_post_' . self::REBUILD_ACTION, array( self::class, 'rebuild' ) );
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
			'title'      => __( 'Internal Links', 'wp-custom-seo' ),
			'menu_title' => __( 'Internal Links', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Queue a full rebuild.
	 */
	public static function rebuild(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-custom-seo' ), 403 );
		}

		check_admin_referer( self::REBUILD_ACTION );

		Scanner::start_rebuild();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => self::SLUG,
					'wpcseo_rebuilding' => 1,
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		$critical = (int) Settings::get( 'orphan_critical', 0 );
		$warning  = max( $critical, (int) Settings::get( 'orphan_warning', 1 ) );

		$vars = array(
			'action'      => self::REBUILD_ACTION,
			'total'       => Links::total(),
			'rebuilding'  => Scanner::is_rebuilding(),
			'progress'    => Scanner::progress(),
			'critical'    => $critical,
			'warning'     => $warning,
			'under'       => Links::under_linked( $warning, Links::post_types() ),
			'most_linked' => Links::most_linked( 10 ),
			'unresolved'  => Links::unresolved( 25 ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only confirmation notice.
			'just_queued' => isset( $_GET['wpcseo_rebuilding'] ),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/links.php';
	}
}
