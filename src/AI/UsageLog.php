<?php
/**
 * AI usage log.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Database\Tables;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/*
 * Every query here reads or writes the plugin's own usage table, for which
 * there is no WordPress API. Table names come from constants, never input, and
 * every value is bound. Stated once rather than above each query.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */

/**
 * Records what was asked of which provider, and what it cost.
 *
 * Prompts and completions are deliberately **not** stored. They contain page
 * content, they would make the table grow without bound, and a log is not a
 * place to keep a copy of the site's content. Only metadata is kept.
 *
 * No cost estimate is calculated. Provider pricing changes, varies by tier and
 * by negotiated rate, and inventing a number would be worse than showing none:
 * token counts are recorded so an administrator can price them against their
 * own bill.
 */
final class UsageLog {

	/**
	 * Record a completed request.
	 *
	 * @param string            $provider Provider id.
	 * @param string            $model    Model id.
	 * @param string            $action   Action id.
	 * @param Response|WP_Error $result Outcome.
	 * @param int               $post_id  Related post, or 0.
	 */
	public static function record( string $provider, string $model, string $action, Response|WP_Error $result, int $post_id = 0 ): void {
		global $wpdb;

		$success = ! $result instanceof WP_Error;

		$wpdb->insert(
			Tables::ai_usage(),
			array(
				'provider'      => mb_substr( $provider, 0, 40 ),
				'model'         => mb_substr( $model, 0, 80 ),
				'action'        => mb_substr( $action, 0, 40 ),
				'post_id'       => $post_id,
				'user_id'       => get_current_user_id(),
				'success'       => $success ? 1 : 0,
				'error_code'    => $success ? '' : mb_substr( $result->get_error_code(), 0, 60 ),
				'input_tokens'  => $success ? $result->input_tokens : 0,
				'output_tokens' => $success ? $result->output_tokens : 0,
				'duration_ms'   => $success ? $result->duration_ms : 0,
				'created'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Recent entries.
	 *
	 * @param int $limit Rows to return.
	 *
	 * @return object[]
	 */
	public static function recent( int $limit = 25 ): array {
		global $wpdb;

		$table = Tables::ai_usage();

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, $limit ) )
		);
	}

	/**
	 * Aggregate counters.
	 *
	 * @return array{total: int, ok: int, failed: int, input: int, output: int}
	 */
	public static function totals(): array {
		global $wpdb;

		$table = Tables::ai_usage();

		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
				SUM(success = 1) AS ok,
				SUM(success = 0) AS failed,
				SUM(input_tokens) AS input,
				SUM(output_tokens) AS output
			FROM {$table}",
			ARRAY_A
		);

		$row = is_array( $row ) ? $row : array();

		return array(
			'total'  => (int) ( $row['total'] ?? 0 ),
			'ok'     => (int) ( $row['ok'] ?? 0 ),
			'failed' => (int) ( $row['failed'] ?? 0 ),
			'input'  => (int) ( $row['input'] ?? 0 ),
			'output' => (int) ( $row['output'] ?? 0 ),
		);
	}

	/**
	 * How many requests this user has made in the last hour.
	 *
	 * @param int $user_id User id.
	 */
	public static function recent_count_for_user( int $user_id ): int {
		global $wpdb;

		$table = Tables::ai_usage();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND created > %s",
				$user_id,
				gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
			)
		);
	}

	/**
	 * Empty the log.
	 */
	public static function clear(): int {
		global $wpdb;

		$table = Tables::ai_usage();

		return (int) $wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Drop entries past the retention window.
	 */
	public static function prune(): int {
		$days = (int) Settings::get( 'ai_log_retention', 90 );

		if ( $days <= 0 ) {
			return 0;
		}

		global $wpdb;

		$table = Tables::ai_usage();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created < %s",
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			)
		);
	}
}
