<?php
/**
 * OpenAI provider.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the OpenAI chat completions API.
 *
 * No model list is published here. Model identifiers change faster than a
 * plugin release cycle, and a hard-coded list that has gone stale produces a
 * 404 the administrator cannot fix from the settings screen. The model id is
 * a free-text field instead, with a link to the provider's own model list.
 */
final class OpenAIProvider extends AbstractProvider {

	private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Provider id.
	 */
	public function id(): string {
		return 'openai';
	}

	/**
	 * Provider name.
	 */
	public function label(): string {
		return __( 'OpenAI', 'wp-custom-seo' );
	}

	/**
	 * Where to obtain a key.
	 */
	public function key_url(): string {
		return 'https://platform.openai.com/api-keys';
	}

	/**
	 * Default model id.
	 */
	public function default_model(): string {
		return 'gpt-4o-mini';
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
			return new WP_Error( 'wpcseo_ai_no_key', __( 'No OpenAI API key has been saved.', 'wp-custom-seo' ) );
		}

		$messages = array();

		if ( '' !== $request->system ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $request->system,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $request->prompt,
		);

		$body = array(
			'model'      => $request->model,
			'messages'   => $messages,
			'max_tokens' => $request->max_tokens,
		);

		if ( null !== $request->temperature ) {
			$body['temperature'] = $request->temperature;
		}

		$result = $this->post(
			self::ENDPOINT,
			array( 'Authorization' => 'Bearer ' . $key ),
			$body
		);

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$data    = $result['data'];
		$choices = (array) ( $data['choices'] ?? array() );
		$first   = is_array( $choices[0] ?? null ) ? $choices[0] : array();
		$message = is_array( $first['message'] ?? null ) ? $first['message'] : array();
		$text    = trim( (string) ( $message['content'] ?? '' ) );

		if ( '' === $text ) {
			return new WP_Error(
				'wpcseo_ai_empty',
				__( 'The model returned no usable text. Try again, or raise the maximum length.', 'wp-custom-seo' )
			);
		}

		$usage = (array) ( $data['usage'] ?? array() );

		return new Response(
			$text,
			(string) ( $data['model'] ?? $request->model ),
			(int) ( $usage['prompt_tokens'] ?? 0 ),
			(int) ( $usage['completion_tokens'] ?? 0 ),
			$result['duration_ms']
		);
	}
}
