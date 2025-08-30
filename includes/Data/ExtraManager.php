<?php
/**
 * Extra Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Extra Manager class for CRUD operations
 */
class ExtraManager {

    /**
     * Get all extras
     *
     * @param bool $active_only Whether to return only active extras
     * @return array
     */
    public static function getAllExtras(bool $active_only = false): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_extras';
        
        if ($active_only) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE is_active = %d ORDER BY name ASC",
                1
            ));
        } else {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$table_name}` ORDER BY name ASC"
            ));
        }
        
        return $results ?: [];
    }

    /**
     * Get extra by ID
     *
     * @param int $id Extra ID
     * @return object|null
     */
    public static function getExtra(int $id): ?object {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_extras';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
        
        return $result ?: null;
    }

    /**
     * Create a new extra
     *
     * @param array $data Extra data
     * @return int|false Extra ID or false on failure
     */
    public static function createExtra(array $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_extras';
        
        $defaults = [
            'name' => '',
            'description' => '',
            'price' => 0.00,
            'billing_type' => 'per_person',
            'tax_class' => '',
            'is_required' => 0,
            'max_quantity' => 1,
            'is_active' => 1
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $table_name,
            [
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'price' => floatval($data['price']),
                'billing_type' => in_array($data['billing_type'], ['per_person', 'per_booking']) ? $data['billing_type'] : 'per_person',
                'tax_class' => sanitize_text_field($data['tax_class']),
                'is_required' => absint($data['is_required']),
                'max_quantity' => absint($data['max_quantity']),
                'is_active' => absint($data['is_active'])
            ],
            [
                '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%d'
            ]
        );
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an extra
     *
     * @param int $id Extra ID
     * @param array $data Extra data
     * @return bool Success
     */
    public static function updateExtra(int $id, array $data): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_extras';
        
        $update_data = [];
        $format = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['price'])) {
            $update_data['price'] = floatval($data['price']);
            $format[] = '%f';
        }
        
        if (isset($data['billing_type'])) {
            $update_data['billing_type'] = in_array($data['billing_type'], ['per_person', 'per_booking']) ? $data['billing_type'] : 'per_person';
            $format[] = '%s';
        }
        
        if (isset($data['tax_class'])) {
            $update_data['tax_class'] = sanitize_text_field($data['tax_class']);
            $format[] = '%s';
        }
        
        if (isset($data['is_required'])) {
            $update_data['is_required'] = absint($data['is_required']);
            $format[] = '%d';
        }
        
        if (isset($data['max_quantity'])) {
            $update_data['max_quantity'] = absint($data['max_quantity']);
            $format[] = '%d';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = absint($data['is_active']);
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete an extra
     *
     * @param int $id Extra ID
     * @return bool Success
     */
    public static function deleteExtra(int $id): bool {
        global $wpdb;
        
        // Check if extra is associated with any products
        $table_product_extras = $wpdb->prefix . 'fp_product_extras';
        $associations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_product_extras WHERE extra_id = %d",
            $id
        ));
        
        if ($associations > 0) {
            return false; // Cannot delete if in use
        }
        
        $table_name = $wpdb->prefix . 'fp_extras';
        $result = $wpdb->delete(
            $table_name,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Get extras for a product
     *
     * @param int $product_id Product ID
     * @param bool $active_only Whether to return only active extras
     * @return array
     */
    public static function getProductExtras(int $product_id, bool $active_only = true): array {
        global $wpdb;
        
        $table_extras = $wpdb->prefix . 'fp_extras';
        $table_product_extras = $wpdb->prefix . 'fp_product_extras';
        
        $where_clause = $active_only ? "AND e.is_active = 1" : "";
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT e.*, pe.sort_order
            FROM $table_extras e
            INNER JOIN $table_product_extras pe ON e.id = pe.extra_id
            WHERE pe.product_id = %d $where_clause
            ORDER BY pe.sort_order ASC, e.name ASC
        ", $product_id));
        
        return $results ?: [];
    }

    /**
     * Associate extra with product
     *
     * @param int $product_id Product ID
     * @param int $extra_id Extra ID
     * @param int $sort_order Sort order
     * @return bool Success
     */
    public static function associateExtraWithProduct(int $product_id, int $extra_id, int $sort_order = 0): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_product_extras';
        
        $result = $wpdb->replace(
            $table_name,
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
     * Remove extra from product
     *
     * @param int $product_id Product ID
     * @param int $extra_id Extra ID
     * @return bool Success
     */
    public static function removeExtraFromProduct(int $product_id, int $extra_id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_product_extras';
        
        $result = $wpdb->delete(
            $table_name,
            [
                'product_id' => $product_id,
                'extra_id' => $extra_id
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }

    /**
     * Update product extras associations
     *
     * @param int $product_id Product ID
     * @param array $extra_ids Array of extra IDs
     * @return bool Success
     */
    public static function updateProductExtras(int $product_id, array $extra_ids): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_product_extras';
        
        // First remove all existing associations
        $wpdb->delete($table_name, ['product_id' => $product_id], ['%d']);
        
        // Then add new associations
        $success = true;
        foreach ($extra_ids as $index => $extra_id) {
            $result = self::associateExtraWithProduct($product_id, $extra_id, $index);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Get available tax classes
     *
     * @return array
     */
    public static function getTaxClasses(): array {
        $tax_classes = [];
        $tax_classes[''] = __('Standard', 'fp-esperienze');
        
        if (class_exists('WC_Tax')) {
            $classes = \WC_Tax::get_tax_classes();
            foreach ($classes as $class) {
                $tax_classes[sanitize_title($class)] = $class;
            }
        }
        
        return $tax_classes;
    }
}