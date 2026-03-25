<?php
/**
 * File Optimization module — CSS/JS minification and CSS delivery optimization.
 *
 * Hooks into the output-buffering pipeline (priority 50, between Page Cache at 0
 * and Delay JS at 99) to minify local CSS/JS files and convert render-blocking
 * stylesheets to async preloads.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_File_Optimizer implements ETR_Module_Interface {

    /**
     * Cache subdirectory for minified files.
     */
    private const MIN_DIR = 'min/';

    /**
     * {@inheritdoc}
     */
    public function get_id(): string {
        return 'file_optimization';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return __( 'Optimización de Archivos', 'el-tronador' );
    }

    /**
     * {@inheritdoc}
     */
    public function is_enabled(): bool {
        return (bool) ETR_Admin_Options::get( 'minify_css_enabled', false )
            || (bool) ETR_Admin_Options::get( 'minify_js_enabled', false )
            || (bool) ETR_Admin_Options::get( 'optimize_css_delivery_enabled', false );
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        // Register as a processor in the unified output buffer (priority 50).
        ETR_Output_Buffer::instance()->register_processor( [ $this, 'process_html' ], 50 );
    }

    /**
     * Process the buffered HTML — minify assets and optimize CSS delivery.
     *
     * @param string $html Full page HTML.
     * @return string Modified HTML.
     */
    public function process_html( string $html ): string {
        if ( ! str_contains( $html, '</html>' ) ) {
            return $html;
        }

        $exclusions = $this->get_exclusions();

        // 1. Minify CSS files.
        if ( ETR_Admin_Options::get( 'minify_css_enabled', false ) ) {
            $html = $this->minify_css_assets( $html, $exclusions );
        }

        // 2. Minify JS files.
        if ( ETR_Admin_Options::get( 'minify_js_enabled', false ) ) {
            $html = $this->minify_js_assets( $html, $exclusions );
        }

        // 3. Optimize CSS delivery (preload pattern).
        if ( ETR_Admin_Options::get( 'optimize_css_delivery_enabled', false ) ) {
            $delivery = new ETR_Css_Delivery( $exclusions );
            $html     = $delivery->optimize_stylesheets( $html );
        }

        return $html;
    }

    /**
     * Find local <link rel="stylesheet"> tags, minify the files, and replace URLs.
     *
     * @param string   $html       Page HTML.
     * @param string[] $exclusions User exclusion keywords.
     * @return string Modified HTML.
     */
    private function minify_css_assets( string $html, array $exclusions ): string {
        return preg_replace_callback(
            '/<link\b([^>]*)\brel\s*=\s*["\']stylesheet["\']([^>]*)\/?\s*>/i',
            function ( array $matches ) use ( $exclusions ): string {
                return $this->process_css_tag( $matches, $exclusions );
            },
            $html
        );
    }

    /**
     * Process a single CSS <link> tag for minification.
     *
     * @param array<int, string> $matches    Regex matches.
     * @param string[]           $exclusions User exclusion keywords.
     * @return string Modified or original tag.
     */
    private function process_css_tag( array $matches, array $exclusions ): string {
        $full_tag = $matches[0];
        $attrs    = $matches[1] . $matches[2];

        $href = $this->extract_href( $attrs );
        if ( null === $href ) {
            return $full_tag;
        }

        // Skip already-minified files.
        if ( str_contains( $href, '.min.css' ) ) {
            return $full_tag;
        }

        // Skip non-local files.
        if ( ! $this->is_local_url( $href ) ) {
            return $full_tag;
        }

        // Check user exclusions.
        if ( $this->matches_exclusion( $href, $exclusions ) ) {
            return $full_tag;
        }

        $local_path = $this->url_to_path( $href );
        if ( null === $local_path || ! file_exists( $local_path ) ) {
            return $full_tag;
        }

        $minified_url = $this->get_or_create_minified( $local_path, 'css' );
        if ( null === $minified_url ) {
            return $full_tag;
        }

        return str_replace( $href, $minified_url, $full_tag );
    }

    /**
     * Find local <script src="..."> tags, minify the files, and replace URLs.
     *
     * @param string   $html       Page HTML.
     * @param string[] $exclusions User exclusion keywords.
     * @return string Modified HTML.
     */
    private function minify_js_assets( string $html, array $exclusions ): string {
        return preg_replace_callback(
            '/<script\b([^>]*)\bsrc\s*=\s*["\']([^"\']+)["\']([^>]*)>/i',
            function ( array $matches ) use ( $exclusions ): string {
                return $this->process_js_tag( $matches, $exclusions );
            },
            $html
        );
    }

    /**
     * Process a single <script src="..."> tag for minification.
     *
     * @param array<int, string> $matches    Regex matches.
     * @param string[]           $exclusions User exclusion keywords.
     * @return string Modified or original tag.
     */
    private function process_js_tag( array $matches, array $exclusions ): string {
        $full_tag = $matches[0];
        $src      = $matches[2];

        // Skip already-minified files.
        if ( str_contains( $src, '.min.js' ) ) {
            return $full_tag;
        }

        // Skip non-local files.
        if ( ! $this->is_local_url( $src ) ) {
            return $full_tag;
        }

        // Check user exclusions.
        if ( $this->matches_exclusion( $src, $exclusions ) ) {
            return $full_tag;
        }

        $local_path = $this->url_to_path( $src );
        if ( null === $local_path || ! file_exists( $local_path ) ) {
            return $full_tag;
        }

        $minified_url = $this->get_or_create_minified( $local_path, 'js' );
        if ( null === $minified_url ) {
            return $full_tag;
        }

        return str_replace( $src, $minified_url, $full_tag );
    }

    /**
     * Get or create a minified version of a local file.
     *
     * Returns the public URL of the cached minified file.
     * Re-generates only when the source file is newer than the cached version.
     *
     * @param string $source_path Absolute path to the original file.
     * @param string $type        File type: 'css' or 'js'.
     * @return string|null Public URL or null on failure.
     */
    private function get_or_create_minified( string $source_path, string $type ): ?string {
        $content = file_get_contents( $source_path );
        if ( false === $content || '' === trim( $content ) ) {
            return null;
        }

        // Build output filename: originalname-{hash}.min.{ext}
        $basename = pathinfo( $source_path, PATHINFO_FILENAME );
        $hash     = substr( md5( $content ), 0, 12 );
        $ext      = 'css' === $type ? 'css' : 'js';
        $filename = $basename . '-' . $hash . '.min.' . $ext;

        $min_dir  = ETR_CACHE_DIR . self::MIN_DIR;
        $min_path = $min_dir . $filename;

        // Return cached version if source hasn't changed.
        if ( file_exists( $min_path ) && filemtime( $min_path ) >= filemtime( $source_path ) ) {
            return content_url( '/cache/el-tronador/' . self::MIN_DIR . $filename );
        }

        // Create the min/ directory if needed.
        if ( ! is_dir( $min_dir ) ) {
            wp_mkdir_p( $min_dir );
        }

        // Minify.
        $minified = 'css' === $type
            ? ETR_Minifier::minify_css( $content )
            : ETR_Minifier::minify_js( $content );

        if ( '' === $minified ) {
            return null;
        }

        // Write minified file.
        $written = file_put_contents( $min_path, $minified, LOCK_EX );
        if ( false === $written ) {
            return null;
        }

        return content_url( '/cache/el-tronador/' . self::MIN_DIR . $filename );
    }

    /**
     * Extract the href attribute value from a tag's attributes string.
     *
     * @param string $attrs Attributes string.
     * @return string|null Href value or null.
     */
    private function extract_href( string $attrs ): ?string {
        if ( preg_match( '/href\s*=\s*["\']([^"\']+)["\']/', $attrs, $m ) ) {
            return $m[1];
        }
        return null;
    }

    /**
     * Check if a URL points to a local file (within wp-content or wp-includes).
     *
     * @param string $url Asset URL.
     * @return bool
     */
    private function is_local_url( string $url ): bool {
        // Relative URLs starting with /wp-content/ or /wp-includes/.
        if ( preg_match( '#^/wp-(content|includes)/#', $url ) ) {
            return true;
        }

        // Absolute URLs containing the site domain.
        $site_url = site_url();
        if ( str_starts_with( $url, $site_url ) ) {
            $path = wp_parse_url( $url, PHP_URL_PATH ) ?? '';
            return (bool) preg_match( '#^/wp-(content|includes)/#', $path );
        }

        return false;
    }

    /**
     * Convert an asset URL to a local filesystem path.
     *
     * @param string $url Asset URL (absolute or root-relative).
     * @return string|null Absolute filesystem path or null.
     */
    private function url_to_path( string $url ): ?string {
        // Extract the path component.
        $path = wp_parse_url( $url, PHP_URL_PATH );
        if ( null === $path || '' === $path ) {
            return null;
        }

        // Remove query strings that might have leaked through.
        $path = strtok( $path, '?' );

        // Build absolute path from ABSPATH.
        $abs_path = ABSPATH . ltrim( $path, '/' );

        // Security: ensure the resolved path stays within ABSPATH.
        $real = realpath( $abs_path );
        if ( false === $real || ! str_starts_with( $real, realpath( ABSPATH ) ) ) {
            return null;
        }

        return $real;
    }

    /**
     * Check if a URL matches any user-defined exclusion keyword.
     *
     * @param string   $url        Asset URL.
     * @param string[] $exclusions Exclusion keywords.
     * @return bool
     */
    private function matches_exclusion( string $url, array $exclusions ): bool {
        foreach ( $exclusions as $keyword ) {
            $keyword = trim( $keyword );
            if ( '' !== $keyword && str_contains( $url, $keyword ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get user-defined exclusion keywords from settings.
     *
     * @return string[]
     */
    private function get_exclusions(): array {
        $raw = ETR_Admin_Options::get( 'file_optimization_exclusions', '' );
        if ( '' === $raw ) {
            return [];
        }

        $lines = explode( "\n", $raw );
        return array_filter( array_map( 'trim', $lines ), fn( string $line ) => '' !== $line );
    }
}
