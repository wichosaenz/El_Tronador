<?php
/**
 * PSR-4-like autoloader for El Tronador classes.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Autoloader {

    /**
     * Map of class prefixes to directory search paths.
     *
     * @var array<string, string[]>
     */
    private static array $search_dirs = [];

    /**
     * Register the autoloader with spl_autoload_register.
     */
    public static function register(): void {
        $base = ETR_PLUGIN_DIR;

        self::$search_dirs = [
            'includes' => [ $base . 'includes/' ],
            'admin'    => [ $base . 'admin/' ],
            'modules'  => [
                $base . 'modules/page-cache/',
                $base . 'modules/delay-js/',
                $base . 'modules/file-optimization/',
                $base . 'modules/media-optimizer/',
                $base . 'modules/database-optimizer/',
            ],
        ];

        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    /**
     * Autoload callback.
     *
     * Converts a class name like ETR_Page_Cache to class-etr-page-cache.php
     * and searches registered directories.
     *
     * @param string $class_name The fully-qualified class name.
     */
    public static function autoload( string $class_name ): void {
        // Only handle our own classes.
        if ( strpos( $class_name, 'ETR_' ) !== 0 ) {
            return;
        }

        $file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

        foreach ( self::$search_dirs as $dirs ) {
            foreach ( $dirs as $dir ) {
                $file_path = $dir . $file_name;
                if ( file_exists( $file_path ) ) {
                    require_once $file_path;
                    return;
                }
            }
        }
    }
}
