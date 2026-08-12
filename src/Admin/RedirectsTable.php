<?php
/**
 * Redirect list table.
 *
 * @package WPCustomSeo
 */

declare( strict_types=1 );

namespace WPCustomSeo\Admin;

use WPCustomSeo\Redirects\Redirects;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists redirect rules, using the same table core uses everywhere else.
 */
final class RedirectsTable extends WP_List_Table {

	/**
	 * Set up the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'redirect',
				'plural'   => 'redirects',
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
			'cb'        => '<input type="checkbox" />',
			'source'    => __( 'From', 'wp-custom-seo' ),
			'target'    => __( 'To', 'wp-custom-seo' ),
			'type'      => __( 'Type', 'wp-custom-seo' ),
			'hits'      => __( 'Hits', 'wp-custom-seo' ),
			'last_used' => __( 'Last used', 'wp-custom-seo' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function get_sortable_columns(): array {
		return array(
			'source'    => array( 'source', false ),
			'type'      => array( 'type', false ),
			'hits'      => array( 'hits', true ),
			'last_used' => array( 'last_used', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'enable'  => __( 'Enable', 'wp-custom-seo' ),
			'disable' => __( 'Disable', 'wp-custom-seo' ),
			'delete'  => __( 'Delete', 'wp-custom-seo' ),
		);
	}

	/**
	 * Load rows.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list controls.
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['s'] ) ) : '';
		$paged   = isset( $_REQUEST['paged'] ) ? absint( wp_unslash( $_REQUEST['paged'] ) ) : 1;
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['orderby'] ) ) : 'id';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['order'] ) ) : 'desc';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$total = Redirects::count( $search );

		$this->items = Redirects::all(
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
	 * @param object $item Redirect row.
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Source column, with row actions.
	 *
	 * @param object $item Redirect row.
	 */
	public function column_source( $item ): string {
		$edit = add_query_arg(
			array(
				'page'   => RedirectsPage::SLUG,
				'action' => 'edit',
				'id'     => (int) $item->id,
			),
			admin_url( 'admin.php' )
		);

		$toggle = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => RedirectsPage::SLUG,
					'action' => $item->enabled ? 'disable' : 'enable',
					'id'     => (int) $item->id,
				),
				admin_url( 'admin.php' )
			),
			'wpcseo_redirect_row'
		);

		$delete = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => RedirectsPage::SLUG,
					'action' => 'delete',
					'id'     => (int) $item->id,
				),
				admin_url( 'admin.php' )
			),
			'wpcseo_redirect_row'
		);

		$badges = '';

		if ( $item->is_regex ) {
			$badges .= ' <span class="wpcseo-badge is-off">' . esc_html__( 'Regex', 'wp-custom-seo' ) . '</span>';
		}

		if ( ! $item->enabled ) {
			$badges .= ' <span class="wpcseo-badge is-off">' . esc_html__( 'Disabled', 'wp-custom-seo' ) . '</span>';
		}

		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'wp-custom-seo' ) . '</a>',
			'toggle' => '<a href="' . esc_url( $toggle ) . '">' . ( $item->enabled ? esc_html__( 'Disable', 'wp-custom-seo' ) : esc_html__( 'Enable', 'wp-custom-seo' ) ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete ) . '" class="submitdelete">' . esc_html__( 'Delete', 'wp-custom-seo' ) . '</a>',
		);

		return '<strong>' . esc_html( (string) $item->source ) . '</strong>' . $badges . $this->row_actions( $actions );
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item        Redirect row.
	 * @param string $column_name Column key.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'target':
				return esc_html( (string) $item->target );

			case 'type':
				return esc_html( (string) $item->type );

			case 'hits':
				return esc_html( number_format_i18n( (int) $item->hits ) );

			case 'last_used':
				return $item->last_used
					? esc_html( (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $item->last_used ) )
					: '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Never used', 'wp-custom-seo' ) . '</span>';
		}

		return '';
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		esc_html_e( 'No redirects yet. Add one above, or create one straight from a logged 404.', 'wp-custom-seo' );
	}
}
