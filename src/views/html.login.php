<?php

declare(strict_types=1);

////	view_login_html
// Render the admin login form inside the shared auth chrome. Shows a failed-
// login banner when $show_error is set, and a TOTP code field only when a
// second factor is enrolled ($totp_required). $version feeds the footer.
// Returns HTML string. Caller is responsible for echo and exit.

function view_login_html(bool $show_error = false, bool $totp_required = false, string $version = ''): string
{
    require_once __DIR__.'/html.auth.layout.php';

    $error_html = '';
    if ($show_error) {
        $error_html = '<div class="alert alert-danger alert-center mt-0" role="alert"><span class="ph-ico" data-lucide="circle-alert"></span>Incorrect password.</div>';
    }

    ////	Optional second-factor field
    $code_html = '';
    if ($totp_required) {
        $code_html = '<div class="ph-field">
				<label for="login-code">Authentication code</label>
				<input type="text" id="login-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" class="mono" placeholder="000000" aria-describedby="login-code-hint">
				<div class="ph-hint" id="login-code-hint">6-digit code from your authenticator app.</div>
			</div>';
    }

    $body = '<div class="ph-form-card">
			'.$error_html.'
			<form method="POST" action="">
				<input type="hidden" name="process" value="login">
				<div class="ph-field"><label for="login-password">Password</label>
					<input type="password" id="login-password" name="password" autocomplete="current-password" autofocus placeholder="••••••••">
				</div>
				'.$code_html.'
				<button class="btn btn-primary btn-block mt-2"><span class="ph-ico" data-lucide="log-in"></span>Log In</button>
			</form>
		</div>';

    $version_html = $version !== ''
        ? ' <span class="mono">'.htmlspecialchars($version, ENT_QUOTES, 'UTF-8').'</span>'
        : '';
    $foot = 'Phoenix'.$version_html.' &middot; eustasy';

    return view_auth_layout_html('Phoenix — Log in', 'Phoenix', 'Admin panel', $body, $foot);
}
