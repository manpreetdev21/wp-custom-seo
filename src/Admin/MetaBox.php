<?php
/**
 * Editor SEO panel.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\SEO\Meta;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Classic meta box carrying the SEO fields and live analysis.
 *
 * A meta box renders in the block editor, the classic editor and every page
 * builder that keeps the WordPress edit screen, so one implementation covers
 * all of them without a JavaScript build step.
 */
final class MetaBox {

	private const NONCE_ACTION = 'wpcseo_save_meta';

	private const NONCE_NAME = 'wpcseo_meta_nonce';

	/**
	 * Maps form field names to meta keys.
	 *
	 * @return array<string, string>
	 */
	private static function fields(): array {
		return array(
			'wpcseo_focus_keyword'       => Meta::FOCUS_KEYWORD,
			'wpcseo_title'               => Meta::TITLE,
			'wpcseo_description'         => Meta::DESCRIPTION,
			'wpcseo_canonical'           => Meta::CANONICAL,
			'wpcseo_noindex'             => Meta::NOINDEX,
			'wpcseo_nofollow'            => Meta::NOFOLLOW,
			'wpcseo_schema_type'         => Meta::SCHEMA_TYPE,
			'wpcseo_breadcrumb_title'    => Meta::BREADCRUMB_TITLE,
			'wpcseo_search_intent'       => Meta::SEARCH_INTENT,
			'wpcseo_og_title'            => Meta::OG_TITLE,
			'wpcseo_og_description'      => Meta::OG_DESCRIPTION,
			'wpcseo_og_image'            => Meta::OG_IMAGE,
			'wpcseo_twitter_title'       => Meta::TWITTER_TITLE,
			'wpcseo_twitter_description' => Meta::TWITTER_DESCRIPTION,
			'wpcseo_twitter_image'       => Meta::TWITTER_IMAGE,
		);
	}

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		add_action( 'save_post', array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Register the meta box on every SEO-enabled post type.
	 */
	public static function add(): void {
		add_meta_box(
			'wpcseo-meta-box',
			__( 'SEO', 'wp-custom-seo' ),
			array( self::class, 'render' ),
			Meta::post_types(),
			'normal',
			'high'
		);
	}

	/**
	 * Load the panel assets on post edit screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, Meta::post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpcseo-metabox',
			WP_CUSTOM_SEO_URL . 'assets/css/metabox.css',
			array(),
			\WPCustomSeo\VERSION
		);

		wp_enqueue_script(
			'wpcseo-metabox',
			WP_CUSTOM_SEO_URL . 'assets/js/metabox.js',
			array( 'wp-api-fetch', 'wp-i18n' ),
			\WPCustomSeo\VERSION,
			true
		);

		// Loaded before the panel script so its renderer is defined by the time
		// a response arrives.
		wp_enqueue_script(
			'wpcseo-ai-report',
			WP_CUSTOM_SEO_URL . 'assets/js/ai-report.js',
			array( 'wp-i18n' ),
			\WPCustomSeo\VERSION,
			true
		);

		wp_set_script_translations( 'wpcseo-metabox', 'wp-custom-seo' );
		wp_set_script_translations( 'wpcseo-ai-report', 'wp-custom-seo' );

		wp_add_inline_script(
			'wpcseo-metabox',
			'window.wpcseoEditor = ' . wp_json_encode(
				array(
					'postId'          => (int) get_the_ID(),
					'analysisEnabled' => Settings::enabled( 'enable_analysis' ),
					'restPath'        => '/wp-custom-seo/v1/analysis/',
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Render the panel.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render( WP_Post $post ): void {
		$values = Meta::all( $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/meta-box.php';
	}

	/**
	 * Persist the submitted values.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, Meta::post_types(), true ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$definitions = Meta::keys();

		foreach ( self::fields() as $field => $meta_key ) {
			$type = $definitions[ $meta_key ]['type'];

			if ( 'boolean' === $type ) {
				$value = ! empty( $_POST[ $field ] );

				if ( $value ) {
					update_post_meta( $post_id, $meta_key, true );
				} else {
					delete_post_meta( $post_id, $meta_key );
				}

				continue;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line by the callback registered for this meta key.
			$raw   = isset( $_POST[ $field ] ) ? wp_unslash( (string) $_POST[ $field ] ) : '';
			$clean = call_user_func( $definitions[ $meta_key ]['sanitize'], $raw );

			if ( '' === $clean ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, $clean );
		}
	}
}
