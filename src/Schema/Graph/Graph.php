<?php
/**
 * Connected schema graph.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Schema\Graph;

defined( 'ABSPATH' ) || exit;

/**
 * Collects schema nodes into one `@graph` with de-duplicated identifiers.
 *
 * A graph is used rather than separate JSON-LD blocks so that entities are
 * stated once and referenced by `@id` everywhere else, which is what lets a
 * consumer resolve author, publisher and page into a single description of
 * the site.
 */
final class Graph {

	public const CONTEXT = 'https://schema.org';

	/**
	 * Nodes keyed by identifier.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $nodes = array();

	/**
	 * Add a node. A node whose `@id` is already present is merged into the
	 * existing one rather than duplicated.
	 *
	 * @param array<string, mixed>|null $node Node, or null to skip.
	 */
	public function add( ?array $node ): void {
		if ( null === $node || ! isset( $node['@id'] ) ) {
			return;
		}

		$id = (string) $node['@id'];

		$this->nodes[ $id ] = isset( $this->nodes[ $id ] )
			? array_merge( $this->nodes[ $id ], $node )
			: $node;
	}

	/**
	 * Add several nodes.
	 *
	 * @param array<int, array<string, mixed>|null> $nodes Nodes.
	 */
	public function add_all( array $nodes ): void {
		foreach ( $nodes as $node ) {
			$this->add( $node );
		}
	}

	/**
	 * Whether a node with this identifier exists.
	 *
	 * @param string $id Node identifier.
	 */
	public function has( string $id ): bool {
		return isset( $this->nodes[ $id ] );
	}

	/**
	 * The nodes, without the `@context` wrapper.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function nodes(): array {
		return array_values( $this->nodes );
	}

	/**
	 * The complete JSON-LD document.
	 *
	 * @return array{@context: string, @graph: array<int, array<string, mixed>>}
	 */
	public function to_array(): array {
		return array(
			'@context' => self::CONTEXT,
			'@graph'   => $this->nodes(),
		);
	}

	/**
	 * Encode for output.
	 *
	 * Slashes are left escaped so the payload can never terminate the script
	 * element that carries it.
	 */
	public function to_json(): string {
		$json = wp_json_encode( $this->to_array(), JSON_UNESCAPED_UNICODE );

		return false === $json ? '' : $json;
	}
}
