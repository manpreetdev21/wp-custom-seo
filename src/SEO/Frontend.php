<?php
/**
 * Front-end SEO output: title, description, canonical, robots.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Emits the head metadata.
 *
 * Everything rides a native WordPress hook rather than being printed blindly,
 * so themes and other plugins keep their usual chance to filter the result.
 */
final class Frontend {

	private const DESCRIPTION_MAX = 160;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		if ( ! Settings::enabled( 'enable_seo' ) ) {
			return;
		}

		add_filter( 'pre_get_document_title', array( self::class, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_filter( 'get_canonical_url', array( self::class, 'canonical' ), 10, 2 );
		add_action( 'wp_head', array( self::class, 'meta_description' ), 1 );
		add_action( 'wp_head', array( self::class, 'term_canonical' ), 1 );
	}

	/**
	 * Print a canonical for a term archive that has one set.
	 *
	 * Core's `rel_canonical` only runs on singular views, so an archive has no
	 * canonical unless something adds one. Nothing is printed unless the term
	 * carries an explicit URL: a self-referencing canonical on a paginated
	 * archive would tell a search engine that page 4 is page 1, which is worse
	 * than saying nothing at all.
	 */
	public static function term_canonical(): void {
		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return;
		}

		$term = get_queried_object();

		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$canonical = trim( (string) Terms::get( (int) $term->term_id, Terms::CANONICAL ) );

		/**
		 * Filters the canonical URL of a term archive.
		 *
		 * @param string   $canonical Canonical URL, empty for none.
		 * @param \WP_Term $term      Term being displayed.
		 */
		$canonical = (string) apply_filters( 'wpcseo_term_canonical', $canonical, $term );

		if ( '' === $canonical ) {
			return;
		}

		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
	}

	/**
	 * Build the document title.
	 */
	public static function document_title(): string {
		$separator = (string) Settings::get( 'title_separator', '-' );
		$post_id   = self::queried_post_id();

		if ( $post_id > 0 ) {
			$custom = trim( (string) Meta::get( $post_id, Meta::TITLE ) );

			if ( '' !== $custom ) {
				$title = Templates::expand( $custom, self::replacements( $separator ), $separator );

				/** This filter is documented at the end of this method. */
				return (string) apply_filters( 'wpcseo_title', $title, $post_id );
			}
		}

		// A term archive gets the same chance to override the template that a
		// post does, before the site-wide taxonomy template is consulted.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$custom = trim( (string) Terms::get( (int) $term->term_id, Terms::TITLE ) );

				if ( '' !== $custom ) {
					$title = Templates::expand( $custom, self::replacements( $separator ), $separator );

					/** This filter is documented at the end of this method. */
					return (string) apply_filters( 'wpcseo_title', $title, 0 );
				}
			}
		}

		$template = (string) Settings::get( self::template_key(), '' );
		$title    = Templates::expand( $template, self::replacements( $separator ), $separator );

		/**
		 * Filters the document title produced by the plugin.
		 *
		 * @param string $title   Expanded title.
		 * @param int    $post_id Queried post id, or 0.
		 */
		return (string) apply_filters( 'wpcseo_title', $title, $post_id );
	}

	/**
	 * Which title template applies to the current request.
	 */
	private static function template_key(): string {
		if ( is_404() ) {
			return 'title_404';
		}

		if ( is_search() ) {
			return 'title_search';
		}

		if ( is_front_page() ) {
			return 'title_home';
		}

		if ( is_singular() || is_home() ) {
			return 'title_singular';
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return 'title_term';
		}

		if ( is_author() ) {
			return 'title_author';
		}

		return 'title_archive';
	}

	/**
	 * Template variable values for the current request.
	 *
	 * @param string $separator Configured separator.
	 *
	 * @return array<string, string>
	 */
	private static function replacements( string $separator ): array {
		$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ), 1 );

		$values = array(
			'sitename'     => (string) get_bloginfo( 'name' ),
			'sitedesc'     => (string) get_bloginfo( 'description' ),
			'sep'          => $separator,
			'currentyear'  => gmdate( 'Y' ),
			'searchphrase' => is_search() ? (string) get_search_query() : '',
			/* translators: %d: page number. */
			'page'         => $paged > 1 ? sprintf( __( 'Page %d', 'wp-custom-seo' ), $paged ) : '',
			'title'        => '',
			'excerpt'      => '',
			'term_title'   => '',
			'archive_type' => '',
			'author'       => '',
			'date'         => '',
		);

		$post_id = self::queried_post_id();

		if ( $post_id > 0 ) {
			$values['title']   = (string) get_the_title( $post_id );
			$values['excerpt'] = self::fallback_description( $post_id );
			$values['date']    = (string) get_the_date( '', $post_id );
			$values['author']  = (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$values['term_title']   = $term->name;
				$values['archive_type'] = (string) ( get_taxonomy( $term->taxonomy )->labels->singular_name ?? '' );
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();

			if ( $author instanceof \WP_User ) {
				$values['author'] = $author->display_name;
			}
		}

		if ( is_post_type_archive() ) {
			$object = get_queried_object();

			if ( $object instanceof \WP_Post_Type ) {
				$values['archive_type'] = (string) $object->labels->name;
			}
		}

		if ( is_date() ) {
			$values['archive_type'] = __( 'Archive', 'wp-custom-seo' );
			$values['date']         = (string) get_the_date();
		}

		return $values;
	}

	/**
	 * Print the meta description.
	 */
	public static function meta_description(): void {
		$description = self::description();

		if ( '' === $description ) {
			return;
		}

		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
	}

	/**
	 * Resolve the meta description for the current request.
	 */
	public static function description(): string {
		$description = '';
		$post_id     = self::queried_post_id();

		if ( $post_id > 0 ) {
			$description = trim( (string) Meta::get( $post_id, Meta::DESCRIPTION ) );

			if ( '' === $description && Settings::enabled( 'description_excerpt' ) ) {
				$description = self::fallback_description( $post_id );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				// The term's own SEO description wins. The term description is a
				// fallback rather than the source: it is written to be read on
				// the page, which is not the same job as a search snippet.
				$description = trim( (string) Terms::get( (int) $term->term_id, Terms::DESCRIPTION ) );

				if ( '' === $description ) {
					$description = Analyzer::to_text( $term->description );
				}
			}
		} elseif ( is_front_page() ) {
			$description = (string) get_bloginfo( 'description' );
		}

		$description = Templates::truncate( $description, self::DESCRIPTION_MAX );

		/**
		 * Filters the meta description.
		 *
		 * @param string $description Resolved description.
		 * @param int    $post_id     Queried post id, or 0.
		 */
		return (string) apply_filters( 'wpcseo_meta_description', $description, $post_id );
	}

	/**
	 * Apply the per-post canonical override.
	 *
	 * @param string   $canonical Canonical URL WordPress resolved.
	 * @param \WP_Post $post      Post being rendered.
	 */
	public static function canonical( string $canonical, \WP_Post $post ): string {
		$custom = trim( (string) Meta::get( $post->ID, Meta::CANONICAL ) );

		if ( '' !== $custom ) {
			$canonical = $custom;
		}

		/**
		 * Filters the canonical URL.
		 *
		 * @param string   $canonical Canonical URL.
		 * @param \WP_Post $post      Post being rendered.
		 */
		return (string) apply_filters( 'wpcseo_canonical', $canonical, $post );
	}

	/**
	 * Merge the per-post robots directives into the native wp_robots output.
	 *
	 * @param array<string, mixed> $robots Directives keyed by name.
	 *
	 * @return array<string, mixed>
	 */
	public static function robots( array $robots ): array {
		$post_id = self::queried_post_id();

		if ( $post_id > 0 ) {
			$robots = Robots::apply( $robots, Meta::robots_values( $post_id ) );
		} else {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$robots = Robots::apply( $robots, Terms::robots_values( $term->term_id ) );
			}
		}

		/**
		 * Filters the robots directives.
		 *
		 * @param array $robots  Directives keyed by name.
		 * @param int   $post_id Queried post id, or 0.
		 */
		return (array) apply_filters( 'wpcseo_robots', $robots, $post_id );
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

	/**
	 * Derive a description from a post's excerpt or opening content.
	 *
	 * Deriving it here avoids get_the_excerpt(), which runs the_content filters:
	 * expensive, and able to recurse through other plugins during wp_head.
	 *
	 * @param int $post_id Post id.
	 */
	public static function fallback_description( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$source = '' !== trim( $post->post_excerpt )
			? $post->post_excerpt
			: strip_shortcodes( $post->post_content );

		return Templates::truncate( Analyzer::to_text( $source ), self::DESCRIPTION_MAX );
	}
}
