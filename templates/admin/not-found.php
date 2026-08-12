<?php
/**
 * 404 monitor screen.
 *
 * @package WPCustomSeo
 *
 * @var \WPCustomSeo\Admin\NotFoundTable          $table     Prepared list table.
 * @var array{type: string, message: string}|null $notice    Notice to display.
 * @var bool                                      $enabled   Whether logging is on.
 * @var int                                       $retention Days entries are kept.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( '404 Monitor', 'wp-custom-seo' ); ?></h1>

	<?php if ( null !== $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $enabled ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( '404 logging is turned off, so this list is not being updated.', 'wp-custom-seo' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-custom-seo-settings' ) ); ?>">
					<?php esc_html_e( 'Turn it on in Settings', 'wp-custom-seo' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'URLs that visitors and crawlers asked for and did not find. Not every 404 is a problem — a mistyped URL or an old scan needs no action. Look for entries with a referrer or a high hit count, which usually mean a real broken link.', 'wp-custom-seo' ); ?>
	</p>

	<?php if ( $retention > 0 ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: %d: number of days. */
				esc_html( _n( 'Entries are removed automatically after %d day.', 'Entries are removed automatically after %d days.', $retention, 'wp-custom-seo' ) ),
				(int) $retention
			);
			?>
		</p>
	<?php endif; ?>

	<form method="post">
		<?php
		wp_nonce_field( 'bulk-not_founds' );
		$table->search_box( __( 'Search URLs', 'wp-custom-seo' ), 'wpcseo-404-search' );
		$table->display();
		?>
	</form>

	<form method="post" onsubmit="return window.confirm( '<?php echo esc_js( __( 'Delete every logged 404? This cannot be undone.', 'wp-custom-seo' ) ); ?>' );">
		<?php wp_nonce_field( 'wpcseo_clear_404' ); ?>
		<?php submit_button( __( 'Clear the whole log', 'wp-custom-seo' ), 'delete', 'wpcseo_clear_404', false ); ?>
	</form>
</div>
