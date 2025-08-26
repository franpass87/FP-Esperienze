<?php
/**
 * Extra Management
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Extra manager class for CRUD operations
 */
class ExtraManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor implementation
    }

    /**
     * Get all extras
     *
     * @param array $args Query arguments
     * @return array Array of extras
     */
    public static function getAllExtras(array $args = []): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_extras';
        $where = ['1=1'];
        
        // Filter by active status
        if (isset($args['is_active'])) {
            $where[] = $wpdb->prepare('is_active = %d', $args['is_active']);
        }
        
        // Build query
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Get extra by ID
     *
     * @param int $id Extra ID
     * @return array|null Extra data or null if not found
     */
    public static function getExtra(int $id): ?array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_extras';
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id);
        
        $result = $wpdb->get_row($sql, ARRAY_A);
        return $result ?: null;
    }

    /**
     * Create extra
     *
     * @param array $data Extra data
     * @return int|false Extra ID on success, false on failure
     */
    public static function createExtra(array $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_extras';
        
        // Validate required fields
        $required = ['name', 'price', 'pricing_type'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }
        
        // Prepare data for insertion
        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'price' => floatval($data['price']),
            'pricing_type' => in_array($data['pricing_type'], ['per_person', 'per_booking']) ? $data['pricing_type'] : 'per_person',
            'is_required' => isset($data['is_required']) ? intval($data['is_required']) : 0,
            'max_quantity' => intval($data['max_quantity'] ?? 1),
            'tax_class' => sanitize_text_field($data['tax_class'] ?? ''),
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
        ];
        
        $formats = ['%s', '%s', '%f', '%s', '%d', '%d', '%s', '%d'];
        
        $result = $wpdb->insert($table, $insert_data, $formats);
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update extra
     *
     * @param int $id Extra ID
     * @param array $data Extra data
     * @return bool Success status
     */
    public static function updateExtra(int $id, array $data): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_extras';
        
        // Prepare data for update
        $update_data = [];
        $formats = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $formats[] = '%s';
        }
        
        if (isset($data['price'])) {
            $update_data['price'] = floatval($data['price']);
            $formats[] = '%f';
        }
        
        if (isset($data['pricing_type'])) {
            $update_data['pricing_type'] = in_array($data['pricing_type'], ['per_person', 'per_booking']) ? $data['pricing_type'] : 'per_person';
            $formats[] = '%s';
        }
        
        if (isset($data['is_required'])) {
            $update_data['is_required'] = intval($data['is_required']);
            $formats[] = '%d';
        }
        
        if (isset($data['max_quantity'])) {
            $update_data['max_quantity'] = intval($data['max_quantity']);
            $formats[] = '%d';
        }
        
        if (isset($data['tax_class'])) {
            $update_data['tax_class'] = sanitize_text_field($data['tax_class']);
            $formats[] = '%s';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = intval($data['is_active']);
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete extra
     *
     * @param int $id Extra ID
     * @return bool Success status
     */
    public static function deleteExtra(int $id): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_extras';
        
        // First remove associations
        self::removeAllProductAssociations($id);
        
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);
        
        return $result !== false;
    }

    /**
     * Get extras for a product
     *
     * @param int $product_id Product ID
     * @return array Array of extras
     */
    public static function getProductExtras(int $product_id): array {
        global $wpdb;
        
        $extras_table = $wpdb->prefix . 'fp_extras';
        $assoc_table = $wpdb->prefix . 'fp_product_extras';
        
        $sql = $wpdb->prepare("
            SELECT e.*, pe.sort_order 
            FROM $extras_table e
            INNER JOIN $assoc_table pe ON e.id = pe.extra_id
            WHERE pe.product_id = %d AND e.is_active = 1
            ORDER BY pe.sort_order ASC, e.name ASC
        ", $product_id);
        
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Associate extra with product
     *
     * @param int $product_id Product ID
     * @param int $extra_id Extra ID
     * @param int $sort_order Sort order
     * @return bool Success status
     */
    public static function associateProductExtra(int $product_id, int $extra_id, int $sort_order = 0): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_product_extras';
        
        $result = $wpdb->replace(
            $table,
            [
                'product_id' => $product_id,
                'extra_id' => $extra_id,
                'sort_order' => $sort_order
            ],
            ['%d', '%d', '%d']
        );
        
        return $result !== false;
    }

    /**
     * Remove product-extra association
     *
     * @param int $product_id Product ID
     * @param int $extra_id Extra ID
     * @return bool Success status
     */
    public static function removeProductAssociation(int $product_id, int $extra_id): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_product_extras';
        
        $result = $wpdb->delete(
            $table,
            [
                'product_id' => $product_id,
                'extra_id' => $extra_id
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }

    /**
     * Remove all product associations for an extra
     *
     * @param int $extra_id Extra ID
     * @return bool Success status
     */
    public static function removeAllProductAssociations(int $extra_id): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_product_extras';
        
        $result = $wpdb->delete($table, ['extra_id' => $extra_id], ['%d']);
        
        return $result !== false;
    }

    /**
     * Set product extras (replace all associations)
     *
     * @param int $product_id Product ID
     * @param array $extra_ids Array of extra IDs
     * @return bool Success status
     */
    public static function setProductExtras(int $product_id, array $extra_ids): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_product_extras';
        
        // Remove existing associations
        $wpdb->delete($table, ['product_id' => $product_id], ['%d']);
        
        // Add new associations
        foreach ($extra_ids as $index => $extra_id) {
            self::associateProductExtra($product_id, intval($extra_id), $index);
        }
        
        return true;
    }
}