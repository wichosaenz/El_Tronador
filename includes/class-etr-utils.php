<?php
/**
 * Shared utility helpers.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Utils {

    /**
     * Get the base cache directory path.
     *
     * @return string
     */
    public static function get_cache_dir(): string {
        return ETR_CACHE_DIR;
    }

    /**
     * Recursively delete a directory and its contents.
     *
     * @param string $path Absolute path to directory.
     * @return bool True on success.
     */
    public static function delete_directory( string $path ): bool {
        if ( ! is_dir( $path ) ) {
            return false;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $items as $item ) {
            if ( $item->isDir() ) {
                @rmdir( $item->getPathname() );
            } else {
                @unlink( $item->getPathname() );
            }
        }

        return @rmdir( $path );
    }

    /**
     * Get the list of URL paths that should never be cached.
     *
     * @return string[]
     */
    public static function get_excluded_urls(): array {
        $exclusions = [
            '/wp-login.php',
            '/wp-admin',
            '/wp-cron.php',
            '/xmlrpc.php',
            '/wp-json',
        ];

        // WPS Hide Login: exclude the custom login slug.
        $whl_page = get_option( 'whl_page', '' );
        if ( ! empty( $whl_page ) ) {
            $exclusions[] = '/' . ltrim( $whl_page, '/' );
        }

        return $exclusions;
    }

    /**
     * Check whether a given URI matches an exclusion pattern.
     *
     * @param string $request_uri The request URI to test.
     * @return bool
     */
    public static function is_excluded_url( string $request_uri ): bool {
        $path = strtok( $request_uri, '?' ); // Strip query string for matching.

        foreach ( self::get_excluded_urls() as $exclusion ) {
            if ( str_starts_with( $path, $exclusion ) ) {
                return true;
            }
        }

        return false;
    }
}
