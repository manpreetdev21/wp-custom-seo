<?php
/**
 * AI crawler controls in robots.txt.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Crawlers;

use WPCustomSeo\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a site tell AI crawlers what it does and does not want.
 *
 * **The distinction that matters.** "AI bot" is three different things, and
 * treating them as one is the mistake this screen exists to prevent:
 *
 * - A **training** crawler collects pages that may be used to train a model.
 *   Blocking it removes nothing from any search result.
 * - An **AI search** crawler indexes pages so they can be cited in an
 *   assistant's answers. Blocking it removes the site from those answers — it
 *   is the AI equivalent of noindex, not a privacy setting.
 * - A **user-initiated** fetch happens when a person asks an assistant about a
 *   specific page. Blocking it means that person is told the page cannot be
 *   read.
 *
 * A site owner who blocks "the AI bots" to protect their writing from training
 * usually does not mean to disappear from ChatGPT's citations as well. So the
 * two are never one switch here, and each control says what blocking it costs.
 *
 * **What robots.txt is worth.** It is a request, standardised as RFC 9309 and
 * honoured voluntarily. The operators listed here publish their tokens and say
 * they respect it; a crawler that lies about its user agent is unaffected by
 * anything in this file, and nothing in a plugin can change that. Enforcement
 * needs server or edge rules. Every screen that offers these controls says so.
 */
final class AiCrawlers {

	/**
	 * Settings key prefix, one per token.
	 */
	public const PREFIX = 'block_';

	/**
	 * A crawler that gathers pages to train a model.
	 */
	public const TRAINING = 'training';

	/**
	 * A crawler that indexes pages so an assistant can cite them.
	 */
	public const SEARCH = 'search';

	/**
	 * A fetch made because a person asked about a page.
	 */
	public const USER = 'user';

	/**
	 * The purposes, in the order the screen shows them.
	 *
	 * @return array<string, array{label: string, consequence: string}>
	 */
	public static function purposes(): array {
		return array(
			self::TRAINING => array(
				'label'       => __( 'Model training', 'wp-custom-seo' ),
				'consequence' => __( 'Blocking these asks that your pages are not used to train a model. It does not affect any search result, in Google or in an assistant.', 'wp-custom-seo' ),
			),
			self::SEARCH   => array(
				'label'       => __( 'AI search and citation', 'wp-custom-seo' ),
				'consequence' => __( 'Blocking these removes the site from that assistant’s answers and citations. This is the AI equivalent of noindex — it costs visibility, and is rarely what someone means by “keep my content out of AI”.', 'wp-custom-seo' ),
			),
			self::USER     => array(
				'label'       => __( 'Fetches a person asked for', 'wp-custom-seo' ),
				'consequence' => __( 'Blocking these means someone who pastes one of your URLs into an assistant is told the page cannot be read.', 'wp-custom-seo' ),
			),
		);
	}

	/**
	 * The crawlers this plugin can write rules for.
	 *
	 * Only tokens whose operator documents them publicly and states that they
	 * are honoured. A control that the operator says does not apply would be a
	 * switch that quietly does nothing — OpenAI's `ChatGPT-User` is documented
	 * as not governed by robots.txt, so it is described on the screen but not
	 * offered as a toggle.
	 *
	 * @return array<string, array{agent: string, operator: string, purpose: string, label: string, note: string}>
	 */
	public static function bots(): array {
		$bots = array(
			'gptbot'             => array(
				'agent'    => 'GPTBot',
				'operator' => 'OpenAI',
				'purpose'  => self::TRAINING,
				'label'    => __( 'OpenAI — model training', 'wp-custom-seo' ),
				'note'     => '',
			),
			'claudebot'          => array(
				'agent'    => 'ClaudeBot',
				'operator' => 'Anthropic',
				'purpose'  => self::TRAINING,
				'label'    => __( 'Anthropic — model training', 'wp-custom-seo' ),
				'note'     => '',
			),
			'google-extended'    => array(
				'agent'    => 'Google-Extended',
				'operator' => 'Google',
				'purpose'  => self::TRAINING,
				'label'    => __( 'Google — Gemini training', 'wp-custom-seo' ),
				'note'     => __( 'Not a crawler of its own. Blocking it does not affect Google Search ranking or indexing in any way.', 'wp-custom-seo' ),
			),
			'applebot-extended'  => array(
				'agent'    => 'Applebot-Extended',
				'operator' => 'Apple',
				'purpose'  => self::TRAINING,
				'label'    => __( 'Apple — Apple Intelligence training', 'wp-custom-seo' ),
				'note'     => __( 'Separate from Applebot, which continues to crawl for Siri and Spotlight either way.', 'wp-custom-seo' ),
			),
			'meta-externalagent' => array(
				'agent'    => 'Meta-ExternalAgent',
				'operator' => 'Meta',
				'purpose'  => self::TRAINING,
				'label'    => __( 'Meta — model training', 'wp-custom-seo' ),
				'note'     => '',
			),
			'ccbot'              => array(
				'agent'    => 'CCBot',
				'operator' => 'Common Crawl',
				'purpose'  => self::TRAINING,
				'label'    => __( 'Common Crawl', 'wp-custom-seo' ),
				'note'     => __( 'A public dataset rather than one company. Many models have been trained from it, and researchers and archivists use it too.', 'wp-custom-seo' ),
			),
			'oai-searchbot'      => array(
				'agent'    => 'OAI-SearchBot',
				'operator' => 'OpenAI',
				'purpose'  => self::SEARCH,
				'label'    => __( 'OpenAI — ChatGPT search', 'wp-custom-seo' ),
				'note'     => __( 'Blocking this removes the site from ChatGPT’s search answers.', 'wp-custom-seo' ),
			),
			'claude-searchbot'   => array(
				'agent'    => 'Claude-SearchBot',
				'operator' => 'Anthropic',
				'purpose'  => self::SEARCH,
				'label'    => __( 'Anthropic — Claude search', 'wp-custom-seo' ),
				'note'     => __( 'Blocking this removes the site from Claude’s search answers.', 'wp-custom-seo' ),
			),
			'perplexitybot'      => array(
				'agent'    => 'PerplexityBot',
				'operator' => 'Perplexity',
				'purpose'  => self::SEARCH,
				'label'    => __( 'Perplexity — search index', 'wp-custom-seo' ),
				'note'     => '',
			),
			'claude-user'        => array(
				'agent'    => 'Claude-User',
				'operator' => 'Anthropic',
				'purpose'  => self::USER,
				'label'    => __( 'Anthropic — pages a person asked about', 'wp-custom-seo' ),
				'note'     => '',
			),
			'perplexity-user'    => array(
				'agent'    => 'Perplexity-User',
				'operator' => 'Perplexity',
				'purpose'  => self::USER,
				'label'    => __( 'Perplexity — pages a person asked about', 'wp-custom-seo' ),
				'note'     => '',
			),
		);

		/**
		 * Filters the AI crawlers offered for control.
		 *
		 * Add an entry to write rules for a crawler this plugin does not know
		 * about. Only add tokens the operator documents: a rule for an invented
		 * user agent is a line of text that does nothing.
		 *
		 * @param array<string, array<string, string>> $bots Crawler definitions.
		 */
		return (array) apply_filters( 'wpcseo_ai_crawlers', $bots );
	}

	/**
	 * Hook registration.
	 */
	public static function init(): void {
		add_filter( 'robots_txt', array( self::class, 'append' ), 10, 2 );
	}

	/**
	 * Whether a crawler is set to be blocked.
	 *
	 * @param string $id Crawler id.
	 */
	public static function is_blocked( string $id ): bool {
		return Settings::enabled( self::PREFIX . $id );
	}

	/**
	 * The crawlers currently set to be blocked.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function blocked(): array {
		return array_filter(
			self::bots(),
			static fn ( array $bot, string $id ): bool => self::is_blocked( $id ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * The robots.txt block this plugin contributes, or an empty string.
	 */
	public static function rules(): string {
		$blocked = self::blocked();

		if ( ! $blocked ) {
			return '';
		}

		$lines = array( '', '# ' . __( 'AI crawler preferences, set in WP Custom SEO.', 'wp-custom-seo' ) );

		foreach ( $blocked as $bot ) {
			$lines[] = '';
			$lines[] = 'User-agent: ' . $bot['agent'];
			$lines[] = 'Disallow: /';
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Append the rules to the virtual robots.txt.
	 *
	 * @param string $output Robots.txt contents so far.
	 * @param bool   $is_public Whether the site is set to be indexed.
	 */
	public static function append( string $output, bool $is_public ): string {
		// A site set to discourage search engines already has a blanket
		// disallow above; adding per-agent rules under it would be noise.
		if ( ! $is_public ) {
			return $output;
		}

		return $output . self::rules();
	}

	/**
	 * Whether a real robots.txt file exists in the web root.
	 *
	 * WordPress only serves its own robots.txt when no physical file is there.
	 * If one is, everything here is written and never read — which is worth
	 * saying on the screen rather than leaving someone to wonder why their
	 * choices have no effect.
	 */
	public static function has_physical_file(): bool {
		$root = defined( 'ABSPATH' ) ? ABSPATH : '';

		return '' !== $root && file_exists( $root . 'robots.txt' );
	}

	/**
	 * The settings section describing the controls.
	 *
	 * @return array{title: string, description: string, fields: array<string, array<string, mixed>>}
	 */
	public static function section(): array {
		$purposes = self::purposes();
		$fields   = array();

		foreach ( array_keys( $purposes ) as $purpose ) {
			foreach ( self::bots() as $id => $bot ) {
				if ( $bot['purpose'] !== $purpose ) {
					continue;
				}

				$description = sprintf(
					/* translators: 1: user agent token, 2: what blocking it costs. */
					__( 'Writes "User-agent: %1$s / Disallow: /" into robots.txt. %2$s', 'wp-custom-seo' ),
					$bot['agent'],
					$purposes[ $purpose ]['consequence']
				);

				if ( '' !== $bot['note'] ) {
					$description .= ' ' . $bot['note'];
				}

				$fields[ self::PREFIX . $id ] = array(
					'type'        => 'checkbox',
					'label'       => $purposes[ $purpose ]['label'] . ' — ' . $bot['label'],
					'description' => $description,
					'default'     => false,
				);
			}
		}

		return array(
			'title'       => __( 'AI crawlers', 'wp-custom-seo' ),
			'description' => __( 'These write rules into robots.txt asking named AI crawlers to stay away. Nothing is blocked by default. Read each one before ticking it: asking not to be used for model training and removing yourself from an assistant’s search answers are different decisions, and only the second costs you visibility. robots.txt is a request that well-behaved crawlers honour voluntarily — a crawler that ignores it, or lies about who it is, is unaffected by anything here, and only server or edge rules can stop that.', 'wp-custom-seo' ),
			'fields'      => $fields,
		);
	}
}
