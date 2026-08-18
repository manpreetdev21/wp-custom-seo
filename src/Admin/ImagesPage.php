<?php
/**
 * Image SEO screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Media\ImageSeo;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Image SEO: what the media library is missing.
 */
final class ImagesPage {

	public const SLUG = 'wp-custom-seo-images';

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
			'title'      => __( 'Image SEO', 'wp-custom-seo' ),
			'menu_title' => __( 'Image SEO', 'wp-custom-seo' ),
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

		$missing = ImageSeo::missing_alt( 50 );
		$sampled = ImageSeo::sampled();

		$vars = array(
			'total'      => ImageSeo::total(),
			'missing'    => $missing,
			'duplicates' => ImageSeo::duplicate_alt( 25 ),
			'sampled'    => $sampled,
			'sample_max' => ImageSeo::SAMPLE,
			'formats'    => ImageSeo::formats(),
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, read here to report the site's setting rather than to change it.
			'lazy'       => (bool) apply_filters( 'wp_lazy_loading_enabled', true, 'img', 'wp_get_attachment_image' ),
			'library'    => admin_url( 'upload.php' ),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/images.php';
	}
}
