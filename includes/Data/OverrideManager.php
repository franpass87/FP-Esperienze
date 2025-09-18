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
        
        $has_capacity_override = isset($data['capacity_override'])
            && $data['capacity_override'] !== ''
            && is_numeric($data['capacity_override']);
        $capacity_override = $has_capacity_override ? (int) $data['capacity_override'] : null;

        $override_data = [
            'product_id' => $product_id,
            'date' => $date,
            'is_closed' => isset($data['is_closed']) ? (int) $data['is_closed'] : 0,
        ];
        $formats = ['%d', '%s', '%d'];

        if ($has_capacity_override) {
            $override_data['capacity_override'] = $capacity_override;
            $formats[] = '%d';
        }

        $override_data['price_override_json'] = isset($data['price_override_json'])
            ? wp_json_encode($data['price_override_json']) : null;
        $formats[] = '%s';

        $override_data['reason'] = isset($data['reason']) ? sanitize_text_field($data['reason']) : null;
        $formats[] = '%s';
        
        if ($existing) {
            // Update existing override
            $result = $wpdb->update(
                $table_name,
                $override_data,
                ['id' => $existing->id],
                $formats,
                ['%d']
            );

            if ($result !== false && ! $has_capacity_override) {
                $null_result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET capacity_override = NULL WHERE id = %d",
                    $existing->id
                ));

                if ($null_result === false) {
                    $result = false;
                }
            }
            
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
                $formats
            );

            if ($result && ! $has_capacity_override) {
                $null_result = $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET capacity_override = NULL WHERE id = %d",
                    $wpdb->insert_id
                ));

                if ($null_result === false) {
                    $result = false;
                }
            }
            
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
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, p.post_title as product_name 
             FROM `{$table_name}` o 
             LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID 
             WHERE o.is_closed = %d 
             ORDER BY o.date DESC",
            1
        ));
        
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
        
        $batch_size = 50;
        $offset     = 0;
        $success    = true;
        $found_any  = false;

        while (true) {
            $query = new \WP_Query([
                'post_type'              => 'product',
                'post_status'            => 'publish',
                'meta_query'             => [
                    [
                        'key'   => '_product_type',
                        'value' => 'experience',
                    ],
                ],
                'posts_per_page'         => $batch_size,
                'offset'                 => $offset,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            $experience_products = $query->posts;

            if (empty($experience_products)) {
                break;
            }

            $found_any = true;

            foreach ($experience_products as $product_id) {
                $result = self::saveOverride([
                    'product_id' => $product_id,
                    'date'       => $date,
                    'is_closed'  => 1,
                    'reason'     => $reason,
                ]);

                if (!$result) {
                    $success = false;
                }
            }

            $offset += $batch_size;
        }

        if (!$found_any) {
            return false;
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