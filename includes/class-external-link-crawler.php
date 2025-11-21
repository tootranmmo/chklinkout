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
     * Scan all posts and pages for external links
     *
     * @return array Array of external links with their locations
     */
    public function scan() {
        $results = array();
        $site_url = get_site_url();
        $site_domain = parse_url( $site_url, PHP_URL_HOST );

        // Get all post types (posts, pages, and custom post types)
        $post_types = get_post_types( array( 'public' => true ), 'names' );

        // Query all published posts
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $posts = get_posts( $args );

        foreach ( $posts as $post_id ) {
            $post = get_post( $post_id );
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
            $custom_fields = get_post_meta( $post_id );
            foreach ( $custom_fields as $key => $values ) {
                // Skip private fields (starting with _)
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

            // If we found external links in this post, add to results
            if ( ! empty( $external_links ) ) {
                $results[] = array(
                    'post_id' => $post_id,
                    'post_title' => get_the_title( $post_id ),
                    'post_type' => get_post_type( $post_id ),
                    'post_url' => get_permalink( $post_id ),
                    'edit_url' => get_edit_post_link( $post_id ),
                    'external_links' => $external_links
                );
            }
        }

        // Scan widgets
        $widget_results = $this->scan_widgets( $site_domain );
        if ( ! empty( $widget_results ) ) {
            $results = array_merge( $results, $widget_results );
        }

        return $results;
    }

    /**
     * Extract external links from content
     *
     * @param string $content Content to scan
     * @param string $site_domain Current site domain
     * @return array Array of external links
     */
    private function extract_links( $content, $site_domain ) {
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
                    if ( ! in_array( $url, $external_links ) ) {
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
        $link_domain = str_replace( 'www.', '', $link_domain );
        $site_domain = str_replace( 'www.', '', $site_domain );

        return $link_domain !== $site_domain;
    }

    /**
     * Scan widgets for external links
     *
     * @param string $site_domain Site domain
     * @return array Array of widget results
     */
    private function scan_widgets( $site_domain ) {
        $results = array();
        $sidebars_widgets = wp_get_sidebars_widgets();

        foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {
            if ( $sidebar_id === 'wp_inactive_widgets' || empty( $widget_ids ) ) {
                continue;
            }

            foreach ( $widget_ids as $widget_id ) {
                // Get widget settings
                $widget_data = $this->get_widget_data( $widget_id );

                if ( $widget_data && ! empty( $widget_data['content'] ) ) {
                    $widget_links = $this->extract_links( $widget_data['content'], $site_domain );

                    if ( ! empty( $widget_links ) ) {
                        $external_links = array();
                        foreach ( $widget_links as $link ) {
                            $external_links[] = array(
                                'url' => $link,
                                'location' => sprintf( __( 'Widget: %s (Sidebar: %s)', 'chklinkout' ), $widget_data['title'], $sidebar_id ),
                                'location_type' => 'widget',
                                'widget_id' => $widget_id,
                                'sidebar_id' => $sidebar_id
                            );
                        }

                        $results[] = array(
                            'post_id' => 0,
                            'post_title' => sprintf( __( 'Widget: %s', 'chklinkout' ), $widget_data['title'] ),
                            'post_type' => 'widget',
                            'post_url' => admin_url( 'widgets.php' ),
                            'edit_url' => admin_url( 'widgets.php' ),
                            'external_links' => $external_links
                        );
                    }
                }
            }
        }

        return $results;
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

                // Get title
                if ( isset( $widget_settings['title'] ) ) {
                    $title = $widget_settings['title'];
                }

                // Get content (text widgets)
                if ( isset( $widget_settings['text'] ) ) {
                    $content = $widget_settings['text'];
                }

                // Get content (custom HTML widgets)
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
     * Get statistics
     *
     * @param array $results Scan results
     * @return array Statistics
     */
    public function get_statistics( $results ) {
        $stats = array(
            'total_posts' => 0,
            'total_links' => 0,
            'total_unique_domains' => 0,
            'by_post_type' => array(),
            'by_location' => array(),
            'top_domains' => array()
        );

        $all_links = array();
        $all_domains = array();

        foreach ( $results as $result ) {
            $stats['total_posts']++;

            $post_type = $result['post_type'];
            if ( ! isset( $stats['by_post_type'][ $post_type ] ) ) {
                $stats['by_post_type'][ $post_type ] = 0;
            }
            $stats['by_post_type'][ $post_type ]++;

            foreach ( $result['external_links'] as $link_data ) {
                $stats['total_links']++;
                $all_links[] = $link_data['url'];

                // Count by location type
                $location_type = $link_data['location_type'];
                if ( ! isset( $stats['by_location'][ $location_type ] ) ) {
                    $stats['by_location'][ $location_type ] = 0;
                }
                $stats['by_location'][ $location_type ]++;

                // Extract domain
                $domain = parse_url( $link_data['url'], PHP_URL_HOST );
                if ( $domain ) {
                    $all_domains[] = $domain;
                }
            }
        }

        // Count unique domains
        $domain_counts = array_count_values( $all_domains );
        arsort( $domain_counts );
        $stats['total_unique_domains'] = count( $domain_counts );
        $stats['top_domains'] = array_slice( $domain_counts, 0, 10, true );

        return $stats;
    }
}
