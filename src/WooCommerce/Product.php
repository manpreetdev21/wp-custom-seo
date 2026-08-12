<?php
/**
 * Product structured data.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\WooCommerce;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Entities\Registry;
use WPCustomSeo\Schema\Graph\Graph;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Describes a WooCommerce product in the schema graph.
 *
 * Every property is read from the product. Price, availability, SKU, brand,
 * ratings and reviews are emitted only where the shop actually holds that
 * data — a product with no reviews gets no aggregateRating, and a product
 * with no price gets no Offer. Inventing any of them would misrepresent the
 * shop to a shopper and risk a structured-data manual action.
 */
final class Product {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_schema', array( self::class, 'add' ), 10, 2 );
		add_filter( 'wpcseo_schema_type', array( self::class, 'schema_type' ), 10, 2 );
	}

	/**
	 * A product page is described as a Product, not an Article.
	 *
	 * @param string   $type Resolved type.
	 * @param \WP_Post $post Post being described.
	 */
	public static function schema_type( string $type, \WP_Post $post ): string {
		return 'product' === $post->post_type ? 'none' : $type;
	}

	/**
	 * Add the product node when a product is being viewed.
	 *
	 * @param Graph  $graph   Graph under construction.
	 * @param string $context Either `page` or `aggregate`.
	 */
	public static function add( Graph $graph, string $context = 'page' ): Graph {
		if ( 'page' !== $context || ! is_singular( 'product' ) ) {
			return $graph;
		}

		$node = self::node( (int) get_queried_object_id() );

		if ( null !== $node ) {
			$graph->add( $node );
		}

		return $graph;
	}

	/**
	 * Build a Product node, or null when the product cannot be read.
	 *
	 * @param int $post_id Product post id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function node( int $post_id ): ?array {
		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			return null;
		}

		$permalink = (string) get_permalink( $post_id );

		$node = array(
			'@type' => 'Product',
			'@id'   => $permalink . '#product',
			'name'  => (string) $product->get_name(),
			'url'   => $permalink,
		);

		$description = Analyzer::to_text( (string) $product->get_short_description() );

		if ( '' === $description ) {
			$description = Analyzer::to_text( (string) $product->get_description() );
		}

		if ( '' !== $description ) {
			$node['description'] = Templates::truncate( $description, 300 );
		}

		$sku = trim( (string) $product->get_sku() );

		if ( '' !== $sku ) {
			$node['sku'] = $sku;
		}

		$image = self::image( $product, $permalink );

		if ( null !== $image ) {
			$node['image'] = $image;
		}

		$brand = self::brand( $post_id );

		if ( array() !== $brand ) {
			$node['brand'] = $brand;
		}

		$offers = self::offers( $product, $permalink );

		if ( array() !== $offers ) {
			$node['offers'] = $offers;
		}

		$rating = self::rating( $product );

		if ( array() !== $rating ) {
			$node['aggregateRating'] = $rating;
		}

		/**
		 * Filters the product entity.
		 *
		 * @param array $node    Product node.
		 * @param int   $post_id Product post id.
		 */
		return (array) apply_filters( 'wpcseo_entity_product', $node, $post_id );
	}

	/**
	 * Product image, or null.
	 *
	 * @param object $product   WooCommerce product.
	 * @param string $permalink Product URL.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function image( object $product, string $permalink ): ?array {
		$image_id = (int) $product->get_image_id();

		if ( $image_id <= 0 ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $image_id, 'full' );

		return is_string( $url ) ? Registry::image( $url, $permalink . '#productimage' ) : null;
	}

	/**
	 * Brand, from the shop's own taxonomy or the configured site brand.
	 *
	 * @param int $post_id Product post id.
	 *
	 * @return array<string, mixed>
	 */
	private static function brand( int $post_id ): array {
		/**
		 * Filters the taxonomy consulted for a product brand.
		 *
		 * WooCommerce core added `product_brand` in 9.6; older shops use a
		 * taxonomy from a plugin, which this filter can point at.
		 *
		 * @param string $taxonomy Taxonomy name.
		 * @param int    $post_id  Product post id.
		 */
		$taxonomy = (string) apply_filters( 'wpcseo_product_brand_taxonomy', 'product_brand', $post_id );

		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = get_the_terms( $post_id, $taxonomy );

			if ( is_array( $terms ) && $terms ) {
				$term = reset( $terms );

				return array(
					'@type' => 'Brand',
					'name'  => $term->name,
				);
			}
		}

		$name = trim( (string) Settings::get( 'brand_name', '' ) );

		return '' !== $name ? Registry::reference( Registry::id( 'brand' ) ) : array();
	}

	/**
	 * Offer, only when the product actually carries a price.
	 *
	 * @param object $product   WooCommerce product.
	 * @param string $permalink Product URL.
	 *
	 * @return array<string, mixed>
	 */
	private static function offers( object $product, string $permalink ): array {
		$price = $product->get_price();

		if ( '' === $price || null === $price || ! is_numeric( $price ) ) {
			return array();
		}

		$offer = array(
			'@type'         => 'Offer',
			'url'           => $permalink,
			'price'         => (string) wc_format_decimal( $price, wc_get_price_decimals() ),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $product->is_in_stock()
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
			'seller'        => Registry::reference( Registry::id( 'organization' ) ),
		);

		$sale_end = $product->get_date_on_sale_to();

		if ( $sale_end ) {
			$offer['priceValidUntil'] = gmdate( 'Y-m-d', (int) $sale_end->getTimestamp() );
		}

		return $offer;
	}

	/**
	 * AggregateRating, only when reviews exist.
	 *
	 * @param object $product WooCommerce product.
	 *
	 * @return array<string, mixed>
	 */
	private static function rating( object $product ): array {
		$count = (int) $product->get_rating_count();

		if ( $count < 1 ) {
			return array();
		}

		$average = (float) $product->get_average_rating();

		if ( $average <= 0.0 ) {
			return array();
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) round( $average, 2 ),
			'reviewCount' => $count,
			'bestRating'  => '5',
			'worstRating' => '1',
		);
	}
}
