<?php
/**
 * Plugin Name:       Business Report
 * Plugin URI:        https://example.com/
 * Description:       A comprehensive reporting tool for WooCommerce.
 * Version:           1.2.8
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       business-report
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// **FIX:** Bumping the version number will force browsers to load the new CSS file.
define( 'BR_PLUGIN_VERSION', '1.2.4' );

/**
 * The core plugin class.
 */
final class Business_Report {

	private static $instance;

	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Business_Report ) ) {
			self::$instance = new Business_Report();
			self::$instance->setup_constants();
			self::$instance->hooks();
			self::$instance->includes();
		}
		return self::$instance;
	}

	private function setup_constants() {
		define( 'BR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}

	private function includes() {
		require_once BR_PLUGIN_DIR . 'includes/cogs-management.php';
		require_once BR_PLUGIN_DIR . 'includes/meta-ads.php';
	}

	private function hooks() {
		add_action( 'plugins_loaded', [ $this, 'check_for_updates' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'remove_admin_notices' ] );
	}

    public function remove_admin_notices() {
        if ( ! isset( $_GET['page'] ) || ( strpos( $_GET['page'], 'br-' ) === false && strpos( $_GET['page'], 'business-report' ) === false ) ) {
            return;
        }
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'all_admin_notices' );
    }

	public function admin_menu() {
		add_menu_page( __( 'Business Report', 'business-report' ), __( 'Business Report', 'business-report' ), 'manage_woocommerce', 'business-report', 'br_dashboard_page_html', 'dashicons-chart-bar', 56 );
	}

	public function enqueue_styles( $hook ) {
		if ( strpos( $hook, 'business-report' ) === false && strpos($hook, 'br-') === false ) { return; }
		
        // **FIX:** Use the main plugin version constant for cache busting.
        wp_enqueue_style(
            'br-admin-styles',
            plugin_dir_url( __FILE__ ) . 'assets/css/admin-styles.css',
            [],
            BR_PLUGIN_VERSION
        );
	}

    public function check_for_updates() {
        if ( get_option( 'br_plugin_version' ) != BR_PLUGIN_VERSION ) {
            $this->run_db_install();
            update_option( 'br_plugin_version', BR_PLUGIN_VERSION );
        }
    }

	public function run_db_install() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$cogs_table_name = $wpdb->prefix . 'br_product_cogs';
		$sql_cogs = "CREATE TABLE $cogs_table_name ( id mediumint(9) NOT NULL AUTO_INCREMENT, post_id bigint(20) NOT NULL, cost decimal(10,2) NOT NULL DEFAULT '0.00', last_updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY  (id), UNIQUE KEY post_id (post_id) ) $charset_collate;";
		dbDelta( $sql_cogs );

		$accounts_table = $wpdb->prefix . 'br_meta_ad_accounts';
		$sql_accounts = "CREATE TABLE $accounts_table ( id BIGINT(20) NOT NULL AUTO_INCREMENT, account_name VARCHAR(255) NOT NULL, app_id VARCHAR(255) NOT NULL, app_secret TEXT NOT NULL, access_token TEXT NOT NULL, ad_account_id VARCHAR(255) NOT NULL, usd_to_bdt_rate DECIMAL(10, 4) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (id) ) $charset_collate;";
		dbDelta($sql_accounts);

        $summary_table = $wpdb->prefix . 'br_meta_ad_summary';
        $campaign_table = $wpdb->prefix . 'br_meta_campaign_data';
        
        dbDelta("CREATE TABLE $summary_table ( id BIGINT(20) NOT NULL AUTO_INCREMENT, account_fk_id BIGINT(20) NOT NULL, report_date DATE NOT NULL, spend_usd DECIMAL(10, 2) NOT NULL, purchases INT(11) DEFAULT 0, PRIMARY KEY (id) ) $charset_collate;");
        dbDelta("CREATE TABLE $campaign_table ( id BIGINT(20) NOT NULL AUTO_INCREMENT, campaign_id VARCHAR(255) NOT NULL, campaign_name TEXT NOT NULL, account_fk_id BIGINT(20) NOT NULL, report_date DATE NOT NULL, spend DECIMAL(10, 2) NOT NULL, impressions INT(11) DEFAULT 0, clicks INT(11) DEFAULT 0, purchases INT(11) DEFAULT 0, PRIMARY KEY (id) ) $charset_collate;");

        $wpdb->query("DELETE t1 FROM {$summary_table} t1 INNER JOIN {$summary_table} t2 WHERE t1.id < t2.id AND t1.account_fk_id = t2.account_fk_id AND t1.report_date = t2.report_date;");
        if ( ! $wpdb->get_var("SHOW INDEX FROM $summary_table WHERE Key_name = 'account_date'") ) {
            $wpdb->query( "ALTER TABLE $summary_table ADD UNIQUE KEY `account_date` (`account_fk_id`, `report_date`)" );
        }
        if ( ! $wpdb->get_var("SHOW INDEX FROM $campaign_table WHERE Key_name = 'campaign_date'") ) {
            $wpdb->query( "ALTER TABLE $campaign_table ADD UNIQUE KEY `campaign_date` (`campaign_id`, `report_date`)" );
        }
	}
}

function br_dashboard_page_html() {
	?>
	<div class="wrap br-wrap">
		<h1><?php esc_html_e( 'Business Report Dashboard', 'business-report' ); ?></h1>
		<p><?php esc_html_e( 'The main dashboard will be built here.', 'business-report' ); ?></p>
	</div>
	<?php
}

function business_report_init() {
	return Business_Report::instance();
}
business_report_init();

