<?php
/**
 * Unified output buffer coordinator.
 *
 * Replaces multiple nested ob_start() calls with a single buffer that
 * runs all registered HTML processors in priority order. This reduces
 * memory usage (one HTML copy instead of four) and prevents timeout
 * issues caused by cascading buffer callbacks.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Output_Buffer {

    /**
     * Maximum HTML size to process (2 MB). Pages larger than this
     * are passed through without optimization to avoid timeouts.
     */
    private const MAX_HTML_SIZE = 2 * 1024 * 1024;

    /**
     * Maximum seconds to spend in HTML processing before aborting
     * remaining processors and returning what we have.
     */
    private const TIME_BUDGET = 8;

    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Registered processors sorted by priority.
     *
     * @var array<int, array{priority: int, callback: callable}>
     */
    private array $processors = [];

    /**
     * Whether to write the final HTML to disk cache.
     */
    private bool $cache_enabled = false;

    /**
     * Get or create the singleton.
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Register a HTML processor to run inside the unified buffer.
     *
     * @param callable $callback  Function that accepts and returns a string (HTML).
     * @param int      $priority  Lower numbers run first.
     */
    public function register_processor( callable $callback, int $priority = 50 ): void {
        $this->processors[] = [
            'priority' => $priority,
            'callback' => $callback,
        ];

        // Keep sorted by priority so execution order is deterministic.
        usort( $this->processors, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
    }

    /**
     * Enable disk caching of the final processed HTML.
     */
    public function enable_cache(): void {
        $this->cache_enabled = true;
    }

    /**
     * Start the single output buffer. Should only be called once.
     */
    public function start(): void {
        ob_start( [ $this, 'process' ] );
    }

    /**
     * Output buffer callback — runs all registered processors in order.
     *
     * @param string $html Raw page HTML.
     * @return string Processed HTML.
     */
    public function process( string $html ): string {
        // Skip non-HTML or incomplete responses.
        if ( strlen( $html ) < 255 || ! str_contains( $html, '</html>' ) ) {
            return $html;
        }

        // Skip oversized pages to avoid memory exhaustion and timeouts.
        if ( strlen( $html ) > self::MAX_HTML_SIZE ) {
            $this->maybe_write_cache( $html );
            return $html;
        }

        $start_time = microtime( true );

        foreach ( $this->processors as $entry ) {
            // Check time budget before each processor.
            $elapsed = microtime( true ) - $start_time;
            if ( $elapsed >= self::TIME_BUDGET ) {
                break;
            }

            $html = call_user_func( $entry['callback'], $html );
        }

        $this->maybe_write_cache( $html );

        return $html;
    }

    /**
     * Write HTML to disk cache if caching is enabled.
     *
     * @param string $html Final HTML.
     */
    private function maybe_write_cache( string $html ): void {
        if ( ! $this->cache_enabled ) {
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';

        ETR_Cache_Filesystem::write( $host, $uri, $html );
    }
}
