<?php
/**
 * Notifications settings tab service.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

class NotificationsSettingsService implements SettingsTabServiceInterface
{
    public function handle(array $data): SettingsUpdateResult
    {
        $notifications = [
            'staff_notifications_enabled' => (bool) rest_sanitize_boolean(wp_unslash($data['staff_notifications_enabled'] ?? false)),
            'staff_emails'                => sanitize_textarea_field(wp_unslash($data['staff_emails'] ?? '')),
            'ics_attachment_enabled'      => (bool) rest_sanitize_boolean(wp_unslash($data['ics_attachment_enabled'] ?? false)),
        ];

        $errors = [];

        if (!empty($notifications['staff_emails'])) {
            $lines = preg_split("/\r\n|\r|\n/", $notifications['staff_emails']);
            $validEmails = [];

            foreach ($lines as $email) {
                $email = trim($email);
                if ($email === '') {
                    continue;
                }

                if (is_email($email)) {
                    $validEmails[] = $email;
                } else {
                    $errors[] = sprintf(__('Invalid email address: %s', 'fp-esperienze'), $email);
                }
            }

            $notifications['staff_emails'] = implode("\n", $validEmails);
        }

        update_option('fp_esperienze_notifications', $notifications);

        return new SettingsUpdateResult(true, [], $errors);
    }
}
