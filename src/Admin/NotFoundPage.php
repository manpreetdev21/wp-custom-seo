<?php
/**
 * 404 monitor screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Core\Settings;
use WPCustomSeo\Redirects\NotFound;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → 404 Monitor: review logged misses and turn them into redirects.
 */
final class NotFoundPage {

	public const SLUG = 'wp-custom-seo-404';

	/**
	 * Notice to show on the next render.
	 *
	 * @var array{type: string, message: string}|null
	 */
	private static ?array $notice = null;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'handle' ) );
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
			'title'      => __( '404 Monitor', 'wp-custom-seo' ),
			'menu_title' => __( '404 Monitor', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Process row and bulk actions.
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only; each branch verifies its own nonce.
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] || ! Capabilities::can_manage() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in each branch below.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';

		if ( 'delete' === $action && isset( $_REQUEST['id'] ) ) {
			check_admin_referer( 'wpcseo_not_found_row' );

			NotFound::delete( absint( wp_unslash( $_REQUEST['id'] ) ) );
			self::$notice = array(
				'type'    => 'success',
				'message' => __( 'Entry deleted.', 'wp-custom-seo' ),
			);

			return;
		}

		if ( isset( $_POST['wpcseo_clear_404'] ) ) {
			check_admin_referer( 'wpcseo_clear_404' );

			$removed      = NotFound::clear();
			self::$notice = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %d: number of entries removed. */
					_n( '%d entry removed.', '%d entries removed.', $removed, 'wp-custom-seo' ),
					$removed
				),
			);

			return;
		}

		if ( isset( $_POST['ids'] ) ) {
			check_admin_referer( 'bulk-not_founds' );

			$ids = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) );

			foreach ( $ids as $id ) {
				NotFound::delete( $id );
			}

			self::$notice = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %d: number of entries removed. */
					_n( '%d entry removed.', '%d entries removed.', count( $ids ), 'wp-custom-seo' ),
					count( $ids )
				),
			);
		}
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		$table = new NotFoundTable();
		$table->prepare_items();

		$vars = array(
			'table'     => $table,
			'notice'    => self::$notice,
			'enabled'   => Settings::enabled( 'monitor_404' ),
			'retention' => (int) Settings::get( 'not_found_retention', 30 ),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/not-found.php';
	}
}
