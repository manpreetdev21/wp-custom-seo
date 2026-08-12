<?php
/**
 * Redirect storage.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Redirects;

use WPCustomSeo\Database\Tables;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes redirect rules.
 *
 * Every query is prepared. Sources are normalised to a site-relative path
 * before storage so the same rule cannot be entered twice under different
 * spellings of the same URL.
 */
final class Redirects {

	/**
	 * HTTP status codes this manager will issue.
	 *
	 * @return array<int, string>
	 */
	public static function types(): array {
		return array(
			301 => __( '301 — Moved permanently', 'wp-custom-seo' ),
			302 => __( '302 — Found (temporary)', 'wp-custom-seo' ),
			307 => __( '307 — Temporary redirect', 'wp-custom-seo' ),
			308 => __( '308 — Permanent redirect', 'wp-custom-seo' ),
		);
	}

	/**
	 * Reduce a URL to the path this plugin matches on.
	 *
	 * Query strings are deliberately ignored: they are carried over to the
	 * target instead, so one rule covers a URL however it was tagged.
	 *
	 * @param string $url Absolute URL or path.
	 */
	public static function normalize( string $url ): string {
		$url  = trim( $url );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $path ) {
			$path = $url;
		}

		$path = rawurldecode( $path );

		// Drop the subdirectory WordPress is installed in, so rules stay
		// portable if the site later moves to the domain root.
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( '' !== $home && '/' !== $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
		}

		$path = '/' . ltrim( $path, '/' );
		$path = strtolower( $path );

		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}

		return '' === $path ? '/' : $path;
	}

	/**
	 * Hash used for the unique index.
	 *
	 * @param string $source Normalised source path.
	 */
	private static function hash( string $source ): string {
		return md5( $source );
	}

	/**
	 * Find an enabled literal rule for a path.
	 *
	 * @param string $path Normalised path.
	 */
	public static function match( string $path ): ?object {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table; indexed lookup by hash.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant, not input.
				"SELECT * FROM {$table} WHERE source_hash = %s AND is_regex = 0 AND enabled = 1 LIMIT 1",
				self::hash( $path )
			)
		);

		return null === $row ? null : $row;
	}

	/**
	 * All enabled regular-expression rules.
	 *
	 * Cached because they must all be tested in turn, unlike literal rules
	 * which are found by index.
	 *
	 * @return object[]
	 */
	public static function regex_rules(): array {
		$cached = get_transient( 'wpcseo_redirect_regex' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table; no user input.
		$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE is_regex = 1 AND enabled = 1 ORDER BY id ASC LIMIT 200" );

		set_transient( 'wpcseo_redirect_regex', $rows, HOUR_IN_SECONDS );

		return $rows;
	}

	/**
	 * Whether any enabled rule exists, cached so ordinary requests cost nothing.
	 */
	public static function has_any(): bool {
		$cached = get_transient( 'wpcseo_redirect_count' );

		if ( false !== $cached ) {
			return (int) $cached > 0;
		}

		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table; no user input.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE enabled = 1" );

		set_transient( 'wpcseo_redirect_count', $count, HOUR_IN_SECONDS );

		return $count > 0;
	}

	/**
	 * Clear the cached rule set.
	 */
	public static function flush_cache(): void {
		delete_transient( 'wpcseo_redirect_regex' );
		delete_transient( 'wpcseo_redirect_count' );
	}

	/**
	 * Validate and store a rule.
	 *
	 * @param array<string, mixed> $data Rule fields.
	 *
	 * @return int|WP_Error New row id.
	 */
	public static function insert( array $data ): int|WP_Error {
		global $wpdb;

		$clean = self::validate( $data );

		if ( $clean instanceof WP_Error ) {
			return $clean;
		}

		if ( null !== self::get_by_source( $clean['source'] ) ) {
			return new WP_Error(
				'wpcseo_redirect_exists',
				__( 'A redirect for that URL already exists.', 'wp-custom-seo' )
			);
		}

		$clean['source_hash'] = self::hash( $clean['source'] );
		$clean['created']     = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$inserted = $wpdb->insert( Tables::redirects(), $clean );

		if ( ! $inserted ) {
			return new WP_Error( 'wpcseo_redirect_insert_failed', __( 'The redirect could not be saved.', 'wp-custom-seo' ) );
		}

		self::flush_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a rule.
	 *
	 * @param int                  $id   Rule id.
	 * @param array<string, mixed> $data Rule fields.
	 *
	 * @return true|WP_Error
	 */
	public static function update( int $id, array $data ): bool|WP_Error {
		global $wpdb;

		$clean = self::validate( $data, $id );

		if ( $clean instanceof WP_Error ) {
			return $clean;
		}

		$existing = self::get_by_source( $clean['source'] );

		if ( null !== $existing && (int) $existing->id !== $id ) {
			return new WP_Error(
				'wpcseo_redirect_exists',
				__( 'Another redirect already uses that URL.', 'wp-custom-seo' )
			);
		}

		$clean['source_hash'] = self::hash( $clean['source'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$wpdb->update( Tables::redirects(), $clean, array( 'id' => $id ) );

		self::flush_cache();

		return true;
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $id Rule id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$deleted = (bool) $wpdb->delete( Tables::redirects(), array( 'id' => $id ), array( '%d' ) );

		self::flush_cache();

		return $deleted;
	}

	/**
	 * Enable or disable a rule.
	 *
	 * @param int  $id      Rule id.
	 * @param bool $enabled Desired state.
	 */
	public static function set_enabled( int $id, bool $enabled ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$wpdb->update( Tables::redirects(), array( 'enabled' => $enabled ? 1 : 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );

		self::flush_cache();
	}

	/**
	 * Fetch one rule.
	 *
	 * @param int $id Rule id.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);

		return null === $row ? null : $row;
	}

	/**
	 * Fetch a rule by its normalised source.
	 *
	 * @param string $source Normalised path.
	 */
	public static function get_by_source( string $source ): ?object {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE source_hash = %s", self::hash( $source ) )
		);

		return null === $row ? null : $row;
	}

	/**
	 * List rules for the admin table.
	 *
	 * @param array{search?: string, per_page?: int, page?: int, orderby?: string, order?: string} $args Query arguments.
	 *
	 * @return object[]
	 */
	public static function all( array $args = array() ): array {
		global $wpdb;

		$table    = Tables::redirects();
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $per_page );
		$search   = trim( (string) ( $args['search'] ?? '' ) );

		$allowed = array( 'id', 'source', 'type', 'hits', 'last_used', 'created' );
		$orderby = in_array( (string) ( $args['orderby'] ?? '' ), $allowed, true ) ? (string) $args['orderby'] : 'id';
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a constant; orderby and order are whitelisted above; values are bound.
					"SELECT * FROM {$table} WHERE source LIKE %s OR target LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					$like,
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
	 * Count rules, optionally matching a search.
	 *
	 * @param string $search Search term.
	 */
	public static function count( string $search = '' ): int {
		global $wpdb;

		$table = Tables::redirects();

		if ( '' !== trim( $search ) ) {
			$like = '%' . $wpdb->esc_like( trim( $search ) ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
					"SELECT COUNT(*) FROM {$table} WHERE source LIKE %s OR target LIKE %s",
					$like,
					$like
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Record that a rule fired.
	 *
	 * @param int $id Rule id.
	 */
	public static function record_hit( int $id ): void {
		global $wpdb;

		$table = Tables::redirects();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from a constant.
				"UPDATE {$table} SET hits = hits + 1, last_used = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$id
			)
		);
	}

	/**
	 * Validate and normalise submitted fields.
	 *
	 * @param array<string, mixed> $data Raw fields.
	 * @param int                  $id   Rule being edited, or 0.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate( array $data, int $id = 0 ): array|WP_Error {
		$is_regex = ! empty( $data['is_regex'] );
		$source   = trim( (string) ( $data['source'] ?? '' ) );
		$target   = trim( (string) ( $data['target'] ?? '' ) );
		$type     = (int) ( $data['type'] ?? 301 );

		if ( '' === $source ) {
			return new WP_Error( 'wpcseo_redirect_no_source', __( 'Enter the URL to redirect from.', 'wp-custom-seo' ) );
		}

		if ( '' === $target ) {
			return new WP_Error( 'wpcseo_redirect_no_target', __( 'Enter the URL to redirect to.', 'wp-custom-seo' ) );
		}

		if ( ! array_key_exists( $type, self::types() ) ) {
			return new WP_Error( 'wpcseo_redirect_bad_type', __( 'Choose a valid redirect type.', 'wp-custom-seo' ) );
		}

		if ( $is_regex ) {
			if ( ! self::is_valid_regex( $source ) ) {
				return new WP_Error(
					'wpcseo_redirect_bad_regex',
					__( 'That pattern is not a valid regular expression.', 'wp-custom-seo' )
				);
			}
		} else {
			$source = self::normalize( $source );

			$loop = self::detect_loop( $source, $target, $id );

			if ( $loop instanceof WP_Error ) {
				return $loop;
			}
		}

		return array(
			'source'   => $source,
			'target'   => self::sanitize_target( $target ),
			'type'     => $type,
			'is_regex' => $is_regex ? 1 : 0,
			'enabled'  => isset( $data['enabled'] ) ? (int) (bool) $data['enabled'] : 1,
		);
	}

	/**
	 * Keep a target to a safe absolute URL or site-relative path.
	 *
	 * @param string $target Raw target.
	 */
	public static function sanitize_target( string $target ): string {
		$target = trim( $target );

		if ( preg_match( '#^https?://#i', $target ) ) {
			return sanitize_url( $target );
		}

		// Anything that is not http(s) and not a path — javascript:, data: —
		// is rejected by being treated as a path.
		$target = preg_replace( '#^[a-z][a-z0-9+.-]*:#i', '', $target ) ?? $target;

		return '/' . ltrim( $target, '/' );
	}

	/**
	 * Whether a pattern compiles.
	 *
	 * @param string $pattern Regular expression, without delimiters.
	 */
	public static function is_valid_regex( string $pattern ): bool {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- compiling is the test; a warning here is the answer, not a fault.
		return false !== @preg_match( self::delimit( $pattern ), '' );
	}

	/**
	 * Wrap a stored pattern in delimiters.
	 *
	 * @param string $pattern Stored pattern.
	 */
	public static function delimit( string $pattern ): string {
		return '#' . str_replace( '#', '\#', $pattern ) . '#i';
	}

	/**
	 * Reject rules that would send a visitor in circles.
	 *
	 * @param string $source Normalised source path.
	 * @param string $target Target URL or path.
	 * @param int    $id     Rule being edited, or 0.
	 *
	 * @return WP_Error|null
	 */
	public static function detect_loop( string $source, string $target, int $id = 0 ): ?WP_Error {
		$target_path = self::normalize( $target );
		$external    = preg_match( '#^https?://#i', $target )
			&& wp_parse_url( $target, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $external ) {
			return null;
		}

		if ( $target_path === $source ) {
			return new WP_Error(
				'wpcseo_redirect_self',
				__( 'That redirect points at itself, which would loop forever.', 'wp-custom-seo' )
			);
		}

		// Walk the chain the visitor would actually follow.
		$seen    = array( $source );
		$current = $target_path;

		for ( $step = 0; $step < 10; $step++ ) {
			$next = self::get_by_source( $current );

			if ( null === $next || (int) $next->id === $id || ! $next->enabled ) {
				return null;
			}

			if ( in_array( $current, $seen, true ) ) {
				break;
			}

			$seen[]  = $current;
			$current = self::normalize( (string) $next->target );

			if ( $current === $source ) {
				return new WP_Error(
					'wpcseo_redirect_loop',
					sprintf(
						/* translators: %s: the URL that closes the loop. */
						__( 'This would create a loop: the target already redirects back to %s.', 'wp-custom-seo' ),
						$source
					)
				);
			}
		}

		return new WP_Error(
			'wpcseo_redirect_chain',
			__( 'This would create a redirect chain more than ten steps long. Point the redirect at its final destination instead.', 'wp-custom-seo' )
		);
	}
}
