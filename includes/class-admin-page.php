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
                    <?php _e( 'Quét và liệt kê tất cả external links trong website của bạn. Plugin sẽ tìm kiếm trong posts, pages, custom post types và widgets.', 'chklinkout' ); ?>
                </p>
                <button type="button" id="chklinkout-scan-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e( 'Bắt đầu quét', 'chklinkout' ); ?>
                </button>
            </div>

            <div id="chklinkout-loading" class="chklinkout-loading" style="display: none;">
                <div class="spinner is-active"></div>
                <p><?php _e( 'Đang quét website...', 'chklinkout' ); ?></p>
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
                        <?php _e( 'Tìm kiếm:', 'chklinkout' ); ?>
                        <input type="text" id="chklinkout-search" placeholder="<?php _e( 'Tìm kiếm URL hoặc tiêu đề...', 'chklinkout' ); ?>" />
                    </label>

                    <button type="button" id="chklinkout-export-csv" class="button">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e( 'Xuất CSV', 'chklinkout' ); ?>
                    </button>
                </div>

                <!-- Results table -->
                <div id="chklinkout-table-container"></div>
            </div>

            <div id="chklinkout-error" class="notice notice-error" style="display: none;">
                <p></p>
            </div>
        </div>
        <?php
    }
}
