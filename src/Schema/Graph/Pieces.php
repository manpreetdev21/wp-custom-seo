<?php
/**
 * Schema graph builders.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema\Graph;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Entities\Registry;
use WPCustomSeo\Schema\Faq;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Breadcrumbs;
use WPCustomSeo\SEO\Frontend;
use WPCustomSeo\SEO\Meta;
use WPCustomSeo\SEO\Templates;
use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Assembles the nodes that describe a request or a single post.
 *
 * Every property is derived from content that actually exists. Where a value
 * cannot be established the property is omitted rather than guessed, because
 * structured data that disagrees with the visible page is worse than none.
 */
final class Pieces {

	/**
	 * Page-level types that describe the page itself rather than a work on it.
	 */
	private const PAGE_TYPES = array( 'WebPage', 'AboutPage', 'ContactPage' );

	/**
	 * Graph for the current request.
	 */
	public static function current(): Graph {
		$graph = new Graph();

		self::site( $graph );

		if ( is_singular() ) {
			$post = get_queried_object();

			if ( $post instanceof WP_Post ) {
				self::post( $graph, $post );
			}
		} elseif ( is_front_page() ) {
			self::front_page( $graph );
		}

		return self::finish( $graph );
	}

	/**
	 * Graph for one post, independent of the main query.
	 *
	 * @param int $post_id Post id.
	 */
	public static function for_post( int $post_id ): Graph {
		$graph = new Graph();

		self::site( $graph );

		$post = get_post( $post_id );

		if ( $post instanceof WP_Post ) {
			self::post( $graph, $post );
		}

		return self::finish( $graph );
	}

	/**
	 * One graph covering many posts.
	 *
	 * Site entities are stated once no matter how many posts are included,
	 * because the graph merges nodes that share an identifier.
	 *
	 * @param WP_Post[] $posts Posts to describe.
	 */
	public static function for_posts( array $posts ): Graph {
		$graph = new Graph();

		self::site( $graph );

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				self::post( $graph, $post );
			}
		}

		return self::finish( $graph, 'aggregate' );
	}

	/**
	 * Apply the developer filter and hand back the graph.
	 *
	 * @param Graph  $graph   Graph under construction.
	 * @param string $context Either `page` for a single request or `aggregate`.
	 */
	private static function finish( Graph $graph, string $context = 'page' ): Graph {
		/**
		 * Fires before the schema graph is finalised.
		 *
		 * @param Graph  $graph   Graph under construction.
		 * @param string $context `page` or `aggregate`.
		 */
		do_action( 'wpcseo_before_schema', $graph, $context );

		/**
		 * Filters the finished schema graph.
		 *
		 * @param Graph  $graph   Graph.
		 * @param string $context `page` for one request, `aggregate` for the API.
		 */
		$graph = apply_filters( 'wpcseo_schema', $graph, $context );

		/**
		 * Fires after the schema graph is finalised.
		 *
		 * @param Graph  $graph   Finished graph.
		 * @param string $context `page` or `aggregate`.
		 */
		do_action( 'wpcseo_after_schema', $graph, $context );

		return $graph;
	}

	/**
	 * Site-level entities present on every page.
	 *
	 * @param Graph $graph Graph under construction.
	 */
	private static function site( Graph $graph ): void {
		$graph->add( Registry::organization() );
		$graph->add( Registry::image( (string) Settings::get( 'schema_org_logo', '' ), Registry::id( 'logo' ) ) );
		$graph->add( Registry::brand() );
		$graph->add( Registry::website() );
	}

	/**
	 * Front page nodes.
	 *
	 * @param Graph $graph Graph under construction.
	 */
	private static function front_page( Graph $graph ): void {
		$node = array(
			'@type'      => 'WebPage',
			'@id'        => home_url( '/' ) . '#webpage',
			'url'        => home_url( '/' ),
			'name'       => (string) get_bloginfo( 'name' ),
			'isPartOf'   => Registry::reference( Registry::id( 'website' ) ),
			'about'      => Registry::reference( Registry::id( 'organization' ) ),
			'inLanguage' => Registry::language(),
		);

		$description = trim( (string) get_bloginfo( 'description' ) );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$graph->add( $node );
	}

	/**
	 * Nodes describing one post.
	 *
	 * @param Graph   $graph Graph under construction.
	 * @param WP_Post $post  Post.
	 */
	private static function post( Graph $graph, WP_Post $post ): void {
		$type = self::resolve_type( $post );

		if ( 'none' === $type ) {
			return;
		}

		$permalink   = (string) get_permalink( $post );
		$webpage_id  = $permalink . '#webpage';
		$is_the_page = in_array( $type, self::PAGE_TYPES, true );

		$webpage = array(
			'@type'         => $is_the_page ? $type : 'WebPage',
			'@id'           => $webpage_id,
			'url'           => $permalink,
			'name'          => (string) get_the_title( $post ),
			'isPartOf'      => Registry::reference( Registry::id( 'website' ) ),
			'datePublished' => self::date( $post->post_date_gmt ),
			'dateModified'  => self::date( $post->post_modified_gmt ),
			'inLanguage'    => Registry::language(),
		);

		foreach ( array( 'datePublished', 'dateModified' ) as $date_key ) {
			if ( '' === $webpage[ $date_key ] ) {
				unset( $webpage[ $date_key ] );
			}
		}

		$description = self::description( $post );

		if ( '' !== $description ) {
			$webpage['description'] = $description;
		}

		$image = Registry::featured_image( $post );

		if ( null !== $image ) {
			$graph->add( $image );
			$webpage['primaryImageOfPage'] = Registry::reference( $image['@id'] );
		}

		$breadcrumb = self::breadcrumb( $post, $webpage_id );

		if ( null !== $breadcrumb ) {
			$graph->add( $breadcrumb );
			$webpage['breadcrumb'] = Registry::reference( $breadcrumb['@id'] );
		}

		$questions = Faq::entities( $post );

		if ( $questions ) {
			// The page is still whatever it was; FAQPage is added to that
			// rather than replacing it, because a page that answers questions
			// is usually also an article or a service page.
			$webpage['@type']      = array_values( array_unique( array_merge( (array) $webpage['@type'], array( 'FAQPage' ) ) ) );
			$webpage['mainEntity'] = $questions;
		}

		$graph->add( $webpage );

		if ( $is_the_page ) {
			return;
		}

		$author = Registry::person( (int) $post->post_author );

		$article = array(
			'@type'            => $type,
			'@id'              => $permalink . '#article',
			'isPartOf'         => Registry::reference( $webpage_id ),
			'mainEntityOfPage' => Registry::reference( $webpage_id ),
			'headline'         => self::headline( $post ),
			'publisher'        => Registry::reference( Registry::id( 'organization' ) ),
			'inLanguage'       => Registry::language(),
		);

		foreach ( array( 'datePublished', 'dateModified' ) as $date_key ) {
			if ( isset( $webpage[ $date_key ] ) ) {
				$article[ $date_key ] = $webpage[ $date_key ];
			}
		}

		if ( null !== $author ) {
			$graph->add( $author );
			$article['author'] = Registry::reference( $author['@id'] );
		}

		if ( '' !== $description ) {
			$article['description'] = $description;
		}

		if ( null !== $image ) {
			$article['image'] = Registry::reference( $image['@id'] );
		}

		$word_count = count( Analyzer::words( Analyzer::to_text( $post->post_content ) ) );

		if ( $word_count > 0 ) {
			$article['wordCount'] = $word_count;
		}

		$sections = self::sections( $post );

		if ( $sections ) {
			$article['articleSection'] = $sections;
		}

		$graph->add( $article );
	}

	/**
	 * Replace an identifier's fragment rather than appending to it.
	 *
	 * @param string $id   An existing node identifier.
	 * @param string $name The fragment the new identifier should carry.
	 */
	public static function fragment( string $id, string $name ): string {
		$base = strtok( $id, '#' );

		return ( false === $base ? $id : $base ) . '#' . $name;
	}

	/**
	 * BreadcrumbList for a post, or null.
	 *
	 * Emitted only when breadcrumbs are switched on, because structured data
	 * must describe what the page actually shows.
	 *
	 * @param WP_Post $post       Post.
	 * @param string  $webpage_id Identifier of the page node.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function breadcrumb( WP_Post $post, string $webpage_id ): ?array {
		if ( ! Settings::enabled( 'enable_breadcrumbs' ) ) {
			return null;
		}

		$trail = Breadcrumbs::trail_for_post( $post );

		if ( count( $trail ) < 2 ) {
			return null;
		}

		$elements = array();

		foreach ( $trail as $index => $item ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $item['name'],
			);

			// The final crumb is the page itself; schema.org guidance is to
			// leave its item off rather than link a page to itself.
			if ( ! $item['current'] && '' !== $item['url'] ) {
				$element['item'] = $item['url'];
			}

			$elements[] = $element;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			// Built from the permalink rather than by appending to the page's
			// own identifier, which already carries a #webpage fragment: a URL
			// has one fragment, and "#webpage#breadcrumb" is not a valid one.
			'@id'             => self::fragment( $webpage_id, 'breadcrumb' ),
			'itemListElement' => $elements,
		);
	}

	/**
	 * Which schema type applies to a post.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function resolve_type( WP_Post $post ): string {
		$stored = (string) Meta::get( $post->ID, Meta::SCHEMA_TYPE );

		if ( '' !== $stored && 'auto' !== $stored ) {
			return $stored;
		}

		$type = 'page' === $post->post_type ? 'WebPage' : ( 'post' === $post->post_type ? 'BlogPosting' : 'Article' );

		/**
		 * Filters the resolved schema type for a post.
		 *
		 * @param string  $type Schema type.
		 * @param WP_Post $post Post.
		 */
		return (string) apply_filters( 'wpcseo_schema_type', $type, $post );
	}

	/**
	 * Headline, trimmed to the length structured data guidance expects.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function headline( WP_Post $post ): string {
		return Templates::truncate( (string) get_the_title( $post ), 110 );
	}

	/**
	 * Description matching what the page actually says.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function description( WP_Post $post ): string {
		$description = trim( (string) Meta::get( $post->ID, Meta::DESCRIPTION ) );

		return '' !== $description ? $description : Frontend::fallback_description( $post->ID );
	}

	/**
	 * Category names assigned to the post.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return string[]
	 */
	private static function sections( WP_Post $post ): array {
		$taxonomies = get_object_taxonomies( $post, 'objects' );
		$names      = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy->hierarchical || ! $taxonomy->public ) {
				continue;
			}

			foreach ( (array) get_the_terms( $post, $taxonomy->name ) as $term ) {
				if ( $term instanceof WP_Term ) {
					$names[] = $term->name;
				}
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Format a GMT date column as ISO 8601, or an empty string.
	 *
	 * @param string $gmt_date Date in `Y-m-d H:i:s` GMT.
	 */
	private static function date( string $gmt_date ): string {
		if ( '' === $gmt_date || '0000-00-00 00:00:00' === $gmt_date ) {
			return '';
		}

		return gmdate( DATE_W3C, (int) strtotime( $gmt_date . ' UTC' ) );
	}
}
