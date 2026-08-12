<?php
/**
 * 404 log list table.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Redirects\NotFound;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists logged 404s, each with a one-click route into the redirect form.
 */
final class NotFoundTable extends WP_List_Table {

	/**
	 * Set up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'not_found',
				'plural'   => 'not_founds',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column headings.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'url'        => __( 'URL', 'wp-custom-seo' ),
			'referrer'   => __( 'Referrer', 'wp-custom-seo' ),
			'hits'       => __( 'Hits', 'wp-custom-seo' ),
			'first_seen' => __( 'First seen', 'wp-custom-seo' ),
			'last_seen'  => __( 'Last seen', 'wp-custom-seo' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function get_sortable_columns(): array {
		return array(
			'url'        => array( 'url', false ),
			'hits'       => array( 'hits', true ),
			'first_seen' => array( 'first_seen', true ),
			'last_seen'  => array( 'last_seen', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array( 'delete' => __( 'Delete', 'wp-custom-seo' ) );
	}

	/**
	 * Load rows.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list controls.
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['s'] ) ) : '';
		$paged   = isset( $_REQUEST['paged'] ) ? absint( wp_unslash( $_REQUEST['paged'] ) ) : 1;
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['orderby'] ) ) : 'last_seen';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['order'] ) ) : 'desc';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$total = NotFound::count( $search );

		$this->items = NotFound::all(
			array(
				'search'   => $search,
				'per_page' => $per_page,
				'page'     => max( 1, $paged ),
				'orderby'  => $orderby,
				'order'    => $order,
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Row checkbox.
	 *
	 * @param object $item Log row.
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item->id );
	}

	/**
	 * URL column, with row actions.
	 *
	 * @param object $item Log row.
	 */
	public function column_url( $item ): string {
		$redirect = add_query_arg(
			array(
				'page'   => RedirectsPage::SLUG,
				'action' => 'add',
				'source' => rawurlencode( (string) $item->url ),
			),
			admin_url( 'admin.php' )
		);

		$delete = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => NotFoundPage::SLUG,
					'action' => 'delete',
					'id'     => (int) $item->id,
				),
				admin_url( 'admin.php' )
			),
			'wpcseo_not_found_row'
		);

		$actions = array(
			'redirect' => '<a href="' . esc_url( $redirect ) . '"><strong>' . esc_html__( 'Create redirect', 'wp-custom-seo' ) . '</strong></a>',
			'view'     => '<a href="' . esc_url( home_url( (string) $item->url ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Visit', 'wp-custom-seo' ) . '</a>',
			'delete'   => '<a href="' . esc_url( $delete ) . '" class="submitdelete">' . esc_html__( 'Delete', 'wp-custom-seo' ) . '</a>',
		);

		return '<strong>' . esc_html( (string) $item->url ) . '</strong>' . $this->row_actions( $actions );
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item        Log row.
	 * @param string $column_name Column key.
	 */
	public function column_default( $item, $column_name ): string {
		$dash = '<span aria-hidden="true">—</span>';

		switch ( $column_name ) {
			case 'referrer':
				return '' !== (string) $item->referrer ? esc_html( (string) $item->referrer ) : $dash;

			case 'hits':
				return esc_html( number_format_i18n( (int) $item->hits ) );

			case 'first_seen':
			case 'last_seen':
				$value = (string) $item->{$column_name};

				return '' !== $value
					? esc_html( (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $value ) )
					: $dash;
		}

		return '';
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		esc_html_e( 'No 404s recorded. That is the result you want.', 'wp-custom-seo' );
	}
}
