<?php
/**
 * External Link Crawler Class
 *
 * @package ChkLinkOut
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ChkLinkOut_External_Link_Crawler
 */
class ChkLinkOut_External_Link_Crawler {

    /**
     * Batch size for processing
     */
    const BATCH_SIZE = 50;

    /**
     * Cache duration (1 hour)
     */
    const CACHE_DURATION = 3600;

    /**
     * Current scan ID
     */
    private $scan_id = null;

    /**
     * Start a new scan
     *
     * @return array Scan information
     */
    public function start_scan() {
        // Create scan record
        $this->scan_id = ChkLinkOut_Database::create_scan();

        // Get total posts count
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        $total_posts = wp_count_posts();
        $total = 0;
        foreach ( $post_types as $type ) {
            $count = wp_count_posts( $type );
            if ( isset( $count->publish ) ) {
                $total += $count->publish;
            }
        }

        return array(
            'scan_id' => $this->scan_id,
            'total_posts' => $total,
            'batch_size' => self::BATCH_SIZE,
            'total_batches' => ceil( $total / self::BATCH_SIZE )
        );
    }

    /**
     * Scan a batch of posts
     *
     * @param int $scan_id Scan ID
     * @param int $offset Offset for batch
     * @return array Batch results
     */
    public function scan_batch( $scan_id, $offset = 0 ) {
        $this->scan_id = $scan_id;

        $site_url = get_site_url();
        $site_domain = parse_url( $site_url, PHP_URL_HOST );

        // Get all post types
        $post_types = get_post_types( array( 'public' => true ), 'names' );

        // Query batch of published posts with optimized fields
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => self::BATCH_SIZE,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true, // Performance optimization
            'update_post_meta_cache' => false, // We'll load meta manually
            'update_post_term_cache' => false
        );

        $query = new WP_Query( $args );
        $processed = 0;
        $links_found = 0;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $post = get_post( $post_id );

                $external_links = $this->extract_post_links( $post, $site_domain );

                if ( ! empty( $external_links ) ) {
                    foreach ( $external_links as $link_data ) {
                        $this->save_link( $post, $link_data );
                        $links_found++;
                    }
                }

                $processed++;
            }
            wp_reset_postdata();
        }

        // Scan widgets on first batch
        if ( $offset === 0 ) {
            $widget_links = $this->scan_widgets( $site_domain );
            $links_found += count( $widget_links );
        }

        return array(
            'processed' => $processed,
            'links_found' => $links_found,
            'offset' => $offset + self::BATCH_SIZE
        );
    }

    /**
     * Complete scan
     *
     * @param int $scan_id Scan ID
     * @return array Final statistics
     */
    public function complete_scan( $scan_id ) {
        $stats = ChkLinkOut_Database::get_scan_statistics( $scan_id );

        // Update scan record
        ChkLinkOut_Database::update_scan(
            $scan_id,
            array(
                'total_posts' => $stats['total_posts'],
                'total_links' => $stats['total_links'],
                'total_domains' => $stats['total_domains'],
                'total_broken' => $stats['total_broken'],
                'status' => 'completed',
                'completed_at' => current_time( 'mysql' )
            )
        );

        // Set cache
        set_transient( 'chklinkout_latest_scan', $scan_id, self::CACHE_DURATION );

        return $stats;
    }

    /**
     * Extract all external links from a post
     *
     * @param WP_Post $post Post object
     * @param string $site_domain Site domain
     * @return array External links data
     */
    private function extract_post_links( $post, $site_domain ) {
        $external_links = array();

        // Check post content
        $content_links = $this->extract_links( $post->post_content, $site_domain );
        if ( ! empty( $content_links ) ) {
            foreach ( $content_links as $link ) {
                $external_links[] = array(
                    'url' => $link,
                    'location' => __( 'Nội dung bài viết', 'chklinkout' ),
                    'location_type' => 'content'
                );
            }
        }

        // Check post excerpt
        if ( ! empty( $post->post_excerpt ) ) {
            $excerpt_links = $this->extract_links( $post->post_excerpt, $site_domain );
            if ( ! empty( $excerpt_links ) ) {
                foreach ( $excerpt_links as $link ) {
                    $external_links[] = array(
                        'url' => $link,
                        'location' => __( 'Trích dẫn', 'chklinkout' ),
                        'location_type' => 'excerpt'
                    );
                }
            }
        }

        // Check custom fields
        $custom_fields = get_post_meta( $post->ID );
        foreach ( $custom_fields as $key => $values ) {
            // Skip private fields
            if ( strpos( $key, '_' ) === 0 ) {
                continue;
            }

            foreach ( $values as $value ) {
                if ( is_string( $value ) ) {
                    $meta_links = $this->extract_links( $value, $site_domain );
                    if ( ! empty( $meta_links ) ) {
                        foreach ( $meta_links as $link ) {
                            $external_links[] = array(
                                'url' => $link,
                                'location' => sprintf( __( 'Custom Field: %s', 'chklinkout' ), $key ),
                                'location_type' => 'custom_field',
                                'field_name' => $key
                            );
                        }
                    }
                }
            }
        }

        return $external_links;
    }

    /**
     * Save link to database
     *
     * @param WP_Post $post Post object
     * @param array $link_data Link data
     */
    private function save_link( $post, $link_data ) {
        $data = array(
            'scan_id' => $this->scan_id,
            'post_id' => $post->ID,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_url' => get_permalink( $post->ID ),
            'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
            'external_url' => esc_url_raw( $link_data['url'] ),
            'location' => sanitize_text_field( $link_data['location'] ),
            'location_type' => sanitize_text_field( $link_data['location_type'] ),
            'field_name' => isset( $link_data['field_name'] ) ? sanitize_text_field( $link_data['field_name'] ) : null,
            'http_status' => null,
            'is_broken' => 0
        );

        ChkLinkOut_Database::save_link( $data );
    }

    /**
     * Extract external links from content
     *
     * @param string $content Content to scan
     * @param string $site_domain Current site domain
     * @return array Array of external links
     */
    private function extract_links( $content, $site_domain ) {
        if ( empty( $content ) ) {
            return array();
        }

        $external_links = array();

        // Find all links using regex
        preg_match_all( '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

        if ( ! empty( $matches[1] ) ) {
            foreach ( $matches[1] as $url ) {
                // Skip anchors, javascript, mailto, tel, etc.
                if ( strpos( $url, '#' ) === 0 ||
                     strpos( $url, 'javascript:' ) === 0 ||
                     strpos( $url, 'mailto:' ) === 0 ||
                     strpos( $url, 'tel:' ) === 0 ) {
                    continue;
                }

                // Parse URL
                $parsed_url = parse_url( $url );

                // Skip if no host (relative URLs)
                if ( empty( $parsed_url['host'] ) ) {
                    continue;
                }

                // Check if external
                if ( $this->is_external_link( $parsed_url['host'], $site_domain ) ) {
                    // Store unique links only
                    if ( ! in_array( $url, $external_links, true ) ) {
                        $external_links[] = $url;
                    }
                }
            }
        }

        return $external_links;
    }

    /**
     * Check if a link is external
     *
     * @param string $link_domain Link domain
     * @param string $site_domain Site domain
     * @return bool True if external, false otherwise
     */
    private function is_external_link( $link_domain, $site_domain ) {
        // Remove www. for comparison
        $link_domain = str_replace( 'www.', '', strtolower( $link_domain ) );
        $site_domain = str_replace( 'www.', '', strtolower( $site_domain ) );

        return $link_domain !== $site_domain;
    }

    /**
     * Scan widgets for external links
     *
     * @param string $site_domain Site domain
     * @return int Number of links found
     */
    private function scan_widgets( $site_domain ) {
        $links_found = 0;
        $sidebars_widgets = wp_get_sidebars_widgets();

        foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {
            if ( $sidebar_id === 'wp_inactive_widgets' || empty( $widget_ids ) ) {
                continue;
            }

            foreach ( $widget_ids as $widget_id ) {
                $widget_data = $this->get_widget_data( $widget_id );

                if ( $widget_data && ! empty( $widget_data['content'] ) ) {
                    $widget_links = $this->extract_links( $widget_data['content'], $site_domain );

                    if ( ! empty( $widget_links ) ) {
                        foreach ( $widget_links as $link ) {
                            $data = array(
                                'scan_id' => $this->scan_id,
                                'post_id' => 0,
                                'post_title' => sprintf( __( 'Widget: %s', 'chklinkout' ), $widget_data['title'] ),
                                'post_type' => 'widget',
                                'post_url' => admin_url( 'widgets.php' ),
                                'edit_url' => admin_url( 'widgets.php' ),
                                'external_url' => esc_url_raw( $link ),
                                'location' => sprintf( __( 'Widget: %s (Sidebar: %s)', 'chklinkout' ), $widget_data['title'], $sidebar_id ),
                                'location_type' => 'widget',
                                'field_name' => $widget_id,
                                'http_status' => null,
                                'is_broken' => 0
                            );

                            ChkLinkOut_Database::save_link( $data );
                            $links_found++;
                        }
                    }
                }
            }
        }

        return $links_found;
    }

    /**
     * Get widget data
     *
     * @param string $widget_id Widget ID
     * @return array|false Widget data or false
     */
    private function get_widget_data( $widget_id ) {
        global $wp_registered_widgets;

        if ( ! isset( $wp_registered_widgets[ $widget_id ] ) ) {
            return false;
        }

        $widget = $wp_registered_widgets[ $widget_id ];
        $content = '';
        $title = '';

        // Try to get widget settings
        if ( isset( $widget['callback'][0] ) && is_object( $widget['callback'][0] ) ) {
            $widget_obj = $widget['callback'][0];
            $widget_name = $widget['callback'][0]->id_base;
            $widget_number = $widget['params'][0]['number'];

            $settings = get_option( 'widget_' . $widget_name );

            if ( isset( $settings[ $widget_number ] ) ) {
                $widget_settings = $settings[ $widget_number ];

                if ( isset( $widget_settings['title'] ) ) {
                    $title = $widget_settings['title'];
                }

                if ( isset( $widget_settings['text'] ) ) {
                    $content = $widget_settings['text'];
                }

                if ( isset( $widget_settings['content'] ) ) {
                    $content = $widget_settings['content'];
                }
            }
        }

        if ( empty( $title ) ) {
            $title = ! empty( $widget['name'] ) ? $widget['name'] : $widget_id;
        }

        return array(
            'title' => $title,
            'content' => $content
        );
    }

    /**
     * Check HTTP status of a URL
     *
     * @param string $url URL to check
     * @return int HTTP status code
     */
    public function check_link_status( $url ) {
        $response = wp_safe_remote_head(
            $url,
            array(
                'timeout' => 10,
                'redirection' => 5,
                'user-agent' => 'ChkLinkOut WordPress Plugin/1.0',
                'sslverify' => false
            )
        );

        if ( is_wp_error( $response ) ) {
            return 0; // Connection error
        }

        return wp_remote_retrieve_response_code( $response );
    }

    /**
     * Check all links in a scan for broken links
     *
     * @param int $scan_id Scan ID
     * @param int $batch Batch number
     * @param int $batch_size Links per batch
     * @return array Results
     */
    public function check_broken_links_batch( $scan_id, $batch = 0, $batch_size = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . ChkLinkOut_Database::TABLE_LINKS;

        // Get links that haven't been checked yet
        $links = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, external_url FROM $table
                WHERE scan_id = %d AND http_status IS NULL
                LIMIT %d OFFSET %d",
                $scan_id,
                $batch_size,
                $batch * $batch_size
            ),
            ARRAY_A
        );

        $checked = 0;
        $broken = 0;

        foreach ( $links as $link ) {
            $status = $this->check_link_status( $link['external_url'] );
            ChkLinkOut_Database::update_link_status( $link['id'], $status );

            $checked++;
            if ( $status >= 400 || $status === 0 ) {
                $broken++;
            }

            // Small delay to avoid rate limiting
            usleep( 200000 ); // 0.2 seconds
        }

        // Get remaining count
        $remaining = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE scan_id = %d AND http_status IS NULL",
                $scan_id
            )
        );

        return array(
            'checked' => $checked,
            'broken' => $broken,
            'remaining' => (int) $remaining,
            'completed' => $remaining === 0
        );
    }

    /**
     * Get cached scan results
     *
     * @return int|false Scan ID or false
     */
    public function get_cached_scan() {
        return get_transient( 'chklinkout_latest_scan' );
    }

    /**
     * Clear cache
     */
    public function clear_cache() {
        delete_transient( 'chklinkout_latest_scan' );
    }
}
