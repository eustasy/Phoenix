<?php

declare(strict_types=1);

////	view_admin_settings_html
// Render the admin Settings page: a read-only table of the effective settings
// (with db_pass, admin_password, admin_totp_secret, and api_keys values masked
// — never leak secrets into the panel), plus, when config/ is writable, a
// password-change form, a two-factor enable/disable form (when the verification
// library is present), and the flag toggles. When not writable, the forms are
// replaced by a note. Wrapped in the shared admin layout (narrow). Returns HTML
// string. $totp_secret/$totp_qr/$totp_url carry the enrolment QR for the enable
// form and are unused once a secret is enrolled.

/**
 * @param PhoenixSettings $settings
 */
function view_admin_settings_html(array $settings, bool $writable, string|false $message, string $csrf_token, bool $totp_available = false, ?string $totp_secret = null, ?string $totp_qr = null, ?string $totp_url = null, bool $geo_available = false): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $body = '';

    if ($message) {
        $body .= '<div class="alert alert-info" role="status"><span class="ph-ico" data-lucide="info"></span><div>'.htmlspecialchars($message).'</div></div>';
    }

    ////	Effective settings (read-only, secrets masked)
    $rows = '';
    foreach ($settings as $key => $value) {
        if ($key === 'db_pass' || $key === 'admin_password' || $key === 'admin_totp_secret') {
            $display = empty($value) ? '<span class="dim">(not set)</span>' : '<span class="mono">********</span>';
        } elseif ($key === 'api_keys') {
            $count = count($value);
            $display = $count.' key'.($count === 1 ? '' : 's').' configured <span class="dim">(values hidden)</span>';
        } elseif (is_bool($value)) {
            $display = $value ? '<span class="badge badge-green">true</span>' : '<span class="badge">false</span>';
        } elseif (is_array($value)) {
            $display = $value === []
                ? '<span class="dim">(empty)</span>'
                : htmlspecialchars(implode(', ', array_map('strval', $value)));
        } else {
            $display = '<span class="muted mono">'.htmlspecialchars((string) $value).'</span>';
        }
        $rows .= '<tr><td>'.htmlspecialchars((string) $key).'</td><td>'.$display.'</td></tr>';
    }
    $body .= '<div class="ph-section-head"><h3 class="mt-0">Effective settings</h3></div>'.
        '<div class="ph-card-table kv"><table><tbody>'.$rows.'</tbody></table></div>';

    if (! $writable) {
        $body .= '<div class="alert alert-warning"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The <code>config/</code> directory is not writable, so settings cannot be changed here. This is often intentional &mdash; it holds the database credentials and is kept out of the document root. Edit <code>config/phoenix.custom.php</code> directly, or make the directory writable to enable editing.</div></div>';

        return view_admin_layout_html($settings, 'Settings', $body, 'settings', $csrf_token, 'Server', '', true);
    }

    ////	Change admin password
    $body .= '<div class="ph-section-head"><h3>Change password</h3></div>
		<div class="ph-form-card">
			<form class="mysql" method="POST">
				<input type="hidden" name="process" value="password">'.$csrf_field.'
				<div class="ph-field mb-4"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" autocomplete="new-password"></div>
				<button type="submit" name="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="key-round"></span>Change Password</button>
			</form>
		</div>';

    ////	Two-factor authentication
    // Only when the verification library is installed. When a secret is
    // enrolled, offer to turn it off (a current code is required, so a hijacked
    // session can't silently disable it). Otherwise offer enrolment.
    if ($totp_available) {
        $body .= '<div class="ph-section-head"><h3>Two-Factor Authentication</h3></div>';

        if (! empty($settings['admin_totp_secret'])) {
            $body .= '<div class="ph-form-card">
				<div class="flex items-center gap-3 mb-4"><span class="ph-ico ph-2fa-ico" data-lucide="shield-check"></span><div><strong>Two-factor authentication is enabled.</strong><div class="dim text-sm">A 6-digit code is required at login.</div></div></div>
				<form class="mysql" method="POST">
					<input type="hidden" name="process" value="totp_disable">'.$csrf_field.'
					<div class="ph-field mb-4"><label for="totp_disable_code">Enter a current authenticator code to turn it off</label><input type="text" id="totp_disable_code" name="totp_code" inputmode="numeric" autocomplete="off" class="mono" placeholder="000000"></div>
					<button type="submit" name="submit" class="btn btn-outline btn-sm btn-outline-danger">Disable 2FA</button>
				</form>
			</div>';
        } else {
            $qr_html = '';
            if ($totp_qr !== null && $totp_qr !== '') {
                $qr_html = '<p><img src="'.htmlspecialchars($totp_qr).'" alt="Two-factor QR code" class="qr-img"></p>';
            }
            $body .= '<div class="ph-form-card">
				<p class="muted mt-0 text-sm">Scan this with an authenticator app (or enter the secret manually), then enter a code to enable it.</p>
				'.$qr_html.'
				<p>Secret: <code>'.htmlspecialchars((string) $totp_secret).'</code></p>
				<form class="mysql" method="POST">
					<input type="hidden" name="process" value="totp_enable">
					<input type="hidden" name="totp_secret" value="'.htmlspecialchars((string) $totp_secret).'">'.$csrf_field.'
					<div class="ph-field mb-4"><label for="totp_enable_code">Authenticator code</label><input type="text" id="totp_enable_code" name="totp_code" inputmode="numeric" autocomplete="off" class="mono" placeholder="000000"></div>
					<button type="submit" name="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="shield-check"></span>Enable 2FA</button>
				</form>
			</div>';
        }
    }

    ////	Toggle flags
    $switch = static function (array $settings, string $flag, string $label, string $note = '', bool $disabled = false): string {
        $checked = ! empty($settings[$flag]) ? ' checked' : '';
        $attrs = $disabled ? ' disabled' : '';
        $label_class = $disabled ? ' is-disabled' : '';

        return '<label class="switch'.$label_class.'"><input type="checkbox" name="'.$flag.'" value="1" role="switch"'.$checked.$attrs.'><span class="switch-track" aria-hidden="true"><span class="switch-thumb"></span></span><span class="switch-label">'.htmlspecialchars($label).$note.'</span></label>';
    };

    $full_scrape_note = ' <span class="text-warning">&mdash; warning: on a closed tracker this exposes every tracked info_hash to anyone who scrapes.</span>';
    $geo_note = $geo_available
        ? ' <span class="dim">&mdash; tag events &amp; map peers by country (coarse; the IP is never stored)</span>'
        : ' <span class="dim">&mdash; needs the geoip2 library and a GeoLite2 database</span>';

    $body .= '<div class="ph-section-head"><h3>Flags</h3></div>
		<div class="ph-form-card">
			<form class="mysql" method="POST">
				<input type="hidden" name="process" value="settings">'.$csrf_field.'
				<div class="flex flex-col gap-4">'.
                    $switch($settings, 'open_tracker', 'open_tracker', ' <span class="dim">&mdash; accept announces for any info hash</span>').
                    $switch($settings, 'public_index', 'public_index', ' <span class="dim">&mdash; expose the public torrent listing</span>').
                    $switch($settings, 'full_scrape', 'full_scrape', $full_scrape_note).
                    $switch($settings, 'stats_enabled', 'stats_enabled', ' <span class="dim">&mdash; log torrent events to the events ledger</span>').
                    $switch($settings, 'stats_geo', 'stats_geo', $geo_note, ! $geo_available).
                    $switch($settings, 'db_reset', 'db_reset', ' <span class="dim">&mdash; permit setup/reset from Utilities</span>').
                '</div>
				<div class="ph-form-actions"><button type="submit" name="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="save"></span>Save Flags</button></div>
			</form>
		</div>';

    return view_admin_layout_html($settings, 'Settings', $body, 'settings', $csrf_token, 'Server', '', true);
}
