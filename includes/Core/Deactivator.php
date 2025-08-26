<?php
/**
 * Plugin Deactivator
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

/**
 * Plugin deactivation handler
 */
class Deactivator {
    
    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}