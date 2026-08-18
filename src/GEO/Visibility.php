<?php
/**
 * AI search visibility provider registry.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\GEO;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of AI visibility providers.
 *
 * Mirrors how `AI\Manager` handles language model providers, so a developer who
 * has integrated one already knows the shape of this one. See
 * {@see VisibilityProvider} for why nothing is bundled.
 */
final class Visibility {

	/**
	 * Registered providers, keyed by id.
	 *
	 * @var array<string, VisibilityProvider>
	 */
	private static array $providers = array();

	/**
	 * Whether the registration action has already fired.
	 *
	 * @var bool
	 */
	private static bool $collected = false;

	/**
	 * Register a provider.
	 *
	 * @param VisibilityProvider $provider Provider instance.
	 */
	public static function register( VisibilityProvider $provider ): void {
		self::$providers[ $provider->id() ] = $provider;
	}

	/**
	 * Every registered provider.
	 *
	 * @return array<string, VisibilityProvider>
	 */
	public static function all(): array {
		if ( ! self::$collected ) {
			self::$collected = true;

			/**
			 * Fires so providers can register themselves.
			 *
			 * Call `Visibility::register()` from a listener. Fired once per
			 * request, the first time the registry is read, so registering is
			 * not repeated on every screen that asks.
			 */
			do_action( 'wpcseo_register_visibility_providers' );
		}

		return self::$providers;
	}

	/**
	 * The providers that are configured and usable.
	 *
	 * @return array<string, VisibilityProvider>
	 */
	public static function ready(): array {
		return array_filter(
			self::all(),
			static fn ( VisibilityProvider $provider ): bool => $provider->is_ready()
		);
	}

	/**
	 * One provider by id, or null.
	 *
	 * @param string $id Provider id.
	 */
	public static function get( string $id ): ?VisibilityProvider {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Drop the registry. Used by tests.
	 */
	public static function flush(): void {
		self::$providers = array();
		self::$collected = false;
	}
}
