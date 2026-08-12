<?php
/**
 * AI orchestration.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WPCustomSeo\AI\Prompts\Prompt;
use WPCustomSeo\Core\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Selects a provider, enforces the privacy and rate rules, and logs the result.
 *
 * Nothing is sent anywhere unless a person pressed a button: there is no
 * background analysis, no request on save, and no request on page load.
 */
final class Manager {

	private const MAX_PER_HOUR = 100;

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_action( 'wpcseo_prune_ai_log', array( UsageLog::class, 'prune' ) );

		if ( ! wp_next_scheduled( 'wpcseo_prune_ai_log' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpcseo_prune_ai_log' );
		}
	}

	/**
	 * Every registered provider, keyed by id.
	 *
	 * @return array<string, ProviderInterface>
	 */
	public static function providers(): array {
		$providers = array();

		foreach ( array( new AnthropicProvider(), new OpenAIProvider(), new GeminiProvider() ) as $provider ) {
			$providers[ $provider->id() ] = $provider;
		}

		/**
		 * Filters the registered AI providers.
		 *
		 * Add a provider by returning it keyed by its id; it only needs to
		 * implement ProviderInterface.
		 *
		 * @param array<string, ProviderInterface> $providers Providers by id.
		 */
		return (array) apply_filters( 'wpcseo_ai_providers', $providers );
	}

	/**
	 * The configured provider, or null when none is selected.
	 */
	public static function provider(): ?ProviderInterface {
		$providers = self::providers();
		$selected  = (string) Settings::get( 'ai_provider', '' );

		return $providers[ $selected ] ?? null;
	}

	/**
	 * The configured model for the active provider.
	 */
	public static function model(): string {
		$provider = self::provider();

		if ( null === $provider ) {
			return '';
		}

		$model = trim( (string) Settings::get( 'ai_model', '' ) );

		return '' !== $model ? $model : $provider->default_model();
	}

	/**
	 * Whether AI features are usable right now.
	 */
	public static function is_ready(): bool {
		$provider = self::provider();

		return null !== $provider && Credentials::has( $provider->id() );
	}

	/**
	 * Run a prompt.
	 *
	 * @param Prompt               $prompt  Prompt to build from.
	 * @param array<string, mixed> $context Page context.
	 *
	 * @return Response|WP_Error
	 */
	public static function run( Prompt $prompt, array $context ): Response|WP_Error {
		$provider = self::provider();

		if ( null === $provider ) {
			return new WP_Error(
				'wpcseo_ai_no_provider',
				__( 'No AI provider has been selected in Settings → AI.', 'wp-custom-seo' )
			);
		}

		if ( ! Credentials::has( $provider->id() ) ) {
			return new WP_Error(
				'wpcseo_ai_no_key',
				__( 'No API key has been saved for the selected provider.', 'wp-custom-seo' )
			);
		}

		$user_id = get_current_user_id();

		if ( $user_id > 0 && UsageLog::recent_count_for_user( $user_id ) >= self::MAX_PER_HOUR ) {
			return new WP_Error(
				'wpcseo_ai_throttled',
				sprintf(
					/* translators: %d: number of requests. */
					__( 'You have made %d AI requests in the last hour. This limit exists so a mistake cannot run up a large bill; it resets automatically.', 'wp-custom-seo' ),
					self::MAX_PER_HOUR
				)
			);
		}

		$model   = self::model();
		$request = $prompt->build( $context )->with_model( $model, self::temperature( $provider, $model ) );

		/**
		 * Fires immediately before an AI request is sent.
		 *
		 * @param Request           $request  Request about to be sent.
		 * @param ProviderInterface $provider Provider handling it.
		 */
		do_action( 'wpcseo_ai_request', $request, $provider );

		$result = $provider->complete( $request );

		UsageLog::record( $provider->id(), $model, $request->action, $result, $request->post_id );

		/**
		 * Filters an AI response before it is returned.
		 *
		 * @param Response|WP_Error $result  Provider outcome.
		 * @param Request           $request Request that produced it.
		 */
		return apply_filters( 'wpcseo_ai_response', $result, $request );
	}

	/**
	 * Temperature to send, or null when it must be omitted.
	 *
	 * @param ProviderInterface $provider Active provider.
	 * @param string            $model    Model id.
	 */
	private static function temperature( ProviderInterface $provider, string $model ): ?float {
		if ( ! $provider->supports_temperature( $model ) ) {
			return null;
		}

		$value = (string) Settings::get( 'ai_temperature', '0.7' );

		return is_numeric( $value ) ? max( 0.0, min( 1.0, (float) $value ) ) : null;
	}

	/**
	 * A plain-language summary of what a request would send.
	 *
	 * Shown in the editor before anything leaves the site, so the decision to
	 * send content to a third party is an informed one.
	 *
	 * @param ProviderInterface|null $provider Active provider.
	 */
	public static function privacy_notice( ?ProviderInterface $provider ): string {
		if ( null === $provider ) {
			return __( 'No AI provider is configured.', 'wp-custom-seo' );
		}

		return sprintf(
			/* translators: %s: provider name. */
			__( 'Pressing a button below sends this page\'s title, focus keyphrase, existing SEO fields and up to roughly 400 words of its content to %s. Nothing is sent until you press one, and nothing is saved to the page until you apply a suggestion.', 'wp-custom-seo' ),
			$provider->label()
		);
	}
}
