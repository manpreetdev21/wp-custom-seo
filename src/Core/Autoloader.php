<?php
/**
 * PSR-4 fallback autoloader, used when Composer's vendor/ is absent.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PSR-4 loader for the WPCustomSeo\ namespace.
 */
final class Autoloader {

	private const PREFIX = 'WPCustomSeo\\';

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Map a class name to a file under src/ and require it.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( ! str_starts_with( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$path     = __DIR__ . '/../' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
