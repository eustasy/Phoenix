<?php

declare(strict_types=1);

////	install_valid_totp_secret
// Defensive check that a candidate TOTP secret is a plausible base32 string
// (uppercase A-Z and digits 2-7, the RFC 4648 base32 alphabet) of at least the
// 16-character minimum the verifier requires. Anything else is treated as
// "no secret" so a tampered or truncated round-tripped value can never be
// written to config or fed to checkCode().

function install_valid_totp_secret(mixed $secret): bool
{
    return is_string($secret) && (bool) preg_match('/^[A-Z2-7]{16,}$/', $secret);
}
