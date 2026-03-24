<?php
/**
 * Cache filesystem operations — read, write, and purge cached HTML files.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Cache_Filesystem {

    /**
     * Cache TTL in seconds (10 hours — keeps CF7 nonces valid).
     */
    public const TTL = 36000;

    /**
     * Build the cache file path for a given host and URI.
     *
     * @param string $host        Server hostname.
     * @param string $request_uri Request URI.
     * @return string Absolute file path.
     */
    public static function get_cache_path( string $host, string $request_uri ): string {
        // Strip query string and sanitize path components.
        $uri  = strtok( $request_uri, '?' );
        $uri  = rtrim( $uri, '/' );
        $uri  = $uri ?: '/';
        $host = preg_replace( '/[^a-zA-Z0-9._-]/', '', $host );
        $uri  = preg_replace( '/[^a-zA-Z0-9\/._-]/', '', $uri );

        return ETR_CACHE_DIR . $host . $uri . '/index.html';
    }

    /**
     * Write HTML content to the cache file.
     *
     * @param string $host        Server hostname.
     * @param string $request_uri Request URI.
     * @param string $html        HTML content to cache.
     * @return bool True on success.
     */
    public static function write( string $host, string $request_uri, string $html ): bool {
        $cache_file = self::get_cache_path( $host, $request_uri );
        $cache_dir  = dirname( $cache_file );

        if ( ! is_dir( $cache_dir ) ) {
            if ( ! wp_mkdir_p( $cache_dir ) ) {
                return false;
            }
        }

        // Append cache signature comment.
        $html .= "\n<!-- Cached by El Tronador v" . ETR_VERSION . ' @ ' . gmdate( 'Y-m-d H:i:s' ) . " UTC -->\n";

        return (bool) file_put_contents( $cache_file, $html, LOCK_EX );
    }

    /**
     * Purge a single cached URL.
     *
     * @param string $host        Server hostname.
     * @param string $request_uri Request URI.
     */
    public static function purge_url( string $host, string $request_uri ): void {
        $cache_file = self::get_cache_path( $host, $request_uri );

        if ( file_exists( $cache_file ) ) {
            @unlink( $cache_file );
        }
    }

    /**
     * Purge the entire cache directory.
     */
    public static function purge_all(): void {
        ETR_Utils::delete_directory( ETR_CACHE_DIR );

        // Also flush object cache for Object Cache Pro / Redis compatibility.
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }
}
