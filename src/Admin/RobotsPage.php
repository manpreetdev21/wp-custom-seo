<?php
/**
 * Robots.txt screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Core\Settings;
use WPCustomSeo\Crawlers\AiCrawlers;
use WPCustomSeo\Crawlers\RobotsTxt;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Robots.txt: view what is served, and edit the custom rules.
 */
final class RobotsPage {

	public const SLUG = 'wp-custom-seo-robots';

	private const SAVE_ACTION = 'wpcseo_save_robots_txt';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'save' ) );
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
			'title'      => __( 'Robots.txt', 'wp-custom-seo' ),
			'menu_title' => __( 'Robots.txt', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Persist the submitted rules.
	 */
	public static function save(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-custom-seo' ), 403 );
		}

		check_admin_referer( self::SAVE_ACTION );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by RobotsTxt::sanitize(), which cleans each line without collapsing the newlines this format is built from.
		$raw = isset( $_POST['wpcseo_robots_rules'] ) ? (string) wp_unslash( $_POST['wpcseo_robots_rules'] ) : '';

		$rules = RobotsTxt::sanitize( $raw );

		// A rule set that hides the entire site is saved only when the person
		// saving it has said, on this submission, that they meant it. Anything
		// weaker than an explicit confirmation would make the most destructive
		// thing on the screen also the easiest thing to do by accident.
		$confirmed = ! empty( $_POST['wpcseo_robots_confirm'] );
		$notice    = 'saved';

		if ( RobotsTxt::blocks_entire_site( $rules ) && ! $confirmed ) {
			$notice = 'blocked';
			$rules  = RobotsTxt::rules();
		}

		Settings::update(
			array(
				RobotsTxt::SETTING         => $rules,
				RobotsTxt::SETTING_SITEMAP => ! empty( $_POST['wpcseo_robots_sitemap'] ),
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => self::SLUG,
					'wpcseo_notice' => $notice,
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

		$rules = RobotsTxt::rules();

		$vars = array(
			'action'        => self::SAVE_ACTION,
			'rules'         => $rules,
			'declare'       => Settings::enabled( RobotsTxt::SETTING_SITEMAP ),
			'preview'       => RobotsTxt::preview(),
			'physical'      => AiCrawlers::has_physical_file(),
			'blocks_site'   => RobotsTxt::blocks_entire_site( $rules ),
			'discouraged'   => '1' !== (string) get_option( 'blog_public' ),
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice selector.
			'notice'        => isset( $_GET['wpcseo_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpcseo_notice'] ) ) : '',
			'settings_page' => admin_url( 'admin.php?page=' . Settings::PAGE ),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/robots.php';
	}
}
