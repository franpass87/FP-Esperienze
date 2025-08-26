<?php
/**
 * Plugin Activator
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

/**
 * Plugin activation handler
 */
class Activator {
    
    /**
     * Activate plugin
     */
    public static function activate() {
        // Flush rewrite rules to ensure REST API endpoints work
        flush_rewrite_rules();
    }
}