<?php
/**
 * Override Model
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Override model class
 */
class Override {
    
    /**
     * Override properties
     */
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
     * Constructor
     *
     * @param object|array $data Override data
     */
    public function __construct($data = null) {
        if ($data) {
            $this->fill($data);
        }
    }

    /**
     * Fill model with data
     *
     * @param object|array $data
     */
    public function fill($data): void {
        $data = (object) $data;
        
        $this->id = $data->id ?? null;
        $this->product_id = $data->product_id ?? null;
        $this->date = $data->date ?? null;
        $this->is_closed = $data->is_closed ?? 0;
        $this->capacity_override = $data->capacity_override ?? null;
        $this->price_override_json = $data->price_override_json ?? null;
        $this->reason = $data->reason ?? null;
        $this->created_at = $data->created_at ?? null;
        $this->updated_at = $data->updated_at ?? null;
    }

    /**
     * Get override for a product on a specific date
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return Override|null
     */
    public static function getByDate(int $product_id, string $date): ?self {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_overrides';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND date = %s",
            $product_id,
            $date
        ));
        
        return $result ? new self($result) : null;
    }

    /**
     * Get all overrides for a product
     *
     * @param int $product_id Product ID
     * @return array Array of Override objects
     */
    public static function getByProduct(int $product_id): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_overrides';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d ORDER BY date",
            $product_id
        ));
        
        $overrides = [];
        foreach ($results as $row) {
            $overrides[] = new self($row);
        }
        
        return $overrides;
    }

    /**
     * Get all global closures (product_id = 0)
     *
     * @return array Array of Override objects
     */
    public static function getGlobalClosures(): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_overrides';
        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE product_id = 0 AND is_closed = 1 ORDER BY date"
        );
        
        $overrides = [];
        foreach ($results as $row) {
            $overrides[] = new self($row);
        }
        
        return $overrides;
    }

    /**
     * Save override
     *
     * @return bool|int False on failure, ID on success
     */
    public function save() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_overrides';
        $data = [
            'product_id' => $this->product_id,
            'date' => $this->date,
            'is_closed' => $this->is_closed,
            'capacity_override' => $this->capacity_override,
            'price_override_json' => $this->price_override_json,
            'reason' => $this->reason,
        ];
        
        if ($this->id) {
            // Update existing
            $result = $wpdb->update($table, $data, ['id' => $this->id]);
            return $result !== false ? $this->id : false;
        } else {
            // Insert new
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
            if ($result) {
                $this->id = $wpdb->insert_id;
                return $this->id;
            }
            return false;
        }
    }

    /**
     * Delete override
     *
     * @return bool
     */
    public function delete(): bool {
        if (!$this->id) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'fp_overrides';
        
        return $wpdb->delete($table, ['id' => $this->id]) !== false;
    }

    /**
     * Get price overrides as array
     *
     * @return array
     */
    public function getPriceOverrides(): array {
        if (!$this->price_override_json) {
            return [];
        }
        
        $decoded = json_decode($this->price_override_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Set price overrides from array
     *
     * @param array $prices
     */
    public function setPriceOverrides(array $prices): void {
        $this->price_override_json = json_encode($prices);
    }

    /**
     * Check if this override affects availability
     *
     * @return bool
     */
    public function affectsAvailability(): bool {
        return $this->is_closed || $this->capacity_override !== null || !empty($this->getPriceOverrides());
    }
}