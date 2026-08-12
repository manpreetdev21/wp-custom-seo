<?php
/**
 * Redirects screen.
 *
 * @package WPCustomSeo
 *
 * @var \WPCustomSeo\Admin\RedirectsTable        $table   Prepared list table.
 * @var object|null                              $editing Rule being edited, or null.
 * @var string                                   $prefill Source to prefill when adding.
 * @var array{type: string, message: string}|null $notice Notice to display.
 * @var string                                   $nonce   Form nonce action.
 */

use WPCustomSeo\Admin\RedirectsPage;
use WPCustomSeo\Redirects\Redirects;

defined( 'ABSPATH' ) || exit;

$wpcseo_source  = null !== $editing ? (string) $editing->source : $prefill;
$wpcseo_target  = null !== $editing ? (string) $editing->target : '';
$wpcseo_type    = null !== $editing ? (int) $editing->type : 301;
$wpcseo_regex   = null !== $editing && (bool) $editing->is_regex;
$wpcseo_enabled = null === $editing || (bool) $editing->enabled;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Redirects', 'wp-custom-seo' ); ?></h1>

	<?php if ( null !== $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'Send visitors and search engines from an old URL to its replacement. Loops and long chains are rejected when you save, rather than discovered by a visitor.', 'wp-custom-seo' ); ?>
	</p>

	<section class="wpcseo-card">
		<h2><?php echo null !== $editing ? esc_html__( 'Edit redirect', 'wp-custom-seo' ) : esc_html__( 'Add redirect', 'wp-custom-seo' ); ?></h2>

		<form method="post" action="<?php echo esc_url( add_query_arg( 'page', RedirectsPage::SLUG, admin_url( 'admin.php' ) ) ); ?>">
			<?php wp_nonce_field( $nonce ); ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (string) ( null !== $editing ? (int) $editing->id : 0 ) ); ?>">

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="wpcseo_source"><?php esc_html_e( 'Redirect from', 'wp-custom-seo' ); ?></label></th>
					<td>
						<input type="text" id="wpcseo_source" name="source" class="regular-text code" required
							value="<?php echo esc_attr( $wpcseo_source ); ?>"
							placeholder="/old-page" aria-describedby="wpcseo_source_help">
						<p class="description" id="wpcseo_source_help">
							<?php esc_html_e( 'A path on this site. Query strings are ignored when matching and carried over to the destination.', 'wp-custom-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="wpcseo_target"><?php esc_html_e( 'Redirect to', 'wp-custom-seo' ); ?></label></th>
					<td>
						<input type="text" id="wpcseo_target" name="target" class="regular-text code" required
							value="<?php echo esc_attr( $wpcseo_target ); ?>"
							placeholder="/new-page" aria-describedby="wpcseo_target_help">
						<p class="description" id="wpcseo_target_help">
							<?php esc_html_e( 'A path on this site, or a full URL elsewhere.', 'wp-custom-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="wpcseo_type"><?php esc_html_e( 'Type', 'wp-custom-seo' ); ?></label></th>
					<td>
						<select id="wpcseo_type" name="type">
							<?php foreach ( Redirects::types() as $wpcseo_code => $wpcseo_label ) : ?>
								<option value="<?php echo esc_attr( (string) $wpcseo_code ); ?>" <?php selected( $wpcseo_type, $wpcseo_code ); ?>>
									<?php echo esc_html( $wpcseo_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Use 301 when the move is permanent. A 302 tells search engines to keep the old URL indexed.', 'wp-custom-seo' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Options', 'wp-custom-seo' ); ?></th>
					<td>
						<label for="wpcseo_is_regex">
							<input type="checkbox" id="wpcseo_is_regex" name="is_regex" value="1" <?php checked( $wpcseo_regex ); ?>>
							<?php esc_html_e( 'Treat the source as a regular expression', 'wp-custom-seo' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Capture groups can be used in the destination as $1, $2 and so on. Patterns are validated before saving.', 'wp-custom-seo' ); ?>
						</p>
						<label for="wpcseo_enabled">
							<input type="checkbox" id="wpcseo_enabled" name="enabled" value="1" <?php checked( $wpcseo_enabled ); ?>>
							<?php esc_html_e( 'Enabled', 'wp-custom-seo' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( null !== $editing ? __( 'Update redirect', 'wp-custom-seo' ) : __( 'Add redirect', 'wp-custom-seo' ), 'primary', 'wpcseo_redirect_submit', false ); ?>

			<?php if ( null !== $editing ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'page', RedirectsPage::SLUG, admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'Cancel', 'wp-custom-seo' ); ?>
				</a>
			<?php endif; ?>
		</form>
	</section>

	<form method="post">
		<?php
		wp_nonce_field( 'bulk-redirects' );
		$table->search_box( __( 'Search redirects', 'wp-custom-seo' ), 'wpcseo-redirect-search' );
		$table->display();
		?>
	</form>
</div>
