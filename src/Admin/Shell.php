<?php
/**
 * The admin application shell.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Audit\Finding;
use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps every plugin screen in one sidebar-and-header layout.
 *
 * **How it attaches.** `Menu::register()` hands WordPress a callback for each
 * screen. Rather than editing fifteen page classes to print a sidebar, the menu
 * wraps each callback once: the shell opens, the page's own callback runs
 * untouched, the shell closes. A screen added later by an add-on gets the
 * chrome for free and never knows it happened.
 *
 * **Why it is not a `.wrap`.** The templates inside already use
 * `.wrap.wpcseo-wrap`, which is what WordPress relocates admin notices into. If
 * the shell claimed `.wrap` as well there would be two candidates and notices
 * would land somewhere unpredictable. So the shell uses its own class, the
 * inner `.wrap` keeps doing its job, and the stylesheet neutralises the inner
 * element's margins so the nesting is invisible.
 *
 * **Why the badge count is cheap.** The sidebar shows how many critical issues
 * the audit found. That number comes from the audit's existing hour-long
 * transient and is never computed on demand — a sidebar that ran a site-wide
 * scan on every page load would make the whole admin slow to punish you for
 * looking at it.
 */
final class Shell {

	/**
	 * Whether the shell has been opened for this request.
	 *
	 * @var bool
	 */
	private static bool $open = false;

	/**
	 * Wrap a page callback in the shell.
	 *
	 * @param string   $slug     Menu slug of the screen being rendered.
	 * @param callable $callback The screen's own render callback.
	 */
	public static function render( string $slug, $callback ): void {
		if ( ! is_callable( $callback ) ) {
			return;
		}

		self::open( $slug );

		call_user_func( $callback );

		self::close();
	}

	/**
	 * Print the shell up to the point the page content begins.
	 *
	 * @param string $slug Current menu slug.
	 */
	public static function open( string $slug ): void {
		if ( self::$open ) {
			return;
		}

		self::$open = true;

		$sections = Nav::sections( Menu::pages() );
		$badges   = self::badges();

		?>
		<div class="wpcseo-app" data-wpcseo-app>
			<a class="screen-reader-text wpcseo-skip" href="#wpcseo-main">
				<?php esc_html_e( 'Skip to SEO content', 'wp-custom-seo' ); ?>
			</a>

			<header class="wpcseo-topbar">
				<div class="wpcseo-topbar__brand">
					<button
						type="button"
						class="wpcseo-iconbtn wpcseo-topbar__toggle"
						data-wpcseo-toggle-nav
						aria-expanded="true"
						aria-controls="wpcseo-sidebar"
					>
						<?php echo Nav::icon( 'panel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Toggle navigation', 'wp-custom-seo' ); ?></span>
					</button>

					<span class="wpcseo-logo" aria-hidden="true">
						<?php echo Nav::icon( 'gauge', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?>
					</span>
					<span class="wpcseo-topbar__name"><?php esc_html_e( 'WP Custom SEO', 'wp-custom-seo' ); ?></span>
				</div>

				<div class="wpcseo-topbar__search">
					<button type="button" class="wpcseo-search-trigger" data-wpcseo-open-search>
						<?php echo Nav::icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?>
						<span><?php esc_html_e( 'Search settings, tools and screens…', 'wp-custom-seo' ); ?></span>
						<kbd class="wpcseo-kbd" data-wpcseo-shortcut>Ctrl K</kbd>
					</button>
				</div>

				<div class="wpcseo-topbar__actions">
					<button
						type="button"
						class="wpcseo-iconbtn"
						data-wpcseo-theme
						aria-label="<?php esc_attr_e( 'Change colour theme', 'wp-custom-seo' ); ?>"
					>
						<span data-wpcseo-theme-icon="light"><?php echo Nav::icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?></span>
						<span data-wpcseo-theme-icon="dark" hidden><?php echo Nav::icon( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?></span>
					</button>

					<a
						class="wpcseo-iconbtn"
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . ToolsPage::SLUG ) ); ?>"
						aria-label="<?php esc_attr_e( 'System status and tools', 'wp-custom-seo' ); ?>"
					>
						<?php echo Nav::icon( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?>
					</a>

					<?php if ( $badges['critical'] > 0 ) : ?>
						<a class="wpcseo-topbar__alert" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AuditPage::SLUG ) ); ?>">
							<?php echo Nav::icon( 'alert', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?>
							<span>
								<?php
								printf(
									/* translators: %s: number of critical issues. */
									esc_html( _n( '%s critical issue', '%s critical issues', $badges['critical'], 'wp-custom-seo' ) ),
									esc_html( number_format_i18n( $badges['critical'] ) )
								);
								?>
							</span>
						</a>
					<?php endif; ?>
				</div>
			</header>

			<div class="wpcseo-app__body">
				<nav
					class="wpcseo-sidebar"
					id="wpcseo-sidebar"
					aria-label="<?php esc_attr_e( 'SEO sections', 'wp-custom-seo' ); ?>"
				>
					<?php foreach ( $sections as $section ) : ?>
						<div class="wpcseo-navgroup">
							<h2 class="wpcseo-navgroup__label"><?php echo esc_html( (string) $section['label'] ); ?></h2>
							<ul class="wpcseo-navgroup__list">
								<?php foreach ( $section['items'] as $item ) : ?>
									<?php
									$is_current = (string) $item['slug'] === $slug;
									$count      = (int) ( $badges['pages'][ $item['slug'] ] ?? 0 );
									?>
									<li>
										<a
											class="wpcseo-navlink<?php echo $is_current ? ' is-current' : ''; ?>"
											href="<?php echo esc_url( admin_url( 'admin.php?page=' . (string) $item['slug'] ) ); ?>"
											<?php echo $is_current ? ' aria-current="page"' : ''; ?>
											data-wpcseo-tip="<?php echo esc_attr( (string) $item['label'] ); ?>"
										>
											<?php echo Nav::icon( (string) $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?>
											<span class="wpcseo-navlink__label"><?php echo esc_html( (string) $item['label'] ); ?></span>
											<?php if ( $count > 0 ) : ?>
												<span class="wpcseo-navlink__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
											<?php endif; ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</nav>

				<main class="wpcseo-app__main" id="wpcseo-main" tabindex="-1">
		<?php
	}

	/**
	 * Close the shell and print the command palette.
	 */
	public static function close(): void {
		if ( ! self::$open ) {
			return;
		}

		self::$open = false;

		?>
				</main>
			</div>

			<div class="wpcseo-palette" data-wpcseo-palette hidden>
				<div class="wpcseo-palette__backdrop" data-wpcseo-close-search></div>
				<div
					class="wpcseo-palette__panel"
					role="dialog"
					aria-modal="true"
					aria-label="<?php esc_attr_e( 'Search WP Custom SEO', 'wp-custom-seo' ); ?>"
				>
					<div class="wpcseo-palette__field">
						<?php echo Nav::icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG from Nav::icons(). ?>
						<input
							type="search"
							class="wpcseo-palette__input"
							data-wpcseo-search-input
							placeholder="<?php esc_attr_e( 'Search settings, tools and screens…', 'wp-custom-seo' ); ?>"
							aria-label="<?php esc_attr_e( 'Search settings, tools and screens', 'wp-custom-seo' ); ?>"
							autocomplete="off"
							spellcheck="false"
						>
						<kbd class="wpcseo-kbd">Esc</kbd>
					</div>

					<ul
						class="wpcseo-palette__results"
						data-wpcseo-search-results
						role="listbox"
						aria-label="<?php esc_attr_e( 'Search results', 'wp-custom-seo' ); ?>"
					></ul>

					<p class="wpcseo-palette__empty" data-wpcseo-search-empty hidden>
						<?php esc_html_e( 'Nothing matches that.', 'wp-custom-seo' ); ?>
					</p>
				</div>
			</div>

			<div class="wpcseo-toasts" data-wpcseo-toasts aria-live="polite" aria-atomic="false"></div>
		</div>
		<?php
	}

	/**
	 * Counts shown against sidebar entries.
	 *
	 * Read from the audit's existing cache only. When no report has been built
	 * yet the badges are simply absent, which is the honest answer — inventing a
	 * zero would claim the site had been checked and found clean.
	 *
	 * @return array{critical: int, pages: array<string, int>}
	 */
	public static function badges(): array {
		$empty = array(
			'critical' => 0,
			'pages'    => array(),
		);

		if ( ! Settings::enabled( 'enable_seo' ) ) {
			return $empty;
		}

		$report = get_transient( 'wpcseo_audit_report' );

		if ( ! is_array( $report ) || ! isset( $report['findings'] ) ) {
			return $empty;
		}

		$critical = (int) ( $report['totals'][ Finding::CRITICAL ] ?? 0 );

		return array(
			'critical' => $critical,
			'pages'    => array_filter( array( AuditPage::SLUG => $critical ) ),
		);
	}

	/**
	 * The search index handed to the command palette.
	 *
	 * Built from the two registries the plugin already keeps — the menu and the
	 * settings schema — so a screen or a field added later is searchable without
	 * anyone remembering to add it to a list.
	 *
	 * @return array<int, array{label: string, group: string, url: string}>
	 */
	public static function search_index(): array {
		$index    = array();
		$groups   = Nav::groups();
		$map      = Nav::map();
		$sections = Menu::pages();

		foreach ( $sections as $slug => $page ) {
			$group = (string) ( $map[ (string) $slug ]['group'] ?? '' );

			$index[] = array(
				'label' => (string) ( $page['menu_title'] ?? $page['title'] ?? $slug ),
				'group' => (string) ( $groups[ $group ] ?? __( 'Screens', 'wp-custom-seo' ) ),
				'url'   => admin_url( 'admin.php?page=' . (string) $slug ),
			);
		}

		foreach ( Settings::schema() as $section_id => $section ) {
			$section_title = (string) ( $section['title'] ?? $section_id );

			foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
				// A hidden field has no control on any screen, so sending someone
				// to a tab where they cannot see it would be a dead end.
				if ( ! empty( $field['hidden'] ) ) {
					continue;
				}

				$index[] = array(
					'label' => (string) ( $field['label'] ?? '' ),
					'group' => $section_title,
					'url'   => admin_url(
						'admin.php?page=' . Settings::PAGE . '&tab=' . rawurlencode( (string) $section_id )
					),
				);
			}
		}

		return array_values(
			array_filter(
				$index,
				static fn ( array $entry ): bool => '' !== trim( $entry['label'] )
			)
		);
	}
}
