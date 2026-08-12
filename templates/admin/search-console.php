<?php
/**
 * Search Console screen.
 *
 * @package WPCustomSeo
 *
 * @var bool                             $connected  Whether a key file is stored.
 * @var string                           $email      Service account address.
 * @var array<int, array<string, mixed>>|\WP_Error $sites Readable properties.
 * @var string                           $property   Chosen property.
 * @var int                              $days       Period length.
 * @var array<string, mixed>|\WP_Error|null $report   The report, or null.
 * @var string                            $error     Report error message.
 * @var string                            $notice    Action error message.
 * @var bool                              $saved     Whether something was just saved.
 * @var bool                              $gone      Whether the account was just removed.
 * @var string                            $connect   Connect action name.
 * @var string                            $disconnect Disconnect action name.
 * @var string                            $choose    Property action name.
 * @var string                            $slug      Page slug.
 * @var string                            $ga4        Analytics property id.
 * @var string                            $ga4_action Analytics action name.
 * @var array<int, array<string, mixed>>|\WP_Error|null $engagement Landing pages.
 * @var array<string, mixed>|\WP_Error|null            $ga4_totals Organic totals.
 */

defined( 'ABSPATH' ) || exit;

$wpcseo_site_error = $sites instanceof WP_Error ? $sites->get_error_message() : '';
$wpcseo_sites      = $sites instanceof WP_Error ? array() : (array) $sites;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Search Performance', 'wp-custom-seo' ); ?></h1>

	<?php if ( '' !== $notice ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'wp-custom-seo' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $gone ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The service account key has been removed from this site.', 'wp-custom-seo' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $connected ) : ?>
		<div class="wpcseo-card">
			<h2><?php esc_html_e( 'Connect Search Console', 'wp-custom-seo' ); ?></h2>
			<p>
				<?php esc_html_e( 'This screen shows what Google reports about your site in search: the queries it appeared for, the pages that were shown, and how often people clicked. None of it is estimated by this plugin — without a connection there is nothing to show, and nothing is shown.', 'wp-custom-seo' ); ?>
			</p>

			<h3><?php esc_html_e( 'What you need to do', 'wp-custom-seo' ); ?></h3>
			<ol class="wpcseo-list">
				<li><?php esc_html_e( 'In the Google Cloud console, create a project and enable the Google Search Console API for it.', 'wp-custom-seo' ); ?></li>
				<li><?php esc_html_e( 'Create a service account in that project and download a JSON key for it.', 'wp-custom-seo' ); ?></li>
				<li><?php esc_html_e( 'Paste the whole key file below.', 'wp-custom-seo' ); ?></li>
				<li><?php esc_html_e( 'In Search Console, add the service account’s email address as a user of your property. This plugin will show you the address once the key is saved.', 'wp-custom-seo' ); ?></li>
			</ol>

			<p class="description">
				<?php esc_html_e( 'A service account is used rather than signing in with Google, which means one extra step in Search Console but no redirect to register and no long-lived login token stored on this site. The key file is encrypted at rest, which protects it if the database leaks — not if the server itself is compromised, since the encryption key lives there too.', 'wp-custom-seo' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $connect ); ?>">
				<?php wp_nonce_field( $connect ); ?>

				<p>
					<label for="wpcseo-key"><?php esc_html_e( 'Service account JSON key', 'wp-custom-seo' ); ?></label><br>
					<textarea id="wpcseo-key" name="wpcseo_key" rows="8" class="large-text code" required placeholder="{&quot;type&quot;: &quot;service_account&quot;, …}"></textarea>
				</p>

				<?php submit_button( __( 'Save key', 'wp-custom-seo' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
	<?php else : ?>
		<div class="wpcseo-card">
			<h2><?php esc_html_e( 'Connection', 'wp-custom-seo' ); ?></h2>
			<p>
				<?php esc_html_e( 'Reading as:', 'wp-custom-seo' ); ?>
				<code><?php echo esc_html( $email ); ?></code>
			</p>

			<?php if ( '' !== $wpcseo_site_error ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $wpcseo_site_error ); ?></p></div>
			<?php elseif ( ! $wpcseo_sites ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e( 'The key works, but this service account cannot read any property yet. In Search Console, open Settings → Users and permissions and add the address above.', 'wp-custom-seo' ); ?>
					</p>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $choose ); ?>">
					<?php wp_nonce_field( $choose ); ?>

					<p>
						<label for="wpcseo-property"><?php esc_html_e( 'Property', 'wp-custom-seo' ); ?></label><br>
						<select id="wpcseo-property" name="wpcseo_property">
							<option value=""><?php esc_html_e( '— none —', 'wp-custom-seo' ); ?></option>
							<?php foreach ( $wpcseo_sites as $wpcseo_site ) : ?>
								<option value="<?php echo esc_attr( (string) $wpcseo_site['url'] ); ?>" <?php selected( $property, (string) $wpcseo_site['url'] ); ?>>
									<?php echo esc_html( (string) $wpcseo_site['url'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<?php submit_button( __( 'Use this property', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $disconnect ); ?>">
				<?php wp_nonce_field( $disconnect ); ?>
				<?php submit_button( __( 'Remove the key', 'wp-custom-seo' ), 'link-delete', 'submit', false ); ?>
			</form>
		</div>

		<div class="wpcseo-card">
			<h2><?php esc_html_e( 'Analytics', 'wp-custom-seo' ); ?></h2>
			<p>
				<?php esc_html_e( 'Search Console says how people found the site. Analytics says what they did next. The same service account covers both — add its address as a Viewer on the Analytics property and enable the Analytics Data API for its project.', 'wp-custom-seo' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $ga4_action ); ?>">
				<?php wp_nonce_field( $ga4_action ); ?>

				<p>
					<label for="wpcseo-ga4"><?php esc_html_e( 'Property id', 'wp-custom-seo' ); ?></label><br>
					<input type="text" id="wpcseo-ga4" name="wpcseo_ga4" value="<?php echo esc_attr( $ga4 ); ?>" class="regular-text" inputmode="numeric" placeholder="123456789">
				</p>

				<p class="description">
					<?php esc_html_e( 'The number under Admin → Property details, not the measurement id beginning with G-. Leave empty to switch Analytics off.', 'wp-custom-seo' ); ?>
				</p>

				<?php submit_button( __( 'Save property', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<?php if ( null !== $ga4_totals ) : ?>
			<div class="wpcseo-card">
				<h2><?php esc_html_e( 'What visitors from search did next', 'wp-custom-seo' ); ?></h2>

				<?php if ( $ga4_totals instanceof WP_Error ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $ga4_totals->get_error_message() ); ?></p></div>
				<?php elseif ( 0 === (int) $ga4_totals['sessions'] ) : ?>
					<p><?php esc_html_e( 'Analytics reports no organic search sessions for this period.', 'wp-custom-seo' ); ?></p>
				<?php else : ?>
					<dl class="wpcseo-list">
						<dt><?php esc_html_e( 'Organic sessions', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( (int) $ga4_totals['sessions'] ) ); ?></dd>
						<dt><?php esc_html_e( 'Engaged sessions', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( (int) $ga4_totals['engaged'] ) ); ?></dd>
						<dt><?php esc_html_e( 'Engagement rate', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( number_format_i18n( (float) $ga4_totals['engagement'] * 100, 1 ) . '%' ); ?></dd>
					</dl>

					<?php if ( is_array( $engagement ) && $engagement ) : ?>
						<table class="wp-list-table widefat striped">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Landing page', 'wp-custom-seo' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Sessions', 'wp-custom-seo' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Engaged', 'wp-custom-seo' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Engagement rate', 'wp-custom-seo' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $engagement as $wpcseo_row ) : ?>
									<tr>
										<td><?php echo esc_html( (string) $wpcseo_row['page'] ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $wpcseo_row['sessions'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (int) $wpcseo_row['engaged'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (float) $wpcseo_row['engagement'] * 100, 1 ) . '%' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<p class="description">
						<?php esc_html_e( 'Organic search traffic only — sessions from newsletters or ads say nothing about how the site performs in search. Reported by Google Analytics, not estimated here.', 'wp-custom-seo' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $property ) : ?>
			<p class="wpcseo-field">
				<?php foreach ( \WPCustomSeo\SearchConsole\Performance::PERIODS as $wpcseo_period ) : ?>
					<a
						class="button <?php echo $wpcseo_period === $days ? 'button-primary' : ''; ?>"
						href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page' => $slug,
								'days' => $wpcseo_period,
							),
							admin_url( 'admin.php' )
						)
					);
					?>
					"
					>
						<?php
						printf(
							/* translators: %d: number of days. */
							esc_html( _n( 'Last %d day', 'Last %d days', $wpcseo_period, 'wp-custom-seo' ) ),
							(int) $wpcseo_period
						);
						?>
					</a>
				<?php endforeach; ?>
			</p>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php elseif ( is_array( $report ) ) : ?>
				<div class="wpcseo-card">
					<h2><?php esc_html_e( 'Totals', 'wp-custom-seo' ); ?></h2>
					<p class="description">
						<?php
						printf(
							/* translators: 1: start date, 2: end date. */
							esc_html__( '%1$s to %2$s. Search Console data lags live traffic by two to three days, so the range ends before today rather than showing a drop that is not there.', 'wp-custom-seo' ),
							esc_html( (string) $report['range']['start'] ),
							esc_html( (string) $report['range']['end'] )
						);
						?>
					</p>

					<?php if ( 0 === (int) $report['totals']['impressions'] ) : ?>
						<p><?php esc_html_e( 'Google reports no impressions for this property in this period.', 'wp-custom-seo' ); ?></p>
					<?php else : ?>
						<dl class="wpcseo-list">
							<dt><?php esc_html_e( 'Clicks', 'wp-custom-seo' ); ?></dt>
							<dd><?php echo esc_html( number_format_i18n( (int) $report['totals']['clicks'] ) ); ?></dd>
							<dt><?php esc_html_e( 'Impressions', 'wp-custom-seo' ); ?></dt>
							<dd><?php echo esc_html( number_format_i18n( (int) $report['totals']['impressions'] ) ); ?></dd>
							<dt><?php esc_html_e( 'Click-through rate', 'wp-custom-seo' ); ?></dt>
							<dd><?php echo esc_html( number_format_i18n( (float) $report['totals']['ctr'] * 100, 1 ) . '%' ); ?></dd>
						</dl>
						<p class="description">
							<?php esc_html_e( 'No average position is given for the site as a whole: averaging the average positions of individual pages does not produce one, and a number that looks like a measurement should be one.', 'wp-custom-seo' ); ?>
						</p>
					<?php endif; ?>
				</div>

				<?php
				foreach ( array(
					'queries' => __( 'Top queries', 'wp-custom-seo' ),
					'pages'   => __( 'Top pages', 'wp-custom-seo' ),
				) as $wpcseo_key => $wpcseo_heading ) :
					?>
					<div class="wpcseo-card">
						<h2><?php echo esc_html( $wpcseo_heading ); ?></h2>

						<?php if ( ! $report[ $wpcseo_key ] ) : ?>
							<p><?php esc_html_e( 'Nothing reported for this period.', 'wp-custom-seo' ); ?></p>
						<?php else : ?>
							<table class="wp-list-table widefat striped">
								<thead>
									<tr>
										<th scope="col"><?php echo 'queries' === $wpcseo_key ? esc_html__( 'Query', 'wp-custom-seo' ) : esc_html__( 'Page', 'wp-custom-seo' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Clicks', 'wp-custom-seo' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Impressions', 'wp-custom-seo' ); ?></th>
										<th scope="col"><?php esc_html_e( 'CTR', 'wp-custom-seo' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Average position', 'wp-custom-seo' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( (array) $report[ $wpcseo_key ] as $wpcseo_row ) : ?>
										<tr>
											<td><?php echo esc_html( (string) $wpcseo_row['key'] ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (int) $wpcseo_row['clicks'] ) ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (int) $wpcseo_row['impressions'] ) ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (float) $wpcseo_row['ctr'] * 100, 1 ) . '%' ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (float) $wpcseo_row['position'], 1 ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<p class="description">
					<?php esc_html_e( 'Every figure on this screen is reported by Google. This plugin adds nothing to it, and where Google reports nothing, nothing is shown. Results are cached for twelve hours; the underlying data updates about once a day.', 'wp-custom-seo' ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
