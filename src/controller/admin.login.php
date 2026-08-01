<?php

declare(strict_types=1);

////	admin_login_controller
//  Handles authentication for admin panel.
//  Returns HTML output if login required, or null if authenticated.

/** @param PhoenixSettings $settings */
function admin_login_controller(array $settings): ?string
{
    if (empty($settings['admin_password'])) {
        // No admin password set — which would otherwise leave the panel fully
        // unauthenticated. Force a one-time "set password" step unless the
        // operator has explicitly opted into an unauthenticated panel (e.g. it
        // is protected by the reverse proxy).
        if (! empty($settings['admin_auth_optional'])) {
            return null;
        }
        require_once __DIR__.'/admin.setup.password.php';

        return admin_setup_password_controller($settings, __DIR__.'/../../config/phoenix.custom.php');
    }

    // Only touch the session when the request actually presents one. PHP's
    // files handler creates a session file on session_start() whether or not
    // anything is ever stored in it, so starting one for every anonymous hit
    // lets a scanner fill the session directory with a file per request. A
    // request with no session has no logout to process and no auth state to
    // read, so the start is deferred to the one branch that needs somewhere to
    // record a result: a successful login.
    require_once __DIR__.'/../functions/auth.session.start.php';
    $has_session = session_status() === PHP_SESSION_ACTIVE
        || isset($_COOKIE[session_name()])
        // A pre-set id (session.use_only_cookies off, or a caller that set one)
        // is still a presented session.
        || session_id() !== '';
    if ($has_session) {
        auth_session_start();

        ////	Handle logout

        require_once __DIR__.'/../functions/auth.handle.logout.php';
        auth_handle_logout();
    }

    ////	Check authentication

    require_once __DIR__.'/../functions/auth.is.authenticated.php';
    if (! auth_is_authenticated()) {
        $login_attempted = isset($_POST['process']) && $_POST['process'] === 'login';

        if ($login_attempted) {
            require_once __DIR__.'/../functions/auth.verify.login.php';
            if (auth_verify_login($settings)) {
                // Transparently upgrade the stored hash if PASSWORD_DEFAULT has
                // moved on since it was created — best-effort, never blocks login.
                require_once __DIR__.'/../functions/auth.rehash.password.php';
                auth_rehash_password(
                    $settings,
                    isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '',
                    __DIR__.'/../../config/phoenix.custom.php',
                );
                // A successful login is the first point that needs somewhere to
                // record state, so start the session here when the request did
                // not already carry one.
                auth_session_start();
                // Successful login clears the brute-force counter and retires
                // any pre-login session id (anti session-fixation) so an
                // attacker who planted one cannot resume the authed session.
                unset($_SESSION['login_fails']);
                session_regenerate_id(true);
                require_once __DIR__.'/../functions/auth.set.authenticated.php';
                auth_set_authenticated();
                // Return to the page just logged into, but only if it is a
                // same-origin path — never an attacker's '//host' (open redirect).
                require_once __DIR__.'/../functions/auth.safe.redirect.path.php';
                $return_to = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
                    ? $_SERVER['REQUEST_URI']
                    : '';
                header('Location: '.auth_safe_redirect_path($return_to));
                exit;
            }

            // Failed login: escalating backoff for a client that carries a
            // session, ADVERTISED rather than enforced. Nothing is tracked for a
            // request that presented no session — there is no state to escalate
            // against, and starting one here would let a scanner spend our disk.
            // Real per-IP rate limiting belongs at the proxy: see APACHE.md /
            // NGINX.md.
            if ($has_session) {
                $fails = (isset($_SESSION['login_fails']) && is_int($_SESSION['login_fails']))
                    ? $_SESSION['login_fails'] + 1
                    : 1;
                $_SESSION['login_fails'] = $fails;

                require_once __DIR__.'/../functions/auth.login.throttle.delay.php';
                $delay = auth_login_throttle_delay(
                    $fails,
                    $settings['admin_login_delay'],
                    $settings['admin_login_delay_max'],
                );
                if ($delay > 0) {
                    // Advertise the backoff instead of sleeping through it. A
                    // blocking sleep() holds the PHP worker for its whole
                    // duration, so a burst of failed logins could exhaust the
                    // pool — the request costs us more than it costs the
                    // attacker. 429 + Retry-After asks a well-behaved client to
                    // wait while the worker is freed immediately. The form is
                    // still rendered below, so a browser shows the error.
                    http_response_code(429);
                    header('Retry-After: '.$delay);
                }
            }
        }

        require_once __DIR__.'/../views/html.login.php';

        // Surface the TOTP field only when a secret is enrolled, so a
        // password-only install never shows a code box.
        $totp_required = ! empty($settings['admin_totp_secret']);

        return view_login_html($login_attempted, $totp_required, $settings['phoenix_version']);
    }

    // Authenticated, allow proceeding
    return null;
}
