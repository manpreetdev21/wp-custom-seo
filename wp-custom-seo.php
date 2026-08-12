<?php
/**
 * Plugin Name:       WP Custom SEO
 * Plugin URI:        https://example.com/wp-custom-seo
 * Description:       On-page analysis, schema, sitemaps, redirects, internal links, a site audit, Search Console and Analytics reporting, and an AI assistant. Recommendations are recommendations: no score here represents a search engine's algorithm.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Manpreet Singh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-custom-seo
 * Domain Path:       /languages
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';

define( 'WP_CUSTOM_SEO_FILE', __FILE__ );
define( 'WP_CUSTOM_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_CUSTOM_SEO_URL', plugin_dir_url( __FILE__ ) );

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Core/Autoloader.php';
	Core\Autoloader::register();
}

require_once __DIR__ . '/src/functions.php';

// Registered here rather than on plugins_loaded: the activation hook fires
// without that action having run, and activation is when tables are created.
Database\Tables::init();

register_activation_hook( __FILE__, array( Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Core\Activator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( Core\Plugin::class, 'boot' ) );

/**
 * Load bundled translations.
 *
 * On `init` rather than earlier: WordPress warns when a text domain is loaded
 * before then, and nothing this plugin does on an earlier hook needs a
 * translated string. Since WordPress 4.6 a language pack in wp-content is found
 * without this — it is here for the .mo files shipped in /languages.
 */
add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'wp-custom-seo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
