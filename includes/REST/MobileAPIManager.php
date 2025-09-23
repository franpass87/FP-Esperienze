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

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\Installer;
use FP\Esperienze\Core\RateLimiter;
use FP\Esperienze\Data\Availability;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Data\StaffScheduleManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Throwable;

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
     * Tracks whether the mobile endpoints have already been registered.
     *
     * @var bool
     */
    private static $endpointsRegistered = false;

    /**
     * Lifetime for generated QR codes in seconds (24 hours).
     */
    private const QR_CODE_TTL = 86400;

    /**
     * Tracks the availability of the QR code library.
     */
    private bool $qrCodeLibraryAvailable = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->qrCodeLibraryAvailable = class_exists(QRCode::class) && class_exists(QROptions::class);

        if (!$this->qrCodeLibraryAvailable) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('FP Esperienze: QR code library chillerlan/php-qrcode is not available. QR code generation will be disabled.');
            }
        }

        if (did_action('rest_api_init')) {
            $this->registerMobileEndpoints();

            return;
        }

        add_action('rest_api_init', [$this, 'registerMobileEndpoints']);
    }

    /**
     * Register mobile-specific REST endpoints
     */
    public function registerMobileEndpoints(): void {
        if (self::$endpointsRegistered) {
            return;
        }

        self::$endpointsRegistered = true;

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

        if (!$this->isMobileEmailVerified($user->ID, true)) {
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
            'no_found_rows' => false,
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

        $query = new \WP_Query($args);
        $experiences = $query->posts;
        $mobile_experiences = [];

        foreach ($experiences as $experience) {
            $product = wc_get_product($experience->ID);
            $reviews_enabled = $this->isReviewsEnabledForProduct($experience->ID, $product);

            $mobile_experiences[] = [
                'id' => $experience->ID,
                'name' => $experience->post_title,
                'description' => wp_trim_words($experience->post_content, 20),
                'short_description' => $product->get_short_description(),
                'price' => floatval($product->get_price()),
                'currency' => get_woocommerce_currency(),
                'images' => $this->getMobileProductImages($product),
                'rating' => $reviews_enabled ? floatval($product->get_average_rating()) : 0.0,
                'review_count' => $reviews_enabled ? intval($product->get_review_count()) : 0,
                'duration' => get_post_meta($experience->ID, '_experience_duration', true),
                'location' => get_post_meta($experience->ID, '_experience_location', true),
                'categories' => wp_get_post_terms($experience->ID, 'product_cat', ['fields' => 'names']),
                'available_dates' => $this->getAvailableDates($experience->ID, $date_from),
                'features' => get_post_meta($experience->ID, '_experience_features', true),
                'reviews_enabled' => $reviews_enabled
            ];
        }

        return new WP_REST_Response([
            'experiences' => $mobile_experiences,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $query->found_posts,
                'has_more' => $page < $query->max_num_pages
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

        $reviews_enabled = $this->isReviewsEnabledForProduct($id, $product);

        $experience_data = [
            'id' => $id,
            'name' => $product->get_name(),
            'description' => wp_kses_post($product->get_description()),
            'short_description' => wp_kses_post($product->get_short_description()),
            'price' => floatval($product->get_price()),
            'currency' => get_woocommerce_currency(),
            'images' => $this->getMobileProductImages($product),
            'gallery' => $this->getMobileProductGallery($product),
            'rating' => $reviews_enabled ? floatval($product->get_average_rating()) : 0.0,
            'review_count' => $reviews_enabled ? intval($product->get_review_count()) : 0,
            'reviews' => $reviews_enabled ? $this->getMobileReviews($id) : [],
            'duration' => get_post_meta($id, '_experience_duration', true),
            'location' => get_post_meta($id, '_experience_location', true),
            'meeting_points' => $this->getMeetingPoints($id),
            'included' => wp_strip_all_tags((string) get_post_meta($id, '_experience_included', true)),
            'excluded' => wp_strip_all_tags((string) get_post_meta($id, '_experience_excluded', true)),
            'requirements' => wp_strip_all_tags((string) get_post_meta($id, '_experience_requirements', true)),
            'cancellation_policy' => wp_strip_all_tags((string) get_post_meta($id, '_cancellation_policy', true)),
            'available_dates' => $this->getAvailableDates($id),
            'extras' => $this->getExperienceExtras($id),
            'similar_experiences' => $this->getSimilarExperiences($id),
            'reviews_enabled' => $reviews_enabled
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
            ORDER BY booking_date DESC, booking_time DESC
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
                'currency' => !empty($booking->currency) ? (string) $booking->currency : get_woocommerce_currency(),
                'meeting_point' => !empty($booking->meeting_point_id)
                    ? $this->getMeetingPointById((int) $booking->meeting_point_id)
                    : null,
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
        if (!$user_id) {
            return new WP_Error('invalid_token', __('Unauthorized', 'fp-esperienze'), ['status' => 401]);
        }

        $participants_param = $request->get_param('participants');
        if (is_array($participants_param)) {
            $participants = [
                'adults' => isset($participants_param['adults']) ? (int) $participants_param['adults'] : 0,
                'children' => isset($participants_param['children']) ? (int) $participants_param['children'] : 0,
            ];
        } else {
            $participants = (int) $participants_param;
        }

        $meeting_point_param = $request->get_param('meeting_point_id');
        $meeting_point_id = null;
        if ($meeting_point_param !== null && $meeting_point_param !== '') {
            $meeting_point_id = (int) $meeting_point_param;
            if ($meeting_point_id <= 0) {
                $meeting_point_id = null;
            }
        }

        $extras_param = $request->get_param('extras');
        $extras = is_array($extras_param) ? $extras_param : [];

        $booking_data = [
            'product_id' => (int) $request->get_param('product_id'),
            'booking_date' => sanitize_text_field((string) $request->get_param('booking_date')),
            'booking_time' => sanitize_text_field((string) $request->get_param('booking_time')),
            'participants' => $participants,
            'meeting_point_id' => $meeting_point_id,
            'extras' => $extras,
            'customer_notes' => sanitize_textarea_field((string) $request->get_param('notes')),
        ];

        $validation = $this->validateBookingData($booking_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $booking_manager = BookingManager::getInstance();
        $booking_id = $booking_manager->createCustomerBooking($user_id, $booking_data);

        if (is_wp_error($booking_id)) {
            return $booking_id;
        }

        $request->set_param('id', $booking_id);

        return $this->getMobileBooking($request);
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
        $meeting_point = null;
        if (!empty($booking->meeting_point_id)) {
            $meeting_point = $this->getMeetingPointById((int) $booking->meeting_point_id);
        }

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
            'currency' => !empty($booking->currency) ? (string) $booking->currency : get_woocommerce_currency(),
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

        if (is_wp_error($qr_image)) {
            return $qr_image;
        }

        return new WP_REST_Response([
            'qr_data' => $qr_data,
            'qr_image' => $qr_image,
            'booking_id' => $booking_id,
            'expires_at' => wp_date('Y-m-d H:i:s', current_time('timestamp') + self::QR_CODE_TTL),
            'expires_in' => self::QR_CODE_TTL,
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

        if ($booking_info instanceof WP_Error) {
            return $booking_info;
        }

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

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_push_tokens';
        $now_local  = current_time('mysql');
        $now_gmt    = current_time('timestamp', true);
        $expires_at = gmdate('Y-m-d H:i:s', $now_gmt + (90 * DAY_IN_SECONDS));

        $platform_value = $platform !== '' ? $platform : null;

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT token FROM {$table_name} WHERE token = %s",
                $token
            )
        );

        if ($existing) {
            $updated = $wpdb->update(
                $table_name,
                [
                    'user_id' => $user_id,
                    'platform' => $platform_value,
                    'expires_at' => $expires_at,
                    'last_seen' => $now_local,
                ],
                [ 'token' => $token ],
                null,
                ['%s']
            );

            if ($updated === false) {
                return new WP_Error(
                    'push_token_update_failed',
                    __( 'Failed to update push token', 'fp-esperienze' ),
                    [
                        'status' => 500,
                        'token' => $token,
                    ]
                );
            }
        } else {
            $inserted = $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'token' => $token,
                    'platform' => $platform_value,
                    'expires_at' => $expires_at,
                    'last_seen' => $now_local,
                    'created_at' => $now_local,
                ]
            );

            if ($inserted === false) {
                return new WP_Error(
                    'push_token_save_failed',
                    __( 'Failed to store push token', 'fp-esperienze' ),
                    [
                        'status' => 500,
                        'token' => $token,
                    ]
                );
            }
        }

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

        $priority = $request->get_param( 'priority' );

        if ( is_string( $priority ) ) {
            $priority = strtolower( sanitize_text_field( $priority ) );
        } else {
            $priority = 'high';
        }

        if ( ! in_array( $priority, [ 'high', 'normal' ], true ) ) {
            $priority = 'high';
        }

        if (empty($recipient_id) || empty($title) || empty($message)) {
            return new WP_Error('missing_params', __('Recipient, title and message are required', 'fp-esperienze'), ['status' => 400]);
        }

        $result = $this->sendPushToUser($recipient_id, $title, $message, $data, $priority);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Notification sent successfully', 'fp-esperienze')
        ]);
    }

    /**
     * Get sync bookings for offline mode (staff only)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public function getSyncBookings(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $timezone = wp_timezone();
        $now      = new DateTimeImmutable('now', $timezone);

        $date_from = $this->normalizeSyncDate($request->get_param('date_from'), $timezone, $now);
        if ($date_from instanceof WP_Error) {
            return $date_from;
        }

        $date_to = $this->normalizeSyncDate($request->get_param('date_to'), $timezone, $now->add(new DateInterval('P7D')));
        if ($date_to instanceof WP_Error) {
            return $date_to;
        }

        global $wpdb;

        $bookings = $wpdb->get_results($wpdb->prepare(
            "            SELECT b.*, u.display_name as customer_name, u.user_email as customer_email,
                   p.post_title as experience_name
            FROM {$wpdb->prefix}fp_bookings b
            LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
            LEFT JOIN {$wpdb->posts} p ON b.product_id = p.ID
            WHERE b.booking_date BETWEEN %s AND %s
            ORDER BY b.booking_date, b.booking_time
        ", $date_from->format('Y-m-d'), $date_to->format('Y-m-d')));

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
     * Normalize sync date parameters using the site timezone.
     *
     * @param mixed             $value    Raw request value.
     * @param \DateTimeZone     $timezone Site timezone.
     * @param DateTimeImmutable $fallback Default date when no value is provided.
     *
     * @return DateTimeImmutable|WP_Error Normalized date object or error on invalid input.
     */
    private function normalizeSyncDate($value, \DateTimeZone $timezone, DateTimeImmutable $fallback): DateTimeImmutable|WP_Error {
        if (is_string($value)) {
            $sanitized = sanitize_text_field($value);

            if ('' !== $sanitized) {
                $date   = DateTimeImmutable::createFromFormat('Y-m-d', $sanitized, $timezone);
                $errors = DateTimeImmutable::getLastErrors();

                if (!$date || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
                    return new WP_Error('invalid_date', __('Invalid date format. Expected YYYY-MM-DD', 'fp-esperienze'), ['status' => 400]);
                }

                return $date;
            }
        }

        return $fallback;
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

        $table_name = $wpdb->prefix . 'fp_staff_attendance';
        $formats = ['%d', '%s', '%s', '%s'];
        $attendance_data = [
            'staff_id'      => $staff_user_id,
            'action_type'   => $action_type,
            'timestamp'     => current_time('mysql'),
            'location_data' => $location_data,
        ];

        $result = $wpdb->insert(
            $table_name,
            $attendance_data,
            $formats
        );

        if ($result === false) {
            $last_error = $wpdb->last_error;
            $table_missing = false;

            if ($last_error) {
                $table_missing = str_contains($last_error, $table_name) && (
                    str_contains($last_error, "doesn't exist") ||
                    str_contains($last_error, 'does not exist') ||
                    str_contains($last_error, 'no such table')
                );
            }

            if ($table_missing) {
                error_log('FP Esperienze: Staff attendance table missing. Attempting to recreate. Error: ' . $last_error);

                if (!class_exists(Installer::class)) {
                    error_log('FP Esperienze: Installer class missing while recreating staff attendance table.');
                    return new WP_Error(
                        'attendance_table_missing',
                        __('Attendance tracking is temporarily unavailable. Please contact the site administrator.', 'fp-esperienze'),
                        ['status' => 500]
                    );
                }

                $upgrade_result = Installer::maybeCreateStaffAttendanceTable();
                if (is_wp_error($upgrade_result)) {
                    error_log('FP Esperienze: Unable to recreate staff attendance table: ' . $upgrade_result->get_error_message());
                    return new WP_Error(
                        'attendance_table_missing',
                        __('Attendance tracking is temporarily unavailable. Please contact the site administrator.', 'fp-esperienze'),
                        ['status' => 500]
                    );
                }

                $attendance_data['timestamp'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $attendance_data, $formats);

                if ($result === false) {
                    error_log('FP Esperienze: Failed to record attendance after recreating table: ' . $wpdb->last_error);
                    return new WP_Error(
                        'attendance_table_missing',
                        __('Attendance tracking is temporarily unavailable. Please try again in a moment.', 'fp-esperienze'),
                        ['status' => 500]
                    );
                }
            } else {
                if ($last_error) {
                    error_log('FP Esperienze: Failed to record attendance: ' . $last_error);
                }

                return new WP_Error('attendance_failed', __('Failed to record attendance', 'fp-esperienze'), ['status' => 500]);
            }
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

        if (!$this->isMobileEmailVerified($user_id)) {
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

    private function isMobileEmailVerified(int $user_id, bool $backfill_if_missing = false): bool {
        $meta_exists = metadata_exists('user', $user_id, '_mobile_email_verified');

        if (!$meta_exists) {
            if ($backfill_if_missing) {
                update_user_meta($user_id, '_mobile_email_verified', 1);
            }

            return true;
        }

        $meta_value = get_user_meta($user_id, '_mobile_email_verified', true);

        return wp_validate_boolean($meta_value);
    }

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

        return $this->encodeMobileTokenPayload($payload_json . '|' . $signature);
    }

    private function validateMobileToken(string $token): ?int {
        $decoded = $this->decodeMobileTokenPayload($token);

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

    private function encodeMobileTokenPayload(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function decodeMobileTokenPayload(string $token): string|false {
        $normalized_token = $this->normalizeMobileToken($token);

        return base64_decode($normalized_token, true);
    }

    private function normalizeMobileToken(string $token): string {
        $token = strtr($token, '-_', '+/');
        $padding = strlen($token) % 4;

        if ($padding !== 0) {
            $token .= str_repeat('=', 4 - $padding);
        }

        return $token;
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

    private function isReviewsEnabledForProduct(int $product_id, ?\WC_Product $product = null): bool {
        $flag = get_post_meta($product_id, '_fp_exp_enable_reviews', true);
        $enabled = $flag !== 'no';

        if ($product === null) {
            $product = wc_get_product($product_id);
        }

        return (bool) apply_filters('fp_experience_reviews_enabled', $enabled, $product);
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
        if (!$this->isReviewsEnabledForProduct($product_id)) {
            return [];
        }

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
        $max_results = 30;
        if (function_exists('apply_filters')) {
            $max_results = (int) apply_filters('fp_esperienze_mobile_available_dates_window', $max_results, $product_id, $from_date);
        }

        if ($max_results <= 0) {
            return [];
        }

        $lookahead_days = $max_results;
        if (function_exists('apply_filters')) {
            $lookahead_days = (int) apply_filters('fp_esperienze_mobile_available_dates_lookahead', $lookahead_days, $product_id, $from_date);
        }

        if ($lookahead_days < $max_results) {
            $lookahead_days = $max_results;
        }

        $timezone = null;
        if (function_exists('wp_timezone')) {
            $timezone = wp_timezone();
        }

        if (!$timezone instanceof \DateTimeZone) {
            $timezone_string = '';
            if (function_exists('wp_timezone_string')) {
                $timezone_string = (string) wp_timezone_string();
            }

            if ($timezone_string === '') {
                $timezone_string = (string) date_default_timezone_get();
            }

            try {
                $timezone = new \DateTimeZone($timezone_string !== '' ? $timezone_string : 'UTC');
            } catch (Throwable $exception) {
                $timezone = new \DateTimeZone('UTC');
            }
        }

        $start_date = null;
        if ($from_date) {
            $start_date = DateTime::createFromFormat('Y-m-d', $from_date, $timezone);
        }

        if (!$start_date) {
            $start_date = new DateTime('now', $timezone);
        }

        $start_date->setTime(0, 0, 0);

        $today = new DateTime('now', $timezone);
        $today->setTime(0, 0, 0);

        if ($start_date < $today) {
            $start_date = $today;
        }

        $dates = [];
        $checked_days = 0;

        while ($checked_days < $lookahead_days && count($dates) < $max_results) {
            $date_string = $start_date->format('Y-m-d');

            try {
                $slots = Availability::forDay($product_id, $date_string);
            } catch (Throwable $exception) {
                $slots = [];
            }

            $normalized_slots = [];
            $remaining_capacity = 0;
            $min_adult_price = null;
            $min_child_price = null;

            foreach ($slots as $slot) {
                if (!is_array($slot)) {
                    continue;
                }

                $start_time = isset($slot['start_time']) ? (string) $slot['start_time'] : '';

                if ($start_time === '') {
                    continue;
                }

                $end_time = isset($slot['end_time']) ? (string) $slot['end_time'] : '';
                $capacity = isset($slot['capacity']) ? (int) $slot['capacity'] : 0;
                $booked = isset($slot['booked']) ? (int) $slot['booked'] : 0;
                $available = isset($slot['available']) ? (int) $slot['available'] : 0;

                if ($available < 0) {
                    $available = 0;
                }

                $held = isset($slot['held_count']) ? (int) $slot['held_count'] : 0;
                $is_available = $available > 0 ? true : (bool) ($slot['is_available'] ?? false);
                $schedule_id = isset($slot['schedule_id']) ? (int) $slot['schedule_id'] : 0;

                $adult_price = array_key_exists('adult_price', $slot) ? (float) $slot['adult_price'] : null;
                $child_price = array_key_exists('child_price', $slot) ? (float) $slot['child_price'] : null;

                if ($adult_price !== null) {
                    $min_adult_price = $min_adult_price === null ? $adult_price : min($min_adult_price, $adult_price);
                }

                if ($child_price !== null) {
                    $min_child_price = $min_child_price === null ? $child_price : min($min_child_price, $child_price);
                }

                $remaining_capacity += $available;

                $normalized_slot = [
                    'schedule_id' => $schedule_id,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'capacity' => $capacity,
                    'booked' => $booked,
                    'available' => $available,
                    'held_count' => $held,
                    'is_available' => $is_available,
                    'adult_price' => $adult_price,
                    'child_price' => $child_price,
                ];

                if (array_key_exists('languages', $slot)) {
                    $normalized_slot['languages'] = $slot['languages'];
                }

                if (array_key_exists('meeting_point_id', $slot)) {
                    $normalized_slot['meeting_point_id'] = $slot['meeting_point_id'] !== null
                        ? (int) $slot['meeting_point_id']
                        : null;
                }

                $normalized_slots[] = $normalized_slot;
            }

            if (!empty($normalized_slots)) {
                $day_data = [
                    'date' => $date_string,
                    'remaining_capacity' => $remaining_capacity,
                    'slots' => array_values($normalized_slots),
                    'prices' => [
                        'adult_from' => $min_adult_price,
                        'child_from' => $min_child_price,
                    ],
                ];

                if (function_exists('apply_filters')) {
                    $day_data = apply_filters('fp_esperienze_mobile_available_date', $day_data, $product_id, $date_string);
                }

                $dates[] = $day_data;
            }

            $start_date->modify('+1 day');
            $checked_days++;
        }

        return $dates;
    }

    private function getMeetingPoints(int $product_id): array {
        if ($product_id > 0) {
            $meeting_points = MeetingPointManager::getMeetingPointsForProduct($product_id, false);

            if (empty($meeting_points)) {
                $meeting_points = MeetingPointManager::getAllMeetingPoints(false);
            }
        } else {
            $meeting_points = MeetingPointManager::getAllMeetingPoints(false);
        }

        if (empty($meeting_points)) {
            return [];
        }

        $normalized_points = [];

        foreach ($meeting_points as $meeting_point) {
            if (is_array($meeting_point)) {
                $meeting_point = (object) $meeting_point;
            }

            if (!is_object($meeting_point)) {
                continue;
            }

            $normalized_points[] = $meeting_point;
        }

        if (empty($normalized_points)) {
            return [];
        }

        return array_map([$this, 'formatMeetingPointForMobile'], $normalized_points);
    }

    private function formatMeetingPointForMobile(object $point): array {
        $latitude = null;
        if (isset($point->lat) && is_numeric($point->lat)) {
            $latitude = (float) $point->lat;
        } elseif (isset($point->latitude) && is_numeric($point->latitude)) {
            $latitude = (float) $point->latitude;
        }

        $longitude = null;
        if (isset($point->lng) && is_numeric($point->lng)) {
            $longitude = (float) $point->lng;
        } elseif (isset($point->longitude) && is_numeric($point->longitude)) {
            $longitude = (float) $point->longitude;
        }

        $description = null;
        if (isset($point->note) && $point->note !== '') {
            $description = (string) $point->note;
        } elseif (isset($point->description) && $point->description !== '') {
            $description = (string) $point->description;
        }

        $is_default = false;
        if (isset($point->is_default)) {
            $is_default = (bool) $point->is_default;
        }

        return [
            'id' => property_exists($point, 'id') && is_numeric($point->id) ? (int) $point->id : 0,
            'name' => property_exists($point, 'name') && $point->name !== null ? (string) $point->name : '',
            'address' => property_exists($point, 'address') && $point->address !== null ? (string) $point->address : '',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'description' => $description,
            'is_default' => $is_default,
        ];
    }

    private function getExperienceExtras(int $product_id): array {
        global $wpdb;

        $table_extras = $wpdb->prefix . 'fp_extras';
        $table_product_extras = $wpdb->prefix . 'fp_product_extras';

        $prepared = $wpdb->prepare(
            "SELECT e.*, pe.sort_order
            FROM {$table_extras} e
            INNER JOIN {$table_product_extras} pe ON e.id = pe.extra_id
            WHERE pe.product_id = %d
            ORDER BY pe.sort_order ASC, e.name ASC",
            $product_id
        );

        $extras = $wpdb->get_results($prepared);

        if (!empty($wpdb->last_error)) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log(sprintf(
                    'FP Esperienze: Failed to load extras for product %d - %s',
                    $product_id,
                    $wpdb->last_error
                ));
            }

            return [];
        }

        if (empty($extras)) {
            return [];
        }

        $metadata_map = $this->getProductExtraMetadata($product_id);

        $mobile_extras = [];

        foreach ($extras as $extra) {
            $extra_id = isset($extra->id) ? (int) $extra->id : 0;
            $extra_metadata = $metadata_map[$extra_id] ?? [];
            if (!is_array($extra_metadata)) {
                $extra_metadata = [];
            }

            $mobile_extras[] = [
                'id' => $extra_id,
                'name' => (string) ($extra->name ?? ''),
                'description' => (string) ($extra->description ?? ''),
                'price' => isset($extra->price) ? (float) $extra->price : 0.0,
                'billing_type' => (string) ($extra->billing_type ?? 'per_person'),
                'type' => (string) ($extra->billing_type ?? 'per_person'),
                'is_required' => !empty($extra->is_required),
                'max_quantity' => isset($extra->max_quantity) ? (int) $extra->max_quantity : 1,
                'tax_class' => (string) ($extra->tax_class ?? ''),
                'sort_order' => isset($extra->sort_order) ? (int) $extra->sort_order : 0,
                'metadata' => $extra_metadata,
            ];
        }

        return $mobile_extras;
    }

    /**
     * Retrieve metadata for extras associated with a product.
     *
     * @param int $product_id Product identifier.
     * @return array<int, array<string, mixed>>
     */
    private function getProductExtraMetadata(int $product_id): array {
        if (!function_exists('get_post_meta')) {
            return [];
        }

        $metadata = get_post_meta($product_id, '_fp_product_extra_meta', true);

        if (!is_array($metadata)) {
            $metadata = [];
        }

        foreach ($metadata as $extra_id => $extra_meta) {
            if (!is_array($extra_meta)) {
                $metadata[$extra_id] = (array) $extra_meta;
            }
        }

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('fp_esperienze_mobile_product_extra_meta', $metadata, $product_id);
            if (is_array($filtered)) {
                $metadata = $filtered;
            }
        }

        return $metadata;
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
        $product_id = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        if ($product_id <= 0 || !wc_get_product($product_id)) {
            return new WP_Error('invalid_product', __('Invalid product', 'fp-esperienze'), ['status' => 400]);
        }

        $booking_date = isset($data['booking_date']) ? $data['booking_date'] : '';
        $date_obj = DateTime::createFromFormat('Y-m-d', $booking_date);
        if ($booking_date === '' || !$date_obj || $date_obj->format('Y-m-d') !== $booking_date) {
            return new WP_Error('invalid_date', __('Invalid booking date', 'fp-esperienze'), ['status' => 400]);
        }

        $booking_time = isset($data['booking_time']) ? $data['booking_time'] : '';
        $valid_time = false;
        foreach (['H:i:s', 'H:i'] as $format) {
            $time_obj = DateTime::createFromFormat($format, $booking_time);
            if ($time_obj && $time_obj->format($format) === $booking_time) {
                $valid_time = true;
                break;
            }
        }

        if (!$valid_time) {
            return new WP_Error('invalid_time', __('Invalid booking time', 'fp-esperienze'), ['status' => 400]);
        }

        $participants_total = 0;
        $participants = $data['participants'] ?? 0;
        if (is_array($participants)) {
            $participants_total = (int) ($participants['adults'] ?? 0) + (int) ($participants['children'] ?? 0);
        } else {
            $participants_total = (int) $participants;
        }

        if ($participants_total < 1) {
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

    /**
     * Generate a QR code image for the provided data.
     *
     * @param string $data QR data payload.
     * @return string|WP_Error Data URL representing the QR code, or WP_Error on failure.
     */
    private function generateQRCodeImage(string $data): string|WP_Error {
        if ('' === trim($data)) {
            return new WP_Error('invalid_qr_data', __('Invalid QR code data.', 'fp-esperienze'), ['status' => 400]);
        }

        if (!$this->qrCodeLibraryAvailable) {
            return new WP_Error(
                'qr_library_missing',
                __('QR code generation is currently unavailable. Please contact support.', 'fp-esperienze'),
                ['status' => 500]
            );
        }

        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel'   => QRCode::ECC_M,
            ]);

            $qrCode = new QRCode($options);
            $svg     = $qrCode->render($data);

            if (!is_string($svg) || '' === trim($svg)) {
                error_log('FP Esperienze: QR code generation returned empty output.');

                return new WP_Error(
                    'qr_generation_failed',
                    __('Unable to generate QR code at the moment. Please try again later.', 'fp-esperienze'),
                    ['status' => 500]
                );
            }

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (Throwable $exception) {
            error_log('FP Esperienze: Failed to generate QR code: ' . $exception->getMessage());

            return new WP_Error(
                'qr_generation_failed',
                __('Unable to generate QR code at the moment. Please try again later.', 'fp-esperienze'),
                ['status' => 500]
            );
        }
    }

    /**
     * Decode QR payload and ensure it has not expired.
     *
     * @param string $qr_data Encoded QR payload.
     * @return array|WP_Error|null
     */
    private function decodeQRData(string $qr_data): array|WP_Error|null {
        $decoded = base64_decode($qr_data, true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        if (!is_array($data) || !isset($data['booking_id'], $data['timestamp'], $data['hash'])) {
            return null;
        }

        // Verify hash integrity before trusting the payload.
        $expected_hash = hash_hmac('sha256', 'booking_' . $data['booking_id'] . '_' . $data['timestamp'], wp_salt('auth'));
        if (!hash_equals($expected_hash, $data['hash'])) {
            return null;
        }

        $timestamp = (int) $data['timestamp'];
        if ($timestamp <= 0) {
            return null;
        }

        $expiry_timestamp = $timestamp + self::QR_CODE_TTL;
        if ($expiry_timestamp < time()) {
            $valid_hours = max(1, (int) ceil(self::QR_CODE_TTL / 3600));

            return new WP_Error(
                'qr_expired',
                sprintf(
                    /* translators: %d: number of hours a QR code remains valid */
                    __('This QR code has expired. QR codes remain valid for %d hours. Please generate a new code.', 'fp-esperienze'),
                    $valid_hours
                ),
                [
                    'status' => 410,
                    'expires_after' => self::QR_CODE_TTL,
                    'expiration_policy' => sprintf(
                        /* translators: %d: number of hours a QR code remains valid */
                        __('For security, QR codes expire %d hours after they are generated. Request a fresh code to continue.', 'fp-esperienze'),
                        $valid_hours
                    ),
                    'expired_at' => $expiry_timestamp,
                ]
            );
        }

        return $data;
    }

    private function getMeetingPointById(?int $meeting_point_id): ?array {
        if ($meeting_point_id === null || $meeting_point_id <= 0) {
            return null;
        }

        $point = MeetingPointManager::getMeetingPoint($meeting_point_id);

        if (!$point) {
            return null;
        }

        $meeting_point = $this->formatMeetingPointForMobile($point);

        $latitude = null;
        foreach (['lat', 'latitude'] as $latitude_property) {
            if (is_object($point) && property_exists($point, $latitude_property)) {
                $value = $point->{$latitude_property};
            } elseif (is_array($point) && array_key_exists($latitude_property, $point)) {
                $value = $point[$latitude_property];
            } else {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $latitude = (float) $value;
                break;
            }
        }

        $longitude = null;
        foreach (['lng', 'longitude'] as $longitude_property) {
            if (is_object($point) && property_exists($point, $longitude_property)) {
                $value = $point->{$longitude_property};
            } elseif (is_array($point) && array_key_exists($longitude_property, $point)) {
                $value = $point[$longitude_property];
            } else {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $longitude = (float) $value;
                break;
            }
        }

        $description = null;
        foreach (['note', 'description'] as $description_property) {
            if (is_object($point) && property_exists($point, $description_property)) {
                $value = $point->{$description_property};
            } elseif (is_array($point) && array_key_exists($description_property, $point)) {
                $value = $point[$description_property];
            } else {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_scalar($value)) {
                $description = (string) $value;
                break;
            }
        }

        $meeting_point['latitude'] = $latitude;
        $meeting_point['longitude'] = $longitude;
        $meeting_point['description'] = $description;

        return $meeting_point;
    }

    private function canCancelBooking(object $booking): bool {
        if ($booking->status !== 'confirmed') {
            return false;
        }

        return $this->isBookingBeyondTwentyFourHours($booking);
    }

    private function canRescheduleBooking(object $booking): bool {
        if ($booking->status !== 'confirmed') {
            return false;
        }

        return $this->isBookingBeyondTwentyFourHours($booking);
    }

    private function isBookingBeyondTwentyFourHours(object $booking): bool {
        if (!isset($booking->booking_date) || $booking->booking_date === '') {
            return false;
        }

        $timezone = wp_timezone();
        $booking_date = trim((string) $booking->booking_date);

        $booking_time = '';
        if (isset($booking->booking_time) && is_scalar($booking->booking_time)) {
            $booking_time = trim((string) $booking->booking_time);
        }

        if ($booking_time === '') {
            $booking_time = '00:00:00';
        }

        $datetime_string = trim($booking_date . ' ' . $booking_time);

        try {
            $booking_datetime = new DateTimeImmutable($datetime_string, $timezone);
        } catch (Throwable $exception) {
            return false;
        }

        $threshold = current_time('timestamp') + DAY_IN_SECONDS;

        return $booking_datetime->getTimestamp() > $threshold;
    }

    private function getBookingExtras(int $booking_id): array {
        global $wpdb;
        
        $extras = $wpdb->get_results($wpdb->prepare("
            SELECT extra_id, name, price, billing_type, quantity, total
            FROM {$wpdb->prefix}fp_booking_extras
            WHERE booking_id = %d
            ORDER BY id ASC
        ", $booking_id));

        if (!$extras) {
            return [];
        }

        $booking_extras = [];

        foreach ($extras as $extra) {
            $price = isset($extra->price) ? (float) $extra->price : 0.0;
            $quantity = isset($extra->quantity) ? (int) $extra->quantity : 0;
            $total = isset($extra->total) ? (float) $extra->total : $price * $quantity;
            $billing_type = isset($extra->billing_type) ? (string) $extra->billing_type : 'per_booking';
            $billing_type = str_replace(['-', ' '], '_', strtolower($billing_type));
            if (!in_array($billing_type, ['per_person', 'per_booking'], true)) {
                $billing_type = 'per_booking';
            }

            $booking_extras[] = [
                'id' => isset($extra->extra_id) ? (int) $extra->extra_id : 0,
                'name' => (string) $extra->name,
                'price' => round($price, 2),
                'quantity' => $quantity,
                'billing_type' => $billing_type,
                'total' => round($total, 2),
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
     * @param int    $user_id  User ID.
     * @param string $title    Notification title.
     * @param string $message  Notification message/body.
     * @param array  $data     Optional additional data.
     * @param string $priority Notification priority.
     *
     * @return bool|WP_Error Whether the push notification was sent, or WP_Error on failure.
     */
    private function sendPushToUser(int $user_id, string $title, string $message, array $data = [], string $priority = 'high'): bool|WP_Error {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_push_tokens';
        $token_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT token, platform, expires_at FROM {$table_name} WHERE user_id = %d",
                $user_id
            )
        );

        if (empty($token_rows)) {
            return new WP_Error(
                'push_no_tokens',
                __( 'The user has no registered push notification tokens', 'fp-esperienze' ),
                [
                    'status'  => 400,
                    'user_id' => $user_id,
                ]
            );
        }

        $priority        = $this->normalizeNotificationPriority( $priority );
        $normalized_data = $this->normalizeNotificationData( $data );

        $payload = [
            'title'    => $title,
            'body'     => $message,
            'data'     => $normalized_data,
            'priority' => $priority,
        ];

        $sent               = false;
        $errors              = [];
        $invalid_tokens      = [];
        $tokens_to_delete   = [];
        $now_gmt            = current_time('timestamp', true);

        foreach ( $token_rows as $row ) {
            $token_value = isset( $row->token ) ? (string) $row->token : '';

            if ( '' === $token_value ) {
                $tokens_to_delete[] = $token_value;
                continue;
            }

            $expires_at_raw = isset( $row->expires_at ) ? (string) $row->expires_at : '';
            if ( '' !== $expires_at_raw ) {
                $expiry_timestamp = strtotime( $expires_at_raw . ' UTC' );
                if ( false === $expiry_timestamp ) {
                    $expiry_timestamp = strtotime( $expires_at_raw );
                }

                if ( false !== $expiry_timestamp && $expiry_timestamp <= $now_gmt ) {
                    $invalid_tokens[]   = $token_value;
                    $tokens_to_delete[] = $token_value;
                    continue;
                }
            }

            $result = $this->sendPushPayload(
                $token_value,
                $payload,
                [
                    'user_id'  => $user_id,
                    'platform' => isset( $row->platform ) ? (string) $row->platform : null,
                ]
            );

            if ( true === $result ) {
                $sent                     = true;
                continue;
            }

            if ( is_wp_error( $result ) ) {
                $errors[] = $result;

                $error_data   = $result->get_error_data();
                $remove_token = is_array( $error_data ) && ! empty( $error_data['remove_token'] );

                if ( $remove_token ) {
                    $invalid_tokens[]   = $token_value;
                    $tokens_to_delete[] = $token_value;
                    continue;
                }
            }
        }

        if ( ! empty( $tokens_to_delete ) ) {
            $tokens_to_delete = array_values( array_unique( $tokens_to_delete ) );

            foreach ( $tokens_to_delete as $token_to_delete ) {
                if ( '' === $token_to_delete ) {
                    continue;
                }

                $wpdb->delete(
                    $table_name,
                    [ 'token' => $token_to_delete ],
                    [ '%s' ]
                );
            }
        }

        $invalid_tokens = array_values( array_unique( $invalid_tokens ) );

        if ( $sent ) {
            return true;
        }

        if ( ! empty( $errors ) ) {
            if ( 1 === count( $errors ) ) {
                $error      = $errors[0];
                $error_data = $error->get_error_data();

                if ( ! is_array( $error_data ) ) {
                    $error_data = [];
                }

                $error_data = array_merge(
                    $error_data,
                    [
                        'user_id'        => $user_id,
                        'invalid_tokens' => $invalid_tokens,
                    ]
                );

                if ( ! isset( $error_data['status'] ) ) {
                    $error_data['status'] = 500;
                }

                $error->add_data( $error_data );

                return $error;
            }

            $messages = array_map(
                static fn( WP_Error $error ): string => $error->get_error_message(),
                $errors
            );

            return new WP_Error(
                'push_delivery_failed',
                __( 'Failed to deliver push notification to any device', 'fp-esperienze' ),
                [
                    'status'          => 500,
                    'user_id'         => $user_id,
                    'errors'          => $messages,
                    'invalid_tokens'  => $invalid_tokens,
                ]
            );
        }

        return new WP_Error(
            'push_no_valid_tokens',
            __( 'No valid push notification tokens are available for this user', 'fp-esperienze' ),
            [
                'status'         => 400,
                'user_id'        => $user_id,
                'invalid_tokens' => $invalid_tokens,
            ]
        );
    }

    /**
     * Send the push payload to the configured provider.
     *
     * @param string $token   Device token.
     * @param array  $payload Notification payload.
     * @param array  $context Additional metadata about the token (user_id, platform).
     *
     * @return bool|WP_Error Whether the notification was sent successfully, or WP_Error on failure.
     */
    private function sendPushPayload( string $token, array $payload, array $context = [] ): bool|WP_Error {
        $platform = isset( $context['platform'] ) && $context['platform'] !== ''
            ? (string) $context['platform']
            : null;

        $config = get_option( 'fp_esperienze_mobile_notifications' );

        if ( ! is_array( $config ) ) {
            $this->logPushError( 'Push notification configuration is missing.' );

            return new WP_Error(
                'push_config_missing',
                __( 'Push notification service is not configured', 'fp-esperienze' ),
                [
                    'status'   => 500,
                    'token'    => $token,
                    'platform' => $platform,
                ]
            );
        }

        $provider  = isset( $config['provider'] ) ? strtolower( (string) $config['provider'] ) : 'fcm';
        $server_key = isset( $config['server_key'] ) ? trim( (string) $config['server_key'] ) : '';
        $project_id = isset( $config['project_id'] ) ? trim( (string) $config['project_id'] ) : '';

        if ( '' === $server_key ) {
            $this->logPushError( 'Missing Firebase server key in configuration.', [ 'provider' => $provider, 'platform' => $platform ] );

            return new WP_Error(
                'push_missing_credentials',
                __( 'Push notification credentials are missing', 'fp-esperienze' ),
                [
                    'status'   => 500,
                    'token'    => $token,
                    'provider' => $provider,
                    'platform' => $platform,
                ]
            );
        }

        if ( '' === $project_id ) {
            $this->logPushError( 'Missing Firebase project ID in configuration.', [ 'provider' => $provider, 'platform' => $platform ] );

            return new WP_Error(
                'push_missing_project',
                __( 'Push notification project ID is missing', 'fp-esperienze' ),
                [
                    'status'   => 500,
                    'token'    => $token,
                    'provider' => $provider,
                    'platform' => $platform,
                ]
            );
        }

        if ( 'fcm' !== $provider ) {
            $this->logPushError( 'Unsupported push provider requested.', [ 'provider' => $provider, 'platform' => $platform ] );

            return new WP_Error(
                'push_provider_unsupported',
                __( 'Configured push notification provider is not supported', 'fp-esperienze' ),
                [
                    'status'   => 500,
                    'token'    => $token,
                    'provider' => $provider,
                    'platform' => $platform,
                ]
            );
        }

        $body = [
            'to'           => $token,
            'priority'     => $payload['priority'] ?? 'high',
            'notification' => [
                'title' => (string) ( $payload['title'] ?? '' ),
                'body'  => (string) ( $payload['body'] ?? ( $payload['message'] ?? '' ) ),
            ],
            'data'         => is_array( $payload['data'] ?? null ) ? $payload['data'] : [],
        ];

        $body_json = wp_json_encode( $body );

        if ( false === $body_json ) {
            $this->logPushError(
                'Failed to encode push payload as JSON.',
                [
                    'token'    => $token,
                    'payload'  => $body,
                    'platform' => $platform,
                ]
            );

            return new WP_Error(
                'push_payload_encoding_failed',
                __( 'Unable to encode push notification payload', 'fp-esperienze' ),
                [
                    'status'   => 500,
                    'token'    => $token,
                    'payload'  => $body,
                    'platform' => $platform,
                ]
            );
        }

        $args = [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'key=' . $server_key,
                'Content-Type'  => 'application/json; charset=utf-8',
            ],
            'body'    => $body_json,
        ];

        $response = wp_remote_post( 'https://fcm.googleapis.com/fcm/send', $args );

        if ( is_wp_error( $response ) ) {
            $this->logPushError(
                'Push notification request failed.',
                [
                    'token'    => $token,
                    'reason'   => $response->get_error_message(),
                    'project'  => $project_id,
                    'platform' => $platform,
                ]
            );

            return new WP_Error(
                'push_http_request_failed',
                __( 'Unable to contact the push notification service', 'fp-esperienze' ),
                [
                    'status'  => 500,
                    'token'   => $token,
                    'reason'  => $response->get_error_message(),
                    'project' => $project_id,
                    'platform' => $platform,
                ]
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );

        if ( 200 !== $status_code ) {
            $this->logPushError(
                'Unexpected HTTP status from push provider.',
                [
                    'token'    => $token,
                    'status'   => $status_code,
                    'body'     => $body_raw,
                    'project'  => $project_id,
                    'platform' => $platform,
                ]
            );

            return new WP_Error(
                'push_http_error',
                __( 'Push notification service returned an unexpected status code', 'fp-esperienze' ),
                [
                    'status'  => 500,
                    'token'   => $token,
                    'body'    => $body_raw,
                    'project' => $project_id,
                    'platform' => $platform,
                ]
            );
        }

        $decoded = json_decode( $body_raw, true );

        if ( ! is_array( $decoded ) ) {
            $this->logPushError(
                'Unable to decode push provider response.',
                [
                    'token'    => $token,
                    'body'     => $body_raw,
                    'project'  => $project_id,
                    'platform' => $platform,
                ]
            );

            return new WP_Error(
                'push_invalid_response',
                __( 'Push notification service returned an invalid response', 'fp-esperienze' ),
                [
                    'status'  => 500,
                    'token'   => $token,
                    'body'    => $body_raw,
                    'project' => $project_id,
                    'platform' => $platform,
                ]
            );
        }

        $success = isset( $decoded['success'] ) ? (int) $decoded['success'] : 0;
        $results = isset( $decoded['results'] ) && is_array( $decoded['results'] ) ? $decoded['results'] : [];

        foreach ( $results as $result ) {
            if ( isset( $result['message_id'] ) ) {
                $this->markPushTokenAsSeen( $token, $context );

                return true;
            }

            if ( isset( $result['error'] ) ) {
                $error_reason  = (string) $result['error'];
                $remove_token  = in_array( $error_reason, [ 'NotRegistered', 'InvalidRegistration', 'MismatchSenderId' ], true );
                $log_context   = [
                    'token'        => $token,
                    'error'        => $error_reason,
                    'project'      => $project_id,
                    'remove_token' => $remove_token,
                    'platform'     => $platform,
                ];

                $this->logPushError( 'Push provider reported delivery error.', $log_context );

                return new WP_Error(
                    'push_delivery_failed',
                    __( 'Push notification could not be delivered', 'fp-esperienze' ),
                    [
                        'status'        => $remove_token ? 410 : 500,
                        'token'         => $token,
                        'provider'      => $provider,
                        'project'       => $project_id,
                        'error'         => $error_reason,
                        'remove_token'  => $remove_token,
                        'response_body' => $decoded,
                        'platform'      => $platform,
                    ]
                );
            }
        }

        if ( $success > 0 ) {
            $this->markPushTokenAsSeen( $token, $context );

            return true;
        }

        $this->logPushError(
            'Push provider response did not confirm delivery.',
            [
                'token'    => $token,
                'body'     => $decoded,
                'project'  => $project_id,
                'platform' => $platform,
            ]
        );

        return new WP_Error(
            'push_delivery_unconfirmed',
            __( 'Push notification delivery could not be confirmed', 'fp-esperienze' ),
            [
                'status'        => 500,
                'token'         => $token,
                'provider'      => $provider,
                'response_body' => $decoded,
                'platform'      => $platform,
            ]
        );
    }

    /**
     * Update the last_seen timestamp for a delivered push token.
     */
    private function markPushTokenAsSeen(string $token, array $context): void {
        if (empty($context['user_id'])) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_push_tokens';
        $user_id    = (int) $context['user_id'];

        $wpdb->update(
            $table_name,
            [ 'last_seen' => current_time('mysql') ],
            [
                'user_id' => $user_id,
                'token'   => $token,
            ],
            null,
            [ '%d', '%s' ]
        );
    }

    /**
     * Normalize additional payload data for the push provider.
     *
     * @param array $data Arbitrary notification data.
     *
     * @return array Normalized data with scalar string values.
     */
    private function normalizeNotificationData( array $data ): array {
        $normalized = [];

        foreach ( $data as $key => $value ) {
            if ( null === $value ) {
                continue;
            }

            if ( is_bool( $value ) ) {
                $normalized[ $key ] = $value ? '1' : '0';
                continue;
            }

            if ( is_scalar( $value ) ) {
                $normalized[ $key ] = (string) $value;
                continue;
            }

            if ( is_object( $value ) && method_exists( $value, '__toString' ) ) {
                $normalized[ $key ] = (string) $value;
                continue;
            }

            $normalized[ $key ] = wp_json_encode( $value );
        }

        return $normalized;
    }

    /**
     * Ensure push notification priority is one of the supported values.
     *
     * @param string $priority Requested priority.
     *
     * @return string Normalized priority.
     */
    private function normalizeNotificationPriority( string $priority ): string {
        $priority = strtolower( $priority );

        if ( ! in_array( $priority, [ 'high', 'normal' ], true ) ) {
            return 'high';
        }

        return $priority;
    }

    /**
     * Log push notification errors for debugging.
     *
     * @param string $message Error message.
     * @param array  $context Additional context.
     */
    private function logPushError( string $message, array $context = [] ): void {
        $log_message = 'FP Esperienze Push: ' . $message;

        if ( ! empty( $context ) ) {
            $encoded_context = wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

            if ( false !== $encoded_context ) {
                $log_message .= ' ' . $encoded_context;
            }
        }

        error_log( $log_message );
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

        $table_name     = $wpdb->prefix . 'fp_bookings';
        $checked_in_at  = current_time('mysql');
        $prepared_query = $wpdb->prepare(
            "UPDATE {$table_name} SET checked_in_at = %s, checked_in_by = %d WHERE id = %d AND checked_in_at IS NULL",
            $checked_in_at,
            $staff_user_id,
            $booking_id
        );

        $result = $wpdb->query($prepared_query);

        if ($result === false) {
            return [
                'success' => false,
                'booking_id' => $booking_id,
                'action' => 'check_in',
            ];
        }

        if ($result === 0) {
            return [
                'success' => false,
                'booking_id' => $booking_id,
                'action' => 'check_in',
                'message' => __('Booking not found or already checked in.', 'fp-esperienze'),
            ];
        }

        return [
            'success' => true,
            'booking_id' => $booking_id,
            'action' => 'check_in',
        ];
    }

    private function processOfflineStatusUpdate(int $booking_id, string $status, int $staff_user_id): array {
        $valid_statuses = ['confirmed', 'completed', 'cancelled', 'no_show'];

        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }

        $booking_manager = BookingManager::getInstance();
        $result = $booking_manager->updateBookingStatusById($booking_id, $status);

        if ($result === false) {
            return [
                'success' => false,
                'booking_id' => $booking_id,
                'action' => 'status_update',
                'status' => $status,
            ];
        }

        if ($result === 0) {
            return [
                'success' => false,
                'booking_id' => $booking_id,
                'action' => 'status_update',
                'status' => $status,
                'message' => __('Booking status was not updated. The booking may not exist or already has this status.', 'fp-esperienze'),
            ];
        }

        return [
            'success' => true,
            'booking_id' => $booking_id,
            'action' => 'status_update',
            'status' => $status,
        ];
    }

    private function getStaffScheduleData(int $staff_user_id, string $date_from, string $date_to): array {
        $assignments = StaffScheduleManager::getAssignmentsForStaff($staff_user_id, $date_from, $date_to);

        if (empty($assignments)) {
            return [];
        }

        $shifts = [];

        foreach ($assignments as $assignment) {
            $shift_start = $this->createDateTimeFromString($assignment['shift_start'] ?? null);
            $shift_end   = $this->createDateTimeFromString($assignment['shift_end'] ?? null);
            $resolved_date = $shift_start ? $shift_start->format('Y-m-d') : $this->normalizeDateValue($assignment['booking_date'] ?? null, $date_from);

            $key_start = $shift_start ? $shift_start->format('Y-m-d H:i:s') : ($resolved_date . ' 00:00:00');
            $key_end   = $shift_end ? $shift_end->format('Y-m-d H:i:s') : '';
            $shift_key = $key_start . '|' . $key_end;

            if ($shift_start === null && $shift_end === null) {
                $shift_key .= '|assignment-' . ($assignment['assignment_id'] ?? uniqid('assignment', true));
            }

            if (!isset($shifts[$shift_key])) {
                $shifts[$shift_key] = [
                    'date' => $resolved_date,
                    'shift_start' => $shift_start ? $shift_start->format('H:i') : null,
                    'shift_end' => $shift_end ? $shift_end->format('H:i') : null,
                    'roles' => [],
                    'assigned_experiences' => [],
                ];
            }

            $assignment_roles = $this->normalizeScheduleRoles($assignment['roles'] ?? null);
            if (!empty($assignment_roles)) {
                foreach ($assignment_roles as $role) {
                    if (!in_array($role, $shifts[$shift_key]['roles'], true)) {
                        $shifts[$shift_key]['roles'][] = $role;
                    }
                }
            }

            $experience = $this->normalizeAssignmentPayload($assignment, $resolved_date, $shift_start, $assignment_roles);
            if ($experience !== null) {
                $shifts[$shift_key]['assigned_experiences'][] = $experience;
            }
        }

        $schedule = [];

        foreach ($shifts as $shift) {
            $shift['roles'] = array_values($shift['roles']);

            if (!empty($shift['assigned_experiences'])) {
                usort($shift['assigned_experiences'], function(array $a, array $b): int {
                    $time_a = $a['booking_time'] ?? '';
                    $time_b = $b['booking_time'] ?? '';

                    $comparison = strcmp((string) $time_a, (string) $time_b);
                    if ($comparison !== 0) {
                        return $comparison;
                    }

                    return ($a['assignment_id'] ?? 0) <=> ($b['assignment_id'] ?? 0);
                });

                $shift['assigned_experiences'] = array_values($shift['assigned_experiences']);
            }

            $schedule[] = $shift;
        }

        usort($schedule, function(array $a, array $b): int {
            $date_comparison = strcmp($a['date'] ?? '', $b['date'] ?? '');
            if ($date_comparison !== 0) {
                return $date_comparison;
            }

            return strcmp($a['shift_start'] ?? '', $b['shift_start'] ?? '');
        });

        return $schedule;
    }

    /**
     * Create DateTime instance from database value.
     */
    private function createDateTimeFromString(?string $datetime): ?DateTime {
        if (!is_string($datetime)) {
            return null;
        }

        $datetime = trim($datetime);

        if ($datetime === '') {
            return null;
        }

        $object = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if ($object instanceof DateTime) {
            return $object;
        }

        $object = DateTime::createFromFormat(DateTime::ATOM, $datetime);
        if ($object instanceof DateTime) {
            return $object;
        }

        return null;
    }

    /**
     * Normalize any supported date value to Y-m-d format.
     *
     * @param mixed  $value    Raw value from the assignment row.
     * @param string $fallback Fallback date.
     */
    private function normalizeDateValue($value, string $fallback): string {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return $fallback;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }

            $object = DateTime::createFromFormat('Y-m-d H:i:s', $value);
            if ($object instanceof DateTime) {
                return $object->format('Y-m-d');
            }
        }

        return $fallback;
    }

    /**
     * Normalize the roles payload into a sanitized array of strings.
     *
     * @param mixed $roles Roles value from database.
     *
     * @return array<int, string>
     */
    private function normalizeScheduleRoles($roles): array {
        if (is_array($roles)) {
            $roles_list = $roles;
        } elseif (is_string($roles)) {
            $roles = trim($roles);

            if ($roles === '') {
                return [];
            }

            $decoded = json_decode($roles, true);
            if (is_array($decoded)) {
                $roles_list = $decoded;
            } else {
                $roles_list = array_map('trim', explode(',', $roles));
            }
        } else {
            return [];
        }

        $normalized = [];

        foreach ($roles_list as $role) {
            if (is_array($role)) {
                $role = implode(' ', $role);
            }

            if (!is_scalar($role)) {
                continue;
            }

            $clean_role = sanitize_text_field((string) $role);

            if ($clean_role === '') {
                continue;
            }

            if (!in_array($clean_role, $normalized, true)) {
                $normalized[] = $clean_role;
            }
        }

        return $normalized;
    }

    /**
     * Build the normalized experience payload for a single assignment.
     *
     * @param array<string, mixed> $assignment  Assignment row.
     * @param string               $resolved_date Resolved date for the shift.
     * @param DateTime|null        $shift_start Shift start time.
     * @param array<int, string>   $roles       Roles attached to the assignment.
     */
    private function normalizeAssignmentPayload(array $assignment, string $resolved_date, ?DateTime $shift_start, array $roles): ?array {
        $booking_id    = $assignment['booking_id'] ?? null;
        $product_id    = $assignment['product_id'] ?? null;
        $product_name  = $assignment['product_name'] ?? null;
        $booking_time  = $assignment['booking_time'] ?? null;
        $booking_status = $assignment['booking_status'] ?? null;
        $participants  = $assignment['participants'] ?? null;

        $normalized_time = null;
        if (is_string($booking_time)) {
            $booking_time = trim($booking_time);

            if ($booking_time !== '') {
                $time_object = DateTime::createFromFormat('H:i:s', $booking_time);
                if (!$time_object instanceof DateTime) {
                    $time_object = DateTime::createFromFormat('H:i', $booking_time);
                }

                if ($time_object instanceof DateTime) {
                    $normalized_time = $time_object->format('H:i');
                } else {
                    $normalized_time = substr($booking_time, 0, 5);
                }
            }
        }

        if ($normalized_time === null && $shift_start instanceof DateTime) {
            $normalized_time = $shift_start->format('H:i');
        }

        $payload = [
            'assignment_id' => (int) ($assignment['assignment_id'] ?? 0),
            'booking_id' => $booking_id !== null ? (int) $booking_id : null,
            'product_id' => $product_id !== null ? (int) $product_id : null,
            'product_name' => is_string($product_name) ? sanitize_text_field($product_name) : '',
            'booking_date' => $this->normalizeDateValue($assignment['booking_date'] ?? null, $resolved_date),
            'booking_time' => $normalized_time,
            'booking_status' => is_string($booking_status) ? sanitize_text_field($booking_status) : '',
            'roles' => $roles,
        ];

        if ($participants !== null && $participants !== '') {
            $payload['participants'] = (int) $participants;
        }

        if (!empty($assignment['notes'])) {
            $payload['notes'] = sanitize_textarea_field((string) $assignment['notes']);
        }

        $meeting_point = $this->normalizeMeetingPointPayload($assignment);
        if ($meeting_point !== null) {
            $payload['meeting_point'] = $meeting_point;
        }

        return $payload;
    }

    /**
     * Normalize meeting point payload from assignment data.
     *
     * @param array<string, mixed> $assignment Assignment data.
     */
    private function normalizeMeetingPointPayload(array $assignment): ?array {
        $meeting_point = [];

        if (isset($assignment['meeting_point_id']) && $assignment['meeting_point_id'] !== null) {
            $meeting_point['id'] = (int) $assignment['meeting_point_id'];
        }

        if (!empty($assignment['meeting_point_name']) && is_string($assignment['meeting_point_name'])) {
            $meeting_point['name'] = sanitize_text_field($assignment['meeting_point_name']);
        }

        if (!empty($assignment['meeting_point_address']) && is_string($assignment['meeting_point_address'])) {
            $meeting_point['address'] = sanitize_textarea_field($assignment['meeting_point_address']);
        }

        if (isset($assignment['meeting_point_lat']) && $assignment['meeting_point_lat'] !== null && $assignment['meeting_point_lat'] !== '') {
            $meeting_point['lat'] = (float) $assignment['meeting_point_lat'];
        }

        if (isset($assignment['meeting_point_lng']) && $assignment['meeting_point_lng'] !== null && $assignment['meeting_point_lng'] !== '') {
            $meeting_point['lng'] = (float) $assignment['meeting_point_lng'];
        }

        return $meeting_point ?: null;
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
