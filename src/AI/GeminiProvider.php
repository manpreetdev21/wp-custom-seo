<?php
/**
 * Google Gemini provider.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the Google Generative Language API.
 *
 * As with OpenAI, no model list is hard-coded — the administrator supplies the
 * model id from Google's own documentation.
 *
 * The key goes in a header rather than the query string, so it does not end up
 * in server access logs.
 */
final class GeminiProvider extends AbstractProvider {

	private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	/**
	 * Provider id.
	 */
	public function id(): string {
		return 'gemini';
	}

	/**
	 * Provider name.
	 */
	public function label(): string {
		return __( 'Google Gemini', 'wp-custom-seo' );
	}

	/**
	 * Where to obtain a key.
	 */
	public function key_url(): string {
		return 'https://aistudio.google.com/apikey';
	}

	/**
	 * Default model id.
	 */
	public function default_model(): string {
		return 'gemini-2.0-flash';
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
			return new WP_Error( 'wpcseo_ai_no_key', __( 'No Google Gemini API key has been saved.', 'wp-custom-seo' ) );
		}

		$body = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $request->prompt ) ),
				),
			),
			'generationConfig' => array( 'maxOutputTokens' => $request->max_tokens ),
		);

		if ( '' !== $request->system ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $request->system ) ) );
		}

		if ( null !== $request->temperature ) {
			$body['generationConfig']['temperature'] = $request->temperature;
		}

		$result = $this->post(
			sprintf( self::ENDPOINT, rawurlencode( $request->model ) ),
			array( 'x-goog-api-key' => $key ),
			$body
		);

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$data       = $result['data'];
		$candidates = (array) ( $data['candidates'] ?? array() );
		$candidate  = is_array( $candidates[0] ?? null ) ? $candidates[0] : array();

		// A blocked response returns 200 with a finishReason and no parts.
		$finish = (string) ( $candidate['finishReason'] ?? '' );

		if ( 'SAFETY' === $finish || 'PROHIBITED_CONTENT' === $finish ) {
			return new WP_Error(
				'wpcseo_ai_refused',
				__( 'The model declined this request. Rephrasing the content or the focus keyphrase usually resolves it.', 'wp-custom-seo' )
			);
		}

		$content = is_array( $candidate['content'] ?? null ) ? $candidate['content'] : array();
		$text    = '';

		foreach ( (array) ( $content['parts'] ?? array() ) as $part ) {
			if ( is_array( $part ) ) {
				$text .= (string) ( $part['text'] ?? '' );
			}
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'wpcseo_ai_empty',
				__( 'The model returned no usable text. Try again, or raise the maximum length.', 'wp-custom-seo' )
			);
		}

		$usage = (array) ( $data['usageMetadata'] ?? array() );

		return new Response(
			trim( $text ),
			$request->model,
			(int) ( $usage['promptTokenCount'] ?? 0 ),
			(int) ( $usage['candidatesTokenCount'] ?? 0 ),
			$result['duration_ms']
		);
	}
}
