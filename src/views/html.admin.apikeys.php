<?php

declare(strict_types=1);

////	view_admin_apikeys_html
// The admin API Keys page: create a key (user -> a one-time-shown key whose
// SHA-256 hash is stored), list existing keys by user with a short hash
// fingerprint, and revoke. $new_key, when non-null, is the freshly generated
// plaintext, shown once. $writable=false swaps the create/revoke controls for a
// read-only notice. Returns the full page HTML.

/** @param PhoenixSettings $settings */
function view_admin_apikeys_html(array $settings, bool $writable, string|false $message, string $csrf_token, ?string $new_key = null): string
{
    require_once __DIR__.'/html.admin.layout.php';

    $csrf_field = '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

    $message_html = '';
    if ($message !== false) {
        $message_html = '<div class="alert alert-info" role="status"><span class="ph-ico" data-lucide="info"></span>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</div>';
    }

    $new_key_html = '';
    if ($new_key !== null) {
        $new_key_html = '<div class="alert alert-success" role="status"><span class="ph-ico" data-lucide="key-round"></span><div style="width:100%">
				<strong>Copy this key now &mdash; it is not stored and will not be shown again.</strong>
				<input type="text" class="mono mt-2" readonly autofocus value="'.htmlspecialchars($new_key, ENT_QUOTES, 'UTF-8').'" style="width:100%">
			</div></div>';
    }

    ////	Create control (or a read-only notice when config/ can't be written)
    if ($writable) {
        $create_html = '<form method="POST">'.$csrf_field.'
				<input type="hidden" name="process" value="apikey_create">
				<div class="ph-field"><label for="api_user">User</label>
					<input type="text" id="api_user" name="api_user" placeholder="alice or *" maxlength="64" pattern="[a-z0-9._*-]+" required>
					<div class="ph-hint">Lowercase letters, digits, <code>. _ -</code>, or <code>*</code> for the admin key. Reusing a name rotates that user&rsquo;s key.</div>
				</div>
				<button type="submit" class="btn btn-primary"><span class="ph-ico" data-lucide="plus"></span>Generate key</button>
			</form>';
    } else {
        $create_html = '<div class="alert alert-warning"><span class="ph-ico" data-lucide="triangle-alert"></span><div>The <code>config/</code> directory is not writable, so keys cannot be created or revoked here. Edit <code>config/phoenix.custom.php</code> directly (each value is the SHA-256 hash of the key), or make the directory writable.</div></div>';
    }

    ////	Existing keys
    $keys = $settings['api_keys'];
    if ($keys === []) {
        $list_html = '<p class="muted">No API keys yet &mdash; the management API is disabled until you create one.</p>';
    } else {
        $rows = '';
        foreach ($keys as $user => $hash) {
            $user_disp = htmlspecialchars((string) $user, ENT_QUOTES, 'UTF-8');
            $admin_badge = ((string) $user === '*') ? ' <span class="badge">admin</span>' : '';
            $fingerprint = htmlspecialchars(substr($hash, 0, 16), ENT_QUOTES, 'UTF-8').'&hellip;';
            $revoke = $writable
                ? '<form method="POST">'.$csrf_field.'<input type="hidden" name="process" value="apikey_revoke"><input type="hidden" name="api_user" value="'.$user_disp.'"><button type="submit" class="btn btn-secondary btn-sm">Revoke</button></form>'
                : '';
            $rows .= '<tr><td class="mono">'.$user_disp.$admin_badge.'</td><td class="mono muted">sha256:'.$fingerprint.'</td><td>'.$revoke.'</td></tr>';
        }
        $list_html = '<div class="ph-card-table"><table><thead><tr><th>User</th><th>Key hash</th><th></th></tr></thead><tbody>'.$rows.'</tbody></table></div>';
    }

    $body = '<div class="ph-section-head"><h3 class="mt-0">Create a key</h3></div>
		<div class="ph-form-card">
			<p class="muted mt-0">Each key authenticates the management REST API (<code>Authorization: Bearer &lt;key&gt;</code>) as its user. <code>*</code> is the admin (any torrent, full list); any other name is scoped to the torrents it adds. Only the key&rsquo;s SHA-256 hash is stored.</p>
			'.$message_html.$new_key_html.$create_html.'
		</div>
		<div class="ph-section-head"><h3>Existing keys</h3></div>
		'.$list_html;

    return view_admin_layout_html($settings, 'API Keys', $body, 'apikeys', $csrf_token, 'Server', '', true);
}
