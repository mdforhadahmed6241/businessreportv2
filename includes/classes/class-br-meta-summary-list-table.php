<?php
/**
 * Creates the WP_List_Table for displaying Meta Ads summary data.
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BR_Meta_Summary_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [ 'singular' => 'Ad Spend Entry', 'plural' => 'Ad Spend Entries', 'ajax' => false ] );
	}

	public function get_columns() {
        return [
            'report_date' => 'Date',
            'account_name'=> 'Account',
            'spend_usd'   => 'USD',
            'rate'        => 'Rate',
            'spend_bdt'   => 'BDT',
            'actions'     => 'Actions',
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $this->_column_headers = [$this->get_columns(), [], []];
        $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'last_30_days';
        $date_range = br_get_date_range($current_range_key);
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];
        $summary_table = $wpdb->prefix . 'br_meta_ad_summary';
        $accounts_table = $wpdb->prefix . 'br_meta_ad_accounts';
        $query = $wpdb->prepare( "SELECT s.id, s.report_date, s.spend_usd, a.account_name, a.usd_to_bdt_rate AS rate FROM {$summary_table} s JOIN {$accounts_table} a ON s.account_fk_id = a.id WHERE s.report_date BETWEEN %s AND %s ORDER BY s.report_date DESC", $start_date, $end_date );
        $this->items = $wpdb->get_results($query, ARRAY_A);
    }

    function column_default($item, $column_name) {
        switch ($column_name) {
            case 'report_date':
                return (new DateTime($item['report_date']))->format('M j, Y');
            case 'account_name':
                return esc_html($item['account_name']);
            case 'spend_usd':
                return '$' . number_format($item['spend_usd'], 2);
            case 'rate':
                return number_format($item['rate'], 4);
            case 'spend_bdt':
                return '৳' . number_format($item['spend_usd'] * $item['rate'], 2);
            case 'actions':
                // UPDATED: Added class and data-id attribute to the button.
                return sprintf(
                    '<button class="button-link-delete br-delete-summary-btn" data-id="%d"><span class="dashicons dashicons-trash"></span></button>',
                    $item['id']
                );
            default:
                return '';
        }
    }
}

