<?php
/**
 * Front-end JSON-LD output.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema;

use WPCustomSeo\Core\Settings;
use WPCustomSeo\Schema\Graph\Pieces;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the schema graph.
 *
 * A graph that fails validation is withheld rather than published, on the
 * principle that no structured data is better than wrong structured data.
 */
final class Output {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		if ( ! Settings::enabled( 'enable_seo' ) || ! Settings::enabled( 'enable_schema' ) ) {
			return;
		}

		add_action( 'wp_head', array( self::class, 'render' ), 5 );
	}

	/**
	 * Render the JSON-LD block.
	 */
	public static function render(): void {
		if ( is_feed() || is_embed() ) {
			return;
		}

		$graph  = Pieces::current();
		$issues = Validator::validate( $graph );

		if ( Validator::has_errors( $issues ) ) {
			self::log( $issues );

			return;
		}

		$json = $graph->to_json();

		if ( '' === $json ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output; slashes stay escaped so it cannot terminate the script element.
		echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}

	/**
	 * Record validation errors when debug logging is on.
	 *
	 * @param array<int, array{level: string, node: string, message: string}> $issues Validation issues.
	 */
	private static function log( array $issues ): void {
		if ( ! Settings::enabled( 'debug_logging' ) ) {
			return;
		}

		foreach ( $issues as $issue ) {
			if ( Validator::ERROR !== $issue['level'] ) {
				continue;
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind the plugin's debug setting.
			error_log( sprintf( '[WP Custom SEO] schema withheld: %s (%s)', $issue['message'], $issue['node'] ) );
		}
	}
}
