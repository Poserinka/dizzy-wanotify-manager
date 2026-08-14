# Dizzy WAnotify Manager

WhatsApp Cloud API notifications for the Dizzy WordPress plugin suite.

## Version 1.1.0

The first working foundation includes:

- A standalone **WAnotify** WordPress admin menu.
- **Settings** for Phone Number ID, WhatsApp Business Account ID, access token, Graph API version, webhook verify token and default country code.
- A real WhatsApp Cloud API connection test.
- **Message Templates** for ticket purchases, reservation confirmations and shift reminders.
- Independent enable/disable controls for every notification.
- Meta template name, language, preview text and supported smart tags.
- A reusable WhatsApp template-message sender.
- Automatic ticket notification hook after confirmed payment.
- Automatic reservation notification after a confirmed reservation is created.
- Five-minute shift reminder queue that sends approximately two hours before a shift.
- A WhatsApp phone field on WordPress user profiles.
- Duplicate shift reminder protection and automatic retry after failed sends.
- Optional secure access token configuration through the `DIZZY_WANOTIFY_ACCESS_TOKEN` constant.
- GitHub Releases update integration.

## Secure token configuration

The preferred production setup is to add the permanent access token to `wp-config.php`:

```php
define('DIZZY_WANOTIFY_ACCESS_TOKEN', 'your-permanent-system-user-token');
```

When this constant exists, the token field in WordPress is disabled.

## Integration requirements

- Dizzy Ticket Manager 1.7.3+
- Dizzy Reservations Manager 3.8.1+
- Dizzy Schedule Manager 2.2.0+

## Requirements

- WordPress 6.7+
- PHP 8.2+
- Meta WhatsApp Business Platform / Cloud API account
