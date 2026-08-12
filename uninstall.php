<?php
/**
 * Uninstall routine.
 *
 * Removes plugin data only when the administrator opted in via
 * SEO → Settings → Advanced. Data belonging to other plugins is never touched.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Core/Autoloader.php';
\WPCustomSeo\Core\Autoloader::register();

/**
 * Remove this plugin's data from the current site.
 */
function wpcseo_uninstall_site(): void {
	global $wpdb;

	// Scheduled events go whatever the setting says. They are not data — they
	// are instructions to run code that is about to stop existing, and leaving
	// them behind means rows in the cron option forever, for a hook nothing
	// will ever listen to again.
	foreach ( \WPCustomSeo\Core\Activator::cron_hooks() as $wpcseo_hook ) {
		wp_clear_scheduled_hook( $wpcseo_hook );
	}

	$settings = get_option( \WPCustomSeo\Core\Settings::OPTION, array() );

	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	foreach ( \WPCustomSeo\Database\Migrator::tables() as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- identifier cannot be bound; source is SHOW TABLES filtered by our prefix.
		$wpdb->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '', (string) $table ) . '`' );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API for prefix-wide meta deletion.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_wpcseo_' ) . '%' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API for prefix-wide meta deletion.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_wpcseo_' ) . '%' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API for prefix-wide meta deletion.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_wpcseo_' ) . '%' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API to list options by prefix.
	$options = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'wpcseo_' ) . '%',
			$wpdb->esc_like( '_transient_wpcseo_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wpcseo_' ) . '%'
		)
	);

	foreach ( (array) $options as $option ) {
		delete_option( (string) $option );
	}

	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );

		if ( $role instanceof WP_Role ) {
			$role->remove_cap( \WPCustomSeo\Core\Capabilities::MANAGE );
		}
	}
}

if ( is_multisite() ) {
	$wpcseo_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wpcseo_site_ids as $wpcseo_site_id ) {
		switch_to_blog( (int) $wpcseo_site_id );
		wpcseo_uninstall_site();
		restore_current_blog();
	}
} else {
	wpcseo_uninstall_site();
}
