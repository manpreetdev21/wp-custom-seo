<?php
/**
 * Site audit screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Audit\Auditor;
use WPCustomSeo\Audit\Cannibalization;
use WPCustomSeo\Audit\Finding;
use WPCustomSeo\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Site Audit.
 */
final class AuditPage {

	public const SLUG = 'wp-custom-seo-audit';

	private const REFRESH_ACTION = 'wpcseo_refresh_audit';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
		add_action( 'admin_post_' . self::REFRESH_ACTION, array( self::class, 'refresh' ) );
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
			'title'      => __( 'Site Audit', 'wp-custom-seo' ),
			'menu_title' => __( 'Site Audit', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Rebuild the report.
	 */
	public static function refresh(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-custom-seo' ), 403 );
		}

		check_admin_referer( self::REFRESH_ACTION );

		Auditor::flush();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::SLUG,
					'wpcseo_refreshed' => 1,
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

		$report = Auditor::report();

		$vars = array(
			'action'       => self::REFRESH_ACTION,
			'report'       => $report,
			'levels'       => Finding::levels(),
			'descriptions' => Finding::level_descriptions(),
			'remedies'     => Cannibalization::remedies(),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only confirmation notice.
			'refreshed'    => isset( $_GET['wpcseo_refreshed'] ),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/audit.php';
	}
}
