<?php
/**
 * AI SEO / GEO screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\GEO\Readiness;
use WPCustomSeo\GEO\Visibility;
use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → AI SEO / GEO: how quotable the site's pages are, and AI visibility.
 */
final class GeoPage {

	public const SLUG = 'wp-custom-seo-geo';

	/**
	 * How many recent posts are scored on one screen load.
	 *
	 * Scoring reads post content, so this is a real cost per row. Twenty is
	 * enough to see a pattern without turning a page load into a site scan.
	 */
	private const BATCH = 20;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
	}

	/**
	 * Add the screen to the menu registry.
	 *
	 * @param array<string, array<string, mixed>> $pages Registered pages.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function register( array $pages ): array {
		$pages[ self::SLUG ] = array(
			'title'      => __( 'AI SEO / GEO', 'wp-custom-seo' ),
			'menu_title' => __( 'AI SEO / GEO', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Meta::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => self::BATCH,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$rows   = array();
		$totals = array_fill_keys( array_keys( Readiness::dimensions() ), 0 );

		foreach ( $query->posts as $post ) {
			$result = Readiness::for_post( (int) $post->ID );

			foreach ( $result['dimensions'] as $dimension ) {
				$totals[ $dimension['id'] ] += (int) $dimension['score'];
			}

			$rows[] = array(
				'id'         => (int) $post->ID,
				'title'      => (string) get_the_title( $post ),
				'edit'       => (string) get_edit_post_link( (int) $post->ID ),
				'score'      => (int) $result['score'],
				'dimensions' => $result['dimensions'],
			);
		}

		$count = max( 1, count( $rows ) );

		$averages = array_map(
			static fn ( int $sum ): int => (int) round( $sum / $count ),
			$totals
		);

		$vars = array(
			'rows'      => $rows,
			'labels'    => Readiness::dimensions(),
			'averages'  => $averages,
			'overall'   => $rows ? (int) round( array_sum( array_column( $rows, 'score' ) ) / $count ) : 0,
			'scanned'   => count( $rows ),
			'providers' => Visibility::all(),
			'ready'     => Visibility::ready(),
			'crawlers'  => admin_url( 'admin.php?page=' . \WPCustomSeo\Core\Settings::PAGE ),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/geo.php';
	}
}
