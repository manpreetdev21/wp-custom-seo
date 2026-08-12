<?php
/**
 * Author entity fields.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Entities;

use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the two author fields WordPress does not already store.
 *
 * Name, biography, website and avatar already exist on the user profile, so
 * only a job title and verified profile URLs are added here. Expertise is
 * never inferred or generated.
 */
final class Authors {

	public const JOB_TITLE = '_wpcseo_job_title';

	public const SAME_AS = '_wpcseo_sameas';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'show_user_profile', array( self::class, 'render' ) );
		add_action( 'edit_user_profile', array( self::class, 'render' ) );
		add_action( 'personal_options_update', array( self::class, 'save' ) );
		add_action( 'edit_user_profile_update', array( self::class, 'save' ) );
	}

	/**
	 * Render the profile fields.
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function render( WP_User $user ): void {
		wp_nonce_field( 'wpcseo_save_author', 'wpcseo_author_nonce' );

		?>
		<h2><?php esc_html_e( 'SEO author entity', 'wp-custom-seo' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wpcseo_job_title"><?php esc_html_e( 'Job title or role', 'wp-custom-seo' ); ?></label></th>
				<td>
					<input
						type="text"
						id="wpcseo_job_title"
						name="wpcseo_job_title"
						class="regular-text"
						value="<?php echo esc_attr( (string) get_user_meta( $user->ID, self::JOB_TITLE, true ) ); ?>"
						aria-describedby="wpcseo_job_title_help"
					>
					<p class="description" id="wpcseo_job_title_help">
						<?php esc_html_e( 'Used in Person structured data. Leave empty if it does not apply.', 'wp-custom-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="wpcseo_sameas"><?php esc_html_e( 'Profile URLs', 'wp-custom-seo' ); ?></label></th>
				<td>
					<textarea
						id="wpcseo_sameas"
						name="wpcseo_sameas"
						rows="4"
						class="large-text code"
						aria-describedby="wpcseo_sameas_help"
					><?php echo esc_textarea( (string) get_user_meta( $user->ID, self::SAME_AS, true ) ); ?></textarea>
					<p class="description" id="wpcseo_sameas_help">
						<?php esc_html_e( 'One absolute URL per line, for profiles that genuinely belong to this person. Anything that is not a valid http(s) URL is discarded.', 'wp-custom-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Persist the profile fields.
	 *
	 * @param int $user_id User id.
	 */
	public static function save( int $user_id ): void {
		$nonce = isset( $_POST['wpcseo_author_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['wpcseo_author_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wpcseo_save_author' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$job_title = isset( $_POST['wpcseo_job_title'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['wpcseo_job_title'] ) )
			: '';

		if ( '' === $job_title ) {
			delete_user_meta( $user_id, self::JOB_TITLE );
		} else {
			update_user_meta( $user_id, self::JOB_TITLE, $job_title );
		}

		$same_as = isset( $_POST['wpcseo_sameas'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['wpcseo_sameas'] ) )
			: '';

		// Store only what validates, so the graph never carries a broken URL.
		$same_as = implode( "\n", Registry::urls( $same_as ) );

		if ( '' === $same_as ) {
			delete_user_meta( $user_id, self::SAME_AS );
		} else {
			update_user_meta( $user_id, self::SAME_AS, $same_as );
		}
	}
}
