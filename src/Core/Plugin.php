<?php
/**
 * Plugin bootstrap.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Core;

use WPCustomSeo\Abilities\Abilities;
use WPCustomSeo\Crawlers\AiCrawlers;
use WPCustomSeo\Crawlers\LlmsTxt;
use WPCustomSeo\Admin\AIPage;
use WPCustomSeo\Admin\AuditPage;
use WPCustomSeo\Admin\BriefPage;
use WPCustomSeo\Admin\Menu;
use WPCustomSeo\Admin\MetaBox;
use WPCustomSeo\Admin\BulkEditorPage;
use WPCustomSeo\AI\Manager as AIManager;
use WPCustomSeo\API\AIRoutes;
use WPCustomSeo\Admin\LinksPage;
use WPCustomSeo\Admin\NotFoundPage;
use WPCustomSeo\Admin\RedirectsPage;
use WPCustomSeo\Admin\SchemaPage;
use WPCustomSeo\Admin\SearchConsolePage;
use WPCustomSeo\Admin\ToolsPage;
use WPCustomSeo\API\Routes;
use WPCustomSeo\API\SchemaRoutes;
use WPCustomSeo\Database\Migrator;
use WPCustomSeo\Entities\Authors;
use WPCustomSeo\Links\Scanner;
use WPCustomSeo\Local\Locations;
use WPCustomSeo\Local\Schema as LocalSchema;
use WPCustomSeo\Redirects\Engine;
use WPCustomSeo\Redirects\NotFound;
use WPCustomSeo\Reports\Schedule as ReportSchedule;
use WPCustomSeo\CLI\Command as CliCommand;
use WPCustomSeo\Schema\Cache;
use WPCustomSeo\Schema\Conflicts;
use WPCustomSeo\Schema\Output;
use WPCustomSeo\SEO\Breadcrumbs;
use WPCustomSeo\SEO\Frontend;
use WPCustomSeo\SEO\Hreflang;
use WPCustomSeo\SEO\Meta;
use WPCustomSeo\SEO\Terms;
use WPCustomSeo\Media\ImageSeo;
use WPCustomSeo\Admin\ImagesPage;
use WPCustomSeo\Admin\RobotsPage;
use WPCustomSeo\Admin\GeoPage;
use WPCustomSeo\Crawlers\RobotsTxt;
use WPCustomSeo\Schema\Video;
use WPCustomSeo\Sitemap\Sitemap;
use WPCustomSeo\Social\Social;
use WPCustomSeo\WooCommerce\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together on `plugins_loaded`.
 *
 * Admin-only modules are never loaded on the front end.
 */
final class Plugin {

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Boot the plugin.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		Settings::init();

		// Modules that contribute settings fields register before anything that
		// reads a setting. Settings::schema() caches itself on first call, and
		// the first call comes from whichever module asks first — so a filter
		// added after that point is added to a schema nobody will build again,
		// and its fields disappear without an error. Sitemap::init() reads a
		// setting, which is where that line falls.
		Hreflang::init();
		RobotsTxt::init();
		Video::init();

		Meta::init();
		Terms::init();
		Routes::init();
		SchemaRoutes::init();
		AIRoutes::init();
		AIManager::init();
		Cache::init();
		Sitemap::init();
		Breadcrumbs::init();
		Engine::init();
		NotFound::init();
		Scanner::init();
		Locations::init();
		LocalSchema::init();
		Integration::init();
		ReportSchedule::init();
		AiCrawlers::init();
		LlmsTxt::init();
		Abilities::init();
		CliCommand::init();

		if ( is_admin() ) {
			Menu::init();
			MetaBox::init();
			AuditPage::init();
			SchemaPage::init();
			RedirectsPage::init();
			NotFoundPage::init();
			LinksPage::init();
			BulkEditorPage::init();
			AIPage::init();
			BriefPage::init();
			ToolsPage::init();
			SearchConsolePage::init();
			ImagesPage::init();
			RobotsPage::init();
			GeoPage::init();
			ImageSeo::init();
			Conflicts::init();
			Authors::init();
			add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );
		} else {
			Frontend::init();
			Output::init();
			Social::init();
		}

		/**
		 * Fires once the plugin has registered its hooks.
		 */
		do_action( 'wpcseo_loaded' );
	}

	/**
	 * Apply pending migrations after a plugin update, where no activation hook runs.
	 */
	public static function maybe_upgrade(): void {
		if ( Migrator::is_pending() ) {
			Migrator::run();
		}

		if ( get_option( 'wpcseo_version' ) !== \WPCustomSeo\VERSION ) {
			update_option( 'wpcseo_version', \WPCustomSeo\VERSION, true );
		}
	}
}
