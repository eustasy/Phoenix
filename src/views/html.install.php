<?php

declare(strict_types=1);

////	view_install_html
// Render the first-run installation form inside the shared auth chrome,
// grouped into Database / Tracker / Admin / (optional) Two-Factor fieldsets.
// When config/ is not writable the form is replaced by an instructional
// banner so credentials that can't be persisted are never collected.
// Returns HTML string. Caller is responsible for echo and exit.
//
// Parameters:
//   $settings_writable - bool, whether config/ directory is writable
//   $install_error - string|false, error message to display (escaped)
//   $form - array, form field values (db_host, db_user, db_name, db_prefix, db_persist, open_tracker, public_index)
//   $totp_secret - string|null, candidate base32 secret; null hides the whole 2FA section (library absent)
//   $totp_qr - string|null, complete data:image/png URI for the QR, or null when GD is unavailable
//   $totp_url - string|null, otpauth:// URL for manual entry

/**
 * @param array{db_host: string, db_user: string, db_name: string, db_prefix: string, db_persist: bool, open_tracker: bool, public_index: bool, stats_enabled: bool, stats_geo: bool, geo_available: bool} $form
 */
function view_install_html(
    bool $settings_writable,
    string|null $install_error,
    array $form,
    string|null $totp_secret = null,
    string|null $totp_qr = null,
    string|null $totp_url = null,
): string {
    require_once __DIR__.'/html.auth.layout.php';

    $extra_head = '
	<link rel="stylesheet" href="/assets/install.css">';

    $foot = 'Phoenix &middot; writes <code>config/phoenix.custom.php</code>';

    ////	Locked config directory — show the banner instead of the form.
    if (! $settings_writable) {
        $body = '<div class="ph-form-card">
			<div class="alert alert-danger m-0"><span class="ph-ico" data-lucide="triangle-alert"></span><div><code>config/</code> is not writable. Make it writable to proceed with installation.</div></div>
		</div>';

        return view_auth_layout_html('Phoenix Setup', 'Phoenix Setup', 'First-run installation', $body, $foot, true, 'ph-auth-install', $extra_head);
    }

    $error_html = '';
    if ($install_error) {
        $error_html = '<div class="alert alert-danger alert-center mt-0" role="alert"><span class="ph-ico" data-lucide="circle-alert"></span>'.htmlspecialchars($install_error).'</div>';
    }

    ////	Optional two-factor section
    // Rendered only when the controller passes a secret (i.e. the verification
    // library is installed). Shows the QR when GD produced one, otherwise the
    // secret + otpauth URL for manual entry. The hidden totp_secret field
    // round-trips the displayed secret so a failed code re-renders the same one.
    $totp_html = '';
    if ($totp_secret !== null) {
        if ($totp_qr !== null && $totp_qr !== '') {
            $totp_display = '<p><img src="'.htmlspecialchars($totp_qr).'" alt="Two-factor QR code" class="qr-img"></p>';
        } else {
            $totp_display = '<p class="muted text-sm">Scan is unavailable (no image support). Add this secret manually:</p>
				<p><code>'.htmlspecialchars($totp_secret).'</code></p>';
            if ($totp_url !== null) {
                $totp_display .= '<p><a href="'.htmlspecialchars($totp_url).'">'.htmlspecialchars($totp_url).'</a></p>';
            }
        }

        $totp_html = '
			<fieldset class="setup-fieldset m-0">
				<div class="setup-legend"><span class="ph-ico" data-lucide="shield-check"></span>Two-Factor Authentication <span class="dim setup-legend-opt">&mdash; optional</span></div>
				<p class="muted setup-note">Scan with an authenticator app, then enter a code to enable &mdash; or leave it blank to skip.</p>
				'.$totp_display.'
				<input type="hidden" name="totp_secret" value="'.htmlspecialchars($totp_secret).'">
				<div class="ph-field mb-0"><label for="install-totp-code">Confirmation code</label>
					<input type="text" id="install-totp-code" name="totp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" class="mono" placeholder="000000">
				</div>
			</fieldset>';
    }

    $checked = static fn (bool $on): string => $on ? ' checked' : '';

    // Geo enrichment is selectable only with both the geoip2 library and a
    // discoverable GeoLite2 database (the controller decides); otherwise the
    // checkbox is greyed out so the operator can't enable a no-op.
    $geo_available = $form['geo_available'];
    $geo_note = $geo_available
        ? '<span class="dim">&mdash; coarse country per event/peer; the IP is never stored</span>'
        : '<span class="dim">&mdash; needs the geoip2 library and a GeoLite2 database</span>';

    $body = $error_html.'
		<form method="POST" action="" class="ph-form-card">
			<input type="hidden" name="process" value="install">

			<div class="alert alert-info mt-0" role="note"><span class="ph-ico" data-lucide="shield-check"></span><div>Setup is protected. Phoenix wrote a one-time token to <code>config/.phoenix-setup-token</code> on the server &mdash; open that file and paste its contents below to confirm you control this machine.</div></div>
			<fieldset class="setup-fieldset">
				<div class="setup-legend"><span class="ph-ico" data-lucide="key-round"></span>Setup token</div>
				<div class="ph-field mb-0"><label for="setup_token">Token from <code>config/.phoenix-setup-token</code> <span class="text-danger" aria-hidden="true">*</span></label>
					<input type="text" id="setup_token" name="setup_token" class="mono" autocomplete="off" required>
				</div>
			</fieldset>

			<fieldset class="setup-fieldset">
				<div class="setup-legend"><span class="ph-ico" data-lucide="database"></span>Database</div>
				<div class="ph-field-row">
					<div class="ph-field"><label for="db_host">Host</label><input type="text" id="db_host" name="db_host" value="'.htmlspecialchars($form['db_host']).'"></div>
					<div class="ph-field"><label for="db_name">Database name</label><input type="text" id="db_name" name="db_name" value="'.htmlspecialchars($form['db_name']).'"></div>
				</div>
				<div class="ph-field-row">
					<div class="ph-field"><label for="db_user">Username</label><input type="text" id="db_user" name="db_user" value="'.htmlspecialchars($form['db_user']).'"></div>
					<div class="ph-field"><label for="db_pass">Password</label><input type="password" id="db_pass" name="db_pass"></div>
				</div>
				<div class="ph-field-row">
					<div class="ph-field"><label for="db_prefix">Table prefix</label><input type="text" id="db_prefix" name="db_prefix" value="'.htmlspecialchars($form['db_prefix']).'" class="mono"></div>
					<div class="ph-field setup-field-inline">
						<label class="checkbox"><input type="checkbox" name="db_persist" value="1"'.$checked($form['db_persist']).'><span class="checkbox-label">Persistent connections</span></label>
					</div>
				</div>
			</fieldset>

			<fieldset class="setup-fieldset">
				<div class="setup-legend"><span class="ph-ico" data-lucide="radio"></span>Tracker</div>
				<div class="flex flex-col gap-3">
					<label class="checkbox"><input type="checkbox" name="open_tracker" value="1"'.$checked($form['open_tracker']).'><span class="checkbox-label">Open tracker <span class="dim">&mdash; accept announces for any info hash</span></span></label>
					<label class="checkbox"><input type="checkbox" name="public_index" value="1"'.$checked($form['public_index']).'><span class="checkbox-label">Public index <span class="dim">&mdash; expose the public torrent listing</span></span></label>
				</div>
			</fieldset>

			<fieldset class="setup-fieldset">
				<div class="setup-legend"><span class="ph-ico" data-lucide="bar-chart-3"></span>Statistics <span class="dim setup-legend-opt">&mdash; optional</span></div>
				<div class="flex flex-col gap-3">
					<label class="checkbox"><input type="checkbox" name="stats_enabled" value="1"'.$checked($form['stats_enabled']).'><span class="checkbox-label">Event logging <span class="dim">&mdash; record completions to the events ledger</span></span></label>
					<label class="checkbox'.($geo_available ? '' : ' is-disabled').'"><input type="checkbox" name="stats_geo" value="1"'.$checked($form['stats_geo']).($geo_available ? '' : ' disabled').'><span class="checkbox-label">Geo enrichment '.$geo_note.'</span></label>
				</div>
			</fieldset>

			<fieldset class="setup-fieldset'.($totp_html === '' ? ' m-0' : '').'">
				<div class="setup-legend"><span class="ph-ico" data-lucide="key-round"></span>Admin</div>
				<div class="ph-field mb-0"><label for="admin_password">Admin password <span class="text-danger" aria-hidden="true">*</span></label>
					<input type="password" id="admin_password" name="admin_password" autocomplete="new-password" required aria-describedby="admin_password-hint" data-pwned-check>
					<div class="ph-hint" id="admin_password-hint">Required &mdash; protects the control panel.</div>
				</div>
			</fieldset>
			'.$totp_html.'

			<div class="ph-form-actions">
				<button class="btn btn-primary btn-lg"><span class="ph-ico" data-lucide="wand-2"></span>Install</button>
			</div>
		</form>
		<script src="/assets/pwned-check.js"></script>';

    return view_auth_layout_html('Phoenix Setup', 'Phoenix Setup', 'First-run installation', $body, $foot, true, 'ph-auth-install', $extra_head);
}
