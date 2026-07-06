<?php

declare(strict_types=1);

////	auth_password_valid
// The admin password policy, shared by every place a password is set: the
// installer, the Settings change-password action, and the first-run set-password
// gate. Two rules only (NIST SP 800-63B — length over composition):
//   * at least 12 characters, and
//   * at most 72 BYTES — bcrypt (PASSWORD_DEFAULT) silently truncates beyond 72
//     bytes, so a longer passphrase would have its tail ignored.
// mb_strlen counts characters when the extension is present (it is optional), so
// a multibyte password is measured by character, not byte, for the minimum.
// Returns null when the password is acceptable, otherwise a human-readable reason.

function auth_password_valid(string $password): ?string
{
    $length = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);
    if ($length < 12) {
        return 'The password must be at least 12 characters.';
    }
    if (strlen($password) > 72) {
        return 'The password must be at most 72 bytes (a bcrypt limit).';
    }

    return null;
}
