<?php
/**
 * CSS and JS minification engine.
 *
 * Pure PHP minifier — no external dependencies. Removes comments,
 * whitespace and line breaks while preserving string literals.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Minifier {

    /**
     * Minify CSS content.
     *
     * Removes comments, excess whitespace, and collapses rules.
     * Preserves content inside string literals (quotes).
     *
     * @param string $css Raw CSS content.
     * @return string Minified CSS.
     */
    public static function minify_css( string $css ): string {
        if ( '' === trim( $css ) ) {
            return '';
        }

        // Preserve @charset declarations (must be first in file).
        $charset = '';
        if ( preg_match( '/^(\s*@charset\s+[^;]+;\s*)/i', $css, $m ) ) {
            $charset = trim( $m[1] ) . "\n";
            $css     = substr( $css, strlen( $m[0] ) );
        }

        // Extract and protect string literals (single and double quoted).
        $strings = [];
        $css     = preg_replace_callback(
            '/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')/',
            function ( array $match ) use ( &$strings ): string {
                $placeholder             = '___ETR_STR_' . count( $strings ) . '___';
                $strings[ $placeholder ] = $match[0];
                return $placeholder;
            },
            $css
        );

        // Remove CSS comments.
        $css = preg_replace( '/\/\*[\s\S]*?\*\//', '', $css );

        // Collapse whitespace.
        $css = preg_replace( '/\s+/', ' ', $css );

        // Remove spaces around structural characters.
        $css = preg_replace( '/\s*([{}:;,>~+])\s*/', '$1', $css );

        // Remove trailing semicolons before closing braces.
        $css = str_replace( ';}', '}', $css );

        // Remove leading/trailing whitespace.
        $css = trim( $css );

        // Restore string literals.
        $css = str_replace( array_keys( $strings ), array_values( $strings ), $css );

        return $charset . $css;
    }

    /**
     * Minify JavaScript content.
     *
     * Removes comments and excess whitespace while preserving
     * string literals and regex patterns. Does NOT rename variables.
     *
     * @param string $js Raw JavaScript content.
     * @return string Minified JavaScript.
     */
    public static function minify_js( string $js ): string {
        if ( '' === trim( $js ) ) {
            return '';
        }

        // Extract and protect string literals (single, double, template).
        $strings = [];
        $js      = preg_replace_callback(
            '/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`)/',
            function ( array $match ) use ( &$strings ): string {
                $placeholder             = '___ETR_STR_' . count( $strings ) . '___';
                $strings[ $placeholder ] = $match[0];
                return $placeholder;
            },
            $js
        );

        // Remove single-line comments (but not URLs like http://).
        $js = preg_replace( '/(?<![:"\'\\\\])\/\/[^\n]*/', '', $js );

        // Remove multi-line comments.
        $js = preg_replace( '/\/\*[\s\S]*?\*\//', '', $js );

        // Collapse multiple newlines into one.
        $js = preg_replace( '/\n{2,}/', "\n", $js );

        // Remove leading/trailing whitespace on each line.
        $js = preg_replace( '/^[ \t]+|[ \t]+$/m', '', $js );

        // Collapse multiple spaces into one.
        $js = preg_replace( '/[ \t]{2,}/', ' ', $js );

        // Remove blank lines.
        $js = preg_replace( '/^\s*\n/m', '', $js );

        // Trim the result.
        $js = trim( $js );

        // Restore string literals.
        $js = str_replace( array_keys( $strings ), array_values( $strings ), $js );

        return $js;
    }
}
