<?php
/**
 * Enhanced REST API Manager for Mobile App Support
 *
 * Expanded REST API with mobile-specific endpoints, QR code functionality,
 * push notifications, and offline support capabilities.
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\RateLimiter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DateTime;

defined('ABSPATH') || exit;

/**
 * Enhanced REST API Manager for mobile applications
 */
class MobileAPIManager {

    /**
     * API namespace
     */
    private const API_NAMESPACE = 'fp-esperienze/v2';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'registerMobileEndpoints']);
    }

    /**
     * Register mobile-specific REST endpoints
     */
    public function registerMobileEndpoints(): void {
        // Authentication endpoints
        register_rest_route(self::API_NAMESPACE, '/mobile/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'mobileLogin'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/auth/register', [
            'methods' => 'POST',
            'callback' => [$this, 'mobileRegister'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/auth/confirm', [
            'methods' => 'GET',
            'callback' => [$this, 'mobileConfirm'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/auth/logout', [
            'methods' => 'POST',
            'callback' => [$this, 'mobileLogout'],
            'permission_callback' => [$this, 'checkMobileAuth']
        ]);

        // Experience endpoints
        register_rest_route(self::API_NAMESPACE, '/mobile/experiences', [
            'methods' => 'GET',
            'callback' => [$this, 'getMobileExperiences'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/experiences/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getMobileExperience'],
            'permission_callback' => '__return_true'
        ]);

        // Booking endpoints
        register_rest_route(self::API_NAMESPACE, '/mobile/bookings', [
            'methods' => 'GET',
            'callback' => [$this, 'getMobileBookings'],
            'permission_callback' => [$this, 'checkMobileAuth']
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/bookings', [
            'methods' => 'POST',
            'callback' => [$this, 'createMobileBooking'],
            'permission_callback' => [$this, 'checkMobileAuth']
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/bookings/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getMobileBooking'],
            'permission_callback' => [$this, 'checkMobileAuth']
        ]);

        // QR Code endpoints
        register_rest_route(self::API_NAMESPACE, '/mobile/qr/generate/(?P<booking_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'generateBookingQR'],
            'permission_callback' => [$this, 'checkMobileAuth']
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/qr/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'scanQRCode'],
            'permission_callback' => [$this, 'checkStaffAuth']
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/qr/checkin', [
            'methods' => 'POST',
            'callback' => [$this, 'processQRCheckin'],
            'permission_callback' => [$this, 'checkStaffAuth']
        ]);

        // Push notification endpoints
        register_rest_route(self::API_NAMESPACE, '/mobile/notifications/register', [
            'methods' => 'POST',
            'callback' => [$this, 'registerPushToken'],
            'permission_callback' => [$this, 'checkMobileAuth']
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/notifications/send', [
            'methods' => 'POST',
            'callback' => [$this, 'sendPushNotification'],
            'permission_callback' => [$this, 'checkStaffAuth']
        ]);

        // Offline sync endpoints
        register_rest_route(self::API_NAMESPACE, '/mobile/sync/bookings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSyncBookings'],
            'permission_callback' => [$this, 'checkStaffAuth']
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/sync/offline-actions', [
            'methods' => 'POST',
            'callback' => [$this, 'processOfflineActions'],
            'permission_callback' => [$this, 'checkStaffAuth']
        ]);

        // Staff management endpoints
        register_rest_route(self::API_NAMESPACE, '/mobile/staff/schedule', [
            'methods' => 'GET',
            'callback' => [$this, 'getStaffSchedule'],
            'permission_callback' => [$this, 'checkStaffAuth']
        ]);

        register_rest_route(self::API_NAMESPACE, '/mobile/staff/attendance', [
            'methods' => 'POST',
            'callback' => [$this, 'recordStaffAttendance'],
            'permission_callback' => [$this, 'checkStaffAuth']
        ]);
    }

    /**
     * Mobile login endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function mobileLogin(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // Rate limiting for login attempts
        $client_ip = $this->getClientIP();
        $rate_limit_key = 'mobile_login_attempts_' . $client_ip;
        $attempts = (int) get_transient($rate_limit_key);
        
        if ($attempts >= 5) {
            return new WP_Error('rate_limit_exceeded', __('Too many login attempts. Please try again later.', 'fp-esperienze'), ['status' => 429]);
        }

        $username = sanitize_text_field(wp_unslash($request->get_param('username')));
        $password = wp_unslash($request->get_param('password'));
        $device_info = $request->get_param('device_info');

        if (empty($username) || empty($password)) {
            return new WP_Error('missing_credentials', __('Username and password are required', 'fp-esperienze'), ['status' => 400]);
        }

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            // Increment failed login attempts
            set_transient($rate_limit_key, $attempts + 1, 900); // 15 minutes
            return new WP_Error('invalid_credentials', __('Invalid username or password', 'fp-esperienze'), ['status' => 401]);
        }

        // Clear rate limiting on successful login
        delete_transient($rate_limit_key);

        if (!get_user_meta($user->ID, '_mobile_email_verified', true)) {
            return new WP_Error(
                'email_not_verified',
                __('Please verify your email before logging in.', 'fp-esperienze'),
                ['status' => 403]
            );
        }

        // Generate mobile auth token
        $token = $this->generateMobileToken($user->ID);
        
        // Store device info
        if ($device_info) {
            update_user_meta($user->ID, '_mobile_device_info', sanitize_text_field($device_info));
        }

        return new WP_REST_Response([
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles,
                'capabilities' => [
                    'can_manage_bookings' => CapabilityManager::userCan($user->ID, 'manage_bookings'),
                    'can_check_in_customers' => CapabilityManager::userCan($user->ID, 'check_in_customers'),
                    'can_view_reports' => CapabilityManager::userCan($user->ID, 'view_reports')
                ]
            ]
        ]);
    }

    /**
     * Mobile registration endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function mobileRegister(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // Rate limiting for registration attempts (5 requests per 15 minutes per IP)
        if (!RateLimiter::checkRateLimit('mobile_register', 5, 900)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many registration attempts. Please try again later.', 'fp-esperienze'),
                ['status' => 429]
            );
        }

        $username = sanitize_text_field(wp_unslash($request->get_param('username')));
        $email = sanitize_email($request->get_param('email'));
        $password = wp_unslash($request->get_param('password'));

        if (empty($username) || empty($email) || empty($password)) {
            return new WP_Error('missing_fields', __('Username, email and password are required', 'fp-esperienze'), ['status' => 400]);
        }

        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address', 'fp-esperienze'), ['status' => 400]);
        }

        if (username_exists($username) || email_exists($email)) {
            return new WP_Error('user_exists', __('Username or email already exists', 'fp-esperienze'), ['status' => 400]);
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $verify_token = wp_generate_password(20, false);
        update_user_meta($user_id, '_mobile_confirm_token', $verify_token);
        update_user_meta($user_id, '_mobile_email_verified', 0);

        $verification_url = add_query_arg(
            [
                'user_id' => $user_id,
                'token'   => $verify_token,
            ],
            rest_url(self::API_NAMESPACE . '/mobile/auth/confirm')
        );

        $subject = __('Confirm your account', 'fp-esperienze');
        $message = sprintf(
            __('Please confirm your account by clicking the following link: %s', 'fp-esperienze'),
            $verification_url
        );

        wp_mail($email, $subject, $message);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Registration successful. Please check your email to confirm your account.', 'fp-esperienze')
        ], 201);
    }

    /**
     * Email confirmation endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function mobileConfirm(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $user_id = absint($request->get_param('user_id'));
        $token   = sanitize_text_field($request->get_param('token'));

        if (!$user_id || empty($token)) {
            return new WP_Error('invalid_token', __('Invalid verification link.', 'fp-esperienze'), ['status' => 400]);
        }

        $saved_token = get_user_meta($user_id, '_mobile_confirm_token', true);

        if (!$saved_token || !hash_equals($saved_token, $token)) {
            return new WP_Error('invalid_token', __('Invalid or expired token.', 'fp-esperienze'), ['status' => 400]);
        }

        update_user_meta($user_id, '_mobile_email_verified', 1);
        delete_user_meta($user_id, '_mobile_confirm_token');

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Email verified. You can now login.', 'fp-esperienze')
        ]);
    }

    /**
     * Mobile logout endpoint
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function mobileLogout(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $auth = $this->checkMobileAuth($request);
        if ($auth !== true) {
            return $auth;
        }

        $user_id = $this->getCurrentMobileUserId($request);

        if ($user_id) {
            update_user_meta($user_id, '_mobile_token_revoked', time());
        }

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Get mobile-optimized experiences
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function getMobileExperiences(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // Apply rate limiting (30 requests per minute per IP)
        if (!RateLimiter::checkRateLimit('mobile_experiences', 30, 60)) {
            return RateLimiter::createRateLimitResponse();
        }

        // Enhanced input validation
        $page = max(1, intval($request->get_param('page') ?: 1));
        $per_page = max(1, min(intval($request->get_param('per_page') ?: 20), 100));
        $category = sanitize_text_field($request->get_param('category'));
        $location = sanitize_text_field($request->get_param('location'));
        $date_from = sanitize_text_field($request->get_param('date_from'));
        
        // Validate date format if provided
        if ($date_from && !DateTime::createFromFormat('Y-m-d', $date_from)) {
            return new WP_Error('invalid_date', __('Invalid date format. Use Y-m-d format.', 'fp-esperienze'), ['status' => 400]);
        }
        
        // Validate category exists if provided
        if ($category && !term_exists($category, 'product_cat')) {
            return new WP_Error('invalid_category', __('Category does not exist', 'fp-esperienze'), ['status' => 400]);
        }

        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'experience'
                ]
            ]
        ];

        if ($category) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $category
            ];
        }

        $experiences = get_posts($args);
        $mobile_experiences = [];

        foreach ($experiences as $experience) {
            $product = wc_get_product($experience->ID);
            
            $mobile_experiences[] = [
                'id' => $experience->ID,
                'name' => $experience->post_title,
                'description' => wp_trim_words($experience->post_content, 20),
                'short_description' => $product->get_short_description(),
                'price' => floatval($product->get_price()),
                'currency' => get_woocommerce_currency(),
                'images' => $this->getMobileProductImages($product),
                'rating' => floatval($product->get_average_rating()),
                'review_count' => intval($product->get_review_count()),
                'duration' => get_post_meta($experience->ID, '_experience_duration', true),
                'location' => get_post_meta($experience->ID, '_experience_location', true),
                'categories' => wp_get_post_terms($experience->ID, 'product_cat', ['fields' => 'names']),
                'available_dates' => $this->getAvailableDates($experience->ID, $date_from),
                'features' => get_post_meta($experience->ID, '_experience_features', true)
            ];
        }

        return new WP_REST_Response([
            'experiences' => $mobile_experiences,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => wp_count_posts('product')->publish,
                'has_more' => count($experiences) === $per_page
            ]
        ]);
    }

    /**
     * Get single mobile experience
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function getMobileExperience(WP_REST_Request $request): WP_REST_Response|WP_Error {
        // Apply rate limiting (30 requests per minute per IP)
        if (!RateLimiter::checkRateLimit('mobile_experience', 30, 60)) {
            return RateLimiter::createRateLimitResponse();
        }

        $id = intval($request->get_param('id'));
        $product = wc_get_product($id);

        if (!$product || $product->get_type() !== 'experience') {
            return new WP_Error('not_found', __('Experience not found', 'fp-esperienze'), ['status' => 404]);
        }

        $experience_data = [
            'id' => $id,
            'name' => $product->get_name(),
            'description' => wp_kses_post($product->get_description()),
            'short_description' => wp_kses_post($product->get_short_description()),
            'price' => floatval($product->get_price()),
            'currency' => get_woocommerce_currency(),
            'images' => $this->getMobileProductImages($product),
            'gallery' => $this->getMobileProductGallery($product),
            'rating' => floatval($product->get_average_rating()),
            'review_count' => intval($product->get_review_count()),
            'reviews' => $this->getMobileReviews($id),
            'duration' => get_post_meta($id, '_experience_duration', true),
            'location' => get_post_meta($id, '_experience_location', true),
            'meeting_points' => $this->getMeetingPoints($id),
            'included' => wp_strip_all_tags((string) get_post_meta($id, '_experience_included', true)),
            'excluded' => wp_strip_all_tags((string) get_post_meta($id, '_experience_excluded', true)),
            'requirements' => wp_strip_all_tags((string) get_post_meta($id, '_experience_requirements', true)),
            'cancellation_policy' => wp_strip_all_tags((string) get_post_meta($id, '_cancellation_policy', true)),
            'available_dates' => $this->getAvailableDates($id),
            'extras' => $this->getExperienceExtras($id),
            'similar_experiences' => $this->getSimilarExperiences($id)
        ];

        return new WP_REST_Response($experience_data);
    }

    /**
     * Get mobile bookings for authenticated user
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function getMobileBookings(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->getCurrentMobileUserId($request);
        $status = sanitize_text_field($request->get_param('status'));

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'fp_bookings';

        $where_conditions = ["customer_id = %d"];
        $where_params = [$user_id];

        if ($status) {
            $where_conditions[] = "status = %s";
            $where_params[] = $status;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$bookings_table}
            WHERE {$where_clause}
            ORDER BY booking_date DESC
        ", $where_params));

        $mobile_bookings = [];

        foreach ($bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            
            $mobile_bookings[] = [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'experience' => [
                    'id' => $booking->product_id,
                    'name' => $product ? $product->get_name() : '',
                    'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'medium') : ''
                ],
                'booking_date' => $booking->booking_date,
                'participants' => intval($booking->participants),
                'status' => $booking->status,
                'total_amount' => floatval($booking->total_amount),
                'currency' => get_woocommerce_currency(),
                'meeting_point' => $this->getMeetingPointById($booking->meeting_point_id),
                'qr_code' => $this->generateBookingQRData($booking->id),
                'can_cancel' => $this->canCancelBooking($booking),
                'can_reschedule' => $this->canRescheduleBooking($booking)
            ];
        }

        return new WP_REST_Response([
            'bookings' => $mobile_bookings,
            'total' => count($mobile_bookings)
        ]);
    }

    /**
     * Create mobile booking
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function createMobileBooking(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $user_id = $this->getCurrentMobileUserId($request);
        
        $booking_data = [
            'product_id' => intval($request->get_param('product_id')),
            'booking_date' => sanitize_text_field($request->get_param('booking_date')),
            'participants' => intval($request->get_param('participants')),
            'meeting_point_id' => intval($request->get_param('meeting_point_id')),
            'extras' => $request->get_param('extras') ?: [],
            'customer_notes' => sanitize_textarea_field($request->get_param('notes'))
        ];

        // Validate booking data
        $validation = $this->validateBookingData($booking_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Create booking
        $booking_manager = new \FP\Esperienze\Booking\BookingManager();
        $booking_id = $booking_manager->createBooking($user_id, $booking_data);

        if (is_wp_error($booking_id)) {
            return $booking_id;
        }

        // Return created booking
        return $this->getMobileBooking(new WP_REST_Request('GET', '', ['id' => $booking_id]));
    }

    /**
     * Get single mobile booking
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function getMobileBooking(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $booking_id = intval($request->get_param('id'));
        $user_id = $this->getCurrentMobileUserId($request);

        global $wpdb;
        $bookings_table = $wpdb->prefix . 'fp_bookings';

        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$bookings_table}
            WHERE id = %d AND customer_id = %d
        ", $booking_id, $user_id));

        if (!$booking) {
            return new WP_Error('not_found', __('Booking not found', 'fp-esperienze'), ['status' => 404]);
        }

        $product = wc_get_product($booking->product_id);
        $meeting_point = $this->getMeetingPointById($booking->meeting_point_id);

        $booking_details = [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'experience' => [
                'id' => $booking->product_id,
                'name' => $product ? $product->get_name() : '',
                'description' => $product ? $product->get_short_description() : '',
                'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'large') : '',
                'duration' => get_post_meta($booking->product_id, '_experience_duration', true)
            ],
            'booking_date' => $booking->booking_date,
            'participants' => intval($booking->participants),
            'status' => $booking->status,
            'total_amount' => floatval($booking->total_amount),
            'currency' => get_woocommerce_currency(),
            'meeting_point' => $meeting_point,
            'extras' => $this->getBookingExtras($booking->id),
            'customer_notes' => $booking->customer_notes,
            'qr_code' => $this->generateBookingQRData($booking->id),
            'voucher_url' => $this->getBookingVoucherUrl($booking->id),
            'cancellation_policy' => get_post_meta($booking->product_id, '_cancellation_policy', true),
            'contact_info' => [
                'phone' => get_option('fp_esperienze_contact_phone', ''),
                'email' => get_option('fp_esperienze_contact_email', get_option('admin_email')),
                'whatsapp' => get_option('fp_esperienze_whatsapp', '')
            ]
        ];

        return new WP_REST_Response($booking_details);
    }

    /**
     * Generate QR code for booking
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function generateBookingQR(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $booking_id = intval($request->get_param('booking_id'));
        $user_id = $this->getCurrentMobileUserId($request);

        // Verify booking belongs to user
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}fp_bookings
            WHERE id = %d AND customer_id = %d
        ", $booking_id, $user_id));

        if (!$booking) {
            return new WP_Error('not_found', __('Booking not found', 'fp-esperienze'), ['status' => 404]);
        }

        $qr_data = $this->generateBookingQRData($booking_id);
        $qr_image = $this->generateQRCodeImage($qr_data);

        return new WP_REST_Response([
            'qr_data' => $qr_data,
            'qr_image' => $qr_image,
            'booking_id' => $booking_id,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ]);
    }

    /**
     * Scan QR code (staff only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function scanQRCode(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $qr_data = sanitize_text_field($request->get_param('qr_data'));
        
        $booking_info = $this->decodeQRData($qr_data);
        
        if (!$booking_info) {
            return new WP_Error('invalid_qr', __('Invalid QR code', 'fp-esperienze'), ['status' => 400]);
        }

        $booking_id = $booking_info['booking_id'];
        
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*, u.display_name as customer_name, u.user_email as customer_email
            FROM {$wpdb->prefix}fp_bookings b
            LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'fp-esperienze'), ['status' => 404]);
        }

        $product = wc_get_product($booking->product_id);

        return new WP_REST_Response([
            'booking' => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'experience_name' => $product ? $product->get_name() : '',
                'booking_date' => $booking->booking_date,
                'participants' => intval($booking->participants),
                'status' => $booking->status,
                'checked_in' => $booking->checked_in_at ? true : false,
                'checked_in_at' => $booking->checked_in_at
            ],
            'can_check_in' => $booking->status === 'confirmed' && !$booking->checked_in_at
        ]);
    }

    /**
     * Process QR check-in (staff only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function processQRCheckin(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $booking_id = intval($request->get_param('booking_id'));
        $staff_user_id = $this->getCurrentMobileUserId($request);
        
        global $wpdb;
        
        // Get booking
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}fp_bookings WHERE id = %d
        ", $booking_id));

        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'fp-esperienze'), ['status' => 404]);
        }

        if ($booking->status !== 'confirmed') {
            return new WP_Error('invalid_status', __('Booking cannot be checked in', 'fp-esperienze'), ['status' => 400]);
        }

        if ($booking->checked_in_at) {
            return new WP_Error('already_checked_in', __('Booking already checked in', 'fp-esperienze'), ['status' => 400]);
        }

        // Perform check-in
        $result = $wpdb->update(
            $wpdb->prefix . 'fp_bookings',
            [
                'checked_in_at' => current_time('mysql'),
                'checked_in_by' => $staff_user_id
            ],
            ['id' => $booking_id],
            ['%s', '%d'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('checkin_failed', __('Check-in failed', 'fp-esperienze'), ['status' => 500]);
        }

        // Send check-in notification
        $this->sendCheckinNotification($booking_id, $staff_user_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Customer checked in successfully', 'fp-esperienze'),
            'checked_in_at' => current_time('mysql'),
            'checked_in_by' => get_user_by('id', $staff_user_id)->display_name
        ]);
    }

    /**
     * Register push notification token.
     *
     * Allowed token characters: letters, numbers, colon (:), dash (-), period (.), underscore (_).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function registerPushToken( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        if (!RateLimiter::checkRateLimit('register_push_token', 30, 60)) {
            return new WP_Error('rate_limit_exceeded', __('Too many requests', 'fp-esperienze'), ['status' => 429]);
        }

        $user_id = $this->getCurrentMobileUserId( $request );
        // Allow letters, numbers, colon, dash, dot and underscore in token.
        $token    = preg_replace( '/[^A-Za-z0-9:\-._]/', '', wp_unslash( $request->get_param( 'token' ) ) );
        $platform = sanitize_text_field( $request->get_param( 'platform' ) ); // ios, android

        if ( empty( $token ) ) {
            return new WP_REST_Response( [ 'error' => 'Token is required' ], 400 );
        }

        // Store push token in array to support multiple devices.
        $tokens = get_user_meta( $user_id, '_push_notification_tokens', true );
        $expiries = get_user_meta( $user_id, '_push_token_expires_at', true );

        if ( ! is_array( $tokens ) ) {
            $tokens = [];
        }

        if ( ! is_array( $expiries ) ) {
            $expiries = [];
        }

        if ( ! in_array( $token, $tokens, true ) ) {
            $tokens[] = $token;
            update_user_meta( $user_id, '_push_notification_tokens', $tokens );
        }

        $expiries[ $token ] = time() + ( 90 * DAY_IN_SECONDS );
        update_user_meta( $user_id, '_push_token_expires_at', $expiries );

        update_user_meta( $user_id, '_push_platform', $platform );
        update_user_meta( $user_id, '_push_registered_at', current_time( 'mysql' ) );

        return new WP_REST_Response(
            [
                'success' => true,
                'message' => __( 'Push token registered successfully', 'fp-esperienze' ),
            ]
        );
    }

    /**
     * Send push notification (staff only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function sendPushNotification(WP_REST_Request $request): WP_REST_Response|WP_Error {
        if (!RateLimiter::checkRateLimit('send_push_notification', 30, 60)) {
            return new WP_Error('rate_limit_exceeded', __('Too many requests', 'fp-esperienze'), ['status' => 429]);
        }

        $recipient_id = intval($request->get_param('recipient_id'));
        $title = sanitize_text_field($request->get_param('title'));
        $message = sanitize_text_field( $request->get_param( 'message' ) );
        $data    = array_map( 'sanitize_text_field', (array) $request->get_param( 'data' ) );

        $allowed_keys = [ 'url', 'booking_id' ];
        $data         = array_intersect_key( $data, array_flip( $allowed_keys ) );

        if ( isset( $data['url'] ) ) {
            $data['url'] = esc_url_raw( $data['url'] );
        }

        if ( isset( $data['booking_id'] ) ) {
            $data['booking_id'] = absint( $data['booking_id'] );
        }

        if (empty($recipient_id) || empty($title) || empty($message)) {
            return new WP_Error('missing_params', __('Recipient, title and message are required', 'fp-esperienze'), ['status' => 400]);
        }

        $result = $this->sendPushToUser($recipient_id, $title, $message, $data);

        if ($result) {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Notification sent successfully', 'fp-esperienze')
            ]);
        } else {
            return new WP_Error('send_failed', __('Failed to send notification', 'fp-esperienze'), ['status' => 500]);
        }
    }

    /**
     * Get sync bookings for offline mode (staff only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function getSyncBookings(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $date_from = sanitize_text_field($request->get_param('date_from')) ?: date('Y-m-d');
        $date_to = sanitize_text_field($request->get_param('date_to')) ?: date('Y-m-d', strtotime('+7 days'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            return new WP_Error('invalid_date', __('Invalid date format. Expected YYYY-MM-DD', 'fp-esperienze'), ['status' => 400]);
        }

        global $wpdb;
        
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT b.*, u.display_name as customer_name, u.user_email as customer_email,
                   p.post_title as experience_name
            FROM {$wpdb->prefix}fp_bookings b
            LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
            LEFT JOIN {$wpdb->posts} p ON b.product_id = p.ID
            WHERE b.booking_date BETWEEN %s AND %s
            ORDER BY b.booking_date, b.booking_time
        ", $date_from, $date_to));

        $sync_data = [];

        foreach ($bookings as $booking) {
            $sync_data[] = [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'experience_name' => $booking->experience_name,
                'booking_date' => $booking->booking_date,
                'booking_time' => $booking->booking_time,
                'participants' => intval($booking->participants),
                'status' => $booking->status,
                'checked_in' => $booking->checked_in_at ? true : false,
                'checked_in_at' => $booking->checked_in_at,
                'qr_data' => $this->generateBookingQRData($booking->id),
                'last_modified' => $booking->updated_at
            ];
        }

        return new WP_REST_Response([
            'bookings' => $sync_data,
            'sync_timestamp' => current_time('mysql'),
            'total' => count($sync_data)
        ]);
    }

    /**
     * Process offline actions
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function processOfflineActions(WP_REST_Request $request): WP_REST_Response {
        $actions_param = wp_unslash($request->get_param('actions'));
        $actions = is_array($actions_param) ? $actions_param : [];
        $staff_user_id = $this->getCurrentMobileUserId($request);

        $results = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $sanitized_action = [];
            foreach ($action as $key => $value) {
                switch ($key) {
                    case 'type':
                    case 'status':
                        $sanitized_action[$key] = sanitize_text_field($value);
                        break;
                    case 'booking_id':
                        $sanitized_action[$key] = absint($value);
                        break;
                    default:
                        $sanitized_action[$key] = sanitize_text_field($value);
                        break;
                }
            }

            $result = $this->processOfflineAction($sanitized_action, $staff_user_id);
            $results[] = $result;
        }

        return new WP_REST_Response([
            'results' => $results,
            'processed' => count($results),
            'success_count' => count(array_filter($results, function($r) { return $r['success']; }))
        ]);
    }

    /**
     * Get staff schedule
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function getStaffSchedule(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $staff_user_id = $this->getCurrentMobileUserId($request);
        $date_from = sanitize_text_field($request->get_param('date_from')) ?: date('Y-m-d');
        $date_to = sanitize_text_field($request->get_param('date_to')) ?: date('Y-m-d', strtotime('+7 days'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            return new WP_Error('invalid_date', __('Invalid date format. Expected YYYY-MM-DD', 'fp-esperienze'), ['status' => 400]);
        }

        // Get staff schedule from custom table or meta
        $schedule = $this->getStaffScheduleData($staff_user_id, $date_from, $date_to);

        return new WP_REST_Response([
            'schedule' => $schedule,
            'staff_id' => $staff_user_id,
            'date_range' => [$date_from, $date_to]
        ]);
    }

    /**
     * Record staff attendance
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function recordStaffAttendance(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $staff_user_id = $this->getCurrentMobileUserId($request);
        $action_type   = sanitize_text_field($request->get_param('action')); // 'clock_in', 'clock_out'
        $location      = $request->get_param('location'); // GPS coordinates

        if (!in_array($action_type, ['clock_in', 'clock_out'], true)) {
            return new WP_Error('invalid_action', __('Invalid action type', 'fp-esperienze'), ['status' => 400]);
        }

        $location_data = null;

        if (null !== $location) {
            if (!is_array($location) || !isset($location['lat'], $location['lng']) || !is_numeric($location['lat']) || !is_numeric($location['lng'])) {
                return new WP_Error('invalid_location', __('Invalid location data', 'fp-esperienze'), ['status' => 400]);
            }

            $lat = (float) $location['lat'];
            $lng = (float) $location['lng'];

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return new WP_Error('invalid_location', __('Invalid location data', 'fp-esperienze'), ['status' => 400]);
            }

            $location_data = wp_json_encode([
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }

        global $wpdb;

        $attendance_data = [
            'staff_id'      => $staff_user_id,
            'action_type'   => $action_type,
            'timestamp'     => current_time('mysql'),
            'location_data' => $location_data,
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'fp_staff_attendance',
            $attendance_data,
            ['%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new WP_Error('attendance_failed', __('Failed to record attendance', 'fp-esperienze'), ['status' => 500]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('%s recorded successfully', 'fp-esperienze'), ucwords(str_replace('_', ' ', $action_type))),
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Permission callbacks
     */

    public function checkMobileAuth(WP_REST_Request $request): bool|WP_Error {
        $user_id = $this->getCurrentMobileUserId($request);
        if (!$user_id) {
            return new WP_Error('invalid_token', __('Unauthorized', 'fp-esperienze'), ['status' => 401]);
        }

        if (!get_user_meta($user_id, '_mobile_email_verified', true)) {
            return new WP_Error('email_not_verified', __('Email not verified', 'fp-esperienze'), ['status' => 403]);
        }

        return true;
    }

    public function checkStaffAuth(WP_REST_Request $request): bool|WP_Error {
        $auth = $this->checkMobileAuth($request);
        if ($auth !== true) {
            return $auth;
        }

        $user_id = $this->getCurrentMobileUserId($request);
        return CapabilityManager::userCan($user_id, 'manage_bookings');
    }

    /**
     * Helper methods
     */

    private function getCurrentMobileUserId(WP_REST_Request $request): ?int {
        $token = $request->get_header('Authorization');
        
        if (!$token) {
            return null;
        }

        // Remove "Bearer " prefix
        $token = str_replace('Bearer ', '', $token);
        
        return $this->validateMobileToken($token);
    }

    private function generateMobileToken(int $user_id): string {
        $payload = [
            'user_id' => $user_id,
            'exp' => time() + (30 * DAY_IN_SECONDS), // 30 days
            'iat' => time()
        ];

        $payload_json = wp_json_encode($payload);
        $signature    = hash_hmac('sha256', $payload_json, wp_salt('auth'));
        return rtrim(strtr(base64_encode($payload_json . '|' . $signature), '+/', '-_'), '=');
    }

    private function validateMobileToken(string $token): ?int {
        $token   = strtr($token, '-_', '+/');
        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);
        
        if (count($parts) !== 2) {
            return null;
        }

        $payload   = $parts[0];
        $signature = $parts[1];

        $expected_signature = hash_hmac('sha256', $payload, wp_salt('auth'));

        if (!hash_equals($expected_signature, $signature)) {
            // Fallback for legacy tokens signed with wp_hash during migration
            if (wp_hash($payload) !== $signature) {
                return null;
            }
        }

        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Failed to decode mobile token payload: ' . json_last_error_msg());
            return null;
        }

        if (!$data || $data['exp'] < time()) {
            return null;
        }

        $user_id = intval($data['user_id']);
        $revoked = (int) get_user_meta($user_id, '_mobile_token_revoked', true);

        if ($revoked && (!isset($data['iat']) || (int) $data['iat'] <= $revoked)) {
            return null;
        }

        return $user_id;
    }

    private function getMobileProductImages(\WC_Product $product): array {
        $images = [];
        
        if ($product->get_image_id()) {
            $images[] = [
                'id' => $product->get_image_id(),
                'url' => wp_get_attachment_image_url($product->get_image_id(), 'large'),
                'thumb' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                'alt' => get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true)
            ];
        }

        return $images;
    }

    private function getMobileProductGallery(\WC_Product $product): array {
        $gallery = [];
        $gallery_ids = $product->get_gallery_image_ids();

        foreach ($gallery_ids as $id) {
            $gallery[] = [
                'id' => $id,
                'url' => wp_get_attachment_image_url($id, 'large'),
                'thumb' => wp_get_attachment_image_url($id, 'medium'),
                'alt' => get_post_meta($id, '_wp_attachment_image_alt', true)
            ];
        }

        return $gallery;
    }

    private function getMobileReviews(int $product_id, int $limit = 5): array {
        $reviews = get_comments([
            'post_id' => $product_id,
            'status' => 'approve',
            'type' => 'review',
            'number' => $limit
        ]);

        $mobile_reviews = [];

        foreach ($reviews as $review) {
            $mobile_reviews[] = [
                'id' => $review->comment_ID,
                'author' => $review->comment_author,
                'rating' => intval(get_comment_meta($review->comment_ID, 'rating', true)),
                'content' => $review->comment_content,
                'date' => $review->comment_date
            ];
        }

        return $mobile_reviews;
    }

    private function getAvailableDates(int $product_id, ?string $from_date = null): array {
        // This would integrate with the schedule system
        // For now, return sample dates
        $dates = [];
        $start_date = $from_date ?: date('Y-m-d');
        
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime($start_date . " +{$i} days"));
            $dates[] = [
                'date' => $date,
                'available_slots' => rand(0, 5),
                'price' => rand(50, 150)
            ];
        }

        return $dates;
    }

    private function getMeetingPoints(int $product_id): array {
        global $wpdb;
        
        $meeting_points = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}fp_meeting_points
            WHERE product_id = %d OR product_id = 0
            ORDER BY is_default DESC, name ASC
        ", $product_id));

        $mobile_points = [];

        foreach ($meeting_points as $point) {
            $mobile_points[] = [
                'id' => $point->id,
                'name' => $point->name,
                'address' => $point->address,
                'latitude' => floatval($point->latitude),
                'longitude' => floatval($point->longitude),
                'description' => $point->description,
                'is_default' => (bool)$point->is_default
            ];
        }

        return $mobile_points;
    }

    private function getExperienceExtras(int $product_id): array {
        global $wpdb;
        
        $extras = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}fp_extras
            WHERE product_id = %d OR product_id = 0
            ORDER BY name ASC
        ", $product_id));

        $mobile_extras = [];

        foreach ($extras as $extra) {
            $mobile_extras[] = [
                'id' => $extra->id,
                'name' => $extra->name,
                'description' => $extra->description,
                'price' => floatval($extra->price),
                'type' => $extra->type,
                'is_required' => (bool)$extra->is_required
            ];
        }

        return $mobile_extras;
    }

    private function getSimilarExperiences(int $product_id, int $limit = 3): array {
        // Simple implementation - get products from same category
        $product = wc_get_product($product_id);
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

        if (empty($categories)) {
            return [];
        }

        $similar = get_posts([
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post__not_in' => [$product_id],
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'experience'
                ]
            ]
        ]);

        $mobile_similar = [];

        foreach ($similar as $similar_product) {
            $product_obj = wc_get_product($similar_product->ID);
            $mobile_similar[] = [
                'id' => $similar_product->ID,
                'name' => $similar_product->post_title,
                'price' => floatval($product_obj->get_price()),
                'image' => wp_get_attachment_image_url($product_obj->get_image_id(), 'medium'),
                'rating' => floatval($product_obj->get_average_rating())
            ];
        }

        return $mobile_similar;
    }

    private function validateBookingData(array $data): bool|\WP_Error {
        if (empty($data['product_id']) || !wc_get_product($data['product_id'])) {
            return new WP_Error('invalid_product', __('Invalid product', 'fp-esperienze'), ['status' => 400]);
        }

        if (empty($data['booking_date']) || !strtotime($data['booking_date'])) {
            return new WP_Error('invalid_date', __('Invalid booking date', 'fp-esperienze'), ['status' => 400]);
        }

        if ($data['participants'] < 1) {
            return new WP_Error('invalid_participants', __('At least one participant required', 'fp-esperienze'), ['status' => 400]);
        }

        return true;
    }

    private function generateBookingQRData(int $booking_id): string {
        $ts   = time();
        $hash = hash_hmac('sha256', 'booking_' . $booking_id . '_' . $ts, wp_salt('auth'));

        $data = [
            'booking_id' => $booking_id,
            'timestamp'  => $ts,
            'hash'       => $hash,
        ];

        return base64_encode(wp_json_encode($data));
    }

    private function generateQRCodeImage(string $data): string {
        // Placeholder - would use a QR code library like chillerlan/php-qr-code
        // For now, return a data URL placeholder
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="white"/><text x="100" y="100" text-anchor="middle" fill="black">QR CODE</text></svg>'
        );
    }

    private function decodeQRData(string $qr_data): ?array {
        $decoded = base64_decode($qr_data);
        $data    = json_decode($decoded, true);

        if (!$data || !isset($data['booking_id'], $data['timestamp'], $data['hash'])) {
            return null;
        }

        // Verify hash
        $expected_hash = hash_hmac('sha256', 'booking_' . $data['booking_id'] . '_' . $data['timestamp'], wp_salt('auth'));
        if (!hash_equals($expected_hash, $data['hash'])) {
            return null;
        }

        return $data;
    }

    private function getMeetingPointById(int $meeting_point_id): ?array {
        global $wpdb;
        
        $point = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}fp_meeting_points WHERE id = %d
        ", $meeting_point_id));

        if (!$point) {
            return null;
        }

        return [
            'id' => $point->id,
            'name' => $point->name,
            'address' => $point->address,
            'latitude' => floatval($point->latitude),
            'longitude' => floatval($point->longitude),
            'description' => $point->description
        ];
    }

    private function canCancelBooking(object $booking): bool {
        // Simple logic - can cancel if booking is confirmed and date is more than 24 hours away
        return $booking->status === 'confirmed' && 
               strtotime($booking->booking_date) > (time() + DAY_IN_SECONDS);
    }

    private function canRescheduleBooking(object $booking): bool {
        return $this->canCancelBooking($booking);
    }

    private function getBookingExtras(int $booking_id): array {
        global $wpdb;
        
        $extras = $wpdb->get_results($wpdb->prepare("
            SELECT e.*, be.quantity
            FROM {$wpdb->prefix}fp_booking_extras be
            INNER JOIN {$wpdb->prefix}fp_extras e ON be.extra_id = e.id
            WHERE be.booking_id = %d
        ", $booking_id));

        $booking_extras = [];

        foreach ($extras as $extra) {
            $booking_extras[] = [
                'id' => $extra->id,
                'name' => $extra->name,
                'price' => floatval($extra->price),
                'quantity' => intval($extra->quantity),
                'total' => floatval($extra->price) * intval($extra->quantity)
            ];
        }

        return $booking_extras;
    }

    private function getBookingVoucherUrl(int $booking_id): string {
        return home_url("/voucher/{$booking_id}");
    }

    private function sendCheckinNotification(int $booking_id, int $staff_user_id): void {
        // Send push notification to customer about check-in
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT customer_id FROM {$wpdb->prefix}fp_bookings WHERE id = %d
        ", $booking_id));

        if ($booking) {
            $this->sendPushToUser(
                $booking->customer_id,
                __('Check-in Confirmed', 'fp-esperienze'),
                __('You have been successfully checked in for your experience', 'fp-esperienze'),
                ['booking_id' => $booking_id]
            );
        }
    }

    /**
     * Send a push notification to a user.
     *
     * @param int    $user_id User ID.
     * @param string $title   Notification title.
     * @param string $message Notification message.
     * @param array  $data    Optional additional data.
     *
     * @return bool Whether the push notification was sent.
     */
    private function sendPushToUser(int $user_id, string $title, string $message, array $data = []): bool {
        $tokens   = get_user_meta( $user_id, '_push_notification_tokens', true );
        $expiries = get_user_meta( $user_id, '_push_token_expires_at', true );

        if ( ! is_array( $tokens ) || empty( $tokens ) ) {
            return false;
        }

        if ( ! is_array( $expiries ) ) {
            $expiries = [];
        }

        $payload = [
            'title'   => $title,
            'message' => $message,
            'data'    => $data,
        ];

        $sent             = false;
        $valid_tokens     = [];
        $valid_expiries   = [];
        $now              = time();

        foreach ( $tokens as $token ) {
            $expiry = isset( $expiries[ $token ] ) ? (int) $expiries[ $token ] : 0;

            if ( $expiry <= $now ) {
                continue;
            }

            if ( $this->sendPushPayload( $token, $payload ) ) {
                $sent               = true;
                $valid_tokens[]     = $token;
                $valid_expiries[ $token ] = $expiry;
            }
        }

        if ( $valid_tokens !== $tokens ) {
            if ( ! empty( $valid_tokens ) ) {
                update_user_meta( $user_id, '_push_notification_tokens', $valid_tokens );
                update_user_meta( $user_id, '_push_token_expires_at', $valid_expiries );
            } else {
                delete_user_meta( $user_id, '_push_notification_tokens' );
                delete_user_meta( $user_id, '_push_token_expires_at' );
            }
        }

        return $sent;
    }

    /**
     * Placeholder for actual push notification service integration.
     *
     * @param string $token   Device token.
     * @param array  $payload Notification payload.
     *
     * @return bool Whether the notification was sent successfully.
     */
    private function sendPushPayload( string $token, array $payload ): bool {
        // Would integrate with Firebase Cloud Messaging, Apple Push Notification Service, etc.
        // $token and $payload would be used here.

        return true;
    }

    private function processOfflineAction(array $action, int $staff_user_id): array {
        $action_type = $action['type'] ?? '';
        $booking_id = $action['booking_id'] ?? 0;

        $allowed_keys = ['type', 'booking_id'];
        if ('status_update' === $action_type) {
            $allowed_keys[] = 'status';
        }

        $unknown_keys = array_diff(array_keys($action), $allowed_keys);
        if (!empty($unknown_keys)) {
            return ['success' => false, 'error' => 'Unknown action fields: ' . implode(', ', $unknown_keys)];
        }

        switch ($action_type) {
            case 'check_in':
                return $this->processOfflineCheckin($booking_id, $staff_user_id);
            case 'status_update':
                $status = $action['status'] ?? '';
                return $this->processOfflineStatusUpdate($booking_id, $status, $staff_user_id);
            default:
                return ['success' => false, 'error' => 'Unknown action type'];
        }
    }

    private function processOfflineCheckin(int $booking_id, int $staff_user_id): array {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'fp_bookings',
            [
                'checked_in_at' => current_time('mysql'),
                'checked_in_by' => $staff_user_id
            ],
            [
                'id' => $booking_id,
                'checked_in_at' => null // Only update if not already checked in
            ],
            ['%s', '%d'],
            ['%d', '%s']
        );

        return [
            'success' => $result !== false,
            'booking_id' => $booking_id,
            'action' => 'check_in'
        ];
    }

    private function processOfflineStatusUpdate(int $booking_id, string $status, int $staff_user_id): array {
        global $wpdb;

        $valid_statuses = ['confirmed', 'completed', 'cancelled', 'no_show'];
        
        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'fp_bookings',
            ['status' => $status],
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );

        return [
            'success' => $result !== false,
            'booking_id' => $booking_id,
            'action' => 'status_update',
            'status' => $status
        ];
    }

    private function getStaffScheduleData(int $staff_user_id, string $date_from, string $date_to): array {
        // Placeholder - would integrate with staff scheduling system
        $schedule = [];
        
        $current_date = $date_from;
        while ($current_date <= $date_to) {
            $schedule[] = [
                'date' => $current_date,
                'shift_start' => '09:00',
                'shift_end' => '17:00',
                'assigned_experiences' => [
                    ['id' => 1, 'name' => 'City Tour', 'time' => '10:00'],
                    ['id' => 2, 'name' => 'Wine Tasting', 'time' => '14:00']
                ]
            ];
            
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }

        return $schedule;
    }

    /**
     * Get client IP address.
     *
     * Uses RateLimiter helper to respect trusted proxy whitelist.
     *
     * @return string Client IP address
     */
    private function getClientIP(): string {
        return RateLimiter::getClientIP();
    }
}
