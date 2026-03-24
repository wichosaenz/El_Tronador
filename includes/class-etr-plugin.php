<?php
/**
 * Core plugin singleton — bootstraps modules and admin.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Plugin {

    /**
     * Singleton instance.
     */
    private static ?ETR_Plugin $instance = null;

    /**
     * Module registry.
     */
    private ETR_Module_Registry $registry;

    /**
     * Compatibility checker.
     */
    private ETR_Compatibility $compatibility;

    /**
     * Get or create the singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->registry      = new ETR_Module_Registry();
        $this->compatibility = new ETR_Compatibility();
    }

    /**
     * Initialize the plugin.
     */
    public function init(): void {
        // Run compatibility checks.
        $this->compatibility->init();

        // Register modules.
        $this->register_modules();

        // Boot enabled modules.
        $this->registry->boot_all();

        // Load admin when in dashboard.
        if ( is_admin() ) {
            $admin = new ETR_Admin();
            $admin->init();
        }

        // Admin bar cache purge button (frontend + backend).
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_purge' ], 100 );
        add_action( 'admin_init', [ $this, 'handle_purge_request' ] );
    }

    /**
     * Register all available modules.
     */
    private function register_modules(): void {
        $page_cache = new ETR_Page_Cache( $this->compatibility );
        $this->registry->register( $page_cache );

        $delay_js = new ETR_Delay_Js();
        $this->registry->register( $delay_js );

        $file_optimizer = new ETR_File_Optimizer();
        $this->registry->register( $file_optimizer );

        $media_optimizer = new ETR_Media_Optimizer();
        $this->registry->register( $media_optimizer );
    }

    /**
     * Get the module registry.
     *
     * @return ETR_Module_Registry
     */
    public function get_registry(): ETR_Module_Registry {
        return $this->registry;
    }

    /**
     * Add a "Purge Cache" button to the admin bar.
     *
     * @param \WP_Admin_Bar $admin_bar Admin bar instance.
     */
    public function add_admin_bar_purge( \WP_Admin_Bar $admin_bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $admin_bar->add_node( [
            'id'    => 'etr-purge-cache',
            'title' => '🚀 El Tronador — Purge Cache',
            'href'  => wp_nonce_url( admin_url( 'admin.php?action=etr_purge_cache' ), 'etr_purge_cache' ),
            'meta'  => [ 'title' => __( 'Purge all El Tronador cache', 'el-tronador' ) ],
        ] );
    }

    /**
     * Handle the cache purge admin action.
     */
    public function handle_purge_request(): void {
        if ( ! isset( $_GET['action'] ) || 'etr_purge_cache' !== $_GET['action'] ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'etr_purge_cache' ) ) {
            wp_die( __( 'Unauthorized request.', 'el-tronador' ) );
        }

        // Purge static disk cache.
        ETR_Cache_Filesystem::purge_all();

        // Flush object cache (Object Cache Pro / Redis compatibility).
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        // Redirect back with success notice.
        set_transient( 'etr_cache_purged', true, 30 );

        wp_safe_redirect( wp_get_referer() ?: admin_url() );
        exit;
    }
}
