<?php
/**
 * Reports Page Template
 *
 * @package FP\Esperienze\Templates\Admin
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php _e('Reports & Analytics', 'fp-esperienze'); ?></h1>
    
    <!-- Filters Section -->
    <div class="fp-reports-filters" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2><?php _e('Filters', 'fp-esperienze'); ?></h2>
        <form id="fp-reports-filters" method="get">
            <input type="hidden" name="page" value="fp-esperienze-reports">
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Date Range', 'fp-esperienze'); ?></th>
                        <td>
                            <input type="date" name="date_from" id="fp-date-from" value="<?php echo esc_attr(date('Y-m-d', strtotime('-30 days'))); ?>">
                            <span> - </span>
                            <input type="date" name="date_to" id="fp-date-to" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Experience', 'fp-esperienze'); ?></th>
                        <td>
                            <select name="product_id" id="fp-product-filter">
                                <option value=""><?php _e('All Experiences', 'fp-esperienze'); ?></option>
                                <?php foreach ($experience_products as $product) : ?>
                                    <option value="<?php echo esc_attr($product->ID); ?>">
                                        <?php echo esc_html($product->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Meeting Point', 'fp-esperienze'); ?></th>
                        <td>
                            <select name="meeting_point_id" id="fp-meeting-point-filter">
                                <option value=""><?php _e('All Meeting Points', 'fp-esperienze'); ?></option>
                                <?php foreach ($meeting_points as $mp) : ?>
                                    <option value="<?php echo esc_attr($mp->id); ?>">
                                        <?php echo esc_html($mp->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Language', 'fp-esperienze'); ?></th>
                        <td>
                            <select name="language" id="fp-language-filter">
                                <option value=""><?php _e('All Languages', 'fp-esperienze'); ?></option>
                                <option value="en">English</option>
                                <option value="it">Italiano</option>
                                <option value="es">Español</option>
                                <option value="fr">Français</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p class="submit">
                <button type="button" id="fp-update-reports" class="button button-primary">
                    <?php _e('Update Reports', 'fp-esperienze'); ?>
                </button>
            </p>
        </form>
    </div>

    <!-- KPI Dashboard -->
    <div class="fp-kpi-dashboard" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <div class="fp-kpi-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3><?php _e('Total Revenue', 'fp-esperienze'); ?></h3>
            <div class="fp-kpi-value" id="kpi-revenue">
                <span class="fp-loading"><?php _e('Loading...', 'fp-esperienze'); ?></span>
            </div>
        </div>
        
        <div class="fp-kpi-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3><?php _e('Seats Sold', 'fp-esperienze'); ?></h3>
            <div class="fp-kpi-value" id="kpi-seats">
                <span class="fp-loading"><?php _e('Loading...', 'fp-esperienze'); ?></span>
            </div>
        </div>
        
        <div class="fp-kpi-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3><?php _e('Total Bookings', 'fp-esperienze'); ?></h3>
            <div class="fp-kpi-value" id="kpi-bookings">
                <span class="fp-loading"><?php _e('Loading...', 'fp-esperienze'); ?></span>
            </div>
        </div>
        
        <div class="fp-kpi-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3><?php _e('Avg Booking Value', 'fp-esperienze'); ?></h3>
            <div class="fp-kpi-value" id="kpi-avg-value">
                <span class="fp-loading"><?php _e('Loading...', 'fp-esperienze'); ?></span>
            </div>
        </div>
        
    </div>

    <!-- Charts Section -->
    <div class="fp-charts-section" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
        
        <!-- Revenue/Seats Chart -->
        <div class="fp-chart-container" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3><?php _e('Revenue & Seats Trends', 'fp-esperienze'); ?></h3>
            <div class="chart-controls" style="margin-bottom: 15px;">
                <button type="button" class="button chart-period" data-period="day">
                    <?php _e('Daily', 'fp-esperienze'); ?>
                </button>
                <button type="button" class="button chart-period" data-period="week">
                    <?php _e('Weekly', 'fp-esperienze'); ?>
                </button>
                <button type="button" class="button chart-period" data-period="month">
                    <?php _e('Monthly', 'fp-esperienze'); ?>
                </button>
            </div>
            <canvas id="fp-revenue-chart" width="400" height="200"></canvas>
        </div>
        
        <!-- Top Experiences -->
        <div class="fp-top-experiences" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3><?php _e('Top 10 Experiences', 'fp-esperienze'); ?></h3>
            <div id="fp-top-experiences-list">
                <span class="fp-loading"><?php _e('Loading...', 'fp-esperienze'); ?></span>
            </div>
        </div>
        
    </div>

    <!-- UTM Conversions -->
    <div class="fp-utm-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3><?php _e('Traffic Source Conversions', 'fp-esperienze'); ?></h3>
        <div id="fp-utm-conversions">
            <span class="fp-loading"><?php _e('Loading...', 'fp-esperienze'); ?></span>
        </div>
    </div>

    <!-- Load Factors -->
    <div class="fp-load-factors" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3><?php _e('Load Factors by Experience', 'fp-esperienze'); ?></h3>
        <div id="fp-load-factors-table">
            <span class="fp-loading"><?php _e('Loading...', 'fp-esperienze'); ?></span>
        </div>
    </div>

    <!-- Export Section -->
    <div class="fp-export-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3><?php _e('Export Report Data', 'fp-esperienze'); ?></h3>
        <form method="post" id="fp-export-form">
            <?php wp_nonce_field('fp_export_report', 'fp_report_nonce'); ?>
            <input type="hidden" name="action" value="export_report">
            <input type="hidden" name="date_from" id="export-date-from">
            <input type="hidden" name="date_to" id="export-date-to">
            <input type="hidden" name="product_id" id="export-product-id">
            <input type="hidden" name="meeting_point_id" id="export-meeting-point-id">
            <input type="hidden" name="language" id="export-language">
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Export Format', 'fp-esperienze'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="export_format" value="csv" checked>
                                <?php _e('CSV', 'fp-esperienze'); ?>
                            </label>
                            <label style="margin-left: 20px;">
                                <input type="radio" name="export_format" value="json">
                                <?php _e('JSON', 'fp-esperienze'); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-secondary">
                    <?php _e('Export Data', 'fp-esperienze'); ?>
                </button>
            </p>
        </form>
    </div>
</div>

<!-- Loading Overlay -->
<div id="fp-reports-loading" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
        <div class="spinner is-active" style="float: none;"></div>
        <p><?php _e('Loading report data...', 'fp-esperienze'); ?></p>
    </div>
</div>

<style>
.fp-kpi-value {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
    margin-top: 10px;
}

.fp-loading {
    color: #666;
    font-style: italic;
}

.chart-period.button-primary {
    background: #0073aa;
    border-color: #0073aa;
}

.fp-top-experience-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.fp-top-experience-item:last-child {
    border-bottom: none;
}

.fp-utm-conversion-item {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.fp-utm-conversion-item:first-child {
    font-weight: bold;
    background: #f9f9f9;
    padding: 10px;
    margin: 0 -10px 10px -10px;
}

.fp-load-factor-table {
    width: 100%;
    border-collapse: collapse;
}

.fp-load-factor-table th,
.fp-load-factor-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.fp-load-factor-table th {
    background: #f9f9f9;
    font-weight: bold;
}

.load-factor-bar {
    background: #e0e0e0;
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
}

.load-factor-fill {
    height: 100%;
    background: linear-gradient(to right, #4CAF50, #FF9800, #F44336);
    transition: width 0.3s ease;
}
</style>

<script>
// Chart.js will be loaded via the reports.js file
</script>