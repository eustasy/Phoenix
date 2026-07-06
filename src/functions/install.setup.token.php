<?php

declare(strict_types=1);

////	install_setup_token
// Returns the first-run setup token, creating and persisting it on first call.
// This token gates the otherwise-unauthenticated installer (findings #1 / #2):
// it is written to a server-only file in config/ (which lives outside the
// docroot), so only someone with filesystem access — the real operator — can
// read it and complete setup. A network attacker who reaches admin.php in the
// pre-setup window cannot. An existing token is reused so it stays stable across
// the GET (render) and POST (submit); admin_install_controller() deletes the
// file once setup succeeds.
function install_setup_token(string $token_path): string
{
    if (is_readable($token_path)) {
        $existing = trim((string) file_get_contents($token_path));
        if ($existing !== '') {
            return $existing;
        }
    }

    $token = bin2hex(random_bytes(18));
    // Best-effort restrictive permissions; config/ sits outside the docroot
    // regardless, so the file is not web-reachable under the documented setups.
    file_put_contents($token_path, $token, LOCK_EX);
    @chmod($token_path, 0o600);

    return $token;
}
