<?php
/**
 * Compatibility checker for third-party plugin conflicts.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Compatibility {

    /**
     * Run all compatibility checks and register admin notices.
     */
    public function init(): void {
        if ( is_admin() ) {
            add_action( 'admin_notices', [ $this, 'display_breeze_notice' ] );
        }
    }

    /**
     * Check whether the Breeze plugin is active.
     *
     * @return bool
     */
    public function is_breeze_active(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active( 'breeze/breeze.php' ) || class_exists( 'Breeze_Admin' );
    }

    /**
     * Display an admin error notice when Breeze is active.
     */
    public function display_breeze_notice(): void {
        if ( ! $this->is_breeze_active() && ! get_transient( 'etr_breeze_conflict' ) ) {
            return;
        }

        // If Breeze was deactivated after the conflict was detected, clean up.
        if ( ! $this->is_breeze_active() && get_transient( 'etr_breeze_conflict' ) ) {
            delete_transient( 'etr_breeze_conflict' );
            return;
        }

        printf(
            '<div class="notice notice-error"><p><strong>El Tronador:</strong> %s</p></div>',
            esc_html__(
                'The Breeze plugin is active. Please deactivate Breeze before using El Tronador to avoid conflicts and potential white-screen errors. The El Tronador page cache engine has been disabled.',
                'el-tronador'
            )
        );
    }

    /**
     * Check whether the page cache module should be disabled due to conflicts.
     *
     * @return bool True if page cache should be blocked.
     */
    public function is_page_cache_blocked(): bool {
        return $this->is_breeze_active() || (bool) get_transient( 'etr_breeze_conflict' );
    }
}
