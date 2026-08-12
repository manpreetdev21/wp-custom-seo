<?php
/**
 * SEO settings screen.
 *
 * @package WPCustomSeo
 *
 * @var string $page Settings page slug used by the Settings API.
 */

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'SEO Settings', 'wp-custom-seo' ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice. ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'wp-custom-seo' ); ?></p>
		</div>
	<?php endif; ?>

	<?php settings_errors( Settings::OPTION ); ?>

	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
		<?php
		settings_fields( Settings::GROUP );
		do_settings_sections( $page );
		submit_button();
		?>
	</form>
</div>
