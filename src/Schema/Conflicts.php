<?php
/**
 * Detection of other structured-data sources.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Reports other plugins that also emit structured data.
 *
 * Detection is by loaded class or constant, never by editing or disabling
 * another plugin. Two sources describing the same page is not automatically
 * an error, but it is worth telling the administrator about.
 */
final class Conflicts {

	/**
	 * Known emitters, keyed by label, detected by class or constant.
	 *
	 * @return array<string, array{type: string, symbol: string, note: string}>
	 */
	private static function known(): array {
		return array(
			'Yoast SEO'      => array(
				'type'   => 'constant',
				'symbol' => 'WPSEO_VERSION',
				'note'   => __( 'Outputs a full schema graph of its own.', 'wp-custom-seo' ),
			),
			'Rank Math'      => array(
				'type'   => 'constant',
				'symbol' => 'RANK_MATH_VERSION',
				'note'   => __( 'Outputs a full schema graph of its own.', 'wp-custom-seo' ),
			),
			'All in One SEO' => array(
				'type'   => 'constant',
				'symbol' => 'AIOSEO_VERSION',
				'note'   => __( 'Outputs a full schema graph of its own.', 'wp-custom-seo' ),
			),
			'SEOPress'       => array(
				'type'   => 'constant',
				'symbol' => 'SEOPRESS_VERSION',
				'note'   => __( 'Outputs a full schema graph of its own.', 'wp-custom-seo' ),
			),
			'Schema Pro'     => array(
				'type'   => 'class',
				'symbol' => 'BSF_AIOSRS_Pro_Markup',
				'note'   => __( 'Outputs structured data for selected post types.', 'wp-custom-seo' ),
			),
			'WooCommerce'    => array(
				'type'   => 'class',
				'symbol' => 'WC_Structured_Data',
				'note'   => __( 'Outputs Product and Offer data on shop pages. Usually complementary rather than conflicting.', 'wp-custom-seo' ),
			),
		);
	}

	/**
	 * Which known emitters are currently active.
	 *
	 * @return array<int, array{name: string, note: string}>
	 */
	public static function detect(): array {
		$found = array();

		foreach ( self::known() as $name => $definition ) {
			$active = 'constant' === $definition['type']
				? defined( $definition['symbol'] )
				: class_exists( $definition['symbol'], false );

			if ( $active ) {
				$found[] = array(
					'name' => $name,
					'note' => $definition['note'],
				);
			}
		}

		/**
		 * Filters the detected structured-data sources.
		 *
		 * @param array $found Detected sources.
		 */
		return (array) apply_filters( 'wpcseo_schema_conflicts', $found );
	}

	/**
	 * Whether another full SEO plugin is also emitting a schema graph.
	 */
	public static function has_competing_seo_plugin(): bool {
		foreach ( self::detect() as $source ) {
			if ( ! in_array( $source['name'], array( 'WooCommerce' ), true ) ) {
				return true;
			}
		}

		return false;
	}
}
