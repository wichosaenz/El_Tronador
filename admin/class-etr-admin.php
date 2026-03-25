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
        add_action( 'admin_init', [ $this, 'handle_db_cleanup_request' ] );
        add_action( 'admin_init', [ $this, 'handle_preload_request' ] );
        add_action( 'admin_notices', [ $this, 'display_preload_notice' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_notices', [ $this, 'display_purge_notice' ] );
        add_action( 'admin_notices', [ $this, 'display_db_cleanup_notice' ] );
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

        // The form includes hidden inputs for ALL settings (not just the
        // active tab), so $input always contains every key.  This removes
        // the need for tab-detection or merge logic — just sanitize.
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
            'db_clean_revisions'            => ! empty( $input['db_clean_revisions'] ),
            'db_clean_drafts'               => ! empty( $input['db_clean_drafts'] ),
            'db_clean_spam'                 => ! empty( $input['db_clean_spam'] ),
            'db_clean_transients'           => ! empty( $input['db_clean_transients'] ),
            'db_auto_cleanup'               => ! empty( $input['db_auto_cleanup'] ),
            'preload_enabled'               => ! empty( $input['preload_enabled'] ),
            'preload_sitemap_url'           => esc_url_raw( trim( $input['preload_sitemap_url'] ?? '' ) ),
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
     * Handle the "Clean Now" database action.
     */
    public function handle_db_cleanup_request(): void {
        if ( ! isset( $_POST['etr_db_cleanup'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'etr_db_cleanup' ) ) {
            wp_die( __( 'Unauthorized request.', 'el-tronador' ) );
        }

        $optimizer = new ETR_Database_Optimizer();
        $results   = $optimizer->run_cleanup();

        set_transient( 'etr_db_cleanup_results', $results, 60 );

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=el-tronador&tab=database' ) );
        exit;
    }

    /**
     * Show a notice with database cleanup results.
     */
    public function display_db_cleanup_notice(): void {
        $results = get_transient( 'etr_db_cleanup_results' );
        if ( ! $results || ! is_array( $results ) ) {
            return;
        }

        delete_transient( 'etr_db_cleanup_results' );

        $messages = [];
        if ( isset( $results['revisions'] ) ) {
            $messages[] = sprintf( __( 'Revisiones: %d eliminadas', 'el-tronador' ), $results['revisions'] );
        }
        if ( isset( $results['drafts'] ) ) {
            $messages[] = sprintf( __( 'Borradores/Papelera: %d eliminados', 'el-tronador' ), $results['drafts'] );
        }
        if ( isset( $results['spam'] ) ) {
            $messages[] = sprintf( __( 'Spam/Papelera comentarios: %d eliminados', 'el-tronador' ), $results['spam'] );
        }
        if ( isset( $results['transients'] ) ) {
            $messages[] = sprintf( __( 'Transients expirados: %d eliminados', 'el-tronador' ), $results['transients'] );
        }

        if ( empty( $messages ) ) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo '<strong>' . esc_html__( 'El Tronador — Limpieza completada:', 'el-tronador' ) . '</strong><br>';
        echo esc_html( implode( ' | ', $messages ) );
        echo '</p></div>';
    }

    /**
     * Handle the "Run Preload Now" action.
     */
    public function handle_preload_request(): void {
        if ( ! isset( $_POST['etr_preload_now'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'etr_preload_now' ) ) {
            wp_die( __( 'Unauthorized request.', 'el-tronador' ) );
        }

        $engine = new ETR_Preload_Engine();
        $count  = $engine->build_queue();

        set_transient( 'etr_preload_result', $count, 60 );

        wp_safe_redirect( wp_get_referer() ?: admin_url( 'options-general.php?page=el-tronador&tab=preload' ) );
        exit;
    }

    /**
     * Show a notice with preload results.
     */
    public function display_preload_notice(): void {
        $count = get_transient( 'etr_preload_result' );
        if ( false === $count ) {
            return;
        }

        delete_transient( 'etr_preload_result' );

        echo '<div class="notice notice-success is-dismissible"><p>';
        printf(
            /* translators: %d: number of URLs queued */
            esc_html__( 'El Tronador — Precarga iniciada: %d URLs en cola. Se procesarán en lotes cada 5 minutos.', 'el-tronador' ),
            (int) $count
        );
        echo '</p></div>';
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
            'database'          => __( 'Base de Datos', 'el-tronador' ),
            'preload'           => __( 'Precarga', 'el-tronador' ),
        ];
    }
}
