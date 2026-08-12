<?php
/**
 * Search Console service account credentials.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SearchConsole;

use WPCustomSeo\AI\Credentials;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the Google service account this site talks to Search Console as.
 *
 * A service account is used rather than the OAuth authorization-code flow, and
 * that is a deliberate trade. The redirect flow would need a registered
 * redirect URI per site, a state nonce, refresh-token storage and a refresh
 * state machine — a lot of moving parts, each a place to leak a token. A
 * service account replaces all of it with one signed assertion: paste the key
 * file once, add its email as a user of the property in Search Console, done.
 *
 * The cost is that step in Search Console. It is a real cost, and the screen
 * says so rather than pretending the connection is one click.
 *
 * The key file is stored through the same encrypted store as the AI keys, with
 * the same honest limits: it defends against a leaked database, not against an
 * attacker who already has the filesystem.
 */
final class Account {

	/**
	 * Credential store id.
	 */
	public const ID = 'search_console';

	/**
	 * Parsed key file for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Validate and store a service account key file.
	 *
	 * @param string $json Raw JSON from the Google Cloud console.
	 *
	 * @return true|WP_Error
	 */
	public static function save( string $json ): bool|WP_Error {
		$json = trim( $json );

		if ( '' === $json ) {
			self::forget();

			return true;
		}

		if ( ! Credentials::can_encrypt() ) {
			return new WP_Error(
				'wpcseo_gsc_no_encryption',
				__( 'This server cannot encrypt credentials, so the key file will not be stored. Sodium and AUTH_SALT are both required.', 'wp-custom-seo' )
			);
		}

		$parsed = json_decode( $json, true );

		if ( ! is_array( $parsed ) ) {
			return new WP_Error(
				'wpcseo_gsc_bad_json',
				__( 'That is not valid JSON. Paste the whole key file, including the surrounding braces.', 'wp-custom-seo' )
			);
		}

		if ( 'service_account' !== ( $parsed['type'] ?? '' ) ) {
			return new WP_Error(
				'wpcseo_gsc_not_service_account',
				__( 'That JSON is not a service account key. In the Google Cloud console, create a service account and download a JSON key for it — an OAuth client file will not work here.', 'wp-custom-seo' )
			);
		}

		foreach ( array( 'client_email', 'private_key' ) as $required ) {
			if ( '' === (string) ( $parsed[ $required ] ?? '' ) ) {
				return new WP_Error(
					'wpcseo_gsc_incomplete',
					sprintf(
						/* translators: %s: JSON field name. */
						__( 'The key file has no "%s". Download a fresh key rather than editing this one.', 'wp-custom-seo' ),
						$required
					)
				);
			}
		}

		if ( ! str_contains( (string) $parsed['private_key'], 'PRIVATE KEY' ) ) {
			return new WP_Error(
				'wpcseo_gsc_bad_key',
				__( 'The private key in that file is not readable. It may have lost its line breaks in copying — download the file again and paste it unaltered.', 'wp-custom-seo' )
			);
		}

		Credentials::set( self::ID, $json );

		self::$cache = null;
		Token::forget();

		return true;
	}

	/**
	 * Discard the stored key and any token derived from it.
	 */
	public static function forget(): void {
		Credentials::set( self::ID, '' );

		self::$cache = null;
		Token::forget();
	}

	/**
	 * The parsed key file, or an empty array.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$parsed      = json_decode( Credentials::get( self::ID ), true );
			self::$cache = is_array( $parsed ) ? $parsed : array();
		}

		return self::$cache;
	}

	/**
	 * Whether a usable key file is stored.
	 */
	public static function is_connected(): bool {
		$account = self::all();

		return '' !== (string) ( $account['client_email'] ?? '' ) && '' !== (string) ( $account['private_key'] ?? '' );
	}

	/**
	 * The address to grant access to in Search Console.
	 */
	public static function email(): string {
		return (string) ( self::all()['client_email'] ?? '' );
	}

	/**
	 * The signing key.
	 */
	public static function private_key(): string {
		return (string) ( self::all()['private_key'] ?? '' );
	}

	/**
	 * Drop the parsed cache. Used by tests.
	 */
	public static function flush(): void {
		self::$cache = null;
	}
}
