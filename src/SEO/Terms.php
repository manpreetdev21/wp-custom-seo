<?php
/**
 * SEO metadata for taxonomy terms.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Gives category, tag and custom taxonomy archives their own SEO metadata.
 *
 * Until now a term archive could only be described by the site-wide
 * `title_term` template, which meant every category on a site shared one shape
 * of title and had no description of its own beyond the term description — a
 * field written for the visible page, not for a search result. A term archive
 * is a landing page like any other, so it gets the same controls a post has.
 *
 * The meta keys are deliberately the same strings as the post ones. Term meta
 * lives in its own table, so there is no collision, and reusing the names means
 * one vocabulary across the plugin rather than two spellings of "canonical".
 *
 * Registration goes through `register_term_meta()`, which supplies the
 * sanitization, the REST exposure and the authorization callback, so the block
 * editor and the REST API share this definition rather than reimplementing it.
 */
final class Terms {

	public const TITLE = '_wpcseo_title';

	public const DESCRIPTION = '_wpcseo_description';

	public const CANONICAL = '_wpcseo_canonical';

	public const NOINDEX = '_wpcseo_noindex';

	public const NOFOLLOW = '_wpcseo_nofollow';

	public const NOARCHIVE = '_wpcseo_noarchive';

	public const NOSNIPPET = '_wpcseo_nosnippet';

	public const MAX_SNIPPET = '_wpcseo_max_snippet';

	public const MAX_IMAGE_PREVIEW = '_wpcseo_max_image_preview';

	public const MAX_VIDEO_PREVIEW = '_wpcseo_max_video_preview';

	private const NONCE = 'wpcseo_term_meta';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 20 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( self::class, 'hook_forms' ) );
		}
	}

	/**
	 * Taxonomies that get SEO metadata.
	 *
	 * Public and query-able only: a taxonomy with no archive has no page for a
	 * title or a canonical to describe.
	 *
	 * @return string[]
	 */
	public static function taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			)
		);

		/**
		 * Filters the taxonomies that receive SEO metadata.
		 *
		 * @param string[] $taxonomies Taxonomy slugs.
		 */
		return array_values( (array) apply_filters( 'wpcseo_taxonomies', array_values( $taxonomies ) ) );
	}

	/**
	 * Meta key definitions.
	 *
	 * @return array<string, array{type: string, sanitize: callable}>
	 */
	public static function keys(): array {
		return array(
			self::TITLE             => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::DESCRIPTION       => array(
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			),
			self::CANONICAL         => array(
				'type'     => 'string',
				'sanitize' => array( Meta::class, 'sanitize_url_field' ),
			),
			self::NOINDEX           => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::NOFOLLOW          => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::NOARCHIVE         => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::NOSNIPPET         => array(
				'type'     => 'boolean',
				'sanitize' => 'rest_sanitize_boolean',
			),
			self::MAX_SNIPPET       => array(
				'type'     => 'string',
				'sanitize' => static fn ( mixed $value ): string => Robots::sanitize( 'max_snippet', $value ),
			),
			self::MAX_IMAGE_PREVIEW => array(
				'type'     => 'string',
				'sanitize' => static fn ( mixed $value ): string => Robots::sanitize( 'max_image_preview', $value ),
			),
			self::MAX_VIDEO_PREVIEW => array(
				'type'     => 'string',
				'sanitize' => static fn ( mixed $value ): string => Robots::sanitize( 'max_video_preview', $value ),
			),
		);
	}

	/**
	 * Register every key against every SEO-enabled taxonomy.
	 */
	public static function register(): void {
		foreach ( self::taxonomies() as $taxonomy ) {
			foreach ( self::keys() as $key => $definition ) {
				register_term_meta(
					$taxonomy,
					$key,
					array(
						'type'              => $definition['type'],
						'single'            => true,
						'default'           => 'boolean' === $definition['type'] ? false : '',
						'show_in_rest'      => true,
						'sanitize_callback' => $definition['sanitize'],
						'auth_callback'     => static function ( bool $allowed, string $meta_key, int $term_id ): bool {
							return current_user_can( 'edit_term', $term_id );
						},
					)
				);
			}
		}
	}

	/**
	 * Attach the fields to each taxonomy's edit screen.
	 */
	public static function hook_forms(): void {
		foreach ( self::taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', array( self::class, 'render_fields' ), 20 );
			add_action( 'edited_' . $taxonomy, array( self::class, 'save' ) );
			add_action( 'created_' . $taxonomy, array( self::class, 'save' ) );
		}
	}

	/**
	 * Read one value.
	 *
	 * @param int    $term_id Term id.
	 * @param string $key     One of the class constants.
	 *
	 * @return mixed
	 */
	public static function get( int $term_id, string $key ): mixed {
		$value = get_term_meta( $term_id, $key, true );

		if ( 'boolean' === ( self::keys()[ $key ]['type'] ?? 'string' ) ) {
			$value = (bool) $value;
		}

		/**
		 * Filters a term SEO meta value as it is read.
		 *
		 * @param mixed  $value   Stored value.
		 * @param int    $term_id Term id.
		 * @param string $key     Meta key.
		 */
		return apply_filters( 'wpcseo_term_meta_value', $value, $term_id, $key );
	}

	/**
	 * A term's robots directives, keyed by the short names Robots understands.
	 *
	 * @param int $term_id Term id.
	 *
	 * @return array<string, mixed>
	 */
	public static function robots_values( int $term_id ): array {
		return array(
			'noindex'           => self::get( $term_id, self::NOINDEX ),
			'nofollow'          => self::get( $term_id, self::NOFOLLOW ),
			'noarchive'         => self::get( $term_id, self::NOARCHIVE ),
			'nosnippet'         => self::get( $term_id, self::NOSNIPPET ),
			'max_snippet'       => self::get( $term_id, self::MAX_SNIPPET ),
			'max_image_preview' => self::get( $term_id, self::MAX_IMAGE_PREVIEW ),
			'max_video_preview' => self::get( $term_id, self::MAX_VIDEO_PREVIEW ),
		);
	}

	/**
	 * Persist the submitted values.
	 *
	 * @param int $term_id Term id.
	 */
	public static function save( int $term_id ): void {
		// Nothing here runs unless this screen actually posted our fields, so a
		// term edited by another plugin, an importer or WP-CLI is untouched.
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		foreach ( self::keys() as $key => $definition ) {
			$field = 'wpcseo_' . ltrim( $key, '_' );

			if ( 'boolean' === $definition['type'] ) {
				// An unchecked box is absent from the post, which is the only way
				// to tell "off" from "not on this form" — and the form is known to
				// have been rendered because the nonce above came from it.
				update_term_meta( $term_id, $key, ! empty( $_POST[ $field ] ) );

				continue;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line by the callback registered for this meta key.
			$raw = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';

			update_term_meta( $term_id, $key, call_user_func( $definition['sanitize'], $raw ) );
		}
	}

	/**
	 * Render the fields on the term edit screen.
	 *
	 * @param WP_Term $term Term being edited.
	 */
	public static function render_fields( WP_Term $term ): void {
		$term_id = (int) $term->term_id;

		wp_nonce_field( self::NONCE, self::NONCE );

		self::text_row(
			self::TITLE,
			__( 'SEO title', 'wp-custom-seo' ),
			(string) self::get( $term_id, self::TITLE ),
			__( 'Leave empty to use the site-wide taxonomy title template. Variables such as %%term_title%% and %%sitename%% work here too.', 'wp-custom-seo' )
		);

		self::textarea_row(
			self::DESCRIPTION,
			__( 'Meta description', 'wp-custom-seo' ),
			(string) self::get( $term_id, self::DESCRIPTION ),
			__( 'Shown under the result in search. Leave empty to fall back to the term description above.', 'wp-custom-seo' )
		);

		self::text_row(
			self::CANONICAL,
			__( 'Canonical URL', 'wp-custom-seo' ),
			(string) self::get( $term_id, self::CANONICAL ),
			__( 'Only set this if this archive genuinely duplicates another page. Must be an absolute URL, or it is discarded.', 'wp-custom-seo' )
		);

		self::checkbox_row( self::NOINDEX, __( 'Robots', 'wp-custom-seo' ), __( 'noindex — keep this archive out of search results', 'wp-custom-seo' ), (bool) self::get( $term_id, self::NOINDEX ) );
		self::checkbox_row( self::NOFOLLOW, '', __( 'nofollow — do not follow links from this archive', 'wp-custom-seo' ), (bool) self::get( $term_id, self::NOFOLLOW ) );
		self::checkbox_row( self::NOARCHIVE, '', __( 'noarchive — do not offer a cached copy', 'wp-custom-seo' ), (bool) self::get( $term_id, self::NOARCHIVE ) );
		self::checkbox_row( self::NOSNIPPET, '', __( 'nosnippet — show no text snippet for this archive', 'wp-custom-seo' ), (bool) self::get( $term_id, self::NOSNIPPET ) );

		self::select_row( self::MAX_SNIPPET, __( 'Snippet length', 'wp-custom-seo' ), Robots::snippet_options(), (string) self::get( $term_id, self::MAX_SNIPPET ), '' );
		self::select_row( self::MAX_IMAGE_PREVIEW, __( 'Image preview', 'wp-custom-seo' ), Robots::image_preview_options(), (string) self::get( $term_id, self::MAX_IMAGE_PREVIEW ), '' );
		self::select_row( self::MAX_VIDEO_PREVIEW, __( 'Video preview', 'wp-custom-seo' ), Robots::video_preview_options(), (string) self::get( $term_id, self::MAX_VIDEO_PREVIEW ), __( 'These are requests, honoured by the search engines that document them. They do nothing on an archive set to noindex.', 'wp-custom-seo' ) );
	}

	/**
	 * The form field name for a meta key.
	 *
	 * @param string $key Meta key.
	 */
	private static function field( string $key ): string {
		return 'wpcseo_' . ltrim( $key, '_' );
	}

	/**
	 * One text input row.
	 *
	 * @param string $key         Meta key.
	 * @param string $label       Row label.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 */
	private static function text_row( string $key, string $label, string $value, string $description ): void {
		$field = self::field( $key );
		?>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * One textarea row.
	 *
	 * @param string $key         Meta key.
	 * @param string $label       Row label.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 */
	private static function textarea_row( string $key, string $label, string $value, string $description ): void {
		$field = self::field( $key );
		?>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * One checkbox row.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Row label, empty to continue the previous group.
	 * @param string $caption Checkbox caption.
	 * @param bool   $checked Whether it is on.
	 */
	private static function checkbox_row( string $key, string $label, string $caption, bool $checked ): void {
		$field = self::field( $key );
		?>
		<tr class="form-field">
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label for="<?php echo esc_attr( $field ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="1" <?php checked( $checked ); ?>>
					<?php echo esc_html( $caption ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * One select row.
	 *
	 * @param string                $key         Meta key.
	 * @param string                $label       Row label.
	 * @param array<string, string> $options     Allowed values.
	 * @param string                $value       Current value.
	 * @param string                $description Help text.
	 */
	private static function select_row( string $key, string $label, array $options, string $value, string $description ): void {
		$field = self::field( $key );
		?>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>">
					<?php foreach ( $options as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( $value, (string) $option_value ); ?>>
							<?php echo esc_html( (string) $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
