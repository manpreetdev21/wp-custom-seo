<?php
/**
 * Tools screen.
 *
 * @package WPCustomSeo
 *
 * @var string                           $action     Clear-cache action name.
 * @var string                           $export     Export action name.
 * @var string                           $import     Import action name.
 * @var string                           $migrate    Migration action name.
 * @var array<int, array<string, mixed>> $post_types Exposed post types with counts.
 * @var array<string, array<string, mixed>> $sources  Importable SEO plugins.
 * @var array<string, int>               $detected   Posts holding data per source.
 * @var bool                             $aioseo     Whether AIOSEO's own table exists.
 * @var string[]                         $columns    CSV header columns.
 * @var array<string, mixed>|null        $report     Report from the last action.
 * @var int|null                         $cleared    Entries removed by the last cache flush.
 * @var string                           $send       Send-report action name.
 * @var string[]                         $recipients Report recipients.
 * @var int|null                         $next       When the next report is due.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Tools', 'wp-custom-seo' ); ?></h1>

	<?php if ( null !== $cleared ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of cache entries removed. */
					esc_html( _n( 'Schema cache cleared. %d stored entry removed.', 'Schema cache cleared. %d stored entries removed.', $cleared, 'wp-custom-seo' ) ),
					(int) $cleared
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $report && ! empty( $report['error'] ) ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( (string) $report['error'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $report && 'import' === ( $report['kind'] ?? '' ) && empty( $report['error'] ) ) : ?>
		<div class="notice <?php echo empty( $report['dry'] ) ? 'notice-success' : 'notice-info'; ?>">
			<p>
				<strong>
					<?php
					echo empty( $report['dry'] )
						? esc_html__( 'Import applied.', 'wp-custom-seo' )
						: esc_html__( 'Preview only — nothing was changed.', 'wp-custom-seo' );
					?>
				</strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: rows read, 2: posts affected, 3: fields changed, 4: fields already matching. */
					esc_html__( '%1$d rows read. %2$d posts would change across %3$d fields; %4$d fields already matched.', 'wp-custom-seo' ),
					(int) $report['rows'],
					(int) $report['posts'],
					(int) $report['fields'],
					(int) $report['unchanged']
				);
				?>
			</p>
			<?php if ( ! empty( $report['problems'] ) ) : ?>
				<ul class="wpcseo-list">
					<?php foreach ( (array) $report['problems'] as $wpcseo_problem ) : ?>
						<li><?php echo esc_html( (string) $wpcseo_problem ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $report && 'migrate' === ( $report['kind'] ?? '' ) && empty( $report['error'] ) ) : ?>
		<div class="notice <?php echo empty( $report['done'] ) ? 'notice-info' : 'notice-success'; ?>">
			<p>
				<strong>
					<?php
					printf(
						/* translators: 1: source plugin name, 2: posts processed, 3: posts found. */
						esc_html__( '%1$s: %2$d of %3$d posts processed.', 'wp-custom-seo' ),
						esc_html( (string) $report['label'] ),
						(int) $report['processed'],
						(int) $report['total']
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: posts written to, 2: fields written, 3: fields left alone. */
					esc_html__( '%1$d posts updated, %2$d fields copied, %3$d fields left alone because this plugin already had a value.', 'wp-custom-seo' ),
					(int) $report['posts'],
					(int) $report['fields'],
					(int) $report['skipped']
				);
				?>
			</p>

			<?php if ( ! empty( $report['dropped'] ) ) : ?>
				<p>
					<?php esc_html_e( 'These template variables have no equivalent here and were removed from the text they appeared in:', 'wp-custom-seo' ); ?>
					<code><?php echo esc_html( implode( ', ', array_map( static fn ( $wpcseo_name ): string => '%%' . $wpcseo_name . '%%', (array) $report['dropped'] ) ) ); ?></code>
				</p>
			<?php endif; ?>

			<?php if ( empty( $report['done'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $migrate ); ?>">
					<input type="hidden" name="wpcseo_source" value="<?php echo esc_attr( (string) $report['source'] ); ?>">
					<input type="hidden" name="wpcseo_offset" value="<?php echo esc_attr( (string) (int) $report['processed'] ); ?>">
					<input type="hidden" name="wpcseo_overwrite" value="<?php echo empty( $report['overwrite'] ) ? '0' : '1'; ?>">
					<input type="hidden" name="wpcseo_posts" value="<?php echo esc_attr( (string) (int) $report['posts'] ); ?>">
					<input type="hidden" name="wpcseo_fields" value="<?php echo esc_attr( (string) (int) $report['fields'] ); ?>">
					<input type="hidden" name="wpcseo_skipped" value="<?php echo esc_attr( (string) (int) $report['skipped'] ); ?>">
					<?php wp_nonce_field( $migrate ); ?>
					<?php submit_button( __( 'Continue importing', 'wp-custom-seo' ), 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Finished. The other plugin’s data has not been touched, so you can run this again or go back to it.', 'wp-custom-seo' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $report && 'email' === ( $report['kind'] ?? '' ) ) : ?>
		<div class="notice <?php echo empty( $report['sent'] ) ? 'notice-error' : 'notice-success'; ?>">
			<p>
				<?php if ( empty( $report['sent'] ) ) : ?>
					<?php esc_html_e( 'WordPress could not send the report. That is a mail configuration problem on this site rather than a problem with the report — check whatever handles outgoing email here.', 'wp-custom-seo' ); ?>
				<?php else : ?>
					<?php
					printf(
						/* translators: %s: comma separated email addresses. */
						esc_html__( 'Report sent to %s. WordPress accepted it for delivery; whether it arrives depends on how this site sends mail.', 'wp-custom-seo' ),
						esc_html( (string) $report['to'] )
					);
					?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="wpcseo-grid">
		<section class="wpcseo-card" aria-labelledby="wpcseo-card-report">
			<h2 id="wpcseo-card-report"><?php esc_html_e( 'Email report', 'wp-custom-seo' ); ?></h2>
			<p>
				<?php esc_html_e( 'A summary of what the site audit found, and what Google reported if Search Console is connected. Sending one now ignores the usual rule that a report with nothing in it is not sent, so you can see what arrives.', 'wp-custom-seo' ); ?>
			</p>

			<p>
				<?php
				printf(
					/* translators: %s: comma separated email addresses. */
					esc_html__( 'Recipients: %s', 'wp-custom-seo' ),
					esc_html( implode( ', ', $recipients ) )
				);
				?>
			</p>

			<p>
				<?php if ( null === $next ) : ?>
					<?php esc_html_e( 'Scheduled reports are switched off. Turn them on under Settings → Reports.', 'wp-custom-seo' ); ?>
				<?php else : ?>
					<?php
					printf(
						/* translators: %s: human readable time difference. */
						esc_html__( 'Next scheduled report in %s.', 'wp-custom-seo' ),
						esc_html( human_time_diff( $next ) )
					);
					?>
				<?php endif; ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $send ); ?>">
				<?php wp_nonce_field( $send ); ?>
				<?php submit_button( __( 'Send one now', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
			</form>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-migrate">
			<h2 id="wpcseo-card-migrate"><?php esc_html_e( 'Import from another SEO plugin', 'wp-custom-seo' ); ?></h2>
			<p>
				<?php esc_html_e( 'Copies titles, descriptions, keyphrases, canonicals, robots settings and social fields into this plugin. The other plugin’s data is read, never changed or deleted — if the result is not what you wanted, it is all still there.', 'wp-custom-seo' ); ?>
			</p>

			<?php $wpcseo_found = array_filter( $detected ); ?>

			<?php if ( ! $wpcseo_found ) : ?>
				<p><?php esc_html_e( 'No data from a supported SEO plugin was found on this site.', 'wp-custom-seo' ); ?></p>
				<p class="description">
					<?php
					printf(
						/* translators: %s: comma separated plugin names. */
						esc_html__( 'Looked for: %s. The plugin does not need to be active — its data stays in the database after it is switched off.', 'wp-custom-seo' ),
						esc_html( implode( ', ', wp_list_pluck( $sources, 'label' ) ) )
					);
					?>
				</p>
			<?php else : ?>
				<?php foreach ( $wpcseo_found as $wpcseo_slug => $wpcseo_count ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpcseo-field">
						<input type="hidden" name="action" value="<?php echo esc_attr( $migrate ); ?>">
						<input type="hidden" name="wpcseo_source" value="<?php echo esc_attr( (string) $wpcseo_slug ); ?>">
						<?php wp_nonce_field( $migrate ); ?>

						<p>
							<strong><?php echo esc_html( (string) $sources[ $wpcseo_slug ]['label'] ); ?></strong> —
							<?php
							printf(
								/* translators: %d: number of posts. */
								esc_html( _n( '%d post has data here', '%d posts have data here', (int) $wpcseo_count, 'wp-custom-seo' ) ),
								(int) $wpcseo_count
							);
							?>
						</p>

						<p>
							<label>
								<input type="checkbox" name="wpcseo_overwrite" value="1">
								<?php esc_html_e( 'Replace values this plugin already holds', 'wp-custom-seo' ); ?>
							</label>
						</p>

						<?php submit_button( __( 'Import', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endforeach; ?>

				<p class="description">
					<?php
					printf(
						/* translators: %d: batch size. */
						esc_html__( 'Runs %d posts at a time so it cannot time out. You will be asked to continue until it finishes.', 'wp-custom-seo' ),
						(int) \WPCustomSeo\Transfer\Import::BATCH
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( $aioseo ) : ?>
				<p class="description">
					<?php esc_html_e( 'All in One SEO was found, but version 4 keeps its data in a table of its own rather than in post meta, and this plugin cannot read it. Nothing from it will be imported — that is a limitation stated plainly rather than a partial import reported as a success.', 'wp-custom-seo' ); ?>
				</p>
			<?php endif; ?>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-csv">
			<h2 id="wpcseo-card-csv"><?php esc_html_e( 'Export and import a spreadsheet', 'wp-custom-seo' ); ?></h2>
			<p>
				<?php esc_html_e( 'Export every post’s SEO fields, edit them in a spreadsheet, and put the file back. What comes out is exactly what goes in, so a row you do not touch is a field that does not change.', 'wp-custom-seo' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $export ); ?>">
				<?php wp_nonce_field( $export ); ?>
				<?php submit_button( __( 'Export CSV', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="<?php echo esc_attr( $import ); ?>">
				<?php wp_nonce_field( $import ); ?>

				<p>
					<label for="wpcseo-csv"><?php esc_html_e( 'CSV file', 'wp-custom-seo' ); ?></label><br>
					<input type="file" id="wpcseo-csv" name="wpcseo_csv" accept=".csv,text/csv" required>
				</p>

				<p>
					<label>
						<input type="checkbox" name="wpcseo_apply" value="1">
						<?php esc_html_e( 'Apply the changes. Leave this unticked to see what would happen first.', 'wp-custom-seo' ); ?>
					</label>
				</p>

				<?php submit_button( __( 'Upload CSV', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
			</form>

			<p class="description">
				<?php esc_html_e( 'Rows are matched on post_id. The post_type, post_title and url columns are for reading and are ignored on import. An emptied cell clears that field; a column you delete from the file is left alone entirely.', 'wp-custom-seo' ); ?>
			</p>
			<p class="description">
				<code><?php echo esc_html( implode( ', ', $columns ) ); ?></code>
			</p>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-cache">
			<h2 id="wpcseo-card-cache"><?php esc_html_e( 'Schema cache', 'wp-custom-seo' ); ?></h2>
			<p>
				<?php esc_html_e( 'Aggregated schema is cached for twelve hours and refreshes on its own whenever content changes. Clear it manually after changing something the cache cannot see, such as a theme or another plugin that filters the graph.', 'wp-custom-seo' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<?php wp_nonce_field( $action ); ?>
				<?php submit_button( __( 'Clear schema cache', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
			</form>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-api">
			<h2 id="wpcseo-card-api"><?php esc_html_e( 'Schema API', 'wp-custom-seo' ); ?></h2>
			<?php if ( ! $post_types ) : ?>
				<p><?php esc_html_e( 'No public post types are exposed.', 'wp-custom-seo' ); ?></p>
			<?php else : ?>
				<dl class="wpcseo-list">
					<?php foreach ( $post_types as $wpcseo_type ) : ?>
						<dt><?php echo esc_html( (string) $wpcseo_type['label'] ); ?></dt>
						<dd>
							<?php
							printf(
								/* translators: 1: number of items, 2: number of API pages. */
								esc_html__( '%1$d items across %2$d page(s)', 'wp-custom-seo' ),
								(int) $wpcseo_type['total'],
								(int) $wpcseo_type['pages']
							);
							?>
							<br>
							<a href="<?php echo esc_url( (string) $wpcseo_type['href'] ); ?>"><code><?php echo esc_html( (string) $wpcseo_type['href'] ); ?></code></a>
						</dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</section>
	</div>
</div>
