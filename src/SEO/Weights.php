<?php
/**
 * The score model: which checks count, and how much.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One place that decides what each check is worth.
 *
 * The weights used to sit inline at each check, which made the score a fact
 * about the code rather than a decision anyone could see or change. They live
 * here instead, so the whole model is one readable table and every weight is
 * adjustable from the settings screen.
 *
 * **This score is not Google's.** Nothing in it is derived from a search
 * engine's ranking system, and no weight here is a claim about ranking. It is a
 * checklist of things that are usually worth doing, added up. Which is exactly
 * why the numbers are editable: a site whose pages are all short by design
 * should not be told forever that its content is too short.
 *
 * A weight of zero removes a check from the score without hiding its advice —
 * it still appears in the editor, marked as not counted. Silencing a
 * recommendation and disagreeing with its importance are different things.
 */
final class Weights {

	/**
	 * Settings key prefix. One select per check lands in the shared option.
	 */
	public const PREFIX = 'weight_';

	/**
	 * Weight values offered on the settings screen.
	 */
	private const CHOICES = array( '0', '1', '2', '3', '4', '5' );

	/**
	 * The model, once built.
	 *
	 * @var array<string, array{label: string, weight: float, group: string}>|null
	 */
	private static ?array $defaults = null;

	/**
	 * Every check, with its label and default weight.
	 *
	 * The ids match what `Analyzer::analyze()` returns. A check absent from
	 * here still runs and still shows its advice; it simply falls back to a
	 * weight of 1, so a third-party check added through
	 * `wpcseo_analysis_checks` is never silently worth nothing.
	 *
	 * @return array<string, array{label: string, weight: float, group: string}>
	 */
	public static function defaults(): array {
		// Read twice for every check on every analysis, and the editor analyses
		// as you type. Held for the request, like the settings schema is.
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}

		$groups = self::groups();

		$checks = array(
			'focus_keyword'       => array(
				'label'  => __( 'Focus keyphrase set', 'wp-custom-seo' ),
				'weight' => 2.0,
				'group'  => 'keyphrase',
			),
			'title_length'        => array(
				'label'  => __( 'Title length', 'wp-custom-seo' ),
				'weight' => 3.0,
				'group'  => 'title',
			),
			'title_keyword'       => array(
				'label'  => __( 'Keyphrase in title', 'wp-custom-seo' ),
				'weight' => 3.0,
				'group'  => 'title',
			),
			'description_length'  => array(
				'label'  => __( 'Meta description length', 'wp-custom-seo' ),
				'weight' => 3.0,
				'group'  => 'title',
			),
			'description_keyword' => array(
				'label'  => __( 'Keyphrase in description', 'wp-custom-seo' ),
				'weight' => 2.0,
				'group'  => 'title',
			),
			'content_length'      => array(
				'label'  => __( 'Content length', 'wp-custom-seo' ),
				'weight' => 3.0,
				'group'  => 'content',
			),
			'keyword_density'     => array(
				'label'  => __( 'Keyphrase distribution', 'wp-custom-seo' ),
				'weight' => 2.0,
				'group'  => 'keyphrase',
			),
			'keyword_intro'       => array(
				'label'  => __( 'Keyphrase in introduction', 'wp-custom-seo' ),
				'weight' => 2.0,
				'group'  => 'keyphrase',
			),
			'subheadings'         => array(
				'label'  => __( 'Subheadings present', 'wp-custom-seo' ),
				'weight' => 2.0,
				'group'  => 'structure',
			),
			'single_h1'           => array(
				'label'  => __( 'Heading hierarchy', 'wp-custom-seo' ),
				'weight' => 1.0,
				'group'  => 'structure',
			),
			'keyword_subheading'  => array(
				'label'  => __( 'Keyphrase in a subheading', 'wp-custom-seo' ),
				'weight' => 1.0,
				'group'  => 'keyphrase',
			),
			'images'              => array(
				'label'  => __( 'Images present', 'wp-custom-seo' ),
				'weight' => 1.0,
				'group'  => 'media',
			),
			'image_alt'           => array(
				'label'  => __( 'Image alt text', 'wp-custom-seo' ),
				'weight' => 2.0,
				'group'  => 'media',
			),
			'internal_links'      => array(
				'label'  => __( 'Internal links', 'wp-custom-seo' ),
				'weight' => 2.0,
				'group'  => 'links',
			),
			'external_links'      => array(
				'label'  => __( 'External references', 'wp-custom-seo' ),
				'weight' => 1.0,
				'group'  => 'links',
			),
			'slug_keyword'        => array(
				'label'  => __( 'Keyphrase in the URL', 'wp-custom-seo' ),
				'weight' => 1.0,
				'group'  => 'content',
			),
		);

		foreach ( $checks as $id => $check ) {
			if ( ! isset( $groups[ $check['group'] ] ) ) {
				$checks[ $id ]['group'] = 'content';
			}
		}

		/**
		 * Filters the checks that make up the optimization score.
		 *
		 * @param array<string, array{label: string, weight: float, group: string}> $checks Check definitions.
		 */
		self::$defaults = (array) apply_filters( 'wpcseo_score_model', $checks );

		return self::$defaults;
	}

	/**
	 * Drop the cached model. Used by tests.
	 */
	public static function flush(): void {
		self::$defaults = null;
	}

	/**
	 * Groups used to order the settings screen.
	 *
	 * @return array<string, string>
	 */
	public static function groups(): array {
		return array(
			'keyphrase' => __( 'Keyphrase', 'wp-custom-seo' ),
			'title'     => __( 'Title and description', 'wp-custom-seo' ),
			'content'   => __( 'Content', 'wp-custom-seo' ),
			'structure' => __( 'Structure', 'wp-custom-seo' ),
			'media'     => __( 'Media', 'wp-custom-seo' ),
			'links'     => __( 'Links', 'wp-custom-seo' ),
		);
	}

	/**
	 * What one check is worth, after any change made on the settings screen.
	 *
	 * @param string $id Check id.
	 */
	public static function get( string $id ): float {
		$defaults = self::defaults();

		if ( ! isset( $defaults[ $id ] ) ) {
			return 1.0;
		}

		$stored = Settings::get( self::PREFIX . $id, null );

		return null === $stored || '' === $stored
			? (float) $defaults[ $id ]['weight']
			: max( 0.0, (float) $stored );
	}

	/**
	 * A check's display name.
	 *
	 * @param string $id       Check id.
	 * @param string $fallback Used when the check is not in the model.
	 */
	public static function label( string $id, string $fallback = '' ): string {
		return (string) ( self::defaults()[ $id ]['label'] ?? ( '' !== $fallback ? $fallback : $id ) );
	}

	/**
	 * Whether a check counts towards the score at all.
	 *
	 * @param string $id Check id.
	 */
	public static function counts( string $id ): bool {
		return self::get( $id ) > 0.0;
	}

	/**
	 * The settings section describing the model.
	 *
	 * Rendered through the ordinary settings pipeline rather than a screen of
	 * its own: it is registered, sanitized, saved and capability-checked by the
	 * same code as every other setting, which is sixteen fewer things to get
	 * wrong.
	 *
	 * @return array{title: string, description: string, fields: array<string, array<string, mixed>>}
	 */
	public static function section(): array {
		$groups  = self::groups();
		$fields  = array();
		$options = array_combine(
			self::CHOICES,
			array_map(
				static fn ( string $value ): string => '0' === $value
					? __( '0 — not counted', 'wp-custom-seo' )
					: $value,
				self::CHOICES
			)
		);

		// Grouped so the screen reads as a model rather than sixteen unrelated
		// dropdowns.
		foreach ( array_keys( $groups ) as $group ) {
			foreach ( self::defaults() as $id => $check ) {
				if ( $check['group'] !== $group ) {
					continue;
				}

				$fields[ self::PREFIX . $id ] = array(
					'type'        => 'select',
					'label'       => $groups[ $group ] . ' — ' . $check['label'],
					'description' => sprintf(
						/* translators: %s: the default weight. */
						__( 'Default %s. Zero keeps the advice in the editor but leaves it out of the score.', 'wp-custom-seo' ),
						(string) (int) $check['weight']
					),
					'default'     => (string) (int) $check['weight'],
					'options'     => $options,
				);
			}
		}

		return array(
			'title'       => __( 'Score model', 'wp-custom-seo' ),
			'description' => __( 'What each check is worth in the editor’s optimization score. This score is this plugin’s own: it is not Google’s ranking algorithm, nothing in it is derived from one, and no weight here is a claim about ranking. It adds up how completely a page follows well-established on-page practices, which is why you can disagree with it — set a weight to zero and that check still gives its advice in the editor without moving the number.', 'wp-custom-seo' ),
			'fields'      => $fields,
		);
	}
}
