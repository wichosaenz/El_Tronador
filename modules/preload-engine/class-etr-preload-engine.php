<?php
/**
 * Preload Engine — Sitemap Crawler Bot.
 *
 * Reads the site's XML sitemap, builds a preload queue, and warms the static
 * page cache by visiting each URL in batches via WP-Cron.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Preload_Engine implements ETR_Module_Interface {

    /**
     * WP-Cron hook name for batch processing.
     */
    public const CRON_HOOK = 'etr_preload_batch';

    /**
     * Custom cron schedule name.
     */
    private const CRON_SCHEDULE = 'etr_every_five_minutes';

    /**
     * Option key for the preload queue.
     */
    private const QUEUE_OPTION = 'etr_preload_queue';

    /**
     * Number of URLs to process per batch.
     */
    private const BATCH_SIZE = 25;

    /**
     * HTTP request timeout in seconds.
     */
    private const REQUEST_TIMEOUT = 3;

    /** @inheritDoc */
    public function get_id(): string {
        return 'preload_engine';
    }

    /** @inheritDoc */
    public function get_name(): string {
        return 'Motor de Precarga';
    }

    /** @inheritDoc */
    public function is_enabled(): bool {
        return (bool) ETR_Admin_Options::get( 'preload_enabled', false );
    }

    /** @inheritDoc */
    public function init(): void {
        // Register custom cron schedule.
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedule' ] );

        // Hook the batch processor.
        add_action( self::CRON_HOOK, [ $this, 'process_batch' ] );

        // Manage cron schedule based on settings.
        $this->manage_cron_schedule();

        // Trigger preload on post save (front-end cache warmer).
        add_action( 'save_post', [ $this, 'on_save_post' ], 20, 2 );
    }

    /**
     * Register the 5-minute cron interval.
     *
     * @param array<string, array<string, mixed>> $schedules Existing schedules.
     * @return array<string, array<string, mixed>>
     */
    public function add_cron_schedule( array $schedules ): array {
        $schedules[ self::CRON_SCHEDULE ] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __( 'Cada 5 minutos (El Tronador)', 'el-tronador' ),
        ];
        return $schedules;
    }

    /**
     * Schedule or unschedule the cron event based on settings.
     */
    private function manage_cron_schedule(): void {
        $enabled   = $this->is_enabled();
        $scheduled = wp_next_scheduled( self::CRON_HOOK );

        if ( $enabled && ! $scheduled ) {
            wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_HOOK );
        } elseif ( ! $enabled && $scheduled ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
        }
    }

    /**
     * Unschedule the cron event (called on plugin deactivation).
     */
    public static function unschedule_cron(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
        delete_option( self::QUEUE_OPTION );
    }

    /**
     * Trigger a full preload when a post is saved (published/updated).
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public function on_save_post( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( 'publish' !== $post->post_status ) {
            return;
        }

        // Rebuild the queue so the new/updated page gets preloaded.
        $this->build_queue();
    }

    /**
     * Build (or rebuild) the preload queue from the sitemap.
     *
     * @return int Number of URLs queued.
     */
    public function build_queue(): int {
        $sitemap_url = $this->get_sitemap_url();
        $urls        = $this->fetch_sitemap_urls( $sitemap_url );

        if ( empty( $urls ) ) {
            delete_option( self::QUEUE_OPTION );
            return 0;
        }

        // Shuffle to avoid always hitting the same pages first.
        shuffle( $urls );

        update_option( self::QUEUE_OPTION, $urls, false );

        // Ensure cron is scheduled.
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_HOOK );
        }

        return count( $urls );
    }

    /**
     * Process a batch of URLs from the queue.
     */
    public function process_batch(): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        $queue = get_option( self::QUEUE_OPTION, [] );

        if ( ! is_array( $queue ) || empty( $queue ) ) {
            return;
        }

        $batch = array_splice( $queue, 0, self::BATCH_SIZE );

        foreach ( $batch as $url ) {
            wp_remote_get( $url, [
                'timeout'   => self::REQUEST_TIMEOUT,
                'sslverify' => false,
                'blocking'  => false,
                'headers'   => [
                    'Cache-Control' => 'no-cache',
                ],
            ] );
        }

        if ( empty( $queue ) ) {
            delete_option( self::QUEUE_OPTION );
        } else {
            update_option( self::QUEUE_OPTION, $queue, false );
        }
    }

    /**
     * Get the configured sitemap URL, falling back to the default.
     *
     * @return string
     */
    private function get_sitemap_url(): string {
        $url = ETR_Admin_Options::get( 'preload_sitemap_url', '' );

        if ( empty( $url ) ) {
            $url = home_url( '/wp-sitemap.xml' );
        }

        return $url;
    }

    /**
     * Fetch and parse URLs from a sitemap (supports sitemap index).
     *
     * @param string $sitemap_url URL of the XML sitemap.
     * @return string[] Array of page URLs.
     */
    private function fetch_sitemap_urls( string $sitemap_url ): array {
        $response = wp_remote_get( $sitemap_url, [
            'timeout'   => 15,
            'sslverify' => false,
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return [];
        }

        // Suppress XML parsing warnings.
        $use_errors = libxml_use_internal_errors( true );
        $xml        = simplexml_load_string( $body );
        libxml_use_internal_errors( $use_errors );

        if ( false === $xml ) {
            return [];
        }

        $urls = [];

        // Check if this is a sitemap index (contains <sitemap> elements).
        if ( isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $entry ) {
                if ( isset( $entry->loc ) ) {
                    $child_urls = $this->fetch_sitemap_urls( (string) $entry->loc );
                    $urls       = array_merge( $urls, $child_urls );
                }
            }
        }

        // Standard sitemap (contains <url> elements).
        if ( isset( $xml->url ) ) {
            foreach ( $xml->url as $entry ) {
                if ( isset( $entry->loc ) ) {
                    $urls[] = (string) $entry->loc;
                }
            }
        }

        return array_unique( $urls );
    }

    /**
     * Get the current queue size for display in admin.
     *
     * @return int
     */
    public static function get_queue_size(): int {
        $queue = get_option( self::QUEUE_OPTION, [] );
        return is_array( $queue ) ? count( $queue ) : 0;
    }
}
