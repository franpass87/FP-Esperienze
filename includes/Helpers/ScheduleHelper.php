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

                foreach ( $schedules as $schedule ) {
                        // Skip schedules missing required data
                        if ( $schedule->duration_min === null || $schedule->duration_min === '' ) {
                                continue;
                        }
                        if ( $schedule->capacity === null || $schedule->capacity === '' ) {
                                continue;
                        }
                        if ( $schedule->lang === null || $schedule->lang === '' ) {
                                continue;
                        }
                        if ( $schedule->price_adult === null || $schedule->price_child === null ) {
                                continue;
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
