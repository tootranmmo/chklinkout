<?php
/**
 * Plugin Name: ChkLinkOut - External Link Crawler
 * Plugin URI: https://github.com/tootranmmo/chklinkout
 * Description: Crawl và liệt kê tất cả external links trong WordPress site, hiển thị vị trí và bài viết chứa link
 * Version: 1.0.0
 * Author: ChkLinkOut Team
 * Author URI: https://github.com/tootranmmo
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chklinkout
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'CHKLINKOUT_VERSION', '1.0.0' );
define( 'CHKLINKOUT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHKLINKOUT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once CHKLINKOUT_PLUGIN_DIR . 'includes/class-external-link-crawler.php';
require_once CHKLINKOUT_PLUGIN_DIR . 'includes/class-admin-page.php';

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
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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
        add_menu_page(
            __( 'External Links', 'chklinkout' ),
            __( 'External Links', 'chklinkout' ),
            'manage_options',
            'chklinkout',
            array( 'ChkLinkOut_Admin_Page', 'display' ),
            'dashicons-admin-links',
            30
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_chklinkout' !== $hook ) {
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
                'nonce' => wp_create_nonce( 'chklinkout_nonce' )
            )
        );
    }
}

// Initialize plugin
ChkLinkOut::get_instance();

// AJAX handlers
add_action( 'wp_ajax_chklinkout_scan', 'chklinkout_ajax_scan' );

function chklinkout_ajax_scan() {
    check_ajax_referer( 'chklinkout_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Unauthorized', 'chklinkout' ) ) );
    }

    $crawler = new ChkLinkOut_External_Link_Crawler();
    $results = $crawler->scan();

    wp_send_json_success( array( 'results' => $results ) );
}
