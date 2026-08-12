<?php
/**
 * Google Analytics 4 Data API client.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Analytics;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\SearchConsole\Account;
use WPCustomSeo\SearchConsole\Token;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the GA4 Data API using the same service account as Search Console.
 *
 * One key file covers both: a service account can be granted access to a
 * Search Console property and a GA4 property alike, so asking for a second key
 * would be asking twice for the same thing. The token is minted per scope, so
 * a project with only one of the two APIs enabled still works for that one.
 *
 * Search Console says how people found the site. This says what they did next.
 * Neither number is computed here — where Google reports nothing, nothing is
 * shown.
 */
final class Client {

	/**
	 * Settings key holding the numeric GA4 property id.
	 */
	public const PROPERTY = 'ga4_property';

	private const BASE = 'https://analyticsdata.googleapis.com/v1beta/';

	private const TIMEOUT = 30;

	/**
	 * Analytics updates through the day, so a shorter life than Search Console's
	 * twelve hours. Still long enough that a page refresh is free.
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Option holding the cache generation.
	 */
	private const GENERATION = 'wpcseo_ga4_generation';

	/**
	 * The configured property id.
	 */
	public static function property(): string {
		return self::normalize_property( (string) Settings::get( self::PROPERTY, '' ) );
	}

	/**
	 * Reduce what someone typed to a GA4 property id, or nothing.
	 *
	 * A property id is entirely numeric. The value people have to hand is
	 * usually the measurement id — `G-ABC123` — and simply stripping non-digits
	 * would turn that into "123": a number that looks like a property id, is
	 * not one, and would fail later as a confusing 404. Anything that is not
	 * digits, with an optional `properties/` prefix, is refused here instead.
	 *
	 * @param string $raw Submitted value.
	 */
	public static function normalize_property( string $raw ): string {
		$raw = trim( $raw );
		$raw = (string) preg_replace( '#^properties/#i', '', $raw );

		return preg_match( '/^\d+$/', $raw ) ? $raw : '';
	}

	/**
	 * Whether there is enough configuration to ask anything.
	 */
	public static function is_configured(): bool {
		return Account::is_connected() && '' !== self::property();
	}

	/**
	 * Run a report.
	 *
	 * @param string[] $dimensions Dimension names.
	 * @param string[] $metrics    Metric names.
	 * @param int      $days       How many days back to look.
	 * @param int      $limit      Maximum rows.
	 * @param bool     $fresh      Skip the cache.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function report( array $dimensions, array $metrics, int $days = 28, int $limit = 25, bool $fresh = false ): array|WP_Error {
		if ( ! Account::is_connected() ) {
			return new WP_Error(
				'wpcseo_ga4_not_connected',
				__( 'No Google service account is connected. Connect one under SEO → Search Performance; the same key covers Analytics.', 'wp-custom-seo' )
			);
		}

		if ( '' === self::property() ) {
			return new WP_Error(
				'wpcseo_ga4_no_property',
				__( 'No Analytics property has been set.', 'wp-custom-seo' )
			);
		}

		$body = array(
			'dateRanges' => array(
				array(
					'startDate' => max( 1, $days ) . 'daysAgo',
					'endDate'   => 'today',
				),
			),
			'dimensions' => array_map( static fn ( string $name ): array => array( 'name' => $name ), $dimensions ),
			'metrics'    => array_map( static fn ( string $name ): array => array( 'name' => $name ), $metrics ),
			'limit'      => max( 1, min( 1000, $limit ) ),
		);

		$response = self::request( 'properties/' . self::property() . ':runReport', $body, $fresh );

		return $response instanceof WP_Error ? $response : self::rows( $response );
	}

	/**
	 * Flatten a runReport response.
	 *
	 * The API returns dimension and metric values as parallel lists, named only
	 * in the headers, so they are zipped back into something readable here.
	 *
	 * @param array<string, mixed> $response Decoded reply.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function rows( array $response ): array {
		$dimension_names = array_map(
			static fn ( array $header ): string => (string) ( $header['name'] ?? '' ),
			array_values( (array) ( $response['dimensionHeaders'] ?? array() ) )
		);

		$metric_names = array_map(
			static fn ( array $header ): string => (string) ( $header['name'] ?? '' ),
			array_values( (array) ( $response['metricHeaders'] ?? array() ) )
		);

		$rows = array();

		foreach ( (array) ( $response['rows'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$flat = array();

			foreach ( array_values( (array) ( $row['dimensionValues'] ?? array() ) ) as $index => $value ) {
				$name = $dimension_names[ $index ] ?? (string) $index;

				$flat[ $name ] = (string) ( $value['value'] ?? '' );
			}

			foreach ( array_values( (array) ( $row['metricValues'] ?? array() ) ) as $index => $value ) {
				$name = $metric_names[ $index ] ?? (string) $index;

				// Every metric arrives as a string. Counts become integers and
				// rates stay floats, so nothing is displayed as "12.0 sessions"
				// or an engagement rate rounded to zero.
				$raw = (string) ( $value['value'] ?? '' );

				$flat[ $name ] = str_contains( $raw, '.' ) ? (float) $raw : (int) $raw;
			}

			$rows[] = $flat;
		}

		return $rows;
	}

	/**
	 * Perform a request, cached.
	 *
	 * @param string               $path  Path below the API base.
	 * @param array<string, mixed> $body  POST body.
	 * @param bool                 $fresh Skip the cache.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function request( string $path, array $body, bool $fresh ): array|WP_Error {
		$generation = (int) get_option( self::GENERATION, 0 );
		$cache_key  = 'wpcseo_ga4_' . md5( $generation . $path . (string) wp_json_encode( $body ) );

		if ( ! $fresh ) {
			$cached = get_transient( $cache_key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = self::send( $path, $body, false );

		if ( $result instanceof WP_Error && 'wpcseo_ga4_expired' === $result->get_error_code() ) {
			Token::forget();

			$result = self::send( $path, $body, true );
		}

		if ( $result instanceof WP_Error ) {
			return 'wpcseo_ga4_expired' === $result->get_error_code()
				? self::status_error( 401, array() )
				: $result;
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Perform one request.
	 *
	 * @param string               $path  Path below the API base.
	 * @param array<string, mixed> $body  POST body.
	 * @param bool                 $fresh Mint a new token.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function send( string $path, array $body, bool $fresh ): array|WP_Error {
		$token = Token::get( $fresh, Token::SCOPE_ANALYTICS );

		if ( $token instanceof WP_Error ) {
			return $token;
		}

		$response = wp_remote_post(
			self::BASE . $path,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode( $body ),
			)
		);

		if ( $response instanceof WP_Error ) {
			return new WP_Error(
				'wpcseo_ga4_network',
				__( 'Could not reach the Analytics API. Check the site can make outbound HTTPS requests.', 'wp-custom-seo' )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data   = is_array( $data ) ? $data : array();

		if ( 401 === $status ) {
			return new WP_Error( 'wpcseo_ga4_expired', __( 'The access token was rejected.', 'wp-custom-seo' ) );
		}

		if ( $status < 200 || $status > 299 ) {
			return self::status_error( $status, $data );
		}

		return $data;
	}

	/**
	 * Turn an API failure into something an administrator can act on.
	 *
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $data   Decoded reply.
	 */
	private static function status_error( int $status, array $data ): WP_Error {
		$reason = (string) ( $data['error']['status'] ?? '' );

		$message = match ( true ) {
			401 === $status => __( 'Google rejected the credentials. Save the key file again, and check the server clock is correct.', 'wp-custom-seo' ),
			403 === $status => sprintf(
				/* translators: %s: service account email address. */
				__( 'The service account cannot read this Analytics property. In Analytics, open Admin → Property access management and add %s as a Viewer. Check the Analytics Data API is enabled for its Google Cloud project too.', 'wp-custom-seo' ),
				Account::email()
			),
			404 === $status => __( 'Analytics has no property with that id. It is the numeric id from Admin → Property details, not the measurement id that starts with G-.', 'wp-custom-seo' ),
			400 === $status && 'INVALID_ARGUMENT' === $reason => __( 'Analytics refused the request as malformed. If the property id was entered by hand, check it is the numeric one.', 'wp-custom-seo' ),
			429 === $status => __( 'Google rate-limited this request. Results are cached for an hour; wait a little and try again.', 'wp-custom-seo' ),
			$status >= 500 => __( 'The Analytics API is unavailable right now. Try again shortly.', 'wp-custom-seo' ),
			default => sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The Analytics API returned an unexpected response (HTTP %d).', 'wp-custom-seo' ),
				$status
			),
		};

		return new WP_Error( 'wpcseo_ga4_api', $message );
	}

	/**
	 * Abandon every cached reply.
	 */
	public static function flush(): void {
		update_option( self::GENERATION, (int) get_option( self::GENERATION, 0 ) + 1, false );
	}
}
