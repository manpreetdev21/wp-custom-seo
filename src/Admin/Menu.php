<?php
/**
 * Admin menu and page rendering.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Core\Settings;
use WPCustomSeo\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the SEO menu.
 *
 * Pages come from a filterable registry so later phases add their screens
 * without touching this class. Only screens with a real implementation are
 * registered — no placeholder pages.
 */
final class Menu {

	public const SLUG = 'wp-custom-seo';

	public const SETTINGS_SLUG = Settings::PAGE;

	/**
	 * Hook the menu.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Registered subpages.
	 *
	 * @return array<string, array{title: string, menu_title: string, callback: callable}>
	 */
	public static function pages(): array {
		$pages = array(
			self::SLUG          => array(
				'title'      => __( 'SEO Dashboard', 'wp-custom-seo' ),
				'menu_title' => __( 'Dashboard', 'wp-custom-seo' ),
				'callback'   => array( self::class, 'render_dashboard' ),
			),
			self::SETTINGS_SLUG => array(
				'title'      => __( 'SEO Settings', 'wp-custom-seo' ),
				'menu_title' => __( 'Settings', 'wp-custom-seo' ),
				'callback'   => array( self::class, 'render_settings' ),
			),
		);

		/**
		 * Filters the admin pages registered under the SEO menu.
		 *
		 * @param array $pages Page definitions keyed by menu slug.
		 */
		return (array) apply_filters( 'wpcseo_admin_pages', $pages );
	}

	/**
	 * Register the top-level menu and its subpages.
	 */
	public static function register(): void {
		$pages = self::pages();

		add_menu_page(
			(string) ( $pages[ self::SLUG ]['title'] ?? __( 'SEO', 'wp-custom-seo' ) ),
			__( 'SEO', 'wp-custom-seo' ),
			Capabilities::MANAGE,
			self::SLUG,
			$pages[ self::SLUG ]['callback'] ?? '__return_false',
			'dashicons-chart-area',
			58
		);

		foreach ( $pages as $slug => $page ) {
			add_submenu_page(
				self::SLUG,
				(string) $page['title'],
				(string) $page['menu_title'],
				Capabilities::MANAGE,
				(string) $slug,
				$page['callback']
			);
		}
	}

	/**
	 * Load admin styles on plugin screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcseo-admin',
			WP_CUSTOM_SEO_URL . 'assets/css/admin.css',
			array(),
			\WPCustomSeo\VERSION
		);
	}

	/**
	 * Render the dashboard.
	 */
	public static function render_dashboard(): void {
		self::guard();

		$modules = array();

		foreach ( Settings::fields() as $id => $field ) {
			if ( str_starts_with( $id, 'enable_' ) ) {
				$modules[ (string) ( $field['label'] ?? $id ) ] = Settings::enabled( $id );
			}
		}

		self::template(
			'dashboard',
			array(
				'version'    => \WPCustomSeo\VERSION,
				'db_version' => Migrator::current_version(),
				'modules'    => $modules,
			)
		);
	}

	/**
	 * Render the settings screen.
	 */
	public static function render_settings(): void {
		self::guard();

		self::template( 'settings', array( 'page' => self::SETTINGS_SLUG ) );
	}

	/**
	 * Stop rendering for users without the capability.
	 */
	private static function guard(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}
	}

	/**
	 * Include an admin template with the given variables in scope.
	 *
	 * @param string               $name Template file name, without extension.
	 * @param array<string, mixed> $vars Variables extracted into the template.
	 */
	private static function template( string $name, array $vars = array() ): void {
		$path = WP_CUSTOM_SEO_DIR . 'templates/admin/' . $name . '.php';

		if ( ! is_readable( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require $path;
	}
}
