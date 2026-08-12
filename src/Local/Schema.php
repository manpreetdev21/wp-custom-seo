<?php
/**
 * LocalBusiness structured data.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Local;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Entities\Registry;
use WPCustomSeo\Schema\Graph\Graph;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Adds each location to the schema graph.
 *
 * Only properties the administrator actually filled in are emitted. A location
 * with no address produces no PostalAddress, and a day with no hours produces
 * no OpeningHoursSpecification, rather than a placeholder that would misinform
 * anyone reading it.
 */
final class Schema {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		if ( ! Settings::enabled( 'enable_local_seo' ) ) {
			return;
		}

		add_filter( 'wpcseo_schema', array( self::class, 'add' ), 10, 2 );
	}

	/**
	 * Add location nodes to a graph.
	 *
	 * @param Graph  $graph   Graph under construction.
	 * @param string $context Either `page` or `aggregate`.
	 */
	public static function add( Graph $graph, string $context = 'page' ): Graph {
		if ( 'page' === $context && ! self::should_output() ) {
			return $graph;
		}

		foreach ( Locations::all() as $location ) {
			$graph->add( self::node( $location ) );
		}

		return $graph;
	}

	/**
	 * Whether the current request should carry location data.
	 *
	 * Defaults to the front page, where a business states who it is. Emitting
	 * a shop's address on every article would not describe those pages.
	 */
	private static function should_output(): bool {
		$scope = (string) Settings::get( 'local_schema_scope', 'front' );

		if ( 'all' === $scope ) {
			return true;
		}

		return is_front_page();
	}

	/**
	 * Build one LocalBusiness node.
	 *
	 * @param WP_Post $location Location.
	 *
	 * @return array<string, mixed>
	 */
	public static function node( WP_Post $location ): array {
		$id   = Registry::id( 'location', (string) $location->ID );
		$type = (string) get_post_meta( $location->ID, Locations::TYPE, true );

		$node = array(
			'@type'              => array_key_exists( $type, Locations::business_types() ) ? $type : 'LocalBusiness',
			'@id'                => $id,
			'name'               => get_the_title( $location ),
			'url'                => home_url( '/' ),
			'parentOrganization' => Registry::reference( Registry::id( 'organization' ) ),
		);

		$address = self::address( $location->ID );

		if ( $address ) {
			$node['address'] = $address;
		}

		$phone = trim( (string) get_post_meta( $location->ID, Locations::PHONE, true ) );

		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}

		$email = trim( (string) get_post_meta( $location->ID, Locations::EMAIL, true ) );

		if ( is_email( $email ) ) {
			$node['email'] = $email;
		}

		$price = trim( (string) get_post_meta( $location->ID, Locations::PRICE_RANGE, true ) );

		if ( '' !== $price ) {
			$node['priceRange'] = $price;
		}

		$geo = self::geo( $location->ID );

		if ( $geo ) {
			$node['geo'] = $geo;
		}

		$image = Registry::image( (string) get_post_meta( $location->ID, Locations::IMAGE, true ), $id . '/image' );

		if ( null !== $image ) {
			$node['image'] = $image;
		}

		$same_as = Registry::urls( (string) get_post_meta( $location->ID, Locations::SAME_AS, true ) );

		if ( $same_as ) {
			$node['sameAs'] = $same_as;
		}

		$hours = self::opening_hours( $location->ID );

		if ( $hours ) {
			$node['openingHoursSpecification'] = $hours;
		}

		/**
		 * Filters a location entity.
		 *
		 * @param array   $node     LocalBusiness node.
		 * @param WP_Post $location Location post.
		 */
		return (array) apply_filters( 'wpcseo_entity_location', $node, $location );
	}

	/**
	 * PostalAddress, or an empty array when no part of it was supplied.
	 *
	 * @param int $post_id Location id.
	 *
	 * @return array<string, mixed>
	 */
	private static function address( int $post_id ): array {
		$parts = array(
			'streetAddress'   => Locations::STREET,
			'addressLocality' => Locations::LOCALITY,
			'addressRegion'   => Locations::REGION,
			'postalCode'      => Locations::POSTCODE,
			'addressCountry'  => Locations::COUNTRY,
		);

		$address = array( '@type' => 'PostalAddress' );

		foreach ( $parts as $property => $key ) {
			$value = trim( (string) get_post_meta( $post_id, $key, true ) );

			if ( '' !== $value ) {
				$address[ $property ] = $value;
			}
		}

		return count( $address ) > 1 ? $address : array();
	}

	/**
	 * GeoCoordinates, or an empty array when either value is missing or invalid.
	 *
	 * @param int $post_id Location id.
	 *
	 * @return array<string, mixed>
	 */
	private static function geo( int $post_id ): array {
		$latitude  = trim( (string) get_post_meta( $post_id, Locations::LATITUDE, true ) );
		$longitude = trim( (string) get_post_meta( $post_id, Locations::LONGITUDE, true ) );

		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return array();
		}

		$latitude  = (float) $latitude;
		$longitude = (float) $longitude;

		// Coordinates outside the real range would place the business nowhere.
		if ( $latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0 ) {
			return array();
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $latitude,
			'longitude' => $longitude,
		);
	}

	/**
	 * OpeningHoursSpecification entries for the days that have hours.
	 *
	 * @param int $post_id Location id.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function opening_hours( int $post_id ): array {
		$specifications = array();

		foreach ( Locations::hours( $post_id ) as $day => $row ) {
			if ( $row['closed'] ) {
				$specifications[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $day,
					'opens'     => '00:00',
					'closes'    => '00:00',
				);

				continue;
			}

			if ( '' === $row['open'] || '' === $row['close'] ) {
				continue;
			}

			$specifications[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/' . $day,
				'opens'     => $row['open'],
				'closes'    => $row['close'],
			);
		}

		return $specifications;
	}
}
