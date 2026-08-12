<?php
/**
 * Content brief screen.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\AI\Json;
use WPCustomSeo\AI\Manager;
use WPCustomSeo\AI\Prompts\ContentBriefPrompt;
use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\SEO\Meta;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * SEO → Content Brief: plan a page that does not exist yet.
 *
 * The brief is generated on demand and rendered; nothing is stored and no
 * draft is created. Writing the page is the writer's job — the plugin's job
 * ends at handing them a plan.
 */
final class BriefPage {

	public const SLUG = 'wp-custom-seo-brief';

	private const NONCE = 'wpcseo_content_brief';

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'wpcseo_admin_pages', array( self::class, 'register' ) );
	}

	/**
	 * Add the screen to the menu registry.
	 *
	 * @param array<string, array<string, mixed>> $pages Registered pages.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function register( array $pages ): array {
		$pages[ self::SLUG ] = array(
			'title'      => __( 'Content Brief', 'wp-custom-seo' ),
			'menu_title' => __( 'Content Brief', 'wp-custom-seo' ),
			'callback'   => array( self::class, 'render' ),
		);

		return $pages;
	}

	/**
	 * Render the screen.
	 */
	public static function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-custom-seo' ), 403 );
		}

		$inputs = self::inputs();
		$brief  = null;
		$error  = null;

		if ( isset( $_POST['wpcseo_brief_submit'] ) ) {
			check_admin_referer( self::NONCE );

			if ( '' === $inputs['topic'] ) {
				$error = new WP_Error( 'wpcseo_brief_no_topic', __( 'Enter a topic to plan.', 'wp-custom-seo' ) );
			} else {
				$result = Manager::run( new ContentBriefPrompt(), $inputs );

				if ( $result instanceof WP_Error ) {
					$error = $result;
				} else {
					$decoded = Json::decode( $result->text );

					if ( $decoded instanceof WP_Error ) {
						$error = $decoded;
					} else {
						$brief = self::shape( $decoded );
					}
				}
			}
		}

		$vars = array(
			'nonce'    => self::NONCE,
			'inputs'   => $inputs,
			'brief'    => $brief,
			'error'    => $error,
			'ready'    => Manager::is_ready(),
			'provider' => Manager::provider(),
		);

		// phpcs:ignore WordPress.PHP.DontExtract -- keys are template-controlled, not user input.
		extract( $vars, EXTR_SKIP );

		require WP_CUSTOM_SEO_DIR . 'templates/admin/brief.php';
	}

	/**
	 * Read and sanitise the form.
	 *
	 * @return array<string, string>
	 */
	private static function inputs(): array {
		$fields = array( 'topic', 'keyword', 'audience', 'country', 'language', 'content_type', 'intent', 'business' );
		$values = array();

		foreach ( $fields as $field ) {
			// Read only to repopulate the form. Nothing is acted on here — the
			// branch that generates a brief verifies the nonce first.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line.
			$raw = isset( $_POST[ $field ] ) ? wp_unslash( (string) $_POST[ $field ] ) : '';

			$values[ $field ] = sanitize_text_field( $raw );
		}

		if ( '' === $values['language'] ) {
			$values['language'] = (string) get_bloginfo( 'language' );
		}

		return $values;
	}

	/**
	 * Normalise a brief reply.
	 *
	 * @param array<string, mixed> $data Decoded reply.
	 *
	 * @return array<string, mixed>
	 */
	private static function shape( array $data ): array {
		$intent = is_array( $data['intent'] ?? null ) ? $data['intent'] : array();
		$type   = strtolower( trim( (string) ( $intent['type'] ?? '' ) ) );

		$outline = array();

		foreach ( (array) ( $data['outline'] ?? array() ) as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$outline[] = array(
				'h2'     => trim( (string) ( $section['h2'] ?? '' ) ),
				'h3'     => Json::strings( $section['h3'] ?? null, 6 ),
				'covers' => trim( (string) ( $section['covers'] ?? '' ) ),
			);

			if ( count( $outline ) >= 8 ) {
				break;
			}
		}

		return array(
			'title'                    => trim( (string) ( $data['title'] ?? '' ) ),
			'intent'                   => array(
				'type'   => array_key_exists( $type, Meta::search_intents() ) ? $type : '',
				'reason' => trim( (string) ( $intent['reason'] ?? '' ) ),
			),
			'audience'                 => trim( (string) ( $data['audience'] ?? '' ) ),
			'h1'                       => trim( (string) ( $data['h1'] ?? '' ) ),
			'outline'                  => $outline,
			'questions'                => Json::strings( $data['questions'] ?? null, 8 ),
			'entities'                 => Json::strings( $data['entities'] ?? null, 10 ),
			'related_keywords'         => Json::strings( $data['related_keywords'] ?? null, 10 ),
			'internal_link_ideas'      => Json::strings( $data['internal_link_ideas'] ?? null, 8 ),
			'external_reference_ideas' => Json::strings( $data['external_reference_ideas'] ?? null, 8 ),
			'faq_topics'               => Json::strings( $data['faq_topics'] ?? null, 6 ),
			'schema_type'              => trim( (string) ( $data['schema_type'] ?? '' ) ),
			'depth'                    => trim( (string) ( $data['depth'] ?? '' ) ),
			'notes'                    => trim( (string) ( $data['notes'] ?? '' ) ),
		);
	}
}
