<?php
/**
 * ICS endpoints partial.
 *
 * @package FP\Esperienze\Admin\Settings
 */
?>
<h3><?php _e('ICS Calendar Endpoints', 'fp-esperienze'); ?></h3>
<p><?php _e('The following REST API endpoints are available for calendar integration:', 'fp-esperienze'); ?></p>

<table class="form-table">
    <tr>
        <th scope="row"><?php _e('Product Calendar', 'fp-esperienze'); ?></th>
        <td>
            <code><?php echo esc_html(rest_url('fp-esperienze/v1/ics/product/{product_id}')); ?></code>
            <p class="description"><?php _e('Public endpoint to get calendar of available slots for a specific experience product.', 'fp-esperienze'); ?></p>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php _e('User Bookings Calendar', 'fp-esperienze'); ?></th>
        <td>
            <code><?php echo esc_html(rest_url('fp-esperienze/v1/ics/user/{user_id}')); ?></code>
            <p class="description"><?php _e('Private endpoint (requires authentication) to get calendar of user\'s confirmed bookings.', 'fp-esperienze'); ?></p>
        </td>
    </tr>

    <tr>
        <th scope="row"><?php _e('Single Booking Calendar', 'fp-esperienze'); ?></th>
        <td>
            <code><?php echo esc_html(rest_url('fp-esperienze/v1/ics/file/booking-{booking_id}-{product}.ics?token={token}')); ?></code>
            <p class="description"><?php _e('Token-protected endpoint that serves stored ICS files for individual bookings.', 'fp-esperienze'); ?></p>
        </td>
    </tr>
</table>
