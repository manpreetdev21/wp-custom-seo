<?php
/**
 * Settings framework: one option, one schema, WordPress Settings API.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Core;

use WPCustomSeo\Crawlers\AiCrawlers;
use WPCustomSeo\SEO\Weights;

defined( 'ABSPATH' ) || exit;

/**
 * Schema-driven settings store.
 *
 * All settings live in a single autoloaded option so reading them costs no
 * extra queries. New modules add their fields via the `wpcseo_settings_schema`
 * filter rather than registering options of their own.
 */
final class Settings {

	public const OPTION = 'wpcseo_settings';

	public const GROUP = 'wpcseo_settings_group';

	/**
	 * Settings API page slug. Owned here so Core does not depend on Admin.
	 */
	public const PAGE = 'wp-custom-seo-settings';

	/**
	 * Cached, filtered schema.
	 *
	 * @var array<string, array{title: string, fields: array<string, array<string, mixed>>}>|null
	 */
	private static ?array $schema = null;

	/**
	 * Cached, sanitised settings values.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $values = null;

	/**
	 * Hook the Settings API registration.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );

		// options.php defaults to manage_options; keep saving tied to our capability.
		add_filter(
			'option_page_capability_' . self::GROUP,
			static fn (): string => Capabilities::MANAGE
		);
	}

	/**
	 * Section and field definitions.
	 *
	 * Field types: checkbox, text, textarea, select.
	 *
	 * @return array<string, array{title: string, fields: array<string, array<string, mixed>>}>
	 */
	public static function schema(): array {
		if ( null !== self::$schema ) {
			return self::$schema;
		}

		$schema = array(
			'general'     => array(
				'title'  => __( 'General', 'wp-custom-seo' ),
				'fields' => array(
					'enable_seo'         => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable SEO output', 'wp-custom-seo' ),
						'description' => __( 'Master switch for front-end SEO output. Turn off to silence the plugin without deactivating it.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'enable_analysis'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable content analysis', 'wp-custom-seo' ),
						'description' => __( 'Run the editor SEO analysis. Analysis never runs on the front end.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'enable_schema'      => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable schema output', 'wp-custom-seo' ),
						'description' => __( 'Output JSON-LD structured data.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'enable_breadcrumbs' => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable breadcrumbs', 'wp-custom-seo' ),
						'description' => __( 'Provide the breadcrumb shortcode, block and template function.', 'wp-custom-seo' ),
						'default'     => false,
					),
				),
			),
			'titles'      => array(
				'title'  => __( 'Titles & Meta', 'wp-custom-seo' ),
				'fields' => array(
					'title_separator'     => array(
						'type'        => 'select',
						'label'       => __( 'Title separator', 'wp-custom-seo' ),
						'description' => __( 'Character used by the %%sep%% variable.', 'wp-custom-seo' ),
						'default'     => '-',
						'options'     => array(
							'-' => '-',
							'–' => '–',
							'—' => '—',
							'·' => '·',
							'•' => '•',
							'|' => '|',
							'/' => '/',
						),
					),
					'title_home'          => array(
						'type'        => 'text',
						'label'       => __( 'Front page title', 'wp-custom-seo' ),
						'description' => __( 'Available variables: %%sitename%%, %%sitedesc%%, %%sep%%, %%page%%, %%currentyear%%.', 'wp-custom-seo' ),
						'default'     => '%%sitename%% %%sep%% %%sitedesc%%',
					),
					'title_singular'      => array(
						'type'        => 'text',
						'label'       => __( 'Post, page and product title', 'wp-custom-seo' ),
						'description' => __( 'Used when a post has no SEO title of its own. Variables: %%title%%, %%sitename%%, %%sep%%, %%page%%, %%date%%.', 'wp-custom-seo' ),
						'default'     => '%%title%% %%sep%% %%sitename%%',
					),
					'title_term'          => array(
						'type'        => 'text',
						'label'       => __( 'Category, tag and taxonomy title', 'wp-custom-seo' ),
						'description' => __( 'Variables: %%term_title%%, %%sitename%%, %%sep%%, %%page%%.', 'wp-custom-seo' ),
						'default'     => '%%term_title%% %%sep%% %%sitename%%',
					),
					'title_author'        => array(
						'type'        => 'text',
						'label'       => __( 'Author archive title', 'wp-custom-seo' ),
						'description' => __( 'Variables: %%author%%, %%sitename%%, %%sep%%, %%page%%.', 'wp-custom-seo' ),
						'default'     => '%%author%% %%sep%% %%sitename%%',
					),
					'title_archive'       => array(
						'type'        => 'text',
						'label'       => __( 'Post type and date archive title', 'wp-custom-seo' ),
						'description' => __( 'Variables: %%archive_type%%, %%date%%, %%sitename%%, %%sep%%, %%page%%.', 'wp-custom-seo' ),
						'default'     => '%%archive_type%% %%sep%% %%sitename%%',
					),
					'title_search'        => array(
						'type'        => 'text',
						'label'       => __( 'Search results title', 'wp-custom-seo' ),
						'description' => __( 'Variables: %%searchphrase%%, %%sitename%%, %%sep%%, %%page%%.', 'wp-custom-seo' ),
						'default'     => __( 'Search for %%searchphrase%% %%sep%% %%sitename%%', 'wp-custom-seo' ),
					),
					'title_404'           => array(
						'type'        => 'text',
						'label'       => __( 'Not found title', 'wp-custom-seo' ),
						'description' => __( 'Variables: %%sitename%%, %%sep%%.', 'wp-custom-seo' ),
						'default'     => __( 'Page not found %%sep%% %%sitename%%', 'wp-custom-seo' ),
					),
					'description_excerpt' => array(
						'type'        => 'checkbox',
						'label'       => __( 'Fall back to the excerpt for meta descriptions', 'wp-custom-seo' ),
						'description' => __( 'When a post has no meta description, derive one from its excerpt or opening content. A description you write yourself is always better.', 'wp-custom-seo' ),
						'default'     => true,
					),
				),
			),
			'social'      => array(
				'title'  => __( 'Social', 'wp-custom-seo' ),
				'fields' => array(
					'social_open_graph'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Output Open Graph tags', 'wp-custom-seo' ),
						'description' => __( 'Used by Facebook, LinkedIn, WhatsApp, Slack and most other platforms that unfurl links.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'social_twitter'       => array(
						'type'        => 'checkbox',
						'label'       => __( 'Output X/Twitter card tags', 'wp-custom-seo' ),
						'description' => __( 'Falls back to the Open Graph values wherever a card-specific value is not set.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'social_twitter_card'  => array(
						'type'        => 'select',
						'label'       => __( 'Card type', 'wp-custom-seo' ),
						'description' => __( 'A large-image card is downgraded automatically when a page has no image to show.', 'wp-custom-seo' ),
						'default'     => 'summary_large_image',
						'options'     => array(
							'summary_large_image' => __( 'Large image', 'wp-custom-seo' ),
							'summary'             => __( 'Summary', 'wp-custom-seo' ),
						),
					),
					'social_twitter_site'  => array(
						'type'        => 'text',
						'label'       => __( 'Site X/Twitter handle', 'wp-custom-seo' ),
						'description' => __( 'Without the @.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'social_default_image' => array(
						'type'        => 'text',
						'label'       => __( 'Default sharing image URL', 'wp-custom-seo' ),
						'description' => __( 'Used when a page has neither a social image nor a featured image. Absolute URL.', 'wp-custom-seo' ),
						'default'     => '',
					),
				),
			),
			'sitemap'     => array(
				'title'  => __( 'Sitemap & Breadcrumbs', 'wp-custom-seo' ),
				'fields' => array(
					'enable_sitemap'        => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable the XML sitemap', 'wp-custom-seo' ),
						'description' => __( 'Shapes the sitemap WordPress already serves at /wp-sitemap.xml rather than publishing a second, competing one. Noindexed content is excluded and a lastmod date is added.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'sitemap_taxonomies'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Include category and tag archives', 'wp-custom-seo' ),
						'description' => __( 'Turn off if term archives are thin or duplicate your content.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'sitemap_authors'       => array(
						'type'        => 'checkbox',
						'label'       => __( 'Include author archives', 'wp-custom-seo' ),
						'description' => __( 'Usually worth turning off on a single-author site.', 'wp-custom-seo' ),
						'default'     => false,
					),
					'breadcrumb_separator'  => array(
						'type'        => 'text',
						'label'       => __( 'Breadcrumb separator', 'wp-custom-seo' ),
						'description' => __( 'Hidden from screen readers, so it is decoration only.', 'wp-custom-seo' ),
						'default'     => '/',
					),
					'breadcrumb_home_label' => array(
						'type'        => 'text',
						'label'       => __( 'Breadcrumb home label', 'wp-custom-seo' ),
						'description' => __( 'Leave empty for “Home”.', 'wp-custom-seo' ),
						'default'     => '',
					),
				),
			),
			'ai'          => array(
				'title'  => __( 'AI', 'wp-custom-seo' ),
				'fields' => array(
					'ai_provider'      => array(
						'type'        => 'select',
						'label'       => __( 'Provider', 'wp-custom-seo' ),
						'description' => __( 'API keys are entered below the settings form and are stored encrypted, separately from these settings.', 'wp-custom-seo' ),
						'default'     => '',
						'options'     => array(
							''          => __( 'None — AI features off', 'wp-custom-seo' ),
							'anthropic' => __( 'Anthropic (Claude)', 'wp-custom-seo' ),
							'openai'    => __( 'OpenAI', 'wp-custom-seo' ),
							'gemini'    => __( 'Google Gemini', 'wp-custom-seo' ),
						),
					),
					'ai_model'         => array(
						'type'        => 'text',
						'label'       => __( 'Model', 'wp-custom-seo' ),
						'description' => __( 'Leave empty to use the provider default. Model identifiers change often, so this is free text rather than a list that would go stale — copy the exact id from your provider\'s documentation.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'ai_temperature'   => array(
						'type'        => 'select',
						'label'       => __( 'Temperature', 'wp-custom-seo' ),
						'description' => __( 'Higher values give more varied wording. Several current models reject this parameter outright, so it is omitted for those rather than breaking the request.', 'wp-custom-seo' ),
						'default'     => '0.7',
						'options'     => array(
							'0.2' => __( '0.2 — predictable', 'wp-custom-seo' ),
							'0.5' => __( '0.5', 'wp-custom-seo' ),
							'0.7' => __( '0.7 — balanced', 'wp-custom-seo' ),
							'1.0' => __( '1.0 — varied', 'wp-custom-seo' ),
						),
					),
					'ai_log_retention' => array(
						'type'        => 'select',
						'label'       => __( 'Keep usage entries for', 'wp-custom-seo' ),
						'description' => __( 'The log records provider, model, action, token counts and outcome. Prompts and generated text are never stored.', 'wp-custom-seo' ),
						'default'     => '90',
						'options'     => array(
							'30'  => __( '30 days', 'wp-custom-seo' ),
							'90'  => __( '90 days', 'wp-custom-seo' ),
							'365' => __( 'A year', 'wp-custom-seo' ),
							'0'   => __( 'Keep everything', 'wp-custom-seo' ),
						),
					),
				),
			),
			'local'       => array(
				'title'  => __( 'Local & Brand', 'wp-custom-seo' ),
				'fields' => array(
					'enable_local_seo'   => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable Local SEO', 'wp-custom-seo' ),
						'description' => __( 'Adds a Locations screen for business details, opening hours and LocalBusiness structured data.', 'wp-custom-seo' ),
						'default'     => false,
					),
					'local_schema_scope' => array(
						'type'        => 'select',
						'label'       => __( 'Publish location data on', 'wp-custom-seo' ),
						'description' => __( 'The front page is where a business states who it is. Publishing a shop address on every article would not describe those pages.', 'wp-custom-seo' ),
						'default'     => 'front',
						'options'     => array(
							'front' => __( 'The front page only', 'wp-custom-seo' ),
							'all'   => __( 'Every page', 'wp-custom-seo' ),
						),
					),
					'brand_name'         => array(
						'type'        => 'text',
						'label'       => __( 'Brand name', 'wp-custom-seo' ),
						'description' => __( 'Leave empty if the site has no brand distinct from the organization. Nothing is published unless this is filled in.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'brand_description'  => array(
						'type'        => 'textarea',
						'label'       => __( 'Brand description', 'wp-custom-seo' ),
						'description' => '',
						'default'     => '',
					),
					'brand_logo'         => array(
						'type'        => 'text',
						'label'       => __( 'Brand logo URL', 'wp-custom-seo' ),
						'description' => __( 'Absolute URL.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'brand_sameas'       => array(
						'type'        => 'textarea',
						'label'       => __( 'Brand profile URLs', 'wp-custom-seo' ),
						'description' => __( 'One absolute URL per line. Invalid lines are discarded.', 'wp-custom-seo' ),
						'default'     => '',
					),
				),
			),
			'woocommerce' => array(
				'title'  => __( 'WooCommerce', 'wp-custom-seo' ),
				'fields' => array(
					'enable_woocommerce'          => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable WooCommerce SEO', 'wp-custom-seo' ),
						'description' => __( 'Has no effect unless WooCommerce is active. Product structured data is built only from data the shop actually holds: no invented price, stock, SKU, brand or rating.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'woo_replace_structured_data' => array(
						'type'        => 'checkbox',
						'label'       => __( 'Replace WooCommerce structured data', 'wp-custom-seo' ),
						'description' => __( 'WooCommerce publishes its own product markup. Leaving both on means two descriptions of the same product. This removes one WooCommerce output hook and nothing else; unchecking it restores that output immediately.', 'wp-custom-seo' ),
						'default'     => false,
					),
				),
			),
			'links'       => array(
				'title'  => __( 'Internal Links', 'wp-custom-seo' ),
				'fields' => array(
					'enable_link_graph' => array(
						'type'        => 'checkbox',
						'label'       => __( 'Track internal links', 'wp-custom-seo' ),
						'description' => __( 'Records which content links to which when a post is saved, so orphan and under-linked reports stay current.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'orphan_critical'   => array(
						'type'        => 'select',
						'label'       => __( 'Treat as critical at or below', 'wp-custom-seo' ),
						'description' => __( 'Incoming internal links. Content in a navigation menu, the front page and the posts page are never reported.', 'wp-custom-seo' ),
						'default'     => '0',
						'options'     => array(
							'0' => __( '0 incoming links', 'wp-custom-seo' ),
							'1' => __( '1 incoming link', 'wp-custom-seo' ),
							'2' => __( '2 incoming links', 'wp-custom-seo' ),
						),
					),
					'decay_months'      => array(
						'type'        => 'select',
						'label'       => __( 'Flag pages untouched for', 'wp-custom-seo' ),
						'description' => __( 'Used by the site audit. Age alone never raises a page — it also has to be linked to or substantial, because evergreen content that was right years ago is still right.', 'wp-custom-seo' ),
						'default'     => '12',
						'options'     => array(
							'6'  => __( '6 months', 'wp-custom-seo' ),
							'12' => __( '12 months', 'wp-custom-seo' ),
							'24' => __( '2 years', 'wp-custom-seo' ),
							'36' => __( '3 years', 'wp-custom-seo' ),
						),
					),
					'orphan_warning'    => array(
						'type'        => 'select',
						'label'       => __( 'Treat as a warning at or below', 'wp-custom-seo' ),
						'description' => __( 'Anything above this is considered healthy. A page with few incoming links is not automatically a problem.', 'wp-custom-seo' ),
						'default'     => '1',
						'options'     => array(
							'1' => __( '1 incoming link', 'wp-custom-seo' ),
							'2' => __( '2 incoming links', 'wp-custom-seo' ),
							'3' => __( '3 incoming links', 'wp-custom-seo' ),
							'5' => __( '5 incoming links', 'wp-custom-seo' ),
						),
					),
				),
			),
			'redirects'   => array(
				'title'  => __( 'Redirects & 404s', 'wp-custom-seo' ),
				'fields' => array(
					'enable_redirects'    => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable the redirect manager', 'wp-custom-seo' ),
						'description' => __( 'Existing rules stay stored when this is off; they simply stop firing.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'monitor_404'         => array(
						'type'        => 'checkbox',
						'label'       => __( 'Log 404s', 'wp-custom-seo' ),
						'description' => __( 'Records the URL, referrer and hit count for requests that were not found. Repeat hits update one row rather than adding another.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'not_found_retention' => array(
						'type'        => 'select',
						'label'       => __( 'Keep 404 entries for', 'wp-custom-seo' ),
						'description' => __( 'Older entries are removed by a daily job, so a scanned site does not accumulate rows forever.', 'wp-custom-seo' ),
						'default'     => '30',
						'options'     => array(
							'7'   => __( '7 days', 'wp-custom-seo' ),
							'30'  => __( '30 days', 'wp-custom-seo' ),
							'90'  => __( '90 days', 'wp-custom-seo' ),
							'365' => __( 'A year', 'wp-custom-seo' ),
							'0'   => __( 'Keep everything', 'wp-custom-seo' ),
						),
					),
				),
			),
			'schema'      => array(
				'title'  => __( 'Schema & Entities', 'wp-custom-seo' ),
				'fields' => array(
					'schema_org_type'                  => array(
						'type'        => 'select',
						'label'       => __( 'Organization type', 'wp-custom-seo' ),
						'description' => __( 'Pick the most specific type that is actually accurate. A wrong type is worse than a general one.', 'wp-custom-seo' ),
						'default'     => 'Organization',
						'options'     => array(
							'Organization'            => __( 'Organization', 'wp-custom-seo' ),
							'Corporation'             => __( 'Corporation', 'wp-custom-seo' ),
							'LocalBusiness'           => __( 'Local business', 'wp-custom-seo' ),
							'NewsMediaOrganization'   => __( 'News media organization', 'wp-custom-seo' ),
							'EducationalOrganization' => __( 'Educational organization', 'wp-custom-seo' ),
							'GovernmentOrganization'  => __( 'Government organization', 'wp-custom-seo' ),
							'NGO'                     => __( 'Non-governmental organization', 'wp-custom-seo' ),
						),
					),
					'schema_org_name'                  => array(
						'type'        => 'text',
						'label'       => __( 'Organization name', 'wp-custom-seo' ),
						'description' => __( 'Leave empty to use the site title.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'schema_org_description'           => array(
						'type'        => 'textarea',
						'label'       => __( 'Organization description', 'wp-custom-seo' ),
						'description' => __( 'A short factual description of the organization.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'schema_org_logo'                  => array(
						'type'        => 'text',
						'label'       => __( 'Logo URL', 'wp-custom-seo' ),
						'description' => __( 'Absolute URL of the organization logo. Dimensions are read automatically when the image is in the media library.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'schema_org_email'                 => array(
						'type'        => 'text',
						'label'       => __( 'Contact email', 'wp-custom-seo' ),
						'description' => __( 'Output only if it is a valid address.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'schema_org_phone'                 => array(
						'type'        => 'text',
						'label'       => __( 'Contact telephone', 'wp-custom-seo' ),
						'description' => __( 'Include the international dialling code.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'schema_org_sameas'                => array(
						'type'        => 'textarea',
						'label'       => __( 'Official profile URLs', 'wp-custom-seo' ),
						'description' => __( 'One absolute URL per line, for profiles the organization genuinely controls. Invalid lines are discarded.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'schema_org_publishing_principles' => array(
						'type'        => 'text',
						'label'       => __( 'Publishing principles URL', 'wp-custom-seo' ),
						'description' => __( 'Only fill these in if the page genuinely exists. Never state a policy you do not publish.', 'wp-custom-seo' ),
						'default'     => '',
					),
					'schema_org_ownership'             => array(
						'type'        => 'text',
						'label'       => __( 'Ownership and funding URL', 'wp-custom-seo' ),
						'description' => '',
						'default'     => '',
					),
					'schema_org_corrections'           => array(
						'type'        => 'text',
						'label'       => __( 'Corrections policy URL', 'wp-custom-seo' ),
						'description' => '',
						'default'     => '',
					),
					'schema_org_feedback'              => array(
						'type'        => 'text',
						'label'       => __( 'Actionable feedback policy URL', 'wp-custom-seo' ),
						'description' => '',
						'default'     => '',
					),
					'schema_org_diversity'             => array(
						'type'        => 'text',
						'label'       => __( 'Diversity policy URL', 'wp-custom-seo' ),
						'description' => '',
						'default'     => '',
					),
					'schema_api_enabled'               => array(
						'type'        => 'checkbox',
						'label'       => __( 'Expose the aggregated schema API', 'wp-custom-seo' ),
						'description' => __( 'Publishes structured data for public content under /wp-json/wp-custom-seo/v1/schema/ so tools and assistants can read the site without crawling every page. Only content that is already publicly readable is included.', 'wp-custom-seo' ),
						'default'     => true,
					),
					'schema_api_batch'                 => array(
						'type'        => 'select',
						'label'       => __( 'Posts per API page', 'wp-custom-seo' ),
						'description' => __( 'Lower this for large catalogues. Individual post types can override it with the wpcseo_schema_api_batch filter.', 'wp-custom-seo' ),
						'default'     => '100',
						'options'     => array(
							'25'  => '25',
							'50'  => '50',
							'100' => '100',
							'200' => '200',
						),
					),
				),
			),
			'advanced'    => array(
				'title'  => __( 'Advanced', 'wp-custom-seo' ),
				'fields' => array(
					'delete_data_on_uninstall' => array(
						'type'        => 'checkbox',
						'label'       => __( 'Delete all plugin data on uninstall', 'wp-custom-seo' ),
						'description' => __( 'When enabled, deleting the plugin also removes its settings, metadata and tables. Data from other plugins is never touched.', 'wp-custom-seo' ),
						'default'     => false,
					),
					'debug_logging'            => array(
						'type'        => 'checkbox',
						'label'       => __( 'Enable debug logging', 'wp-custom-seo' ),
						'description' => __( 'Write plugin diagnostics to the WordPress debug log. API credentials are never logged.', 'wp-custom-seo' ),
						'default'     => false,
					),
					// Chosen on the Search Performance screen from the list the
					// connected account can actually read, so it is stored here
					// but never offered as a field to type into.
					'gsc_property'             => array(
						'type'    => 'text',
						'label'   => __( 'Search Console property', 'wp-custom-seo' ),
						'default' => '',
						'hidden'  => true,
					),
					'ga4_property'             => array(
						'type'    => 'text',
						'label'   => __( 'Analytics property id', 'wp-custom-seo' ),
						'default' => '',
						'hidden'  => true,
					),
				),
			),
		);

		$schema['ai_crawlers'] = AiCrawlers::section();

		$schema['reports'] = array(
			'title'       => __( 'Reports', 'wp-custom-seo' ),
			'description' => __( 'A periodic email summarising what the site audit found, and what Google reported if Search Console is connected. It is sent only when there is something to say — a report that arrives every week saying "nothing to report" is a report people stop reading.', 'wp-custom-seo' ),
			'fields'      => array(
				'enable_reports'    => array(
					'type'        => 'checkbox',
					'label'       => __( 'Email a periodic report', 'wp-custom-seo' ),
					'description' => __( 'Sent by WP-Cron, which runs when the site is visited. A site with no traffic may send late.', 'wp-custom-seo' ),
					'default'     => false,
				),
				'report_frequency'  => array(
					'type'    => 'select',
					'label'   => __( 'How often', 'wp-custom-seo' ),
					'default' => 'weekly',
					'options' => array(
						'weekly'  => __( 'Weekly', 'wp-custom-seo' ),
						'monthly' => __( 'Monthly', 'wp-custom-seo' ),
					),
				),
				'report_recipients' => array(
					'type'        => 'text',
					'label'       => __( 'Send to', 'wp-custom-seo' ),
					'description' => __( 'Comma separated. Leave empty to use the site administrator’s address.', 'wp-custom-seo' ),
					'default'     => '',
				),
			),
		);

		// The score model owns its own fields — one per analysis check — so
		// that adding a check adds its weight setting without touching this.
		$schema['score'] = Weights::section();

		/**
		 * Filters the settings schema.
		 *
		 * @param array $schema Sections keyed by id, each with `title` and `fields`.
		 */
		self::$schema = (array) apply_filters( 'wpcseo_settings_schema', $schema );

		return self::$schema;
	}

	/**
	 * Flattened field definitions keyed by field id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields(): array {
		$fields = array();

		foreach ( self::schema() as $section ) {
			foreach ( (array) ( $section['fields'] ?? array() ) as $id => $field ) {
				$fields[ $id ] = $field;
			}
		}

		return $fields;
	}

	/**
	 * All settings, defaults filled in.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$values ) {
			$stored = get_option( self::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();

			self::$values = array();

			foreach ( self::fields() as $id => $field ) {
				self::$values[ $id ] = array_key_exists( $id, $stored )
					? $stored[ $id ]
					: ( $field['default'] ?? null );
			}
		}

		return self::$values;
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key      Field id.
	 * @param mixed  $fallback Returned when the field is unknown.
	 *
	 * @return mixed
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Read a boolean setting.
	 *
	 * @param string $key Field id.
	 */
	public static function enabled( string $key ): bool {
		return (bool) self::get( $key, false );
	}

	/**
	 * Persist a partial set of values, merged over what is stored.
	 *
	 * @param array<string, mixed> $values Raw values keyed by field id.
	 */
	public static function update( array $values ): void {
		// Merged over the effective values, not the raw stored ones. sanitize()
		// walks the whole schema and reads a missing checkbox as unchecked — so
		// merging over what happens to be in the option would switch off every
		// default-on setting that had never been explicitly saved. One screen
		// storing one unrelated value would silently disable SEO output.
		update_option( self::OPTION, self::sanitize( array_merge( self::all(), $values ) ), true );

		self::$values = null;
	}

	/**
	 * Register the option and render the Settings API sections and fields.
	 */
	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => array(),
			)
		);

		$page = self::PAGE;

		foreach ( self::schema() as $section_id => $section ) {
			$description = (string) ( $section['description'] ?? '' );

			add_settings_section(
				'wpcseo_section_' . $section_id,
				(string) ( $section['title'] ?? '' ),
				'' === $description
					? '__return_false'
					: static function () use ( $description ): void {
						printf( '<p class="description">%s</p>', esc_html( $description ) );
					},
				$page
			);

			foreach ( (array) ( $section['fields'] ?? array() ) as $id => $field ) {
				$id = (string) $id;

				// A hidden field is still part of the schema — it is validated
				// and saved like any other — it simply has no place on a screen
				// because another screen owns how it is chosen.
				if ( ! empty( $field['hidden'] ) ) {
					continue;
				}

				add_settings_field(
					'wpcseo_field_' . $id,
					(string) ( $field['label'] ?? $id ),
					array( self::class, 'render_field' ),
					$page,
					'wpcseo_section_' . $section_id,
					array(
						'id'        => $id,
						'field'     => $field,
						'label_for' => 'wpcseo-field-' . $id,
					)
				);
			}
		}
	}

	/**
	 * Render one field.
	 *
	 * @param array<string, mixed> $args Field id and definition.
	 */
	public static function render_field( array $args ): void {
		$id    = (string) $args['id'];
		$field = (array) $args['field'];
		$type  = (string) ( $field['type'] ?? 'text' );
		$value = self::get( $id );
		$name  = self::OPTION . '[' . $id . ']';
		$el_id = 'wpcseo-field-' . $id;
		$desc  = (string) ( $field['description'] ?? '' );

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> %4$s</label>',
					esc_attr( $el_id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( (string) ( $field['label'] ?? '' ) )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="6" class="large-text code">%3$s</textarea>',
					esc_attr( $el_id ),
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $el_id ), esc_attr( $name ) );
				foreach ( (array) ( $field['options'] ?? array() ) as $option_value => $option_label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( (string) $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( (string) $option_label )
					);
				}
				echo '</select>';
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text">',
					esc_attr( $el_id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
		}

		if ( '' !== $desc ) {
			printf( '<p class="description">%s</p>', esc_html( $desc ) );
		}
	}

	/**
	 * Sanitize a raw settings array against the schema.
	 *
	 * Unknown keys are dropped. Missing checkboxes become false, which is how
	 * unchecked boxes arrive from a form post.
	 *
	 * @param mixed $input Raw submitted values.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		foreach ( self::fields() as $id => $field ) {
			$type = (string) ( $field['type'] ?? 'text' );
			$raw  = $input[ $id ] ?? null;

			switch ( $type ) {
				case 'checkbox':
					$clean[ $id ] = ! empty( $raw );
					break;

				case 'textarea':
					$clean[ $id ] = sanitize_textarea_field( (string) $raw );
					break;

				case 'select':
					$allowed      = array_map( 'strval', array_keys( (array) ( $field['options'] ?? array() ) ) );
					$candidate    = sanitize_text_field( (string) $raw );
					$clean[ $id ] = in_array( $candidate, $allowed, true )
						? $candidate
						: (string) ( $field['default'] ?? '' );
					break;

				default:
					$clean[ $id ] = sanitize_text_field( (string) $raw );
			}
		}

		self::$values = null;

		/**
		 * Filters sanitized settings before they are stored.
		 *
		 * @param array $clean Sanitized values.
		 * @param array $input Raw input.
		 */
		return (array) apply_filters( 'wpcseo_sanitize_settings', $clean, $input );
	}

	/**
	 * Drop cached schema and values. Used by tests and after option deletion.
	 */
	public static function flush(): void {
		self::$schema = null;
		self::$values = null;
	}
}
