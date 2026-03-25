<?php
/**
 * Options API wrapper for El Tronador settings.
 *
 * All plugin settings are stored in a single serialized option.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Admin_Options {

    /**
     * WordPress option name.
     */
    public const OPTION_KEY = 'el_tronador_settings';

    /**
     * Default settings values.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array {
        return [
            'page_cache_enabled'            => true,
            'delay_js_enabled'              => true,
            'minify_css_enabled'            => false,
            'minify_js_enabled'             => false,
            'optimize_css_delivery_enabled' => false,
            'file_optimization_exclusions'  => '',
            'lazy_images_enabled'           => false,
            'lazy_iframes_enabled'          => false,
            'youtube_facade_enabled'        => false,
            'media_lazy_exclusions'         => '',
            'db_clean_revisions'            => false,
            'db_clean_drafts'               => false,
            'db_clean_spam'                 => false,
            'db_clean_transients'           => false,
            'db_auto_cleanup'               => false,
        ];
    }

    /**
     * Get all settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function get_all(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        return array_merge( self::defaults(), $saved );
    }

    /**
     * Get a single setting value.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Fallback if key is missing.
     * @return mixed
     */
    public static function get( string $key, mixed $default = null ): mixed {
        $settings = self::get_all();
        return $settings[ $key ] ?? $default;
    }

    /**
     * Set a single setting value and persist.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     */
    public static function set( string $key, mixed $value ): void {
        $settings         = self::get_all();
        $settings[ $key ] = $value;
        self::save( $settings );
    }

    /**
     * Save the full settings array.
     *
     * @param array<string, mixed> $settings Settings to save.
     */
    public static function save( array $settings ): void {
        update_option( self::OPTION_KEY, $settings );
    }

    /**
     * Delete all plugin settings from the database.
     */
    public static function delete(): void {
        delete_option( self::OPTION_KEY );
    }
}
