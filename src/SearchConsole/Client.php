<?php
/**
 * Search Console API client.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SearchConsole;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the Search Console API through the WordPress HTTP layer.
 *
 * Everything this returns is Google's own reported data. The plugin computes
 * nothing here and estimates nothing: if the API is unreachable or the account
 * has no access, the screens say so and show no figures, because an invented
 * impression count would be indistinguishable from a real one.
 *
 * Replies are cached for twelve hours. Search Console data updates roughly
 * daily and lags by two to three days, so a shorter cache would spend quota to
 * fetch the same numbers.
 */
final class Client {

	private const BASE = 'https://searchconsole.googleapis.com/webmasters/v3/';

	private const TIMEOUT = 30;

	private const CACHE_TTL = 43200;

	/**
	 * Option holding the cache generation.
	 */
	private const GENERATION = 'wpcseo_gsc_generation';

	/**
	 * Properties the service account can read.
	 *
	 * An empty list is the normal state right after connecting, and it means
	 * one specific thing: the account exists but has not been added to any
	 * property yet.
	 *
	 * @param bool $fresh Skip the cache.
	 *
	 * @return array<int, array{url: string, level: string}>|WP_Error
	 */
	public static function sites( bool $fresh = false ): array|WP_Error {
		$response = self::request( 'sites', null, $fresh );

		if ( $response instanceof WP_Error ) {
			return $response;
		}

		$sites = array();

		foreach ( (array) ( $response['siteEntry'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) || '' === (string) ( $entry['siteUrl'] ?? '' ) ) {
				continue;
			}

			$sites[] = array(
				'url'   => (string) $entry['siteUrl'],
				'level' => (string) ( $entry['permissionLevel'] ?? '' ),
			);
		}

		return $sites;
	}

	/**
	 * Run a search analytics query.
	 *
	 * @param string   $property   Property URL as Search Console spells it.
	 * @param string[] $dimensions One or more of query, page, country, device, date.
	 * @param string   $start      Start date, Y-m-d.
	 * @param string   $end        End date, Y-m-d.
	 * @param int      $limit      Maximum rows.
	 * @param array    $filters    Dimension filters, already in API shape.
	 * @param bool     $fresh      Skip the cache.
	 *
	 * @return array<int, array{keys: string[], clicks: float, impressions: float, ctr: float, position: float}>|WP_Error
	 */
	public static function query( string $property, array $dimensions, string $start, string $end, int $limit = 25, array $filters = array(), bool $fresh = false ): array|WP_Error {
		if ( '' === $property ) {
			return new WP_Error(
				'wpcseo_gsc_no_property',
				__( 'No Search Console property has been chosen.', 'wp-custom-seo' )
			);
		}

		$body = array(
			'startDate'  => $start,
			'endDate'    => $end,
			'dimensions' => array_values( $dimensions ),
			'rowLimit'   => max( 1, min( 25000, $limit ) ),
		);

		if ( $filters ) {
			$body['dimensionFilterGroups'] = array( array( 'filters' => array_values( $filters ) ) );
		}

		$response = self::request( 'sites/' . rawurlencode( $property ) . '/searchAnalytics/query', $body, $fresh );

		if ( $response instanceof WP_Error ) {
			return $response;
		}

		$rows = array();

		foreach ( (array) ( $response['rows'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$rows[] = array(
				'keys'        => array_map( 'strval', (array) ( $row['keys'] ?? array() ) ),
				'clicks'      => (float) ( $row['clicks'] ?? 0 ),
				'impressions' => (float) ( $row['impressions'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			);
		}

		return $rows;
	}

	/**
	 * Perform a request, cached.
	 *
	 * @param string                    $path  Path below the API base.
	 * @param array<string, mixed>|null $body  POST body, or null for a GET.
	 * @param bool                      $fresh Skip the cache.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function request( string $path, ?array $body, bool $fresh ): array|WP_Error {
		$cache_key = self::key( $path, $body );

		if ( ! $fresh ) {
			$cached = get_transient( $cache_key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = self::send( $path, $body, false );

		// A 401 usually means the cached token expired early rather than that
		// the credentials are wrong, so it is worth exactly one more attempt
		// with a freshly minted one.
		if ( $result instanceof WP_Error && 'wpcseo_gsc_expired' === $result->get_error_code() ) {
			Token::forget();

			$result = self::send( $path, $body, true );
		}

		if ( $result instanceof WP_Error ) {
			return 'wpcseo_gsc_expired' === $result->get_error_code()
				? self::status_error( 401, array() )
				: $result;
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Perform one request.
	 *
	 * @param string                    $path  Path below the API base.
	 * @param array<string, mixed>|null $body  POST body, or null for a GET.
	 * @param bool                      $fresh Mint a new token rather than reusing one.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function send( string $path, ?array $body, bool $fresh ): array|WP_Error {
		$token = Token::get( $fresh );

		if ( $token instanceof WP_Error ) {
			return $token;
		}

		$args = array(
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = null === $body
			? wp_remote_get( self::BASE . $path, $args )
			: wp_remote_post( self::BASE . $path, $args );

		if ( $response instanceof WP_Error ) {
			return new WP_Error(
				'wpcseo_gsc_network',
				__( 'Could not reach the Search Console API. Check the site can make outbound HTTPS requests.', 'wp-custom-seo' )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data   = is_array( $data ) ? $data : array();

		if ( 401 === $status ) {
			return new WP_Error( 'wpcseo_gsc_expired', __( 'The access token was rejected.', 'wp-custom-seo' ) );
		}

		if ( $status < 200 || $status > 299 ) {
			return self::status_error( $status, $data );
		}

		return $data;
	}

	/**
	 * Cache key for a request.
	 *
	 * The generation counter is part of the key, so clearing the cache is one
	 * option write rather than a scan for transient rows — which is the only
	 * approach that also works when an object cache is holding them.
	 *
	 * @param string                    $path Path below the API base.
	 * @param array<string, mixed>|null $body POST body.
	 */
	private static function key( string $path, ?array $body ): string {
		$generation = (int) get_option( self::GENERATION, 0 );

		return 'wpcseo_gsc_' . md5( $generation . $path . (string) wp_json_encode( $body ) );
	}

	/**
	 * Translate an API failure into something an administrator can act on.
	 *
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $data   Decoded reply.
	 */
	private static function status_error( int $status, array $data ): WP_Error {
		$reason = (string) ( $data['error']['errors'][0]['reason'] ?? '' );

		$message = match ( true ) {
			401 === $status => __( 'Google rejected the credentials. Save the key file again, and check the server clock is correct.', 'wp-custom-seo' ),
			403 === $status && 'insufficientPermissions' === $reason => sprintf(
				/* translators: %s: service account email address. */
				__( 'The service account cannot read this property. In Search Console, add %s as a user of it.', 'wp-custom-seo' ),
				Account::email()
			),
			403 === $status => __( 'Access was refused. Check the Search Console API is enabled for the key file\'s project, and that the service account has been added to the property.', 'wp-custom-seo' ),
			404 === $status => __( 'Search Console has no such property. Its URL must match exactly, including the protocol and any trailing slash.', 'wp-custom-seo' ),
			429 === $status => __( 'Google rate-limited this request. The plugin caches results for twelve hours; wait a little and try again.', 'wp-custom-seo' ),
			$status >= 500 => __( 'The Search Console API is unavailable right now. Try again shortly.', 'wp-custom-seo' ),
			default => sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The Search Console API returned an unexpected response (HTTP %d).', 'wp-custom-seo' ),
				$status
			),
		};

		return new WP_Error( 'wpcseo_gsc_api', $message );
	}

	/**
	 * Abandon every cached reply.
	 *
	 * The old entries are left to expire on their own rather than hunted down:
	 * nothing will ask for them again, and they carry a twelve-hour lifetime.
	 */
	public static function flush(): void {
		update_option( self::GENERATION, (int) get_option( self::GENERATION, 0 ) + 1, false );
	}
}
