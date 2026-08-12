<?php
/**
 * Custom table definitions.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's two custom tables, created through the migration system.
 *
 * These are the first tables the plugin has needed. Redirects and 404 hits are
 * high-write, high-row-count records queried by exact URL on ordinary page
 * loads, which is the one shape post meta genuinely cannot serve.
 *
 * The migration is registered at file load rather than on `plugins_loaded`,
 * because the activation hook fires without that action having run.
 */
final class Tables {

	public const VERSION = '1.1.0';

	public const LINKS_VERSION = '1.2.0';

	public const AI_VERSION = '1.3.0';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_migrations', array( self::class, 'register' ) );
	}

	/**
	 * Add this migration to the map.
	 *
	 * @param array<string, callable> $migrations Migrations keyed by version.
	 *
	 * @return array<string, callable>
	 */
	public static function register( array $migrations ): array {
		$migrations[ self::VERSION ]       = array( self::class, 'create' );
		$migrations[ self::LINKS_VERSION ] = array( self::class, 'create_links' );
		$migrations[ self::AI_VERSION ]    = array( self::class, 'create_ai_usage' );

		return $migrations;
	}

	/**
	 * AI usage table name.
	 */
	public static function ai_usage(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpcseo_ai_usage';
	}

	/**
	 * Create the AI usage table.
	 *
	 * Prompts and completions are deliberately absent: only metadata is kept.
	 *
	 * @param string $prefix  Plugin table prefix, supplied by the migrator.
	 * @param string $collate Charset and collation clause.
	 */
	public static function create_ai_usage( string $prefix, string $collate ): void {
		$usage = $prefix . 'ai_usage';

		dbDelta(
			"CREATE TABLE {$usage} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				provider varchar(40) NOT NULL,
				model varchar(80) NOT NULL,
				action varchar(40) NOT NULL,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				success tinyint(1) NOT NULL DEFAULT 0,
				error_code varchar(60) NULL DEFAULT NULL,
				input_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
				output_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
				duration_ms bigint(20) unsigned NOT NULL DEFAULT 0,
				created datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY created (created),
				KEY user_id (user_id)
			) {$collate};"
		);
	}

	/**
	 * Link graph table name.
	 */
	public static function links(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpcseo_links';
	}

	/**
	 * Create the link graph table.
	 *
	 * @param string $prefix  Plugin table prefix, supplied by the migrator.
	 * @param string $collate Charset and collation clause.
	 */
	public static function create_links( string $prefix, string $collate ): void {
		$links = $prefix . 'links';

		dbDelta(
			"CREATE TABLE {$links} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_id bigint(20) unsigned NOT NULL,
				target_id bigint(20) unsigned NOT NULL DEFAULT 0,
				url varchar(255) NOT NULL,
				anchor varchar(255) NULL DEFAULT NULL,
				type varchar(12) NOT NULL DEFAULT 'internal',
				PRIMARY KEY  (id),
				KEY source_id (source_id),
				KEY target_id (target_id),
				KEY type (type)
			) {$collate};"
		);
	}

	/**
	 * Redirect table name.
	 */
	public static function redirects(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpcseo_redirects';
	}

	/**
	 * Not-found log table name.
	 */
	public static function not_found(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpcseo_not_found';
	}

	/**
	 * Create or update the tables.
	 *
	 * @param string $prefix  Plugin table prefix, supplied by the migrator.
	 * @param string $collate Charset and collation clause.
	 */
	public static function create( string $prefix, string $collate ): void {
		$redirects = $prefix . 'redirects';
		$not_found = $prefix . 'not_found';

		// source_hash carries the unique index because a 255-character utf8mb4
		// column exceeds the InnoDB key length limit on older servers.
		dbDelta(
			"CREATE TABLE {$redirects} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source varchar(255) NOT NULL,
				source_hash char(32) NOT NULL,
				target text NOT NULL,
				type smallint(4) NOT NULL DEFAULT 301,
				is_regex tinyint(1) NOT NULL DEFAULT 0,
				enabled tinyint(1) NOT NULL DEFAULT 1,
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				last_used datetime NULL DEFAULT NULL,
				created datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source_hash (source_hash),
				KEY lookup (enabled,is_regex)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$not_found} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url varchar(255) NOT NULL,
				url_hash char(32) NOT NULL,
				referrer varchar(255) NULL DEFAULT NULL,
				user_agent varchar(255) NULL DEFAULT NULL,
				hits bigint(20) unsigned NOT NULL DEFAULT 1,
				first_seen datetime NULL DEFAULT NULL,
				last_seen datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY url_hash (url_hash),
				KEY last_seen (last_seen)
			) {$collate};"
		);
	}
}
