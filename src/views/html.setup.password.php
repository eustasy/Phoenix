<?php

declare(strict_types=1);

////	view_setup_password_html
// The first-run "set admin password" gate, rendered in the shared
// auth chrome. A required password + confirmation, plus an OPTIONAL TOTP
// enrolment section shown only when a candidate secret is supplied (the
// authenticatron library is present). $writable=false swaps the form for a
// "config not writable" notice. Returns HTML string; the caller echoes it.

function view_setup_password_html(
    ?string $error = null,
    ?string $totp_secret = null,
    ?string $totp_qr = null,
    ?string $totp_url = null,
    bool $writable = true,
    string $version = '',
): string {
    require_once __DIR__.'/html.auth.layout.php';

    $error_html = '';
    if (! $writable) {
        $error_html = '<div class="alert alert-danger alert-center mt-0" role="alert"><span class="ph-ico" data-lucide="triangle-alert"></span><code>config/</code> is not writable. Make it writable to set a password.</div>';
    } elseif ($error !== null) {
        $error_html = '<div class="alert alert-danger alert-center mt-0" role="alert"><span class="ph-ico" data-lucide="circle-alert"></span>'.htmlspecialchars($error, ENT_QUOTES, 'UTF-8').'</div>';
    }

    ////	Optional second-factor section
    // Shown only when a candidate secret is available. The hidden totp_secret
    // field round-trips the displayed secret so a failed submit keeps the same
    // one (no re-scan). A blank code skips 2FA entirely.
    $totp_html = '';
    if ($totp_secret !== null) {
        if ($totp_qr !== null && $totp_qr !== '') {
            $totp_display = '<p><img src="'.htmlspecialchars($totp_qr, ENT_QUOTES, 'UTF-8').'" alt="Two-factor QR code" class="qr-img"></p>';
        } else {
            $totp_display = '<p class="muted text-sm">Scan is unavailable (no image support). Add this secret manually:</p>
				<p><code>'.htmlspecialchars($totp_secret, ENT_QUOTES, 'UTF-8').'</code></p>';
            if ($totp_url !== null) {
                $totp_display .= '<p><a href="'.htmlspecialchars($totp_url, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($totp_url, ENT_QUOTES, 'UTF-8').'</a></p>';
            }
        }
        $totp_html = '<fieldset class="setup-fieldset">
				<legend>Two-factor authentication <span class="muted">(optional)</span></legend>
				<p class="muted setup-note">Scan with an authenticator app, then enter a code to enable &mdash; or leave it blank to skip.</p>
				'.$totp_display.'
				<input type="hidden" name="totp_secret" value="'.htmlspecialchars($totp_secret, ENT_QUOTES, 'UTF-8').'">
				<div class="ph-field mb-0"><label for="setup-totp-code">Confirmation code</label>
					<input type="text" id="setup-totp-code" name="totp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" class="mono" placeholder="000000">
				</div>
			</fieldset>';
    }

    $body = '<div class="ph-form-card">
			<div class="alert alert-info alert-center mt-0"><span class="ph-ico" data-lucide="shield"></span>Set an admin password to protect this panel before you can use it.</div>
			'.$error_html.'
			<form method="POST" action="">
				<input type="hidden" name="process" value="setup_password">
				<div class="ph-field"><label for="setup-password">New password</label>
					<input type="password" id="setup-password" name="password" autocomplete="new-password" required minlength="12" maxlength="72" autofocus aria-describedby="setup-password-hint" data-pwned-check>
					<div class="ph-hint" id="setup-password-hint">At least 12 characters (max 72 bytes).</div>
				</div>
				<div class="ph-field"><label for="setup-confirm">Confirm password</label>
					<input type="password" id="setup-confirm" name="confirm" autocomplete="new-password" required minlength="12" maxlength="72">
				</div>
				'.$totp_html.'
				<button class="btn btn-primary btn-block mt-2"><span class="ph-ico" data-lucide="lock"></span>Set password</button>
			</form>
		</div>
		<script src="/assets/pwned-check.js"></script>';

    $version_html = $version !== ''
        ? ' <span class="mono">'.htmlspecialchars($version, ENT_QUOTES, 'UTF-8').'</span>'
        : '';
    $foot = 'Phoenix'.$version_html.' &middot; writes <code>config/phoenix.custom.php</code>';

    return view_auth_layout_html('Phoenix — Set admin password', 'Phoenix', 'Set admin password', $body, $foot);
}
