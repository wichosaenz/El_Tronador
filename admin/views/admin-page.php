<?php
/**
 * Admin settings page template.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tabs        = ETR_Admin::get_tabs();
$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
$settings    = ETR_Admin_Options::get_all();
$purge_url   = wp_nonce_url( admin_url( 'admin.php?action=etr_purge_cache' ), 'etr_purge_cache' );
?>
<div class="wrap etr-admin-wrap">
    <h1><?php esc_html_e( 'El Tronador — Performance Optimization', 'el-tronador' ); ?></h1>

    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper etr-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=el-tronador&tab=' . $slug ) ); ?>"
               class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Settings form -->
    <form method="post" action="options.php" class="etr-settings-form">
        <?php settings_fields( 'etr_settings_group' ); ?>

        <?php if ( 'general' === $current_tab ) : ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Page Cache', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[page_cache_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[page_cache_enabled]"
                                   value="1"
                                   <?php checked( $settings['page_cache_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Enable static page caching. Serves cached HTML files directly from disk for non-logged-in visitors.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e( 'Delay JavaScript', 'el-tronador' ); ?>
                    </th>
                    <td>
                        <label class="etr-toggle">
                            <input type="hidden" name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[delay_js_enabled]" value="0">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( ETR_Admin_Options::OPTION_KEY ); ?>[delay_js_enabled]"
                                   value="1"
                                   <?php checked( $settings['delay_js_enabled'] ); ?>>
                            <span class="etr-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Delay loading of JavaScript files until the user interacts with the page (scroll, click, touch). Improves Core Web Vitals scores.', 'el-tronador' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <?php submit_button( __( 'Save Settings', 'el-tronador' ) ); ?>
    </form>

    <!-- Purge cache button -->
    <div class="etr-purge-section">
        <h2><?php esc_html_e( 'Cache Management', 'el-tronador' ); ?></h2>
        <p><?php esc_html_e( 'Clear all cached pages. This also flushes the object cache (Redis / Object Cache Pro) if active.', 'el-tronador' ); ?></p>
        <a href="<?php echo esc_url( $purge_url ); ?>" class="button button-secondary etr-purge-btn">
            <?php esc_html_e( 'Purge All Cache', 'el-tronador' ); ?>
        </a>
    </div>

    <div class="etr-footer">
        <p>El Tronador v<?php echo esc_html( ETR_VERSION ); ?></p>
    </div>
</div>
