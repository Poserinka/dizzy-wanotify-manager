<?php

declare(strict_types=1);

namespace Dizzy\WAnotify;

use Throwable;
use WP_User;

defined('ABSPATH') || exit;

final class Integrations
{
    private const PHONE_META = 'dizzy_wanotify_phone';

    public function __construct(
        private Settings $settings,
        private WhatsAppClient $client
    ) {
    }

    public function register(): void
    {
        add_action('dizzy_ticket_purchased', [$this, 'ticketPurchased']);
        add_action('dizzy_reservation_created', [$this, 'reservationCreated']);
        add_action('dizzy_wanotify_check_shift_reminders', [$this, 'checkShiftReminders']);
        add_action('show_user_profile', [$this, 'profileField']);
        add_action('edit_user_profile', [$this, 'profileField']);
        add_action('personal_options_update', [$this, 'saveProfileField']);
        add_action('edit_user_profile_update', [$this, 'saveProfileField']);
    }

    public function ticketPurchased(array $payload): void
    {
        $template = $this->settings->templates()['ticket'];

        if ($template['enabled'] !== '1' || empty($payload['customer_phone'])) {
            return;
        }

        $ticket = is_array($payload['tickets'][0] ?? null) ? $payload['tickets'][0] : [];

        $this->send(
            (string) $payload['customer_phone'],
            $template,
            [
                (string) ($payload['customer_name'] ?? ''),
                (string) ($payload['event_name'] ?? ''),
                (string) ($payload['event_date'] ?? ''),
                (string) ($ticket['type'] ?? ''),
                (string) ($ticket['url'] ?? ''),
            ],
            'ticket:' . (string) ($payload['order_id'] ?? '')
        );
    }

    public function reservationCreated(array $payload): void
    {
        $template = $this->settings->templates()['reservation'];

        if ($template['enabled'] !== '1' || empty($payload['phone'])) {
            return;
        }

        $this->send(
            (string) $payload['phone'],
            $template,
            [
                (string) ($payload['name'] ?? ''),
                (string) ($payload['date'] ?? ''),
                (string) ($payload['time'] ?? ''),
                (string) ($payload['guests'] ?? ''),
            ],
            'reservation:' . (string) ($payload['reservation_id'] ?? '')
        );
    }

    public function checkShiftReminders(): void
    {
        $template = $this->settings->templates()['schedule'];

        if ($template['enabled'] !== '1') {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dizzy_schedule_shifts';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        $now = new \DateTimeImmutable('now', wp_timezone());
        $from = $now->modify('+115 minutes');
        $to = $now->modify('+125 minutes');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*,u.display_name
             FROM {$table} s
             INNER JOIN {$wpdb->users} u ON u.ID=s.employee_id
             WHERE s.status='published'
             AND TIMESTAMP(
                 DATE_ADD(s.shift_date, INTERVAL IF(s.start_time <= '02:00:00',1,0) DAY),
                 s.start_time
             ) BETWEEN %s AND %s",
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s')
        ), ARRAY_A) ?: [];

        foreach ($rows as $row) {
            $phone = (string) get_user_meta((int) $row['employee_id'], self::PHONE_META, true);
            $marker = 'dizzy_wanotify_shift_' . (int) $row['id'] . '_' . md5((string) $row['updated_at']);

            if ($phone === '' || get_option($marker, '') === 'sent') {
                continue;
            }

            if ($this->send(
                $phone,
                $template,
                [
                    (string) $row['display_name'],
                    wp_date('d/m/Y', strtotime((string) $row['shift_date']), wp_timezone()),
                    substr((string) $row['start_time'], 0, 5),
                    substr((string) $row['end_time'], 0, 5),
                    (string) $row['position'],
                ],
                'shift:' . (int) $row['id']
            )) {
                update_option($marker, 'sent', false);
            }
        }
    }

    public function profileField(WP_User $user): void
    {
        if (! current_user_can('edit_user', $user->ID)) {
            return;
        }
        ?>
        <h2><?php esc_html_e('WAnotify', 'dizzy-wanotify-manager'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="dizzy-wanotify-phone"><?php esc_html_e('WhatsApp phone', 'dizzy-wanotify-manager'); ?></label></th>
                <td>
                    <input type="tel" class="regular-text" id="dizzy-wanotify-phone" name="dizzy_wanotify_phone" value="<?php echo esc_attr((string) get_user_meta($user->ID, self::PHONE_META, true)); ?>">
                    <p class="description"><?php esc_html_e('Use an international number, for example +31612345678.', 'dizzy-wanotify-manager'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function saveProfileField(int $userId): void
    {
        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        update_user_meta(
            $userId,
            self::PHONE_META,
            sanitize_text_field(wp_unslash((string) ($_POST['dizzy_wanotify_phone'] ?? '')))
        );
    }

    private function send(string $phone, array $template, array $parameters, string $context): bool
    {
        try {
            $this->client->sendTemplate(
                $phone,
                (string) $template['template_name'],
                (string) $template['language'],
                $parameters
            );
            do_action('dizzy_wanotify_message_sent', $context, $phone);

            return true;
        } catch (Throwable $error) {
            do_action('dizzy_wanotify_message_failed', $context, $phone, $error->getMessage());
            error_log('WAnotify ' . $context . ': ' . $error->getMessage());

            return false;
        }
    }
}
