<?php
/**
 * SEO dashboard screen.
 *
 * @package WPCustomSeo
 *
 * @var string              $version    Plugin version.
 * @var string              $db_version Applied schema version.
 * @var array<string, bool> $modules    Module label => enabled.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'SEO Dashboard', 'wp-custom-seo' ); ?></h1>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'Foundation installed. SEO analysis, schema, sitemaps and the AI assistant arrive in later releases.', 'wp-custom-seo' ); ?>
	</p>

	<div class="wpcseo-grid">
		<section class="wpcseo-card" aria-labelledby="wpcseo-card-status">
			<h2 id="wpcseo-card-status"><?php esc_html_e( 'Status', 'wp-custom-seo' ); ?></h2>
			<dl class="wpcseo-list">
				<dt><?php esc_html_e( 'Plugin version', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( $version ); ?></dd>

				<dt><?php esc_html_e( 'Schema version', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( $db_version ); ?></dd>

				<dt><?php esc_html_e( 'WordPress', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( get_bloginfo( 'version' ) ); ?></dd>

				<dt><?php esc_html_e( 'PHP', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( PHP_VERSION ); ?></dd>
			</dl>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-modules">
			<h2 id="wpcseo-card-modules"><?php esc_html_e( 'Modules', 'wp-custom-seo' ); ?></h2>
			<ul class="wpcseo-modules">
				<?php foreach ( $modules as $label => $enabled ) : ?>
					<li>
						<span class="wpcseo-badge <?php echo $enabled ? 'is-on' : 'is-off'; ?>">
							<?php echo $enabled ? esc_html__( 'On', 'wp-custom-seo' ) : esc_html__( 'Off', 'wp-custom-seo' ); ?>
						</span>
						<?php echo esc_html( $label ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-custom-seo-settings' ) ); ?>">
					<?php esc_html_e( 'Configure settings', 'wp-custom-seo' ); ?>
				</a>
			</p>
		</section>
	</div>
</div>
