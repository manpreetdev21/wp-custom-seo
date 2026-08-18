<?php
/**
 * AI SEO / GEO screen.
 *
 * @package WPCustomSeo
 *
 * @var array<int, array<string, mixed>> $rows      Scored posts.
 * @var array<string, string>            $labels    Dimension labels keyed by id.
 * @var array<string, int>               $averages  Average score per dimension.
 * @var int                              $overall   Average overall score.
 * @var int                              $scanned   How many posts were scored.
 * @var array<string, object>            $providers Registered visibility providers.
 * @var array<string, object>            $ready     Providers that are configured.
 * @var string                           $crawlers  URL of the settings screen.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'AI SEO / GEO', 'wp-custom-seo' ); ?></h1>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'How usable your pages are as a source for an AI-generated answer. An assistant has to find a claim, see what it is about, and decide whether to trust it — this measures how easy your pages make those three things.', 'wp-custom-seo' ); ?>
	</p>

	<div class="notice notice-info inline">
		<p>
			<strong><?php esc_html_e( 'This score is this plugin’s own.', 'wp-custom-seo' ); ?></strong>
			<?php esc_html_e( 'It is not a Google ranking score and is not derived from one. No search engine or AI company publishes a metric like it, and no one outside this plugin has seen this number. Every dimension below says exactly what it counted, so you can disagree with it — a page can score badly here and be excellent, and a page can score well and say nothing new.', 'wp-custom-seo' ); ?>
		</p>
	</div>

	<?php if ( ! $rows ) : ?>
		<?php
		$wpcseo_empty = \WPCustomSeo\Admin\UI::empty_state(
			'sparkle',
			__( 'No published content to score yet', 'wp-custom-seo' ),
			__( 'Readiness is measured from what a page actually contains, so there is nothing to read until something is published.', 'wp-custom-seo' ),
			\WPCustomSeo\Admin\UI::button( __( 'Write a post', 'wp-custom-seo' ), admin_url( 'post-new.php' ), 'primary', 'document' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::empty_state() escapes every argument it is given and returns finished markup.
		echo $wpcseo_empty;
		?>
	<?php else : ?>
		<div class="wpcseo-grid">
			<section class="wpcseo-card" aria-labelledby="wpcseo-card-readiness">
				<h2 id="wpcseo-card-readiness"><?php esc_html_e( 'AI answer readiness', 'wp-custom-seo' ); ?></h2>
				<p class="wpcseo-score"><?php echo esc_html( sprintf( '%d/100', $overall ) ); ?></p>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of posts scored. */
						esc_html( _n( 'Averaged across the %d most recently updated published page.', 'Averaged across the %d most recently updated published pages.', $scanned, 'wp-custom-seo' ) ),
						esc_html( number_format_i18n( $scanned ) )
					);
					?>
				</p>

				<dl class="wpcseo-list">
					<?php foreach ( $labels as $wpcseo_id => $wpcseo_label ) : ?>
						<dt><?php echo esc_html( $wpcseo_label ); ?></dt>
						<dd><?php echo esc_html( sprintf( '%d%%', (int) ( $averages[ $wpcseo_id ] ?? 0 ) ) ); ?></dd>
					<?php endforeach; ?>
				</dl>
			</section>

			<section class="wpcseo-card" aria-labelledby="wpcseo-card-visibility">
				<h2 id="wpcseo-card-visibility"><?php esc_html_e( 'AI search visibility', 'wp-custom-seo' ); ?></h2>

				<?php if ( ! $providers ) : ?>
					<p>
						<?php esc_html_e( 'No visibility provider is connected, and none ships with this plugin.', 'wp-custom-seo' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'There is no public API from OpenAI, Anthropic, Google or Perplexity that reports how often a domain was cited, the way Search Console reports impressions. The only sources today are commercial tracking services with their own APIs, or scraping an assistant’s interface — which is against their terms of service, so it is not shipped here.', 'wp-custom-seo' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'If you subscribe to a service that does report this, a provider can be registered against the WPCustomSeo\GEO\VisibilityProvider interface and its data appears on this screen.', 'wp-custom-seo' ); ?>
					</p>
				<?php else : ?>
					<dl class="wpcseo-list">
						<?php foreach ( $providers as $wpcseo_provider ) : ?>
							<dt><?php echo esc_html( $wpcseo_provider->label() ); ?></dt>
							<dd>
								<?php if ( $wpcseo_provider->is_ready() ) : ?>
									<span class="wpcseo-badge is-on"><?php esc_html_e( 'Connected', 'wp-custom-seo' ); ?></span>
								<?php else : ?>
									<span class="wpcseo-badge is-off"><?php esc_html_e( 'Not configured', 'wp-custom-seo' ); ?></span>
								<?php endif; ?>
							</dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>

				<p>
					<a href="<?php echo esc_url( $crawlers ); ?>"><?php esc_html_e( 'Check which AI crawlers can reach this site', 'wp-custom-seo' ); ?></a>
					<br>
					<span class="description"><?php esc_html_e( 'Nothing on this screen matters if an assistant’s crawler is blocked from reading the site.', 'wp-custom-seo' ); ?></span>
				</p>
			</section>
		</div>

		<h2><?php esc_html_e( 'Recently updated pages', 'wp-custom-seo' ); ?></h2>

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Page', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Readiness', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Weakest dimension', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'First thing to fix', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $wpcseo_row ) :
					$wpcseo_weakest = null;

					foreach ( $wpcseo_row['dimensions'] as $wpcseo_dimension ) {
						if ( null === $wpcseo_weakest || $wpcseo_dimension['score'] < $wpcseo_weakest['score'] ) {
							$wpcseo_weakest = $wpcseo_dimension;
						}
					}
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) $wpcseo_row['edit'] ); ?>">
								<?php echo esc_html( (string) $wpcseo_row['title'] ); ?>
							</a>
						</td>
						<td><?php echo esc_html( sprintf( '%d/100', (int) $wpcseo_row['score'] ) ); ?></td>
						<td>
							<?php if ( null !== $wpcseo_weakest ) : ?>
								<?php echo esc_html( sprintf( '%s — %d%%', (string) $wpcseo_weakest['label'], (int) $wpcseo_weakest['score'] ) ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( null !== $wpcseo_weakest && ! empty( $wpcseo_weakest['fixes'] ) ) : ?>
								<?php echo esc_html( (string) $wpcseo_weakest['fixes'][0] ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Nothing outstanding.', 'wp-custom-seo' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'What each dimension measures', 'wp-custom-seo' ); ?></h2>

		<?php
		$wpcseo_explained = $rows[0]['dimensions'] ?? array();
		?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Dimension', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Why it matters', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $wpcseo_explained as $wpcseo_dimension ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $wpcseo_dimension['label'] ); ?></th>
						<td><?php echo esc_html( (string) $wpcseo_dimension['why'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
