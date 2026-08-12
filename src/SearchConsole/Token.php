<?php
/**
 * Google access tokens from a signed assertion.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SearchConsole;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/*
 * base64 here is JWT encoding, which is defined in terms of base64url. It is
 * not obfuscation, and nothing in this file encodes PHP.
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
 */

/**
 * Exchanges a signed JWT for a short-lived access token.
 *
 * This is Google's `urn:ietf:params:oauth:grant-type:jwt-bearer` flow: build a
 * claim set, sign it with the service account's private key, and post it. No
 * library is needed for that — a JWT is two base64url segments, a signature,
 * and one call to openssl_sign — and pulling in a Google SDK would drag a
 * whole HTTP stack into a plugin that already has one in WordPress itself.
 *
 * Tokens are cached for slightly less than their hour so a page of reports is
 * one token, not one per request, and the cache is dropped whenever the key
 * file changes.
 */
final class Token {

	private const ENDPOINT = 'https://oauth2.googleapis.com/token';

	/**
	 * Read-only Search Console.
	 */
	public const SCOPE_SEARCH_CONSOLE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Read-only Analytics.
	 */
	public const SCOPE_ANALYTICS = 'https://www.googleapis.com/auth/analytics.readonly';

	private const TRANSIENT = 'wpcseo_gsc_token';

	/**
	 * How long a token is kept. Google issues them for an hour; the margin
	 * covers a slow request and a clock that is a little out.
	 */
	private const TTL = 3000;

	/**
	 * A usable access token for one scope.
	 *
	 * Tokens are minted and cached per scope rather than requesting both at
	 * once. A single assertion covering both would fail entirely if only one of
	 * the two APIs were enabled on the Google project — which would mean
	 * switching on Analytics could break Search Console, or the reverse. Kept
	 * apart, each degrades on its own.
	 *
	 * @param bool   $fresh Skip the cache.
	 * @param string $scope Which API the token is for.
	 *
	 * @return string|WP_Error
	 */
	public static function get( bool $fresh = false, string $scope = self::SCOPE_SEARCH_CONSOLE ): string|WP_Error {
		$key = self::TRANSIENT . '_' . md5( $scope );

		if ( ! $fresh ) {
			$cached = get_transient( $key );

			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		if ( ! Account::is_connected() ) {
			return new WP_Error(
				'wpcseo_gsc_not_connected',
				__( 'No Search Console service account is connected.', 'wp-custom-seo' )
			);
		}

		$assertion = self::assertion( $scope );

		if ( $assertion instanceof WP_Error ) {
			return $assertion;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $assertion,
				),
			)
		);

		if ( $response instanceof WP_Error ) {
			return new WP_Error(
				'wpcseo_gsc_network',
				__( 'Could not reach Google to authenticate. Check the site can make outbound HTTPS requests.', 'wp-custom-seo' )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data   = is_array( $data ) ? $data : array();

		if ( 200 !== $status || '' === (string) ( $data['access_token'] ?? '' ) ) {
			return self::token_error( $status, $data );
		}

		$token = (string) $data['access_token'];

		set_transient( $key, $token, self::TTL );

		return $token;
	}

	/**
	 * Drop every cached token.
	 */
	public static function forget(): void {
		foreach ( array( self::SCOPE_SEARCH_CONSOLE, self::SCOPE_ANALYTICS ) as $scope ) {
			delete_transient( self::TRANSIENT . '_' . md5( $scope ) );
		}
	}

	/**
	 * Build and sign the assertion.
	 *
	 * @param string $scope Scope to request.
	 *
	 * @return string|WP_Error
	 */
	private static function assertion( string $scope ): string|WP_Error {
		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error(
				'wpcseo_gsc_no_openssl',
				__( 'This server has no OpenSSL support, so the request to Google cannot be signed.', 'wp-custom-seo' )
			);
		}

		$now = time();

		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);

		$claims = array(
			'iss'   => Account::email(),
			'scope' => $scope,
			'aud'   => self::ENDPOINT,
			'exp'   => $now + 3600,
			'iat'   => $now,
		);

		$payload = self::encode( $header ) . '.' . self::encode( $claims );

		$signature = '';
		$signed    = openssl_sign( $payload, $signature, Account::private_key(), OPENSSL_ALGO_SHA256 );

		if ( ! $signed || '' === $signature ) {
			return new WP_Error(
				'wpcseo_gsc_sign_failed',
				__( 'The service account key could not be used to sign the request. Download a fresh key file and save it again.', 'wp-custom-seo' )
			);
		}

		return $payload . '.' . self::base64url( $signature );
	}

	/**
	 * JSON-encode and base64url a JWT segment.
	 *
	 * @param array<string, mixed> $segment Header or claim set.
	 */
	private static function encode( array $segment ): string {
		return self::base64url( (string) wp_json_encode( $segment ) );
	}

	/**
	 * Encode as base64url: base64 with a URL-safe alphabet and no padding.
	 *
	 * @param string $value Raw bytes.
	 */
	public static function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Turn an authentication failure into something actionable.
	 *
	 * Google's own message is included when it names a cause, because
	 * "invalid_grant" versus "invalid_client" is the difference between a clock
	 * problem and a wrong key, and only the reply knows which.
	 *
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $data   Decoded reply.
	 */
	private static function token_error( int $status, array $data ): WP_Error {
		$code = (string) ( $data['error'] ?? '' );

		$message = match ( $code ) {
			'invalid_grant'  => __( 'Google rejected the signed request. This is usually a server clock that is out by more than a few minutes, or a key that has been deleted in the Google Cloud console.', 'wp-custom-seo' ),
			'invalid_client' => __( 'Google does not recognise this service account. Check the key file belongs to a project where the Search Console API is enabled.', 'wp-custom-seo' ),
			'invalid_scope'  => __( 'The service account is not allowed to read Search Console. Enable the Search Console API for its project.', 'wp-custom-seo' ),
			default          => sprintf(
				/* translators: %d: HTTP status code. */
				__( 'Google refused the authentication request (HTTP %d).', 'wp-custom-seo' ),
				$status
			),
		};

		return new WP_Error( 'wpcseo_gsc_auth', $message );
	}
}
