<?php
/**
 * AI provider contract.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One text-completion provider.
 *
 * Implementations translate a neutral request into the provider's wire format
 * and back. Nothing above this interface knows which vendor is in use, which
 * is what lets a site switch providers without touching a prompt.
 */
interface ProviderInterface {

	/**
	 * Stable identifier used in settings and logs.
	 */
	public function id(): string;

	/**
	 * Human-readable name.
	 */
	public function label(): string;

	/**
	 * Where to get an API key, for the settings screen.
	 */
	public function key_url(): string;

	/**
	 * Models this provider offers, keyed by model id.
	 *
	 * An empty array means the provider does not publish a fixed list and the
	 * administrator supplies the model id themselves.
	 *
	 * @return array<string, string>
	 */
	public function models(): array;

	/**
	 * Default model id.
	 */
	public function default_model(): string;

	/**
	 * Whether a model accepts a sampling temperature.
	 *
	 * Several current models reject the parameter outright, so this is asked
	 * per model rather than assumed.
	 *
	 * @param string $model Model id.
	 */
	public function supports_temperature( string $model ): bool;

	/**
	 * Send a completion request.
	 *
	 * @param Request $request Neutral request.
	 *
	 * @return Response|WP_Error
	 */
	public function complete( Request $request ): Response|WP_Error;
}
