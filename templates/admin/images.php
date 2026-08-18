<?php
/**
 * Image SEO screen.
 *
 * @package WPCustomSeo
 *
 * @var int                                                    $total      Image attachments in the library.
 * @var array{count: int, items: array<int, array<string, mixed>>} $missing Images with no alt text.
 * @var array<int, array<string, mixed>>                        $duplicates Alt text used more than once.
 * @var array<string, mixed>                                    $sampled    Sampled filename and dimension checks.
 * @var int                                                     $sample_max How many attachments the sample reads.
 * @var array<string, bool>                                     $formats    Modern formats this server can produce.
 * @var bool                                                    $lazy       Whether core lazy loading is on.
 * @var string                                                  $library    URL of the media library.
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wrap wpcseo-wrap">
	<h1><?php esc_html_e( 'Image SEO', 'wp-custom-seo' ); ?></h1>

	<p class="wpcseo-lede">
		<?php esc_html_e( 'Alt text is what a screen reader user hears in place of an image, and it is how anything that cannot see the image works out what it shows. Nothing on this screen writes alt text for you: an empty alt is the correct answer for a purely decorative image, and filling those in with a filename would replace a right answer with noise.', 'wp-custom-seo' ); ?>
	</p>

	<div class="wpcseo-grid">
		<section class="wpcseo-card" aria-labelledby="wpcseo-card-library">
			<h2 id="wpcseo-card-library"><?php esc_html_e( 'Library', 'wp-custom-seo' ); ?></h2>
			<dl class="wpcseo-list">
				<dt><?php esc_html_e( 'Images', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $total ) ); ?></dd>

				<dt><?php esc_html_e( 'Without alt text', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( (int) $missing['count'] ) ); ?></dd>

				<dt><?php esc_html_e( 'Alt text reused', 'wp-custom-seo' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( count( $duplicates ) ) ); ?></dd>
			</dl>
			<p class="description"><?php esc_html_e( 'These three are exact counts over the whole library.', 'wp-custom-seo' ); ?></p>
		</section>

		<section class="wpcseo-card" aria-labelledby="wpcseo-card-delivery">
			<h2 id="wpcseo-card-delivery"><?php esc_html_e( 'Delivery', 'wp-custom-seo' ); ?></h2>
			<dl class="wpcseo-list">
				<dt><?php esc_html_e( 'Lazy loading', 'wp-custom-seo' ); ?></dt>
				<dd>
					<?php if ( $lazy ) : ?>
						<span class="wpcseo-badge is-on"><?php esc_html_e( 'On', 'wp-custom-seo' ); ?></span>
					<?php else : ?>
						<span class="wpcseo-badge is-off"><?php esc_html_e( 'Off', 'wp-custom-seo' ); ?></span>
					<?php endif; ?>
				</dd>

				<?php foreach ( $formats as $wpcseo_mime => $wpcseo_supported ) : ?>
					<dt><?php echo esc_html( strtoupper( str_replace( 'image/', '', (string) $wpcseo_mime ) ) ); ?></dt>
					<dd>
						<?php if ( $wpcseo_supported ) : ?>
							<span class="wpcseo-badge is-on"><?php esc_html_e( 'Available', 'wp-custom-seo' ); ?></span>
						<?php else : ?>
							<span class="wpcseo-badge is-off"><?php esc_html_e( 'Not supported by this server', 'wp-custom-seo' ); ?></span>
						<?php endif; ?>
					</dd>
				<?php endforeach; ?>
			</dl>
			<p class="description">
				<?php esc_html_e( 'Lazy loading and responsive srcset are WordPress core behaviour; this reports whether they are switched on rather than adding a second copy of them. Format support depends on the imaging library compiled into this server.', 'wp-custom-seo' ); ?>
			</p>
		</section>
	</div>

	<h2><?php esc_html_e( 'Images without alt text', 'wp-custom-seo' ); ?></h2>

	<?php if ( ! $missing['items'] ) : ?>
		<p><?php esc_html_e( 'Every image in the library has alt text.', 'wp-custom-seo' ); ?></p>
	<?php else : ?>
		<p class="description">
			<?php
			printf(
				/* translators: 1: rows shown, 2: total rows. */
				esc_html__( 'Showing %1$s of %2$s. An alt column has been added to the media library so these can be fixed in place.', 'wp-custom-seo' ),
				esc_html( number_format_i18n( count( $missing['items'] ) ) ),
				esc_html( number_format_i18n( (int) $missing['count'] ) )
			);
			?>
			<a href="<?php echo esc_url( $library ); ?>"><?php esc_html_e( 'Open the media library', 'wp-custom-seo' ); ?></a>
		</p>

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Image', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'File', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $missing['items'] as $wpcseo_row ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $wpcseo_row['ID'] ) ); ?>">
								<?php echo esc_html( (string) $wpcseo_row['post_title'] ); ?>
							</a>
						</td>
						<td><code><?php echo esc_html( wp_basename( (string) $wpcseo_row['guid'] ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Alt text used more than once', 'wp-custom-seo' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Not automatically wrong — the same product photographed twice can describe itself the same way — but it is usually one value pasted across a batch of uploads.', 'wp-custom-seo' ); ?>
	</p>

	<?php if ( ! $duplicates ) : ?>
		<p><?php esc_html_e( 'No alt text is shared between images.', 'wp-custom-seo' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Alt text', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Images', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $duplicates as $wpcseo_row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $wpcseo_row['alt'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $wpcseo_row['total'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Filenames and dimensions', 'wp-custom-seo' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: 1: attachments scanned, 2: sample limit. */
			esc_html__( 'A sample of the %1$s most recent uploads, up to %2$s. Unlike the counts above these are not totals: filename and dimension data lives in serialized metadata that cannot be counted in a query without reading every row.', 'wp-custom-seo' ),
			esc_html( number_format_i18n( (int) $sampled['scanned'] ) ),
			esc_html( number_format_i18n( $sample_max ) )
		);
		?>
	</p>

	<table class="wp-list-table widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Check', 'wp-custom-seo' ); ?></th>
				<th scope="col"><?php esc_html_e( 'In the sample', 'wp-custom-seo' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Why it matters', 'wp-custom-seo' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Filenames that say nothing', 'wp-custom-seo' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( count( (array) $sampled['opaque'] ) ) ); ?></td>
				<td><?php esc_html_e( 'IMG_4021.jpg and Screenshot 2024-08-17.png describe nothing. Renaming before upload gives the image one more description; it is not a ranking factor, and renaming after upload breaks existing URLs.', 'wp-custom-seo' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Very large images', 'wp-custom-seo' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( count( (array) $sampled['large'] ) ) ); ?></td>
				<td><?php esc_html_e( 'Over 2500px on an edge, the browser is almost certainly scaling the image down — the visitor downloaded pixels they never saw. A full-bleed hero image is a legitimate exception.', 'wp-custom-seo' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'No stored dimensions', 'wp-custom-seo' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( (int) $sampled['no_dimensions'] ) ); ?></td>
				<td><?php esc_html_e( 'Without width and height WordPress cannot build a srcset or reserve layout space, so the page shifts as the image loads. Regenerating thumbnails usually restores them.', 'wp-custom-seo' ); ?></td>
			</tr>
		</tbody>
	</table>

	<?php if ( ! empty( $sampled['opaque'] ) ) : ?>
		<h3><?php esc_html_e( 'Filenames worth a look', 'wp-custom-seo' ); ?></h3>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Image', 'wp-custom-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Slug', 'wp-custom-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( (array) $sampled['opaque'], 0, 25 ) as $wpcseo_row ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $wpcseo_row['ID'] ) ); ?>">
								<?php echo esc_html( (string) $wpcseo_row['post_title'] ); ?>
							</a>
						</td>
						<td><code><?php echo esc_html( (string) $wpcseo_row['post_name'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
