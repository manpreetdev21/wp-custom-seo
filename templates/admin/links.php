<?php
/**
 * Internal links screen.
 *
 * @package WPCustomSeo
 *
 * @var string                    $action      Admin-post action name.
 * @var int                       $total       Links recorded.
 * @var bool                      $rebuilding  Whether a rebuild is running.
 * @var array{done: int, total: int} $progress  Rebuild progress.
 * @var int                       $critical    Incoming-link count treated as critical.
 * @var int                       $warning     Incoming-link count treated as a warning.
 * @var object[]                  $under       Under-linked content.
 * @var object[]                  $most_linked Most linked-to content.
 * @var object[]                  $unresolved  Internal links that resolve to no post.
 * @var bool                      $just_queued Whether a rebuild was just queued.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Internal Links', 'wp-custom-seo' ); ?></h1>

	<?php if ( $just_queued ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Rebuild queued. It runs in the background, a batch at a time, so a large site does not stall.', 'wp-custom-seo' ); ?></p>
		</div>
	<?php endif; ?>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'Internal links let readers follow a subject further and are how search engines discover and relate your pages. This graph is rebuilt whenever a post is saved.', 'wp-custom-seo' ); ?>
	</p>

	<div class="wpcseo-grid">
		<section class="wpcseo-card" aria-labelledby="wpcseo-card-graph">
			<h2 id="wpcseo-card-graph"><?php esc_html_e( 'Graph', 'wp-custom-seo' ); ?></h2>
			<dl class="wpcseo-list">
				<dt><?php esc_html_e( 'Links recorded', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $total ) ); ?></dd>
			</dl>

			<?php if ( $rebuilding ) : ?>
				<p>
					<?php
					printf(
						/* translators: 1: posts scanned, 2: total posts. */
						esc_html__( 'Rebuilding: %1$s of %2$s posts scanned.', 'wp-custom-seo' ),
						esc_html( number_format_i18n( $progress['done'] ) ),
						esc_html( number_format_i18n( $progress['total'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
				<?php wp_nonce_field( $action ); ?>
				<?php submit_button( __( 'Rebuild the link graph', 'wp-custom-seo' ), 'secondary', 'submit', false ); ?>
			</form>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-most">
			<h2 id="wpcseo-card-most"><?php esc_html_e( 'Most linked-to', 'wp-custom-seo' ); ?></h2>
			<?php if ( ! $most_linked ) : ?>
				<p><?php esc_html_e( 'Nothing recorded yet. Rebuild the graph to populate this.', 'wp-custom-seo' ); ?></p>
			<?php else : ?>
				<dl class="wpcseo-list">
					<?php foreach ( $most_linked as $wpcseo_row ) : ?>
						<dt><a href="<?php echo esc_url( (string) get_edit_post_link( (int) $wpcseo_row->target_id ) ); ?>"><?php echo esc_html( (string) $wpcseo_row->post_title ); ?></a></dt>
						<dd><?php echo esc_html( number_format_i18n( (int) $wpcseo_row->total ) ); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</section>
	</div>

	<h2><?php esc_html_e( 'Under-linked content', 'wp-custom-seo' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Pages that little else on the site points to. Content reachable from a navigation menu, along with the front page and posts page, is excluded — a page in the main menu is discoverable regardless of how often prose links to it.', 'wp-custom-seo' ); ?>
	</p>

	<?php if ( ! $under ) : ?>
		<?php
		$wpcseo_empty = \WPCustomSeo\Admin\UI::empty_state(
			'link',
			__( 'Nothing falls below the thresholds', 'wp-custom-seo' ),
			__( 'Every tracked page has at least as many incoming internal links as your settings ask for.', 'wp-custom-seo' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::empty_state() escapes every argument it is given and returns finished markup.
		echo $wpcseo_empty;
		?>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Content', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Incoming links', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $under as $wpcseo_row ) : ?>
					<?php $wpcseo_incoming = (int) $wpcseo_row->incoming; ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $wpcseo_row->ID ) ); ?>">
								<?php echo esc_html( (string) $wpcseo_row->post_title ); ?>
							</a>
						</td>
						<td><?php echo esc_html( (string) $wpcseo_row->post_type ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $wpcseo_incoming ) ); ?></td>
						<td>
							<?php if ( $wpcseo_incoming <= $critical ) : ?>
								<span class="wpcseo-badge is-off"><?php esc_html_e( 'Critical', 'wp-custom-seo' ); ?></span>
							<?php else : ?>
								<span class="wpcseo-badge is-off"><?php esc_html_e( 'Warning', 'wp-custom-seo' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Unresolved internal links', 'wp-custom-seo' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Links pointing at this site that do not resolve to a post. These are not necessarily broken — a category archive, a date archive or a page served by something other than a post all appear here legitimately. Confirming a link is broken needs an actual request, which this screen does not make.', 'wp-custom-seo' ); ?>
	</p>

	<?php if ( ! $unresolved ) : ?>
		<p><?php esc_html_e( 'Every internal link resolves to a post.', 'wp-custom-seo' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'URL', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Anchor', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Linked from', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $unresolved as $wpcseo_row ) : ?>
					<tr>
						<td><code><?php echo esc_html( (string) $wpcseo_row->url ); ?></code></td>
						<td><?php echo esc_html( (string) $wpcseo_row->anchor ); ?></td>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $wpcseo_row->source_id ) ); ?>">
								<?php echo esc_html( (string) $wpcseo_row->post_title ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
