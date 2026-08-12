<?php
/**
 * One audit finding.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * A single thing the audit noticed.
 *
 * Severity follows the four levels the specification asks for rather than a
 * red/amber/green score, because "important" and "worth doing eventually" are
 * different decisions and a colour cannot express that.
 *
 * Every finding carries its evidence — the actual rows it is based on — so an
 * administrator can check the claim instead of taking it on trust.
 */
final class Finding {

	public const CRITICAL = 'critical';

	public const IMPORTANT = 'important';

	public const OPPORTUNITY = 'opportunity';

	public const GOOD = 'good';

	/**
	 * Build a finding.
	 *
	 * @param string                           $id       Stable identifier.
	 * @param string                           $level    One of the level constants.
	 * @param string                           $title    Short statement of the problem.
	 * @param string                           $why      Why it matters.
	 * @param string                           $action   What to do about it.
	 * @param int                              $count    How many items are affected.
	 * @param array<int, array<string, mixed>> $items   Evidence rows.
	 * @param string                           $link     Admin URL that acts on it, or an empty string.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $level,
		public readonly string $title,
		public readonly string $why,
		public readonly string $action,
		public readonly int $count = 0,
		public readonly array $items = array(),
		public readonly string $link = ''
	) {
	}

	/**
	 * Severity levels in the order they should be shown.
	 *
	 * @return array<string, string>
	 */
	public static function levels(): array {
		return array(
			self::CRITICAL    => __( 'Critical', 'wp-custom-seo' ),
			self::IMPORTANT   => __( 'Important', 'wp-custom-seo' ),
			self::OPPORTUNITY => __( 'Opportunity', 'wp-custom-seo' ),
			self::GOOD        => __( 'Good', 'wp-custom-seo' ),
		);
	}

	/**
	 * How each level should be described, so the label is not doing the work alone.
	 *
	 * @return array<string, string>
	 */
	public static function level_descriptions(): array {
		return array(
			self::CRITICAL    => __( 'May stop pages being crawled or indexed at all.', 'wp-custom-seo' ),
			self::IMPORTANT   => __( 'Likely worth fixing, and usually quick.', 'wp-custom-seo' ),
			self::OPPORTUNITY => __( 'Would probably help, but nothing is broken.', 'wp-custom-seo' ),
			self::GOOD        => __( 'Already in order — listed so you can see it was checked.', 'wp-custom-seo' ),
		);
	}
}
