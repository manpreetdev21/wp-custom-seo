<?php
/**
 * Bulk SEO editor screen.
 *
 * @package WPCustomSeo
 *
 * @var WP_Query                                  $query      Paginated posts.
 * @var string                                    $post_type  Selected post type.
 * @var string[]                                  $post_types Available post types.
 * @var string                                    $search     Search term.
 * @var string                                    $missing    Missing-field filter.
 * @var int                                       $paged      Current page.
 * @var array{type: string, message: string}|null $notice     Notice to display.
 * @var string                                    $nonce      Form nonce action.
 * @var int                                       $per_page   Rows per page.
 */

use WPCustomSeo\Admin\BulkEditorPage;
use WPCustomSeo\SEO\Meta;

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Bulk SEO Editor', 'wp-custom-seo' ); ?></h1>

	<?php if ( null !== $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<p class="wpcseo-lede">
		<?php
		printf(
			/* translators: %d: rows per page. */
			esc_html__( 'Edit SEO fields across many items without opening each one. %d items are loaded at a time, so this stays usable on a large site.', 'wp-custom-seo' ),
			(int) $per_page
		);
		?>
	</p>

	<form method="get" class="wpcseo-schema-picker">
		<input type="hidden" name="page" value="<?php echo esc_attr( BulkEditorPage::SLUG ); ?>">

		<label for="wpcseo_post_type" class="screen-reader-text"><?php esc_html_e( 'Post type', 'wp-custom-seo' ); ?></label>
		<select id="wpcseo_post_type" name="post_type">
			<?php foreach ( $post_types as $wpcseo_type ) : ?>
				<?php $wpcseo_object = get_post_type_object( $wpcseo_type ); ?>
				<option value="<?php echo esc_attr( $wpcseo_type ); ?>" <?php selected( $post_type, $wpcseo_type ); ?>>
					<?php echo esc_html( $wpcseo_object ? (string) $wpcseo_object->labels->name : $wpcseo_type ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label for="wpcseo_missing" class="screen-reader-text"><?php esc_html_e( 'Filter', 'wp-custom-seo' ); ?></label>
		<select id="wpcseo_missing" name="missing">
			<option value=""><?php esc_html_e( 'All items', 'wp-custom-seo' ); ?></option>
			<option value="title" <?php selected( $missing, 'title' ); ?>><?php esc_html_e( 'Missing SEO title', 'wp-custom-seo' ); ?></option>
			<option value="description" <?php selected( $missing, 'description' ); ?>><?php esc_html_e( 'Missing meta description', 'wp-custom-seo' ); ?></option>
		</select>

		<label for="wpcseo_search" class="screen-reader-text"><?php esc_html_e( 'Search', 'wp-custom-seo' ); ?></label>
		<input type="search" id="wpcseo_search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search titles', 'wp-custom-seo' ); ?>">

		<?php submit_button( __( 'Filter', 'wp-custom-seo' ), 'secondary', '', false ); ?>
	</form>

	<?php if ( ! $query->have_posts() ) : ?>
		<p><?php esc_html_e( 'Nothing matches those filters.', 'wp-custom-seo' ); ?></p>
	<?php else : ?>
		<form method="post">
			<?php wp_nonce_field( $nonce ); ?>

			<table class="wp-list-table widefat striped wpcseo-bulk">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Content', 'wp-custom-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'SEO title', 'wp-custom-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Meta description', 'wp-custom-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Canonical', 'wp-custom-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Noindex', 'wp-custom-seo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$wpcseo_post     = get_post();
						$wpcseo_id       = (int) $wpcseo_post->ID;
						$wpcseo_editable = BulkEditorPage::can_edit( $wpcseo_post );
						$wpcseo_values   = Meta::all( $wpcseo_id );
						?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( (string) get_edit_post_link( $wpcseo_id ) ); ?>">
										<?php echo esc_html( get_the_title() ); ?>
									</a>
								</strong><br>
								<span class="description"><?php echo esc_html( get_post_status() ); ?></span>
							</td>

							<?php if ( ! $wpcseo_editable ) : ?>
								<td colspan="4">
									<span class="description"><?php esc_html_e( 'You cannot edit this item.', 'wp-custom-seo' ); ?></span>
								</td>
							<?php else : ?>
								<td>
									<label class="screen-reader-text" for="wpcseo_title_<?php echo esc_attr( (string) $wpcseo_id ); ?>">
										<?php esc_html_e( 'SEO title', 'wp-custom-seo' ); ?>
									</label>
									<input type="text" class="widefat"
										id="wpcseo_title_<?php echo esc_attr( (string) $wpcseo_id ); ?>"
										name="wpcseo[<?php echo esc_attr( (string) $wpcseo_id ); ?>][title]"
										value="<?php echo esc_attr( (string) $wpcseo_values[ Meta::TITLE ] ); ?>">
								</td>
								<td>
									<label class="screen-reader-text" for="wpcseo_desc_<?php echo esc_attr( (string) $wpcseo_id ); ?>">
										<?php esc_html_e( 'Meta description', 'wp-custom-seo' ); ?>
									</label>
									<textarea class="widefat" rows="2"
										id="wpcseo_desc_<?php echo esc_attr( (string) $wpcseo_id ); ?>"
										name="wpcseo[<?php echo esc_attr( (string) $wpcseo_id ); ?>][description]"><?php echo esc_textarea( (string) $wpcseo_values[ Meta::DESCRIPTION ] ); ?></textarea>
								</td>
								<td>
									<label class="screen-reader-text" for="wpcseo_canon_<?php echo esc_attr( (string) $wpcseo_id ); ?>">
										<?php esc_html_e( 'Canonical URL', 'wp-custom-seo' ); ?>
									</label>
									<input type="url" class="widefat"
										id="wpcseo_canon_<?php echo esc_attr( (string) $wpcseo_id ); ?>"
										name="wpcseo[<?php echo esc_attr( (string) $wpcseo_id ); ?>][canonical]"
										value="<?php echo esc_attr( (string) $wpcseo_values[ Meta::CANONICAL ] ); ?>">
								</td>
								<td>
									<label for="wpcseo_noindex_<?php echo esc_attr( (string) $wpcseo_id ); ?>">
										<input type="checkbox" value="1"
											id="wpcseo_noindex_<?php echo esc_attr( (string) $wpcseo_id ); ?>"
											name="wpcseo[<?php echo esc_attr( (string) $wpcseo_id ); ?>][noindex]"
											<?php checked( (bool) $wpcseo_values[ Meta::NOINDEX ], true ); ?>>
										<span class="screen-reader-text"><?php esc_html_e( 'Noindex', 'wp-custom-seo' ); ?></span>
									</label>
								</td>
							<?php endif; ?>
						</tr>
					<?php endwhile; ?>
				</tbody>
			</table>

			<?php submit_button( __( 'Save this page', 'wp-custom-seo' ), 'primary', 'wpcseo_bulk_submit' ); ?>
		</form>

		<?php
		$wpcseo_links = paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'total'     => (int) $query->max_num_pages,
				'current'   => $paged,
				'prev_text' => __( '&laquo; Previous', 'wp-custom-seo' ),
				'next_text' => __( 'Next &raquo;', 'wp-custom-seo' ),
			)
		);
		?>

		<?php if ( $wpcseo_links ) : ?>
			<nav class="tablenav" aria-label="<?php esc_attr_e( 'Bulk editor pagination', 'wp-custom-seo' ); ?>">
				<div class="tablenav-pages">
					<?php echo wp_kses_post( $wpcseo_links ); ?>
				</div>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
