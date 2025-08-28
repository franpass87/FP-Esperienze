<?php
/**
 * Debug script to test experience product type functionality
 * Place this in the wp-content/plugins/fp-esperienze/ directory and access via admin
 */

// This should be included or called from within WordPress context

function fp_debug_product_type() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo "<h2>FP Esperienze Product Type Debug</h2>";
    
    // Check if Experience class exists
    if (!class_exists('FP\Esperienze\ProductType\Experience')) {
        echo "<p>❌ Experience class not found</p>";
        return;
    }
    
    // Check if WC_Product_Experience class exists
    if (!class_exists('WC_Product_Experience')) {
        echo "<p>❌ WC_Product_Experience class not found</p>";
        return;
    }
    
    echo "<p>✅ Experience classes found</p>";
    
    // Check if product type is registered
    $product_types = wc_get_product_types();
    if (isset($product_types['experience'])) {
        echo "<p>✅ Experience product type is registered: " . esc_html($product_types['experience']) . "</p>";
    } else {
        echo "<p>❌ Experience product type not registered</p>";
        echo "<p>Available types: " . esc_html(implode(', ', array_keys($product_types))) . "</p>";
    }
    
    // Test the product class filter
    $class = apply_filters('woocommerce_product_class', 'WC_Product', 'experience');
    echo "<p>Product class for 'experience' type: " . esc_html($class) . "</p>";
    
    // Check if filters are hooked
    global $wp_filter;
    if (isset($wp_filter['product_type_selector'])) {
        echo "<p>✅ product_type_selector filter has " . count($wp_filter['product_type_selector']->callbacks) . " callbacks</p>";
    } else {
        echo "<p>❌ product_type_selector filter not found</p>";
    }
    
    if (isset($wp_filter['woocommerce_product_class'])) {
        echo "<p>✅ woocommerce_product_class filter has " . count($wp_filter['woocommerce_product_class']->callbacks) . " callbacks</p>";
    } else {
        echo "<p>❌ woocommerce_product_class filter not found</p>";
    }
    
    // Check for actual experience products
    $experience_products = get_posts([
        'post_type' => 'product',
        'meta_query' => [
            [
                'key' => '_product_type',
                'value' => 'experience'
            ]
        ],
        'post_status' => 'any',
        'numberposts' => 5
    ]);
    
    echo "<p>Found " . count($experience_products) . " experience products in database</p>";
    
    if (!empty($experience_products)) {
        foreach ($experience_products as $post) {
            $product = wc_get_product($post->ID);
            $stored_type = get_post_meta($post->ID, '_product_type', true);
            $object_type = $product ? $product->get_type() : 'N/A';
            $product_class = $product ? get_class($product) : 'N/A';
            
            echo "<div style='margin-left: 20px; border-left: 2px solid #ccc; padding-left: 10px;'>";
            echo "<strong>Product ID {$post->ID}: {$post->post_title}</strong><br>";
            echo "Stored type: {$stored_type}<br>";
            echo "Object type: {$object_type}<br>";
            echo "Product class: {$product_class}<br>";
            echo "</div><br>";
        }
    }
}

// Add admin page for debugging
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'FP Esperienze Debug',
        'FP Esperienze Debug',
        'manage_options',
        'fp-esperienze-debug',
        'fp_debug_product_type'
    );
});