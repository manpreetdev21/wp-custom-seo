<?php
/**
 * Plugin capability.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A single custom capability so SEO access can be delegated to editors without
 * handing out `manage_options`. Granted to administrators at activation.
 */
final class Capabilities {

	public const MANAGE = 'wpcseo_manage_seo';

	/**
	 * Whether the current user may manage the plugin.
	 */
	public static function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}
}
