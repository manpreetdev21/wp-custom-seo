<?php
/**
 * Search Console screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Core\Settings;
use WPCustomSeo\Analytics\Client as AnalyticsClient;
use WPCustomSeo\Analytics\Engagement;
use WPCustomSeo\SearchConsole\Account;
use WPCustomSeo\SearchConsole\Client;
use WPCustomSeo\SearchConsole\Performance;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Search Performance.
 *
 * Everything on this screen is Google's own reported data. When there is no
 * connection there are no figures — not zeroes, not placeholders, not a demo
 * chart. A screen that shows numbers it does not have teaches an editor to
 * trust numbers it does.
 */
final class SearchConsolePage {

	public const SLUG = 'wp-custom-seo-search';

	private const CONNECT_ACTION = 'wpcseo_gsc_connect';

	private const DISCONNECT_ACTION = 'wpcseo_gsc_disconnect';

	private const PROPERTY_ACTION = 'wpcseo_gsc_property';

	private const ANALYTICS_ACTION = 'wpcseo_ga4_property';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
		add_action( 'admin_post_' . self::CONNECT_ACTION, array( self::class, 'connect' ) );
		add_action( 'admin_post_' . self::DISCONNECT_ACTION, array( self::class, 'disconnect' ) );
		add_action( 'admin_post_' . self::PROPERTY_ACTION, array( self::class, 'choose_property' ) );
		add_action( 'admin_post_' . self::ANALYTICS_ACTION, array( self::class, 'choose_analytics' ) );
	}

	/**
	 * Store the Analytics property id.
	 */
	public static function choose_analytics(): void {
		self::guard();
		check_admin_referer( self::ANALYTICS_ACTION );

		$raw = isset( $_POST['wpcseo_ga4'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcseo_ga4'] ) ) : '';

		// The numeric property id, not the G- measurement id, which is the one
		// people have to hand and the one the Data API does not accept.
		$digits = AnalyticsClient::normalize_property( $raw );

		if ( '' !== trim( $raw ) && '' === $digits ) {
			self::back( array( 'wpcseo_error' => __( 'That is not an Analytics property id. It is the number shown under Admin → Property details, not the measurement id beginning with G-.', 'wp-custom-seo' ) ) );
		}

		Settings::update( array( AnalyticsClient::PROPERTY => $digits ) );
		AnalyticsClient::flush();

		self::back( array( 'wpcseo_saved' => 1 ) );
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
			'title'      => __( 'Search Performance', 'wp-custom-seo' ),
			'menu_title' => __( 'Search Performance', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Refuse anyone without the plugin capability.
	 */
	private static function guard(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wp-custom-seo' ), 403 );
		}
	}

	/**
	 * Store a pasted service account key file.
	 */
	public static function connect(): void {
		self::guard();
		check_admin_referer( self::CONNECT_ACTION );

		// The key file is JSON containing a PEM private key, so it must not go
		// through a text sanitizer that would strip its line breaks. It is
		// validated by being parsed, and it is never echoed back.
		$json = isset( $_POST['wpcseo_key'] ) ? trim( (string) wp_unslash( $_POST['wpcseo_key'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$saved = Account::save( $json );

		self::back( $saved instanceof WP_Error ? array( 'wpcseo_error' => $saved->get_error_message() ) : array( 'wpcseo_connected' => 1 ) );
	}

	/**
	 * Forget the key file.
	 */
	public static function disconnect(): void {
		self::guard();
		check_admin_referer( self::DISCONNECT_ACTION );

		Account::forget();
		Client::flush();
		Settings::update( array( Performance::PROPERTY => '' ) );

		self::back( array( 'wpcseo_disconnected' => 1 ) );
	}

	/**
	 * Store the chosen property.
	 */
	public static function choose_property(): void {
		self::guard();
		check_admin_referer( self::PROPERTY_ACTION );

		$property  = isset( $_POST['wpcseo_property'] ) ? sanitize_text_field( wp_unslash( $_POST['wpcseo_property'] ) ) : '';
		$available = Client::sites();

		// Only a property this account can actually read may be stored, so a
		// tampered form cannot make the screen query something else.
		$allowed = $available instanceof WP_Error ? array() : wp_list_pluck( $available, 'url' );

		if ( '' !== $property && ! in_array( $property, $allowed, true ) ) {
			self::back( array( 'wpcseo_error' => __( 'That property is not one this service account can read.', 'wp-custom-seo' ) ) );
		}

		Settings::update( array( Performance::PROPERTY => $property ) );
		Client::flush();

		self::back( array( 'wpcseo_saved' => 1 ) );
	}

	/**
	 * Return to the screen.
	 *
	 * @param array<string, int|string> $args Extra query arguments.
	 */
	private static function back( array $args = array() ): void {
		wp_safe_redirect(
			add_query_arg(
				array_merge( array( 'page' => self::SLUG ), array_map( 'rawurlencode', $args ) ),
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

		$connected = Account::is_connected();
		$sites     = $connected ? Client::sites() : array();
		$property  = Performance::property();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notices; each action verifies its own nonce.
		$days   = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : 28;
		$notice = isset( $_GET['wpcseo_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wpcseo_error'] ) ) : '';
		$saved  = isset( $_GET['wpcseo_saved'] ) || isset( $_GET['wpcseo_connected'] );
		$gone   = isset( $_GET['wpcseo_disconnected'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$days   = in_array( $days, Performance::PERIODS, true ) ? $days : 28;
		$report = ( $connected && '' !== $property ) ? Performance::report( $days ) : null;

		$vars = array(
			'connected'  => $connected,
			'email'      => Account::email(),
			'sites'      => $sites,
			'property'   => $property,
			'days'       => $days,
			'report'     => $report,
			'error'      => $report instanceof WP_Error ? $report->get_error_message() : '',
			'notice'     => $notice,
			'saved'      => $saved,
			'gone'       => $gone,
			'ga4'        => AnalyticsClient::property(),
			'ga4_action' => self::ANALYTICS_ACTION,
			// Only asked for once a property is set, so an unconfigured site
			// makes no request and shows no figures.
			'engagement' => AnalyticsClient::is_configured() ? Engagement::landing_pages( $days ) : null,
			'ga4_totals' => AnalyticsClient::is_configured() ? Engagement::totals( $days ) : null,
			'connect'    => self::CONNECT_ACTION,
			'disconnect' => self::DISCONNECT_ACTION,
			'choose'     => self::PROPERTY_ACTION,
			'slug'       => self::SLUG,
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/search-console.php';
	}
}
