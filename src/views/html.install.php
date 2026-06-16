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
	<style>
		.setup-fieldset { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); margin-bottom: var(--space-4); }
		.setup-legend { font-size: var(--font-size-xs); text-transform: uppercase; letter-spacing: 0.07em; color: var(--color-text-tertiary); font-weight: 600; margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2); }
		.setup-legend .ph-ico { width: 15px; height: 15px; }
	</style>';

    $foot = 'Phoenix &middot; writes <code>config/phoenix.custom.php</code>';

    ////	Locked config directory — show the banner instead of the form.
    if (! $settings_writable) {
        $body = '<div class="ph-form-card">
			<div class="alert alert-danger" style="display:flex;gap:var(--space-2);align-items:flex-start;margin:0"><span class="ph-ico" data-lucide="triangle-alert" style="flex-shrink:0"></span><div><code>config/</code> is not writable. Make it writable to proceed with installation.</div></div>
		</div>';

        return view_auth_layout_html('Phoenix Setup', 'Phoenix Setup', 'First-run installation', $body, $foot, true, 'background:var(--color-bg-secondary);align-items:flex-start;padding-block:var(--space-10)', $extra_head);
    }

    $error_html = '';
    if ($install_error) {
        $error_html = '<div class="alert alert-danger" style="display:flex;gap:var(--space-2);align-items:center;margin-top:0"><span class="ph-ico" data-lucide="circle-alert"></span>'.htmlspecialchars($install_error).'</div>';
    }

    ////	Optional two-factor section
    // Rendered only when the controller passes a secret (i.e. the verification
    // library is installed). Shows the QR when GD produced one, otherwise the
    // secret + otpauth URL for manual entry. The hidden totp_secret field
    // round-trips the displayed secret so a failed code re-renders the same one.
    $totp_html = '';
    if ($totp_secret !== null) {
        if ($totp_qr !== null && $totp_qr !== '') {
            $totp_display = '<p><img src="'.htmlspecialchars($totp_qr).'" alt="Two-factor QR code" style="border:1px solid var(--color-border);border-radius:var(--radius-md)"></p>';
        } else {
            $totp_display = '<p class="muted" style="font-size:var(--font-size-sm)">Scan is unavailable (no image support). Add this secret manually:</p>
				<p><code>'.htmlspecialchars($totp_secret).'</code></p>';
            if ($totp_url !== null) {
                $totp_display .= '<p><a href="'.htmlspecialchars($totp_url).'">'.htmlspecialchars($totp_url).'</a></p>';
            }
        }

        $totp_html = '
			<fieldset class="setup-fieldset" style="margin:0">
				<div class="setup-legend"><span class="ph-ico" data-lucide="shield-check"></span>Two-Factor Authentication <span class="dim" style="text-transform:none;letter-spacing:0;font-weight:400">&mdash; optional</span></div>
				<p class="muted" style="margin:0 0 var(--space-3);font-size:var(--font-size-sm)">Scan with an authenticator app, then enter a code to enable &mdash; or leave it blank to skip.</p>
				'.$totp_display.'
				<input type="hidden" name="totp_secret" value="'.htmlspecialchars($totp_secret).'">
				<div class="ph-field" style="margin-bottom:0"><label>Confirmation code</label>
					<input type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" class="mono" placeholder="000000">
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

			<fieldset class="setup-fieldset">
				<div class="setup-legend"><span class="ph-ico" data-lucide="database"></span>Database</div>
				<div class="ph-field-row">
					<div class="ph-field"><label>Host</label><input type="text" name="db_host" value="'.htmlspecialchars($form['db_host']).'"></div>
					<div class="ph-field"><label>Database name</label><input type="text" name="db_name" value="'.htmlspecialchars($form['db_name']).'"></div>
				</div>
				<div class="ph-field-row">
					<div class="ph-field"><label>Username</label><input type="text" name="db_user" value="'.htmlspecialchars($form['db_user']).'"></div>
					<div class="ph-field"><label>Password</label><input type="password" name="db_pass"></div>
				</div>
				<div class="ph-field-row">
					<div class="ph-field"><label>Table prefix</label><input type="text" name="db_prefix" value="'.htmlspecialchars($form['db_prefix']).'" class="mono"></div>
					<div class="ph-field" style="display:flex;align-items:flex-end;padding-bottom:var(--space-2)">
						<label class="checkbox"><input type="checkbox" name="db_persist" value="1"'.$checked($form['db_persist']).'><span class="checkbox-label">Persistent connections</span></label>
					</div>
				</div>
			</fieldset>

			<fieldset class="setup-fieldset">
				<div class="setup-legend"><span class="ph-ico" data-lucide="radio"></span>Tracker</div>
				<div style="display:flex;flex-direction:column;gap:var(--space-3)">
					<label class="checkbox"><input type="checkbox" name="open_tracker" value="1"'.$checked($form['open_tracker']).'><span class="checkbox-label">Open tracker <span class="dim">&mdash; accept announces for any info hash</span></span></label>
					<label class="checkbox"><input type="checkbox" name="public_index" value="1"'.$checked($form['public_index']).'><span class="checkbox-label">Public index <span class="dim">&mdash; expose the public torrent listing</span></span></label>
				</div>
			</fieldset>

			<fieldset class="setup-fieldset">
				<div class="setup-legend"><span class="ph-ico" data-lucide="bar-chart-3"></span>Statistics <span class="dim" style="text-transform:none;letter-spacing:0;font-weight:400">&mdash; optional</span></div>
				<div style="display:flex;flex-direction:column;gap:var(--space-3)">
					<label class="checkbox"><input type="checkbox" name="stats_enabled" value="1"'.$checked($form['stats_enabled']).'><span class="checkbox-label">Event logging <span class="dim">&mdash; record completions to the events ledger</span></span></label>
					<label class="checkbox"'.($geo_available ? '' : ' style="opacity:.55"').'><input type="checkbox" name="stats_geo" value="1"'.$checked($form['stats_geo']).($geo_available ? '' : ' disabled').'><span class="checkbox-label">Geo enrichment '.$geo_note.'</span></label>
				</div>
			</fieldset>

			<fieldset class="setup-fieldset"'.($totp_html === '' ? ' style="margin:0"' : '').'>
				<div class="setup-legend"><span class="ph-ico" data-lucide="key-round"></span>Admin</div>
				<div class="ph-field" style="margin-bottom:0"><label>Admin password <span style="color:var(--color-danger)">*</span></label>
					<input type="password" name="admin_password" required>
					<div class="ph-hint">Required &mdash; protects the control panel.</div>
				</div>
			</fieldset>
			'.$totp_html.'

			<div class="ph-form-actions">
				<button class="btn btn-primary btn-lg"><span class="ph-ico" data-lucide="wand-2"></span>Install</button>
			</div>
		</form>';

    return view_auth_layout_html('Phoenix Setup', 'Phoenix Setup', 'First-run installation', $body, $foot, true, 'background:var(--color-bg-secondary);align-items:flex-start;padding-block:var(--space-10)', $extra_head);
}
