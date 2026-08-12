<?php
/**
 * Title template variables.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Expands %%variable%% placeholders in title templates.
 *
 * Replacements are supplied per context by the caller, so this class stays
 * free of query state and can be unit tested directly.
 */
final class Templates {

	/**
	 * Variables understood by the template engine, with human descriptions.
	 *
	 * @return array<string, string>
	 */
	public static function variables(): array {
		return array(
			'title'        => __( 'Title of the post, page or product', 'wp-custom-seo' ),
			'sitename'     => __( 'Site name', 'wp-custom-seo' ),
			'sitedesc'     => __( 'Site tagline', 'wp-custom-seo' ),
			'sep'          => __( 'Title separator', 'wp-custom-seo' ),
			'excerpt'      => __( 'Post excerpt', 'wp-custom-seo' ),
			'term_title'   => __( 'Taxonomy term name', 'wp-custom-seo' ),
			'archive_type' => __( 'Archive label, e.g. the post type name', 'wp-custom-seo' ),
			'author'       => __( 'Author display name', 'wp-custom-seo' ),
			'searchphrase' => __( 'Search query', 'wp-custom-seo' ),
			'page'         => __( 'Current page number in a paginated series', 'wp-custom-seo' ),
			'date'         => __( 'Post or archive date', 'wp-custom-seo' ),
			'currentyear'  => __( 'Current year', 'wp-custom-seo' ),
		);
	}

	/**
	 * Expand a template.
	 *
	 * Unknown or empty variables are removed, then leftover separators and
	 * whitespace are tidied so a missing value never leaves a dangling dash.
	 *
	 * @param string                $template     Template containing %%vars%%.
	 * @param array<string, string> $replacements Values keyed by variable name.
	 * @param string                $separator    Separator used to tidy the result.
	 */
	public static function expand( string $template, array $replacements, string $separator = '-' ): string {
		/**
		 * Filters the replacement values before a template is expanded.
		 *
		 * @param array<string, string> $replacements Values keyed by variable name.
		 * @param string                $template     The template being expanded.
		 */
		$replacements = (array) apply_filters( 'wpcseo_title_replacements', $replacements, $template );

		$output = (string) preg_replace_callback(
			'/%%([a-z_]+)%%/',
			static function ( array $matches ) use ( $replacements ): string {
				return isset( $replacements[ $matches[1] ] ) ? (string) $replacements[ $matches[1] ] : '';
			},
			$template
		);

		return self::tidy( $output, $separator );
	}

	/**
	 * Collapse whitespace and drop separators left stranded by empty variables.
	 *
	 * @param string $text      Expanded template.
	 * @param string $separator Separator character.
	 */
	public static function tidy( string $text, string $separator ): string {
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		if ( '' !== $separator ) {
			$quoted = preg_quote( $separator, '/' );

			// Collapse runs of separators and strip leading or trailing ones.
			$text = (string) preg_replace( '/(?:\s*' . $quoted . '\s*){2,}/u', ' ' . $separator . ' ', $text );
			$text = (string) preg_replace( '/^(?:\s*' . $quoted . '\s*)+|(?:\s*' . $quoted . '\s*)+$/u', '', $text );
		}

		return trim( $text );
	}

	/**
	 * Truncate on a word boundary, appending an ellipsis when shortened.
	 *
	 * @param string $text   Source text.
	 * @param int    $length Maximum length in characters.
	 */
	public static function truncate( string $text, int $length ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, $length - 1 );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, " \t\n\r\0\x0B.,;:-" ) . '…';
	}
}
