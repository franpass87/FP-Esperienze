<?php
/**
 * Secure PDF Download API
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Data\VoucherManager;
use FP\Esperienze\Core\CapabilityManager;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Secure PDF Download API class
 */
class SecurePDFAPI {

    /**
     * Constructor
     */
    public function __construct() {
        // Register routes immediately since this is already called from rest_api_init
        $this->registerRoutes();
    }

    /**
     * Register REST routes
     */
    public function registerRoutes(): void {
        // Secure voucher PDF download endpoint
        register_rest_route('fp-exp/v1', '/voucher/(?P<voucher_id>\d+)/pdf', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'downloadVoucherPDF'],
            'permission_callback' => [$this, 'checkDownloadPermission'],
            'args'                => [
                'voucher_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
                'nonce' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return !empty($param);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Check permission for PDF download
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function checkDownloadPermission(WP_REST_Request $request) {
        // Verify nonce
        $nonce = $request->get_param('nonce');
        if (!wp_verify_nonce($nonce, 'fp_download_voucher_pdf')) {
            return new WP_Error(
                'invalid_nonce',
                __('Security check failed.', 'fp-esperienze'),
                ['status' => 403]
            );
        }

        $voucher_id = $request->get_param('voucher_id');
        $current_user = wp_get_current_user();

        // Must be logged in
        if (!$current_user || !$current_user->ID) {
            return new WP_Error(
                'not_authenticated',
                __('Authentication required.', 'fp-esperienze'),
                ['status' => 401]
            );
        }

        // Admin/shop manager can download any voucher
        if (CapabilityManager::canManageFPEsperienze()) {
            return true;
        }

        // Check if user owns this voucher (customer who purchased it)
        $voucher = VoucherManager::getVoucherById($voucher_id);
        if (!$voucher) {
            return new WP_Error(
                'voucher_not_found',
                __('Voucher not found.', 'fp-esperienze'),
                ['status' => 404]
            );
        }

        // Get order associated with voucher
        $order = wc_get_order($voucher['order_id']);
        if ($order && $order->get_customer_id() === $current_user->ID) {
            return true;
        }

        return new WP_Error(
            'insufficient_permissions',
            __('You do not have permission to download this voucher.', 'fp-esperienze'),
            ['status' => 403]
        );
    }

    /**
     * Download voucher PDF
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function downloadVoucherPDF(WP_REST_Request $request) {
        $voucher_id = $request->get_param('voucher_id');
        
        $voucher = VoucherManager::getVoucherById($voucher_id);
        if (!$voucher) {
            return new WP_Error(
                'voucher_not_found',
                __('Voucher not found.', 'fp-esperienze'),
                ['status' => 404]
            );
        }

        $pdf_path = $voucher['pdf_path'];
        if (empty($pdf_path)) {
            return new WP_Error(
                'pdf_not_found',
                __('PDF file not found.', 'fp-esperienze'),
                ['status' => 404]
            );
        }

        $real_pdf_path = realpath($pdf_path);
        if ($real_pdf_path === false || !file_exists($real_pdf_path)) {
            return new WP_Error(
                'pdf_not_found',
                __('PDF file not found.', 'fp-esperienze'),
                ['status' => 404]
            );
        }

        $uploads = wp_upload_dir();
        $uploads_basedir = isset($uploads['basedir']) ? realpath($uploads['basedir']) : false;
        if (!$uploads_basedir || strpos($real_pdf_path, $uploads_basedir) !== 0) {
            return new WP_Error(
                'invalid_pdf_path',
                __('Access to the requested file is denied.', 'fp-esperienze'),
                ['status' => 403]
            );
        }

        $filename = 'voucher-' . $voucher['code'] . '.pdf';

        if (!file_exists($real_pdf_path) || !is_readable($real_pdf_path)) {
            return new WP_Error(
                'pdf_read_error',
                __('Unable to read PDF file.', 'fp-esperienze'),
                ['status' => 500]
            );
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($real_pdf_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $handle = fopen($real_pdf_path, 'rb');
        if ($handle === false) {
            return new WP_Error(
                'pdf_read_error',
                __('Unable to read PDF file.', 'fp-esperienze'),
                ['status' => 500]
            );
        }

        fpassthru($handle);
        fclose($handle);
        exit;
    }
}