<?php

declare(strict_types=1);

namespace Dizzy\WAnotify;

use RuntimeException;

defined('ABSPATH') || exit;

final class WhatsAppClient
{
    public function __construct(private Settings $settings)
    {
    }

    public function testConnection(): array
    {
        $connection = $this->settings->connection();
        $phoneId = (string) $connection['phone_number_id'];

        if ($phoneId === '' || $this->settings->accessToken() === '') {
            throw new RuntimeException(__('Phone Number ID and Access Token are required.', 'dizzy-wanotify-manager'));
        }

        return $this->request('GET', '/' . $phoneId, [
            'fields' => 'id,display_phone_number,verified_name',
        ]);
    }

    public function sendTemplate(string $recipient, string $templateName, string $language, array $parameters = []): array
    {
        $connection = $this->settings->connection();
        $phoneId = (string) $connection['phone_number_id'];
        $recipient = $this->normalizePhone($recipient);

        if ($phoneId === '' || $recipient === '') {
            throw new RuntimeException(__('A valid sender and recipient phone number are required.', 'dizzy-wanotify-manager'));
        }

        $components = [];

        if ($parameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (mixed $value): array => ['type' => 'text', 'text' => (string) $value],
                    array_values($parameters)
                ),
            ];
        }

        return $this->request('POST', '/' . $phoneId . '/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $country = (string) $this->settings->connection()['country_code'];

        if ($digits !== '' && str_starts_with($digits, '0')) {
            $digits = $country . ltrim($digits, '0');
        }

        return $digits;
    }

    private function request(string $method, string $path, array $data): array
    {
        $connection = $this->settings->connection();
        $token = $this->settings->accessToken();

        if ($token === '') {
            throw new RuntimeException(__('WhatsApp Access Token is missing.', 'dizzy-wanotify-manager'));
        }

        $url = 'https://graph.facebook.com/' . rawurlencode((string) $connection['api_version']) . $path;
        $args = [
            'method' => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($method === 'GET') {
            $url = add_query_arg($data, $url);
        } else {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $body = is_array($body) ? $body : [];

        if (wp_remote_retrieve_response_code($response) >= 400 || isset($body['error'])) {
            throw new RuntimeException((string) ($body['error']['message'] ?? __('WhatsApp API request failed.', 'dizzy-wanotify-manager')));
        }

        return $body;
    }
}
