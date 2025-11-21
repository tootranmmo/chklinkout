<?php
/**
 * Admin Page Class
 *
 * @package ChkLinkOut
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ChkLinkOut_Admin_Page
 */
class ChkLinkOut_Admin_Page {

    /**
     * Display admin page
     */
    public static function display() {
        ?>
        <div class="wrap chklinkout-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="chklinkout-header">
                <p class="description">
                    <?php _e( 'Quét và liệt kê tất cả external links trong website với batch processing, broken link checker và monitoring.', 'chklinkout' ); ?>
                </p>
                <div class="chklinkout-actions">
                    <button type="button" id="chklinkout-scan-btn" class="button button-primary button-hero">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e( 'Bắt đầu quét mới', 'chklinkout' ); ?>
                    </button>
                    <button type="button" id="chklinkout-load-cache-btn" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e( 'Tải kết quả đã cache', 'chklinkout' ); ?>
                    </button>
                    <button type="button" id="chklinkout-check-broken-btn" class="button button-secondary" style="display:none;">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e( 'Kiểm tra Broken Links', 'chklinkout' ); ?>
                    </button>
                </div>
            </div>

            <!-- Progress Bar -->
            <div id="chklinkout-progress" class="chklinkout-progress" style="display: none;">
                <div class="progress-info">
                    <span id="chklinkout-progress-text"></span>
                    <span id="chklinkout-progress-percent">0%</span>
                </div>
                <div class="progress-bar-container">
                    <div id="chklinkout-progress-bar" class="progress-bar"></div>
                </div>
            </div>

            <div id="chklinkout-results" class="chklinkout-results" style="display: none;">
                <!-- Statistics -->
                <div id="chklinkout-stats" class="chklinkout-stats"></div>

                <!-- Filters -->
                <div class="chklinkout-filters">
                    <label>
                        <?php _e( 'Lọc theo loại:', 'chklinkout' ); ?>
                        <select id="chklinkout-filter-type">
                            <option value=""><?php _e( 'Tất cả', 'chklinkout' ); ?></option>
                            <option value="post"><?php _e( 'Bài viết', 'chklinkout' ); ?></option>
                            <option value="page"><?php _e( 'Trang', 'chklinkout' ); ?></option>
                            <option value="widget"><?php _e( 'Widget', 'chklinkout' ); ?></option>
                        </select>
                    </label>

                    <label>
                        <?php _e( 'Broken Links:', 'chklinkout' ); ?>
                        <select id="chklinkout-filter-broken">
                            <option value=""><?php _e( 'Tất cả', 'chklinkout' ); ?></option>
                            <option value="true"><?php _e( 'Chỉ broken links', 'chklinkout' ); ?></option>
                            <option value="false"><?php _e( 'Chỉ working links', 'chklinkout' ); ?></option>
                        </select>
                    </label>

                    <label>
                        <?php _e( 'Tìm kiếm:', 'chklinkout' ); ?>
                        <input type="text" id="chklinkout-search" placeholder="<?php _e( 'Tìm kiếm URL hoặc tiêu đề...', 'chklinkout' ); ?>" />
                    </label>

                    <div class="export-buttons">
                        <button type="button" id="chklinkout-export-csv" class="button">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e( 'Xuất CSV', 'chklinkout' ); ?>
                        </button>
                        <button type="button" id="chklinkout-export-json" class="button">
                            <span class="dashicons dashicons-media-code"></span>
                            <?php _e( 'Xuất JSON', 'chklinkout' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Results table -->
                <div id="chklinkout-table-container"></div>

                <!-- Pagination -->
                <div id="chklinkout-pagination" class="chklinkout-pagination"></div>
            </div>

            <div id="chklinkout-error" class="notice notice-error" style="display: none;">
                <p></p>
            </div>
        </div>
        <?php
    }
}
