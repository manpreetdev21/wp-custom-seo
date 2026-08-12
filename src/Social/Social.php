<?php
/**
 * Open Graph and X/Twitter metadata.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Social;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Entities\Registry;
use WPCustomSeo\SEO\Frontend;
use WPCustomSeo\SEO\Meta;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Emits social sharing metadata.
 *
 * Values fall back through the chain a person would expect: the social field,
 * then the SEO field, then what the page actually contains. Nothing is
 * invented, and a tag whose value cannot be resolved is simply not printed.
 */
final class Social {

	private const DESCRIPTION_MAX = 200;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		if ( ! Settings::enabled( 'enable_seo' ) ) {
			return;
		}

		add_action( 'wp_head', array( self::class, 'render' ), 3 );
	}

	/**
	 * Print the tags.
	 */
	public static function render(): void {
		if ( is_feed() || is_embed() ) {
			return;
		}

		foreach ( self::tags() as $tag ) {
			printf(
				'<meta %1$s="%2$s" content="%3$s" />' . "\n",
				esc_attr( $tag['attribute'] ),
				esc_attr( $tag['key'] ),
				esc_attr( $tag['value'] )
			);
		}
	}

	/**
	 * Every social tag for the current request.
	 *
	 * @return array<int, array{attribute: string, key: string, value: string}>
	 */
	public static function tags(): array {
		$post_id = self::queried_post_id();
		$tags    = array();

		if ( Settings::enabled( 'social_open_graph' ) ) {
			$tags = array_merge( $tags, self::open_graph( $post_id ) );
		}

		if ( Settings::enabled( 'social_twitter' ) ) {
			$tags = array_merge( $tags, self::twitter( $post_id ) );
		}

		/**
		 * Filters the social metadata tags.
		 *
		 * @param array $tags    Tags, each with `attribute`, `key` and `value`.
		 * @param int   $post_id Queried post id, or 0.
		 */
		return (array) apply_filters( 'wpcseo_social_tags', $tags, $post_id );
	}

	/**
	 * Open Graph tags.
	 *
	 * @param int $post_id Queried post id, or 0.
	 *
	 * @return array<int, array{attribute: string, key: string, value: string}>
	 */
	private static function open_graph( int $post_id ): array {
		$tags = array(
			'og:type'      => self::type( $post_id ),
			'og:title'     => self::title( $post_id, Meta::OG_TITLE ),
			'og:site_name' => (string) get_bloginfo( 'name' ),
			'og:url'       => self::url( $post_id ),
			'og:locale'    => str_replace( '-', '_', Registry::language() ),
		);

		$description = self::description( $post_id, Meta::OG_DESCRIPTION );

		if ( '' !== $description ) {
			$tags['og:description'] = $description;
		}

		$image = self::image( $post_id, Meta::OG_IMAGE );

		if ( '' !== $image ) {
			$tags['og:image'] = $image;
		}

		if ( $post_id > 0 && 'article' === $tags['og:type'] ) {
			$post = get_post( $post_id );

			if ( $post instanceof WP_Post ) {
				$published = get_post_time( DATE_W3C, true, $post );
				$modified  = get_post_modified_time( DATE_W3C, true, $post );

				if ( is_string( $published ) ) {
					$tags['article:published_time'] = $published;
				}

				if ( is_string( $modified ) ) {
					$tags['article:modified_time'] = $modified;
				}
			}
		}

		return self::format( 'property', $tags );
	}

	/**
	 * X/Twitter card tags.
	 *
	 * @param int $post_id Queried post id, or 0.
	 *
	 * @return array<int, array{attribute: string, key: string, value: string}>
	 */
	private static function twitter( int $post_id ): array {
		$image = self::image( $post_id, Meta::TWITTER_IMAGE );
		$card  = (string) Settings::get( 'social_twitter_card', 'summary_large_image' );

		// A large-image card without an image renders as a plain summary
		// anyway, so declare what the page can actually support.
		if ( 'summary_large_image' === $card && '' === $image ) {
			$card = 'summary';
		}

		$tags = array(
			'twitter:card'  => $card,
			'twitter:title' => self::title( $post_id, Meta::TWITTER_TITLE, Meta::OG_TITLE ),
		);

		$description = self::description( $post_id, Meta::TWITTER_DESCRIPTION, Meta::OG_DESCRIPTION );

		if ( '' !== $description ) {
			$tags['twitter:description'] = $description;
		}

		if ( '' !== $image ) {
			$tags['twitter:image'] = $image;
		}

		$site = trim( (string) Settings::get( 'social_twitter_site', '' ) );

		if ( '' !== $site ) {
			$tags['twitter:site'] = '@' . ltrim( $site, '@' );
		}

		return self::format( 'name', $tags );
	}

	/**
	 * Turn a key/value map into tag descriptors, dropping empties.
	 *
	 * @param string                $attribute Either `property` or `name`.
	 * @param array<string, string> $tags      Tag values keyed by name.
	 *
	 * @return array<int, array{attribute: string, key: string, value: string}>
	 */
	private static function format( string $attribute, array $tags ): array {
		$formatted = array();

		foreach ( $tags as $key => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			$formatted[] = array(
				'attribute' => $attribute,
				'key'       => $key,
				'value'     => (string) $value,
			);
		}

		return $formatted;
	}

	/**
	 * Content type for the page.
	 *
	 * @param int $post_id Queried post id, or 0.
	 */
	private static function type( int $post_id ): string {
		if ( 0 === $post_id || is_front_page() ) {
			return 'website';
		}

		return 'page' === get_post_type( $post_id ) ? 'website' : 'article';
	}

	/**
	 * Social title, falling back to the SEO title and then the page title.
	 *
	 * @param int    $post_id Queried post id, or 0.
	 * @param string ...$keys Meta keys to try, most specific first.
	 */
	private static function title( int $post_id, string ...$keys ): string {
		if ( $post_id > 0 ) {
			foreach ( $keys as $key ) {
				$value = trim( (string) Meta::get( $post_id, $key ) );

				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		return wp_get_document_title();
	}

	/**
	 * Social description, falling back to the meta description.
	 *
	 * @param int    $post_id Queried post id, or 0.
	 * @param string ...$keys Meta keys to try, most specific first.
	 */
	private static function description( int $post_id, string ...$keys ): string {
		if ( $post_id > 0 ) {
			foreach ( $keys as $key ) {
				$value = trim( (string) Meta::get( $post_id, $key ) );

				if ( '' !== $value ) {
					return \WPCustomSeo\SEO\Templates::truncate( $value, self::DESCRIPTION_MAX );
				}
			}
		}

		return Frontend::description();
	}

	/**
	 * Social image: the field, then the featured image, then the site default.
	 *
	 * @param int    $post_id Queried post id, or 0.
	 * @param string $key     Meta key holding an override.
	 */
	private static function image( int $post_id, string $key ): string {
		if ( $post_id > 0 ) {
			$override = trim( (string) Meta::get( $post_id, $key ) );

			if ( '' === $override ) {
				$override = trim( (string) Meta::get( $post_id, Meta::OG_IMAGE ) );
			}

			if ( '' !== $override && Registry::is_url( $override ) ) {
				return $override;
			}

			$featured = get_the_post_thumbnail_url( $post_id, 'full' );

			if ( is_string( $featured ) && Registry::is_url( $featured ) ) {
				return $featured;
			}
		}

		$default = trim( (string) Settings::get( 'social_default_image', '' ) );

		return Registry::is_url( $default ) ? $default : '';
	}

	/**
	 * Canonical URL for the page.
	 *
	 * @param int $post_id Queried post id, or 0.
	 */
	private static function url( int $post_id ): string {
		if ( $post_id > 0 ) {
			$canonical = wp_get_canonical_url( $post_id );

			if ( is_string( $canonical ) ) {
				return $canonical;
			}
		}

		return home_url( add_query_arg( array() ) );
	}

	/**
	 * Id of the singular post being displayed, or 0.
	 */
	private static function queried_post_id(): int {
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}

		if ( is_home() && ! is_front_page() ) {
			return (int) get_option( 'page_for_posts' );
		}

		return 0;
	}
}
