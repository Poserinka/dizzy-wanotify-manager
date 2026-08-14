<?php

declare(strict_types=1);

namespace Dizzy\WAnotify;

defined('ABSPATH') || exit;

final class Settings
{
    public const CONNECTION_OPTION = 'dizzy_wanotify_settings';
    public const TEMPLATES_OPTION = 'dizzy_wanotify_templates';

    public function connection(): array
    {
        return wp_parse_args((array) get_option(self::CONNECTION_OPTION, []), [
            'phone_number_id' => '',
            'business_account_id' => '',
            'access_token' => '',
            'api_version' => 'v26.0',
            'webhook_verify_token' => '',
            'country_code' => '31',
        ]);
    }

    public function templates(): array
    {
        $saved = (array) get_option(self::TEMPLATES_OPTION, []);
        $defaults = [
            'ticket' => [
                'enabled' => '0',
                'template_name' => 'ticket_confirmed',
                'language' => 'en',
                'message' => 'Hello {customer_name}, your ticket for {event_name} is ready. Open ticket: {ticket_url}',
            ],
            'reservation' => [
                'enabled' => '0',
                'template_name' => 'reservation_confirmed',
                'language' => 'en',
                'message' => 'Hello {customer_name}, your reservation for {reservation_date} at {reservation_time} is confirmed for {guest_count} guests.',
            ],
            'schedule' => [
                'enabled' => '0',
                'template_name' => 'shift_reminder',
                'language' => 'en',
                'message' => 'Hello {employee_name}, your shift starts at {start_time} on {shift_date}. Position: {position}.',
            ],
        ];

        foreach ($defaults as $key => $default) {
            $saved[$key] = wp_parse_args((array) ($saved[$key] ?? []), $default);
        }

        return $saved;
    }

    public function accessToken(): string
    {
        if (defined('DIZZY_WANOTIFY_ACCESS_TOKEN') && is_string(DIZZY_WANOTIFY_ACCESS_TOKEN)) {
            return DIZZY_WANOTIFY_ACCESS_TOKEN;
        }

        return (string) $this->connection()['access_token'];
    }

    public function sanitizeConnection(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $current = $this->connection();
        $token = trim((string) ($input['access_token'] ?? ''));

        return [
            'phone_number_id' => preg_replace('/\D+/', '', (string) ($input['phone_number_id'] ?? '')),
            'business_account_id' => preg_replace('/\D+/', '', (string) ($input['business_account_id'] ?? '')),
            'access_token' => $token !== '' ? sanitize_text_field($token) : (string) $current['access_token'],
            'api_version' => preg_match('/^v\d+\.\d+$/', (string) ($input['api_version'] ?? '')) === 1
                ? sanitize_text_field((string) $input['api_version'])
                : 'v26.0',
            'webhook_verify_token' => sanitize_text_field((string) ($input['webhook_verify_token'] ?? '')),
            'country_code' => preg_replace('/\D+/', '', (string) ($input['country_code'] ?? '31')),
        ];
    }

    public function sanitizeTemplates(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $clean = [];

        foreach (array_keys($this->templates()) as $key) {
            $item = is_array($input[$key] ?? null) ? $input[$key] : [];
            $clean[$key] = [
                'enabled' => isset($item['enabled']) ? '1' : '0',
                'template_name' => sanitize_key((string) ($item['template_name'] ?? '')),
                'language' => sanitize_text_field((string) ($item['language'] ?? 'en')),
                'message' => sanitize_textarea_field((string) ($item['message'] ?? '')),
            ];
        }

        return $clean;
    }
}
