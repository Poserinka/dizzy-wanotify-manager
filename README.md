# Dizzy WAnotify Manager

WhatsApp Cloud API notifications for the Dizzy WordPress plugin suite.

## Version 1.0.0

The first working foundation includes:

- A standalone **WAnotify** WordPress admin menu.
- **Settings** for Phone Number ID, WhatsApp Business Account ID, access token, Graph API version, webhook verify token and default country code.
- A real WhatsApp Cloud API connection test.
- **Message Templates** for ticket purchases, reservation confirmations and shift reminders.
- Independent enable/disable controls for every notification.
- Meta template name, language, preview text and supported smart tags.
- A reusable WhatsApp template-message sender.
- Optional secure access token configuration through the `DIZZY_WANOTIFY_ACCESS_TOKEN` constant.
- GitHub Releases update integration.

## Secure token configuration

The preferred production setup is to add the permanent access token to `wp-config.php`:

```php
define('DIZZY_WANOTIFY_ACCESS_TOKEN', 'your-permanent-system-user-token');
```

When this constant exists, the token field in WordPress is disabled.

## Planned integration layer

- Dizzy Ticket Manager: send after confirmed payment.
- Dizzy Reservations Manager: send after reservation creation or confirmation.
- Dizzy Schedule Manager: queue a reminder two hours before a shift.

## Requirements

- WordPress 6.7+
- PHP 8.2+
- Meta WhatsApp Business Platform / Cloud API account
