<?php
/**
 * El Tronador — Uninstall script.
 *
 * Fired when the plugin is deleted from WordPress.
 * Cleans up all plugin data: options, cache files, and the advanced-cache.php drop-in.
 *
 * @package ElTronador
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'el_tronador_settings' );
delete_transient( 'etr_breeze_conflict' );
delete_transient( 'etr_cache_purged' );

// Remove cache directory.
$cache_dir = WP_CONTENT_DIR . '/cache/el-tronador/';
if ( is_dir( $cache_dir ) ) {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $cache_dir, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ( $items as $item ) {
        if ( $item->isDir() ) {
            @rmdir( $item->getPathname() );
        } else {
            @unlink( $item->getPathname() );
        }
    }

    @rmdir( $cache_dir );
}

// Remove advanced-cache.php drop-in (only if it's ours).
$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
if ( file_exists( $dropin ) ) {
    $content = file_get_contents( $dropin );
    if ( strpos( $content, 'El Tronador' ) !== false ) {
        @unlink( $dropin );
    }
}

// Remove WP_CACHE constant from wp-config.php.
$config_path = ABSPATH . 'wp-config.php';
if ( file_exists( $config_path ) && is_writable( $config_path ) ) {
    $config = file_get_contents( $config_path );
    $config = preg_replace(
        '/\n?\s*define\s*\(\s*[\'"]WP_CACHE[\'"]\s*,\s*(true|false)\s*\)\s*;\s*\/\/\s*El Tronador\s*\n?/',
        "\n",
        $config
    );
    file_put_contents( $config_path, $config, LOCK_EX );
}
