<?php
/**
 * Activation and deactivation.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Core;

use WPCustomSeo\Database\Migrator;
use WPCustomSeo\Links\Scanner;
use WPCustomSeo\Redirects\NotFound;
use WPCustomSeo\Reports\Schedule as ReportSchedule;

defined( 'ABSPATH' ) || exit;

/**
 * Sets up capabilities and schema on activation; leaves all data in place on
 * deactivation. Data removal happens only in uninstall.php, and only if the
 * administrator opted in.
 */
final class Activator {

	/**
	 * Roles granted the plugin capability on activation.
	 */
	private const DEFAULT_ROLES = array( 'administrator' );

	/**
	 * Run activation, network-wide when asked.
	 *
	 * @param bool $network_wide Whether the plugin was network-activated.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}

			return;
		}

		self::activate_site();
	}

	/**
	 * Activation work for a single site.
	 */
	public static function activate_site(): void {
		self::add_capabilities();
		Migrator::run();

		add_option( 'wpcseo_version', \WPCustomSeo\VERSION, '', true );
		update_option( 'wpcseo_version', \WPCustomSeo\VERSION, true );

		/**
		 * Fires after the plugin is activated on a site.
		 */
		do_action( 'wpcseo_activated' );
	}

	/**
	 * Every recurring event this plugin schedules.
	 *
	 * Taken from the constants the modules themselves declare, so a hook cannot
	 * be renamed in one place and left scheduled forever here. This list was a
	 * set of literal strings and it had already drifted once: the report added
	 * in a later phase was not in it, and would have gone on being scheduled
	 * after the plugin was switched off.
	 *
	 * @return string[]
	 */
	public static function cron_hooks(): array {
		return array(
			'wpcseo_daily_maintenance',
			'wpcseo_prune_ai_log',
			NotFound::CRON_HOOK,
			Scanner::REBUILD_HOOK,
			ReportSchedule::HOOK,
		);
	}

	/**
	 * Deactivation. Deliberately non-destructive.
	 *
	 * Nothing is deleted; the scheduled events are cleared because an event for
	 * a hook nobody listens to is a row that sits in the cron option for good.
	 */
	public static function deactivate(): void {
		foreach ( self::cron_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		/**
		 * Fires when the plugin is deactivated.
		 */
		do_action( 'wpcseo_deactivated' );
	}

	/**
	 * Grant the plugin capability to the default roles.
	 */
	public static function add_capabilities(): void {
		/**
		 * Filters which roles receive the plugin capability at activation.
		 *
		 * @param string[] $roles Role slugs.
		 */
		$roles = (array) apply_filters( 'wpcseo_default_capable_roles', self::DEFAULT_ROLES );

		foreach ( $roles as $role_name ) {
			$role = get_role( (string) $role_name );

			if ( $role instanceof \WP_Role ) {
				$role->add_cap( Capabilities::MANAGE );
			}
		}
	}
}
