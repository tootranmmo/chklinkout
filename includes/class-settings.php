<?php
/**
 * Settings Page Class
 *
 * @package ChkLinkOut
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ChkLinkOut_Settings
 */
class ChkLinkOut_Settings {

    /**
     * Display settings page
     */
    public static function display() {
        // Save settings
        if ( isset( $_POST['chklinkout_save_settings'] ) && check_admin_referer( 'chklinkout_settings' ) ) {
            self::save_settings();
            echo '<div class="notice notice-success"><p>' . __( 'Cài đặt đã được lưu.', 'chklinkout' ) . '</p></div>';
        }

        $settings = get_option( 'chklinkout_settings', array() );
        ?>
        <div class="wrap chklinkout-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'chklinkout_settings' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Auto Scan', 'chklinkout' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_scan_enabled" value="1" <?php checked( ! empty( $settings['auto_scan_enabled'] ) ); ?>>
                                <?php _e( 'Bật tự động quét định kỳ', 'chklinkout' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e( 'Tần suất quét', 'chklinkout' ); ?></th>
                        <td>
                            <select name="scan_frequency">
                                <option value="daily" <?php selected( isset( $settings['scan_frequency'] ) && $settings['scan_frequency'] === 'daily' ); ?>><?php _e( 'Hàng ngày', 'chklinkout' ); ?></option>
                                <option value="weekly" <?php selected( ! isset( $settings['scan_frequency'] ) || $settings['scan_frequency'] === 'weekly' ); ?>><?php _e( 'Hàng tuần', 'chklinkout' ); ?></option>
                                <option value="monthly" <?php selected( isset( $settings['scan_frequency'] ) && $settings['scan_frequency'] === 'monthly' ); ?>><?php _e( 'Hàng tháng', 'chklinkout' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e( 'Kiểm tra Broken Links', 'chklinkout' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="check_broken_links" value="1" <?php checked( ! isset( $settings['check_broken_links'] ) || $settings['check_broken_links'] ); ?>>
                                <?php _e( 'Tự động kiểm tra broken links sau mỗi scan', 'chklinkout' ); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e( 'Email Notifications', 'chklinkout' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1" <?php checked( ! empty( $settings['email_notifications'] ) ); ?>>
                                <?php _e( 'Gửi email thông báo sau mỗi scan', 'chklinkout' ); ?>
                            </label>
                            <p class="description"><?php _e( 'Nhận thông báo qua email khi scan hoàn tất', 'chklinkout' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e( 'Email nhận thông báo', 'chklinkout' ); ?></th>
                        <td>
                            <input type="email" name="notification_email" value="<?php echo esc_attr( ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' ) ); ?>" class="regular-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e( 'Cleanup Old Scans', 'chklinkout' ); ?></th>
                        <td>
                            <input type="number" name="cleanup_days" value="<?php echo esc_attr( ! empty( $settings['cleanup_days'] ) ? $settings['cleanup_days'] : 30 ); ?>" min="1" max="365" class="small-text">
                            <?php _e( 'ngày', 'chklinkout' ); ?>
                            <p class="description"><?php _e( 'Tự động xóa các scan cũ hơn số ngày này', 'chklinkout' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="chklinkout_save_settings" class="button button-primary" value="<?php _e( 'Lưu cài đặt', 'chklinkout' ); ?>">
                </p>
            </form>

            <hr>

            <h2><?php _e( 'Thông tin hệ thống', 'chklinkout' ); ?></h2>
            <table class="widefat">
                <tr>
                    <td><strong><?php _e( 'Plugin Version', 'chklinkout' ); ?></strong></td>
                    <td><?php echo CHKLINKOUT_VERSION; ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'WordPress Version', 'chklinkout' ); ?></strong></td>
                    <td><?php echo get_bloginfo( 'version' ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'PHP Version', 'chklinkout' ); ?></strong></td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e( 'Next Scheduled Scan', 'chklinkout' ); ?></strong></td>
                    <td>
                        <?php
                        $next_scan = wp_next_scheduled( ChkLinkOut_Cron::HOOK_SCAN );
                        if ( $next_scan ) {
                            echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_scan );
                        } else {
                            _e( 'Không có lịch', 'chklinkout' );
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private static function save_settings() {
        $settings = array(
            'auto_scan_enabled' => isset( $_POST['auto_scan_enabled'] ),
            'scan_frequency' => sanitize_text_field( $_POST['scan_frequency'] ),
            'email_notifications' => isset( $_POST['email_notifications'] ),
            'notification_email' => sanitize_email( $_POST['notification_email'] ),
            'check_broken_links' => isset( $_POST['check_broken_links'] ),
            'cleanup_days' => intval( $_POST['cleanup_days'] )
        );

        update_option( 'chklinkout_settings', $settings );

        // Update cron schedule
        if ( $settings['auto_scan_enabled'] ) {
            ChkLinkOut_Cron::schedule();
        } else {
            ChkLinkOut_Cron::unschedule();
        }
    }
}
