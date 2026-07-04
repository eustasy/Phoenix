<?php

declare(strict_types=1);

////	auth_safe_redirect_path
// Neutralise the open-redirect risk in a Location: header built from
// $_SERVER['REQUEST_URI'] (the admin login / logout post-redirect-GET). Only a
// same-origin absolute path is allowed through: it must begin with a single '/'
// and must NOT be protocol-relative — neither '//host' nor the '/\host'
// backslash variant browsers normalise to it — nor an absolute URL (an
// absolute-form request target). Anything else falls back to a fixed local page,
// so a crafted request target can never bounce the admin off-site. header()
// already blocks CR/LF, so the off-site scheme is the only concern left here.

function auth_safe_redirect_path(string $uri, string $fallback = 'admin.php'): string
{
    if (
        isset($uri[0]) && $uri[0] === '/' &&
        (! isset($uri[1]) || ($uri[1] !== '/' && $uri[1] !== '\\'))
    ) {
        return $uri;
    }

    return $fallback;
}
