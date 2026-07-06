<?php

declare(strict_types=1);

////	api_key_generate
// Generates a new API key: a "phx_" prefix plus 256 bits of CSPRNG randomness as
// hex (68 chars). The entropy is high enough that no slow hashing is needed at
// rest — only its SHA-256 is stored (see api_authenticate_key), and this
// plaintext is shown to the operator once at creation and never persisted. The
// prefix makes a leaked key easy to recognise (e.g. by secret scanners).
function api_key_generate(): string
{
    return 'phx_'.bin2hex(random_bytes(32));
}
