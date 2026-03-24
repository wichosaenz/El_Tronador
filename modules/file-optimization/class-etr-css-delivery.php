<?php
/**
 * CSS delivery optimization — converts render-blocking stylesheets to async preloads.
 *
 * Transforms <link rel="stylesheet"> tags into <link rel="preload" as="style" onload="...">
 * with a <noscript> fallback for non-JavaScript browsers.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Css_Delivery {

    /**
     * User-defined exclusion keywords (one per line).
     *
     * @var string[]
     */
    private array $exclusions;

    /**
     * @param string[] $exclusions Keywords/URLs to exclude from optimization.
     */
    public function __construct( array $exclusions = [] ) {
        $this->exclusions = $exclusions;
    }

    /**
     * Process HTML and convert stylesheets to async preloads.
     *
     * @param string $html Full page HTML.
     * @return string Modified HTML.
     */
    public function optimize_stylesheets( string $html ): string {
        return preg_replace_callback(
            '/<link\b([^>]*)\brel\s*=\s*["\']stylesheet["\']([^>]*)\/?\s*>/i',
            [ $this, 'transform_stylesheet' ],
            $html
        );
    }

    /**
     * Callback to transform a single <link rel="stylesheet"> tag.
     *
     * @param array<int, string> $matches Regex matches.
     * @return string Transformed tag or original if excluded.
     */
    private function transform_stylesheet( array $matches ): string {
        $full_tag   = $matches[0];
        $all_attrs  = $matches[1] . ' rel="stylesheet"' . $matches[2];

        // Extract href for exclusion checking.
        if ( ! preg_match( '/href\s*=\s*["\']([^"\']+)["\']/', $all_attrs, $href_match ) ) {
            return $full_tag;
        }

        $href = $href_match[1];

        // Skip admin stylesheets.
        if ( str_contains( $href, 'wp-admin' ) ) {
            return $full_tag;
        }

        // Skip print-only stylesheets (already non-blocking).
        if ( preg_match( '/media\s*=\s*["\']print["\']/', $all_attrs ) ) {
            return $full_tag;
        }

        // Check user exclusions.
        foreach ( $this->exclusions as $exclusion ) {
            $exclusion = trim( $exclusion );
            if ( '' !== $exclusion && str_contains( $href, $exclusion ) ) {
                return $full_tag;
            }
        }

        // Build the preload tag — replace rel="stylesheet" with rel="preload" + as="style" + onload.
        $preload_tag = preg_replace(
            '/rel\s*=\s*["\']stylesheet["\']/',
            'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"',
            $full_tag
        );

        // Build noscript fallback (original tag unchanged).
        $noscript = '<noscript>' . $full_tag . '</noscript>';

        return $preload_tag . "\n" . $noscript;
    }
}
