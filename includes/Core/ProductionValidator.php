<?php
/**
 * Production Readiness Validator
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use FP\Esperienze\Admin\DependencyChecker;

defined('ABSPATH') || exit;

/**
 * Production Readiness Validator class
 */
class ProductionValidator {

    /**
     * Run complete production readiness check
     *
     * @return array Validation results
     */
    public static function validateProductionReadiness(): array {
        $results = [
            'overall_status' => 'pass',
            'critical_issues' => [],
            'warnings' => [],
            'checks' => []
        ];

        // Core component checks
        $results = self::checkCoreComponents($results);
        
        // Database checks
        $results = self::checkDatabase($results);
        
        // Security checks
        $results = self::checkSecurity($results);
        
        // WooCommerce integration checks
        $results = self::checkWooCommerceIntegration($results);
        
        // Asset checks
        $results = self::checkAssets($results);
        
        // Translation checks
        $results = self::checkTranslations($results);

        // Optional dependency checks
        $results = self::checkOptionalDependencies($results);

        // Admin interface checks
        $results = self::checkAdminInterface($results);

        // REST API checks
        $results = self::checkRESTAPI($results);

        // Template checks
        $results = self::checkTemplates($results);

        // Set overall status based on critical issues
        if (!empty($results['critical_issues'])) {
            $results['overall_status'] = 'fail';
        } elseif (!empty($results['warnings'])) {
            $results['overall_status'] = 'warning';
        }

        return $results;
    }

    /**
     * Check optional composer-powered features and report their status.
     *
     * @param array $results Current validation results accumulator.
     * @return array
     */
    private static function checkOptionalDependencies(array $results): array {
        if (!class_exists(DependencyChecker::class)) {
            return $results;
        }

        $dependencies = DependencyChecker::checkAll();

        foreach ($dependencies as $slug => $dependency) {
            $name        = $dependency['name'] ?? ucfirst($slug);
            $is_available = !empty($dependency['available']);
            $impact      = $dependency['impact'] ?? '';

            if ($is_available) {
                $results['checks'][] = sprintf('✅ %s available', $name);
                continue;
            }

            $message = sprintf('⚠️ %s not available', $name);

            if ($impact !== '') {
                $message .= sprintf(': %s', $impact);
            }

            $results['warnings'][] = $message;
        }

        return $results;
    }

    /**
     * Check core components
     */
    private static function checkCoreComponents(array $results): array {
        $core_classes = [
            'FP\Esperienze\Core\Plugin',
            'FP\Esperienze\Core\Installer', 
            'FP\Esperienze\ProductType\Experience',
            'FP\Esperienze\ProductType\WC_Product_Experience',
            'FP\Esperienze\Admin\MenuManager',
            'FP\Esperienze\REST\AvailabilityAPI',
            'FP\Esperienze\Data\ScheduleManager',
            'FP\Esperienze\Data\BookingManager'
        ];

        foreach ($core_classes as $class) {
            if (class_exists($class)) {
                $results['checks'][] = "✅ Core class $class exists";
            } else {
                $results['critical_issues'][] = "❌ Critical core class $class missing";
            }
        }

        // Check main plugin file
        if (defined('FP_ESPERIENZE_VERSION')) {
            $results['checks'][] = "✅ Plugin constants defined";
        } else {
            $results['critical_issues'][] = "❌ Plugin constants not defined";
        }

        return $results;
    }

    /**
     * Check database requirements
     */
    private static function checkDatabase(array $results): array {
        global $wpdb;

        $required_tables = [
            $wpdb->prefix . 'fp_meeting_points',
            $wpdb->prefix . 'fp_extras',
            $wpdb->prefix . 'fp_product_extras',
            $wpdb->prefix . 'fp_schedules',
            $wpdb->prefix . 'fp_overrides',
            $wpdb->prefix . 'fp_bookings',
            $wpdb->prefix . 'fp_vouchers'
        ];

        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($table_exists) {
                $results['checks'][] = "✅ Database table $table exists";
            } else {
                $results['critical_issues'][] = "❌ Required database table $table missing";
            }
        }

        return $results;
    }

    /**
     * Check security implementations
     */
    private static function checkSecurity(array $results): array {
        // Check if security enhancer is active
        if (class_exists('FP\Esperienze\Core\SecurityEnhancer')) {
            $results['checks'][] = "✅ Security enhancer available";
        } else {
            $results['warnings'][] = "⚠️ Security enhancer class not found";
        }

        // Check capability manager
        if (class_exists('FP\Esperienze\Core\CapabilityManager')) {
            $results['checks'][] = "✅ Capability manager available";
        } else {
            $results['critical_issues'][] = "❌ Capability manager missing";
        }

        // Check for rate limiting
        if (class_exists('FP\Esperienze\Core\RateLimiter')) {
            $results['checks'][] = "✅ Rate limiter available";
        } else {
            $results['warnings'][] = "⚠️ Rate limiter not found";
        }

        return $results;
    }

    /**
     * Check WooCommerce integration
     */
    private static function checkWooCommerceIntegration(array $results): array {
        if (!class_exists('WooCommerce')) {
            $results['critical_issues'][] = "❌ WooCommerce not active";
            return $results;
        }

        $results['checks'][] = "✅ WooCommerce is active";

        // Check if experience product type is registered
        $product_types = wc_get_product_types();
        if (isset($product_types['experience'])) {
            $results['checks'][] = "✅ Experience product type registered";
        } else {
            $results['critical_issues'][] = "❌ Experience product type not registered";
        }

        // Check HPOS compatibility
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            $results['checks'][] = "✅ HPOS compatibility declared";
        } else {
            $results['warnings'][] = "⚠️ HPOS compatibility not available";
        }

        return $results;
    }

    /**
     * Check assets
     */
    private static function checkAssets(array $results): array {
        $required_assets = [
            FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/admin-modular.js',
            FP_ESPERIENZE_PLUGIN_DIR . 'assets/css/admin.css',
            FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/modules/schedule-builder.js'
        ];

        foreach ($required_assets as $asset) {
            if (file_exists($asset)) {
                $results['checks'][] = "✅ Asset " . basename($asset) . " exists";
            } else {
                $results['warnings'][] = "⚠️ Asset " . basename($asset) . " missing";
            }
        }

        return $results;
    }

    /**
     * Check translations
     */
    private static function checkTranslations(array $results): array {
        $translation_files = [
            FP_ESPERIENZE_PLUGIN_DIR . 'languages/fp-esperienze.pot',
            FP_ESPERIENZE_PLUGIN_DIR . 'languages/fp-esperienze-en_US.po'
        ];

        foreach ($translation_files as $file) {
            if (file_exists($file)) {
                $results['checks'][] = "✅ Translation file " . basename($file) . " exists";
            } else {
                $results['warnings'][] = "⚠️ Translation file " . basename($file) . " missing";
            }
        }

        // Check if text domain is loaded
        if (is_textdomain_loaded('fp-esperienze')) {
            $results['checks'][] = "✅ Text domain loaded";
        } else {
            $results['warnings'][] = "⚠️ Text domain not loaded";
        }

        return $results;
    }

    /**
     * Check admin interface
     */
    private static function checkAdminInterface(array $results): array {
        if (class_exists('FP\Esperienze\Admin\MenuManager')) {
            $results['checks'][] = "✅ Admin menu manager available";
        } else {
            $results['critical_issues'][] = "❌ Admin menu manager missing";
        }

        if (class_exists('FP\Esperienze\Admin\SetupWizard')) {
            $results['checks'][] = "✅ Setup wizard available";
        } else {
            $results['warnings'][] = "⚠️ Setup wizard missing";
        }

        return $results;
    }

    /**
     * Check REST API
     */
    private static function checkRESTAPI(array $results): array {
        $rest_classes = [
            'FP\Esperienze\REST\AvailabilityAPI',
            'FP\Esperienze\REST\BookingsAPI',
            'FP\Esperienze\REST\ICSAPI'
        ];

        foreach ($rest_classes as $class) {
            if (class_exists($class)) {
                $results['checks'][] = "✅ REST API class " . basename($class) . " exists";
            } else {
                $results['critical_issues'][] = "❌ REST API class " . basename($class) . " missing";
            }
        }

        return $results;
    }

    /**
     * Check templates
     */
    private static function checkTemplates(array $results): array {
        $required_templates = [
            FP_ESPERIENZE_PLUGIN_DIR . 'templates/single-experience.php',
            FP_ESPERIENZE_PLUGIN_DIR . 'templates/voucher-form.php'
        ];

        foreach ($required_templates as $template) {
            if (file_exists($template)) {
                $results['checks'][] = "✅ Template " . basename($template) . " exists";
            } else {
                $results['warnings'][] = "⚠️ Template " . basename($template) . " missing";
            }
        }

        return $results;
    }

    /**
     * Display validation results
     */
    public static function displayResults(array $results): void {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "FP ESPERIENZE PRODUCTION READINESS REPORT\n";
        echo str_repeat("=", 60) . "\n";
        
        echo "\nOVERALL STATUS: ";
        switch ($results['overall_status']) {
            case 'pass':
                echo "✅ READY FOR PRODUCTION\n";
                break;
            case 'warning':
                echo "⚠️ READY WITH WARNINGS\n";
                break;
            case 'fail':
                echo "❌ NOT READY FOR PRODUCTION\n";
                break;
        }

        if (!empty($results['critical_issues'])) {
            echo "\n🚨 CRITICAL ISSUES:\n";
            echo str_repeat("-", 40) . "\n";
            foreach ($results['critical_issues'] as $issue) {
                echo "$issue\n";
            }
        }

        if (!empty($results['warnings'])) {
            echo "\n⚠️ WARNINGS:\n";
            echo str_repeat("-", 40) . "\n";
            foreach ($results['warnings'] as $warning) {
                echo "$warning\n";
            }
        }

        echo "\n✅ SUCCESSFUL CHECKS:\n";
        echo str_repeat("-", 40) . "\n";
        foreach ($results['checks'] as $check) {
            echo "$check\n";
        }

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "Total checks: " . count($results['checks']) . "\n";
        echo "Warnings: " . count($results['warnings']) . "\n";
        echo "Critical issues: " . count($results['critical_issues']) . "\n";
        echo str_repeat("=", 60) . "\n";
    }
}