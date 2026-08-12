<?php
/**
 * Anthropic provider.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the Anthropic Messages API.
 *
 * Two details of this API are easy to get wrong and are handled explicitly:
 *
 * - The response `content` is a list of blocks and the first one is not
 *   guaranteed to be text, so the text block is searched for rather than
 *   indexed at position zero.
 * - Current models reject `temperature` outright, so it is sent only for the
 *   models that still accept it.
 */
final class AnthropicProvider extends AbstractProvider {

	private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	private const VERSION = '2023-06-01';

	/**
	 * Models that still accept a sampling temperature.
	 *
	 * @var string[]
	 */
	private const TEMPERATURE_MODELS = array( 'claude-haiku-4-5' );

	/**
	 * Provider id.
	 */
	public function id(): string {
		return 'anthropic';
	}

	/**
	 * Provider name.
	 */
	public function label(): string {
		return __( 'Anthropic (Claude)', 'wp-custom-seo' );
	}

	/**
	 * Where to obtain a key.
	 */
	public function key_url(): string {
		return 'https://console.anthropic.com/settings/keys';
	}

	/**
	 * Offered models.
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array(
			'claude-opus-5'    => __( 'Claude Opus 5 — most capable', 'wp-custom-seo' ),
			'claude-sonnet-5'  => __( 'Claude Sonnet 5 — balanced', 'wp-custom-seo' ),
			'claude-haiku-4-5' => __( 'Claude Haiku 4.5 — fastest and cheapest', 'wp-custom-seo' ),
		);
	}

	/**
	 * Default model.
	 */
	public function default_model(): string {
		return 'claude-opus-5';
	}

	/**
	 * Whether a model accepts a temperature.
	 *
	 * The current Claude models reject `temperature` with a 400 rather than
	 * ignoring it, so sending it anyway would break every request.
	 *
	 * @param string $model Model id.
	 */
	public function supports_temperature( string $model ): bool {
		return in_array( $model, self::TEMPERATURE_MODELS, true );
	}

	/**
	 * Send a completion request.
	 *
	 * @param Request $request Neutral request.
	 *
	 * @return Response|WP_Error
	 */
	public function complete( Request $request ): Response|WP_Error {
		$key = Credentials::get( $this->id() );

		if ( '' === $key ) {
			return new WP_Error( 'wpcseo_ai_no_key', __( 'No Anthropic API key has been saved.', 'wp-custom-seo' ) );
		}

		$body = array(
			'model'      => $request->model,
			'max_tokens' => $request->max_tokens,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $request->prompt,
				),
			),
		);

		if ( '' !== $request->system ) {
			$body['system'] = $request->system;
		}

		if ( null !== $request->temperature && $this->supports_temperature( $request->model ) ) {
			$body['temperature'] = $request->temperature;
		}

		$result = $this->post(
			self::ENDPOINT,
			array(
				'x-api-key'         => $key,
				'anthropic-version' => self::VERSION,
			),
			$body
		);

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return $this->read( $result['data'], $request, $result['duration_ms'] );
	}

	/**
	 * Turn a successful body into a Response.
	 *
	 * @param array<string, mixed> $data        Decoded body.
	 * @param Request              $request     Original request.
	 * @param int                  $duration_ms Round-trip time.
	 *
	 * @return Response|WP_Error
	 */
	private function read( array $data, Request $request, int $duration_ms ): Response|WP_Error {
		// A safety refusal returns HTTP 200 with an empty or partial body, so
		// it has to be checked before the content is read.
		if ( 'refusal' === ( $data['stop_reason'] ?? '' ) ) {
			return new WP_Error(
				'wpcseo_ai_refused',
				__( 'The model declined this request. Rephrasing the content or the focus keyphrase usually resolves it.', 'wp-custom-seo' )
			);
		}

		$text = '';

		// content is a list of typed blocks; the text block is not necessarily
		// first, so never index content[0] blindly.
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) ( $block['text'] ?? '' );
			}
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'wpcseo_ai_empty',
				__( 'The model returned no usable text. Try again, or raise the maximum length.', 'wp-custom-seo' )
			);
		}

		$usage = (array) ( $data['usage'] ?? array() );

		return new Response(
			trim( $text ),
			(string) ( $data['model'] ?? $request->model ),
			(int) ( $usage['input_tokens'] ?? 0 ),
			(int) ( $usage['output_tokens'] ?? 0 ),
			$duration_ms
		);
	}
}
