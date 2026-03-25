<?php
/**
 * Page Cache module — generates and serves static HTML cache files.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Page_Cache implements ETR_Module_Interface {

    /**
     * Compatibility checker instance.
     */
    private ETR_Compatibility $compatibility;

    /**
     * @param ETR_Compatibility $compatibility Compatibility checker.
     */
    public function __construct( ETR_Compatibility $compatibility ) {
        $this->compatibility = $compatibility;
    }

    /**
     * {@inheritdoc}
     */
    public function get_id(): string {
        return 'page_cache';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return __( 'Page Cache', 'el-tronador' );
    }

    /**
     * {@inheritdoc}
     */
    public function is_enabled(): bool {
        // Disabled if Breeze conflict exists.
        if ( $this->compatibility->is_page_cache_blocked() ) {
            return false;
        }

        return (bool) ETR_Admin_Options::get( 'page_cache_enabled', true );
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void {
        // Only cache on the frontend for non-logged-in users.
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        add_action( 'template_redirect', [ $this, 'maybe_start_buffer' ], 0 );

        // Purge cache when posts are updated.
        add_action( 'save_post', [ $this, 'purge_post_cache' ], 10, 1 );
        add_action( 'comment_post', [ $this, 'purge_post_cache_from_comment' ], 10, 2 );
    }

    /**
     * Start output buffering if the request is cacheable.
     */
    public function maybe_start_buffer(): void {
        if ( ! $this->is_request_cacheable() ) {
            return;
        }

        // Tell the unified buffer to write the result to disk.
        ETR_Output_Buffer::instance()->enable_cache();
    }

    /**
     * Determine whether the current request should be cached.
     *
     * @return bool
     */
    private function is_request_cacheable(): bool {
        // Skip POST requests.
        if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            return false;
        }

        // Skip logged-in users (check WordPress login cookies).
        foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
            if ( str_starts_with( $cookie_name, 'wordpress_logged_in_' ) ) {
                return false;
            }
        }

        // Skip excluded URLs (WPS Hide Login, wp-login, wp-admin, etc.).
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        if ( ETR_Utils::is_excluded_url( $request_uri ) ) {
            return false;
        }

        // Skip if a query string is present (dynamic pages).
        if ( ! empty( $_GET ) && ! isset( $_GET['utm_source'] ) ) {
            // Allow UTM parameters but skip other query strings.
            $non_utm_params = array_filter(
                array_keys( $_GET ),
                fn( $key ) => ! str_starts_with( $key, 'utm_' )
            );
            if ( ! empty( $non_utm_params ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Purge cache for a specific post when it's updated.
     *
     * @param int $post_id Post ID.
     */
    public function purge_post_cache( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return;
        }

        $parsed = wp_parse_url( $permalink );
        $host   = $parsed['host'] ?? ( $_SERVER['HTTP_HOST'] ?? '' );
        $uri    = $parsed['path'] ?? '/';

        ETR_Cache_Filesystem::purge_url( $host, $uri );

        // Also purge the homepage.
        ETR_Cache_Filesystem::purge_url( $host, '/' );
    }

    /**
     * Purge post cache when a comment is submitted.
     *
     * @param int        $comment_id Comment ID.
     * @param int|string $approved   Approval status.
     */
    public function purge_post_cache_from_comment( int $comment_id, int|string $approved ): void {
        $comment = get_comment( $comment_id );
        if ( $comment && $comment->comment_post_ID ) {
            $this->purge_post_cache( (int) $comment->comment_post_ID );
        }
    }

    /**
     * Install the advanced-cache.php drop-in and enable WP_CACHE.
     */
    public static function install_advanced_cache(): void {
        $dropin_path   = WP_CONTENT_DIR . '/advanced-cache.php';
        $template_path = ETR_PLUGIN_DIR . 'modules/page-cache/advanced-cache-template.php';

        if ( ! file_exists( $template_path ) ) {
            return;
        }

        $template = file_get_contents( $template_path );

        // Replace placeholder with actual cache directory.
        $template = str_replace( '{{ETR_CACHE_DIR}}', ETR_CACHE_DIR, $template );
        $template = str_replace( '{{ETR_VERSION}}', ETR_VERSION, $template );

        file_put_contents( $dropin_path, $template, LOCK_EX );

        self::set_wp_cache_constant( true );
    }

    /**
     * Remove the advanced-cache.php drop-in and disable WP_CACHE.
     */
    public static function uninstall_advanced_cache(): void {
        $dropin_path = WP_CONTENT_DIR . '/advanced-cache.php';

        if ( file_exists( $dropin_path ) ) {
            $content = file_get_contents( $dropin_path );
            // Only remove if it's ours.
            if ( str_contains( $content, 'El Tronador' ) ) {
                @unlink( $dropin_path );
            }
        }

        self::set_wp_cache_constant( false );
    }

    /**
     * Add or remove the WP_CACHE constant in wp-config.php.
     *
     * @param bool $enable True to enable, false to disable.
     */
    private static function set_wp_cache_constant( bool $enable ): void {
        $config_path = ABSPATH . 'wp-config.php';

        if ( ! file_exists( $config_path ) || ! is_writable( $config_path ) ) {
            return;
        }

        $config = file_get_contents( $config_path );

        // Remove existing WP_CACHE definition if present.
        $config = preg_replace(
            '/\n?\s*define\s*\(\s*[\'"]WP_CACHE[\'"]\s*,\s*(true|false)\s*\)\s*;\s*\/\/\s*El Tronador\s*\n?/',
            "\n",
            $config
        );

        if ( $enable ) {
            // Insert before "That's all, stop editing!" or before the first require/include.
            $anchor = "/* That's all, stop editing!";
            if ( str_contains( $config, $anchor ) ) {
                $config = str_replace(
                    $anchor,
                    "define( 'WP_CACHE', true ); // El Tronador\n\n" . $anchor,
                    $config
                );
            }
        }

        file_put_contents( $config_path, $config, LOCK_EX );
    }
}
