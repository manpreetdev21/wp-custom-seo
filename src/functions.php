<?php
/**
 * Template functions for theme authors.
 *
 * Declared in the global namespace so a theme can call them directly.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpcseo_breadcrumbs' ) ) {
	/**
	 * Print the breadcrumb trail.
	 *
	 * Output is already escaped by the renderer.
	 */
	function wpcseo_breadcrumbs(): void {
		echo wpcseo_get_breadcrumbs(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup assembled and escaped in Breadcrumbs::render().
	}
}

if ( ! function_exists( 'wpcseo_get_breadcrumbs' ) ) {
	/**
	 * Return the breadcrumb trail as HTML.
	 */
	function wpcseo_get_breadcrumbs(): string {
		return \WPCustomSeo\SEO\Breadcrumbs::render();
	}
}

if ( ! function_exists( 'wpcseo_breadcrumb_trail' ) ) {
	/**
	 * Return the breadcrumb trail as data.
	 *
	 * @return array<int, array{name: string, url: string, current: bool}>
	 */
	function wpcseo_breadcrumb_trail(): array {
		return \WPCustomSeo\SEO\Breadcrumbs::trail();
	}
}
