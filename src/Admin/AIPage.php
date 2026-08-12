<?php
/**
 * AI keys and usage screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\AI\Credentials;
use WPCustomSeo\AI\Manager;
use WPCustomSeo\AI\UsageLog;
use WPCustomSeo\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → AI: credential entry and the usage log.
 *
 * Keys are handled here rather than in the settings form because the Settings
 * API round-trips values through the page, and a credential must never be
 * rendered into HTML.
 */
final class AIPage {

	public const SLUG = 'wp-custom-seo-ai';

	private const SAVE_ACTION = 'wpcseo_save_ai_keys';

	private const CLEAR_ACTION = 'wpcseo_clear_ai_log';

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
		add_action( 'admin_post_' . self::SAVE_ACTION, array( self::class, 'save_keys' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( self::class, 'clear_log' ) );
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
			'title'      => __( 'AI', 'wp-custom-seo' ),
			'menu_title' => __( 'AI', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Store submitted keys.
	 */
	public static function save_keys(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-custom-seo' ), 403 );
		}

		check_admin_referer( self::SAVE_ACTION );

		foreach ( array_keys( Manager::providers() ) as $provider ) {
			$field = 'wpcseo_key_' . $provider;

			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// A credential is not "text": sanitize_text_field would silently
			// mangle a key containing characters it strips. Only whitespace and
			// slashes added by WordPress are removed.
			$submitted = trim( wp_unslash( (string) $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- deliberately preserved verbatim; never rendered or executed.

			// An untouched field posts the mask, which must not overwrite the key.
			if ( '' !== $submitted && ! str_contains( $submitted, '•' ) ) {
				Credentials::set( $provider, $submitted );
			}

			if ( '' === $submitted && ! empty( $_POST[ 'wpcseo_clear_' . $provider ] ) ) {
				Credentials::set( $provider, '' );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::SLUG,
					'wpcseo_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Empty the usage log.
	 */
	public static function clear_log(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-custom-seo' ), 403 );
		}

		check_admin_referer( self::CLEAR_ACTION );

		$removed = UsageLog::clear();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => self::SLUG,
					'wpcseo_cleared' => $removed,
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

		$vars = array(
			'save_action'  => self::SAVE_ACTION,
			'clear_action' => self::CLEAR_ACTION,
			'providers'    => Manager::providers(),
			'active'       => Manager::provider(),
			'model'        => Manager::model(),
			'ready'        => Manager::is_ready(),
			'encrypted'    => Credentials::can_encrypt(),
			'totals'       => UsageLog::totals(),
			'recent'       => UsageLog::recent( 25 ),
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only confirmation notices.
			'saved'        => isset( $_GET['wpcseo_saved'] ),
			'cleared'      => isset( $_GET['wpcseo_cleared'] ) ? absint( wp_unslash( $_GET['wpcseo_cleared'] ) ) : null,
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			'notice'       => self::$notice,
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/ai.php';
	}
}
