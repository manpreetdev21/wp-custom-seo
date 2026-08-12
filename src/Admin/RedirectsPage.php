<?php
/**
 * Redirects screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Redirects\Redirects;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Redirects: list, add, edit and bulk-manage rules.
 */
final class RedirectsPage {

	public const SLUG = 'wp-custom-seo-redirects';

	private const NONCE = 'wpcseo_redirect_form';

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
			'title'      => __( 'Redirects', 'wp-custom-seo' ),
			'menu_title' => __( 'Redirects', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Process form submissions and row actions.
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only; each branch verifies its own nonce.
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] || ! Capabilities::can_manage() ) {
			return;
		}

		if ( isset( $_POST['wpcseo_redirect_submit'] ) ) {
			self::save();

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified immediately below.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';

		if ( in_array( $action, array( 'delete', 'enable', 'disable' ), true ) && isset( $_REQUEST['id'] ) ) {
			check_admin_referer( 'wpcseo_redirect_row' );

			$id = absint( wp_unslash( $_REQUEST['id'] ) );

			if ( 'delete' === $action ) {
				Redirects::delete( $id );
				self::$notice = array(
					'type'    => 'success',
					'message' => __( 'Redirect deleted.', 'wp-custom-seo' ),
				);
			} else {
				Redirects::set_enabled( $id, 'enable' === $action );
				self::$notice = array(
					'type'    => 'success',
					'message' => __( 'Redirect updated.', 'wp-custom-seo' ),
				);
			}

			return;
		}

		if ( isset( $_POST['ids'] ) ) {
			check_admin_referer( 'bulk-redirects' );

			$bulk  = new RedirectsTable();
			$doing = $bulk->current_action();
			$ids   = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) );

			foreach ( $ids as $id ) {
				match ( $doing ) {
					'delete'  => Redirects::delete( $id ),
					'enable'  => Redirects::set_enabled( $id, true ),
					'disable' => Redirects::set_enabled( $id, false ),
					default   => null,
				};
			}

			if ( in_array( $doing, array( 'delete', 'enable', 'disable' ), true ) ) {
				self::$notice = array(
					'type'    => 'success',
					'message' => sprintf(
						/* translators: %d: number of redirects affected. */
						_n( '%d redirect updated.', '%d redirects updated.', count( $ids ), 'wp-custom-seo' ),
						count( $ids )
					),
				);
			}
		}
	}

	/**
	 * Save the add or edit form.
	 */
	private static function save(): void {
		check_admin_referer( self::NONCE );

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		$data = array(
			'source'   => isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['source'] ) ) : '',
			'target'   => isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['target'] ) ) : '',
			'type'     => isset( $_POST['type'] ) ? absint( wp_unslash( $_POST['type'] ) ) : 301,
			'is_regex' => ! empty( $_POST['is_regex'] ),
			'enabled'  => ! empty( $_POST['enabled'] ),
		);

		$result = $id > 0 ? Redirects::update( $id, $data ) : Redirects::insert( $data );

		if ( $result instanceof WP_Error ) {
			self::$notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);

			return;
		}

		self::$notice = array(
			'type'    => 'success',
			'message' => $id > 0 ? __( 'Redirect updated.', 'wp-custom-seo' ) : __( 'Redirect created.', 'wp-custom-seo' ),
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
		$id     = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		// Decoded before sanitising so an encoded payload cannot survive the pass.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_text_field wraps the decoded value.
		$prefill = isset( $_GET['source'] ) ? sanitize_text_field( rawurldecode( wp_unslash( (string) $_GET['source'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$editing = 'edit' === $action && $id > 0 ? Redirects::get( $id ) : null;

		$table = new RedirectsTable();
		$table->prepare_items();

		$vars = array(
			'table'   => $table,
			'editing' => $editing,
			'prefill' => $prefill,
			'notice'  => self::$notice,
			'nonce'   => self::NONCE,
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/redirects.php';
	}
}
