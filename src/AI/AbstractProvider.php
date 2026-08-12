<?php
/**
 * Shared provider plumbing.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP transport and error translation shared by every provider.
 *
 * Requests go through the WordPress HTTP API rather than a bundled client, so
 * they honour the site's proxy configuration and any filters an administrator
 * has in place, and the plugin ships no vendor HTTP stack to collide with
 * another plugin's copy.
 *
 * No error path ever includes the API key or the raw provider body: both end
 * up in logs and admin notices.
 */
abstract class AbstractProvider implements ProviderInterface {

	protected const TIMEOUT = 45;

	/**
	 * Most providers publish a fixed model list; those that do not return an
	 * empty array and take a free-text model id.
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array();
	}

	/**
	 * Most current models accept a temperature. Providers override for the
	 * models that reject it.
	 *
	 * @param string $model Model id.
	 */
	public function supports_temperature( string $model ): bool {
		return true;
	}

	/**
	 * POST JSON and decode the reply.
	 *
	 * @param string                $url     Endpoint.
	 * @param array<string, string> $headers Request headers.
	 * @param array<string, mixed>  $body    Request body.
	 *
	 * @return array{data: array<string, mixed>, status: int, duration_ms: int}|WP_Error
	 */
	protected function post( string $url, array $headers, array $body ): array|WP_Error {
		$started = microtime( true );

		$encoded = wp_json_encode( $body );

		if ( false === $encoded ) {
			return new WP_Error( 'wpcseo_ai_encode', __( 'The request could not be prepared.', 'wp-custom-seo' ) );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'headers'     => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'        => $encoded,
				'data_format' => 'body',
			)
		);

		$duration = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( $response instanceof WP_Error ) {
			return $this->network_error( $response );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'wpcseo_ai_malformed',
				__( 'The AI provider returned a response this plugin could not read. Try again; if it persists, check the provider status page.', 'wp-custom-seo' )
			);
		}

		if ( $status < 200 || $status > 299 ) {
			return $this->status_error( $status, $data );
		}

		return array(
			'data'        => $data,
			'status'      => $status,
			'duration_ms' => $duration,
		);
	}

	/**
	 * Translate a transport failure.
	 *
	 * @param WP_Error $error Transport error.
	 */
	private function network_error( WP_Error $error ): WP_Error {
		$message = $error->get_error_message();

		if ( false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'timeout' ) ) {
			return new WP_Error(
				'wpcseo_ai_timeout',
				sprintf(
					/* translators: %d: seconds. */
					__( 'The AI provider did not respond within %d seconds. It may be busy — try again, or use a faster model.', 'wp-custom-seo' ),
					self::TIMEOUT
				)
			);
		}

		return new WP_Error(
			'wpcseo_ai_network',
			__( 'The AI provider could not be reached. Check that this server is allowed to make outbound requests.', 'wp-custom-seo' )
		);
	}

	/**
	 * Translate an HTTP error status into something an administrator can act on.
	 *
	 * The provider's own message is deliberately not passed through: it can
	 * echo request content, and on some providers it echoes the credential.
	 *
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $data   Decoded body.
	 */
	protected function status_error( int $status, array $data ): WP_Error {
		$type = '';

		if ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
			$type = (string) ( $data['error']['type'] ?? $data['error']['status'] ?? '' );
		}

		$message = match ( true ) {
			401 === $status, 403 === $status => __( 'The API key was rejected. Check that it is correct, active, and has access to the selected model.', 'wp-custom-seo' ),
			404 === $status => __( 'That model was not found. Check the model id against your provider\'s documentation.', 'wp-custom-seo' ),
			413 === $status => __( 'The request was too large. Try again on a shorter piece of content.', 'wp-custom-seo' ),
			429 === $status => __( 'The provider rate-limited this request. Wait a moment and try again.', 'wp-custom-seo' ),
			$status >= 500 => __( 'The AI provider is unavailable right now. Try again shortly.', 'wp-custom-seo' ),
			default => __( 'The AI provider rejected the request.', 'wp-custom-seo' ),
		};

		return new WP_Error(
			'wpcseo_ai_http_' . $status,
			$message,
			array(
				'status'         => $status,
				'provider_error' => $type,
			)
		);
	}
}
