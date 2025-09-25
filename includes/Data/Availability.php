<?php
/**
 * Availability Management
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use DateTime;
use FP\Esperienze\Core\CacheManager;
use FP\Esperienze\Helpers\TimezoneHelper;

defined( 'ABSPATH' ) || exit;

if (!class_exists('FP\\Esperienze\\Helpers\\TimezoneHelper')) {
    require_once dirname(__DIR__) . '/Helpers/TimezoneHelper.php';
}

/**
 * Availability class for calculating real-time availability
 */
class Availability {

	/**
	 * Get availability slots for a specific day
	 *
	 * @param int    $product_id Product ID
	 * @param string $date Date in Y-m-d format
	 * @return array
	 */
	public static function forDay( int $product_id, string $date ): array {
                $holds_enabled = HoldManager::isEnabled();
                $session_id    = '';
                if ( $holds_enabled && function_exists( 'WC' ) && WC()->session ) {
                        $session_id = WC()->session->get_customer_id();
                }

                // Check cache first for performance
                $cached_data = CacheManager::getAvailabilityCache( $product_id, $date );
                if ( $cached_data !== false && isset( $cached_data['slots'] ) ) {
                        if ( $holds_enabled ) {
                                return self::applyHoldAdjustments( $cached_data['slots'], $product_id, $date, $session_id );
                        }

                        return $cached_data['slots'];
                }

		// Get WordPress timezone
                $wp_timezone = TimezoneHelper::getSiteTimezone();

		// Create date object in WordPress timezone
		$date_obj = DateTime::createFromFormat( 'Y-m-d', $date, $wp_timezone );
		if ( ! $date_obj ) {
			return array();
		}

		// Get day of week (0=Sunday, 1=Monday, etc.)
		$day_of_week = (int) $date_obj->format( 'w' );

		// Check for overrides first
		$override = OverrideManager::getOverride( $product_id, $date );

		// If day is closed, return empty array
		if ( $override && $override->is_closed ) {
			return array();
		}

		// Get schedules for this day
		$schedules = ScheduleManager::getSchedulesForDay( $product_id, $day_of_week, $date );

		if ( empty( $schedules ) ) {
			return array();
		}

		$slots = array();

		foreach ( $schedules as $schedule ) {
			// Create start time
			$start_time = DateTime::createFromFormat( 'Y-m-d H:i:s', $date . ' ' . $schedule->start_time, $wp_timezone );
			if ( ! $start_time ) {
				continue;
			}

			// Calculate end time using explicit duration
			$end_time = clone $start_time;
			$end_time->modify( '+' . (int) $schedule->duration_min . ' minutes' );

			// Use explicit values from schedule
			$capacity         = (int) $schedule->capacity;
			$adult_price      = (float) $schedule->price_adult;
			$child_price      = (float) $schedule->price_child;
			$meeting_point_id = (int) $schedule->meeting_point_id;
			$language         = $schedule->lang;

			// Apply date-specific overrides if they exist
			if ( $override ) {
				// Apply capacity override
				if ( $override->capacity_override !== null ) {
					$capacity = $override->capacity_override;
				}

				// Apply price override
				if ( $override->price_override_json ) {
					$price_override = json_decode( $override->price_override_json, true );
					if ( is_array( $price_override ) ) {
						if ( isset( $price_override['adult'] ) ) {
							$adult_price = (float) $price_override['adult'];
						}
						if ( isset( $price_override['child'] ) ) {
							$child_price = (float) $price_override['child'];
						}
					}
				}
			}

			// Get existing bookings for this slot
			$booked_count = self::getBookedCount( $product_id, $date, $schedule->start_time );

                        // Get held capacity for this slot (if holds are enabled)
                        $held_count = 0;
                        if ( $holds_enabled ) {
                                $slot_datetime_str = $date . ' ' . $start_time->format( 'H:i' ); // Y-m-d H:i format
                                $held_count        = HoldManager::getHeldQuantity( $product_id, $slot_datetime_str, $session_id );
                        }

                        $available_spots = max( 0, $capacity - $booked_count - $held_count );

                        $slots[] = array(
                                'schedule_id'      => $schedule->id,
				'start_time'       => $start_time->format( 'H:i' ),
				'end_time'         => $end_time->format( 'H:i' ),
                                'capacity'         => $capacity,
                                'booked'           => $booked_count,
                                'available'        => $available_spots,
                                'held_count'       => $held_count,
                                'is_available'     => $available_spots > 0,
                                'adult_price'      => $adult_price,
                                'child_price'      => $child_price,
                                'languages'        => $language,
                                'meeting_point_id' => $meeting_point_id,
                        );
                }

		// Cache the result before returning
		$cache_data = array(
			'product_id'  => $product_id,
			'date'        => $date,
			'slots'       => $slots,
			'total_slots' => count( $slots ),
			'_cached_at'  => time(),
		);
		CacheManager::setAvailabilityCache( $product_id, $date, $cache_data );

                return $slots;
        }

        /**
         * Apply hold adjustments to cached slots when hold system is active.
         *
         * @param array  $slots Slots array from cache.
         * @param int    $product_id Product ID.
         * @param string $date Date in Y-m-d format.
         * @param string $session_id Current session identifier.
         * @return array
         */
        private static function applyHoldAdjustments( array $slots, int $product_id, string $date, string $session_id ): array {
                foreach ( $slots as &$slot ) {
                        if ( empty( $slot['start_time'] ) ) {
                                continue;
                        }

                        $slot_datetime_str = $date . ' ' . $slot['start_time'];
                        $held_count        = HoldManager::getHeldQuantity( $product_id, $slot_datetime_str, $session_id );
                        $capacity          = isset( $slot['capacity'] ) ? (int) $slot['capacity'] : 0;
                        $booked            = isset( $slot['booked'] ) ? (int) $slot['booked'] : 0;
                        $available_spots   = max( 0, $capacity - $booked - $held_count );

                        $slot['held_count']   = $held_count;
                        $slot['available']    = $available_spots;
                        $slot['is_available'] = $available_spots > 0;
                }

                unset( $slot );

                return $slots;
        }

	/**
	 * Get count of booked participants for a specific slot
	 *
	 * @param int    $product_id Product ID
	 * @param string $date Date in Y-m-d format
	 * @param string $time Time in H:i:s format
	 * @return int
	 */
	private static function getBookedCount( int $product_id, string $date, string $time ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'fp_bookings';
		$result     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(adults + children) 
             FROM $table_name 
             WHERE product_id = %d 
             AND booking_date = %s 
             AND booking_time = %s 
             AND status IN ('confirmed', 'pending')",
				$product_id,
				$date,
				$time
			)
		);

		return (int) ( $result ?: 0 );
	}

	/**
	 * Check if a specific slot is available
	 *
	 * @param int    $product_id Product ID
	 * @param string $date Date in Y-m-d format
	 * @param string $time Time in H:i format
	 * @param int    $requested_spots Number of spots requested
	 * @return bool
	 */
	public static function isSlotAvailable( int $product_id, string $date, string $time, int $requested_spots = 1 ): bool {
		$slots = self::forDay( $product_id, $date );

		foreach ( $slots as $slot ) {
			if ( $slot['start_time'] === $time ) {
				return $slot['available'] >= $requested_spots;
			}
		}

		return false;
	}

	/**
	 * Get meeting point information for a slot
	 *
	 * @param int $meeting_point_id Meeting point ID
	 * @return object|null
	 */
	public static function getMeetingPoint( int $meeting_point_id ): ?object {
		return MeetingPointManager::getMeetingPoint( $meeting_point_id );
	}

	/**
	 * Get slots for a specific date (alias for forDay for backward compatibility)
	 *
	 * @param int    $product_id Product ID
	 * @param string $date Date in Y-m-d format
	 * @return array
	 */
	public static function getSlotsForDate( int $product_id, string $date ): array {
		return self::forDay( $product_id, $date );
	}
}
