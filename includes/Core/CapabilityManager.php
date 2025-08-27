<?php
/**
 * Capability Manager
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Capability Manager class for handling custom capabilities
 */
class CapabilityManager {

    /**
     * Custom capability for managing FP Esperienze
     */
    const MANAGE_FP_ESPERIENZE = 'manage_fp_esperienze';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_loaded', [$this, 'addCapabilitiesToRoles']);
        
        // Handle capability checks for AJAX and form submissions
        add_action('wp_ajax_fp_esperienze_admin_action', [$this, 'checkAdminCapability']);
        add_action('wp_ajax_nopriv_fp_esperienze_admin_action', [$this, 'denyUnauthorizedAccess']);
    }

    /**
     * Add capabilities to appropriate user roles
     */
    public function addCapabilitiesToRoles(): void {
        // Get roles that should have the capability
        $roles_with_capability = [
            'administrator',  // Full admin access
            'shop_manager',   // WooCommerce shop managers
        ];

        foreach ($roles_with_capability as $role_name) {
            $role = get_role($role_name);
            if ($role && !$role->has_cap(self::MANAGE_FP_ESPERIENZE)) {
                $role->add_cap(self::MANAGE_FP_ESPERIENZE);
            }
        }
    }

    /**
     * Remove capabilities from roles (for cleanup/uninstall)
     */
    public static function removeCapabilitiesFromRoles(): void {
        $all_roles = wp_roles()->roles;
        
        foreach ($all_roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role && $role->has_cap(self::MANAGE_FP_ESPERIENZE)) {
                $role->remove_cap(self::MANAGE_FP_ESPERIENZE);
            }
        }
    }

    /**
     * Check if current user can manage FP Esperienze
     *
     * @return bool
     */
    public static function canManageFPEsperienze(): bool {
        return current_user_can(self::MANAGE_FP_ESPERIENZE);
    }

    /**
     * Check admin capability for AJAX requests
     */
    public function checkAdminCapability(): void {
        if (!self::canManageFPEsperienze()) {
            $this->denyUnauthorizedAccess();
        }
    }

    /**
     * Deny unauthorized access
     */
    public function denyUnauthorizedAccess(): void {
        wp_die(
            __('You do not have permission to perform this action.', 'fp-esperienze'),
            __('Permission Denied', 'fp-esperienze'),
            ['response' => 403]
        );
    }

    /**
     * Check nonce and capability for admin actions
     *
     * @param string $nonce_action Nonce action
     * @param string $nonce_name Nonce field name
     * @return bool
     */
    public static function verifyAdminAction(string $nonce_action, string $nonce_name = '_wpnonce'): bool {
        // Check capability first
        if (!self::canManageFPEsperienze()) {
            return false;
        }

        // Check nonce
        if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
            return false;
        }

        return true;
    }

    /**
     * Get capability requirements for different admin pages
     *
     * @return array
     */
    public static function getPageCapabilities(): array {
        return [
            'fp-esperienze' => self::MANAGE_FP_ESPERIENZE,
            'fp-esperienze-bookings' => self::MANAGE_FP_ESPERIENZE,
            'fp-esperienze-meeting-points' => self::MANAGE_FP_ESPERIENZE,
            'fp-esperienze-extras' => self::MANAGE_FP_ESPERIENZE,
            'fp-esperienze-vouchers' => self::MANAGE_FP_ESPERIENZE,
            'fp-esperienze-closures' => self::MANAGE_FP_ESPERIENZE,
            'fp-esperienze-reports' => self::MANAGE_FP_ESPERIENZE,
            'fp-esperienze-settings' => self::MANAGE_FP_ESPERIENZE,
        ];
    }
}