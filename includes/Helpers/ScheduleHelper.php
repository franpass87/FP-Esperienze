<?php
/**
 * Schedule Helper
 *
 * @package FP\Esperienze\Helpers
 */

namespace FP\Esperienze\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Helper class for schedule-related operations
 */
class ScheduleHelper {

	/**
	 * Placeholder used when generating grouping keys for inherited values.
	 */
	private const INHERIT_PLACEHOLDER = '__inherit__';

	/**
	 * Aggregate existing schedules into builder-friendly format and
	 * migrate legacy schedules missing explicit values.
	 *
	 * @param array<int, object> $schedules Array of schedule records.
	 * @param int                $product_id Product ID for meta context.
	 * @return array{
	 *     time_slots: array<int, array{
	 *         start_time: string,
	 *         duration_min: int|null,
	 *         capacity: int|null,
	 *         lang: string|null,
	 *         meeting_point_id: int|null,
	 *         price_adult: float|null,
	 *         price_child: float|null,
	 *         days: array<int, int>,
	 *         schedule_ids: array<int, int>
	 *     }>,
	 *     raw_schedules: array<int, array{
	 *         id: int|null,
	 *         day_of_week: int|null,
	 *         start_time: string,
	 *         duration_min: int|null,
	 *         capacity: int|null,
	 *         lang: string|null,
	 *         meeting_point_id: int|null,
	 *         price_adult: float|null,
	 *         price_child: float|null
	 *     }>
	 * }
	 */
	public static function aggregateSchedulesForBuilder( array $schedules, int $product_id ): array {
		unset( $product_id ); // Parameter kept for backwards compatibility.

		$time_slots    = array();
		$groups        = array();
		$raw_schedules = array();

		foreach ( $schedules as $schedule ) {
			/**
			 * @var object{
			 *     schedule_type?: string,
			 *     start_time?: string|null,
			 *     duration_min?: int|null,
			 *     capacity?: int|null,
			 *     lang?: string|null,
			 *     meeting_point_id?: int|null,
			 *     price_adult?: float|null,
			 *     price_child?: float|null,
			 *     id?: int|null,
			 *     day_of_week?: int|null
			 * } $schedule
			 */
			if ( isset( $schedule->schedule_type ) && 'recurring' !== $schedule->schedule_type ) {
				continue;
			}

			$start_time = self::normalizeTime( $schedule->start_time ?? null );
			if ( null === $start_time ) {
				continue;
			}

			$duration        = self::normalizeInt( $schedule->duration_min ?? null, true );
			$capacity        = self::normalizeInt( $schedule->capacity ?? null, true );
			$lang            = self::normalizeString( $schedule->lang ?? null );
			$meeting_point   = self::normalizeInt( $schedule->meeting_point_id ?? null, false );
			$price_adult     = self::normalizeFloat( $schedule->price_adult ?? null );
			$price_child     = self::normalizeFloat( $schedule->price_child ?? null );
			$schedule_id     = self::normalizeInt( $schedule->id ?? null );
			$day_of_week_raw = self::normalizeInt( $schedule->day_of_week ?? null, true );

			$raw_schedules[] = array(
				'id'               => $schedule_id,
				'day_of_week'      => $day_of_week_raw,
				'start_time'       => $start_time,
				'duration_min'     => $duration,
				'capacity'         => $capacity,
				'lang'             => $lang,
				'meeting_point_id' => $meeting_point,
				'price_adult'      => $price_adult,
				'price_child'      => $price_child,
			);

			if ( null === $day_of_week_raw ) {
				continue;
			}

			$key = self::buildGroupKey( $start_time, $duration, $capacity, $lang, $meeting_point, $price_adult, $price_child );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'start_time'       => $start_time,
					'duration_min'     => $duration,
					'capacity'         => $capacity,
					'lang'             => $lang,
					'meeting_point_id' => $meeting_point,
					'price_adult'      => $price_adult,
					'price_child'      => $price_child,
					'days'             => array(),
					'schedule_ids'     => array(),
				);
			}

			$groups[ $key ]['days'][] = $day_of_week_raw;

			if ( null !== $schedule_id && $schedule_id > 0 ) {
				$groups[ $key ]['schedule_ids'][] = $schedule_id;
			}
		}

		foreach ( $groups as &$group ) {
			$group['days'] = array_values( array_unique( array_map( 'intval', $group['days'] ) ) );
			sort( $group['days'], SORT_NUMERIC );

			$group['schedule_ids'] = array_values( array_unique( array_map( 'intval', $group['schedule_ids'] ) ) );
		}
		unset( $group );

		$time_slots = array_values( $groups );
		usort(
			$time_slots,
			static function ( array $a, array $b ): int {
				$time_comparison = strcmp( $a['start_time'], $b['start_time'] );
				if ( 0 !== $time_comparison ) {
					return $time_comparison;
				}

				$duration_a          = $a['duration_min'] ?? PHP_INT_MAX;
				$duration_b          = $b['duration_min'] ?? PHP_INT_MAX;
				$duration_comparison = $duration_a <=> $duration_b;
				if ( 0 !== $duration_comparison ) {
					return $duration_comparison;
				}

				$lang_a = $a['lang'] ?? '';
				$lang_b = $b['lang'] ?? '';
				return strcmp( $lang_a, $lang_b );
			}
		);

		$raw_schedules = array_values( $raw_schedules );
		usort(
			$raw_schedules,
			static function ( array $a, array $b ): int {
				$day_a          = $a['day_of_week'] ?? PHP_INT_MAX;
				$day_b          = $b['day_of_week'] ?? PHP_INT_MAX;
				$day_comparison = $day_a <=> $day_b;
				if ( 0 !== $day_comparison ) {
					return $day_comparison;
				}

				return strcmp( $a['start_time'], $b['start_time'] );
			}
		);

		return array(
			'time_slots'    => $time_slots,
			'raw_schedules' => $raw_schedules,
		);
	}

	/**
	 * Normalize a schedule time value to HH:MM format.
	 *
	 * @param mixed $time Raw time value.
	 * @return string|null
	 */
	private static function normalizeTime( $time ): ?string {
		if ( ! is_string( $time ) && ! is_numeric( $time ) ) {
			return null;
		}

		$time = trim( (string) $time );
		if ( '' === $time ) {
			return null;
		}

		if ( 1 === preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches ) ) {
			$hours   = max( 0, min( 23, (int) $matches[1] ) );
			$minutes = max( 0, min( 59, (int) $matches[2] ) );

			return sprintf( '%02d:%02d', $hours, $minutes );
		}

		return null;
	}

	/**
	 * Normalize integers stored as strings or database values.
	 *
	 * @param mixed $value      Raw value.
	 * @param bool  $allow_zero Whether zero should be treated as a valid number.
	 * @return int|null
	 */
	private static function normalizeInt( $value, bool $allow_zero = true ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			$int_value = (int) $value;

			if ( ! $allow_zero && 0 === $int_value ) {
				return null;
			}

			return $int_value;
		}

		return null;
	}

	/**
	 * Normalize floating point values.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	private static function normalizeFloat( $value ): ?float {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		return null;
	}

	/**
	 * Normalize string values, returning null when empty.
	 *
	 * @param string|null $value Raw value.
	 * @return string|null
	 */
	private static function normalizeString( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		if ( function_exists( 'sanitize_text_field' ) ) {
			$value = sanitize_text_field( $value );
		}

		return strtolower( $value );
	}

	/**
	 * Build a grouping key for a schedule time slot.
	 *
	 * @param string      $start_time    Normalized start time.
	 * @param int|null    $duration      Duration in minutes.
	 * @param int|null    $capacity      Capacity.
	 * @param string|null $lang          Language code.
	 * @param int|null    $meeting_point Meeting point ID.
	 * @param float|null  $price_adult   Adult price.
	 * @param float|null  $price_child   Child price.
	 * @return string
	 */
	private static function buildGroupKey( string $start_time, ?int $duration, ?int $capacity, ?string $lang, ?int $meeting_point, ?float $price_adult, ?float $price_child ): string {
		$parts = array(
			$start_time,
			self::valueForKey( $duration ),
			self::valueForKey( $capacity ),
			self::valueForKey( $lang ),
			self::valueForKey( $meeting_point ),
			self::valueForKey( $price_adult, '%.2f' ),
			self::valueForKey( $price_child, '%.2f' ),
		);

		return implode( '|', $parts );
	}

	/**
	 * Format a value for usage inside a grouping key.
	 *
	 * @param mixed       $value  Value to format.
	 * @param string|null $format Optional sprintf format.
	 * @return string
	 */
	private static function valueForKey( $value, ?string $format = null ): string {
		if ( null === $value ) {
			return self::INHERIT_PLACEHOLDER;
		}

		if ( null !== $format ) {
			return sprintf( $format, $value );
		}

		return (string) $value;
	}
}
