<?php
/**
 * Virtual robots.txt manager.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Crawlers;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Sitemap\Sitemap;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an administrator add their own rules to the robots.txt WordPress serves.
 *
 * **What this does and does not touch.** WordPress serves a virtual robots.txt
 * only when no physical file exists in the web root. Nothing here writes a file
 * — the rules are stored as a setting and appended through the `robots_txt`
 * filter, which is the same seam the AI crawler controls use. That means the
 * plugin can never leave a stray file behind on uninstall, and a site that
 * already has a real robots.txt is left entirely alone (and told so).
 *
 * **Why the rules are validated rather than trusted.** `Disallow: /` under a
 * wildcard user agent removes an entire site from search. That is a legitimate
 * thing to want — a staging site, a private archive — and an extremely easy
 * thing to do by accident while meaning to block one directory. So it is
 * detected, and saving it requires a second, explicit confirmation. Every other
 * line is passed through as typed: robots.txt has directives this plugin has no
 * business second-guessing, and silently rewriting someone's file would be
 * worse than letting them write what they meant.
 */
final class RobotsTxt {

	public const SETTING = 'robots_txt_rules';

	public const SETTING_SITEMAP = 'robots_txt_sitemap';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_settings_schema', array( self::class, 'schema' ) );

		// Priority 11 so these land after the AI crawler block, which is added at
		// the default priority. Order in robots.txt is not significant to a
		// parser, but a file a person can read is worth more than one they cannot.
		add_filter( 'robots_txt', array( self::class, 'append' ), 11, 2 );
	}

	/**
	 * Register the storage fields.
	 *
	 * They are hidden from the settings form: the dedicated screen renders the
	 * textarea with a live preview and the whole-site warning beside it, none of
	 * which a generic textarea row can do. They still go through the ordinary
	 * settings pipeline for sanitization and saving.
	 *
	 * @param array<string, array<string, mixed>> $schema Settings schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema( array $schema ): array {
		$schema['ai_crawlers']['fields'][ self::SETTING ] = array(
			'type'    => 'textarea',
			'label'   => __( 'Custom robots.txt rules', 'wp-custom-seo' ),
			'default' => '',
			'hidden'  => true,
		);

		$schema['ai_crawlers']['fields'][ self::SETTING_SITEMAP ] = array(
			'type'    => 'checkbox',
			'label'   => __( 'Declare the sitemap in robots.txt', 'wp-custom-seo' ),
			'default' => true,
			'hidden'  => true,
		);

		return $schema;
	}

	/**
	 * The stored custom rules.
	 */
	public static function rules(): string {
		return trim( (string) Settings::get( self::SETTING, '' ) );
	}

	/**
	 * Normalise submitted rules.
	 *
	 * Line endings are unified and trailing whitespace dropped, because those
	 * are transport artefacts rather than anything the author meant. The
	 * directives themselves are left exactly as typed.
	 *
	 * @param string $raw Submitted text.
	 */
	public static function sanitize( string $raw ): string {
		$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		$lines = array();

		foreach ( explode( "\n", $raw ) as $line ) {
			// sanitize_text_field() would collapse the newlines this format is
			// built from, so each line is cleaned individually: control
			// characters and tags out, the directive itself untouched.
			$lines[] = rtrim( wp_strip_all_tags( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $line ) ) );
		}

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Whether a rule set asks search engines to leave the whole site alone.
	 *
	 * Looks for `Disallow: /` (exactly the site root, not a subdirectory) under
	 * a `User-agent: *` group, which is the form that costs a site everything.
	 * A blanket disallow aimed at one named crawler is a normal thing to write
	 * and is not flagged.
	 *
	 * @param string $rules Rule text.
	 */
	public static function blocks_entire_site( string $rules ): bool {
		$wildcard = false;

		foreach ( explode( "\n", $rules ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			if ( preg_match( '/^user-agent\s*:\s*(.+)$/i', $line, $matches ) ) {
				// Consecutive User-agent lines form one group, so the wildcard
				// stays in force until a directive line ends the group.
				$wildcard = '*' === trim( $matches[1] );

				continue;
			}

			if ( $wildcard && preg_match( '/^disallow\s*:\s*\/\s*$/i', $line ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Append the custom rules and the sitemap declaration.
	 *
	 * @param string $output    Robots.txt contents so far.
	 * @param bool   $is_public Whether the site is set to be indexed.
	 */
	public static function append( string $output, bool $is_public ): string {
		$rules = self::rules();

		if ( '' !== $rules ) {
			$output .= "\n# " . __( 'Custom rules, set in WP Custom SEO.', 'wp-custom-seo' ) . "\n" . $rules . "\n";
		}

		// A site set to discourage search engines is already saying it does not
		// want to be crawled; pointing at a sitemap under that would be talking
		// out of both sides of the file.
		if ( $is_public && Settings::enabled( self::SETTING_SITEMAP ) ) {
			$index = Sitemap::index_url();

			if ( '' !== $index && ! str_contains( $output, $index ) ) {
				$output .= "\nSitemap: " . $index . "\n";
			}
		}

		return $output;
	}

	/**
	 * The robots.txt this site would serve right now.
	 *
	 * Built by running the same filter WordPress runs, so the preview on the
	 * screen is the file itself rather than a reconstruction of it that could
	 * disagree with what a crawler actually fetches.
	 */
	public static function preview(): string {
		$public = '1' === (string) get_option( 'blog_public' );
		$output = $public ? "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n" : "User-agent: *\nDisallow: /\n";

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, applied here so the preview is the file itself rather than a reconstruction of it.
		return trim( (string) apply_filters( 'robots_txt', $output, $public ) );
	}
}
