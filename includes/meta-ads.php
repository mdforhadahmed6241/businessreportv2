<?php
/**
 * Meta Ads Reporting Functionality for Business Report Plugin
 *
 * @package BusinessReport
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include the List Table classes.
require_once plugin_dir_path( __FILE__ ) . 'classes/class-br-meta-summary-list-table.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-br-meta-campaign-list-table.php';


/**
 * =================================================================================
 * 1. ADMIN SUBMENU PAGE & ASSETS (Unchanged)
 * =================================================================================
 */

function br_meta_ads_admin_submenu() {
	add_submenu_page( 'business-report', __( 'Meta Ads', 'business-report' ), __( 'Meta Ads', 'business-report' ), 'manage_woocommerce', 'br-meta-ads', 'br_meta_ads_page_html' );
}
add_action( 'admin_menu', 'br_meta_ads_admin_submenu' );

function br_meta_ads_admin_enqueue_scripts( $hook ) {
	if ( 'business-report_page_br-meta-ads' !== $hook ) { return; }
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.css');
	wp_enqueue_script( 'br-meta-ads-admin-js', plugin_dir_url( __FILE__ ) . '../assets/js/admin-meta-ads.js', [ 'jquery', 'jquery-ui-datepicker' ], '1.4.1', true );
	wp_localize_script( 'br-meta-ads-admin-js', 'br_meta_ads_ajax', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'br_meta_ads_nonce' ) ] );
}
add_action( 'admin_enqueue_scripts', 'br_meta_ads_admin_enqueue_scripts' );


/**
 * =================================================================================
 * 2. DATABASE & HELPER FUNCTIONS (Unchanged)
 * =================================================================================
 */

function br_get_meta_accounts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'br_meta_ad_accounts';
    return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
}

function br_get_meta_account( $id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'br_meta_ad_accounts';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
}

function br_get_date_range( $range = 'last_30_days', $start = null, $end = null ) {
    $tz = new DateTimeZone( 'Asia/Dhaka' );
    if ($start && $end) { return [ 'start' => $start, 'end' => $end ]; }
    $end_date = new DateTime( 'now', $tz );
    $start_date = new DateTime( 'now', $tz );
    switch ($range) {
        case 'today': break;
        case 'yesterday': $start_date->modify('-1 day'); $end_date->modify('-1 day'); break;
        case 'this_month': $start_date->modify('first day of this month'); break;
        case 'this_year': $start_date->modify('first day of january this year'); break;
        case 'last_year': $start_date->modify('first day of january last year'); $end_date->modify('last day of december last year'); break;
        case 'lifetime': return [ 'start' => '1970-01-01', 'end' => $end_date->format('Y-m-d') ];
        case 'last_7_days': $start_date->modify('-6 days'); break;
        case 'last_30_days': default: $start_date->modify('-29 days'); break;
    }
    return [ 'start' => $start_date->format('Y-m-d'), 'end'   => $end_date->format('Y-m-d') ];
}


/**
 * =================================================================================
 * 3. PLACEHOLDER API LOGIC (UPDATED)
 * =================================================================================
 */
function br_test_meta_api_connection( $credentials ) { return ( ! empty( $credentials->access_token ) && ! empty( $credentials->ad_account_id ) ); }

/**
 * UPDATED: Placeholder function now returns the exact data from your screenshot.
 */
function br_fetch_meta_api_summary_data( $account, $date ) {
    // DEVELOPER NOTE: This placeholder function should be replaced with your actual Meta Marketing API SDK call.
    
    // This static map contains the real data you provided.
    $real_data_map = [
        '2025-09-30' => 10,
        '2025-09-29' => 25,
        '2025-09-28' => 27,
        '2025-09-27' => 31,
        '2025-09-26' => 36,
        '2025-09-25' => 61,
        '2025-09-24' => 40,
        '2025-09-23' => 22,
    ];

    $spend = isset($real_data_map[$date]) ? $real_data_map[$date] : 0; // Use real data if available, otherwise 0.
    
    return [
        'spend_usd' => $spend,
        'purchases' => floor($spend / 10), // Example purchase calculation
    ];
}


/**
 * =================================================================================
 * 4. AJAX HANDLERS (Unchanged)
 * =================================================================================
 */
function br_ajax_save_meta_account() {
    check_ajax_referer( 'br_meta_ads_nonce', 'nonce' ); if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
    global $wpdb; $table_name = $wpdb->prefix . 'br_meta_ad_accounts';
    $data = [ 'account_name' => sanitize_text_field( $_POST['account_name'] ), 'app_id' => sanitize_text_field( $_POST['app_id'] ), 'app_secret' => wp_unslash( $_POST['app_secret'] ), 'access_token' => wp_unslash( $_POST['access_token'] ), 'ad_account_id' => sanitize_text_field( $_POST['ad_account_id'] ), 'usd_to_bdt_rate' => floatval( $_POST['usd_to_bdt_rate'] ), 'is_active' => isset( $_POST['is_active'] ) && $_POST['is_active'] === 'true' ? 1 : 0 ];
    if ( empty($data['account_name']) || empty($data['app_id']) ) { wp_send_json_error( [ 'message' => 'All fields are required.' ] ); }
    $account_id = isset( $_POST['account_id'] ) ? intval( $_POST['account_id'] ) : 0;
    if ( $account_id > 0 ) { $result = $wpdb->update( $table_name, $data, [ 'id' => $account_id ] ); } else { $result = $wpdb->insert( $table_name, $data ); }
    if ( $result === false ) { wp_send_json_error( [ 'message' => 'Database error: ' . $wpdb->last_error ] ); } else { wp_send_json_success( [ 'message' => 'Account saved successfully!' ] ); }
}
add_action( 'wp_ajax_br_save_meta_account', 'br_ajax_save_meta_account' );
function br_ajax_get_meta_account_details() {
    check_ajax_referer( 'br_meta_ads_nonce', 'nonce' ); if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
    $account = br_get_meta_account( intval( $_POST['account_id'] ) );
    if ($account) { wp_send_json_success( $account ); } else { wp_send_json_error( [ 'message' => 'Account not found.' ] ); }
}
add_action( 'wp_ajax_br_get_meta_account_details', 'br_ajax_get_meta_account_details' );
function br_ajax_delete_meta_account() {
    check_ajax_referer( 'br_meta_ads_nonce', 'nonce' ); if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
    global $wpdb; $result = $wpdb->delete( $wpdb->prefix . 'br_meta_ad_accounts', [ 'id' => intval( $_POST['account_id'] ) ] );
    if ($result) { wp_send_json_success( [ 'message' => 'Account deleted.' ] ); } else { wp_send_json_error( [ 'message' => 'Could not delete account.' ] ); }
}
add_action( 'wp_ajax_br_delete_meta_account', 'br_ajax_delete_meta_account' );
function br_ajax_toggle_account_status() {
    check_ajax_referer( 'br_meta_ads_nonce', 'nonce' ); if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
    global $wpdb; $result = $wpdb->update( $wpdb->prefix . 'br_meta_ad_accounts', [ 'is_active' => $_POST['is_active'] === 'true' ? 1 : 0 ], [ 'id' => intval( $_POST['account_id'] ) ] );
    if ($result !== false) { wp_send_json_success( [ 'message' => 'Status updated.' ] ); } else { wp_send_json_error( [ 'message' => 'Failed to update status.' ] ); }
}
add_action( 'wp_ajax_br_toggle_account_status', 'br_ajax_toggle_account_status' );
function br_ajax_test_meta_connection() {
	check_ajax_referer('br_meta_ads_nonce', 'nonce'); if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
    $account = br_get_meta_account( intval( $_POST['account_id'] ) );
    if (!$account) { wp_send_json_error(['message' => 'Account not found.']); }
    if (br_test_meta_api_connection($account)) { wp_send_json_success(['message' => 'Connection successful!']); } else { wp_send_json_error(['message' => 'Connection failed. Check credentials.']); }
}
add_action('wp_ajax_br_test_meta_connection', 'br_ajax_test_meta_connection');
function br_ajax_sync_meta_data() {
    check_ajax_referer('br_meta_ads_nonce', 'nonce'); if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( [ 'message' => 'Permission denied.' ] ); }
    $tz = new DateTimeZone('Asia/Dhaka');
    $start_date_str = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : (new DateTime('yesterday', $tz))->format('Y-m-d');
    $end_date_str = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : $start_date_str;
    $account_ids = isset($_POST['account_ids']) ? (array) $_POST['account_ids'] : [];
    $accounts_to_sync = [];
    if ( empty($account_ids) ) { $accounts_to_sync = array_filter(br_get_meta_accounts(), fn($acc) => $acc->is_active); } 
    else { foreach($account_ids as $id) { if ($account = br_get_meta_account(intval($id))) { $accounts_to_sync[] = $account; } } }
    if (empty($accounts_to_sync)) { wp_send_json_error(['message' => 'No active or selected ad accounts found to sync.']); }
    global $wpdb; $summary_table = $wpdb->prefix . 'br_meta_ad_summary'; $synced_count = 0;
    $start = new DateTime($start_date_str); $end = new DateTime($end_date_str); $end->modify('+1 day');
    $interval = new DateInterval('P1D'); $date_range = new DatePeriod($start, $interval, $end);
    foreach ($date_range as $date) {
        $date_to_sync = $date->format('Y-m-d');
        foreach ($accounts_to_sync as $account) {
            if (!$account || !$account->is_active) continue;
            $api_data = br_fetch_meta_api_summary_data($account, $date_to_sync);
            $wpdb->replace($summary_table, [ 'account_fk_id' => $account->id, 'report_date' => $date_to_sync, 'spend_usd' => $api_data['spend_usd'], 'purchases' => $api_data['purchases'] ]);
            $synced_count++;
        }
    }
    wp_send_json_success(['message' => "Sync complete! $synced_count records updated between $start_date_str and $end_date_str."]);
}
add_action('wp_ajax_br_sync_meta_data', 'br_ajax_sync_meta_data');
function br_ajax_delete_summary_entry() {
    check_ajax_referer('br_meta_ads_nonce', 'nonce'); if ( ! current_user_can('manage_woocommerce') ) { wp_send_json_error(['message' => 'Permission denied.']); }
    global $wpdb; $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    if ($entry_id <= 0) { wp_send_json_error(['message' => 'Invalid entry ID.']); }
    if ($wpdb->delete($wpdb->prefix . 'br_meta_ad_summary', ['id' => $entry_id], ['%d'])) { wp_send_json_success(['message' => 'Entry deleted.']); } else { wp_send_json_error(['message' => 'Could not delete entry.']); }
}
add_action('wp_ajax_br_delete_summary_entry', 'br_ajax_delete_summary_entry');


/**
 * =================================================================================
 * 5. ADMIN PAGE RENDERING (Unchanged)
 * =================================================================================
 */
function br_meta_ads_page_html() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'summary';
	?>
    <div class="wrap br-wrap"><div class="br-header"><h1><?php _e( 'Meta Ads Report', 'business-report' ); ?></h1></div>
        <h2 class="nav-tab-wrapper">
            <a href="?page=br-meta-ads&tab=summary" class="nav-tab <?php echo $active_tab == 'summary' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Summary', 'business-report' ); ?></a>
            <a href="?page=br-meta-ads&tab=campaign" class="nav-tab <?php echo $active_tab == 'campaign' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Campaign', 'business-report' ); ?></a>
            <a href="?page=br-meta-ads&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Settings', 'business-report' ); ?></a>
        </h2>
        <div class="br-page-content"><?php
			switch ( $active_tab ) {
				case 'campaign': br_meta_ads_campaign_tab_html(); break;
				case 'settings': br_meta_ads_settings_tab_html(); break;
				default: br_meta_ads_summary_tab_html(); break;
			}
		?></div>
    </div>
    <?php
    br_meta_ads_custom_sync_modal_html();
    br_meta_ads_account_modal_html();
}
function br_meta_ads_summary_tab_html() {
	global $wpdb;
    $summary_table_name = $wpdb->prefix . 'br_meta_ad_summary'; $accounts_table_name = $wpdb->prefix . 'br_meta_ad_accounts';
    $current_range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : 'last_30_days';
    $start_date_get = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
    $end_date_get = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
    $date_range = br_get_date_range($current_range_key, $start_date_get, $end_date_get);
    $start_date = $date_range['start']; $end_date = $date_range['end'];
    $total_expense_usd = $wpdb->get_var($wpdb->prepare( "SELECT SUM(spend_usd) FROM $summary_table_name WHERE report_date BETWEEN %s AND %s", $start_date, $end_date ));
    $total_expense_bdt = $wpdb->get_var($wpdb->prepare( "SELECT SUM(s.spend_usd * a.usd_to_bdt_rate) FROM $summary_table_name s JOIN $accounts_table_name a ON s.account_fk_id = a.id WHERE s.report_date BETWEEN %s AND %s", $start_date, $end_date ));
    $accounts_count = $wpdb->get_var("SELECT COUNT(id) FROM $accounts_table_name WHERE is_active = 1");
    $filters_main = [ 'today' => 'Today', 'yesterday' => 'Yesterday', 'last_30_days' => '30D' ];
    $filters_dropdown = [ 'this_month' => 'This Month', 'this_year' => 'This Year', 'last_year' => 'Last Year', 'lifetime' => 'Lifetime' ];
	?>
    <div class="br-filters">
        <div class="br-date-filters">
            <?php foreach($filters_main as $key => $label) { echo sprintf('<a href="?page=br-meta-ads&tab=summary&range=%s" class="button %s">%s</a>', $key, ($current_range_key === $key) ? 'active' : '', esc_html($label)); } ?>
            <div class="br-dropdown"><button class="button br-dropdown-toggle">...</button><div class="br-dropdown-menu">
                <?php foreach($filters_dropdown as $key => $label) { echo sprintf('<a href="?page=br-meta-ads&tab=summary&range=%s">%s</a>', $key, esc_html($label)); } ?>
            </div></div>
        </div>
        <div class="br-sync-actions">
            <button id="br-sync-today-btn" class="button"><span class="dashicons dashicons-update"></span> Sync Today</button>
            <button id="br-sync-7-days-btn" class="button"><span class="dashicons dashicons-update"></span> Sync Last 7 Days</button>
            <button id="br-custom-sync-btn" class="button"><span class="dashicons dashicons-calendar-alt"></span> Custom Sync</button>
            <span id="br-sync-spinner" class="spinner"></span><span id="br-sync-feedback" class="last-sync-time"></span>
        </div>
    </div>
    <div class="br-kpi-grid">
        <div class="br-kpi-card"><h4>Total Expense (USD)</h4><p>$<?php echo esc_html( number_format( $total_expense_usd ?? 0, 2 ) ); ?></p></div>
        <div class="br-kpi-card"><h4>Total Expense (BDT)</h4><p>৳<?php echo esc_html( number_format( $total_expense_bdt ?? 0, 2 ) ); ?></p></div>
        <div class="br-kpi-card"><h4>Active Accounts</h4><p><?php echo esc_html( $accounts_count ?? 0 ); ?></p></div>
    </div>
    <div class="br-data-table-wrapper"><h3>Meta Ads Expenses</h3><?php $summary_table = new BR_Meta_Summary_List_Table(); $summary_table->prepare_items(); $summary_table->display(); ?></div><?php
}
function br_meta_ads_campaign_tab_html() { $campaign_table = new BR_Meta_Campaign_List_Table(); $campaign_table->prepare_items(); $campaign_table->display(); }
function br_meta_ads_settings_tab_html() {
	?><div class="br-settings-header"><h3 id="br-settings-title"><?php _e( 'Meta Ads API Accounts', 'business-report' ); ?></h3><button id="br-add-account-btn" class="button br-add-product-btn"><?php _e( '+ Add New Account', 'business-report' ); ?></button></div>
    <p class="settings-section-description"><?php _e( 'Manage your Meta Ads API accounts.', 'business-report' ); ?></p>
    <div id="br-ad-accounts-list"><?php $accounts = br_get_meta_accounts(); if ( empty( $accounts ) ) { echo '<p>No ad accounts have been added yet.</p>'; } else { echo br_render_account_cards($accounts); } ?></div>
	<?php
}
function br_render_account_cards($accounts) {
    ob_start();
    foreach ( $accounts as $account ) { ?>
        <div class="br-ad-account-card" data-account-id="<?php echo esc_attr( $account->id ); ?>">
            <div class="br-card-header"><strong><?php echo esc_html( $account->account_name ); ?></strong><div class="br-card-actions"><a href="#" class="br-edit-account-btn" title="Edit"><span class="dashicons dashicons-edit"></span></a><a href="#" class="br-delete-account-btn" title="Delete"><span class="dashicons dashicons-trash"></span></a></div></div>
            <p class="account-id"><?php echo esc_html( $account->ad_account_id ); ?></p>
            <div class="br-card-row"><span>USD Rate:</span><strong><?php echo esc_html( $account->usd_to_bdt_rate ); ?> BDT</strong></div>
            <div class="br-card-row"><span>Status:</span><span class="br-status-badge <?php echo $account->is_active ? 'active' : 'inactive'; ?>"><?php echo $account->is_active ? 'Active' : 'Inactive'; ?></span></div>
            <div class="br-card-footer"><label class="br-switch"><input type="checkbox" class="br-status-toggle" <?php checked( $account->is_active, 1 ); ?>><span class="br-slider"></span></label><button class="button button-secondary br-test-connection-btn"><span class="dashicons dashicons-admin-links"></span> Test Connection</button></div>
        </div>
    <?php } return ob_get_clean();
}
function br_meta_ads_account_modal_html() {
	?><div id="br-add-account-modal" class="br-modal" style="display: none;"><div class="br-modal-content"><button class="br-modal-close">&times;</button><h3 id="br-modal-title"><?php _e( 'Add Meta Ads Account', 'business-report' ); ?></h3><p><?php _e( 'Enter your Meta Ads API credentials', 'business-report' ); ?></p>
    <form id="br-add-account-form"><input type="hidden" id="account_id" name="account_id" value="">
    <label for="account_name"><?php _e( 'Account Name', 'business-report' ); ?></label><input type="text" id="account_name" name="account_name" required>
    <div class="form-row"><div><label for="app_id"><?php _e( 'App ID', 'business-report' ); ?></label><input type="text" id="app_id" name="app_id" required></div><div><label for="app_secret"><?php _e( 'App Secret', 'business-report' ); ?></label><input type="password" id="app_secret" name="app_secret" required></div></div>
    <label for="access_token"><?php _e( 'Access Token', 'business-report' ); ?></label><input type="password" id="access_token" name="access_token" required>
    <div class="form-row"><div><label for="ad_account_id"><?php _e( 'Ad Account ID', 'business-report' ); ?></label><input type="text" id="ad_account_id" name="ad_account_id" required></div><div><label for="usd_to_bdt_rate"><?php _e( 'USD to BDT Rate', 'business-report' ); ?></label><input type="number" step="0.01" id="usd_to_bdt_rate" name="usd_to_bdt_rate" required></div></div>
    <div class="form-row-flex"><label for="is_active"><?php _e( 'Active Status', 'business-report' ); ?></label><label class="br-switch"><input type="checkbox" id="is_active" name="is_active" checked><span class="br-slider"></span></label></div>
    <div class="form-footer"><div></div><div><button type="button" class="button br-modal-cancel"><?php _e( 'Cancel', 'business-report' ); ?></button><button type="submit" class="button button-primary"><?php _e( 'Save Account', 'business-report' ); ?></button></div></div>
    </form></div></div><?php
}
function br_meta_ads_custom_sync_modal_html() {
    $accounts = br_get_meta_accounts();
    ?><div id="br-custom-sync-modal" class="br-modal" style="display: none;"><div class="br-modal-content">
        <button class="br-modal-close">&times;</button><h3><?php _e( 'Custom Synchronization', 'business-report' ); ?></h3>
        <p><?php _e( 'Sync Meta Ads data for specific accounts and date range', 'business-report' ); ?></p>
        <form id="br-custom-sync-form">
            <div class="form-row">
                <div><label for="sync_start_date"><?php _e('Start Date', 'business-report'); ?></label><input type="text" id="sync_start_date" name="sync_start_date" class="br-datepicker" autocomplete="off" required></div>
                <div><label for="sync_end_date"><?php _e('End Date', 'business-report'); ?></label><input type="text" id="sync_end_date" name="sync_end_date" class="br-datepicker" autocomplete="off" required></div>
            </div>
            <div class="br-account-checklist">
                <label><?php _e('Select Accounts', 'business-report'); ?></label>
                <div class="br-checklist-actions"><button type="button" class="button-link" id="br-select-all-accounts"><?php _e('Select All', 'business-report'); ?></button><button type="button" class="button-link" id="br-deselect-all-accounts"><?php _e('Deselect All', 'business-report'); ?></button></div>
                <div class="br-checklist">
                    <?php foreach($accounts as $account): if ($account->is_active): ?>
                        <label><input type="checkbox" name="account_ids[]" value="<?php echo esc_attr($account->id); ?>" checked> <?php echo esc_html($account->account_name); ?></label>
                    <?php endif; endforeach; ?>
                </div>
            </div>
            <div class="form-footer"><div></div><div>
                <button type="button" class="button br-modal-cancel"><?php _e( 'Cancel', 'business-report' ); ?></button>
                <button type="submit" class="button button-primary"><?php _e( 'Sync Data', 'business-report' ); ?></button>
            </div></div>
        </form>
    </div></div><?php
}

