<?php
/**
 * Schema aggregation cache.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Version-keyed cache for aggregated schema.
 *
 * Transients are used rather than a bespoke layer because they already write
 * to a persistent object cache when one is installed and fall back to the
 * options table when it is not, which is exactly the required behaviour.
 *
 * Entries are never deleted individually. The cache key carries a version
 * built from the last content change, so a change simply makes every old key
 * unreachable and the stale entries expire on their own. That keeps post
 * saves free of cache-invalidation writes.
 */
final class Cache {

	public const VERSION_OPTION = 'wpcseo_schema_cache_version';

	private const TTL = 12 * HOUR_IN_SECONDS;

	private const PREFIX = 'wpcseo_s_';

	/**
	 * Hook the events that must invalidate everything.
	 */
	public static function init(): void {
		// Deleting a post does not move the last-modified time, and a profile
		// change alters Person nodes without touching any post at all.
		add_action( 'deleted_post', array( self::class, 'bump' ) );
		add_action( 'profile_update', array( self::class, 'bump' ) );
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'bump' ) );
	}

	/**
	 * Current cache version.
	 *
	 * Combines the manual counter with the last content change, so ordinary
	 * edits invalidate the cache without writing anything.
	 */
	public static function version(): string {
		return (string) get_option( self::VERSION_OPTION, '1' ) . ':' . (string) get_lastpostmodified( 'gmt' );
	}

	/**
	 * Invalidate every cached entry.
	 */
	public static function bump(): void {
		update_option( self::VERSION_OPTION, (string) ( (int) get_option( self::VERSION_OPTION, '1' ) + 1 ), true );
	}

	/**
	 * Build a transient key.
	 *
	 * Hashed to stay inside the transient name length limit.
	 *
	 * @param string ...$parts Key components.
	 */
	public static function key( string ...$parts ): string {
		return self::PREFIX . md5( self::version() . '|' . implode( '|', $parts ) );
	}

	/**
	 * Read a cached value.
	 *
	 * @param string $key Cache key.
	 *
	 * @return mixed Cached value, or null when absent.
	 */
	public static function get( string $key ): mixed {
		$value = get_transient( $key );

		return false === $value ? null : $value;
	}

	/**
	 * Store a value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 */
	public static function set( string $key, mixed $value ): void {
		set_transient( $key, $value, self::TTL );
	}

	/**
	 * Remove every entry this plugin has stored, then invalidate.
	 *
	 * Expired entries would fall out on their own; this exists so an
	 * administrator can reclaim the space immediately.
	 *
	 * @return int Number of entries removed.
	 */
	public static function flush(): int {
		global $wpdb;

		self::bump();

		if ( wp_using_ext_object_cache() ) {
			// Transients live in the object cache, where they cannot be
			// enumerated. The version bump has already orphaned them.
			return 0;
		}

		$like = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API lists transients by prefix.
		$names = (array) $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		foreach ( $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}

		return count( $names );
	}
}
