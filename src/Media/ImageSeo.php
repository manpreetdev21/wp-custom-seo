<?php
/**
 * Image SEO reporting.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Media;

defined( 'ABSPATH' ) || exit;

/*
 * Alt-text counts are taken with grouped queries over core tables rather than
 * by loading attachments, which is what keeps a library-wide report to a
 * handful of queries instead of thousands.
 *
 * Every value that varies is still passed through prepare(); what is
 * interpolated is `$wpdb->posts` and `$wpdb->postmeta`, which are table names
 * WordPress owns, and the fixed `WHERE` fragments built beside them.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
 */

/**
 * Reports what the media library is missing, and lets it be fixed in place.
 *
 * **Nothing here writes alt text.** An empty `alt` attribute is meaningful: it
 * is how you tell a screen reader that an image is decoration and should be
 * skipped. A plugin that filled every empty alt with the filename or the post
 * title would be replacing a correct answer with noise, and would make the
 * library look complete while making the site worse to listen to. So this
 * reports, and a person writes.
 *
 * **Why alt is exact and the rest is sampled.** Missing and duplicate alt text
 * are single grouped queries over `postmeta`, so those numbers are counts, not
 * estimates. Filename quality and dimensions live in serialized metadata that
 * cannot be queried without unserializing it, so those are a bounded scan of
 * the most recent uploads. The screen says which is which rather than
 * presenting a sample as a total.
 */
final class ImageSeo {

	/**
	 * How many attachments the sampled checks read.
	 */
	public const SAMPLE = 200;

	/**
	 * Width or height above which an image is worth looking at.
	 *
	 * Not a rule — a hero image legitimately exceeds this. It is the point at
	 * which an image is probably being scaled down by the browser, which means
	 * the visitor downloaded pixels they never saw.
	 */
	private const LARGE_EDGE = 2500;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'manage_media_columns', array( self::class, 'column' ) );
		add_action( 'manage_media_custom_column', array( self::class, 'column_value' ), 10, 2 );
	}

	/**
	 * Add an alt text column to the media library list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string>
	 */
	public static function column( array $columns ): array {
		$columns['wpcseo_alt'] = __( 'Alt text', 'wp-custom-seo' );

		return $columns;
	}

	/**
	 * Render the alt text column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Attachment id.
	 */
	public static function column_value( string $column, int $post_id ): void {
		if ( 'wpcseo_alt' !== $column ) {
			return;
		}

		if ( ! wp_attachment_is_image( $post_id ) ) {
			echo '&mdash;';

			return;
		}

		$alt = trim( (string) get_post_meta( $post_id, '_wp_attachment_image_alt', true ) );

		if ( '' !== $alt ) {
			echo esc_html( $alt );

			return;
		}

		printf(
			'<span class="wpcseo-badge is-off">%s</span>',
			esc_html__( 'None', 'wp-custom-seo' )
		);
	}

	/**
	 * Image attachments in the library.
	 */
	public static function total(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);
	}

	/**
	 * Images with no alt text at all, or alt text that is only whitespace.
	 *
	 * An image row with no meta row and one with an empty string are the same
	 * thing to a browser, so they are counted together.
	 *
	 * @param int $limit Rows to return.
	 *
	 * @return array{count: int, items: array<int, array<string, mixed>>}
	 */
	public static function missing_alt( int $limit = 50 ): array {
		global $wpdb;

		$join = "LEFT JOIN {$wpdb->postmeta} m
			ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'";

		$where = "p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND ( m.meta_id IS NULL OR TRIM( m.meta_value ) = '' )";

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p {$join} WHERE {$where}" );

		$items = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.guid FROM {$wpdb->posts} p {$join}
				WHERE {$where} ORDER BY p.post_date DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return array(
			'count' => $count,
			'items' => $items,
		);
	}

	/**
	 * Alt text used by more than one image.
	 *
	 * Two images sharing alt text is not automatically wrong — the same product
	 * photographed twice can legitimately describe itself the same way — but it
	 * is usually a value pasted across a batch.
	 *
	 * @param int $limit Groups to return.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function duplicate_alt( int $limit = 25 ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_value AS alt, COUNT(*) AS total
				FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key = '_wp_attachment_image_alt'
					AND TRIM( m.meta_value ) <> ''
					AND p.post_type = 'attachment'
				GROUP BY m.meta_value
				HAVING total > 1
				ORDER BY total DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Whether a filename says anything about what the image shows.
	 *
	 * Camera and phone exports (`IMG_4021`, `DSC00417`, `20240817_113256`) and
	 * editor exports (`Screenshot 2024-08-17 at 11.32.56`) all carry no meaning.
	 * A filename is not a ranking factor, but it is one more description of the
	 * image for anything that has to work out what it is.
	 *
	 * @param string $filename Filename without extension.
	 */
	public static function is_opaque_filename( string $filename ): bool {
		$name = strtolower( trim( $filename ) );

		if ( '' === $name ) {
			return true;
		}

		$patterns = array(
			// A known camera or editor prefix followed by nothing but a serial
			// number or a timestamp — "img_4021", "screenshot-2024-08-17".
			'/^(img|dsc|dscn|dcim|pxl|mvimg|photo|image|untitled|scan|screen[ _-]?shot|screenshot)[ _-]?[\d\s\-_.:]*$/',
			'/^\d{4}[-_]?\d{2}[-_]?\d{2}([ _-].*)?$/',
			'/^[0-9a-f]{8,}$/',
			'/^\d+$/',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $name ) ) {
				return true;
			}
		}

		// Two words or fewer, none of them longer than three characters, is not
		// a description of anything.
		$parts = preg_split( '/[^a-z0-9]+/', $name );
		$words = array_filter( is_array( $parts ) ? $parts : array() );

		return ! array_filter( $words, static fn ( string $word ): bool => strlen( $word ) > 3 );
	}

	/**
	 * Sampled checks that need the attachment's stored metadata.
	 *
	 * One query for the ids, then core's own cached metadata reads. Bounded by
	 * SAMPLE because unserializing every attachment's metadata on a large
	 * library is exactly the kind of work a page load should not be doing.
	 *
	 * @return array{scanned: int, opaque: array<int, array<string, mixed>>, large: array<int, array<string, mixed>>, no_dimensions: int}
	 */
	public static function sampled(): array {
		global $wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts}
				WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%'
				ORDER BY post_date DESC LIMIT %d",
				self::SAMPLE
			)
		);

		$opaque        = array();
		$large         = array();
		$no_dimensions = 0;

		foreach ( $rows as $row ) {
			$id   = (int) $row->ID;
			$meta = wp_get_attachment_metadata( $id );

			$width  = (int) ( $meta['width'] ?? 0 );
			$height = (int) ( $meta['height'] ?? 0 );

			if ( $width <= 0 || $height <= 0 ) {
				++$no_dimensions;
			} elseif ( $width > self::LARGE_EDGE || $height > self::LARGE_EDGE ) {
				$large[] = array(
					'ID'         => $id,
					'post_title' => (string) $row->post_title,
					'width'      => $width,
					'height'     => $height,
				);
			}

			if ( self::is_opaque_filename( (string) $row->post_name ) ) {
				$opaque[] = array(
					'ID'         => $id,
					'post_title' => (string) $row->post_title,
					'post_name'  => (string) $row->post_name,
				);
			}
		}

		return array(
			'scanned'       => count( $rows ),
			'opaque'        => $opaque,
			'large'         => $large,
			'no_dimensions' => $no_dimensions,
		);
	}

	/**
	 * Whether the site is serving a modern image format.
	 *
	 * Reports what the installation can actually do rather than asserting a
	 * recommendation: WebP and AVIF support depend on the imaging library
	 * compiled into this server, and telling someone to use a format their host
	 * cannot produce is advice they cannot act on.
	 *
	 * @return array<string, bool>
	 */
	public static function formats(): array {
		return array(
			'image/webp' => (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ),
			'image/avif' => (bool) wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ),
		);
	}
}
