<?php
/**
 * Override Management
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Override manager class for CRUD operations
 */
class OverrideManager {
    
    /**
     * Get override for a specific product and date
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return object|null
     */
    public static function getOverride(int $product_id, string $date): ?object {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_overrides';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d AND date = %s",
            $product_id,
            $date
        ));
        
        return $result ?: null;
    }
    
    /**
     * Get all overrides for a product
     *
     * @param int $product_id Product ID
     * @return array
     */
    public static function getOverrides(int $product_id): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_overrides';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d ORDER BY date",
            $product_id
        ));
        
        return $results ?: [];
    }
    
    /**
     * Create or update an override
     *
     * @param array $data Override data
     * @return int|false Override ID on success, false on failure
     */
    public static function saveOverride(array $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_overrides';
        
        $product_id = (int) $data['product_id'];
        $date = sanitize_text_field($data['date']);
        
        // Check if override already exists
        $existing = self::getOverride($product_id, $date);
        
        $override_data = [
            'product_id' => $product_id,
            'date' => $date,
            'is_closed' => isset($data['is_closed']) ? (int) $data['is_closed'] : 0,
            'capacity_override' => isset($data['capacity_override']) && $data['capacity_override'] !== '' 
                ? (int) $data['capacity_override'] : null,
            'price_override_json' => isset($data['price_override_json']) 
                ? wp_json_encode($data['price_override_json']) : null,
            'reason' => isset($data['reason']) ? sanitize_text_field($data['reason']) : null
        ];
        
        if ($existing) {
            // Update existing override
            $result = $wpdb->update(
                $table_name,
                $override_data,
                ['id' => $existing->id],
                ['%d', '%s', '%d', '%d', '%s', '%s'],
                ['%d']
            );
            
            if ($result !== false) {
                // Trigger cache invalidation
                do_action('fp_esperienze_override_saved', $product_id, $date);
            }
            
            return $result !== false ? $existing->id : false;
        } else {
            // Create new override
            $result = $wpdb->insert(
                $table_name,
                $override_data,
                ['%d', '%s', '%d', '%d', '%s', '%s']
            );
            
            if ($result) {
                // Trigger cache invalidation
                do_action('fp_esperienze_override_saved', $product_id, $date);
            }
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Delete an override
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return bool
     */
    public static function deleteOverride(int $product_id, string $date): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_overrides';
        $result = $wpdb->delete(
            $table_name, 
            [
                'product_id' => $product_id,
                'date' => $date
            ], 
            ['%d', '%s']
        );
        
        if ($result !== false) {
            // Trigger cache invalidation
            do_action('fp_esperienze_override_deleted', $product_id, $date);
        }
        
        return $result !== false;
    }
    
    /**
     * Get global closures (all closed dates across all products)
     *
     * @return array
     */
    public static function getGlobalClosures(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_overrides';
        $results = $wpdb->get_results(
            "SELECT o.*, p.post_title as product_name 
             FROM $table_name o 
             LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID 
             WHERE o.is_closed = 1 
             ORDER BY o.date DESC"
        );
        
        return $results ?: [];
    }
    
    /**
     * Create a global closure (applies to all products)
     *
     * @param string $date Date in Y-m-d format
     * @param string $reason Closure reason
     * @return bool
     */
    public static function createGlobalClosure(string $date, string $reason = ''): bool {
        global $wpdb;
        
        // Get all experience products
        $experience_products = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'experience'
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        if (empty($experience_products)) {
            return false;
        }
        
        $success = true;
        
        foreach ($experience_products as $product_id) {
            $result = self::saveOverride([
                'product_id' => $product_id,
                'date' => $date,
                'is_closed' => 1,
                'reason' => $reason
            ]);
            
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Remove a global closure
     *
     * @param string $date Date in Y-m-d format
     * @return bool
     */
    public static function removeGlobalClosure(string $date): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_overrides';
        $result = $wpdb->delete(
            $table_name,
            [
                'date' => $date,
                'is_closed' => 1
            ],
            ['%s', '%d']
        );
        
        return $result !== false;
    }
}