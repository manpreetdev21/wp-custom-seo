<?php
/**
 * Business locations.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Local;

use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Core\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Stores business locations as a custom post type.
 *
 * A post type is used rather than a repeating settings field because
 * WordPress already supplies a list table, search, pagination, revisions and
 * capability handling for one. Locations are not publicly queryable: they
 * describe the business, they are not pages in their own right.
 */
final class Locations {

	public const POST_TYPE = 'wpcseo_location';

	public const TYPE = '_wpcseo_business_type';

	public const STREET = '_wpcseo_street';

	public const LOCALITY = '_wpcseo_locality';

	public const REGION = '_wpcseo_region';

	public const POSTCODE = '_wpcseo_postcode';

	public const COUNTRY = '_wpcseo_country';

	public const PHONE = '_wpcseo_phone';

	public const EMAIL = '_wpcseo_email';

	public const PRICE_RANGE = '_wpcseo_price_range';

	public const LATITUDE = '_wpcseo_latitude';

	public const LONGITUDE = '_wpcseo_longitude';

	public const IMAGE = '_wpcseo_location_image';

	public const SAME_AS = '_wpcseo_location_sameas';

	public const HOURS = '_wpcseo_hours';

	private const NONCE = 'wpcseo_save_location';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );

		if ( ! Settings::enabled( 'enable_local_seo' ) ) {
			return;
		}

		add_shortcode( 'wpcseo_business', array( self::class, 'shortcode' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( self::class, 'add_meta_box' ) );
			add_action( 'save_post_' . self::POST_TYPE, array( self::class, 'save' ) );
		}
	}

	/**
	 * Register the post type.
	 */
	public static function register(): void {
		if ( ! Settings::enabled( 'enable_local_seo' ) ) {
			return;
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Locations', 'wp-custom-seo' ),
					'singular_name' => __( 'Location', 'wp-custom-seo' ),
					'add_new_item'  => __( 'Add location', 'wp-custom-seo' ),
					'edit_item'     => __( 'Edit location', 'wp-custom-seo' ),
					'search_items'  => __( 'Search locations', 'wp-custom-seo' ),
					'not_found'     => __( 'No locations yet.', 'wp-custom-seo' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'wp-custom-seo',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => false,
				'capabilities'    => array_fill_keys(
					array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'create_posts' ),
					Capabilities::MANAGE
				),
			)
		);
	}

	/**
	 * Days of the week, in the order schema.org expects.
	 *
	 * @return array<string, string>
	 */
	public static function days(): array {
		return array(
			'Monday'    => __( 'Monday', 'wp-custom-seo' ),
			'Tuesday'   => __( 'Tuesday', 'wp-custom-seo' ),
			'Wednesday' => __( 'Wednesday', 'wp-custom-seo' ),
			'Thursday'  => __( 'Thursday', 'wp-custom-seo' ),
			'Friday'    => __( 'Friday', 'wp-custom-seo' ),
			'Saturday'  => __( 'Saturday', 'wp-custom-seo' ),
			'Sunday'    => __( 'Sunday', 'wp-custom-seo' ),
		);
	}

	/**
	 * LocalBusiness types offered.
	 *
	 * Only a general set is listed. Choosing a type that does not describe the
	 * business is worse than choosing the general one.
	 *
	 * @return array<string, string>
	 */
	public static function business_types(): array {
		return array(
			'LocalBusiness'               => __( 'General local business', 'wp-custom-seo' ),
			'Store'                       => __( 'Store', 'wp-custom-seo' ),
			'Restaurant'                  => __( 'Restaurant', 'wp-custom-seo' ),
			'CafeOrCoffeeShop'            => __( 'Café or coffee shop', 'wp-custom-seo' ),
			'ProfessionalService'         => __( 'Professional service', 'wp-custom-seo' ),
			'HomeAndConstructionBusiness' => __( 'Home or construction business', 'wp-custom-seo' ),
			'MedicalBusiness'             => __( 'Medical business', 'wp-custom-seo' ),
			'LegalService'                => __( 'Legal service', 'wp-custom-seo' ),
			'FinancialService'            => __( 'Financial service', 'wp-custom-seo' ),
			'AutomotiveBusiness'          => __( 'Automotive business', 'wp-custom-seo' ),
			'HealthAndBeautyBusiness'     => __( 'Health and beauty business', 'wp-custom-seo' ),
			'Lodging'                     => __( 'Lodging', 'wp-custom-seo' ),
			'EducationalOrganization'     => __( 'Educational organization', 'wp-custom-seo' ),
		);
	}

	/**
	 * Text fields, mapped to their sanitizers.
	 *
	 * @return array<string, callable-string>
	 */
	public static function fields(): array {
		return array(
			self::STREET      => 'sanitize_text_field',
			self::LOCALITY    => 'sanitize_text_field',
			self::REGION      => 'sanitize_text_field',
			self::POSTCODE    => 'sanitize_text_field',
			self::COUNTRY     => 'sanitize_text_field',
			self::PHONE       => 'sanitize_text_field',
			self::EMAIL       => 'sanitize_email',
			self::PRICE_RANGE => 'sanitize_text_field',
			self::LATITUDE    => 'sanitize_text_field',
			self::LONGITUDE   => 'sanitize_text_field',
			self::IMAGE       => 'sanitize_url',
			self::SAME_AS     => 'sanitize_textarea_field',
		);
	}

	/**
	 * Every published location.
	 *
	 * @return WP_Post[]
	 */
	public static function all(): array {
		if ( ! Settings::enabled( 'enable_local_seo' ) ) {
			return array();
		}

		return (array) get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 50,
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Opening hours for a location, normalised.
	 *
	 * @param int $post_id Location id.
	 *
	 * @return array<string, array{closed: bool, open: string, close: string}>
	 */
	public static function hours( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::HOURS, true );
		$stored = is_array( $stored ) ? $stored : array();
		$hours  = array();

		foreach ( array_keys( self::days() ) as $day ) {
			$row = is_array( $stored[ $day ] ?? null ) ? $stored[ $day ] : array();

			$hours[ $day ] = array(
				'closed' => ! empty( $row['closed'] ),
				'open'   => self::time( (string) ( $row['open'] ?? '' ) ),
				'close'  => self::time( (string) ( $row['close'] ?? '' ) ),
			);
		}

		return $hours;
	}

	/**
	 * Accept a 24-hour time, or nothing.
	 *
	 * @param string $value Submitted value.
	 */
	public static function time( string $value ): string {
		$value = trim( $value );

		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	/**
	 * Register the editor panel.
	 */
	public static function add_meta_box(): void {
		add_meta_box(
			'wpcseo-location',
			__( 'Business details', 'wp-custom-seo' ),
			array( self::class, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the editor panel.
	 *
	 * @param WP_Post $post Location being edited.
	 */
	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, 'wpcseo_location_nonce' );

		$values = array();

		foreach ( array_keys( self::fields() ) as $key ) {
			$values[ $key ] = (string) get_post_meta( $post->ID, $key, true );
		}

		$values[ self::TYPE ] = (string) get_post_meta( $post->ID, self::TYPE, true );
		$hours                = self::hours( $post->ID );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/location.php';
	}

	/**
	 * Persist the panel.
	 *
	 * @param int $post_id Location id.
	 */
	public static function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['wpcseo_location_nonce'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['wpcseo_location_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		$type = isset( $_POST['wpcseo_business_type'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['wpcseo_business_type'] ) ) : '';

		if ( array_key_exists( $type, self::business_types() ) ) {
			update_post_meta( $post_id, self::TYPE, $type );
		} else {
			delete_post_meta( $post_id, self::TYPE );
		}

		foreach ( self::fields() as $key => $sanitizer ) {
			$field = 'wpcseo_' . ltrim( $key, '_' );

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line by the callback registered for this field.
			$raw   = isset( $_POST[ $field ] ) ? wp_unslash( (string) $_POST[ $field ] ) : '';
			$clean = call_user_func( $sanitizer, $raw );

			if ( '' === $clean ) {
				delete_post_meta( $post_id, $key );
				continue;
			}

			update_post_meta( $post_id, $key, $clean );
		}

		self::save_hours( $post_id );
	}

	/**
	 * Persist the opening hours grid.
	 *
	 * @param int $post_id Location id.
	 */
	private static function save_hours( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by the caller; each value is validated below.
		$submitted = isset( $_POST['wpcseo_hours'] ) ? (array) wp_unslash( $_POST['wpcseo_hours'] ) : array();
		$hours     = array();

		foreach ( array_keys( self::days() ) as $day ) {
			$row    = is_array( $submitted[ $day ] ?? null ) ? $submitted[ $day ] : array();
			$closed = ! empty( $row['closed'] );
			$open   = self::time( (string) ( $row['open'] ?? '' ) );
			$close  = self::time( (string) ( $row['close'] ?? '' ) );

			// A day with no usable times says nothing, so it is not stored.
			if ( ! $closed && ( '' === $open || '' === $close ) ) {
				continue;
			}

			$hours[ $day ] = array(
				'closed' => $closed,
				'open'   => $open,
				'close'  => $close,
			);
		}

		if ( ! $hours ) {
			delete_post_meta( $post_id, self::HOURS );

			return;
		}

		update_post_meta( $post_id, self::HOURS, $hours );
	}

	/**
	 * Render location details for the front end.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 */
	public static function shortcode( $atts = array() ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'wpcseo_business' );
		$id   = (int) $atts['id'];

		$locations = self::all();

		if ( $id > 0 ) {
			$locations = array_values(
				array_filter( $locations, static fn ( WP_Post $post ): bool => $post->ID === $id )
			);
		}

		if ( ! $locations ) {
			return '';
		}

		ob_start();

		require WP_CUSTOM_SEO_DIR . 'templates/business.php';

		return (string) ob_get_clean();
	}
}
