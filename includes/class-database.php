<?php
/**
 * Database Management Class
 *
 * @package ChkLinkOut
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ChkLinkOut_Database
 */
class ChkLinkOut_Database {

    /**
     * Table name for external links
     */
    const TABLE_LINKS = 'chklinkout_external_links';

    /**
     * Table name for scan history
     */
    const TABLE_SCANS = 'chklinkout_scans';

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // External Links table
        $table_links = $wpdb->prefix . self::TABLE_LINKS;
        $sql_links = "CREATE TABLE IF NOT EXISTS $table_links (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            post_title text NOT NULL,
            post_type varchar(50) NOT NULL,
            post_url text NOT NULL,
            edit_url text NOT NULL,
            external_url text NOT NULL,
            location varchar(100) NOT NULL,
            location_type varchar(50) NOT NULL,
            field_name varchar(100) DEFAULT NULL,
            http_status int(3) DEFAULT NULL,
            is_broken tinyint(1) DEFAULT 0,
            last_checked datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY scan_id (scan_id),
            KEY post_id (post_id),
            KEY is_broken (is_broken),
            KEY http_status (http_status)
        ) $charset_collate;";

        // Scans History table
        $table_scans = $wpdb->prefix . self::TABLE_SCANS;
        $sql_scans = "CREATE TABLE IF NOT EXISTS $table_scans (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            total_posts int(11) DEFAULT 0,
            total_links int(11) DEFAULT 0,
            total_domains int(11) DEFAULT 0,
            total_broken int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_by bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_links );
        dbDelta( $sql_scans );
    }

    /**
     * Drop database tables
     */
    public static function drop_tables() {
        global $wpdb;
        $table_links = $wpdb->prefix . self::TABLE_LINKS;
        $table_scans = $wpdb->prefix . self::TABLE_SCANS;
        $wpdb->query( "DROP TABLE IF EXISTS $table_links" );
        $wpdb->query( "DROP TABLE IF EXISTS $table_scans" );
    }

    /**
     * Create new scan record
     *
     * @return int Scan ID
     */
    public static function create_scan() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SCANS;

        $wpdb->insert(
            $table,
            array(
                'status' => 'running',
                'created_by' => get_current_user_id(),
                'started_at' => current_time( 'mysql' )
            ),
            array( '%s', '%d', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Update scan record
     *
     * @param int $scan_id Scan ID
     * @param array $data Data to update
     */
    public static function update_scan( $scan_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SCANS;

        $wpdb->update(
            $table,
            $data,
            array( 'id' => $scan_id ),
            null,
            array( '%d' )
        );
    }

    /**
     * Save external link
     *
     * @param array $link_data Link data
     * @return int|false
     */
    public static function save_link( $link_data ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LINKS;

        $result = $wpdb->insert(
            $table,
            $link_data,
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get links by scan ID
     *
     * @param int $scan_id Scan ID
     * @param array $args Query arguments
     * @return array
     */
    public static function get_links( $scan_id, $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LINKS;

        $defaults = array(
            'post_type' => '',
            'is_broken' => null,
            'search' => '',
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );

        $args = wp_parse_args( $args, $defaults );

        $where = $wpdb->prepare( 'scan_id = %d', $scan_id );

        if ( ! empty( $args['post_type'] ) ) {
            $where .= $wpdb->prepare( ' AND post_type = %s', $args['post_type'] );
        }

        if ( $args['is_broken'] !== null ) {
            $where .= $wpdb->prepare( ' AND is_broken = %d', $args['is_broken'] ? 1 : 0 );
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= $wpdb->prepare( ' AND (post_title LIKE %s OR external_url LIKE %s)', $search, $search );
        }

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        $query = "SELECT * FROM $table WHERE $where ORDER BY $orderby LIMIT $limit OFFSET $offset";

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get total links count
     *
     * @param int $scan_id Scan ID
     * @param array $args Query arguments
     * @return int
     */
    public static function get_links_count( $scan_id, $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LINKS;

        $where = $wpdb->prepare( 'scan_id = %d', $scan_id );

        if ( ! empty( $args['post_type'] ) ) {
            $where .= $wpdb->prepare( ' AND post_type = %s', $args['post_type'] );
        }

        if ( isset( $args['is_broken'] ) && $args['is_broken'] !== null ) {
            $where .= $wpdb->prepare( ' AND is_broken = %d', $args['is_broken'] ? 1 : 0 );
        }

        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= $wpdb->prepare( ' AND (post_title LIKE %s OR external_url LIKE %s)', $search, $search );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );
    }

    /**
     * Get latest scan
     *
     * @return object|null
     */
    public static function get_latest_scan() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SCANS;
        return $wpdb->get_row( "SELECT * FROM $table ORDER BY id DESC LIMIT 1" );
    }

    /**
     * Get scan by ID
     *
     * @param int $scan_id Scan ID
     * @return object|null
     */
    public static function get_scan( $scan_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SCANS;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $scan_id ) );
    }

    /**
     * Delete old scans
     *
     * @param int $keep_days Days to keep
     */
    public static function cleanup_old_scans( $keep_days = 30 ) {
        global $wpdb;
        $table_scans = $wpdb->prefix . self::TABLE_SCANS;
        $table_links = $wpdb->prefix . self::TABLE_LINKS;

        $date = date( 'Y-m-d H:i:s', strtotime( "-{$keep_days} days" ) );

        // Get old scan IDs
        $old_scan_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $table_scans WHERE created_at < %s",
                $date
            )
        );

        if ( ! empty( $old_scan_ids ) ) {
            $ids = implode( ',', array_map( 'intval', $old_scan_ids ) );

            // Delete links
            $wpdb->query( "DELETE FROM $table_links WHERE scan_id IN ($ids)" );

            // Delete scans
            $wpdb->query( "DELETE FROM $table_scans WHERE id IN ($ids)" );
        }
    }

    /**
     * Update link HTTP status
     *
     * @param int $link_id Link ID
     * @param int $http_status HTTP status code
     */
    public static function update_link_status( $link_id, $http_status ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LINKS;

        $is_broken = ( $http_status >= 400 || $http_status === 0 ) ? 1 : 0;

        $wpdb->update(
            $table,
            array(
                'http_status' => $http_status,
                'is_broken' => $is_broken,
                'last_checked' => current_time( 'mysql' )
            ),
            array( 'id' => $link_id ),
            array( '%d', '%d', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Get statistics for scan
     *
     * @param int $scan_id Scan ID
     * @return array
     */
    public static function get_scan_statistics( $scan_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LINKS;

        // Get basic stats
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT post_id) as total_posts,
                    COUNT(*) as total_links,
                    COUNT(DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(external_url, '/', 3), '/', -1)) as total_domains,
                    SUM(is_broken) as total_broken
                FROM $table
                WHERE scan_id = %d",
                $scan_id
            ),
            ARRAY_A
        );

        // Get top domains
        $top_domains = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    SUBSTRING_INDEX(SUBSTRING_INDEX(external_url, '/', 3), '/', -1) as domain,
                    COUNT(*) as count
                FROM $table
                WHERE scan_id = %d
                GROUP BY domain
                ORDER BY count DESC
                LIMIT 10",
                $scan_id
            ),
            ARRAY_A
        );

        // Get by post type
        $by_post_type = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_type, COUNT(DISTINCT post_id) as count
                FROM $table
                WHERE scan_id = %d
                GROUP BY post_type",
                $scan_id
            ),
            ARRAY_A
        );

        $stats['top_domains'] = $top_domains;
        $stats['by_post_type'] = $by_post_type;

        return $stats;
    }
}
