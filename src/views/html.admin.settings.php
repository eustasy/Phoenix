<?php

declare(strict_types=1);

////	view_admin_settings_html
// Render the admin Settings page: a read-only table of the effective settings
// (with db_pass, admin_password, admin_totp_secret, and api_keys values masked
// — never leak secrets into the panel), plus, when config/ is writable, a
// password-change form, a two-factor enable/disable form (when the verification
// library is present), and the flag toggles. When not writable, the forms are
// replaced by a note explaining that config/ is intentionally often not
// web-writable. Wrapped in the shared admin layout (wide variant). Returns HTML
// string. $totp_secret/$totp_qr/$totp_url carry the enrolment QR for the enable
// form and are unused once a secret is enrolled.

/**
 * @param PhoenixSettings $settings
 */
function view_admin_settings_html(array $settings, bool $writable, string|false $message, string $csrf_token, bool $totp_available = false, ?string $totp_secret = null, ?string $totp_qr = null, ?string $totp_url = null): string
{
    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $body = '<h1>Settings</h1>';

    if ($message) {
        $body .= '<div class="box background-wisteria color-clouds"><h3>'.htmlspecialchars($message).'</h3></div>';
    }

    ////	Effective settings (read-only, secrets masked)
    $rows = '';
    foreach ($settings as $key => $value) {
        if ($key === 'db_pass' || $key === 'admin_password' || $key === 'admin_totp_secret') {
            $display = empty($value) ? '<em>(not set)</em>' : '********';
        } elseif ($key === 'api_keys') {
            $count = count($value);
            $display = $count.' key'.($count === 1 ? '' : 's').' configured <em>(values hidden)</em>';
        } elseif (is_bool($value)) {
            $display = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $display = $value === []
                ? '<em>(empty)</em>'
                : htmlspecialchars(implode(', ', array_map('strval', $value)));
        } else {
            $display = htmlspecialchars((string) $value);
        }
        $rows .= '<tr><td><code>'.htmlspecialchars((string) $key).'</code></td><td>'.$display.'</td></tr>';
    }
    $body .= '<table class="data-table"><thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>'.$rows.'</tbody></table>';

    if (! $writable) {
        $body .= '<p class="box background-pomegranate color-clouds">The <code>config/</code> directory is not '.
            'writable, so settings cannot be changed here. This is often intentional — it holds the database '.
            'credentials and is kept out of the document root. Edit <code>config/phoenix.custom.php</code> '.
            'directly, or make the directory writable to enable editing.</p>';

        require_once __DIR__.'/html.admin.layout.php';

        return view_admin_layout_html($settings, 'Settings', $body, 'settings', $csrf_token, true);
    }

    ////	Change admin password
    $body .= '<br><h2>Change Admin Password</h2>
		<form class="mysql" method="POST">
			<input type="hidden" name="process" value="password">'.$csrf_field.'
			<input type="password" name="new_password" placeholder="New password" autocomplete="new-password">
			<input class="button background-belize-hole color-clouds" type="submit" name="submit" value="Change Password">
		</form>';

    ////	Two-factor authentication
    // Only when the verification library is installed. When a secret is
    // enrolled, offer to turn it off (a current code is required, so a hijacked
    // session can't silently disable it). Otherwise offer enrolment: scan the
    // QR (or enter the secret), then prove a code to enable.
    if ($totp_available) {
        $body .= '<br><h2>Two-Factor Authentication</h2>';

        if (! empty($settings['admin_totp_secret'])) {
            $body .= '<p class="box background-green-sea color-clouds">Two-factor authentication is enabled.</p>
		<form class="mysql" method="POST">
			<input type="hidden" name="process" value="totp_disable">'.$csrf_field.'
			<p class="text-left">Enter a current authenticator code to turn it off.</p>
			<input type="text" name="totp_code" inputmode="numeric" autocomplete="off" placeholder="Authenticator code">
			<input class="button background-pomegranate color-clouds" type="submit" name="submit" value="Disable 2FA">
		</form>';
        } else {
            $qr_html = '';
            if ($totp_qr !== null && $totp_qr !== '') {
                $qr_html = '<p><img src="'.htmlspecialchars($totp_qr).'" alt="Two-factor QR code"></p>';
            }
            $body .= '<p class="text-left">Scan this with an authenticator app (or enter the secret manually), then enter a code to enable it.</p>
		'.$qr_html.'
		<p class="text-left">Secret: <code>'.htmlspecialchars((string) $totp_secret).'</code></p>
		<form class="mysql" method="POST">
			<input type="hidden" name="process" value="totp_enable">
			<input type="hidden" name="totp_secret" value="'.htmlspecialchars((string) $totp_secret).'">'.$csrf_field.'
			<input type="text" name="totp_code" inputmode="numeric" autocomplete="off" placeholder="Authenticator code">
			<input class="button background-belize-hole color-clouds" type="submit" name="submit" value="Enable 2FA">
		</form>';
        }
    }

    ////	Toggle flags
    $checkbox = static function (array $settings, string $flag, string $label, string $note = ''): string {
        $checked = ! empty($settings[$flag]) ? ' checked' : '';

        return '<p class="text-left"><label><input type="checkbox" name="'.$flag.'" value="1"'.$checked.'> '.
            htmlspecialchars($label).'</label>'.$note.'</p>';
    };

    $full_scrape_note = ' <span class="color-pomegranate">— warning: on a closed tracker this exposes every '.
        'tracked info_hash to anyone who scrapes.</span>';

    $body .= '<br><h2>Flags</h2>
		<form class="mysql" method="POST">
			<input type="hidden" name="process" value="settings">'.$csrf_field.
            $checkbox($settings, 'open_tracker', 'Open tracker (accept any info_hash)').
            $checkbox($settings, 'public_index', 'Public torrent index').
            $checkbox($settings, 'full_scrape', 'Allow full scrape', $full_scrape_note).
            $checkbox($settings, 'db_reset', 'Allow database reset / setup').
            '<input class="button background-belize-hole color-clouds" type="submit" name="submit" value="Save Flags">
		</form>';

    require_once __DIR__.'/html.admin.layout.php';

    return view_admin_layout_html($settings, 'Settings', $body, 'settings', $csrf_token, true);
}
