<?php
/**
 * AI search visibility provider contract.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\GEO;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A provider that can report how a site appears in AI search answers.
 *
 * Nothing implements this yet, and that is deliberate. There is no public API
 * from OpenAI, Anthropic, Google or Perplexity that reports "how often was this
 * domain cited", the way Search Console reports impressions. The only ways to
 * get that data today are commercial rank-tracking services with their own
 * APIs, or scraping an assistant's interface — and scraping is against every
 * one of their terms of service, so this plugin will not ship it.
 *
 * What ships instead is the seam. When a site has a subscription to a service
 * that does report this, a small plugin implements this interface, calls
 * `Visibility::register()`, and the reporting screen shows its data. That is
 * the whole integration. Hardcoding one vendor would make the plugin's
 * usefulness depend on that vendor still existing.
 *
 * Both read methods return `WP_Error` rather than throwing, matching how the
 * Search Console client reports a failed call, so a screen can render the
 * reason instead of an empty table.
 */
interface VisibilityProvider {

	/**
	 * A stable machine name, e.g. `acme-rank`.
	 */
	public function id(): string;

	/**
	 * The name shown to an administrator.
	 */
	public function label(): string;

	/**
	 * Whether this provider is configured and usable right now.
	 */
	public function is_ready(): bool;

	/**
	 * Citations of this site observed in AI answers.
	 *
	 * @param string $start ISO 8601 start date.
	 * @param string $end   ISO 8601 end date.
	 *
	 * @return array<int, array{query: string, url: string, engine: string, observed: string}>|WP_Error
	 */
	public function citations( string $start, string $end ): array|WP_Error;

	/**
	 * Mentions of the site's brand, with or without a link.
	 *
	 * @param string $start ISO 8601 start date.
	 * @param string $end   ISO 8601 end date.
	 *
	 * @return array<int, array{query: string, engine: string, excerpt: string, observed: string}>|WP_Error
	 */
	public function mentions( string $start, string $end ): array|WP_Error;
}
