<?php
/**
 * Robots.txt screen.
 *
 * @package WPCustomSeo
 *
 * @var string $action        Admin-post action name.
 * @var string $rules         Stored custom rules.
 * @var bool   $declare       Whether the sitemap is declared.
 * @var string $preview       The robots.txt currently served.
 * @var bool   $physical      Whether a real robots.txt file exists in the web root.
 * @var bool   $blocks_site   Whether the stored rules hide the whole site.
 * @var bool   $discouraged   Whether the site is set to discourage search engines.
 * @var string $notice        Notice key from the last save.
 * @var string $settings_page URL of the settings screen.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Robots.txt', 'wp-custom-seo' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Rules saved.', 'wp-custom-seo' ); ?></p>
		</div>
	<?php elseif ( 'blocked' === $notice ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Nothing was saved.', 'wp-custom-seo' ); ?></strong>
				<?php esc_html_e( 'Those rules contained “Disallow: /” under “User-agent: *”, which asks every search engine to drop the entire site. If that is genuinely what you want, tick the confirmation box below and save again.', 'wp-custom-seo' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'WordPress serves a robots.txt of its own at /robots.txt. Anything you add here is appended to it. Nothing is written to disk, so removing this plugin cannot leave a file behind.', 'wp-custom-seo' ); ?>
	</p>

	<?php if ( $physical ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'A real robots.txt file exists in the site root.', 'wp-custom-seo' ); ?></strong>
				<?php esc_html_e( 'WordPress only serves its own when there is not one, so nothing on this screen is reaching any crawler. Delete that file to use these rules, or copy them into it by hand.', 'wp-custom-seo' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $discouraged ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'This site is set to discourage search engines.', 'wp-custom-seo' ); ?></strong>
				<?php esc_html_e( 'Settings → Reading has “Discourage search engines from indexing this site” switched on, so robots.txt already disallows everything. That setting overrules anything written here.', 'wp-custom-seo' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $blocks_site ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'These rules hide the whole site from search.', 'wp-custom-seo' ); ?></strong>
				<?php esc_html_e( 'The saved rules disallow everything for every crawler. That is correct for a staging site and catastrophic for a live one.', 'wp-custom-seo' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
		<?php wp_nonce_field( $action ); ?>

		<h2><?php esc_html_e( 'Your rules', 'wp-custom-seo' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'One directive per line, in the usual format — a “User-agent:” line, then the “Allow:” and “Disallow:” lines that apply to it. Lines beginning with # are comments. What you type is passed through as written.', 'wp-custom-seo' ); ?>
		</p>

		<textarea
			name="wpcseo_robots_rules"
			id="wpcseo-robots-rules"
			rows="12"
			class="large-text code"
			spellcheck="false"
			aria-describedby="wpcseo-robots-help"
		><?php echo esc_textarea( $rules ); ?></textarea>

		<p class="description" id="wpcseo-robots-help">
			<?php esc_html_e( 'robots.txt is a request, standardised as RFC 9309 and honoured voluntarily. It controls crawling, not indexing: a page blocked here can still appear in results if something else links to it. To keep a page out of results, use noindex on the page itself — and note that a crawler blocked from fetching the page will never see that noindex.', 'wp-custom-seo' ); ?>
		</p>

		<p>
			<label>
				<input type="checkbox" name="wpcseo_robots_sitemap" value="1" <?php checked( $declare ); ?>>
				<?php esc_html_e( 'Declare the XML sitemap in robots.txt', 'wp-custom-seo' ); ?>
			</label>
		</p>

		<p>
			<label>
				<input type="checkbox" name="wpcseo_robots_confirm" value="1">
				<?php esc_html_e( 'I understand these rules may remove the entire site from search, and I mean to save them anyway.', 'wp-custom-seo' ); ?>
			</label>
			<br>
			<span class="description"><?php esc_html_e( 'Only needed when the rules disallow everything for every crawler.', 'wp-custom-seo' ); ?></span>
		</p>

		<?php submit_button( __( 'Save rules', 'wp-custom-seo' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'What is served right now', 'wp-custom-seo' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: %s: link to the AI crawler settings. */
			esc_html__( 'The complete file, including the AI crawler rules from %s and anything other plugins contribute.', 'wp-custom-seo' ),
			'<a href="' . esc_url( $settings_page ) . '">' . esc_html__( 'Settings → AI crawlers', 'wp-custom-seo' ) . '</a>'
		);
		?>
	</p>

	<pre class="wpcseo-code"><?php echo esc_html( $preview ); ?></pre>

	<p>
		<a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Open /robots.txt', 'wp-custom-seo' ); ?>
		</a>
	</p>
</div>
