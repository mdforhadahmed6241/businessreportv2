<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BR_Meta_Campaign_List_Table extends WP_List_Table {

    public function __construct() {
		parent::__construct( [
			'singular' => 'Campaign',
			'plural'   => 'Campaigns',
			'ajax'     => false,
		] );
	}

    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'campaign'    => 'Campaign',
            'status'      => 'Status',
            'spend'       => 'Spend',
            'purch'       => 'Purch.',
            'cost_purch'  => 'Cost/Purch.',
            'roas'        => 'ROAS',
            'impr'        => 'Impr.',
            'clicks'      => 'Clicks',
            'ctr'         => 'CTR',
        ];
    }

     public function prepare_items() {
        global $wpdb;
        $this->_column_headers = [$this->get_columns(), [], []];
        
        $table_name = $wpdb->prefix . 'br_meta_campaign_data';
        // For now, we'll fetch all data. You can add date filters here later.
        $this->items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY report_date DESC", ARRAY_A);
    }
    
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'campaign':
                return '<strong>' . esc_html($item['campaign_name']) . '</strong><br><small>' . esc_html($item['campaign_id']) . '</small>';
            case 'status':
                // You would need to fetch this status from the API.
                return '<span class="br-status-badge active">Active</span>';
            case 'spend':
                return wc_price($item[$column_name]);
            case 'purch':
            case 'impr':
            case 'clicks':
                return number_format_i18n($item[$column_name]);
            case 'cost_purch':
                 return ($item['purch'] > 0) ? wc_price($item['spend'] / $item['purch']) : 'N/A';
            case 'ctr':
                // You would need 'impressions' to calculate this.
                return ($item['impr'] > 0) ? number_format(($item['clicks'] / $item['impr']) * 100, 2) . '%' : '0.00%';
            case 'roas':
                return 'N/A'; // Needs revenue data
            default:
                return '–';
        }
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="campaign_id[]" value="%s" />', $item['id']);
    }
}

