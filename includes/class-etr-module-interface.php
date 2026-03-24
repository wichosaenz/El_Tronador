<?php
/**
 * Module interface for El Tronador.
 *
 * All optimization modules must implement this interface.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ETR_Module_Interface {

    /**
     * Get the unique module identifier.
     *
     * @return string e.g. 'page_cache', 'delay_js'.
     */
    public function get_id(): string;

    /**
     * Get the human-readable module name.
     *
     * @return string e.g. 'Page Cache'.
     */
    public function get_name(): string;

    /**
     * Check whether this module is enabled in settings.
     *
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Initialize the module — register hooks, filters, etc.
     */
    public function init(): void;
}
