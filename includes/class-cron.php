<?php
/**
 * Cron Management Class
 *
 * @package ChkLinkOut
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ChkLinkOut_Cron
 */
class ChkLinkOut_Cron {

    /**
     * Cron hook name
     */
    const HOOK_SCAN = 'chklinkout_auto_scan';
    const HOOK_CLEANUP = 'chklinkout_cleanup';

    /**
     * Initialize cron
     */
    public static function init() {
        add_action( self::HOOK_SCAN, array( __CLASS__, 'run_auto_scan' ) );
        add_action( self::HOOK_CLEANUP, array( __CLASS__, 'run_cleanup' ) );
    }

    /**
     * Schedule cron jobs
     */
    public static function schedule() {
        $settings = get_option( 'chklinkout_settings' );
        $frequency = ! empty( $settings['scan_frequency'] ) ? $settings['scan_frequency'] : 'weekly';

        // Unschedule first
        self::unschedule();

        // Schedule scan
        if ( ! wp_next_scheduled( self::HOOK_SCAN ) ) {
            wp_schedule_event( time(), $frequency, self::HOOK_SCAN );
        }

        // Schedule cleanup (daily)
        if ( ! wp_next_scheduled( self::HOOK_CLEANUP ) ) {
            wp_schedule_event( time(), 'daily', self::HOOK_CLEANUP );
        }
    }

    /**
     * Unschedule cron jobs
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::HOOK_SCAN );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK_SCAN );
        }

        $timestamp = wp_next_scheduled( self::HOOK_CLEANUP );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK_CLEANUP );
        }
    }

    /**
     * Run automatic scan
     */
    public static function run_auto_scan() {
        $crawler = new ChkLinkOut_External_Link_Crawler();

        // Start scan
        $scan_info = $crawler->start_scan();
        $scan_id = $scan_info['scan_id'];
        $total_posts = $scan_info['total_posts'];

        // Process all batches
        $offset = 0;
        while ( $offset < $total_posts ) {
            $crawler->scan_batch( $scan_id, $offset );
            $offset += ChkLinkOut_External_Link_Crawler::BATCH_SIZE;
        }

        // Complete scan
        $stats = $crawler->complete_scan( $scan_id );

        // Check broken links if enabled
        $settings = get_option( 'chklinkout_settings' );
        if ( ! empty( $settings['check_broken_links'] ) ) {
            $batch = 0;
            do {
                $result = $crawler->check_broken_links_batch( $scan_id, $batch );
                $batch++;
            } while ( ! $result['completed'] );

            // Update broken count
            $final_stats = ChkLinkOut_Database::get_scan_statistics( $scan_id );
            ChkLinkOut_Database::update_scan( $scan_id, array( 'total_broken' => $final_stats['total_broken'] ) );
        }

        // Send email notification if enabled
        if ( ! empty( $settings['email_notifications'] ) ) {
            self::send_notification_email( $scan_id, $stats );
        }
    }

    /**
     * Run cleanup
     */
    public static function run_cleanup() {
        $settings = get_option( 'chklinkout_settings' );
        $days = ! empty( $settings['cleanup_days'] ) ? intval( $settings['cleanup_days'] ) : 30;

        ChkLinkOut_Database::cleanup_old_scans( $days );
    }

    /**
     * Send notification email
     *
     * @param int $scan_id Scan ID
     * @param array $stats Statistics
     */
    private static function send_notification_email( $scan_id, $stats ) {
        $settings = get_option( 'chklinkout_settings' );
        $to = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );

        $subject = sprintf(
            __( '[%s] External Links Scan Completed', 'chklinkout' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            __( "External Links Scan Results\n\n" .
                "Scan ID: %d\n" .
                "Total Posts: %d\n" .
                "Total Links: %d\n" .
                "Unique Domains: %d\n" .
                "Broken Links: %d\n\n" .
                "View details: %s\n", 'chklinkout' ),
            $scan_id,
            $stats['total_posts'],
            $stats['total_links'],
            $stats['total_domains'],
            $stats['total_broken'],
            admin_url( 'admin.php?page=chklinkout' )
        );

        wp_mail( $to, $subject, $message );
    }
}
