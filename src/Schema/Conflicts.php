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
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	/**
	 * Warn, on this plugin's own screens, when something else is also emitting.
	 *
	 * Shown here rather than site-wide on purpose. A conflict notice on every
	 * admin page is a notice people learn to scroll past, and the fix — deciding
	 * which plugin owns the site's metadata — is a decision made on these
	 * screens anyway.
	 *
	 * No plugin is deactivated and no setting is changed. Two SEO plugins is a
	 * choice with consequences, not an error, and which one should win is not
	 * something this code can know.
	 */
	public static function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $screen || ! str_contains( (string) $screen->id, 'wp-custom-seo' ) ) {
			return;
		}

		$sources = array_values(
			array_filter(
				self::detect(),
				// WooCommerce describes products, which this plugin defers to
				// rather than competes with, so it is not a conflict to report.
				static fn ( array $source ): bool => 'WooCommerce' !== $source['name']
			)
		);

		if ( ! $sources ) {
			return;
		}

		$names = implode( ', ', array_column( $sources, 'name' ) );

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
			esc_html__( 'Another SEO plugin is active.', 'wp-custom-seo' ),
			esc_html(
				sprintf(
					/* translators: %s: comma-separated plugin names. */
					__( '%s is also generating meta tags, canonicals, Open Graph tags, structured data and sitemaps for this site.', 'wp-custom-seo' ),
					$names
				)
			),
			esc_html__( 'Two sources describing the same page means duplicate tags, and where they disagree a search engine has to pick one. Choose which plugin owns this — either switch this one’s output off under Settings → General, or deactivate the other. Nothing has been changed for you, and no stored data has been touched.', 'wp-custom-seo' )
		);
	}

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
