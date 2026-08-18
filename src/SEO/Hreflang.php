<?php
/**
 * Multilingual detection and hreflang output.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Emits `hreflang` for sites running a multilingual plugin.
 *
 * **Why the translations are asked for rather than worked out.** hreflang is a
 * claim that two URLs are the same page in different languages. Getting that
 * wrong is worse than omitting it: a search engine that follows a bad
 * alternate serves the wrong language to a reader. Only the translation plugin
 * knows which posts are translations of which, so this asks it and publishes
 * nothing when there is no answer.
 *
 * Polylang and TranslatePress are queried through their public functions.
 * WPML's `wpml_active_languages` filter is its documented read API. If none of
 * them are present, this module does nothing at all — it does not invent a
 * language set from the site locale, because a single-language site publishing
 * hreflang is stating a relationship that does not exist.
 *
 * `x-default` is emitted only when the site has a genuine default language,
 * which is the one case the specification describes it for.
 */
final class Hreflang {

	public const SETTING = 'enable_hreflang';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_settings_schema', array( self::class, 'schema' ) );

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_head', array( self::class, 'render' ), 2 );
	}

	/**
	 * Add the toggle to the settings schema.
	 *
	 * @param array<string, array<string, mixed>> $schema Settings schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema( array $schema ): array {
		$detected = self::plugin();

		$schema['general']['fields'][ self::SETTING ] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Output hreflang tags', 'wp-custom-seo' ),
			'description' => null === $detected
				? __( 'No multilingual plugin was detected, so this does nothing. hreflang is only emitted when a translation plugin can say which URLs are translations of each other — guessing that from the site locale would publish a relationship that does not exist.', 'wp-custom-seo' )
				: sprintf(
					/* translators: %s: name of the detected multilingual plugin. */
					__( 'Translations are read from %s. Check whether it already outputs hreflang itself before turning this on — two sets of alternates on one page is a contradiction rather than a reinforcement.', 'wp-custom-seo' ),
					$detected
				),
			'default'     => false,
		);

		return $schema;
	}

	/**
	 * The multilingual plugin in use, or null.
	 */
	public static function plugin(): ?string {
		if ( function_exists( 'pll_the_languages' ) ) {
			return 'Polylang';
		}

		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'WPML';
		}

		if ( function_exists( 'trp_custom_language_switcher' ) || defined( 'TRP_PLUGIN_VERSION' ) ) {
			return 'TranslatePress';
		}

		return null;
	}

	/**
	 * Alternates for the current request, keyed by BCP 47 language tag.
	 *
	 * @return array<string, string>
	 */
	public static function alternates(): array {
		$alternates = array();

		switch ( self::plugin() ) {
			case 'Polylang':
				$alternates = self::from_polylang();
				break;

			case 'WPML':
				$alternates = self::from_wpml();
				break;

			case 'TranslatePress':
				$alternates = self::from_translatepress();
				break;
		}

		/**
		 * Filters the hreflang alternates for the current request.
		 *
		 * Return an array of absolute URLs keyed by BCP 47 language tag. An
		 * empty array suppresses the output entirely, which is the right answer
		 * whenever the translations cannot be established.
		 *
		 * @param array<string, string> $alternates Alternates keyed by language tag.
		 */
		$alternates = (array) apply_filters( 'wpcseo_hreflang_alternates', $alternates );

		return array_filter(
			$alternates,
			static fn ( $url, $tag ): bool => is_string( $url ) && '' !== $url && is_string( $tag ) && '' !== $tag,
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Alternates from Polylang.
	 *
	 * @return array<string, string>
	 */
	private static function from_polylang(): array {
		if ( ! function_exists( 'pll_the_languages' ) ) {
			return array();
		}

		$languages = pll_the_languages(
			array(
				'raw'                    => 1,
				'hide_if_no_translation' => 1,
			)
		);

		$alternates = array();

		foreach ( (array) $languages as $language ) {
			$tag = (string) ( $language['locale'] ?? $language['slug'] ?? '' );
			$url = (string) ( $language['url'] ?? '' );

			if ( '' !== $tag && '' !== $url ) {
				$alternates[ str_replace( '_', '-', $tag ) ] = $url;
			}
		}

		return $alternates;
	}

	/**
	 * Alternates from WPML.
	 *
	 * @return array<string, string>
	 */
	private static function from_wpml(): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own documented read API, applied rather than declared here.
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 1 ) );

		$alternates = array();

		foreach ( (array) $languages as $language ) {
			$tag = (string) ( $language['default_locale'] ?? $language['language_code'] ?? '' );
			$url = (string) ( $language['url'] ?? '' );

			if ( '' !== $tag && '' !== $url ) {
				$alternates[ str_replace( '_', '-', $tag ) ] = $url;
			}
		}

		return $alternates;
	}

	/**
	 * Alternates from TranslatePress.
	 *
	 * TranslatePress serves every language from a URL derived from the current
	 * one, so the set is built from its published language list rather than a
	 * per-post translation map.
	 *
	 * @return array<string, string>
	 */
	private static function from_translatepress(): array {
		if ( ! function_exists( 'trp_get_languages' ) ) {
			return array();
		}

		$settings = get_option( 'trp_settings', array() );
		$default  = (string) ( is_array( $settings ) ? ( $settings['default-language'] ?? '' ) : '' );
		$current  = trp_get_languages();

		if ( ! is_array( $current ) || '' === $default ) {
			return array();
		}

		$converter = new \TRP_Url_Converter( $settings );

		$alternates = array();

		foreach ( array_keys( $current ) as $code ) {
			$url = $converter->get_url_for_language( (string) $code );

			if ( is_string( $url ) && '' !== $url ) {
				$alternates[ str_replace( '_', '-', (string) $code ) ] = $url;
			}
		}

		return $alternates;
	}

	/**
	 * The site's default language tag, or an empty string.
	 */
	public static function default_language(): string {
		if ( function_exists( 'pll_default_language' ) ) {
			return str_replace( '_', '-', (string) pll_default_language( 'locale' ) );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own documented read API, applied rather than declared here.
		$wpml = apply_filters( 'wpml_default_language', null );

		if ( is_string( $wpml ) && '' !== $wpml ) {
			return str_replace( '_', '-', $wpml );
		}

		$settings = get_option( 'trp_settings', array() );

		return is_array( $settings ) ? str_replace( '_', '-', (string) ( $settings['default-language'] ?? '' ) ) : '';
	}

	/**
	 * Print the link elements.
	 */
	public static function render(): void {
		if ( ! Settings::enabled( 'enable_seo' ) || ! Settings::enabled( self::SETTING ) ) {
			return;
		}

		$alternates = self::alternates();

		// One alternate is the page itself, which says nothing. hreflang is only
		// meaningful as a set describing a choice a reader could make.
		if ( count( $alternates ) < 2 ) {
			return;
		}

		$default = self::default_language();

		foreach ( $alternates as $tag => $url ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $tag ),
				esc_url( $url )
			);
		}

		if ( '' !== $default && isset( $alternates[ $default ] ) ) {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
				esc_url( $alternates[ $default ] )
			);
		}
	}
}
