<?php
/**
 * The /llms.txt content map.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Crawlers;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\SEO\Frontend;
use WPCustomSeo\SEO\Meta;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a curated markdown map of the site at /llms.txt.
 *
 * **What this is worth, stated plainly.** llms.txt is a proposed convention,
 * not a standard. At the time of writing no major AI company has committed to
 * reading it in production, and Google has said its Search team does not use
 * it. Publishing one is not known to affect how any assistant sees the site.
 *
 * What it *is* is a short, accurate, machine-readable index of what this site
 * contains — which is useful to anyone pointing a tool at the site on purpose,
 * costs one cached page render, and is trivially removed. It is off by default
 * and the setting says all of the above, so nobody switches it on expecting
 * something it has not been shown to do.
 *
 * The listing follows the same rule as the sitemap: a page marked noindex is
 * left out. Asking search engines to ignore a page and then advertising it in a
 * content map would be the site contradicting itself.
 */
final class LlmsTxt {

	/**
	 * Settings key.
	 */
	public const SETTING = 'enable_llms_txt';

	/**
	 * Path served, relative to the site root.
	 */
	public const PATH = 'llms.txt';

	/**
	 * Cache key for the rendered file.
	 */
	private const CACHE = 'wpcseo_llms_txt';

	/**
	 * Entries listed per section before the list is cut.
	 *
	 * A map of everything is not a map. Past a hundred or so links per section
	 * the file stops being a summary and becomes a worse sitemap.
	 */
	private const PER_SECTION = 100;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		// Matched on the request path rather than through a rewrite rule: a
		// rewrite would need flushing to take effect and would not resolve at
		// all on a site using plain permalinks. This works on any of them and
		// adds one string comparison to a request.
		add_action( 'parse_request', array( self::class, 'maybe_serve' ) );

		// The map is built from published content, so it goes stale the moment
		// any of it changes.
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post' ) as $hook ) {
			add_action( $hook, array( self::class, 'flush' ) );
		}

		add_action( 'update_option_' . Settings::OPTION, array( self::class, 'flush' ) );
	}

	/**
	 * Whether the file is switched on.
	 */
	public static function is_enabled(): bool {
		return Settings::enabled( self::SETTING );
	}

	/**
	 * Serve the file when this request is for it.
	 */
	public static function maybe_serve(): void {
		if ( ! self::is_enabled() || ! self::is_request() ) {
			return;
		}

		$body = self::render();

		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		// Cached for a day at the edge: the content changes when the site does,
		// and the plugin clears its own copy on every save.
		header( 'Cache-Control: public, max-age=86400' );

		// Served as text/markdown, not HTML. Running it through an HTML escaper
		// would corrupt the document; each field is flattened and its markdown
		// metacharacters escaped as the document is built instead.
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * Whether the current request is for the file.
	 */
	private static function is_request(): bool {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		// The site may live in a subdirectory, so the comparison is against the
		// path WordPress is installed at rather than the domain root.
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return trailingslashit( $home ) . self::PATH === $path;
	}

	/**
	 * Discard the cached file.
	 */
	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * The rendered document.
	 */
	public static function render(): string {
		$cached = get_transient( self::CACHE );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$document = self::build();

		set_transient( self::CACHE, $document, DAY_IN_SECONDS );

		return $document;
	}

	/**
	 * Build the document.
	 */
	public static function build(): string {
		$lines = array( '# ' . self::text( (string) get_bloginfo( 'name' ) ) );

		$summary = self::summary();

		if ( '' !== $summary ) {
			$lines[] = '';
			$lines[] = '> ' . $summary;
		}

		foreach ( self::sections() as $label => $entries ) {
			if ( ! $entries ) {
				continue;
			}

			$lines[] = '';
			$lines[] = '## ' . self::text( (string) $label );
			$lines[] = '';

			foreach ( $entries as $entry ) {
				$line = sprintf( '- [%s](%s)', $entry['title'], $entry['url'] );

				if ( '' !== $entry['description'] ) {
					$line .= ': ' . $entry['description'];
				}

				$lines[] = $line;
			}
		}

		$document = implode( "\n", $lines ) . "\n";

		/**
		 * Filters the rendered llms.txt document.
		 *
		 * @param string $document Markdown document.
		 */
		return (string) apply_filters( 'wpcseo_llms_txt', $document );
	}

	/**
	 * The one-line description of the site.
	 */
	private static function summary(): string {
		$summary = trim( (string) Settings::get( 'llms_txt_summary', '' ) );

		if ( '' === $summary ) {
			$summary = (string) get_bloginfo( 'description' );
		}

		return self::text( $summary );
	}

	/**
	 * The listed content, grouped by post type.
	 *
	 * @return array<string, array<int, array{title: string, url: string, description: string}>>
	 */
	private static function sections(): array {
		$sections = array();

		foreach ( Meta::post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( ! $object ) {
				continue;
			}

			$entries = self::entries( $post_type );

			if ( $entries ) {
				$sections[ (string) $object->labels->name ] = $entries;
			}
		}

		/**
		 * Filters the sections listed in llms.txt.
		 *
		 * @param array<string, array<int, array<string, string>>> $sections Sections keyed by heading.
		 */
		return (array) apply_filters( 'wpcseo_llms_txt_sections', $sections );
	}

	/**
	 * Entries for one post type.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array<int, array{title: string, url: string, description: string}>
	 */
	private static function entries( string $post_type ): array {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => self::PER_SECTION,
				'orderby'                => 'menu_order date',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				// The same rule the sitemap follows: a page the site asks
				// search engines to ignore is not advertised here either.
				'meta_query'             => array( Meta::exclude_noindex_clause() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the same exclusion the sitemap uses, on an indexed key.
			)
		);

		$entries = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$entries[] = array(
				'title'       => self::text( (string) get_the_title( $post ) ),
				'url'         => (string) get_permalink( $post ),
				'description' => self::text( self::description( $post ) ),
			);
		}

		return $entries;
	}

	/**
	 * The description for one entry.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function description( WP_Post $post ): string {
		$description = trim( (string) Meta::get( $post->ID, Meta::DESCRIPTION ) );

		if ( '' === $description ) {
			$description = Frontend::fallback_description( $post->ID );
		}

		return $description;
	}

	/**
	 * Flatten a value for a markdown line.
	 *
	 * Newlines and the brackets that carry meaning in a markdown link are the
	 * only things that could break the document's shape.
	 *
	 * @param string $value Raw value.
	 */
	private static function text( string $value ): string {
		$value = wp_strip_all_tags( $value );
		$value = (string) preg_replace( '/\s+/u', ' ', $value );
		$value = str_replace( array( '[', ']' ), array( '\[', '\]' ), $value );

		return trim( $value );
	}
}
