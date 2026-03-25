<?php
/**
 * Database Optimizer module — cleans revisions, drafts, spam, trash, and expired transients.
 *
 * Provides manual one-click cleanup and optional weekly WP-Cron automation.
 * All queries use $wpdb with prepared statements for safety.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Database_Optimizer implements ETR_Module_Interface {

    /**
     * WP-Cron hook name for scheduled cleanup.
     */
    public const CRON_HOOK = 'etr_weekly_db_cleanup';

    /**
     * {@inheritdoc}
     */
    public function get_id(): string {
        return 'database_optimizer';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name(): string {
        return __( 'Optimización de Base de Datos', 'el-tronador' );
    }

    /**
     * {@inheritdoc}
     */
    public function is_enabled(): bool {
        return (bool) ETR_Admin_Options::get( 'db_clean_revisions', false )
            || (bool) ETR_Admin_Options::get( 'db_clean_drafts', false )
            || (bool) ETR_Admin_Options::get( 'db_clean_spam', false )
            || (bool) ETR_Admin_Options::get( 'db_clean_transients', false );
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void {
        // Register the cron hook callback.
        add_action( self::CRON_HOOK, [ $this, 'run_scheduled_cleanup' ] );

        // Manage cron schedule based on settings.
        $this->manage_cron_schedule();
    }

    /**
     * Manage WP-Cron schedule: add or remove based on user setting.
     */
    private function manage_cron_schedule(): void {
        $auto_enabled = (bool) ETR_Admin_Options::get( 'db_auto_cleanup', false );
        $scheduled    = wp_next_scheduled( self::CRON_HOOK );

        if ( $auto_enabled && ! $scheduled ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
        } elseif ( ! $auto_enabled && $scheduled ) {
            wp_unschedule_event( $scheduled, self::CRON_HOOK );
        }
    }

    /**
     * Run scheduled cleanup — executes all enabled cleanup tasks.
     */
    public function run_scheduled_cleanup(): void {
        $this->run_cleanup();
    }

    /**
     * Run cleanup based on enabled settings.
     *
     * @return array<string, int> Summary of deleted items per category.
     */
    public function run_cleanup(): array {
        $results = [];

        if ( ETR_Admin_Options::get( 'db_clean_revisions', false ) ) {
            $results['revisions'] = $this->clean_revisions();
        }

        if ( ETR_Admin_Options::get( 'db_clean_drafts', false ) ) {
            $results['drafts'] = $this->clean_drafts_and_trash();
        }

        if ( ETR_Admin_Options::get( 'db_clean_spam', false ) ) {
            $results['spam'] = $this->clean_spam_and_trash_comments();
        }

        if ( ETR_Admin_Options::get( 'db_clean_transients', false ) ) {
            $results['transients'] = $this->clean_expired_transients();
        }

        return $results;
    }

    /**
     * Delete all post revisions.
     *
     * @return int Number of deleted revisions.
     */
    private function clean_revisions(): int {
        global $wpdb;

        // Delete revision meta first (referential cleanup).
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'revision'"
        );

        // Delete revision posts.
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );

        return max( 0, (int) $deleted );
    }

    /**
     * Delete auto-drafts and trashed posts.
     *
     * @return int Number of deleted posts.
     */
    private function clean_drafts_and_trash(): int {
        global $wpdb;

        // Delete meta for auto-drafts and trashed posts.
        $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_status IN ('auto-draft', 'trash')"
        );

        // Delete the posts themselves.
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->posts} WHERE post_status IN ('auto-draft', 'trash')"
        );

        return max( 0, (int) $deleted );
    }

    /**
     * Delete spam and trashed comments.
     *
     * @return int Number of deleted comments.
     */
    private function clean_spam_and_trash_comments(): int {
        global $wpdb;

        // Delete comment meta first.
        $wpdb->query(
            "DELETE cm FROM {$wpdb->commentmeta} cm
             INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
             WHERE c.comment_approved IN ('spam', 'trash')"
        );

        // Delete the comments.
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')"
        );

        return max( 0, (int) $deleted );
    }

    /**
     * Delete expired transients from the options table.
     *
     * @return int Number of deleted transient pairs.
     */
    private function clean_expired_transients(): int {
        global $wpdb;

        $time = time();

        // Find expired transient timeout keys.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $expired = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_value < %d",
                $wpdb->esc_like( '_transient_timeout_' ) . '%',
                $time
            )
        );

        if ( empty( $expired ) ) {
            return 0;
        }

        $count = 0;

        foreach ( $expired as $timeout_key ) {
            // Derive the transient name from the timeout key.
            $transient_name = str_replace( '_transient_timeout_', '', $timeout_key );

            // Delete both the transient value and its timeout entry.
            delete_transient( $transient_name );
            $count++;
        }

        return $count;
    }

    /**
     * Unschedule the cron event (used on deactivation).
     */
    public static function unschedule_cron(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}
