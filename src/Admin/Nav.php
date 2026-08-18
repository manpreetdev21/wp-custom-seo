<?php
/**
 * Admin navigation model and icon set.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Groups the plugin's screens, and holds the one icon set they all draw from.
 *
 * **Why the grouping lives here rather than on each screen.** Every page class
 * registers itself through the `wpcseo_admin_pages` filter and says what it is
 * called. Where it *sits* relative to the other thirteen is not a fact that
 * screen knows — it is a decision about the whole product, and putting a
 * `'group' => 'technical'` key in eleven separate files would scatter one
 * decision across eleven places and guarantee the order drifts. So the pages
 * stay ignorant of the menu, and the menu is described once, here.
 *
 * A screen registered by a third party and absent from this map is not dropped:
 * it lands in a trailing group of its own, so an add-on that knows nothing about
 * this file still appears in the sidebar.
 *
 * **The icons.** One set, inline, single-stroke, drawn on the same 24-unit grid
 * at the same 1.75 weight, so nothing in the sidebar looks heavier than its
 * neighbour. They are inline SVG rather than an icon font or a library because
 * the whole set used here is under three kilobytes — a request for an icon
 * library would cost more than the icons do, and Dashicons cannot be stroked to
 * match. Each is `currentColor`, so the active, hover and dark-mode states are
 * handled by the colour the sidebar already sets.
 */
final class Nav {

	/**
	 * Section headings, in order.
	 *
	 * @return array<string, string>
	 */
	public static function groups(): array {
		return array(
			'overview'  => __( 'Overview', 'wp-custom-seo' ),
			'optimize'  => __( 'Optimize', 'wp-custom-seo' ),
			'intel'     => __( 'Intelligence', 'wp-custom-seo' ),
			'technical' => __( 'Technical', 'wp-custom-seo' ),
			'manage'    => __( 'Manage', 'wp-custom-seo' ),
		);
	}

	/**
	 * Which group and icon each screen belongs to.
	 *
	 * @return array<string, array{group: string, icon: string}>
	 */
	public static function map(): array {
		$map = array(
			'wp-custom-seo'           => array(
				'group' => 'overview',
				'icon'  => 'gauge',
			),
			'wp-custom-seo-audit'     => array(
				'group' => 'overview',
				'icon'  => 'shield',
			),
			'wp-custom-seo-bulk'      => array(
				'group' => 'optimize',
				'icon'  => 'list',
			),
			'wp-custom-seo-links'     => array(
				'group' => 'optimize',
				'icon'  => 'link',
			),
			'wp-custom-seo-images'    => array(
				'group' => 'optimize',
				'icon'  => 'image',
			),
			'wp-custom-seo-schema'    => array(
				'group' => 'optimize',
				'icon'  => 'braces',
			),
			'wp-custom-seo-geo'       => array(
				'group' => 'intel',
				'icon'  => 'sparkle',
			),
			'wp-custom-seo-ai'        => array(
				'group' => 'intel',
				'icon'  => 'wand',
			),
			'wp-custom-seo-brief'     => array(
				'group' => 'intel',
				'icon'  => 'document',
			),
			'wp-custom-seo-search'    => array(
				'group' => 'intel',
				'icon'  => 'chart',
			),
			'wp-custom-seo-robots'    => array(
				'group' => 'technical',
				'icon'  => 'robot',
			),
			'wp-custom-seo-redirects' => array(
				'group' => 'technical',
				'icon'  => 'arrows',
			),
			'wp-custom-seo-404'       => array(
				'group' => 'technical',
				'icon'  => 'alert',
			),
			'wp-custom-seo-tools'     => array(
				'group' => 'manage',
				'icon'  => 'tools',
			),
			'wp-custom-seo-settings'  => array(
				'group' => 'manage',
				'icon'  => 'sliders',
			),
		);

		/**
		 * Filters where each admin screen sits in the sidebar.
		 *
		 * Keyed by menu slug, each entry naming a group from Nav::groups() and an
		 * icon from Nav::icons(). A screen absent from this map still appears.
		 *
		 * @param array<string, array{group: string, icon: string}> $map Navigation placement.
		 */
		return (array) apply_filters( 'wpcseo_nav_map', $map );
	}

	/**
	 * Registered pages arranged into their groups.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages from the menu registry.
	 *
	 * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
	 */
	public static function sections( array $pages ): array {
		$map     = self::map();
		$groups  = self::groups();
		$buckets = array_fill_keys( array_keys( $groups ), array() );
		$orphans = array();

		foreach ( $pages as $slug => $page ) {
			$slug  = (string) $slug;
			$entry = array(
				'slug'  => $slug,
				'label' => (string) ( $page['menu_title'] ?? $page['title'] ?? $slug ),
				'icon'  => (string) ( $map[ $slug ]['icon'] ?? 'dot' ),
			);

			$group = (string) ( $map[ $slug ]['group'] ?? '' );

			if ( isset( $buckets[ $group ] ) ) {
				$buckets[ $group ][] = $entry;

				continue;
			}

			// An add-on's screen, or one this map has not been told about. It goes
			// last rather than nowhere.
			$orphans[] = $entry;
		}

		$sections = array();

		foreach ( $buckets as $group => $items ) {
			if ( $items ) {
				$sections[] = array(
					'label' => $groups[ $group ],
					'items' => $items,
				);
			}
		}

		if ( $orphans ) {
			$sections[] = array(
				'label' => __( 'More', 'wp-custom-seo' ),
				'items' => $orphans,
			);
		}

		return $sections;
	}

	/**
	 * The icon set, as SVG path bodies drawn on a 24-unit grid.
	 *
	 * @return array<string, string>
	 */
	public static function icons(): array {
		return array(
			'gauge'    => '<path d="M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="m13.4 10.6 3.9-3.9"/><path d="M4 18a9 9 0 1 1 16 0"/>',
			'shield'   => '<path d="M12 3.5 5 6.2v5.1c0 4.2 2.8 7.6 7 9.2 4.2-1.6 7-5 7-9.2V6.2Z"/><path d="m9.2 12 2 2 3.6-3.7"/>',
			'list'     => '<path d="M9 6.5h11"/><path d="M9 12h11"/><path d="M9 17.5h11"/><path d="M4.5 6.5h.01"/><path d="M4.5 12h.01"/><path d="M4.5 17.5h.01"/>',
			'link'     => '<path d="M10.5 13.5a3.6 3.6 0 0 0 5.3.4l2.6-2.6a3.6 3.6 0 0 0-5.1-5.1l-1.5 1.5"/><path d="M13.5 10.5a3.6 3.6 0 0 0-5.3-.4l-2.6 2.6a3.6 3.6 0 0 0 5.1 5.1l1.5-1.5"/>',
			'image'    => '<rect x="3.5" y="5" width="17" height="14" rx="2.5"/><path d="M9 11a1.4 1.4 0 1 0 0-2.8A1.4 1.4 0 0 0 9 11Z"/><path d="m4 16.5 4.2-3.6a1.8 1.8 0 0 1 2.4 0L15 17"/><path d="m14 14 1.6-1.4a1.8 1.8 0 0 1 2.4 0L20 14.5"/>',
			'braces'   => '<path d="M9 4.5c-2 0-2.5 1-2.5 2.5v2c0 1.5-.7 2.4-2 3 1.3.6 2 1.5 2 3v2c0 1.5.5 2.5 2.5 2.5"/><path d="M15 4.5c2 0 2.5 1 2.5 2.5v2c0 1.5.7 2.4 2 3-1.3.6-2 1.5-2 3v2c0 1.5-.5 2.5-2.5 2.5"/>',
			'sparkle'  => '<path d="m12 3.5 1.9 4.9 4.9 1.9-4.9 1.9L12 17.1l-1.9-4.9-4.9-1.9 4.9-1.9Z"/><path d="M18 16.5v3.5"/><path d="M16.3 18.3h3.4"/>',
			'wand'     => '<path d="m5 19 9.5-9.5"/><path d="m13 6 5 5"/><path d="M16.5 3.5v2.2"/><path d="M20.5 7.5h-2.2"/><path d="m19.6 4.4-1.5 1.5"/>',
			'document' => '<path d="M13.5 3.5H7a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8.5Z"/><path d="M13.5 3.5v5h5"/><path d="M9 13h6"/><path d="M9 16.5h4"/>',
			'chart'    => '<path d="M4 20h16"/><path d="M7.5 20v-5.5"/><path d="M12 20V8.5"/><path d="M16.5 20v-8"/>',
			'robot'    => '<rect x="4.5" y="8" width="15" height="11" rx="2.5"/><path d="M12 8V4.5"/><path d="M9.5 13h.01"/><path d="M14.5 13h.01"/><path d="M9.5 16h5"/>',
			'arrows'   => '<path d="M4 8.5h12.5"/><path d="m14 6 2.5 2.5L14 11"/><path d="M20 15.5H7.5"/><path d="M10 13l-2.5 2.5L10 18"/>',
			'alert'    => '<path d="M12 4.8 3.8 19h16.4Z"/><path d="M12 10v3.6"/><path d="M12 16.4h.01"/>',
			'tools'    => '<path d="m14.5 6.5 3-3a4 4 0 0 1-5 5l-7 7a1.8 1.8 0 1 0 2.5 2.5l7-7a4 4 0 0 1 5-5l-3 3"/>',
			'sliders'  => '<path d="M4 8h8"/><path d="M16 8h4"/><path d="M4 16h4"/><path d="M12 16h8"/><path d="M14 8a2 2 0 1 0 0-.01"/><path d="M8 16a2 2 0 1 0 0-.01"/>',
			'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m15.8 15.8 4.2 4.2"/>',
			'help'     => '<circle cx="12" cy="12" r="8.5"/><path d="M9.7 9.6a2.4 2.4 0 1 1 2.9 2.7v1.3"/><path d="M12.5 16.6h.01"/>',
			'moon'     => '<path d="M20 14.3A8.5 8.5 0 0 1 9.7 4a8.5 8.5 0 1 0 10.3 10.3Z"/>',
			'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 3v1.8"/><path d="M12 19.2V21"/><path d="M3 12h1.8"/><path d="M19.2 12H21"/><path d="m5.6 5.6 1.3 1.3"/><path d="m17.1 17.1 1.3 1.3"/><path d="m18.4 5.6-1.3 1.3"/><path d="m6.9 17.1-1.3 1.3"/>',
			'panel'    => '<rect x="3.5" y="5" width="17" height="14" rx="2.5"/><path d="M9.5 5v14"/>',
			'external' => '<path d="M13.5 5h5.5v5.5"/><path d="m19 5-7 7"/><path d="M18 14v4a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 18V7.5A1.5 1.5 0 0 1 6 6h4"/>',
			'dot'      => '<circle cx="12" cy="12" r="3.5"/>',
		);
	}

	/**
	 * Render one icon.
	 *
	 * The paths are literals from the map above, never user input, so they are
	 * printed rather than escaped — `esc_html` would render the markup as text
	 * and `wp_kses` would have to be handed an SVG allow-list to permit exactly
	 * what this method already controls.
	 *
	 * @param string $name    Icon name.
	 * @param int    $size    Pixel size.
	 * @param string $classes Extra class names.
	 */
	public static function icon( string $name, int $size = 20, string $classes = '' ): string {
		$icons = self::icons();
		$body  = $icons[ $name ] ?? $icons['dot'];

		return sprintf(
			'<svg class="wpcseo-icon %1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $classes ),
			$size,
			$body
		);
	}
}
