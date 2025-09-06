<?php
/**
 * Setup Wizard Diagnostic Script
 *
 * This script helps diagnose setup wizard issues in FP Esperienze plugin.
 * Place this file in WordPress root directory and access via browser.
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

// Check if we're in admin context
if (!is_admin()) {
    wp_redirect(admin_url('admin.php?page=fp-esperienze-diagnostic'));
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>FP Esperienze Setup Wizard Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status-ok { color: green; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
        .status-warning { color: orange; font-weight: bold; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .code { background: #f5f5f5; padding: 10px; font-family: monospace; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 FP Esperienze Setup Wizard Diagnostic</h1>
    
    <?php
    
    // Check 1: Plugin Active
    echo '<div class="section">';
    echo '<h2>1. Plugin Status</h2>';
    
    if (is_plugin_active('fp-esperienze/fp-esperienze.php')) {
        echo '<div class="status-ok">✅ FP Esperienze plugin is active</div>';
    } else {
        echo '<div class="status-error">❌ FP Esperienze plugin is not active</div>';
    }
    echo '</div>';
    
    // Check 2: WooCommerce
    echo '<div class="section">';
    echo '<h2>2. WooCommerce Status</h2>';
    
    if (class_exists('WooCommerce')) {
        echo '<div class="status-ok">✅ WooCommerce is active</div>';
        
        if (function_exists('get_woocommerce_currency')) {
            echo '<div class="status-ok">✅ WooCommerce currency functions available</div>';
        } else {
            echo '<div class="status-warning">⚠️ WooCommerce currency functions not available</div>';
        }
    } else {
        echo '<div class="status-warning">⚠️ WooCommerce not active (not required, but recommended)</div>';
    }
    echo '</div>';
    
    // Check 3: Setup Wizard Class
    echo '<div class="section">';
    echo '<h2>3. Setup Wizard Class</h2>';
    
    if (class_exists('FP\\Esperienze\\Admin\\SetupWizard')) {
        echo '<div class="status-ok">✅ SetupWizard class exists</div>';
        
        $setup_wizard = new FP\Esperienze\Admin\SetupWizard();
        $is_complete = $setup_wizard->isSetupComplete();
        
        if ($is_complete) {
            echo '<div class="status-ok">✅ Setup is marked as complete</div>';
        } else {
            echo '<div class="status-warning">⚠️ Setup is not complete - wizard should be visible</div>';
        }
    } else {
        echo '<div class="status-error">❌ SetupWizard class not found</div>';
    }
    echo '</div>';
    
    // Check 4: Menu Structure
    echo '<div class="section">';
    echo '<h2>4. Admin Menu Analysis</h2>';
    
    global $menu, $submenu;
    
    $fp_menu_found = false;
    $setup_submenu_found = false;
    
    // Check main menu
    foreach ($menu as $menu_item) {
        if (isset($menu_item[2]) && $menu_item[2] === 'fp-esperienze') {
            $fp_menu_found = true;
            break;
        }
    }
    
    if ($fp_menu_found) {
        echo '<div class="status-ok">✅ Main FP Esperienze menu found</div>';
        
        // Check submenu
        if (isset($submenu['fp-esperienze'])) {
            foreach ($submenu['fp-esperienze'] as $submenu_item) {
                if (isset($submenu_item[2]) && $submenu_item[2] === 'fp-esperienze-setup-wizard') {
                    $setup_submenu_found = true;
                    break;
                }
            }
        }
        
        if ($setup_submenu_found) {
            echo '<div class="status-ok">✅ Setup Wizard submenu found</div>';
        } else {
            echo '<div class="status-warning">⚠️ Setup Wizard submenu not found</div>';
            
            if (class_exists('FP\\Esperienze\\Admin\\SetupWizard')) {
                $setup_wizard = new FP\Esperienze\Admin\SetupWizard();
                if (!$setup_wizard->isSetupComplete()) {
                    echo '<div class="status-error">❌ Setup not complete but submenu missing - this indicates the fix didn\'t work</div>';
                }
            }
        }
    } else {
        echo '<div class="status-error">❌ Main FP Esperienze menu not found</div>';
    }
    echo '</div>';
    
    // Check 5: Capabilities
    echo '<div class="section">';
    echo '<h2>5. User Capabilities</h2>';
    
    $current_user = wp_get_current_user();
    
    if (current_user_can('manage_woocommerce')) {
        echo '<div class="status-ok">✅ Current user has manage_woocommerce capability</div>';
    } else {
        echo '<div class="status-error">❌ Current user lacks manage_woocommerce capability</div>';
        echo '<div>Current user roles: ' . implode(', ', $current_user->roles) . '</div>';
    }
    
    if (current_user_can('manage_options')) {
        echo '<div class="status-ok">✅ Current user has manage_options capability</div>';
    } else {
        echo '<div class="status-warning">⚠️ Current user lacks manage_options capability</div>';
    }
    echo '</div>';
    
    // Check 6: Database Options
    echo '<div class="section">';
    echo '<h2>6. Database Options</h2>';
    
    $setup_complete = get_option('fp_esperienze_setup_complete', false);
    $activation_redirect = get_transient('fp_esperienze_activation_redirect');
    
    echo '<div>Setup Complete Option: ' . ($setup_complete ? '<span class="status-ok">Yes</span>' : '<span class="status-warning">No</span>') . '</div>';
    echo '<div>Activation Redirect Transient: ' . ($activation_redirect ? '<span class="status-warning">Active</span>' : '<span class="status-ok">None</span>') . '</div>';
    echo '</div>';
    
    // Check 7: File Permissions
    echo '<div class="section">';
    echo '<h2>7. File Checks</h2>';
    
    $plugin_file = WP_PLUGIN_DIR . '/fp-esperienze/includes/Admin/SetupWizard.php';
    
    if (file_exists($plugin_file)) {
        echo '<div class="status-ok">✅ SetupWizard.php file exists</div>';
        
        if (is_readable($plugin_file)) {
            echo '<div class="status-ok">✅ SetupWizard.php file is readable</div>';
            
            // Check for our fix
            $file_content = file_get_contents($plugin_file);
            if (strpos($file_content, "add_action('admin_menu', [\$this, 'addSetupWizardMenu'], 15)") !== false) {
                echo '<div class="status-ok">✅ Setup wizard fix detected (priority 15)</div>';
            } else {
                echo '<div class="status-error">❌ Setup wizard fix not detected - priority should be 15</div>';
            }
        } else {
            echo '<div class="status-error">❌ SetupWizard.php file not readable</div>';
        }
    } else {
        echo '<div class="status-error">❌ SetupWizard.php file not found</div>';
    }
    echo '</div>';
    
    // Recommendations
    echo '<div class="section">';
    echo '<h2>8. Recommendations</h2>';
    
    if (!$fp_menu_found || (!$setup_submenu_found && !$setup_complete)) {
        echo '<div class="status-error">❌ Setup wizard appears to have issues</div>';
        echo '<div class="code">';
        echo 'To reset and test:<br>';
        echo '1. DELETE FROM ' . $GLOBALS['wpdb']->options . ' WHERE option_name = "fp_esperienze_setup_complete";<br>';
        echo '2. Deactivate and reactivate FP Esperienze plugin<br>';
        echo '3. Check if setup wizard appears and redirects properly';
        echo '</div>';
    } else {
        echo '<div class="status-ok">✅ Setup wizard appears to be functioning correctly</div>';
    }
    echo '</div>';
    
    ?>
    
    <div class="section">
        <h2>9. Direct Testing Links</h2>
        <p><a href="<?php echo admin_url('admin.php?page=fp-esperienze-setup-wizard'); ?>" target="_blank">🔗 Open Setup Wizard Directly</a></p>
        <p><a href="<?php echo admin_url('admin.php?page=fp-esperienze'); ?>" target="_blank">🔗 Open FP Esperienze Dashboard</a></p>
    </div>
    
    <div class="section">
        <h2>10. Reset Setup (for Testing)</h2>
        <p><strong>Warning:</strong> This will reset the setup completion status.</p>
        <p><a href="<?php echo wp_nonce_url(admin_url('admin.php?page=fp-esperienze-diagnostic&action=reset_setup'), 'reset_setup'); ?>" onclick="return confirm('Are you sure you want to reset the setup status?');">🔄 Reset Setup Status</a></p>
    </div>
    
    <?php
    
    // Handle reset action
    if (isset($_GET['action']) && $_GET['action'] === 'reset_setup' && wp_verify_nonce($_GET['_wpnonce'], 'reset_setup')) {
        delete_option('fp_esperienze_setup_complete');
        delete_transient('fp_esperienze_activation_redirect');
        echo '<div class="section"><div class="status-ok">✅ Setup status has been reset. Refresh this page to see updated status.</div></div>';
    }
    
    ?>
    
</body>
</html>
