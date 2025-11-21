<?php
/**
 * Plugin Name: ChkLinkOut - External Link Crawler
 * Plugin URI: https://github.com/tootranmmo/chklinkout
 * Description: Crawl và liệt kê tất cả external links trong WordPress site với batch processing, broken link checker và monitoring
 * Version: 2.0.0
 * Author: ChkLinkOut Team
 * Author URI: https://github.com/tootranmmo
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chklinkout
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'CHKLINKOUT_VERSION', '2.0.0' );
define( 'CHKLINKOUT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHKLINKOUT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CHKLINKOUT_PLUGIN_FILE', __FILE__ );

// Include required files
require_once CHKLINKOUT_PLUGIN_DIR . 'includes/class-database.php';
require_once CHKLINKOUT_PLUGIN_DIR . 'includes/class-external-link-crawler.php';
require_once CHKLINKOUT_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once CHKLINKOUT_PLUGIN_DIR . 'includes/class-settings.php';
require_once CHKLINKOUT_PLUGIN_DIR . 'includes/class-cron.php';

/**
 * Main plugin class
 */
class ChkLinkOut {

    /**
     * The single instance of the class
     */
    private static $instance = null;

    /**
     * Get the single instance of the class
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Activation/Deactivation hooks
        register_activation_hook( CHKLINKOUT_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( CHKLINKOUT_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Initialize plugin
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

        // AJAX handlers
        add_action( 'wp_ajax_chklinkout_start_scan', array( $this, 'ajax_start_scan' ) );
        add_action( 'wp_ajax_chklinkout_scan_batch', array( $this, 'ajax_scan_batch' ) );
        add_action( 'wp_ajax_chklinkout_complete_scan', array( $this, 'ajax_complete_scan' ) );
        add_action( 'wp_ajax_chklinkout_check_broken_links', array( $this, 'ajax_check_broken_links' ) );
        add_action( 'wp_ajax_chklinkout_get_results', array( $this, 'ajax_get_results' ) );
        add_action( 'wp_ajax_chklinkout_export_csv', array( $this, 'ajax_export_csv' ) );
        add_action( 'wp_ajax_chklinkout_export_json', array( $this, 'ajax_export_json' ) );
        add_action( 'wp_ajax_chklinkout_clear_cache', array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_chklinkout_get_cached_scan', array( $this, 'ajax_get_cached_scan' ) );

        // Initialize cron
        ChkLinkOut_Cron::init();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        ChkLinkOut_Database::create_tables();

        // Set default options
        if ( ! get_option( 'chklinkout_settings' ) ) {
            add_option( 'chklinkout_settings', array(
                'auto_scan_enabled' => false,
                'scan_frequency' => 'weekly',
                'email_notifications' => false,
                'notification_email' => get_option( 'admin_email' ),
                'check_broken_links' => true,
                'cleanup_days' => 30
            ) );
        }

        // Schedule cron if enabled
        $settings = get_option( 'chklinkout_settings' );
        if ( ! empty( $settings['auto_scan_enabled'] ) ) {
            ChkLinkOut_Cron::schedule();
        }

        // Set activation flag for notice
        set_transient( 'chklinkout_activated', true, 30 );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Unschedule cron
        ChkLinkOut_Cron::unschedule();

        // Clear transients
        delete_transient( 'chklinkout_latest_scan' );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain( 'chklinkout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __( 'External Links', 'chklinkout' ),
            __( 'External Links', 'chklinkout' ),
            'manage_options',
            'chklinkout',
            array( 'ChkLinkOut_Admin_Page', 'display' ),
            'dashicons-admin-links',
            30
        );

        // Settings submenu
        add_submenu_page(
            'chklinkout',
            __( 'Settings', 'chklinkout' ),
            __( 'Settings', 'chklinkout' ),
            'manage_options',
            'chklinkout-settings',
            array( 'ChkLinkOut_Settings', 'display' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( ! in_array( $hook, array( 'toplevel_page_chklinkout', 'external-links_page_chklinkout-settings' ) ) ) {
            return;
        }

        wp_enqueue_style(
            'chklinkout-admin',
            CHKLINKOUT_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            CHKLINKOUT_VERSION
        );

        wp_enqueue_script(
            'chklinkout-admin',
            CHKLINKOUT_PLUGIN_URL . 'admin/js/admin-script.js',
            array( 'jquery' ),
            CHKLINKOUT_VERSION,
            true
        );

        wp_localize_script(
            'chklinkout-admin',
            'chklinkoutAjax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'chklinkout_nonce' ),
                'i18n' => array(
                    'scanning' => __( 'Đang quét...', 'chklinkout' ),
                    'checking' => __( 'Đang kiểm tra broken links...', 'chklinkout' ),
                    'completed' => __( 'Hoàn thành!', 'chklinkout' ),
                    'error' => __( 'Có lỗi xảy ra', 'chklinkout' ),
                    'confirm_new_scan' => __( 'Bạn có chắc muốn bắt đầu scan mới? Cache hiện tại sẽ bị xóa.', 'chklinkout' )
                )
            )
        );
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Activation notice
        if ( get_transient( 'chklinkout_activated' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( '<strong>ChkLinkOut</strong> đã được kích hoạt thành công! Vào menu <a href="' . admin_url( 'admin.php?page=chklinkout' ) . '">External Links</a> để bắt đầu.', 'chklinkout' ); ?></p>
            </div>
            <?php
            delete_transient( 'chklinkout_activated' );
        }
    }

    /**
     * AJAX: Start new scan
     */
    public function ajax_start_scan() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        // Rate limiting
        $last_scan = get_transient( 'chklinkout_last_scan_time' );
        if ( $last_scan && ( time() - $last_scan ) < 60 ) {
            wp_send_json_error( array( 'message' => __( 'Vui lòng đợi 1 phút trước khi scan lại.', 'chklinkout' ) ) );
        }

        set_transient( 'chklinkout_last_scan_time', time(), 60 );

        $crawler = new ChkLinkOut_External_Link_Crawler();
        $result = $crawler->start_scan();

        // Check if scan was created successfully
        if ( empty( $result['scan_id'] ) || $result['scan_id'] == 0 ) {
            wp_send_json_error( array( 'message' => __( 'Không thể tạo scan. Vui lòng deactivate và activate lại plugin để tạo database tables.', 'chklinkout' ) ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Scan batch
     */
    public function ajax_scan_batch() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        $scan_id = isset( $_POST['scan_id'] ) ? intval( $_POST['scan_id'] ) : 0;
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

        if ( ! $scan_id ) {
            error_log( 'ChkLinkOut: Invalid scan ID in scan_batch. Received: ' . var_export( $_POST, true ) );
            wp_send_json_error( array( 'message' => __( 'Invalid scan ID. Scan không được tạo thành công. Vui lòng thử lại hoặc deactivate/activate plugin.', 'chklinkout' ) ) );
        }

        $crawler = new ChkLinkOut_External_Link_Crawler();
        $result = $crawler->scan_batch( $scan_id, $offset );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Complete scan
     */
    public function ajax_complete_scan() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        $scan_id = isset( $_POST['scan_id'] ) ? intval( $_POST['scan_id'] ) : 0;

        if ( ! $scan_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid scan ID', 'chklinkout' ) ) );
        }

        $crawler = new ChkLinkOut_External_Link_Crawler();
        $stats = $crawler->complete_scan( $scan_id );

        wp_send_json_success( array( 'scan_id' => $scan_id, 'stats' => $stats ) );
    }

    /**
     * AJAX: Check broken links
     */
    public function ajax_check_broken_links() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        $scan_id = isset( $_POST['scan_id'] ) ? intval( $_POST['scan_id'] ) : 0;
        $batch = isset( $_POST['batch'] ) ? intval( $_POST['batch'] ) : 0;

        if ( ! $scan_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid scan ID', 'chklinkout' ) ) );
        }

        $crawler = new ChkLinkOut_External_Link_Crawler();
        $result = $crawler->check_broken_links_batch( $scan_id, $batch );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Get results
     */
    public function ajax_get_results() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        $scan_id = isset( $_POST['scan_id'] ) ? intval( $_POST['scan_id'] ) : 0;
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 50;
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : '';
        $is_broken = isset( $_POST['is_broken'] ) ? ( $_POST['is_broken'] === 'true' ? true : ( $_POST['is_broken'] === 'false' ? false : null ) ) : null;
        $orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'id';
        $order = isset( $_POST['order'] ) ? sanitize_text_field( $_POST['order'] ) : 'DESC';

        if ( ! $scan_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid scan ID', 'chklinkout' ) ) );
        }

        $args = array(
            'post_type' => $post_type,
            'is_broken' => $is_broken,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'limit' => $per_page,
            'offset' => ( $page - 1 ) * $per_page
        );

        $links = ChkLinkOut_Database::get_links( $scan_id, $args );
        $total = ChkLinkOut_Database::get_links_count( $scan_id, $args );
        $stats = ChkLinkOut_Database::get_scan_statistics( $scan_id );

        wp_send_json_success( array(
            'links' => $links,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil( $total / $per_page ),
            'stats' => $stats
        ) );
    }

    /**
     * AJAX: Export CSV
     */
    public function ajax_export_csv() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'chklinkout' ) );
        }

        $scan_id = isset( $_GET['scan_id'] ) ? intval( $_GET['scan_id'] ) : 0;

        if ( ! $scan_id ) {
            wp_die( __( 'Invalid scan ID', 'chklinkout' ) );
        }

        $links = ChkLinkOut_Database::get_links( $scan_id, array( 'limit' => 999999 ) );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=chklinkout-export-' . date( 'Y-m-d-H-i-s' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );

        // BOM for UTF-8
        fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

        // Headers
        fputcsv( $output, array( 'Tiêu đề', 'Loại', 'External URL', 'Vị trí', 'HTTP Status', 'Broken', 'URL Bài viết', 'Edit URL' ) );

        foreach ( $links as $link ) {
            fputcsv( $output, array(
                $link['post_title'],
                $link['post_type'],
                $link['external_url'],
                $link['location'],
                $link['http_status'] ?? 'N/A',
                $link['is_broken'] ? 'Yes' : 'No',
                $link['post_url'],
                $link['edit_url']
            ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * AJAX: Export JSON
     */
    public function ajax_export_json() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        $scan_id = isset( $_POST['scan_id'] ) ? intval( $_POST['scan_id'] ) : 0;

        if ( ! $scan_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid scan ID', 'chklinkout' ) ) );
        }

        $links = ChkLinkOut_Database::get_links( $scan_id, array( 'limit' => 999999 ) );
        $stats = ChkLinkOut_Database::get_scan_statistics( $scan_id );

        wp_send_json_success( array(
            'scan_id' => $scan_id,
            'exported_at' => current_time( 'mysql' ),
            'statistics' => $stats,
            'links' => $links
        ) );
    }

    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        $crawler = new ChkLinkOut_External_Link_Crawler();
        $crawler->clear_cache();

        wp_send_json_success( array( 'message' => __( 'Cache đã được xóa', 'chklinkout' ) ) );
    }

    /**
     * AJAX: Get cached scan
     */
    public function ajax_get_cached_scan() {
        check_ajax_referer( 'chklinkout_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
        }

        $crawler = new ChkLinkOut_External_Link_Crawler();
        $scan_id = $crawler->get_cached_scan();

        if ( $scan_id ) {
            $scan = ChkLinkOut_Database::get_scan( $scan_id );
            wp_send_json_success( array( 'scan_id' => $scan_id, 'scan' => $scan ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'No cached scan', 'chklinkout' ) ) );
        }
    }
}

// Initialize plugin
ChkLinkOut::get_instance();
