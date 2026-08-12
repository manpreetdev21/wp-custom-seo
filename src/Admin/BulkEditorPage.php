<?php
/**
 * Bulk SEO editor.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\SEO\Meta;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Bulk Editor: edit titles, descriptions, canonicals and robots in a list.
 *
 * One page of results is loaded at a time and the page size is capped, so a
 * site with fifty thousand posts never ships more than a screenful to the
 * browser.
 */
final class BulkEditorPage {

	public const SLUG = 'wp-custom-seo-bulk';

	private const NONCE = 'wpcseo_bulk_editor';

	private const PER_PAGE = 20;

	/**
	 * Notice to show on the next render.
	 *
	 * @var array{type: string, message: string}|null
	 */
	private static ?array $notice = null;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'handle' ) );
	}

	/**
	 * Add the screen to the menu registry.
	 *
	 * @param array<string, array<string, mixed>> $pages Registered pages.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function register( array $pages ): array {
		$pages[ self::SLUG ] = array(
			'title'      => __( 'Bulk Editor', 'wp-custom-seo' ),
			'menu_title' => __( 'Bulk Editor', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Fields this screen edits, mapped to their meta keys.
	 *
	 * @return array<string, string>
	 */
	private static function fields(): array {
		return array(
			'title'       => Meta::TITLE,
			'description' => Meta::DESCRIPTION,
			'canonical'   => Meta::CANONICAL,
		);
	}

	/**
	 * Save submitted rows.
	 */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is checked below.
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] || ! isset( $_POST['wpcseo_bulk_submit'] ) ) {
			return;
		}

		if ( ! Capabilities::can_manage() ) {
			return;
		}

		check_admin_referer( self::NONCE );

		$rows        = isset( $_POST['wpcseo'] ) ? (array) wp_unslash( $_POST['wpcseo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized by its registered callback below.
		$definitions = Meta::keys();
		$saved       = 0;

		foreach ( $rows as $post_id => $values ) {
			$post_id = absint( $post_id );

			// Capability is checked per post, not once for the screen: an
			// editor may be able to reach this page without being allowed to
			// edit every post listed on it.
			if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) || ! is_array( $values ) ) {
				continue;
			}

			foreach ( self::fields() as $field => $meta_key ) {
				if ( ! array_key_exists( $field, $values ) ) {
					continue;
				}

				$clean = call_user_func( $definitions[ $meta_key ]['sanitize'], (string) $values[ $field ] );

				if ( '' === $clean ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $clean );
				}
			}

			if ( empty( $values['noindex'] ) ) {
				delete_post_meta( $post_id, Meta::NOINDEX );
			} else {
				update_post_meta( $post_id, Meta::NOINDEX, true );
			}

			++$saved;
		}

		self::$notice = array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: %d: number of rows saved. */
				_n( '%d item saved.', '%d items saved.', $saved, 'wp-custom-seo' ),
				$saved
			),
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list controls.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : 'post';
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		$missing   = isset( $_GET['missing'] ) ? sanitize_key( wp_unslash( (string) $_GET['missing'] ) ) : '';
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$post_types = Meta::post_types();
		$post_type  = in_array( $post_type, $post_types, true ) ? $post_type : (string) reset( $post_types );

		$args = array(
			'post_type'              => $post_type,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => self::PER_PAGE,
			'paged'                  => $paged,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( 'title' === $missing || 'description' === $missing ) {
			$key = 'title' === $missing ? Meta::TITLE : Meta::DESCRIPTION;

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an indexed key, on a paginated admin screen only.
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => $key,
					'value'   => '',
					'compare' => '=',
				),
			);
		}

		$query = new WP_Query( $args );

		$vars = array(
			'query'      => $query,
			'post_type'  => $post_type,
			'post_types' => $post_types,
			'search'     => $search,
			'missing'    => $missing,
			'paged'      => $paged,
			'notice'     => self::$notice,
			'nonce'      => self::NONCE,
			'per_page'   => self::PER_PAGE,
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/bulk-editor.php';

		wp_reset_postdata();
	}

	/**
	 * Whether a post object is editable by the current user.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function can_edit( WP_Post $post ): bool {
		return current_user_can( 'edit_post', $post->ID );
	}
}
