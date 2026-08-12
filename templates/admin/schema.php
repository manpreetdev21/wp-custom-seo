<?php
/**
 * Schema validator screen.
 *
 * @package WPCustomSeo
 *
 * @var int                                                              $post_id   Selected post id, or 0 for the front page.
 * @var \WPCustomSeo\Schema\Graph\Graph                                   $graph     Generated graph.
 * @var array<int, array{level: string, node: string, message: string}>   $issues    Validation issues.
 * @var array<int, array{name: string, note: string}>                     $conflicts Other structured-data sources.
 * @var WP_Post[]                                                        $posts     Recent posts for the selector.
 */

use WPCustomSeo\Admin\SchemaPage;
use WPCustomSeo\Schema\Validator;

defined( 'ABSPATH' ) || exit;

$wpcseo_levels = array(
	Validator::ERROR   => __( 'Error', 'wp-custom-seo' ),
	Validator::WARNING => __( 'Warning', 'wp-custom-seo' ),
	Validator::NOTICE  => __( 'Notice', 'wp-custom-seo' ),
);

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Schema', 'wp-custom-seo' ); ?></h1>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'The plugin builds one connected graph per page rather than separate JSON-LD blocks, so entities are stated once and referenced everywhere else. A graph that fails validation is withheld rather than published.', 'wp-custom-seo' ); ?>
	</p>

	<form method="get" class="wpcseo-schema-picker">
		<input type="hidden" name="page" value="<?php echo esc_attr( SchemaPage::SLUG ); ?>">
		<label for="wpcseo_post"><?php esc_html_e( 'Validate', 'wp-custom-seo' ); ?></label>
		<select id="wpcseo_post" name="wpcseo_post">
			<option value="0"><?php esc_html_e( 'Site front page', 'wp-custom-seo' ); ?></option>
			<?php foreach ( $posts as $wpcseo_post ) : ?>
				<option value="<?php echo esc_attr( (string) $wpcseo_post->ID ); ?>" <?php selected( $post_id, $wpcseo_post->ID ); ?>>
					<?php echo esc_html( get_the_title( $wpcseo_post ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Validate', 'wp-custom-seo' ), 'secondary', '', false ); ?>
	</form>

	<div class="wpcseo-grid">
		<section class="wpcseo-card" aria-labelledby="wpcseo-card-validation">
			<h2 id="wpcseo-card-validation"><?php esc_html_e( 'Validation', 'wp-custom-seo' ); ?></h2>

			<?php if ( ! $issues ) : ?>
				<p>
					<span class="wpcseo-badge is-on"><?php esc_html_e( 'Valid', 'wp-custom-seo' ); ?></span>
					<?php
					printf(
						/* translators: %d: number of nodes. */
						esc_html__( '%d nodes, all identifiers unique and all references resolved.', 'wp-custom-seo' ),
						count( $graph->nodes() )
					);
					?>
				</p>
			<?php else : ?>
				<ul class="wpcseo-issues">
					<?php foreach ( $issues as $wpcseo_issue ) : ?>
						<li class="wpcseo-issue is-<?php echo esc_attr( $wpcseo_issue['level'] ); ?>">
							<span class="wpcseo-check-badge">
								<?php echo esc_html( $wpcseo_levels[ $wpcseo_issue['level'] ] ?? $wpcseo_issue['level'] ); ?>
							</span>
							<p><?php echo esc_html( $wpcseo_issue['message'] ); ?></p>
							<?php if ( '' !== $wpcseo_issue['node'] ) : ?>
								<code><?php echo esc_html( $wpcseo_issue['node'] ); ?></code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( Validator::has_errors( $issues ) ) : ?>
					<p><strong><?php esc_html_e( 'This graph contains errors and will not be output on the front end.', 'wp-custom-seo' ); ?></strong></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-sources">
			<h2 id="wpcseo-card-sources"><?php esc_html_e( 'Detected schema sources', 'wp-custom-seo' ); ?></h2>

			<?php if ( ! $conflicts ) : ?>
				<p><?php esc_html_e( 'No other structured-data plugin was detected.', 'wp-custom-seo' ); ?></p>
			<?php else : ?>
				<ul class="wpcseo-modules">
					<?php foreach ( $conflicts as $wpcseo_source ) : ?>
						<li>
							<strong><?php echo esc_html( $wpcseo_source['name'] ); ?></strong><br>
							<span class="description"><?php echo esc_html( $wpcseo_source['note'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="description">
					<?php esc_html_e( 'Two plugins describing the same page can produce contradictory structured data. Nothing is disabled automatically — turn off schema output here, or in the other plugin, whichever you prefer.', 'wp-custom-seo' ); ?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-custom-seo-settings' ) ); ?>">
						<?php esc_html_e( 'Schema settings', 'wp-custom-seo' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</section>
	</div>

	<h2><?php esc_html_e( 'Generated graph', 'wp-custom-seo' ); ?></h2>
	<pre class="wpcseo-json"><?php echo esc_html( wp_json_encode( $graph->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
</div>
