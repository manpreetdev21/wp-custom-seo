<?php
/**
 * WordPress Abilities API registration.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Abilities;

use WPCustomSeo\Audit\Auditor;
use WPCustomSeo\Core\Capabilities;
use WPCustomSeo\Links\Candidates;
use WPCustomSeo\SearchConsole\Account;
use WPCustomSeo\SearchConsole\Performance;
use WPCustomSeo\SEO\Analyzer;
use WPCustomSeo\SEO\Frontend;
use WPCustomSeo\SEO\Meta;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes what this plugin knows to the Abilities API.
 *
 * The abilities registered here are the plugin's **deterministic** knowledge:
 * what the analysis found, which pages exist to link to, what Google reported,
 * what the audit says. Nothing here calls a language model.
 *
 * That is deliberate. The consumer of an ability is usually already a model —
 * offering it "ask a model to write a title" adds a bill and a second opinion
 * where it already had its own. What it cannot do without the site is know
 * which pages exist, what they currently say, and what search engines report
 * about them. That is what is offered.
 *
 * Every ability is permission-checked exactly as the equivalent screen is. An
 * agent acting for a user can do what that user could do, and nothing more.
 * The registration is skipped entirely when the Abilities API is not present,
 * so the plugin neither requires it nor breaks without it.
 */
final class Abilities {

	/**
	 * Namespace for every ability this plugin registers.
	 */
	public const PREFIX = 'wp-custom-seo/';

	/**
	 * Ability category slug.
	 */
	private const CATEGORY = 'wp-custom-seo';

	/**
	 * Hook registration.
	 *
	 * The Abilities API arrived in recent WordPress and may not be present, so
	 * this attaches nothing at all rather than depending on it.
	 */
	public static function init(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register' ) );
	}

	/**
	 * Whether the API this integrates with exists.
	 */
	public static function is_available(): bool {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * Register the category the abilities belong to.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'SEO', 'wp-custom-seo' ),
				'description' => __( 'Reading and editing this site’s SEO data.', 'wp-custom-seo' ),
			)
		);
	}

	/**
	 * Register every ability.
	 */
	public static function register(): void {
		foreach ( self::definitions() as $name => $definition ) {
			wp_register_ability( self::PREFIX . $name, $definition );
		}
	}

	/**
	 * The abilities, keyed by unprefixed name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array {
		$post_input = array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => __( 'The post, page or product to look at.', 'wp-custom-seo' ),
				),
			),
			'required'   => array( 'post_id' ),
		);

		$definitions = array(
			'analyze-post'       => array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Analyze a page for SEO', 'wp-custom-seo' ),
				'description'         => __( 'Runs this plugin\'s on-page analysis and returns the score with every check, what it found, why it matters and what to do about it. The score is this plugin\'s own checklist, not a search engine\'s ranking.', 'wp-custom-seo' ),
				'input_schema'        => $post_input,
				'execute_callback'    => array( self::class, 'analyze' ),
				'permission_callback' => array( self::class, 'can_edit' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			),
			'get-seo-meta'       => array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Read a page\'s SEO fields', 'wp-custom-seo' ),
				'description'         => __( 'Returns the stored SEO title, meta description, focus keyphrase, canonical, robots directives and social fields for one page, plus the title and description that are actually output once templates are applied.', 'wp-custom-seo' ),
				'input_schema'        => $post_input,
				'execute_callback'    => array( self::class, 'get_meta' ),
				'permission_callback' => array( self::class, 'can_edit' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			),
			'update-seo-meta'    => array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Change a page\'s SEO fields', 'wp-custom-seo' ),
				'description'         => __( 'Writes the SEO title, meta description or focus keyphrase for one page. Only the fields supplied are changed; each is validated the same way the editor validates it.', 'wp-custom-seo' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array(
							'type'        => 'integer',
							'description' => __( 'The post, page or product to change.', 'wp-custom-seo' ),
						),
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'SEO title. Supports %%variable%% placeholders.', 'wp-custom-seo' ),
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Meta description.', 'wp-custom-seo' ),
						),
						'keyword'     => array(
							'type'        => 'string',
							'description' => __( 'Focus keyphrase.', 'wp-custom-seo' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'execute_callback'    => array( self::class, 'update_meta' ),
				'permission_callback' => array( self::class, 'can_edit' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						// Replaces named fields rather than removing anything,
						// and writing the same values twice changes nothing.
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			),
			'link-candidates'    => array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Find pages this page could link to', 'wp-custom-seo' ),
				'description'         => __( 'Returns real published pages on this site that share subject matter with the given page and are not already linked to from it. Every result exists — the list is computed from the site\'s own content, not suggested.', 'wp-custom-seo' ),
				'input_schema'        => $post_input,
				'execute_callback'    => array( self::class, 'link_candidates' ),
				'permission_callback' => array( self::class, 'can_edit' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			),
			'site-audit'         => array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Audit the whole site', 'wp-custom-seo' ),
				'description'         => __( 'Returns everything the site audit found, by severity, each with the number of pages affected and what to do about it. Computed from stored data — it makes no external request and costs nothing to run.', 'wp-custom-seo' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'fresh' => array(
							'type'        => 'boolean',
							'description' => __( 'Rebuild rather than returning the hourly cached report.', 'wp-custom-seo' ),
						),
					),
				),
				'execute_callback'    => array( self::class, 'audit' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			),
			'search-performance' => array(
				'category'            => self::CATEGORY,
				'label'               => __( 'Read what Google reports for a page', 'wp-custom-seo' ),
				'description'         => __( 'Returns the search queries Google reports for one page, with clicks, impressions and average position. Requires Search Console to be connected; returns nothing rather than estimating when it is not.', 'wp-custom-seo' ),
				'input_schema'        => $post_input,
				'execute_callback'    => array( self::class, 'performance' ),
				'permission_callback' => array( self::class, 'can_edit' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'show_in_rest' => true,
				),
			),
		);

		/**
		 * Filters the abilities this plugin registers.
		 *
		 * Remove an entry to withhold it — useful for a site that does not want
		 * an agent able to write SEO fields.
		 *
		 * @param array<string, array<string, mixed>> $definitions Ability definitions, keyed by unprefixed name.
		 */
		return (array) apply_filters( 'wpcseo_abilities', $definitions );
	}

	/**
	 * Read the post id out of ability input.
	 *
	 * @param mixed $input Ability input.
	 */
	private static function post_id( mixed $input ): int {
		return is_array( $input ) ? absint( $input['post_id'] ?? 0 ) : 0;
	}

	/**
	 * Whether the current user may edit the post the input names.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return bool|WP_Error
	 */
	public static function can_edit( mixed $input = null ): bool|WP_Error {
		$post_id = self::post_id( $input );

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error(
				'wpcseo_ability_no_post',
				__( 'That post does not exist.', 'wp-custom-seo' )
			);
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Whether the current user may see site-wide SEO data.
	 *
	 * @param mixed $input Ability input.
	 */
	public static function can_manage( mixed $input = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- permission callbacks share one signature; this one needs no input.
		return Capabilities::can_manage();
	}

	/**
	 * Run the on-page analysis.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array<string, mixed>
	 */
	public static function analyze( mixed $input = null ): array {
		$result = Analyzer::analyze( Meta::analysis_input( self::post_id( $input ) ) );

		$result['note'] = __( 'This score is this plugin\'s own checklist of on-page practices. It is not a search engine ranking and does not predict one.', 'wp-custom-seo' );

		return $result;
	}

	/**
	 * Read a page's SEO fields.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_meta( mixed $input = null ): array {
		$post_id = self::post_id( $input );
		$stored  = array();

		foreach ( array_keys( Meta::keys() ) as $key ) {
			// Trim the storage prefix so the shape an agent sees is readable
			// rather than a list of database keys.
			$stored[ substr( $key, strlen( '_wpcseo_' ) ) ] = Meta::get( $post_id, $key );
		}

		$effective = Meta::analysis_input( $post_id );

		return array(
			'post_id'   => $post_id,
			'url'       => (string) get_permalink( $post_id ),
			'stored'    => $stored,
			// What the page is judged by once fallbacks apply, which is not
			// always what is stored — an empty SEO title means the post title.
			'effective' => array(
				'title'       => $effective['title'],
				'description' => '' !== $effective['description']
					? $effective['description']
					: Frontend::fallback_description( $post_id ),
			),
		);
	}

	/**
	 * Write a page's SEO fields.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update_meta( mixed $input = null ): array|WP_Error {
		$post_id = self::post_id( $input );
		$input   = is_array( $input ) ? $input : array();

		$writable = array(
			'title'       => Meta::TITLE,
			'description' => Meta::DESCRIPTION,
			'keyword'     => Meta::FOCUS_KEYWORD,
		);

		$changed  = array();
		$rejected = array();

		foreach ( $writable as $field => $key ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}

			$raw   = (string) $input[ $field ];
			$clean = call_user_func( Meta::keys()[ $key ]['sanitize'], $raw );

			if ( '' !== trim( $raw ) && '' === (string) $clean ) {
				$rejected[] = $field;

				continue;
			}

			if ( '' === (string) $clean ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $clean );
			}

			$changed[ $field ] = $clean;
		}

		if ( ! $changed && ! $rejected ) {
			return new WP_Error(
				'wpcseo_ability_nothing_to_do',
				__( 'No writable field was supplied. This ability changes the SEO title, meta description or focus keyphrase.', 'wp-custom-seo' )
			);
		}

		return array(
			'post_id'  => $post_id,
			'changed'  => $changed,
			// A value its own validator refused is named rather than silently
			// dropped, so an agent is told what did not happen.
			'rejected' => $rejected,
		);
	}

	/**
	 * Pages this page could link to.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array<string, mixed>
	 */
	public static function link_candidates( mixed $input = null ): array {
		$post_id = self::post_id( $input );

		return array(
			'post_id'    => $post_id,
			'candidates' => Candidates::for_post( $post_id ),
			'note'       => __( 'Every page listed exists on this site and is not already linked to from this one. Nothing here is a suggestion to link — that is a judgement about whether it helps a reader.', 'wp-custom-seo' ),
		);
	}

	/**
	 * The site audit.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array<string, mixed>
	 */
	public static function audit( mixed $input = null ): array {
		$fresh  = is_array( $input ) && ! empty( $input['fresh'] );
		$report = Auditor::report( $fresh );

		$findings = array();

		foreach ( $report['findings'] as $finding ) {
			$findings[] = array(
				'id'     => $finding->id,
				'level'  => $finding->level,
				'title'  => $finding->title,
				'why'    => $finding->why,
				'action' => $finding->action,
				'count'  => $finding->count,
			);
		}

		return array(
			'generated' => $report['generated'],
			'totals'    => $report['totals'],
			'findings'  => $findings,
		);
	}

	/**
	 * What Google reports for a page.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array<string, mixed>
	 */
	public static function performance( mixed $input = null ): array {
		$post_id = self::post_id( $input );
		$post    = get_post( $post_id );

		if ( ! Account::is_connected() || '' === Performance::property() ) {
			return array(
				'available' => false,
				'reason'    => __( 'Search Console is not connected to this site, so there is nothing to report.', 'wp-custom-seo' ),
				'rows'      => array(),
			);
		}

		if ( 'publish' !== $post->post_status ) {
			return array(
				'available' => false,
				'reason'    => __( 'This content is not published, so Google has nothing to report about it.', 'wp-custom-seo' ),
				'rows'      => array(),
			);
		}

		$rows = Performance::for_url( (string) get_permalink( $post ) );

		if ( $rows instanceof WP_Error ) {
			return array(
				'available' => false,
				'reason'    => $rows->get_error_message(),
				'rows'      => array(),
			);
		}

		return array(
			'available' => true,
			'rows'      => $rows,
			'note'      => __( 'Reported by Google, not estimated by this plugin. The list is the top queries only.', 'wp-custom-seo' ),
		);
	}
}
