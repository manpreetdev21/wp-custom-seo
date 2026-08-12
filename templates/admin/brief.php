<?php
/**
 * Content brief screen.
 *
 * @package WPCustomSeo
 *
 * @var string                                  $nonce    Form nonce action.
 * @var array<string, string>                   $inputs   Submitted inputs.
 * @var array<string, mixed>|null               $brief    Generated brief, or null.
 * @var \WP_Error|null                          $error    Error to display, or null.
 * @var bool                                    $ready    Whether AI is configured.
 * @var \WPCustomSeo\AI\ProviderInterface|null   $provider Active provider.
 */

use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

$wpcseo_fields = array(
	'topic'        => array( __( 'Topic', 'wp-custom-seo' ), __( 'What the page is about. Required.', 'wp-custom-seo' ) ),
	'keyword'      => array( __( 'Primary keyword', 'wp-custom-seo' ), '' ),
	'audience'     => array( __( 'Target audience', 'wp-custom-seo' ), __( 'Who it is for, and what they already know.', 'wp-custom-seo' ) ),
	'business'     => array( __( 'Business or website', 'wp-custom-seo' ), '' ),
	'country'      => array( __( 'Country', 'wp-custom-seo' ), __( 'Shapes examples, units and regulations.', 'wp-custom-seo' ) ),
	'language'     => array( __( 'Language', 'wp-custom-seo' ), '' ),
	'content_type' => array( __( 'Content type', 'wp-custom-seo' ), __( 'Guide, comparison, landing page, and so on.', 'wp-custom-seo' ) ),
);

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Content Brief', 'wp-custom-seo' ); ?></h1>

	<?php if ( null !== $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error->get_error_message() ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $ready ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'AI is not configured, so briefs cannot be generated.', 'wp-custom-seo' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-custom-seo-ai' ) ); ?>">
					<?php esc_html_e( 'Set up AI', 'wp-custom-seo' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'Plan a page before writing it. The brief is generated on demand and not saved — copy what you need. Nothing is published, and no draft is created.', 'wp-custom-seo' ); ?>
	</p>

	<form method="post">
		<?php wp_nonce_field( $nonce ); ?>

		<table class="form-table" role="presentation">
			<?php foreach ( $wpcseo_fields as $wpcseo_name => $wpcseo_field ) : ?>
				<tr>
					<th><label for="wpcseo_<?php echo esc_attr( $wpcseo_name ); ?>"><?php echo esc_html( $wpcseo_field[0] ); ?></label></th>
					<td>
						<input type="text" class="regular-text"
							id="wpcseo_<?php echo esc_attr( $wpcseo_name ); ?>"
							name="<?php echo esc_attr( $wpcseo_name ); ?>"
							value="<?php echo esc_attr( $inputs[ $wpcseo_name ] ); ?>"
							<?php echo 'topic' === $wpcseo_name ? 'required' : ''; ?>>
						<?php if ( '' !== $wpcseo_field[1] ) : ?>
							<p class="description"><?php echo esc_html( $wpcseo_field[1] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th><label for="wpcseo_intent"><?php esc_html_e( 'Search intent', 'wp-custom-seo' ); ?></label></th>
				<td>
					<select id="wpcseo_intent" name="intent">
						<?php foreach ( Meta::search_intents() as $wpcseo_key => $wpcseo_label ) : ?>
							<option value="<?php echo esc_attr( $wpcseo_key ); ?>" <?php selected( $inputs['intent'], $wpcseo_key ); ?>>
								<?php echo esc_html( '' === $wpcseo_key ? __( 'Let the model decide', 'wp-custom-seo' ) : $wpcseo_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Generate brief', 'wp-custom-seo' ), 'primary', 'wpcseo_brief_submit', true, $ready ? array() : array( 'disabled' => 'disabled' ) ); ?>
	</form>

	<?php if ( null !== $brief ) : ?>
		<hr>
		<h2><?php esc_html_e( 'Brief', 'wp-custom-seo' ); ?></h2>

		<p class="wpcseo-preview-note">
			<?php esc_html_e( 'A plan, not a finished page, and not a set of verified facts. Check anything factual before you publish it.', 'wp-custom-seo' ); ?>
		</p>

		<div class="wpcseo-grid">
			<section class="wpcseo-card">
				<h3><?php esc_html_e( 'Summary', 'wp-custom-seo' ); ?></h3>
				<dl class="wpcseo-list">
					<?php if ( '' !== $brief['title'] ) : ?>
						<dt><?php esc_html_e( 'Suggested title', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( $brief['title'] ); ?></dd>
					<?php endif; ?>

					<?php if ( '' !== $brief['h1'] ) : ?>
						<dt><?php esc_html_e( 'Suggested H1', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( $brief['h1'] ); ?></dd>
					<?php endif; ?>

					<?php if ( '' !== $brief['intent']['type'] ) : ?>
						<dt><?php esc_html_e( 'Search intent', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( $brief['intent']['type'] ); ?></dd>
					<?php endif; ?>

					<?php if ( '' !== $brief['audience'] ) : ?>
						<dt><?php esc_html_e( 'Audience', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( $brief['audience'] ); ?></dd>
					<?php endif; ?>

					<?php if ( '' !== $brief['schema_type'] ) : ?>
						<dt><?php esc_html_e( 'Schema type', 'wp-custom-seo' ); ?></dt>
						<dd><code><?php echo esc_html( $brief['schema_type'] ); ?></code></dd>
					<?php endif; ?>

					<?php if ( '' !== $brief['depth'] ) : ?>
						<dt><?php esc_html_e( 'Depth', 'wp-custom-seo' ); ?></dt>
						<dd><?php echo esc_html( $brief['depth'] ); ?></dd>
					<?php endif; ?>
				</dl>

				<?php if ( '' !== $brief['intent']['reason'] ) : ?>
					<p class="description"><?php echo esc_html( $brief['intent']['reason'] ); ?></p>
				<?php endif; ?>
			</section>

			<section class="wpcseo-card">
				<h3><?php esc_html_e( 'Outline', 'wp-custom-seo' ); ?></h3>
				<?php if ( ! $brief['outline'] ) : ?>
					<p><?php esc_html_e( 'No outline was returned.', 'wp-custom-seo' ); ?></p>
				<?php else : ?>
					<ol class="wpcseo-outline">
						<?php foreach ( $brief['outline'] as $wpcseo_section ) : ?>
							<li>
								<strong><?php echo esc_html( $wpcseo_section['h2'] ); ?></strong>
								<?php if ( '' !== $wpcseo_section['covers'] ) : ?>
									<br><span class="description"><?php echo esc_html( $wpcseo_section['covers'] ); ?></span>
								<?php endif; ?>
								<?php if ( $wpcseo_section['h3'] ) : ?>
									<ul>
										<?php foreach ( $wpcseo_section['h3'] as $wpcseo_sub ) : ?>
											<li><?php echo esc_html( $wpcseo_sub ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</section>
		</div>

		<?php
		$wpcseo_lists = array(
			'questions'                => __( 'Questions to answer', 'wp-custom-seo' ),
			'entities'                 => __( 'Important entities', 'wp-custom-seo' ),
			'related_keywords'         => __( 'Related keywords', 'wp-custom-seo' ),
			'faq_topics'               => __( 'FAQ topics', 'wp-custom-seo' ),
			'internal_link_ideas'      => __( 'Internal linking ideas', 'wp-custom-seo' ),
			'external_reference_ideas' => __( 'Kinds of source to cite', 'wp-custom-seo' ),
		);
		?>

		<div class="wpcseo-grid">
			<?php foreach ( $wpcseo_lists as $wpcseo_key => $wpcseo_label ) : ?>
				<?php if ( $brief[ $wpcseo_key ] ) : ?>
					<section class="wpcseo-card">
						<h3><?php echo esc_html( $wpcseo_label ); ?></h3>
						<ul class="wpcseo-modules">
							<?php foreach ( $brief[ $wpcseo_key ] as $wpcseo_item ) : ?>
								<li><?php echo esc_html( $wpcseo_item ); ?></li>
							<?php endforeach; ?>
						</ul>
						<?php if ( 'external_reference_ideas' === $wpcseo_key ) : ?>
							<p class="description">
								<?php esc_html_e( 'Kinds of source, not specific URLs — the model cannot verify that a particular page exists or says what it claims. Find the actual source yourself.', 'wp-custom-seo' ); ?>
							</p>
						<?php endif; ?>
					</section>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<?php if ( '' !== $brief['notes'] ) : ?>
			<h3><?php esc_html_e( 'Notes', 'wp-custom-seo' ); ?></h3>
			<p><?php echo esc_html( $brief['notes'] ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
