<?php
/**
 * SEO dashboard screen.
 *
 * Composed almost entirely from the UI component library. Every component
 * escapes each argument it is given and returns finished markup, so the echoes
 * below carry a phpcs:ignore naming that contract. Anything not coming from a
 * component is escaped inline in the usual way.
 *
 * @package WPCustomSeo
 *
 * @var string               $version    Plugin version.
 * @var string               $db_version Applied schema version.
 * @var array<string, bool>  $modules    Module label => enabled.
 * @var array<string, mixed> $health     Health summary from Health::summary().
 * @var int|null             $links      Internal links recorded, or null when tracking is off.
 * @var string               $sitemap    Sitemap index URL, or an empty string.
 */

use WPCustomSeo\Admin\AuditPage;
use WPCustomSeo\Admin\GeoPage;
use WPCustomSeo\Admin\UI;
use WPCustomSeo\Audit\Finding;
use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

$wpcseo_levels = Finding::levels();
$wpcseo_notes  = Finding::level_descriptions();
$wpcseo_tones  = array(
	Finding::CRITICAL    => 'bad',
	Finding::IMPORTANT   => 'warn',
	Finding::OPPORTUNITY => 'info',
	Finding::GOOD        => 'good',
);

$wpcseo_enabled = count( array_filter( $modules ) );

$wpcseo_head = UI::page_header(
	__( 'SEO Dashboard', 'wp-custom-seo' ),
	__( 'What this plugin has checked, what it found, and what is worth doing next.', 'wp-custom-seo' ),
	UI::button( __( 'Run a full audit', 'wp-custom-seo' ), admin_url( 'admin.php?page=' . AuditPage::SLUG ), 'primary', 'shield' )
		. UI::button( __( 'Settings', 'wp-custom-seo' ), admin_url( 'admin.php?page=' . Settings::PAGE ), 'secondary', 'sliders' )
);

?>
<div class="wrap wpcseo-wrap">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::page_header() escapes every argument it is given and returns finished markup.
	echo $wpcseo_head;
	?>

	<?php if ( ! Settings::enabled( 'enable_seo' ) ) : ?>
		<?php
		$wpcseo_off = UI::alert(
			'bad',
			__( 'SEO output is switched off.', 'wp-custom-seo' ),
			__( 'No titles, descriptions, canonicals, robots directives or structured data are being emitted. Turn on “Enable SEO output” under Settings → General.', 'wp-custom-seo' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::alert() escapes every argument it is given and returns finished markup.
		echo $wpcseo_off;
		?>
	<?php endif; ?>

	<?php if ( ! $health['available'] ) : ?>
		<?php
		$wpcseo_empty = UI::empty_state(
			'shield',
			__( 'No audit has run yet', 'wp-custom-seo' ),
			__( 'The health score is read from the site audit rather than measured again here, so there is nothing to show until the audit has run once. It takes a few seconds and makes no network requests.', 'wp-custom-seo' ),
			UI::button( __( 'Run the site audit', 'wp-custom-seo' ), admin_url( 'admin.php?page=' . AuditPage::SLUG ), 'primary', 'shield' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::empty_state() escapes every argument it is given and returns finished markup.
		echo $wpcseo_empty;
		?>
	<?php else : ?>

		<div class="wpcseo-grid">
			<section class="wpcseo-card" aria-labelledby="wpcseo-card-health">
				<h2 class="wpcseo-card__title" id="wpcseo-card-health"><?php esc_html_e( 'SEO health', 'wp-custom-seo' ); ?></h2>

				<div class="wpcseo-health">
					<?php
					$wpcseo_ring = UI::score_ring(
						(int) $health['score'],
						__( 'SEO health', 'wp-custom-seo' ),
						(string) $health['band']
					);

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::score_ring() escapes every argument it is given and returns finished markup.
					echo $wpcseo_ring;
					?>

					<div class="wpcseo-meters">
						<?php
						foreach ( (array) $health['categories'] as $wpcseo_category ) {
							$wpcseo_meter = UI::meter(
								(string) $wpcseo_category['label'],
								(int) $wpcseo_category['score'],
								'',
								(string) $wpcseo_category['link']
							);

							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::meter() escapes every argument it is given and returns finished markup.
							echo $wpcseo_meter;
						}
						?>
					</div>
				</div>

				<p class="description">
					<?php esc_html_e( 'This score is this plugin’s own reading of the checks it ran. It is not a Google ranking score and is not derived from one. Each row links to the screen that can fix it.', 'wp-custom-seo' ); ?>
				</p>
			</section>

			<section class="wpcseo-card" aria-labelledby="wpcseo-card-issues">
				<h2 class="wpcseo-card__title" id="wpcseo-card-issues"><?php esc_html_e( 'Issues found', 'wp-custom-seo' ); ?></h2>

				<div class="wpcseo-grid wpcseo-grid--stats">
					<?php
					foreach ( array( Finding::CRITICAL, Finding::IMPORTANT, Finding::OPPORTUNITY ) as $wpcseo_level ) {
						$wpcseo_tile = UI::stat(
							(string) $wpcseo_levels[ $wpcseo_level ],
							number_format_i18n( (int) ( $health['totals'][ $wpcseo_level ] ?? 0 ) ),
							(string) ( $wpcseo_notes[ $wpcseo_level ] ?? '' ),
							(string) $wpcseo_tones[ $wpcseo_level ]
						);

						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::stat() escapes every argument it is given and returns finished markup.
						echo $wpcseo_tile;
					}
					?>
				</div>

				<?php if ( ! $health['issues'] ) : ?>
					<p><?php esc_html_e( 'Every check the audit ran came back in order.', 'wp-custom-seo' ); ?></p>
				<?php else : ?>
					<hr>
					<?php foreach ( (array) $health['issues'] as $wpcseo_issue ) : ?>
						<a class="wpcseo-issuerow is-<?php echo esc_attr( (string) $wpcseo_issue['level'] ); ?>" href="<?php echo esc_url( (string) $wpcseo_issue['link'] ); ?>">
							<span class="wpcseo-issuerow__dot" aria-hidden="true"></span>
							<span class="wpcseo-issuerow__title"><?php echo esc_html( (string) $wpcseo_issue['title'] ); ?></span>
							<?php
							$wpcseo_badge = UI::badge(
								(string) $wpcseo_levels[ $wpcseo_issue['level'] ],
								(string) $wpcseo_tones[ $wpcseo_issue['level'] ]
							);

							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::badge() escapes every argument it is given and returns finished markup.
							echo $wpcseo_badge;
							?>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>

				<p>
					<?php
					$wpcseo_all = UI::button( __( 'View all findings', 'wp-custom-seo' ), admin_url( 'admin.php?page=' . AuditPage::SLUG ), 'secondary' );

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::button() escapes every argument it is given and returns finished markup.
					echo $wpcseo_all;
					?>
				</p>
			</section>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'At a glance', 'wp-custom-seo' ); ?></h2>

	<div class="wpcseo-grid wpcseo-grid--stats">
		<?php
		$wpcseo_tiles = array(
			UI::stat(
				__( 'Modules on', 'wp-custom-seo' ),
				number_format_i18n( $wpcseo_enabled ) . ' / ' . number_format_i18n( count( $modules ) ),
				__( 'Every feature is optional.', 'wp-custom-seo' )
			),
			UI::stat(
				__( 'Internal links tracked', 'wp-custom-seo' ),
				null === $links ? '—' : number_format_i18n( $links ),
				null === $links
					? __( 'Link tracking is off.', 'wp-custom-seo' )
					: __( 'Rebuilt whenever a post is saved.', 'wp-custom-seo' ),
				null === $links ? 'neutral' : 'good'
			),
			UI::stat(
				__( 'XML sitemap', 'wp-custom-seo' ),
				'' !== $sitemap ? __( 'Serving', 'wp-custom-seo' ) : __( 'Off', 'wp-custom-seo' ),
				'' !== $sitemap
					? __( 'Search engines have a full list of your pages.', 'wp-custom-seo' )
					: __( 'Discovery depends entirely on internal links.', 'wp-custom-seo' ),
				'' !== $sitemap ? 'good' : 'warn'
			) . ( '' !== $sitemap
				? '<p class="wpcseo-stat__action">' . UI::button( __( 'Open sitemap', 'wp-custom-seo' ), $sitemap, 'ghost', 'external', true ) . '</p>'
				: '' ),
			UI::stat(
				__( 'AI answer readiness', 'wp-custom-seo' ),
				__( 'Review', 'wp-custom-seo' ),
				__( 'How quotable your pages are as a source.', 'wp-custom-seo' ),
				'info'
			) . '<p class="wpcseo-stat__action">' . UI::button( __( 'Open AI SEO', 'wp-custom-seo' ), admin_url( 'admin.php?page=' . GeoPage::SLUG ), 'ghost', 'sparkle' ) . '</p>',
		);

		foreach ( $wpcseo_tiles as $wpcseo_tile ) {
			echo '<section class="wpcseo-card">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from UI::stat() and UI::button(), each of which escapes every argument it is given.
			echo $wpcseo_tile;
			echo '</section>';
		}
		?>
	</div>

	<div class="wpcseo-grid">
		<section class="wpcseo-card" aria-labelledby="wpcseo-card-modules">
			<h2 class="wpcseo-card__title" id="wpcseo-card-modules"><?php esc_html_e( 'Modules', 'wp-custom-seo' ); ?></h2>

			<ul class="wpcseo-modules">
				<?php foreach ( $modules as $wpcseo_label => $wpcseo_on ) : ?>
					<li>
						<?php
						$wpcseo_state = UI::state( (bool) $wpcseo_on );

						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::state() escapes every argument it is given and returns finished markup.
						echo $wpcseo_state;
						?>
						<span><?php echo esc_html( (string) $wpcseo_label ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-status">
			<h2 class="wpcseo-card__title" id="wpcseo-card-status"><?php esc_html_e( 'System', 'wp-custom-seo' ); ?></h2>

			<dl class="wpcseo-list">
				<?php
				$wpcseo_rows = array(
					__( 'Plugin version', 'wp-custom-seo' ) => $version,
					__( 'Schema version', 'wp-custom-seo' ) => $db_version,
					__( 'WordPress', 'wp-custom-seo' ) => (string) get_bloginfo( 'version' ),
					__( 'PHP', 'wp-custom-seo' )       => PHP_VERSION,
				);

				if ( '' !== (string) $health['generated'] ) {
					$wpcseo_rows[ __( 'Audit last run', 'wp-custom-seo' ) ] = (string) mysql2date(
						(string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ),
						(string) $health['generated']
					);
				}

				foreach ( $wpcseo_rows as $wpcseo_term => $wpcseo_value ) {
					$wpcseo_row = UI::row( (string) $wpcseo_term, (string) $wpcseo_value );

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::row() escapes every argument it is given and returns finished markup.
					echo $wpcseo_row;
				}
				?>
			</dl>
		</section>
	</div>
</div>
