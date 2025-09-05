<?php
/**
 * Dependency Checker for Optional Libraries
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

defined('ABSPATH') || exit;

/**
 * Check for optional dependencies and provide user-friendly status
 */
class DependencyChecker {
    
    /**
     * Check all optional dependencies
     *
     * @return array Status of all dependencies
     */
    public static function checkAll(): array {
        return [
            'dompdf' => self::checkDompdf(),
            'qrcode' => self::checkQRCode(),
            'composer' => self::checkComposer(),
        ];
    }
    
    /**
     * Check if DomPDF is available
     *
     * @return array Status info
     */
    public static function checkDompdf(): array {
        $available = class_exists('Dompdf\Dompdf');
        return [
            'available' => $available,
            'name' => 'DomPDF',
            'description' => __('Required for PDF voucher generation', 'fp-esperienze'),
            'impact' => $available ? '' : __('Vouchers will be generated as HTML instead of PDF', 'fp-esperienze'),
            'status' => $available ? 'success' : 'warning'
        ];
    }
    
    /**
     * Check if QR Code library is available
     *
     * @return array Status info
     */
    public static function checkQRCode(): array {
        $available = class_exists('chillerlan\QRCode\QRCode');
        return [
            'available' => $available,
            'name' => 'PHP QR Code',
            'description' => __('Required for QR code generation on vouchers', 'fp-esperienze'),
            'impact' => $available ? '' : __('Vouchers will not include QR codes for scanning', 'fp-esperienze'),
            'status' => $available ? 'success' : 'warning'
        ];
    }
    
    /**
     * Check if Composer autoloader is available
     *
     * @return array Status info
     */
    public static function checkComposer(): array {
        $autoloader_path = FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php';
        $available = file_exists($autoloader_path);
        return [
            'available' => $available,
            'name' => 'Composer Dependencies',
            'description' => __('External libraries for enhanced functionality', 'fp-esperienze'),
            'impact' => $available ? '' : __('Run "composer install --no-dev" to enable all features', 'fp-esperienze'),
            'status' => $available ? 'success' : 'info'
        ];
    }
    
    /**
     * Get installation instructions
     *
     * @return string HTML instructions
     */
    public static function getInstallationInstructions(): string {
        $instructions = '<div class="fp-dependency-instructions">';
        $instructions .= '<h4>' . __('To enable all features:', 'fp-esperienze') . '</h4>';
        $instructions .= '<ol>';
        $instructions .= '<li>' . __('Navigate to the plugin directory in terminal:', 'fp-esperienze') . '<br>';
        $instructions .= '<code>cd ' . esc_html(FP_ESPERIENZE_PLUGIN_DIR) . '</code></li>';
        $instructions .= '<li>' . __('Install dependencies:', 'fp-esperienze') . '<br>';
        $instructions .= '<code>composer install --no-dev</code></li>';
        $instructions .= '</ol>';
        $instructions .= '<p><em>' . __('Note: The plugin works without these dependencies, but some features will have fallback behavior.', 'fp-esperienze') . '</em></p>';
        $instructions .= '</div>';
        
        return $instructions;
    }
    
    /**
     * Render dependency status widget for admin
     *
     * @return void
     */
    public static function renderStatusWidget(): void {
        $dependencies = self::checkAll();
        
        echo '<div class="fp-dependency-status postbox">';
        echo '<h3 class="hndle">' . __('Optional Dependencies Status', 'fp-esperienze') . '</h3>';
        echo '<div class="inside">';
        
        foreach ($dependencies as $dep) {
            $icon_class = $dep['status'] === 'success' ? 'dashicons-yes-alt' : 
                         ($dep['status'] === 'warning' ? 'dashicons-warning' : 'dashicons-info');
            $status_color = $dep['status'] === 'success' ? 'green' : 
                           ($dep['status'] === 'warning' ? 'orange' : 'blue');
            
            echo '<div class="fp-dependency-item" style="margin: 10px 0; padding: 10px; border-left: 3px solid ' . esc_attr($status_color) . ';">';
            echo '<strong><span class="dashicons ' . esc_attr($icon_class) . '" style="color: ' . esc_attr($status_color) . ';"></span> ';
            echo esc_html($dep['name']) . '</strong><br>';
            echo '<small>' . esc_html($dep['description']) . '</small>';
            if (!empty($dep['impact'])) {
                echo '<br><em style="color: #666;">' . esc_html($dep['impact']) . '</em>';
            }
            echo '</div>';
        }
        
        $any_missing = array_filter($dependencies, function($dep) {
            return !$dep['available'];
        });
        
        if (!empty($any_missing)) {
            echo '<div style="margin-top: 15px;">';
            echo self::getInstallationInstructions();
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
}