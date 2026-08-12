<?php
/**
 * API credential storage.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

defined( 'ABSPATH' ) || exit;

/*
 * base64 is used here to make raw ciphertext and nonce bytes safe to store in
 * a text option — not to obscure code. There is no encoded PHP anywhere in
 * this file.
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
 */

/**
 * Stores provider API keys encrypted at rest.
 *
 * Keys live in their own non-autoloaded option, never in the main settings
 * array: that array is read on nearly every request, is dumped by debug tools,
 * and is exposed through the Settings API. A credential has no business there.
 *
 * **What the encryption does and does not protect.** The key is derived from
 * the site's `AUTH_SALT`, which lives in wp-config.php on the same server. That
 * defends against a leaked database — a stolen SQL dump, a backup on shared
 * storage, a compromised read-only replica — which is how API keys usually
 * escape. It does not defend against an attacker who already has the
 * filesystem, because they have the salt too. Claiming otherwise would be
 * worse than not encrypting.
 */
final class Credentials {

	public const OPTION = 'wpcseo_ai_credentials';

	/**
	 * Cached decrypted keys for this request.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $cache = null;

	/**
	 * Whether the platform can encrypt.
	 *
	 * Sodium is bundled with PHP 7.2 and later, so on the plugin's supported
	 * range this is always true; the check exists so a hardened build that
	 * removed the extension degrades loudly rather than silently.
	 */
	public static function can_encrypt(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) && defined( 'AUTH_SALT' ) && '' !== AUTH_SALT;
	}

	/**
	 * Read a provider's key, or an empty string.
	 *
	 * @param string $provider Provider id.
	 */
	public static function get( string $provider ): string {
		if ( null === self::$cache ) {
			self::$cache = array();

			foreach ( (array) get_option( self::OPTION, array() ) as $id => $stored ) {
				$plain = self::decrypt( (string) $stored );

				if ( '' !== $plain ) {
					self::$cache[ (string) $id ] = $plain;
				}
			}
		}

		return self::$cache[ $provider ] ?? '';
	}

	/**
	 * Whether a key is stored for a provider.
	 *
	 * @param string $provider Provider id.
	 */
	public static function has( string $provider ): bool {
		return '' !== self::get( $provider );
	}

	/**
	 * The last four characters of a stored key, for confirming which key is saved.
	 *
	 * Never returns the key itself: this value reaches the settings screen, and
	 * anything rendered there is in the page source.
	 *
	 * @param string $provider Provider id.
	 */
	public static function hint( string $provider ): string {
		$key = self::get( $provider );

		return '' === $key ? '' : str_repeat( '•', 8 ) . mb_substr( $key, -4 );
	}

	/**
	 * Store or clear a provider's key.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Raw key, or an empty string to remove it.
	 */
	public static function set( string $provider, string $key ): void {
		$stored = (array) get_option( self::OPTION, array() );
		$key    = trim( $key );

		if ( '' === $key ) {
			unset( $stored[ $provider ] );
		} else {
			$stored[ $provider ] = self::encrypt( $key );
		}

		// Never autoloaded: a credential should not be read into memory on
		// requests that will not use it.
		update_option( self::OPTION, $stored, false );

		self::$cache = null;
	}

	/**
	 * Remove every stored key.
	 */
	public static function flush(): void {
		delete_option( self::OPTION );

		self::$cache = null;
	}

	/**
	 * Encrypt a value for storage.
	 *
	 * @param string $plain Raw key.
	 */
	private static function encrypt( string $plain ): string {
		if ( ! self::can_encrypt() ) {
			return 'plain:' . base64_encode( $plain );
		}

		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );

		return 'v1:' . base64_encode( $nonce . $cipher );
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $stored Stored value.
	 */
	private static function decrypt( string $stored ): string {
		if ( str_starts_with( $stored, 'plain:' ) ) {
			$decoded = base64_decode( substr( $stored, 6 ), true );

			return false === $decoded ? '' : $decoded;
		}

		if ( ! str_starts_with( $stored, 'v1:' ) || ! self::can_encrypt() ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, 3 ), true );

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );

		// A false here means the salt changed since the key was saved. The
		// administrator has to re-enter it; there is nothing to recover.
		return false === $plain ? '' : $plain;
	}

	/**
	 * Derive the encryption key from the site's authentication salt.
	 */
	private static function key(): string {
		return sodium_crypto_generichash( 'wpcseo-credentials|' . AUTH_SALT, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
