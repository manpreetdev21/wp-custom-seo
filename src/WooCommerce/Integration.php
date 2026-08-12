<?php
/**
 * WooCommerce integration.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\WooCommerce;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the shop features only when WooCommerce is actually present.
 *
 * Nothing in this namespace is referenced unless `is_active()` passes, so a
 * site without WooCommerce never loads a class that would fatal on a missing
 * function. Checkout and cart behaviour is not touched: this module reads
 * product data and describes it, and does nothing else.
 */
final class Integration {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		if ( ! self::is_active() || ! Settings::enabled( 'enable_woocommerce' ) ) {
			return;
		}

		Product::init();

		if ( Settings::enabled( 'woo_replace_structured_data' ) ) {
			add_action( 'init', array( self::class, 'silence_woocommerce_schema' ), 20 );
		}
	}

	/**
	 * Whether WooCommerce is loaded.
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce', false ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Stop WooCommerce emitting its own product structured data.
	 *
	 * Only ever called when the administrator has opted in. This removes one
	 * hook callback; WooCommerce itself is left entirely alone, and unchecking
	 * the setting restores its output on the next request.
	 */
	public static function silence_woocommerce_schema(): void {
		$woocommerce = function_exists( 'WC' ) ? WC() : null;

		if ( ! $woocommerce || ! isset( $woocommerce->structured_data ) ) {
			return;
		}

		remove_action( 'wp_footer', array( $woocommerce->structured_data, 'output_structured_data' ), 10 );
		remove_action( 'woocommerce_email_order_details', array( $woocommerce->structured_data, 'output_email_structured_data' ), 30 );
	}
}
