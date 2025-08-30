<?php
/**
 * Voucher PDF Generator
 *
 * @package FP\Esperienze\PDF
 */

namespace FP\Esperienze\PDF;

use Dompdf\Dompdf;
use Dompdf\Options;

defined('ABSPATH') || exit;

/**
 * Voucher PDF generation class
 */
class Voucher_Pdf {
    
    /**
     * Generate voucher PDF
     *
     * @param array $voucher_data Voucher data
     * @return string PDF file path
     */
    public static function generate($voucher_data): string {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generate QR code
        $qr_code_path = Qr::generate($voucher_data);
        
        // Build HTML content
        $html = self::buildHtmlContent($voucher_data, $qr_code_path);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Save PDF file
        $upload_dir = wp_upload_dir();
        $voucher_dir = $upload_dir['basedir'] . '/fp-esperienze/voucher/';
        
        if (!file_exists($voucher_dir)) {
            wp_mkdir_p($voucher_dir);
            // Create .htaccess for security
            self::createSecurityHtaccess($voucher_dir);
        }
        
        $filename = 'voucher-' . $voucher_data['code'] . '-' . time() . '.pdf';
        $file_path = $voucher_dir . $filename;
        
        $result = file_put_contents($file_path, $dompdf->output());
        
        if ($result === false) {
            throw new \Exception('Failed to save PDF voucher to: ' . $file_path);
        }
        
        return $file_path;
    }
    
    /**
     * Build HTML content for PDF
     *
     * @param array $voucher_data Voucher data
     * @param string $qr_code_path QR code file path
     * @return string HTML content
     */
    private static function buildHtmlContent($voucher_data, $qr_code_path): string {
        $logo_url = get_option('fp_esperienze_gift_pdf_logo', '');
        $brand_color = get_option('fp_esperienze_gift_pdf_brand_color', '#ff6b35');
        $terms = get_option('fp_esperienze_gift_terms', '');
        $site_name = get_bloginfo('name');
        
        $product = wc_get_product($voucher_data['product_id']);
        $product_name = $product ? $product->get_name() : __('Experience', 'fp-esperienze');
        
        $amount_display = $voucher_data['amount_type'] === 'full' 
            ? __('Prepaid Ticket', 'fp-esperienze')
            : wc_price($voucher_data['amount']);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . esc_html__('Gift Voucher', 'fp-esperienze') . '</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    color: #333;
                }
                .voucher-container {
                    max-width: 600px;
                    margin: 0 auto;
                    border: 2px solid ' . esc_attr($brand_color) . ';
                    border-radius: 10px;
                    padding: 30px;
                    background: #fff;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 20px;
                }
                .logo {
                    max-height: 80px;
                    margin-bottom: 10px;
                }
                .title {
                    color: ' . esc_attr($brand_color) . ';
                    font-size: 28px;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .voucher-details {
                    display: table;
                    width: 100%;
                    margin: 20px 0;
                }
                .detail-row {
                    display: table-row;
                }
                .detail-label, .detail-value {
                    display: table-cell;
                    padding: 8px 0;
                    vertical-align: top;
                }
                .detail-label {
                    font-weight: bold;
                    width: 40%;
                }
                .voucher-code {
                    background: ' . esc_attr($brand_color) . ';
                    color: white;
                    padding: 15px;
                    text-align: center;
                    font-size: 24px;
                    font-weight: bold;
                    letter-spacing: 2px;
                    margin: 20px 0;
                    border-radius: 5px;
                }
                .qr-code {
                    text-align: center;
                    margin: 30px 0;
                }
                .qr-code img {
                    max-width: 150px;
                }
                .message {
                    background: #f8f8f8;
                    padding: 20px;
                    border-radius: 5px;
                    margin: 20px 0;
                    font-style: italic;
                }
                .instructions {
                    background: #e8f4f8;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    font-size: 14px;
                }
                .terms {
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                }
            </style>
        </head>
        <body>
            <div class="voucher-container">
                <div class="header">';
        
        if (!empty($logo_url)) {
            $html .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" class="logo">';
        }
        
        $html .= '
                    <h1 class="title">' . esc_html__('Gift Voucher', 'fp-esperienze') . '</h1>
                    <p>' . esc_html($site_name) . '</p>
                </div>
                
                <div class="voucher-code">' . esc_html($voucher_data['code']) . '</div>
                
                <div class="voucher-details">
                    <div class="detail-row">
                        <div class="detail-label">' . esc_html__('For:', 'fp-esperienze') . '</div>
                        <div class="detail-value">' . esc_html($voucher_data['recipient_name']) . '</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">' . esc_html__('Experience:', 'fp-esperienze') . '</div>
                        <div class="detail-value">' . esc_html($product_name) . '</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">' . esc_html__('Value:', 'fp-esperienze') . '</div>
                        <div class="detail-value">' . $amount_display . '</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">' . esc_html__('Expires:', 'fp-esperienze') . '</div>
                        <div class="detail-value">' . esc_html(date_i18n(get_option('date_format'), strtotime($voucher_data['expires_on']))) . '</div>
                    </div>
                </div>';
        
        if (!empty($voucher_data['message'])) {
            $html .= '
                <div class="message">
                    <strong>' . esc_html__('Personal Message:', 'fp-esperienze') . '</strong><br>
                    ' . nl2br(esc_html($voucher_data['message'])) . '
                </div>';
        }
        
        $html .= '
                <div class="qr-code">
                    <img src="' . esc_url($qr_code_path) . '" alt="' . esc_attr__('QR Code for voucher redemption', 'fp-esperienze') . '">
                    <p><small>' . esc_html__('Scan this QR code to redeem your voucher', 'fp-esperienze') . '</small></p>
                </div>
                
                <div class="instructions">
                    <h4>' . esc_html__('How to Redeem:', 'fp-esperienze') . '</h4>
                    <p>' . sprintf(
                        esc_html__('Visit %s to book your experience and use the voucher code above or scan the QR code during checkout.', 'fp-esperienze'),
                        '<strong>' . esc_html(home_url()) . '</strong>'
                    ) . '</p>
                </div>
                
                <div class="terms">
                    ' . wp_kses_post(nl2br($terms)) . '
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Create security .htaccess file in uploads directory
     *
     * @param string $directory Directory path
     */
    private static function createSecurityHtaccess(string $directory): void {
        $htaccess_content = '# FP Esperienze Security - Prevent directory listing and direct access
Options -Indexes
<Files "*.pdf">
    # Allow only authenticated access to PDFs
    # This will be handled by WordPress endpoint
    Order Deny,Allow
    Deny from all
</Files>
<Files "*.png">
    # QR codes can be accessed directly
    Order Allow,Deny
    Allow from all
</Files>';
        
        $htaccess_path = $directory . '.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, $htaccess_content);
        }
    }
}