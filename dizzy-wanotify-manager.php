<?php
/**
 * Plugin Name: Dizzy WAnotify Manager
 * Plugin URI: https://github.com/Poserinka/dizzy-wanotify-manager
 * Description: WhatsApp notifications for Dizzy tickets, reservations and employee schedules.
 * Version: 1.0.0
 * Author: Poserinka Design
 * Text Domain: dizzy-wanotify-manager
 * Requires PHP: 8.2
 * Update URI: https://github.com/Poserinka/dizzy-wanotify-manager
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('DIZZY_WANOTIFY_VERSION', '1.0.0');
define('DIZZY_WANOTIFY_FILE', __FILE__);
define('DIZZY_WANOTIFY_PATH', plugin_dir_path(__FILE__));
define('DIZZY_WANOTIFY_URL', plugin_dir_url(__FILE__));

require_once DIZZY_WANOTIFY_PATH . 'includes/Settings.php';
require_once DIZZY_WANOTIFY_PATH . 'includes/WhatsAppClient.php';
require_once DIZZY_WANOTIFY_PATH . 'includes/Admin.php';
require_once DIZZY_WANOTIFY_PATH . 'includes/GitHubUpdater.php';

add_action('init', static function (): void {
    load_plugin_textdomain(
        'dizzy-wanotify-manager',
        false,
        dirname(plugin_basename(DIZZY_WANOTIFY_FILE)) . '/languages'
    );
}, 5);

add_action('plugins_loaded', static function (): void {
    $settings = new \Dizzy\WAnotify\Settings();
    $client = new \Dizzy\WAnotify\WhatsAppClient($settings);

    if (is_admin()) {
        (new \Dizzy\WAnotify\Admin($settings, $client))->register();
    }
});

(new \Dizzy\WAnotify\GitHubUpdater(
    __FILE__,
    'dizzy-wanotify-manager',
    'Poserinka/dizzy-wanotify-manager',
    DIZZY_WANOTIFY_VERSION
))->register();
