<?php

declare(strict_types=1);

////	admin_login_controller
//  Handles authentication for admin panel.
//  Returns HTML output if login required, or null if authenticated.

/** @param PhoenixSettings $settings */
function admin_login_controller(array $settings): ?string
{
    if (empty($settings['admin_password'])) {
        // No password configured, skip auth
        return null;
    }

    // Harden the session cookie before session_start() — params apply to the
    // cookie that is about to be sent. Secure is conditional on the request
    // arriving over HTTPS so local-dev plain-HTTP setups still work.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => ! empty($_SERVER['HTTPS']),
    ]);
    session_start();

    ////	Handle logout

    require_once __DIR__.'/../functions/auth.handle.logout.php';
    auth_handle_logout();

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
                // Successful login clears the brute-force counter and retires
                // any pre-login session id (anti session-fixation) so an
                // attacker who planted one cannot resume the authed session.
                unset($_SESSION['login_fails']);
                session_regenerate_id(true);
                require_once __DIR__.'/../functions/auth.set.authenticated.php';
                auth_set_authenticated();
                header('Location: '.$_SERVER['REQUEST_URI']);
                exit;
            }

            // Failed login: escalating per-session delay to throttle brute
            // force. A client that keeps its session cookie is slowed
            // progressively; a cookie-less attacker still pays the base delay on
            // each request. Complements — does not replace — the per-IP proxy
            // rate-limiting documented in APACHE.md / NGINX.md.
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
                sleep($delay);
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
