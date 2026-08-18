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
use WPCustomSeo\Links\Links;
use WPCustomSeo\Sitemap\Sitemap;

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

		// The top-level entry and the first submenu entry share a slug, so
		// WordPress derives the same hook name for both and registers each
		// callback against it. Identical callables collapse into one — which is
		// why passing the same array to both used to be safe — but two distinct
		// closures would not, and the dashboard would render twice. So the
		// wrapper for this slug is built once and handed to both.
		$dashboard = isset( $pages[ self::SLUG ]['callback'] )
			? self::wrap( self::SLUG, $pages[ self::SLUG ]['callback'] )
			: '__return_false';

		add_menu_page(
			(string) ( $pages[ self::SLUG ]['title'] ?? __( 'SEO', 'wp-custom-seo' ) ),
			__( 'SEO', 'wp-custom-seo' ),
			Capabilities::MANAGE,
			self::SLUG,
			$dashboard,
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
				self::SLUG === (string) $slug ? $dashboard : self::wrap( (string) $slug, $page['callback'] )
			);
		}
	}

	/**
	 * Wrap a screen's callback in the application shell.
	 *
	 * Done here, once, rather than by editing every page class: the sidebar and
	 * header are a property of the plugin's admin as a whole, not of any one
	 * screen, and a screen registered later by an add-on picks the chrome up
	 * without knowing it exists.
	 *
	 * The screen's own callback is called untouched in the middle, so nothing
	 * about how a page renders — or what it checks before rendering — changes.
	 *
	 * @param string   $slug     Menu slug.
	 * @param callable $callback The screen's render callback.
	 */
	private static function wrap( string $slug, $callback ): callable {
		return static function () use ( $slug, $callback ): void {
			Shell::render( $slug, $callback );
		};
	}

	/**
	 * Load admin styles on plugin screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( string $hook_suffix ): void {
		// Both assets are gated on this one check, so nothing the plugin adds is
		// downloaded on a screen that belongs to WordPress or another plugin.
		if ( ! str_contains( $hook_suffix, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcseo-admin',
			WP_CUSTOM_SEO_URL . 'assets/css/admin.css',
			array(),
			\WPCustomSeo\VERSION
		);

		wp_enqueue_script(
			'wpcseo-admin',
			WP_CUSTOM_SEO_URL . 'assets/js/admin.js',
			array(),
			\WPCustomSeo\VERSION,
			true
		);

		wp_add_inline_script(
			'wpcseo-admin',
			'window.wpcseoShell = ' . wp_json_encode(
				array(
					// The palette searches in the browser rather than over AJAX:
					// the index is every screen and every visible setting, which
					// is a few kilobytes, and a round trip per keystroke to
					// filter a list that small would be slower and no fresher.
					'index' => Shell::search_index(),
					'i18n'  => array(
						'saved'        => __( 'Settings saved', 'wp-custom-seo' ),
						'theme'        => __( 'Theme', 'wp-custom-seo' ),
						'theme_system' => __( 'Match system', 'wp-custom-seo' ),
						'theme_light'  => __( 'Light', 'wp-custom-seo' ),
						'theme_dark'   => __( 'Dark', 'wp-custom-seo' ),
					),
				)
			) . ';',
			'before'
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
				// Reads the audit's existing hourly cache. Building the report
				// here would make opening the dashboard trigger a site-wide scan.
				'health'     => Health::summary(),
				'links'      => Settings::enabled( 'enable_link_graph' ) ? Links::total() : null,
				'sitemap'    => Sitemap::index_url(),
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
