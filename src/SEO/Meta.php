<?php
/**
 * SEO post meta registration and access.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the SEO meta keys and reads them back.
 *
 * Registration via register_post_meta() supplies sanitization, capability checks and REST
 * exposure, so the editor and the REST API share one definition.
 */
final class Meta {

	public const TITLE = '_wpcseo_title';

	public const DESCRIPTION = '_wpcseo_description';

	public const FOCUS_KEYWORD = '_wpcseo_focus_keyword';

	public const CANONICAL = '_wpcseo_canonical';

	public const NOINDEX = '_wpcseo_noindex';

	public const NOFOLLOW = '_wpcseo_nofollow';

	public const NOARCHIVE = '_wpcseo_noarchive';

	public const NOSNIPPET = '_wpcseo_nosnippet';

	public const MAX_SNIPPET = '_wpcseo_max_snippet';

	public const MAX_IMAGE_PREVIEW = '_wpcseo_max_image_preview';

	public const MAX_VIDEO_PREVIEW = '_wpcseo_max_video_preview';

	public const SCHEMA_TYPE = '_wpcseo_schema_type';

	public const BREADCRUMB_TITLE = '_wpcseo_breadcrumb_title';

	public const SEARCH_INTENT = '_wpcseo_search_intent';

	/**
	 * Search intents a page can be assigned.
	 *
	 * The AI analysis suggests one; this field is the editor's own answer and
	 * always wins, because the person who wrote the page knows what it is for.
	 *
	 * @return array<string, string>
	 */
	public static function search_intents(): array {
		return array(
			''              => __( 'Not set', 'wp-custom-seo' ),
			'informational' => __( 'Informational — the reader wants to understand something', 'wp-custom-seo' ),
			'navigational'  => __( 'Navigational — the reader wants a specific page or brand', 'wp-custom-seo' ),
			'commercial'    => __( 'Commercial — the reader is comparing options', 'wp-custom-seo' ),
			'transactional' => __( 'Transactional — the reader is ready to act', 'wp-custom-seo' ),
			'local'         => __( 'Local — the reader wants something nearby', 'wp-custom-seo' ),
			'mixed'         => __( 'Mixed', 'wp-custom-seo' ),
		);
	}

	/**
	 * Restrict the search intent to the offered list.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_search_intent( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );

		return array_key_exists( $value, self::search_intents() ) ? $value : '';
	}

	public const OG_TITLE = '_wpcseo_og_title';

	public const OG_DESCRIPTION = '_wpcseo_og_description';

	public const OG_IMAGE = '_wpcseo_og_image';

	public const TWITTER_TITLE = '_wpcseo_twitter_title';

	public const TWITTER_DESCRIPTION = '_wpcseo_twitter_description';

	public const TWITTER_IMAGE = '_wpcseo_twitter_image';

	/**
	 * Meta query fragment that excludes noindexed posts.
	 *
	 * A page the site asks search engines to ignore does not belong in the
	 * sitemap or the aggregation API either.
	 *
	 * @return array<int|string, mixed>
	 */
	public static function exclude_noindex_clause(): array {
		return array(
			'relation' => 'OR',
			array(
				'key'     => self::NOINDEX,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::NOINDEX,
				'value'   => '1',
				'compare' => '!=',
			),
		);
	}

	/**
	 * Schema types selectable per post.
	 *
	 * Only types that can be honestly derived from a WordPress post are listed.
	 * FAQPage and HowTo are absent by design: they require the visible page to
	 * actually contain that content, so they are offered by the schema module
	 * once it can verify it.
	 *
	 * @return array<string, string>
	 */
	public static function schema_types(): array {
		return array(
			'auto'        => __( 'Automatic', 'wp-custom-seo' ),
			'Article'     => __( 'Article', 'wp-custom-seo' ),
			'BlogPosting' => __( 'Blog posting', 'wp-custom-seo' ),
			'NewsArticle' => __( 'News article', 'wp-custom-seo' ),
			'WebPage'     => __( 'Web page', 'wp-custom-seo' ),
			'AboutPage'   => __( 'About page', 'wp-custom-seo' ),
			'ContactPage' => __( 'Contact page', 'wp-custom-seo' ),
			'none'        => __( 'No page-level schema', 'wp-custom-seo' ),
		);
	}

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 20 );
	}

	/**
	 * Post types that get SEO metadata.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = get_post_types( array( 'public' => true ) );

		unset( $types['attachment'] );

		/**
		 * Filters the post types that receive SEO metadata.
		 *
		 * @param string[] $types Post type slugs.
		 */
		return array_values( (array) apply_filters( 'wpcseo_post_types', array_values( $types ) ) );
	}

	/**
	 * Meta key definitions.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	public static function keys(): array {
		return array(
			self::TITLE               => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::DESCRIPTION         => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::FOCUS_KEYWORD       => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::CANONICAL           => array(
				'type'     => 'string',
				'sanitize' => array( self::class, 'sanitize_url_field' ),
			),
			self::NOINDEX             => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::NOFOLLOW            => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::NOARCHIVE           => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::NOSNIPPET           => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::MAX_SNIPPET         => array(
				'type'     => 'string',
				'sanitize' => static fn ( mixed $value ): string => Robots::sanitize( 'max_snippet', $value ),
			),
			self::MAX_IMAGE_PREVIEW   => array(
				'type'     => 'string',
				'sanitize' => static fn ( mixed $value ): string => Robots::sanitize( 'max_image_preview', $value ),
			),
			self::MAX_VIDEO_PREVIEW   => array(
				'type'     => 'string',
				'sanitize' => static fn ( mixed $value ): string => Robots::sanitize( 'max_video_preview', $value ),
			),
			self::SCHEMA_TYPE         => array(
				'type'     => 'string',
				'sanitize' => array( self::class, 'sanitize_schema_type' ),
			),
			self::BREADCRUMB_TITLE    => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::SEARCH_INTENT       => array(
				'type'     => 'string',
				'sanitize' => array( self::class, 'sanitize_search_intent' ),
			),
			self::OG_TITLE            => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::OG_DESCRIPTION      => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::OG_IMAGE            => array(
				'type'     => 'string',
				'sanitize' => array( self::class, 'sanitize_url_field' ),
			),
			self::TWITTER_TITLE       => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::TWITTER_DESCRIPTION => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::TWITTER_IMAGE       => array(
				'type'     => 'string',
				'sanitize' => array( self::class, 'sanitize_url_field' ),
			),
		);
	}

	/**
	 * Register every key against every SEO-enabled post type.
	 */
	public static function register(): void {
		foreach ( self::post_types() as $post_type ) {
			foreach ( self::keys() as $key => $definition ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'              => $definition['type'],
						'single'            => true,
						'default'           => 'boolean' === $definition['type'] ? false : '',
						'show_in_rest'      => true,
						'sanitize_callback' => $definition['sanitize'],
						'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}

	/**
	 * The values the analyzer should judge a stored post by.
	 *
	 * Lives here rather than in the analyzer because it reads the database, and
	 * the analyzer is deliberately a pure function of its input so it can be
	 * exercised without WordPress. Both the REST route and the Abilities API
	 * start from this, so "the effective title of this page" cannot mean two
	 * different things depending on which asked.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array{title: string, description: string, keyword: string, content: string, slug: string, host: string}
	 */
	public static function analysis_input( int $post_id ): array {
		$post  = get_post( $post_id );
		$title = trim( (string) self::get( $post_id, self::TITLE ) );

		return array(
			// An empty SEO title means the post title is what gets used.
			'title'       => '' !== $title ? $title : (string) get_the_title( $post_id ),
			'description' => (string) self::get( $post_id, self::DESCRIPTION ),
			'keyword'     => (string) self::get( $post_id, self::FOCUS_KEYWORD ),
			'content'     => $post ? (string) $post->post_content : '',
			'slug'        => $post ? (string) $post->post_name : '',
			'host'        => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		);
	}

	/**
	 * Keep an absolute URL, and drop anything that is not one.
	 *
	 * `sanitize_url()` alone is not enough here: it prepends a scheme to
	 * whatever it is given, so a typo like "roof-page" is stored as
	 * "http://roof-page" and looks valid. A canonical pointing at a host that
	 * does not exist tells search engines to prefer a page that cannot be
	 * fetched, which is worse for the site than having no canonical at all —
	 * so the scheme has to be there in the first place or the value is dropped.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_url_field( mixed $value ): string {
		$value = trim( (string) $value );

		return preg_match( '#^https?://#i', $value ) ? sanitize_url( $value ) : '';
	}

	/**
	 * Restrict the schema type to the offered list.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize_schema_type( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );

		return array_key_exists( $value, self::schema_types() ) ? $value : '';
	}

	/**
	 * Read one SEO meta value.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     One of the class constants.
	 *
	 * @return mixed
	 */
	public static function get( int $post_id, string $key ): mixed {
		$value = get_post_meta( $post_id, $key, true );

		if ( 'boolean' === ( self::keys()[ $key ]['type'] ?? 'string' ) ) {
			$value = (bool) $value;
		}

		/**
		 * Filters an SEO meta value as it is read.
		 *
		 * @param mixed  $value   Stored value.
		 * @param int    $post_id Post id.
		 * @param string $key     Meta key.
		 */
		return apply_filters( 'wpcseo_meta_value', $value, $post_id, $key );
	}

	/**
	 * A post's robots directives, keyed by the short names Robots understands.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array<string, mixed>
	 */
	public static function robots_values( int $post_id ): array {
		return array(
			'noindex'           => self::get( $post_id, self::NOINDEX ),
			'nofollow'          => self::get( $post_id, self::NOFOLLOW ),
			'noarchive'         => self::get( $post_id, self::NOARCHIVE ),
			'nosnippet'         => self::get( $post_id, self::NOSNIPPET ),
			'max_snippet'       => self::get( $post_id, self::MAX_SNIPPET ),
			'max_image_preview' => self::get( $post_id, self::MAX_IMAGE_PREVIEW ),
			'max_video_preview' => self::get( $post_id, self::MAX_VIDEO_PREVIEW ),
		);
	}

	/**
	 * All SEO meta for a post, keyed by meta key.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array<string, mixed>
	 */
	public static function all( int $post_id ): array {
		$values = array();

		foreach ( array_keys( self::keys() ) as $key ) {
			$values[ $key ] = self::get( $post_id, $key );
		}

		return $values;
	}
}
