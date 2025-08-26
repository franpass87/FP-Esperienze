<?php
/**
 * Override Model - Reference Implementation
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Override model class - reference implementation from PR #5
 */
class Override {
    
    public $id;
    public $product_id;
    public $date;
    public $is_closed;
    public $capacity_override;
    public $price_override_json;
    public $reason;
    public $created_at;
    public $updated_at;
    
    /**
     * Get override by product and date
     *
     * @param int $product_id Product ID  
     * @param string $date Date in Y-m-d format
     * @return Override|null
     */
    public static function getByProductAndDate(int $product_id, string $date): ?Override {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_overrides';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND date = %s",
            $product_id,
            $date
        ));
        
        if (!$row) {
            return null;
        }
        
        $override = new self();
        foreach (get_object_vars($row) as $key => $value) {
            $override->$key = $value;
        }
        
        return $override;
    }
    
    /**
     * Get global closure for a date (product_id = 0)
     *
     * @param string $date Date in Y-m-d format
     * @return Override|null
     */
    public static function getGlobalClosure(string $date): ?Override {
        return self::getByProductAndDate(0, $date);
    }
}