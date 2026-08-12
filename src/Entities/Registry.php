<?php
/**
 * Entity registry: stable identifiers and the site's core entities.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Entities;

use WPCustomSeo\Core\Settings;
use WP_Post;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the entities a site is made of, each with a stable @id.
 *
 * Identifiers are derived from the site URL rather than stored, so they stay
 * consistent across requests without a table to keep in sync. Every builder
 * returns null when the underlying information does not actually exist, which
 * is what keeps the graph free of invented data.
 */
final class Registry {

	/**
	 * Mint a stable site-scoped identifier.
	 *
	 * @param string ...$parts Path segments, e.g. 'person', '12'.
	 */
	public static function id( string ...$parts ): string {
		return home_url( '/#/schema/' . implode( '/', array_map( 'rawurlencode', $parts ) ) );
	}

	/**
	 * A reference to another node in the graph.
	 *
	 * @param string $id Node identifier.
	 *
	 * @return array{@id: string}
	 */
	public static function reference( string $id ): array {
		return array( '@id' => $id );
	}

	/**
	 * The organization publishing this site.
	 *
	 * @return array<string, mixed>
	 */
	public static function organization(): array {
		$name = trim( (string) Settings::get( 'schema_org_name', '' ) );

		if ( '' === $name ) {
			$name = (string) get_bloginfo( 'name' );
		}

		$node = array(
			'@type' => (string) Settings::get( 'schema_org_type', 'Organization' ),
			'@id'   => self::id( 'organization' ),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);

		$description = trim( (string) Settings::get( 'schema_org_description', '' ) );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$logo = self::image( (string) Settings::get( 'schema_org_logo', '' ), self::id( 'logo' ) );

		if ( null !== $logo ) {
			$node['logo']  = self::reference( $logo['@id'] );
			$node['image'] = self::reference( $logo['@id'] );
		}

		$email = trim( (string) Settings::get( 'schema_org_email', '' ) );

		if ( is_email( $email ) ) {
			$node['email'] = $email;
		}

		$phone = trim( (string) Settings::get( 'schema_org_phone', '' ) );

		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}

		$same_as = self::urls( (string) Settings::get( 'schema_org_sameas', '' ) );

		if ( $same_as ) {
			$node['sameAs'] = $same_as;
		}

		// Trust properties are emitted only when the site actually publishes
		// the corresponding page. Never invent a policy.
		$policies = array(
			'publishingPrinciples'     => 'schema_org_publishing_principles',
			'ownershipFundingInfo'     => 'schema_org_ownership',
			'correctionsPolicy'        => 'schema_org_corrections',
			'actionableFeedbackPolicy' => 'schema_org_feedback',
			'diversityPolicy'          => 'schema_org_diversity',
		);

		foreach ( $policies as $property => $setting ) {
			$url = trim( (string) Settings::get( $setting, '' ) );

			if ( '' !== $url && self::is_url( $url ) ) {
				$node[ $property ] = $url;
			}
		}

		/**
		 * Filters the organization entity.
		 *
		 * @param array $node Organization node.
		 */
		return (array) apply_filters( 'wpcseo_entity_organization', $node );
	}

	/**
	 * The brand this site represents, or null when none is configured.
	 *
	 * A brand is only stated when it has a name. Deriving one from the site
	 * title would assert a relationship the administrator never claimed.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function brand(): ?array {
		$name = trim( (string) Settings::get( 'brand_name', '' ) );

		if ( '' === $name ) {
			return null;
		}

		$node = array(
			'@type' => 'Brand',
			'@id'   => self::id( 'brand' ),
			'name'  => $name,
		);

		$description = trim( (string) Settings::get( 'brand_description', '' ) );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		$logo = self::image( (string) Settings::get( 'brand_logo', '' ), self::id( 'brand', 'logo' ) );

		if ( null !== $logo ) {
			$node['logo'] = $logo;
		}

		$same_as = self::urls( (string) Settings::get( 'brand_sameas', '' ) );

		if ( $same_as ) {
			$node['sameAs'] = $same_as;
		}

		// The organization publishes the brand; stated as a reference so the
		// two entities stay distinct rather than being conflated.
		$node['parentOrganization'] = self::reference( self::id( 'organization' ) );

		/**
		 * Filters the brand entity.
		 *
		 * @param array $node Brand node.
		 */
		return (array) apply_filters( 'wpcseo_entity_brand', $node );
	}

	/**
	 * The website entity.
	 *
	 * @return array<string, mixed>
	 */
	public static function website(): array {
		$node = array(
			'@type'      => 'WebSite',
			'@id'        => self::id( 'website' ),
			'url'        => home_url( '/' ),
			'name'       => (string) get_bloginfo( 'name' ),
			'publisher'  => self::reference( self::id( 'organization' ) ),
			'inLanguage' => self::language(),
		);

		$tagline = trim( (string) get_bloginfo( 'description' ) );

		if ( '' !== $tagline ) {
			$node['description'] = $tagline;
		}

		/**
		 * Filters the website entity.
		 *
		 * @param array $node WebSite node.
		 */
		return (array) apply_filters( 'wpcseo_entity_website', $node );
	}

	/**
	 * A person entity built from a WordPress user.
	 *
	 * Returns null when the user does not exist. Nothing beyond what the user
	 * has actually filled in is emitted; expertise is never inferred.
	 *
	 * @param int $user_id User id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function person( int $user_id ): ?array {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return null;
		}

		$node = array(
			'@type' => 'Person',
			'@id'   => self::id( 'person', (string) $user_id ),
			'name'  => $user->display_name,
		);

		$bio = trim( (string) $user->description );

		if ( '' !== $bio ) {
			$node['description'] = $bio;
		}

		if ( self::is_url( (string) $user->user_url ) ) {
			$node['url'] = $user->user_url;
		}

		$job_title = trim( (string) get_user_meta( $user_id, Authors::JOB_TITLE, true ) );

		if ( '' !== $job_title ) {
			$node['jobTitle'] = $job_title;
		}

		$same_as = self::urls( (string) get_user_meta( $user_id, Authors::SAME_AS, true ) );

		if ( $same_as ) {
			$node['sameAs'] = $same_as;
		}

		$avatar = get_avatar_url( $user_id, array( 'size' => 192 ) );

		if ( is_string( $avatar ) && self::is_url( $avatar ) ) {
			$node['image'] = array(
				'@type' => 'ImageObject',
				'@id'   => self::id( 'person', (string) $user_id, 'image' ),
				'url'   => $avatar,
			);
		}

		/**
		 * Filters a person entity.
		 *
		 * @param array $node    Person node.
		 * @param int   $user_id User id.
		 */
		return (array) apply_filters( 'wpcseo_entity_person', $node, $user_id );
	}

	/**
	 * An ImageObject built from a URL, or null when there is no usable image.
	 *
	 * @param string $url Image URL.
	 * @param string $id  Identifier for the node.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function image( string $url, string $id ): ?array {
		$url = trim( $url );

		if ( '' === $url || ! self::is_url( $url ) ) {
			return null;
		}

		$node = array(
			'@type' => 'ImageObject',
			'@id'   => $id,
			'url'   => $url,
		);

		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id > 0 ) {
			$meta = wp_get_attachment_metadata( $attachment_id );

			if ( isset( $meta['width'], $meta['height'] ) ) {
				$node['width']  = (int) $meta['width'];
				$node['height'] = (int) $meta['height'];
			}

			$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

			if ( '' !== $alt ) {
				$node['caption'] = $alt;
			}
		}

		return $node;
	}

	/**
	 * The featured image of a post as an ImageObject, or null.
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function featured_image( WP_Post $post ): ?array {
		$thumbnail_id = (int) get_post_thumbnail_id( $post );

		if ( $thumbnail_id <= 0 ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $thumbnail_id, 'full' );

		if ( ! is_string( $url ) ) {
			return null;
		}

		return self::image( $url, get_permalink( $post ) . '#primaryimage' );
	}

	/**
	 * Site language as a BCP 47 tag.
	 */
	public static function language(): string {
		return str_replace( '_', '-', (string) get_locale() );
	}

	/**
	 * Split a newline-separated list into validated absolute URLs.
	 *
	 * @param string $value Raw setting value.
	 *
	 * @return string[]
	 */
	public static function urls( string $value ): array {
		$urls = array();

		$lines = preg_split( '/\R/', $value );

		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = trim( (string) $line );

			if ( '' !== $line && self::is_url( $line ) ) {
				$urls[] = $line;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Whether a string is an absolute http(s) URL.
	 *
	 * @param string $url Candidate URL.
	 */
	public static function is_url( string $url ): bool {
		return (bool) filter_var( $url, FILTER_VALIDATE_URL )
			&& in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true );
	}
}
