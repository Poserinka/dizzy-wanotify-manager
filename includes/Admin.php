<?php

declare(strict_types=1);

namespace Dizzy\WAnotify;

use Throwable;

defined('ABSPATH') || exit;

final class Admin
{
    private const SETTINGS_SLUG = 'dizzy-wanotify-settings';
    private const TEMPLATES_SLUG = 'dizzy-wanotify-templates';
    private array $hooks = [];

    public function __construct(
        private Settings $settings,
        private WhatsAppClient $client
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_dizzy_wanotify_test_connection', [$this, 'testConnection']);
    }

    public function menu(): void
    {
        $this->hooks[] = (string) add_menu_page(
            __('WAnotify', 'dizzy-wanotify-manager'),
            __('WAnotify', 'dizzy-wanotify-manager'),
            'manage_options',
            self::SETTINGS_SLUG,
            [$this, 'renderSettings'],
            'dashicons-whatsapp',
            27
        );

        $this->hooks[] = (string) add_submenu_page(
            self::SETTINGS_SLUG,
            __('WAnotify Settings', 'dizzy-wanotify-manager'),
            __('Settings', 'dizzy-wanotify-manager'),
            'manage_options',
            self::SETTINGS_SLUG,
            [$this, 'renderSettings']
        );

        $this->hooks[] = (string) add_submenu_page(
            self::SETTINGS_SLUG,
            __('Message Templates', 'dizzy-wanotify-manager'),
            __('Message Templates', 'dizzy-wanotify-manager'),
            'manage_options',
            self::TEMPLATES_SLUG,
            [$this, 'renderTemplates']
        );
    }

    public function registerSettings(): void
    {
        register_setting('dizzy_wanotify_connection', Settings::CONNECTION_OPTION, [
            'sanitize_callback' => [$this->settings, 'sanitizeConnection'],
        ]);
        register_setting('dizzy_wanotify_templates', Settings::TEMPLATES_OPTION, [
            'sanitize_callback' => [$this->settings, 'sanitizeTemplates'],
        ]);
    }

    public function assets(string $hook): void
    {
        if (! in_array($hook, $this->hooks, true)) {
            return;
        }

        wp_enqueue_style('dizzy-wanotify-admin', DIZZY_WANOTIFY_URL . 'assets/admin.css', [], DIZZY_WANOTIFY_VERSION);
        wp_enqueue_script('dizzy-wanotify-admin', DIZZY_WANOTIFY_URL . 'assets/admin.js', [], DIZZY_WANOTIFY_VERSION, true);
        wp_localize_script('dizzy-wanotify-admin', 'dizzyWAnotify', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dizzy_wanotify_test'),
            'testing' => __('Testing connection…', 'dizzy-wanotify-manager'),
            'failed' => __('Connection test failed.', 'dizzy-wanotify-manager'),
        ]);
    }

    public function renderSettings(): void
    {
        $settings = $this->settings->connection();
        ?>
        <div class="wrap dizzy-wa-wrap">
            <div class="dizzy-wa-heading"><h1><?php esc_html_e('WhatsApp Settings', 'dizzy-wanotify-manager'); ?></h1><p><?php esc_html_e('Connect WAnotify to the WhatsApp Cloud API.', 'dizzy-wanotify-manager'); ?></p></div>
            <?php settings_errors(); ?>
            <form method="post" action="options.php" class="dizzy-wa-panel">
                <?php settings_fields('dizzy_wanotify_connection'); ?>
                <div class="dizzy-wa-grid">
                    <?php $this->input('phone_number_id', __('Phone Number ID', 'dizzy-wanotify-manager'), $settings); ?>
                    <?php $this->input('business_account_id', __('WhatsApp Business Account ID', 'dizzy-wanotify-manager'), $settings); ?>
                    <?php $this->input('access_token', __('Access Token', 'dizzy-wanotify-manager'), $settings, 'password', defined('DIZZY_WANOTIFY_ACCESS_TOKEN')); ?>
                    <?php $this->input('api_version', __('Graph API Version', 'dizzy-wanotify-manager'), $settings); ?>
                    <?php $this->input('webhook_verify_token', __('Webhook Verify Token', 'dizzy-wanotify-manager'), $settings); ?>
                    <?php $this->input('country_code', __('Default country code', 'dizzy-wanotify-manager'), $settings); ?>
                </div>
                <div class="dizzy-wa-actions"><button type="button" class="button" data-test-connection><?php esc_html_e('Test connection', 'dizzy-wanotify-manager'); ?></button><?php submit_button(__('Save Settings', 'dizzy-wanotify-manager'), 'primary', 'submit', false); ?></div>
                <div class="dizzy-wa-test-result" data-test-result role="status" aria-live="polite"></div>
            </form>
        </div>
        <?php
    }

    public function renderTemplates(): void
    {
        $templates = $this->settings->templates();
        $definitions = [
            'ticket' => [__('Ticket purchased', 'dizzy-wanotify-manager'), __('Sent after payment is confirmed.', 'dizzy-wanotify-manager'), '{customer_name}, {event_name}, {event_date}, {ticket_type}, {ticket_url}'],
            'reservation' => [__('Reservation confirmed', 'dizzy-wanotify-manager'), __('Sent when a reservation is created or confirmed.', 'dizzy-wanotify-manager'), '{customer_name}, {reservation_date}, {reservation_time}, {guest_count}'],
            'schedule' => [__('Shift reminder', 'dizzy-wanotify-manager'), __('Sent two hours before the employee shift.', 'dizzy-wanotify-manager'), '{employee_name}, {shift_date}, {start_time}, {end_time}, {position}'],
        ];
        ?>
        <div class="wrap dizzy-wa-wrap">
            <div class="dizzy-wa-heading"><h1><?php esc_html_e('Message Templates', 'dizzy-wanotify-manager'); ?></h1><p><?php esc_html_e('Enable notifications and map them to approved Meta templates.', 'dizzy-wanotify-manager'); ?></p></div>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('dizzy_wanotify_templates'); ?>
                <?php foreach ($definitions as $key => [$title, $description, $tags]) : $item = $templates[$key]; ?>
                    <section class="dizzy-wa-panel">
                        <div class="dizzy-wa-template-head"><div><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($description); ?></p></div><label><input type="checkbox" name="<?php echo esc_attr(Settings::TEMPLATES_OPTION . '[' . $key . '][enabled]'); ?>" value="1" <?php checked($item['enabled'], '1'); ?>> <?php esc_html_e('Enabled', 'dizzy-wanotify-manager'); ?></label></div>
                        <div class="dizzy-wa-grid">
                            <label><?php esc_html_e('Meta template name', 'dizzy-wanotify-manager'); ?><input type="text" name="<?php echo esc_attr(Settings::TEMPLATES_OPTION . '[' . $key . '][template_name]'); ?>" value="<?php echo esc_attr($item['template_name']); ?>"></label>
                            <label><?php esc_html_e('Language', 'dizzy-wanotify-manager'); ?><input type="text" name="<?php echo esc_attr(Settings::TEMPLATES_OPTION . '[' . $key . '][language]'); ?>" value="<?php echo esc_attr($item['language']); ?>"></label>
                        </div>
                        <label class="dizzy-wa-message"><?php esc_html_e('Message preview', 'dizzy-wanotify-manager'); ?><textarea name="<?php echo esc_attr(Settings::TEMPLATES_OPTION . '[' . $key . '][message]'); ?>" rows="5"><?php echo esc_textarea($item['message']); ?></textarea></label>
                        <p class="description"><?php esc_html_e('Available tags:', 'dizzy-wanotify-manager'); ?> <?php echo esc_html($tags); ?></p>
                    </section>
                <?php endforeach; ?>
                <?php submit_button(__('Save Templates', 'dizzy-wanotify-manager')); ?>
            </form>
        </div>
        <?php
    }

    public function testConnection(): void
    {
        check_ajax_referer('dizzy_wanotify_test', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Access denied.', 'dizzy-wanotify-manager')], 403);
        }

        try {
            $result = $this->client->testConnection();
            wp_send_json_success([
                'message' => sprintf(
                    __('Connected: %s (%s)', 'dizzy-wanotify-manager'),
                    (string) ($result['verified_name'] ?? __('WhatsApp account', 'dizzy-wanotify-manager')),
                    (string) ($result['display_phone_number'] ?? '')
                ),
            ]);
        } catch (Throwable $error) {
            wp_send_json_error(['message' => $error->getMessage()], 400);
        }
    }

    private function input(string $key, string $label, array $settings, string $type = 'text', bool $disabled = false): void
    {
        $name = Settings::CONNECTION_OPTION . '[' . $key . ']';
        ?>
        <label><?php echo esc_html($label); ?><input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo $type === 'password' ? '' : esc_attr((string) $settings[$key]); ?>" <?php disabled($disabled); ?> autocomplete="off"><?php if ($disabled) : ?><small><?php esc_html_e('Configured in wp-config.php.', 'dizzy-wanotify-manager'); ?></small><?php endif; ?></label>
        <?php
    }
}
