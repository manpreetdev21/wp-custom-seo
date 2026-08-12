<?php
/**
 * AI REST endpoints.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\API;

use WPCustomSeo\AI\Json;
use WPCustomSeo\AI\Manager;
use WPCustomSeo\AI\Prompts\ContentAnalysisPrompt;
use WPCustomSeo\AI\Prompts\FaqPrompt;
use WPCustomSeo\AI\Prompts\InternalLinkPrompt;
use WPCustomSeo\AI\Prompts\KeywordPrompt;
use WPCustomSeo\AI\Prompts\MetaDescriptionPrompt;
use WPCustomSeo\AI\Prompts\Prompt;
use WPCustomSeo\AI\Prompts\TitlePrompt;
use WPCustomSeo\Links\Candidates;
use WPCustomSeo\Schema\Faq;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Meta;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Generation endpoints.
 *
 * Every route is a POST: these have a side effect (a billable third-party
 * request), so they must not be reachable by a GET that a browser, prefetcher
 * or crawler could follow.
 */
final class AIRoutes {

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register' ) );
	}

	/**
	 * Register routes.
	 */
	public static function register(): void {
		foreach ( self::actions() as $slug => $prompt ) {
			register_rest_route(
				Routes::NAMESPACE,
				'/ai/' . $slug,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static fn ( WP_REST_Request $request ) => self::generate( $request, $prompt ),
					'permission_callback' => array( self::class, 'can_generate' ),
					'args'                => array(
						'post_id'     => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'title'       => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'keyword'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'     => array(
							'type'              => 'string',
							'sanitize_callback' => static fn ( $value ): string => wp_kses_post( (string) $value ),
						),
					),
				)
			);
		}
	}

	/**
	 * Available actions, keyed by route slug.
	 *
	 * @return array<string, Prompt>
	 */
	private static function actions(): array {
		return array(
			'title'            => new TitlePrompt(),
			'meta-description' => new MetaDescriptionPrompt(),
			'keywords'         => new KeywordPrompt(),
			'content-analysis' => new ContentAnalysisPrompt(),
			'internal-links'   => new InternalLinkPrompt(),
			'faq'              => new FaqPrompt(),
		);
	}

	/**
	 * Actions whose reply is structured rather than a list of lines.
	 *
	 * Every shaper takes the decoded reply and the context the request was
	 * built from, because checking an answer against what was actually sent is
	 * the only way to tell a grounded reply from a plausible one.
	 *
	 * @return array<string, callable>
	 */
	private static function structured(): array {
		return array(
			'keywords'         => array( self::class, 'shape_keywords' ),
			'content-analysis' => array( self::class, 'shape_analysis' ),
			'internal-links'   => array( self::class, 'shape_links' ),
			'faq'              => array( self::class, 'shape_faq' ),
		);
	}

	/**
	 * Normalise a keyword reply.
	 *
	 * @param array<string, mixed> $data    Decoded reply.
	 * @param array<string, mixed> $context Request context. Unused here.
	 *
	 * @return array<string, mixed>
	 */
	public static function shape_keywords( array $data, array $context = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Shapers share one signature.
		$primary = is_array( $data['primary'] ?? null ) ? $data['primary'] : array();
		$fields  = array( 'keyword', 'intent', 'usage', 'location' );

		return array(
			'primary'   => array(
				'keyword' => trim( (string) ( $primary['keyword'] ?? '' ) ),
				'intent'  => self::intent( (string) ( $primary['intent'] ?? '' ) ),
				'reason'  => trim( (string) ( $primary['reason'] ?? '' ) ),
			),
			'secondary' => Json::rows( $data['secondary'] ?? null, $fields, 8 ),
			'long_tail' => Json::rows( $data['long_tail'] ?? null, $fields, 8 ),
			'questions' => Json::strings( $data['questions'] ?? null, 6 ),
			'entities'  => Json::strings( $data['entities'] ?? null, 8 ),
			'semantic'  => Json::strings( $data['semantic'] ?? null, 8 ),
		);
	}

	/**
	 * Normalise a content analysis reply.
	 *
	 * @param array<string, mixed> $data    Decoded reply.
	 * @param array<string, mixed> $context Request context. Unused here.
	 *
	 * @return array<string, mixed>
	 */
	public static function shape_analysis( array $data, array $context = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Shapers share one signature.
		$intent     = is_array( $data['intent'] ?? null ) ? $data['intent'] : array();
		$explained  = array( 'issue', 'why', 'recommendation' );
		$confidence = (int) ( $intent['confidence'] ?? 0 );

		return array(
			'intent'                   => array(
				'type'       => self::intent( (string) ( $intent['type'] ?? '' ) ),
				'confidence' => max( 0, min( 100, $confidence ) ),
				'reason'     => trim( (string) ( $intent['reason'] ?? '' ) ),
			),
			'summary'                  => trim( (string) ( $data['summary'] ?? '' ) ),
			'missing_topics'           => Json::rows( $data['missing_topics'] ?? null, $explained, 6 ),
			'weak_sections'            => Json::rows( $data['weak_sections'] ?? null, $explained, 6 ),
			'heading_suggestions'      => Json::rows( $data['heading_suggestions'] ?? null, $explained, 6 ),
			'missing_questions'        => Json::strings( $data['missing_questions'] ?? null, 6 ),
			'missing_entities'         => Json::strings( $data['missing_entities'] ?? null, 6 ),
			'internal_link_ideas'      => Json::strings( $data['internal_link_ideas'] ?? null, 6 ),
			'external_reference_ideas' => Json::strings( $data['external_reference_ideas'] ?? null, 6 ),
		);
	}

	/**
	 * Normalise an internal linking reply.
	 *
	 * A suggestion survives only if its target is one of the pages that were
	 * offered. The title and URL shown to the editor are then taken from that
	 * page rather than from the reply, so what is displayed is what exists.
	 *
	 * @param array<string, mixed> $data    Decoded reply.
	 * @param array<string, mixed> $context Request context, carrying candidates.
	 *
	 * @return array<string, mixed>
	 */
	public static function shape_links( array $data, array $context = array() ): array {
		$offered = array();

		foreach ( (array) ( $context['candidates'] ?? array() ) as $candidate ) {
			if ( is_array( $candidate ) ) {
				$offered[ (int) ( $candidate['id'] ?? 0 ) ] = $candidate;
			}
		}

		$content  = (string) ( $context['content'] ?? '' );
		$links    = array();
		$rejected = 0;

		foreach ( Json::rows( $data['links'] ?? null, array( 'id', 'anchor', 'reason', 'confidence', 'placement' ), 6 ) as $row ) {
			$id = (int) $row['id'];

			if ( ! isset( $offered[ $id ] ) || '' === $row['anchor'] ) {
				++$rejected;

				continue;
			}

			$links[] = array(
				'id'         => $id,
				'title'      => (string) ( $offered[ $id ]['title'] ?? '' ),
				'url'        => (string) ( $offered[ $id ]['url'] ?? '' ),
				'anchor'     => $row['anchor'],
				'reason'     => $row['reason'],
				'placement'  => $row['placement'],
				'confidence' => max( 0, min( 100, (int) $row['confidence'] ) ),
				// Whether the anchor wording is already on the page. When it is
				// not, the editor has to write the sentence before linking it,
				// which is worth knowing before accepting.
				'in_content' => self::contains( $content, $row['anchor'] ),
			);
		}

		return array(
			'links'      => $links,
			'considered' => count( $offered ),
			'discarded'  => $rejected,
		);
	}

	/**
	 * Normalise an FAQ reply.
	 *
	 * Each answer is checked against the content it was supposed to come from.
	 * An answer whose quoted source is not in the page is still shown, but
	 * marked, because that is the one an editor needs to read closely.
	 *
	 * @param array<string, mixed> $data    Decoded reply.
	 * @param array<string, mixed> $context Request context.
	 *
	 * @return array<string, mixed>
	 */
	public static function shape_faq( array $data, array $context = array() ): array {
		$content  = (string) ( $context['content'] ?? '' );
		$answered = array();

		foreach ( Json::rows( $data['answered'] ?? null, array( 'question', 'answer', 'source' ), 6 ) as $row ) {
			if ( '' === $row['question'] || '' === $row['answer'] ) {
				continue;
			}

			$row['grounded'] = '' !== $row['source'] && self::contains( $content, $row['source'] );
			$answered[]      = $row;
		}

		$post_id = (int) ( $context['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		return array(
			'answered'   => $answered,
			'unanswered' => Json::rows( $data['unanswered'] ?? null, array( 'question', 'why' ), 6 ),
			// Whether the page already shows an FAQ, which is what decides
			// whether FAQPage schema is emitted for it.
			'in_content' => $post instanceof WP_Post && Faq::qualifies( (string) $post->post_content ),
		);
	}

	/**
	 * Whether a phrase appears in a block of text.
	 *
	 * Compared with whitespace collapsed and case ignored, so a quotation that
	 * crossed a line break in the original still matches.
	 *
	 * @param string $haystack Text to search.
	 * @param string $needle   Phrase to find.
	 */
	private static function contains( string $haystack, string $needle ): bool {
		$flatten = static function ( string $value ): string {
			$value = preg_replace( '/\s+/u', ' ', $value );

			return mb_strtolower( trim( is_string( $value ) ? $value : '' ) );
		};

		$needle = $flatten( $needle );

		return '' !== $needle && str_contains( $flatten( $haystack ), $needle );
	}

	/**
	 * Constrain an intent to the values the plugin recognises.
	 *
	 * A model occasionally invents a category; anything unrecognised becomes
	 * empty rather than being shown as though it meant something.
	 *
	 * @param string $value Raw value.
	 */
	public static function intent( string $value ): string {
		$value = strtolower( trim( $value ) );

		return array_key_exists( $value, Meta::search_intents() ) && '' !== $value ? $value : '';
	}

	/**
	 * Only users who may edit the post may spend the site's AI budget on it.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return true|WP_Error
	 */
	public static function can_generate( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request['post_id'];

		if ( ! get_post( $post_id ) ) {
			return new WP_Error(
				'wpcseo_post_not_found',
				__( 'That post does not exist.', 'wp-custom-seo' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'wpcseo_forbidden',
				__( 'You are not allowed to do that.', 'wp-custom-seo' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Run a prompt and return its suggestions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param Prompt          $prompt  Prompt to run.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function generate( WP_REST_Request $request, Prompt $prompt ): WP_REST_Response|WP_Error {
		$post_id = (int) $request['post_id'];
		$post    = get_post( $post_id );

		$content = $request->has_param( 'content' ) ? (string) $request['content'] : (string) $post->post_content;

		$context = array(
			'post_id'     => $post_id,
			'title'       => $request->has_param( 'title' ) ? (string) $request['title'] : (string) get_the_title( $post_id ),
			'keyword'     => $request->has_param( 'keyword' ) ? (string) $request['keyword'] : (string) Meta::get( $post_id, Meta::FOCUS_KEYWORD ),
			'description' => $request->has_param( 'description' ) ? (string) $request['description'] : (string) Meta::get( $post_id, Meta::DESCRIPTION ),
			'seo_title'   => (string) Meta::get( $post_id, Meta::TITLE ),
			'content'     => Analyzer::to_text( $content ),
		);

		if ( 'internal_links' === $prompt->action() ) {
			$context['candidates'] = Candidates::for_post( $post_id, $content );

			// With nothing to choose from there is no question to ask, and a
			// request that can only come back empty is not worth paying for.
			if ( ! $context['candidates'] ) {
				return new WP_REST_Response(
					array(
						'links'      => array(),
						'considered' => 0,
						'discarded'  => 0,
						'note'       => __( 'No other published page on this site covers enough of the same ground to link to yet.', 'wp-custom-seo' ),
					)
				);
			}
		}

		$result = Manager::run( $prompt, $context );

		if ( $result instanceof WP_Error ) {
			$result->add_data( array( 'status' => 502 ), $result->get_error_code() );

			return $result;
		}

		$payload = array(
			'model' => $result->model,
			'usage' => array(
				'input_tokens'  => $result->input_tokens,
				'output_tokens' => $result->output_tokens,
				'duration_ms'   => $result->duration_ms,
			),
		);

		$structured = self::structured();
		$slug       = str_replace( '_', '-', $prompt->action() );

		if ( isset( $structured[ $slug ] ) ) {
			$decoded = Json::decode( $result->text );

			if ( $decoded instanceof WP_Error ) {
				$decoded->add_data( array( 'status' => 502 ), $decoded->get_error_code() );

				return $decoded;
			}

			return new WP_REST_Response( array_merge( $payload, call_user_func( $structured[ $slug ], $decoded, $context ) ) );
		}

		$payload['suggestions'] = $result->lines();

		return new WP_REST_Response( $payload );
	}
}
