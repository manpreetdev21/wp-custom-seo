<?php
/**
 * Redirect matching and dispatch.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Redirects;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Matches the current request against the redirect rules.
 *
 * Runs on `template_redirect`, which WordPress has already excluded admin,
 * REST, cron and AJAX requests from, so no hand-rolled guard list is needed.
 *
 * ponytail: matching after the main query costs one wasted query on a URL that
 * is about to be redirected. Move to `parse_request` if redirect volume ever
 * makes that measurable.
 */
final class Engine {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		if ( ! Settings::enabled( 'enable_redirects' ) ) {
			return;
		}

		add_action( 'template_redirect', array( self::class, 'dispatch' ), 1 );
	}

	/**
	 * Redirect the current request if a rule matches.
	 */
	public static function dispatch(): void {
		if ( is_robots() || is_favicon() ) {
			return;
		}

		$request = self::request_path();

		if ( '' === $request || ! Redirects::has_any() ) {
			return;
		}

		$match = self::find( $request );

		if ( null === $match ) {
			return;
		}

		[ $rule, $target ] = $match;

		$target = self::carry_query( $target );

		/**
		 * Filters the destination just before the redirect is issued.
		 *
		 * @param string $target  Destination URL.
		 * @param object $rule    Matched rule.
		 * @param string $request Requested path.
		 */
		$target = (string) apply_filters( 'wpcseo_redirect_target', $target, $rule, $request );

		if ( '' === $target ) {
			return;
		}

		Redirects::record_hit( (int) $rule->id );

		/**
		 * Fires immediately before a redirect is sent.
		 *
		 * @param object $rule    Matched rule.
		 * @param string $target  Destination URL.
		 * @param string $request Requested path.
		 */
		do_action( 'wpcseo_redirect', $rule, $target, $request );

		// wp_safe_redirect for local destinations; an explicitly configured
		// external target is allowed, since an administrator entered it.
		if ( self::is_external( $target ) ) {
			wp_redirect( $target, (int) $rule->type ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- destination entered by an administrator with the plugin capability.
		} else {
			wp_safe_redirect( $target, (int) $rule->type );
		}

		exit;
	}

	/**
	 * Find a matching rule and resolve its target.
	 *
	 * @param string $request Normalised request path.
	 *
	 * @return array{0: object, 1: string}|null
	 */
	public static function find( string $request ): ?array {
		$literal = Redirects::match( $request );

		if ( null !== $literal ) {
			return array( $literal, (string) $literal->target );
		}

		foreach ( Redirects::regex_rules() as $rule ) {
			$pattern = Redirects::delimit( (string) $rule->source );

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a pattern that stopped compiling must not break the site.
			$matched = @preg_match( $pattern, $request );

			if ( 1 !== $matched ) {
				continue;
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- as above.
			$target = @preg_replace( $pattern, (string) $rule->target, $request );

			if ( is_string( $target ) && '' !== $target ) {
				return array( $rule, $target );
			}
		}

		return null;
	}

	/**
	 * The requested path, normalised the same way stored sources are.
	 */
	public static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		return '' === $uri ? '' : Redirects::normalize( $uri );
	}

	/**
	 * Preserve the incoming query string when the target has none.
	 *
	 * @param string $target Destination URL or path.
	 */
	private static function carry_query( string $target ): string {
		$query = isset( $_SERVER['REQUEST_URI'] )
			? (string) wp_parse_url( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ), PHP_URL_QUERY )
			: '';

		if ( '' === $query || str_contains( $target, '?' ) ) {
			return $target;
		}

		return $target . '?' . $query;
	}

	/**
	 * Whether a destination leaves this site.
	 *
	 * @param string $target Destination URL or path.
	 */
	private static function is_external( string $target ): bool {
		$host = (string) wp_parse_url( $target, PHP_URL_HOST );

		return '' !== $host && (string) wp_parse_url( home_url(), PHP_URL_HOST ) !== $host;
	}
}
