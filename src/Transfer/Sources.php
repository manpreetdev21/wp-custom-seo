<?php
/**
 * Where SEO data can be imported from.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Transfer;

use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

/*
 * Counts rows in postmeta directly. There is no per-post cache to prime here —
 * the question is "how many posts across the site have this key", which is one
 * COUNT rather than a loop.
 *
 * Every value is still passed through prepare(); what is interpolated is the
 * list of %s placeholders itself, built from the source's key list, which the
 * placeholder sniffs cannot count statically.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders
 */

/**
 * Field maps for the other SEO plugins, and the reading of their values.
 *
 * Each plugin stores the same handful of ideas in its own keys, its own
 * spelling of "noindex", and its own template variables. That translation is
 * the whole job, and it is done explicitly per plugin rather than guessed at:
 * an import that silently mangles four hundred meta descriptions is worse than
 * one that refuses to run.
 *
 * Nothing here reads a plugin's code or requires it to be active. The data sits
 * in postmeta and outlives the plugin that wrote it, which is exactly the case
 * that matters — people usually deactivate the old plugin first.
 */
final class Sources {

	/**
	 * Every supported source, keyed by slug.
	 *
	 * `fields` maps our meta key to theirs. `flags` are the keys whose meaning
	 * is a yes or no rather than text, and are read through robots().
	 *
	 * @return array<string, array{label: string, prefix: string, variables: string, fields: array<string, string>, flags: array<string, string>}>
	 */
	public static function all(): array {
		$sources = array(
			'yoast'    => array(
				'label'     => 'Yoast SEO',
				'prefix'    => '_yoast_wpseo_',
				'variables' => 'double',
				'fields'    => array(
					Meta::TITLE               => '_yoast_wpseo_title',
					Meta::DESCRIPTION         => '_yoast_wpseo_metadesc',
					Meta::FOCUS_KEYWORD       => '_yoast_wpseo_focuskw',
					Meta::CANONICAL           => '_yoast_wpseo_canonical',
					Meta::BREADCRUMB_TITLE    => '_yoast_wpseo_bctitle',
					Meta::OG_TITLE            => '_yoast_wpseo_opengraph-title',
					Meta::OG_DESCRIPTION      => '_yoast_wpseo_opengraph-description',
					Meta::OG_IMAGE            => '_yoast_wpseo_opengraph-image',
					Meta::TWITTER_TITLE       => '_yoast_wpseo_twitter-title',
					Meta::TWITTER_DESCRIPTION => '_yoast_wpseo_twitter-description',
					Meta::TWITTER_IMAGE       => '_yoast_wpseo_twitter-image',
				),
				'flags'     => array(
					Meta::NOINDEX  => '_yoast_wpseo_meta-robots-noindex',
					Meta::NOFOLLOW => '_yoast_wpseo_meta-robots-nofollow',
				),
			),
			'rankmath' => array(
				'label'     => 'Rank Math',
				'prefix'    => 'rank_math_',
				'variables' => 'single',
				'fields'    => array(
					Meta::TITLE               => 'rank_math_title',
					Meta::DESCRIPTION         => 'rank_math_description',
					Meta::FOCUS_KEYWORD       => 'rank_math_focus_keyword',
					Meta::CANONICAL           => 'rank_math_canonical_url',
					Meta::OG_TITLE            => 'rank_math_facebook_title',
					Meta::OG_DESCRIPTION      => 'rank_math_facebook_description',
					Meta::OG_IMAGE            => 'rank_math_facebook_image',
					Meta::TWITTER_TITLE       => 'rank_math_twitter_title',
					Meta::TWITTER_DESCRIPTION => 'rank_math_twitter_description',
					Meta::TWITTER_IMAGE       => 'rank_math_twitter_image',
				),
				'flags'     => array(
					Meta::NOINDEX  => 'rank_math_robots',
					Meta::NOFOLLOW => 'rank_math_robots',
				),
			),
			'seopress' => array(
				'label'     => 'SEOPress',
				'prefix'    => '_seopress_',
				'variables' => 'double',
				'fields'    => array(
					Meta::TITLE               => '_seopress_titles_title',
					Meta::DESCRIPTION         => '_seopress_titles_desc',
					Meta::FOCUS_KEYWORD       => '_seopress_analysis_target_kw',
					Meta::CANONICAL           => '_seopress_robots_canonical',
					Meta::OG_TITLE            => '_seopress_social_fb_title',
					Meta::OG_DESCRIPTION      => '_seopress_social_fb_desc',
					Meta::OG_IMAGE            => '_seopress_social_fb_img',
					Meta::TWITTER_TITLE       => '_seopress_social_twitter_title',
					Meta::TWITTER_DESCRIPTION => '_seopress_social_twitter_desc',
					Meta::TWITTER_IMAGE       => '_seopress_social_twitter_img',
				),
				'flags'     => array(
					Meta::NOINDEX  => '_seopress_robots_index',
					Meta::NOFOLLOW => '_seopress_robots_follow',
				),
			),
			'tsf'      => array(
				'label'     => 'The SEO Framework',
				'prefix'    => '_genesis_',
				'variables' => 'none',
				'fields'    => array(
					Meta::TITLE               => '_genesis_title',
					Meta::DESCRIPTION         => '_genesis_description',
					Meta::CANONICAL           => '_genesis_canonical_uri',
					Meta::OG_TITLE            => '_open_graph_title',
					Meta::OG_DESCRIPTION      => '_open_graph_description',
					Meta::OG_IMAGE            => '_social_image_url',
					Meta::TWITTER_TITLE       => '_twitter_title',
					Meta::TWITTER_DESCRIPTION => '_twitter_description',
				),
				'flags'     => array(
					Meta::NOINDEX  => '_genesis_noindex',
					Meta::NOFOLLOW => '_genesis_nofollow',
				),
			),
		);

		/**
		 * Filters the SEO plugins that can be imported from.
		 *
		 * @param array<string, array<string, mixed>> $sources Source definitions.
		 */
		return (array) apply_filters( 'wpcseo_import_sources', $sources );
	}

	/**
	 * One source definition, or null.
	 *
	 * @param string $slug Source slug.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get( string $slug ): ?array {
		return self::all()[ $slug ] ?? null;
	}

	/**
	 * How many posts hold data from each source.
	 *
	 * Counts distinct posts rather than meta rows, because one post with ten
	 * fields is one post to an editor deciding whether an import is worth it.
	 *
	 * @return array<string, int>
	 */
	public static function detect(): array {
		global $wpdb;

		$counts = array();

		foreach ( self::all() as $slug => $source ) {
			$keys   = array_values( array_unique( array_merge( array_values( $source['fields'] ), array_values( $source['flags'] ) ) ) );
			$tokens = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

			$counts[ $slug ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT( DISTINCT post_id )
					FROM {$wpdb->postmeta}
					WHERE meta_key IN ({$tokens}) AND meta_value <> ''",
					...$keys
				)
			);
		}

		return $counts;
	}

	/**
	 * Whether All in One SEO has data this plugin cannot read.
	 *
	 * AIOSEO version 4 moved its data out of postmeta into a table of its own,
	 * so none of the machinery here reaches it. Rather than import a fraction
	 * of it and report success, the screen says plainly that it is not covered.
	 */
	public static function aioseo_present(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_posts';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Read a stored value into ours.
	 *
	 * @param string $slug  Source slug.
	 * @param string $key   Our meta key.
	 * @param mixed  $value Stored value.
	 *
	 * @return array{value: string, dropped: string[]}
	 */
	public static function text( string $slug, string $key, mixed $value ): array {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return array(
				'value'   => '',
				'dropped' => array(),
			);
		}

		// Several plugins keep a comma-separated keyword list; ours holds one
		// focus keyphrase, so the first is taken and the rest left behind.
		if ( Meta::FOCUS_KEYWORD === $key ) {
			$parts = explode( ',', $value );

			return array(
				'value'   => trim( (string) reset( $parts ) ),
				'dropped' => array(),
			);
		}

		$style = (string) ( self::get( $slug )['variables'] ?? 'none' );

		return 'none' === $style ? array(
			'value'   => $value,
			'dropped' => array(),
		) : self::translate( $value, $style );
	}

	/**
	 * Turn another plugin's template variables into ours.
	 *
	 * A variable this plugin does not have cannot be honoured, and leaving
	 * `%%primary_category%%` in a title would publish that text to search
	 * results. So an unknown variable is removed and reported, which is the
	 * difference between an import an editor can trust and one they have to
	 * re-check by hand.
	 *
	 * @param string $value Raw template.
	 * @param string $style `single` for %var%, `double` for %%var%%.
	 *
	 * @return array{value: string, dropped: string[]}
	 */
	public static function translate( string $value, string $style = 'double' ): array {
		if ( 'single' === $style ) {
			$value = (string) preg_replace( '/%([a-z_]+)%/', '%%$1%%', $value );
		}

		$known   = self::variable_map();
		$dropped = array();

		$value = (string) preg_replace_callback(
			'/%%([a-z_]+(?:\([^)]*\))?)%%/',
			static function ( array $matches ) use ( $known, &$dropped ): string {
				// A parameterised variable such as %%cf_price%% names a field
				// this plugin knows nothing about.
				$name = strtolower( (string) $matches[1] );

				if ( isset( $known[ $name ] ) ) {
					return '%%' . $known[ $name ] . '%%';
				}

				$dropped[] = $name;

				return '';
			},
			$value
		);

		return array(
			'value'   => trim( (string) preg_replace( '/\s{2,}/u', ' ', $value ) ),
			'dropped' => array_values( array_unique( $dropped ) ),
		);
	}

	/**
	 * Other plugins' variable names, mapped to ours.
	 *
	 * Only variables that mean the same thing in the same place are mapped. A
	 * post-level `%%primary_category%%` is deliberately absent: this plugin has
	 * no equivalent, and mapping it to something that expands to nothing on a
	 * post would hide the loss instead of reporting it.
	 *
	 * @return array<string, string>
	 */
	private static function variable_map(): array {
		$map = array(
			'title'           => 'title',
			'post_title'      => 'title',
			'page_title'      => 'title',
			'pt_single'       => 'title',
			'sitename'        => 'sitename',
			'sitetitle'       => 'sitename',
			'blogname'        => 'sitename',
			'sitedesc'        => 'sitedesc',
			'blogdescription' => 'sitedesc',
			'tagline'         => 'sitedesc',
			'sep'             => 'sep',
			'separator'       => 'sep',
			'sep_icon'        => 'sep',
			'excerpt'         => 'excerpt',
			'excerpt_only'    => 'excerpt',
			'post_excerpt'    => 'excerpt',
			'currentyear'     => 'currentyear',
			'current_year'    => 'currentyear',
			'date'            => 'date',
			'post_date'       => 'date',
			'name'            => 'author',
			'author'          => 'author',
			'post_author'     => 'author',
			'user_name'       => 'author',
			'page'            => 'page',
			'page_number'     => 'page',
			'term_title'      => 'term_title',
			'searchphrase'    => 'searchphrase',
			'search_query'    => 'searchphrase',
		);

		/**
		 * Filters the mapping from another plugin's template variables to ours.
		 *
		 * @param array<string, string> $map Foreign variable name to ours.
		 */
		return (array) apply_filters( 'wpcseo_import_variable_map', $map );
	}

	/**
	 * Read a robots flag the way the source plugin meant it.
	 *
	 * @param string $slug  Source slug.
	 * @param string $key   Our meta key, NOINDEX or NOFOLLOW.
	 * @param mixed  $value Stored value.
	 */
	public static function flag( string $slug, string $key, mixed $value ): bool {
		$directive = Meta::NOINDEX === $key ? 'noindex' : 'nofollow';

		switch ( $slug ) {
			case 'yoast':
				// 1 means the directive is set; 2 means explicitly the opposite.
				return '1' === (string) $value;

			case 'rankmath':
				// One serialized list holds every robots directive.
				return is_array( $value ) && in_array( $directive, $value, true );

			case 'seopress':
				return 'yes' === (string) $value;

			default:
				return '' !== (string) $value && '0' !== (string) $value;
		}
	}
}
