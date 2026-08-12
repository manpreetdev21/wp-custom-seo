<?php
/**
 * When the report is sent.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Reports;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the report's cron event in step with the setting.
 *
 * The schedule is reconciled whenever settings are saved rather than only at
 * activation, because the common failure of a feature like this is being
 * switched on in the admin and never actually scheduling anything.
 */
final class Schedule {

	public const HOOK = 'wpcseo_send_report';

	/**
	 * Frequencies offered, mapped to cron schedule names.
	 *
	 * @return array<string, string>
	 */
	public static function frequencies(): array {
		return array(
			'weekly'  => __( 'Weekly', 'wp-custom-seo' ),
			'monthly' => __( 'Monthly', 'wp-custom-seo' ),
		);
	}

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- adds a monthly interval, longer than core's longest, not a shorter one.
		add_action( self::HOOK, array( self::class, 'run' ) );

		// Saving settings is the moment the answer can change.
		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'reconcile' ) );
		add_action( 'add_option_' . Settings::OPTION, array( self::class, 'reconcile' ) );
	}

	/**
	 * Add a monthly interval, which core does not define.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Registered schedules.
	 *
	 * @return array<string, array{interval: int, display: string}>
	 */
	public static function add_interval( array $schedules ): array {
		$schedules['wpcseo_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once a month', 'wp-custom-seo' ),
		);

		return $schedules;
	}

	/**
	 * Schedule or unschedule to match the settings.
	 */
	public static function reconcile(): void {
		Settings::flush();

		$wanted    = Settings::enabled( 'enable_reports' );
		$frequency = 'monthly' === (string) Settings::get( 'report_frequency', 'weekly' ) ? 'wpcseo_monthly' : 'weekly';
		$existing  = wp_get_scheduled_event( self::HOOK );

		if ( ! $wanted ) {
			if ( $existing ) {
				wp_clear_scheduled_hook( self::HOOK );
			}

			return;
		}

		// Already scheduled at the right frequency: leave it alone rather than
		// rescheduling, which would push the next send back every save.
		if ( $existing && $existing->schedule === $frequency ) {
			return;
		}

		wp_clear_scheduled_hook( self::HOOK );

		// The first report is a day out, not immediate: a site that has just
		// switched this on has probably not finished setting the plugin up.
		wp_schedule_event( time() + DAY_IN_SECONDS, $frequency, self::HOOK );
	}

	/**
	 * Build and send, if there is anything to say.
	 */
	public static function run(): void {
		if ( ! Settings::enabled( 'enable_reports' ) ) {
			return;
		}

		$report = Report::build();

		if ( ! Report::is_worth_sending( $report ) ) {
			return;
		}

		Mailer::send( $report );
	}

	/**
	 * When the next report is due, or null.
	 */
	public static function next(): ?int {
		$timestamp = wp_next_scheduled( self::HOOK );

		return false === $timestamp ? null : (int) $timestamp;
	}
}
