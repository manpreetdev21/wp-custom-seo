<?php
/**
 * 404 monitor.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Redirects;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Records requests that ended in a 404.
 *
 * A repeat of an already-known URL costs one indexed UPDATE, so a site being
 * scanned by a bot does not grow the table without bound. Logging is off the
 * critical path for every request that resolves normally.
 */
final class NotFound {

	/**
	 * Public so the lifecycle can clear this event without repeating its name.
	 */
	public const CRON_HOOK = 'wpcseo_prune_not_found';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( self::CRON_HOOK, array( self::class, 'prune' ) );

		if ( ! Settings::enabled( 'monitor_404' ) ) {
			self::unschedule();

			return;
		}

		self::schedule();

		add_action( 'template_redirect', array( self::class, 'record' ), 5 );
	}

	/**
	 * Ensure the cleanup job exists.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Remove the cleanup job.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Log the current request if it 404ed.
	 */
	public static function record(): void {
		if ( ! is_404() || is_robots() || is_favicon() ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			return;
		}

		$url = Engine::request_path();

		if ( '' === $url || strlen( $url ) > 255 ) {
			return;
		}

		/**
		 * Filters whether a 404 is recorded.
		 *
		 * @param bool   $record Whether to log this hit.
		 * @param string $url    Requested path.
		 */
		if ( ! apply_filters( 'wpcseo_record_404', true, $url ) ) {
			return;
		}

		self::log( $url, self::header( 'HTTP_REFERER' ), self::header( 'HTTP_USER_AGENT' ) );
	}

	/**
	 * Read and trim a request header.
	 *
	 * @param string $key Server key.
	 */
	private static function header( string $key ): string {
		if ( ! isset( $_SERVER[ $key ] ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) ), 0, 255 );
	}

	/**
	 * Insert a hit, or bump the counter on one already seen.
	 *
	 * @param string $url        Requested path.
	 * @param string $referrer   Referring URL.
	 * @param string $user_agent Requesting agent.
	 */
	public static function log( string $url, string $referrer = '', string $user_agent = '' ): void {
		global $wpdb;

		$table = Tables::not_found();
		$now   = current_time( 'mysql', true );

		// One statement covers both the first sighting and every repeat.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant; every value is bound.
				"INSERT INTO {$table} (url, url_hash, referrer, user_agent, hits, first_seen, last_seen)
				VALUES (%s, %s, %s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = VALUES(referrer)",
				$url,
				md5( $url ),
				$referrer,
				$user_agent,
				$now,
				$now
			)
		);
	}

	/**
	 * List logged hits.
	 *
	 * @param array{search?: string, per_page?: int, page?: int, orderby?: string, order?: string} $args Query arguments.
	 *
	 * @return object[]
	 */
	public static function all( array $args = array() ): array {
		global $wpdb;

		$table    = Tables::not_found();
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $per_page );
		$search   = trim( (string) ( $args['search'] ?? '' ) );

		$allowed = array( 'id', 'url', 'hits', 'first_seen', 'last_seen' );
		$orderby = in_array( (string) ( $args['orderby'] ?? '' ), $allowed, true ) ? (string) $args['orderby'] : 'last_seen';
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant; orderby and order are whitelisted above; values are bound.
					"SELECT * FROM {$table} WHERE url LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					$like,
					$per_page,
					$offset
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant; orderby and order are whitelisted above.
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count logged hits.
	 *
	 * @param string $search Search term.
	 */
	public static function count( string $search = '' ): int {
		global $wpdb;

		$table = Tables::not_found();

		if ( '' !== trim( $search ) ) {
			$like = '%' . $wpdb->esc_like( trim( $search ) ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE url LIKE %s", $like ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Fetch one logged hit.
	 *
	 * @param int $id Row id.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		$table = Tables::not_found();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return null === $row ? null : $row;
	}

	/**
	 * Delete one logged hit.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		return (bool) $wpdb->delete( Tables::not_found(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Empty the log.
	 */
	public static function clear(): int {
		global $wpdb;

		$table = Tables::not_found();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
		return (int) $wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Drop entries older than the configured retention window.
	 */
	public static function prune(): int {
		$days = (int) Settings::get( 'not_found_retention', 30 );

		if ( $days <= 0 ) {
			return 0;
		}

		global $wpdb;

		$table  = Tables::not_found();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant; cutoff is bound.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", $cutoff ) );
	}
}
