<?php
/**
 * Admin page registration and settings handler.
 *
 * Provides a tab-ready settings page under the WordPress Settings menu.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Admin {

    /**
     * Settings page slug.
     */
    private const PAGE_SLUG = 'el-tronador';

    /**
     * Hook the admin page and assets.
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_notices', [ $this, 'display_purge_notice' ] );
    }

    /**
     * Register the settings page under the Settings menu.
     */
    public function add_menu_page(): void {
        add_options_page(
            __( 'El Tronador', 'el-tronador' ),
            __( 'El Tronador', 'el-tronador' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register plugin settings with the Settings API.
     */
    public function register_settings(): void {
        register_setting(
            'etr_settings_group',
            ETR_Admin_Options::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => ETR_Admin_Options::defaults(),
            ]
        );
    }

    /**
     * Sanitize submitted settings.
     *
     * @param mixed $input Raw input from form submission.
     * @return array<string, mixed>
     */
    public function sanitize_settings( mixed $input ): array {
        if ( ! is_array( $input ) ) {
            $input = [];
        }

        return [
            'page_cache_enabled'            => ! empty( $input['page_cache_enabled'] ),
            'delay_js_enabled'              => ! empty( $input['delay_js_enabled'] ),
            'minify_css_enabled'            => ! empty( $input['minify_css_enabled'] ),
            'minify_js_enabled'             => ! empty( $input['minify_js_enabled'] ),
            'optimize_css_delivery_enabled' => ! empty( $input['optimize_css_delivery_enabled'] ),
            'file_optimization_exclusions'  => sanitize_textarea_field( $input['file_optimization_exclusions'] ?? '' ),
            'lazy_images_enabled'           => ! empty( $input['lazy_images_enabled'] ),
            'lazy_iframes_enabled'          => ! empty( $input['lazy_iframes_enabled'] ),
            'youtube_facade_enabled'        => ! empty( $input['youtube_facade_enabled'] ),
            'media_lazy_exclusions'         => sanitize_textarea_field( $input['media_lazy_exclusions'] ?? '' ),
        ];
    }

    /**
     * Enqueue admin CSS on our settings page only.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_assets( string $hook_suffix ): void {
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'etr-admin-css',
            ETR_PLUGIN_URL . 'assets/css/etr-admin.css',
            [],
            ETR_VERSION
        );
    }

    /**
     * Show a success notice after cache purge.
     */
    public function display_purge_notice(): void {
        if ( get_transient( 'etr_cache_purged' ) ) {
            delete_transient( 'etr_cache_purged' );
            echo '<div class="notice notice-success is-dismissible"><p>';
            esc_html_e( 'El Tronador: Cache purged successfully.', 'el-tronador' );
            echo '</p></div>';
        }
    }

    /**
     * Render the settings page.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include ETR_PLUGIN_DIR . 'admin/views/admin-page.php';
    }

    /**
     * Get available tabs. Extendable for future modules.
     *
     * @return array<string, string> Tab slug => label.
     */
    public static function get_tabs(): array {
        return [
            'general'           => __( 'General', 'el-tronador' ),
            'file_optimization' => __( 'Optimización de Archivos', 'el-tronador' ),
            'media'             => __( 'Medios', 'el-tronador' ),
            // Future tabs:
            // 'database' => __( 'Database', 'el-tronador' ),
            // 'preload'  => __( 'Preload', 'el-tronador' ),
        ];
    }
}
