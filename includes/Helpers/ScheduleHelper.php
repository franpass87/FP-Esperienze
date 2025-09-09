<?php
/**
 * Schedule Helper
 *
 * @package FP\Esperienze\Helpers
 */

namespace FP\Esperienze\Helpers;

use FP\Esperienze\Data\ScheduleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Helper class for schedule-related operations
 */
class ScheduleHelper {
	/**
	 * Aggregate existing schedules into builder-friendly format and
	 * migrate legacy schedules missing explicit values.
	 *
	 * @param array $schedules Array of schedule objects.
	 * @param int   $product_id Product ID for meta context.
	 * @return array Array with 'time_slots' and 'raw_schedules' keys.
	 */
	public static function aggregateSchedulesForBuilder( array $schedules, int $product_id ): array {
		$time_slots = array();
		$groups     = array();

		// Defaults used for one-time migration of legacy schedules.
		$defaults = array(
			'duration_min'     => (int) get_post_meta( $product_id, '_fp_exp_duration', true ),
			'capacity'         => (int) get_post_meta( $product_id, '_fp_exp_capacity', true ),
			'lang'             => get_post_meta( $product_id, '_fp_exp_language', true ),
			'meeting_point_id' => (int) get_post_meta( $product_id, '_fp_exp_meeting_point_id', true ),
			'price_adult'      => (float) get_post_meta( $product_id, '_regular_price', true ),
			'price_child'      => (float) get_post_meta( $product_id, '_fp_exp_price_child', true ),
		);

		foreach ( $schedules as $schedule ) {
			$update_data = array();

			// Migrate legacy schedules by filling missing values with defaults.
			foreach ( $defaults as $field => $default ) {
				if ( $schedule->$field === null || $schedule->$field === '' ) {
					if ( $default !== '' && $default !== null ) {
						$schedule->$field      = $default;
						$update_data[ $field ] = $default;
					} else {
						// If we cannot determine a value, skip this schedule.
						continue 2;
					}
				}
			}

			if ( ! empty( $update_data ) ) {
				ScheduleManager::updateSchedule( $schedule->id, $update_data );
			}

			$start_time = substr( $schedule->start_time, 0, 5 );
			$key        = sprintf(
				'%s_%d_%d_%s_%s_%.2f_%.2f',
				$start_time,
				(int) $schedule->duration_min,
				(int) $schedule->capacity,
				$schedule->lang,
				$schedule->meeting_point_id ?: 'null',
				(float) $schedule->price_adult,
				(float) $schedule->price_child
			);

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'start_time'       => $start_time,
					'duration_min'     => (int) $schedule->duration_min,
					'capacity'         => (int) $schedule->capacity,
					'lang'             => $schedule->lang,
					'meeting_point_id' => (int) $schedule->meeting_point_id,
					'price_adult'      => (float) $schedule->price_adult,
					'price_child'      => (float) $schedule->price_child,
					'days'             => array(),
					'schedule_ids'     => array(),
				);
			}

			$groups[ $key ]['days'][]         = (int) $schedule->day_of_week;
			$groups[ $key ]['schedule_ids'][] = $schedule->id;
		}

		foreach ( $groups as $group ) {
			$time_slots[] = $group;
		}

		return array(
			'time_slots'    => $time_slots,
			'raw_schedules' => array(),
		);
	}
}
