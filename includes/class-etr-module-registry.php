<?php
/**
 * Module registry — registers, stores, and boots optimization modules.
 *
 * @package ElTronador
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ETR_Module_Registry {

    /**
     * Registered modules keyed by ID.
     *
     * @var array<string, ETR_Module_Interface>
     */
    private array $modules = [];

    /**
     * Register a module.
     *
     * @param ETR_Module_Interface $module Module instance.
     */
    public function register( ETR_Module_Interface $module ): void {
        $this->modules[ $module->get_id() ] = $module;
    }

    /**
     * Boot all enabled modules by calling their init() method.
     */
    public function boot_all(): void {
        foreach ( $this->modules as $module ) {
            if ( $module->is_enabled() ) {
                $module->init();
            }
        }
    }

    /**
     * Get a registered module by ID.
     *
     * @param string $id Module identifier.
     * @return ETR_Module_Interface|null
     */
    public function get( string $id ): ?ETR_Module_Interface {
        return $this->modules[ $id ] ?? null;
    }

    /**
     * Get all registered modules.
     *
     * @return array<string, ETR_Module_Interface>
     */
    public function get_all(): array {
        return $this->modules;
    }
}
