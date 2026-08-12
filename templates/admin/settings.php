<?php
/**
 * SEO settings screen.
 *
 * Sections are shown one at a time. There are fourteen of them and several run
 * to a dozen fields, so a single page meant scrolling past the AI provider
 * settings to reach the sitemap ones.
 *
 * @package WPCustomSeo
 *
 * @var string $page Settings page slug used by the Settings API.
 */

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

global $wp_settings_sections, $wp_settings_fields;

$wpcseo_sections = (array) ( $wp_settings_sections[ $page ] ?? array() );

// Sections that registered no visible field — everything in them is hidden and
// owned by another screen — would otherwise be an empty tab.
$wpcseo_sections = array_filter(
	$wpcseo_sections,
	static fn ( array $section ): bool => ! empty( $wp_settings_fields[ $page ][ $section['id'] ] )
);

$wpcseo_ids = array_keys( $wpcseo_sections );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing which tab to read; the save has its own nonce.
$wpcseo_current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

if ( ! in_array( $wpcseo_current, $wpcseo_ids, true ) ) {
	$wpcseo_first   = reset( $wpcseo_ids );
	$wpcseo_current = false === $wpcseo_first ? '' : (string) $wpcseo_first;
}

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'SEO Settings', 'wp-custom-seo' ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice. ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'wp-custom-seo' ); ?></p>
		</div>
	<?php endif; ?>

	<?php settings_errors( Settings::OPTION ); ?>

	<nav class="wpcseo-tabnav nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'wp-custom-seo' ); ?>">
		<?php foreach ( $wpcseo_sections as $wpcseo_id => $wpcseo_section ) : ?>
			<a
				class="nav-tab<?php echo $wpcseo_id === $wpcseo_current ? ' nav-tab-active' : ''; ?>"
				href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => Settings::PAGE,
							'tab'  => $wpcseo_id,
						),
						admin_url( 'admin.php' )
					)
				);
				?>
				"
				<?php echo $wpcseo_id === $wpcseo_current ? 'aria-current="page"' : ''; ?>
			>
				<?php echo esc_html( (string) $wpcseo_section['title'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
		<?php settings_fields( Settings::GROUP ); ?>

		<?php
		// Which section this save covers. Everything outside it keeps the value
		// it already has, so saving one tab cannot clear another.
		printf(
			'<input type="hidden" name="%s[wpcseo_sections][]" value="%s">',
			esc_attr( Settings::OPTION ),
			esc_attr( $wpcseo_current )
		);
		?>

		<?php if ( isset( $wpcseo_sections[ $wpcseo_current ] ) ) : ?>
			<?php
			$wpcseo_section = $wpcseo_sections[ $wpcseo_current ];

			if ( is_callable( $wpcseo_section['callback'] ) ) {
				call_user_func( $wpcseo_section['callback'], $wpcseo_section );
			}
			?>

			<table class="form-table" role="presentation">
				<?php do_settings_fields( $page, $wpcseo_current ); ?>
			</table>
		<?php endif; ?>

		<?php submit_button(); ?>
	</form>
</div>
