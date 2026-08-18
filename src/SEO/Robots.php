<?php
/**
 * Robots directive vocabulary and merging.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * The robots directives a page or term can carry, and how they combine.
 *
 * Split out of Frontend because posts are no longer the only thing that has
 * them: a category archive needs the same vocabulary and the same merge rules,
 * and having two copies of "what does nosnippet do to max-snippet" is how the
 * two drift apart.
 *
 * Only directives Google documents are offered. `noarchive`, `nosnippet`,
 * `max-snippet`, `max-image-preview` and `max-video-preview` are all in the
 * published robots meta tag specification; anything invented would be a control
 * that renders a line of text nothing reads.
 *
 * Values are merged into the array `wp_robots` passes around rather than
 * printed, so core still renders the tag and other plugins keep their filter.
 */
final class Robots {

	/**
	 * Directive keys, in the order they are offered.
	 *
	 * Keyed by the short name used in meta and settings; the value is the
	 * directive as it appears in the tag.
	 */
	public const DIRECTIVES = array(
		'noindex'           => 'noindex',
		'nofollow'          => 'nofollow',
		'noarchive'         => 'noarchive',
		'nosnippet'         => 'nosnippet',
		'max_snippet'       => 'max-snippet',
		'max_image_preview' => 'max-image-preview',
		'max_video_preview' => 'max-video-preview',
	);

	/**
	 * Choices for `max-snippet`.
	 *
	 * An empty value means "say nothing", which is not the same as `-1`: the
	 * first leaves the decision to the search engine's default, the second
	 * states out loud that there is no limit.
	 *
	 * @return array<string, string>
	 */
	public static function snippet_options(): array {
		return array(
			''    => __( 'Default — say nothing', 'wp-custom-seo' ),
			'-1'  => __( 'No limit', 'wp-custom-seo' ),
			'0'   => __( 'No text snippet', 'wp-custom-seo' ),
			'20'  => __( 'Up to 20 characters', 'wp-custom-seo' ),
			'50'  => __( 'Up to 50 characters', 'wp-custom-seo' ),
			'100' => __( 'Up to 100 characters', 'wp-custom-seo' ),
			'160' => __( 'Up to 160 characters', 'wp-custom-seo' ),
		);
	}

	/**
	 * Choices for `max-image-preview`.
	 *
	 * @return array<string, string>
	 */
	public static function image_preview_options(): array {
		return array(
			''         => __( 'Default — say nothing', 'wp-custom-seo' ),
			'large'    => __( 'Large', 'wp-custom-seo' ),
			'standard' => __( 'Standard', 'wp-custom-seo' ),
			'none'     => __( 'No image preview', 'wp-custom-seo' ),
		);
	}

	/**
	 * Choices for `max-video-preview`, in seconds.
	 *
	 * @return array<string, string>
	 */
	public static function video_preview_options(): array {
		return array(
			''   => __( 'Default — say nothing', 'wp-custom-seo' ),
			'-1' => __( 'No limit', 'wp-custom-seo' ),
			'0'  => __( 'A still image only', 'wp-custom-seo' ),
			'15' => __( 'Up to 15 seconds', 'wp-custom-seo' ),
			'30' => __( 'Up to 30 seconds', 'wp-custom-seo' ),
		);
	}

	/**
	 * Every select-style directive with its allowed values.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function choices(): array {
		return array(
			'max_snippet'       => self::snippet_options(),
			'max_image_preview' => self::image_preview_options(),
			'max_video_preview' => self::video_preview_options(),
		);
	}

	/**
	 * Restrict a directive value to what that directive actually accepts.
	 *
	 * @param string $directive Short name, e.g. `max_snippet`.
	 * @param mixed  $value     Submitted value.
	 */
	public static function sanitize( string $directive, mixed $value ): string {
		$allowed = self::choices()[ $directive ] ?? array();
		$value   = sanitize_text_field( (string) $value );

		return array_key_exists( $value, $allowed ) ? $value : '';
	}

	/**
	 * Merge stored directives into a `wp_robots` array.
	 *
	 * @param array<string, mixed> $robots Directives keyed by name, as wp_robots passes them.
	 * @param array<string, mixed> $values Stored values keyed by short name.
	 *
	 * @return array<string, mixed>
	 */
	public static function apply( array $robots, array $values ): array {
		if ( ! empty( $values['noindex'] ) ) {
			// `index` and `noindex` in the same tag is a contradiction, and which
			// one wins is undefined. Remove the one being overruled.
			unset( $robots['index'] );
			$robots['noindex'] = true;
		}

		if ( ! empty( $values['nofollow'] ) ) {
			unset( $robots['follow'] );
			$robots['nofollow'] = true;
		}

		if ( ! empty( $values['noarchive'] ) ) {
			$robots['noarchive'] = true;
		}

		if ( ! empty( $values['nosnippet'] ) ) {
			$robots['nosnippet'] = true;
		}

		foreach ( array( 'max_snippet', 'max_image_preview', 'max_video_preview' ) as $key ) {
			$value = trim( (string) ( $values[ $key ] ?? '' ) );

			if ( '' === $value || ! array_key_exists( $value, self::choices()[ $key ] ) ) {
				continue;
			}

			$robots[ self::DIRECTIVES[ $key ] ] = $value;
		}

		// nosnippet already forbids the text snippet outright, so a length limit
		// beside it is a second answer to a question already settled. Emitting
		// both is not an error, but it reads as a mistake to anyone auditing the
		// page, so the redundant one goes.
		if ( ! empty( $robots['nosnippet'] ) ) {
			unset( $robots['max-snippet'] );
		}

		return $robots;
	}
}
