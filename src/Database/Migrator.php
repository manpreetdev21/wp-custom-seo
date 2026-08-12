<?php
/**
 * Versioned database migrations.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Runs numbered migrations once each, tracking the applied version per site.
 *
 * Later phases register their tables by hooking `wpcseo_migrations` and adding
 * `'<version>' => callable`. Callbacks receive the table prefix and should use
 * dbDelta() so re-running them is safe.
 */
final class Migrator {

	public const VERSION_OPTION = 'wpcseo_db_version';

	/**
	 * Migrations keyed by schema version, ascending.
	 *
	 * Phase 1 ships none: no table has a reason to exist yet.
	 *
	 * @return array<string, callable>
	 */
	public static function migrations(): array {
		/**
		 * Filters the migration map.
		 *
		 * @param array<string, callable> $migrations Keyed by schema version.
		 */
		$migrations = (array) apply_filters( 'wpcseo_migrations', array() );

		uksort( $migrations, 'version_compare' );

		return $migrations;
	}

	/**
	 * The schema version this codebase expects.
	 */
	public static function target_version(): string {
		$versions = array_keys( self::migrations() );

		return $versions ? (string) end( $versions ) : '0';
	}

	/**
	 * The schema version currently applied to this site.
	 */
	public static function current_version(): string {
		return (string) get_option( self::VERSION_OPTION, '0' );
	}

	/**
	 * Whether migrations are outstanding.
	 */
	public static function is_pending(): bool {
		return version_compare( self::current_version(), self::target_version(), '<' );
	}

	/**
	 * Apply every migration newer than the stored version.
	 *
	 * Each migration commits its own version, so a fatal partway through does
	 * not replay the migrations that already succeeded.
	 *
	 * @return string The version reached.
	 */
	public static function run(): string {
		global $wpdb;

		$current = self::current_version();

		foreach ( self::migrations() as $version => $callback ) {
			$version = (string) $version;

			if ( version_compare( $current, $version, '>=' ) || ! is_callable( $callback ) ) {
				continue;
			}

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$callback( $wpdb->prefix . 'wpcseo_', $wpdb->get_charset_collate() );

			update_option( self::VERSION_OPTION, $version, false );
			$current = $version;
		}

		return $current;
	}

	/**
	 * Table names created by this plugin on the current site.
	 *
	 * Used by uninstall. Only tables carrying the plugin prefix are listed, so
	 * no other plugin's data can be reached through it.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		global $wpdb;

		$like = $wpdb->esc_like( $wpdb->prefix . 'wpcseo_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- SHOW TABLES has no API equivalent.
		return (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}
}
