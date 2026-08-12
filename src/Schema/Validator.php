<?php
/**
 * Schema graph validation.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema;

use WPCustomSeo\Schema\Graph\Graph;

defined( 'ABSPATH' ) || exit;

/**
 * Checks a graph before it is published.
 *
 * This validates structure and internal consistency: that identifiers are
 * unique, that references resolve, that URLs are absolute and that types
 * carry the properties consumers expect. It cannot verify eligibility for any
 * particular search feature, and does not claim to.
 */
final class Validator {

	public const ERROR = 'error';

	public const WARNING = 'warning';

	public const NOTICE = 'notice';

	/**
	 * Properties each type should carry to be usable.
	 *
	 * @var array<string, string[]>
	 */
	private const EXPECTED = array(
		'Article'      => array( 'headline', 'datePublished', 'author', 'publisher' ),
		'BlogPosting'  => array( 'headline', 'datePublished', 'author', 'publisher' ),
		'NewsArticle'  => array( 'headline', 'datePublished', 'author', 'publisher' ),
		'WebPage'      => array( 'url', 'name' ),
		'WebSite'      => array( 'url', 'name' ),
		'Organization' => array( 'name', 'url' ),
		'Person'       => array( 'name' ),
		'ImageObject'  => array( 'url' ),
	);

	/**
	 * Properties whose values must be absolute URLs.
	 *
	 * @var string[]
	 */
	private const URL_PROPERTIES = array( 'url', 'logo', 'image', 'sameAs', 'publishingPrinciples', 'ownershipFundingInfo', 'correctionsPolicy', 'actionableFeedbackPolicy', 'diversityPolicy' );

	/**
	 * Validate a graph.
	 *
	 * @param Graph $graph Graph to check.
	 *
	 * @return array<int, array{level: string, node: string, message: string}>
	 */
	public static function validate( Graph $graph ): array {
		$issues = self::validate_nodes( $graph->nodes() );

		/**
		 * Filters schema validation issues.
		 *
		 * @param array $issues Issues found.
		 * @param Graph $graph  Graph validated.
		 */
		return (array) apply_filters( 'wpcseo_schema_validation', $issues, $graph );
	}

	/**
	 * Validate raw graph nodes.
	 *
	 * @param array<int, array<string, mixed>> $nodes Nodes.
	 *
	 * @return array<int, array{level: string, node: string, message: string}>
	 */
	public static function validate_nodes( array $nodes ): array {
		$issues = array();

		if ( ! $nodes ) {
			return array(
				array(
					'level'   => self::NOTICE,
					'node'    => '',
					'message' => __( 'The graph is empty. No structured data will be output for this page.', 'wp-custom-seo' ),
				),
			);
		}

		if ( false === wp_json_encode( $nodes ) ) {
			$issues[] = array(
				'level'   => self::ERROR,
				'node'    => '',
				'message' => __( 'The graph could not be encoded as JSON. It will not be output.', 'wp-custom-seo' ),
			);
		}

		$seen = array();

		foreach ( $nodes as $node ) {
			$id   = (string) ( $node['@id'] ?? '' );
			$type = $node['@type'] ?? '';
			$type = is_array( $type ) ? (string) reset( $type ) : (string) $type;

			if ( '' === $id ) {
				$issues[] = array(
					'level'   => self::ERROR,
					'node'    => $type,
					'message' => __( 'A node has no @id, so nothing else can reference it.', 'wp-custom-seo' ),
				);
				continue;
			}

			if ( isset( $seen[ $id ] ) ) {
				$issues[] = array(
					'level'   => self::ERROR,
					'node'    => $id,
					/* translators: %s: node identifier. */
					'message' => sprintf( __( 'Duplicate @id "%s". Two nodes claim to be the same entity.', 'wp-custom-seo' ), $id ),
				);
			}

			$seen[ $id ] = true;

			if ( '' === $type ) {
				$issues[] = array(
					'level'   => self::ERROR,
					'node'    => $id,
					'message' => __( 'Node has no @type.', 'wp-custom-seo' ),
				);
			}

			$issues = array_merge(
				$issues,
				self::check_expected( $id, $type, $node ),
				self::check_urls( $id, $node )
			);
		}

		return array_merge( $issues, self::check_references( $nodes, $seen ) );
	}

	/**
	 * Whether a set of issues contains a blocking error.
	 *
	 * @param array<int, array{level: string, node: string, message: string}> $issues Issues.
	 */
	public static function has_errors( array $issues ): bool {
		foreach ( $issues as $issue ) {
			if ( self::ERROR === $issue['level'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Report properties a type is expected to carry.
	 *
	 * @param string               $id   Node identifier.
	 * @param string               $type Node type.
	 * @param array<string, mixed> $node Node.
	 *
	 * @return array<int, array{level: string, node: string, message: string}>
	 */
	private static function check_expected( string $id, string $type, array $node ): array {
		$issues = array();

		foreach ( self::EXPECTED[ $type ] ?? array() as $property ) {
			if ( ! isset( $node[ $property ] ) || '' === $node[ $property ] ) {
				$issues[] = array(
					'level'   => self::WARNING,
					'node'    => $id,
					'message' => sprintf(
						/* translators: 1: schema type, 2: property name. */
						__( '%1$s is missing "%2$s". Consumers may ignore the node without it.', 'wp-custom-seo' ),
						$type,
						$property
					),
				);
			}
		}

		return $issues;
	}

	/**
	 * Report values that should be URLs but are not.
	 *
	 * @param string               $id   Node identifier.
	 * @param array<string, mixed> $node Node.
	 *
	 * @return array<int, array{level: string, node: string, message: string}>
	 */
	private static function check_urls( string $id, array $node ): array {
		$issues = array();

		foreach ( self::URL_PROPERTIES as $property ) {
			if ( ! isset( $node[ $property ] ) ) {
				continue;
			}

			$value = $node[ $property ];

			// A nested node or a reference is an object, not a URL, and is
			// checked elsewhere. Only a bare string or a list of them is a URL.
			if ( is_array( $value ) && ! array_is_list( $value ) ) {
				continue;
			}

			foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}

				if ( ! \WPCustomSeo\Entities\Registry::is_url( $item ) ) {
					$issues[] = array(
						'level'   => self::ERROR,
						'node'    => $id,
						'message' => sprintf(
							/* translators: 1: property name, 2: offending value. */
							__( '"%1$s" must be an absolute http(s) URL. Found: %2$s', 'wp-custom-seo' ),
							$property,
							$item
						),
					);
				}
			}
		}

		return $issues;
	}

	/**
	 * Report `@id` references that do not resolve to a node in the graph.
	 *
	 * @param array<int, array<string, mixed>> $nodes Graph nodes.
	 * @param array<string, bool>              $seen  Known identifiers.
	 *
	 * @return array<int, array{level: string, node: string, message: string}>
	 */
	private static function check_references( array $nodes, array $seen ): array {
		$issues = array();

		foreach ( $nodes as $node ) {
			$id = (string) ( $node['@id'] ?? '' );

			foreach ( $node as $property => $value ) {
				if ( '@id' === $property ) {
					continue;
				}

				foreach ( self::references( $value ) as $reference ) {
					if ( isset( $seen[ $reference ] ) ) {
						continue;
					}

					$issues[] = array(
						'level'   => self::WARNING,
						'node'    => $id,
						'message' => sprintf(
							/* translators: 1: property name, 2: unresolved identifier. */
							__( '"%1$s" points at %2$s, which is not in the graph.', 'wp-custom-seo' ),
							(string) $property,
							$reference
						),
					);
				}
			}
		}

		return $issues;
	}

	/**
	 * Collect `@id` references from a property value.
	 *
	 * @param mixed $value Property value.
	 *
	 * @return string[]
	 */
	private static function references( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		// A bare {"@id": "..."} with nothing else is a reference, not a node.
		if ( isset( $value['@id'] ) && 1 === count( $value ) ) {
			return array( (string) $value['@id'] );
		}

		$found = array();

		foreach ( $value as $item ) {
			$found = array_merge( $found, self::references( $item ) );
		}

		return $found;
	}
}
