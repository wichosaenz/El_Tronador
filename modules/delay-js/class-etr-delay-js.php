<?php
/**
 * Delay JS module — delays non-critical JavaScript execution until user interaction.
 *
 * Uses output buffering to intercept the final HTML and modify <script> tags.
 * Scripts are deferred until the first scroll, mousemove, touchstart, keydown, or click event.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Delay_Js implements ETR_Module_Interface {

    /**
     * Script src patterns to EXCLUDE from delay (these scripts must load immediately).
     *
     * @var string[]
     */
    private const EXCLUDED_PATTERNS = [
        'contact-form-7',
    ];

    /**
     * Custom type attribute used to prevent browser execution.
     */
    private const DELAY_TYPE = 'etr-delay/javascript';

    /**
     * {@inheritdoc}
     */
    public function get_id(): string {
        return 'delay_js';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return __( 'Delay JavaScript', 'el-tronador' );
    }

    /**
     * {@inheritdoc}
     */
    public function is_enabled(): bool {
        return (bool) ETR_Admin_Options::get( 'delay_js_enabled', true );
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void {
        // Only process on the frontend for non-admin pages.
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        // Register as a processor in the unified output buffer (priority 99).
        ETR_Output_Buffer::instance()->register_processor( [ $this, 'process_html' ], 99 );
    }

    /**
     * Process the buffered HTML — find and delay script tags.
     *
     * @param string $html Full page HTML.
     * @return string Modified HTML with delayed scripts.
     */
    public function process_html( string $html ): string {
        // Only process complete HTML documents.
        if ( ! str_contains( $html, '</html>' ) ) {
            return $html;
        }

        // Match all <script> tags (both self-closing with src and inline).
        $html = preg_replace_callback(
            '/<script\b([^>]*)>(.*?)<\/script>/is',
            [ $this, 'delay_script_tag' ],
            $html
        );

        // Inject the loader script before </body>.
        $loader = $this->get_loader_script();
        $html   = str_replace( '</body>', $loader . "\n</body>", $html );

        return $html;
    }

    /**
     * Callback for each matched <script> tag — decide whether to delay it.
     *
     * @param array<int, string> $matches Regex matches.
     * @return string Modified or unmodified script tag.
     */
    private function delay_script_tag( array $matches ): string {
        $full_tag   = $matches[0];
        $attributes = $matches[1];
        $content    = $matches[2];

        // Skip scripts that are already our delay type.
        if ( str_contains( $attributes, self::DELAY_TYPE ) ) {
            return $full_tag;
        }

        // Check exclusion patterns against both src attribute and inline content.
        foreach ( self::EXCLUDED_PATTERNS as $pattern ) {
            if ( str_contains( $attributes, $pattern ) || str_contains( $content, $pattern ) ) {
                return $full_tag;
            }
        }

        // Skip scripts with type attributes that aren't JavaScript (e.g., application/json, application/ld+json).
        if ( preg_match( '/type\s*=\s*["\']([^"\']*)["\']/', $attributes, $type_match ) ) {
            $type = strtolower( trim( $type_match[1] ) );
            if ( $type !== '' && $type !== 'text/javascript' && $type !== 'application/javascript' && $type !== 'module' ) {
                return $full_tag;
            }
        }

        // External script: rename src → data-etr-src.
        if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/', $attributes, $src_match ) ) {
            $new_attributes = str_replace( $src_match[0], 'data-etr-src="' . esc_attr( $src_match[1] ) . '"', $attributes );
            $new_attributes = $this->replace_or_add_type( $new_attributes );
            return '<script' . $new_attributes . '>' . $content . '</script>';
        }

        // Inline script with actual content.
        if ( trim( $content ) !== '' ) {
            $new_attributes = $this->replace_or_add_type( $attributes );
            return '<script' . $new_attributes . '>' . $content . '</script>';
        }

        // Empty script or unrecognized pattern — leave as-is.
        return $full_tag;
    }

    /**
     * Replace existing type attribute or add the delay type.
     *
     * @param string $attributes Script tag attributes string.
     * @return string Modified attributes.
     */
    private function replace_or_add_type( string $attributes ): string {
        if ( preg_match( '/type\s*=\s*["\'][^"\']*["\']/', $attributes ) ) {
            return preg_replace(
                '/type\s*=\s*["\'][^"\']*["\']/',
                'type="' . self::DELAY_TYPE . '"',
                $attributes
            );
        }

        return ' type="' . self::DELAY_TYPE . '"' . $attributes;
    }

    /**
     * Get the inline loader script that fires delayed scripts on user interaction.
     *
     * @return string Script tag with loader JS.
     */
    private function get_loader_script(): string {
        $js_path = ETR_PLUGIN_DIR . 'modules/delay-js/assets/etr-delay-js-loader.js';

        if ( ! file_exists( $js_path ) ) {
            return '';
        }

        $js = file_get_contents( $js_path );

        return '<script id="etr-delay-js-loader" type="text/javascript">' . $js . '</script>';
    }
}
