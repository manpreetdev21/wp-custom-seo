<?php
/**
 * Breadcrumb trail.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

use WPCustomSeo\Core\Settings;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and renders a breadcrumb trail.
 *
 * The trail is derived from real site structure — post ancestors, taxonomy
 * parents, archive relationships — rather than from the path a visitor
 * happened to take, so the matching BreadcrumbList structured data describes
 * what is actually on the page.
 */
final class Breadcrumbs {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		if ( ! Settings::enabled( 'enable_breadcrumbs' ) ) {
			return;
		}

		add_shortcode( 'wpcseo_breadcrumbs', array( self::class, 'shortcode' ) );
		add_action( 'init', array( self::class, 'register_block' ) );
		add_action( 'rest_api_init', array( self::class, 'register_rest_field' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Register the front-end stylesheet.
	 *
	 * Registered rather than enqueued outright: the renderer asks for it only
	 * when a trail is actually printed, so a page without breadcrumbs carries
	 * no extra request.
	 */
	public static function enqueue(): void {
		wp_register_style(
			'wpcseo-breadcrumbs',
			WP_CUSTOM_SEO_URL . 'assets/css/breadcrumbs.css',
			array(),
			\WPCustomSeo\VERSION
		);
	}

	/**
	 * Trail for the current request.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	public static function trail(): array {
		$items = array( self::item( (string) self::home_label(), home_url( '/' ) ) );

		if ( is_front_page() ) {
			$items = array();
		} elseif ( is_singular() ) {
			$post = get_queried_object();

			if ( $post instanceof WP_Post ) {
				$items   = array_merge( $items, self::post_ancestry( $post ) );
				$items[] = self::item( self::post_label( $post ), (string) get_permalink( $post ) );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$items   = array_merge( $items, self::term_ancestry( $term ) );
				$items[] = self::item( $term->name, (string) get_term_link( $term ) );
			}
		} elseif ( is_post_type_archive() ) {
			$object = get_queried_object();

			if ( $object instanceof WP_Post_Type ) {
				$items[] = self::item( (string) $object->labels->name, (string) get_post_type_archive_link( $object->name ) );
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();

			if ( $author instanceof WP_User ) {
				$items[] = self::item( $author->display_name, (string) get_author_posts_url( $author->ID ) );
			}
		} elseif ( is_search() ) {
			$items[] = self::item(
				/* translators: %s: search query. */
				sprintf( __( 'Search results for “%s”', 'wp-custom-seo' ), get_search_query() ),
				(string) get_search_link()
			);
		} elseif ( is_404() ) {
			$items[] = self::item( __( 'Page not found', 'wp-custom-seo' ), '' );
		} elseif ( is_date() ) {
			$items = array_merge( $items, self::date_ancestry() );
		}

		return self::finalise( $items );
	}

	/**
	 * Trail for one post, independent of the main query.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	public static function trail_for_post( WP_Post $post ): array {
		if ( (int) get_option( 'page_on_front' ) === $post->ID ) {
			return array();
		}

		$items = array( self::item( (string) self::home_label(), home_url( '/' ) ) );
		$items = array_merge( $items, self::post_ancestry( $post ) );

		$items[] = self::item( self::post_label( $post ), (string) get_permalink( $post ) );

		return self::finalise( $items );
	}

	/**
	 * Mark the last item as current and let developers reshape the trail.
	 *
	 * @param array<int, array{name: string, url: string, current: bool}> $items Trail items.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	private static function finalise( array $items ): array {
		if ( $items ) {
			$items[ count( $items ) - 1 ]['current'] = true;
		}

		/**
		 * Filters the breadcrumb trail.
		 *
		 * @param array $items Trail items, each with `name`, `url` and `current`.
		 */
		return array_values( (array) apply_filters( 'wpcseo_breadcrumb_trail', $items ) );
	}

	/**
	 * Build one trail item.
	 *
	 * @param string $name Label.
	 * @param string $url  URL, empty for an item with no destination.
	 *
	 * @return array{name: string, url: string, current: bool}
	 */
	private static function item( string $name, string $url ): array {
		return array(
			'name'    => $name,
			'url'     => $url,
			'current' => false,
		);
	}

	/**
	 * Everything above a post: its archive, ancestors or primary term.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	private static function post_ancestry( WP_Post $post ): array {
		$items = array();
		$type  = get_post_type_object( $post->post_type );

		if ( $type instanceof WP_Post_Type && $type->has_archive ) {
			$link = get_post_type_archive_link( $type->name );

			if ( is_string( $link ) ) {
				$items[] = self::item( (string) $type->labels->name, $link );
			}
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				$ancestor = get_post( (int) $ancestor_id );

				if ( $ancestor instanceof WP_Post ) {
					$items[] = self::item( self::post_label( $ancestor ), (string) get_permalink( $ancestor ) );
				}
			}

			return $items;
		}

		$term = self::primary_term( $post );

		if ( $term instanceof WP_Term ) {
			$items   = array_merge( $items, self::term_ancestry( $term ) );
			$items[] = self::item( $term->name, (string) get_term_link( $term ) );
		}

		return $items;
	}

	/**
	 * The term a post sits under, if any.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function primary_term( WP_Post $post ): ?WP_Term {
		foreach ( get_object_taxonomies( $post, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->hierarchical || ! $taxonomy->public ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy->name );

			if ( is_array( $terms ) && $terms ) {
				$term = reset( $terms );

				/**
				 * Filters the term used as a post's breadcrumb parent.
				 *
				 * @param WP_Term $term Chosen term.
				 * @param WP_Post $post Post.
				 */
				return apply_filters( 'wpcseo_breadcrumb_primary_term', $term, $post );
			}
		}

		return null;
	}

	/**
	 * Parent terms above a term.
	 *
	 * @param WP_Term $term Term.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	private static function term_ancestry( WP_Term $term ): array {
		$items   = array();
		$parents = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );

		foreach ( $parents as $parent_id ) {
			$parent = get_term( (int) $parent_id, $term->taxonomy );

			if ( $parent instanceof WP_Term ) {
				$items[] = self::item( $parent->name, (string) get_term_link( $parent ) );
			}
		}

		return $items;
	}

	/**
	 * Year, month and day steps for a date archive.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	private static function date_ancestry(): array {
		$year  = (int) get_query_var( 'year' );
		$month = (int) get_query_var( 'monthnum' );
		$day   = (int) get_query_var( 'day' );
		$items = array();

		if ( $year > 0 ) {
			$items[] = self::item( (string) $year, (string) get_year_link( $year ) );
		}

		if ( $year > 0 && $month > 0 ) {
			$items[] = self::item( (string) date_i18n( 'F', mktime( 0, 0, 0, $month, 1, $year ) ), (string) get_month_link( $year, $month ) );
		}

		if ( $year > 0 && $month > 0 && $day > 0 ) {
			$items[] = self::item( (string) $day, (string) get_day_link( $year, $month, $day ) );
		}

		return $items;
	}

	/**
	 * Label for a post, preferring its breadcrumb title.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function post_label( WP_Post $post ): string {
		$custom = trim( (string) Meta::get( $post->ID, Meta::BREADCRUMB_TITLE ) );

		return '' !== $custom ? $custom : (string) get_the_title( $post );
	}

	/**
	 * Label for the home step.
	 */
	private static function home_label(): string {
		$label = trim( (string) Settings::get( 'breadcrumb_home_label', '' ) );

		return '' !== $label ? $label : __( 'Home', 'wp-custom-seo' );
	}

	/**
	 * Render the trail as HTML.
	 *
	 * @param array<int, array{name: string, url: string, current: bool}>|null $items Trail, or null for the current request.
	 */
	public static function render( ?array $items = null ): string {
		$items = null === $items ? self::trail() : $items;

		if ( count( $items ) < 2 ) {
			return '';
		}

		if ( ! is_admin() ) {
			wp_enqueue_style( 'wpcseo-breadcrumbs' );
		}

		$separator = (string) Settings::get( 'breadcrumb_separator', '/' );
		$html      = '<nav class="wpcseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'wp-custom-seo' ) . '"><ol>';

		foreach ( $items as $index => $item ) {
			$html .= '<li>';

			if ( $index > 0 ) {
				$html .= '<span class="wpcseo-breadcrumbs__sep" aria-hidden="true">' . esc_html( $separator ) . '</span> ';
			}

			if ( $item['current'] || '' === $item['url'] ) {
				$html .= '<span aria-current="page">' . esc_html( $item['name'] ) . '</span>';
			} else {
				$html .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
			}

			$html .= '</li>';
		}

		$html .= '</ol></nav>';

		/**
		 * Filters the rendered breadcrumb HTML.
		 *
		 * @param string $html  Rendered trail.
		 * @param array  $items Trail items.
		 */
		return (string) apply_filters( 'wpcseo_breadcrumb_html', $html, $items );
	}

	/**
	 * Shortcode handler.
	 */
	public static function shortcode(): string {
		return self::render();
	}

	/**
	 * Register the block.
	 *
	 * The editor script is inline ES5 rather than a compiled bundle, so the
	 * plugin needs no build step to ship a working block.
	 */
	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script( 'wpcseo-breadcrumb-block', '', array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-server-side-render' ), \WPCustomSeo\VERSION, true );

		wp_add_inline_script(
			'wpcseo-breadcrumb-block',
			"( function ( blocks, element, i18n, ssr ) {
				blocks.registerBlockType( 'wp-custom-seo/breadcrumbs', {
					apiVersion: 2,
					title: i18n.__( 'Breadcrumbs', 'wp-custom-seo' ),
					icon: 'menu-alt',
					category: 'design',
					edit: function () {
						return element.createElement( ssr, { block: 'wp-custom-seo/breadcrumbs' } );
					},
					save: function () { return null; }
				} );
			} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.serverSideRender );"
		);

		register_block_type(
			'wp-custom-seo/breadcrumbs',
			array(
				'api_version'     => 2,
				'editor_script'   => 'wpcseo-breadcrumb-block',
				'render_callback' => array( self::class, 'render_block' ),
			)
		);
	}

	/**
	 * Block render callback.
	 */
	public static function render_block(): string {
		$html = self::render();

		if ( '' === $html && is_admin() ) {
			return '<p>' . esc_html__( 'The breadcrumb trail appears here on the front end.', 'wp-custom-seo' ) . '</p>';
		}

		return $html;
	}

	/**
	 * Expose the trail on post responses.
	 */
	public static function register_rest_field(): void {
		register_rest_field(
			Meta::post_types(),
			'wpcseo_breadcrumbs',
			array(
				'get_callback' => static function ( array $post ): array {
					$object = get_post( (int) $post['id'] );

					return $object instanceof WP_Post ? self::trail_for_post( $object ) : array();
				},
				'schema'       => array(
					'description' => __( 'Breadcrumb trail for this content.', 'wp-custom-seo' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}
}
