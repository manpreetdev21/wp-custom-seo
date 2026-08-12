<?php
/**
 * AI screen.
 *
 * @package WPCustomSeo
 *
 * @var string                                          $save_action  Admin-post action for saving keys.
 * @var string                                          $clear_action Admin-post action for clearing the log.
 * @var array<string, \WPCustomSeo\AI\ProviderInterface> $providers   Registered providers.
 * @var \WPCustomSeo\AI\ProviderInterface|null           $active      Selected provider.
 * @var string                                          $model        Effective model id.
 * @var bool                                            $ready        Whether generation is possible.
 * @var bool                                            $encrypted    Whether keys can be encrypted.
 * @var array{total:int,ok:int,failed:int,input:int,output:int} $totals Aggregate counters.
 * @var object[]                                        $recent       Recent log entries.
 * @var bool                                            $saved        Whether keys were just saved.
 * @var int|null                                        $cleared      Entries removed, or null.
 * @var array{type:string,message:string}|null          $notice       Notice to display.
 */

use WPCustomSeo\AI\Credentials;

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'AI', 'wp-custom-seo' ); ?></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'API keys saved.', 'wp-custom-seo' ); ?></p></div>
	<?php endif; ?>

	<?php if ( null !== $cleared ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of entries removed. */
					esc_html( _n( '%d usage entry removed.', '%d usage entries removed.', (int) $cleared, 'wp-custom-seo' ) ),
					(int) $cleared
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $encrypted ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'This server cannot encrypt stored credentials (the sodium extension or AUTH_SALT is unavailable). Keys would be stored in plain text — fix the server configuration before entering one.', 'wp-custom-seo' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'AI features are off until you choose a provider and save a key. Nothing is ever sent automatically: no request is made on save, on page load, or in the background. Content leaves this site only when someone presses a generate button in the editor.', 'wp-custom-seo' ); ?>
	</p>

	<div class="wpcseo-grid">
		<section class="wpcseo-card" aria-labelledby="wpcseo-card-status">
			<h2 id="wpcseo-card-status"><?php esc_html_e( 'Status', 'wp-custom-seo' ); ?></h2>
			<dl class="wpcseo-list">
				<dt><?php esc_html_e( 'Provider', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( null !== $active ? $active->label() : __( 'None selected', 'wp-custom-seo' ) ); ?></dd>

				<dt><?php esc_html_e( 'Model', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo '' !== $model ? esc_html( $model ) : '<span aria-hidden="true">—</span>'; ?></dd>

				<dt><?php esc_html_e( 'Ready', 'wp-custom-seo' ); ?></dt>
				<dd>
					<span class="wpcseo-badge <?php echo $ready ? 'is-on' : 'is-off'; ?>">
						<?php echo $ready ? esc_html__( 'Yes', 'wp-custom-seo' ) : esc_html__( 'No', 'wp-custom-seo' ); ?>
					</span>
				</dd>
			</dl>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-custom-seo-settings' ) ); ?>">
					<?php esc_html_e( 'Choose provider and model', 'wp-custom-seo' ); ?>
				</a>
			</p>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-usage">
			<h2 id="wpcseo-card-usage"><?php esc_html_e( 'Usage', 'wp-custom-seo' ); ?></h2>
			<dl class="wpcseo-list">
				<dt><?php esc_html_e( 'Requests', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $totals['total'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Successful', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $totals['ok'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Failed', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $totals['failed'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Input tokens', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $totals['input'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Output tokens', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $totals['output'] ) ); ?></dd>
			</dl>
			<p class="description">
				<?php esc_html_e( 'No cost estimate is shown. Provider pricing changes and varies by account, so an invented figure would be misleading — price these token counts against your own bill.', 'wp-custom-seo' ); ?>
			</p>
		</section>
	</div>

	<h2><?php esc_html_e( 'API keys', 'wp-custom-seo' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Keys are encrypted before storage and are never rendered back into this page, sent to the browser, or written to the log. A saved key shows only its last four characters.', 'wp-custom-seo' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<input type="hidden" name="action" value="<?php echo esc_attr( $save_action ); ?>">
		<?php wp_nonce_field( $save_action ); ?>

		<table class="form-table" role="presentation">
			<?php foreach ( $providers as $wpcseo_id => $wpcseo_provider ) : ?>
				<?php $wpcseo_hint = Credentials::hint( $wpcseo_id ); ?>
				<tr>
					<th>
						<label for="wpcseo_key_<?php echo esc_attr( $wpcseo_id ); ?>">
							<?php echo esc_html( $wpcseo_provider->label() ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="wpcseo_key_<?php echo esc_attr( $wpcseo_id ); ?>"
							name="wpcseo_key_<?php echo esc_attr( $wpcseo_id ); ?>"
							class="regular-text"
							autocomplete="new-password"
							spellcheck="false"
							value=""
							placeholder="<?php echo '' !== $wpcseo_hint ? esc_attr( $wpcseo_hint ) : esc_attr__( 'Not set', 'wp-custom-seo' ); ?>"
							aria-describedby="wpcseo_key_<?php echo esc_attr( $wpcseo_id ); ?>_help"
						>
						<p class="description" id="wpcseo_key_<?php echo esc_attr( $wpcseo_id ); ?>_help">
							<?php if ( '' !== $wpcseo_hint ) : ?>
								<?php esc_html_e( 'A key is saved. Leave empty to keep it.', 'wp-custom-seo' ); ?>
								<label style="margin-left:8px;">
									<input type="checkbox" name="wpcseo_clear_<?php echo esc_attr( $wpcseo_id ); ?>" value="1">
									<?php esc_html_e( 'Remove the saved key', 'wp-custom-seo' ); ?>
								</label>
							<?php else : ?>
								<a href="<?php echo esc_url( $wpcseo_provider->key_url() ); ?>" target="_blank" rel="noopener">
									<?php esc_html_e( 'Get a key', 'wp-custom-seo' ); ?>
								</a>
							<?php endif; ?>
						</p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Save keys', 'wp-custom-seo' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Recent requests', 'wp-custom-seo' ); ?></h2>

	<?php if ( ! $recent ) : ?>
		<p><?php esc_html_e( 'No AI requests have been made.', 'wp-custom-seo' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'When', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Provider', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Model', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tokens in / out', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Outcome', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent as $wpcseo_row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $wpcseo_row->created ) ); ?></td>
						<td><?php echo esc_html( (string) $wpcseo_row->action ); ?></td>
						<td><?php echo esc_html( (string) $wpcseo_row->provider ); ?></td>
						<td><code><?php echo esc_html( (string) $wpcseo_row->model ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( (int) $wpcseo_row->input_tokens ) . ' / ' . number_format_i18n( (int) $wpcseo_row->output_tokens ) ); ?></td>
						<td>
							<?php if ( 1 === (int) $wpcseo_row->success ) : ?>
								<span class="wpcseo-badge is-on"><?php esc_html_e( 'OK', 'wp-custom-seo' ); ?></span>
							<?php else : ?>
								<span class="wpcseo-badge is-off"><?php echo esc_html( (string) $wpcseo_row->error_code ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $clear_action ); ?>">
			<?php wp_nonce_field( $clear_action ); ?>
			<?php submit_button( __( 'Clear the usage log', 'wp-custom-seo' ), 'delete', 'submit', false ); ?>
		</form>
	<?php endif; ?>
</div>
