<?php
/**
 * Staff Schedule Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Manager responsible for retrieving staff schedule assignments.
 */
class StaffScheduleManager {

    /**
     * Retrieve assignments for a staff member within a date range.
     *
     * @param int    $staff_user_id Staff user ID.
     * @param string $date_from     Start date (Y-m-d).
     * @param string $date_to       End date (Y-m-d).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAssignmentsForStaff(int $staff_user_id, string $date_from, string $date_to): array {
        global $wpdb;

        $assignments_table     = $wpdb->prefix . 'fp_staff_assignments';
        $bookings_table        = $wpdb->prefix . 'fp_bookings';
        $meeting_points_table  = $wpdb->prefix . 'fp_meeting_points';
        $posts_table           = $wpdb->posts;

        $start_boundary = $date_from . ' 00:00:00';
        $end_boundary   = $date_to . ' 23:59:59';

        $sql = "
            SELECT
                sa.id AS assignment_id,
                sa.staff_id,
                sa.booking_id,
                sa.shift_start,
                sa.shift_end,
                sa.roles,
                sa.notes,
                b.booking_date,
                b.booking_time,
                b.status AS booking_status,
                b.product_id,
                b.participants,
                mp.id AS meeting_point_id,
                mp.name AS meeting_point_name,
                mp.address AS meeting_point_address,
                mp.lat AS meeting_point_lat,
                mp.lng AS meeting_point_lng,
                p.post_title AS product_name
            FROM {$assignments_table} sa
            LEFT JOIN {$bookings_table} b ON b.id = sa.booking_id
            LEFT JOIN {$meeting_points_table} mp ON mp.id = b.meeting_point_id
            LEFT JOIN {$posts_table} p ON p.ID = b.product_id
            WHERE sa.staff_id = %d
                AND (sa.shift_end IS NULL OR sa.shift_end >= %s)
                AND (sa.shift_start IS NULL OR sa.shift_start <= %s)
            ORDER BY sa.shift_start ASC, sa.id ASC
        ";

        $prepared = $wpdb->prepare($sql, $staff_user_id, $start_boundary, $end_boundary);
        $results  = $wpdb->get_results($prepared, 'ARRAY_A');

        if (!$results) {
            return [];
        }

        foreach ($results as &$row) {
            $row['assignment_id'] = (int) $row['assignment_id'];
            $row['staff_id']      = (int) $row['staff_id'];

            if (array_key_exists('booking_id', $row)) {
                $row['booking_id'] = $row['booking_id'] !== null ? (int) $row['booking_id'] : null;
                if ($row['booking_id'] !== null && $row['booking_id'] <= 0) {
                    $row['booking_id'] = null;
                }
            }

            if (array_key_exists('product_id', $row)) {
                $row['product_id'] = $row['product_id'] !== null ? (int) $row['product_id'] : null;
                if ($row['product_id'] !== null && $row['product_id'] <= 0) {
                    $row['product_id'] = null;
                }
            }

            if (array_key_exists('participants', $row)) {
                $row['participants'] = $row['participants'] !== null ? (int) $row['participants'] : null;
            }

            if (array_key_exists('meeting_point_id', $row)) {
                $row['meeting_point_id'] = $row['meeting_point_id'] !== null ? (int) $row['meeting_point_id'] : null;
                if ($row['meeting_point_id'] !== null && $row['meeting_point_id'] <= 0) {
                    $row['meeting_point_id'] = null;
                }
            }

            if (array_key_exists('meeting_point_lat', $row)) {
                $row['meeting_point_lat'] = $row['meeting_point_lat'] !== null ? (float) $row['meeting_point_lat'] : null;
            }

            if (array_key_exists('meeting_point_lng', $row)) {
                $row['meeting_point_lng'] = $row['meeting_point_lng'] !== null ? (float) $row['meeting_point_lng'] : null;
            }
        }
        unset($row);

        return $results;
    }
}
