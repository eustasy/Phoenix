<?php

declare(strict_types=1);

////	admin_apikey_revoke_action
// Removes the posted user's API key (merged out of api_keys via config_write).
// Returns [message, updated_api_keys|null]; the caller reflects the updated array
// into $settings so the list re-renders this request.
/**
 * @param PhoenixSettings $settings
 * @return array{0: string, 1: array<string, string>|null}
 */
function admin_apikey_revoke_action(array $settings, string $config_path): array
{
    $user = isset($_POST['api_user']) && is_string($_POST['api_user']) ? $_POST['api_user'] : '';
    $keys = $settings['api_keys'];
    if ($user === '' || ! isset($keys[$user])) {
        return ['No API key found for that user.', null];
    }
    unset($keys[$user]);

    require_once __DIR__.'/../functions/config.write.php';
    if (! config_write($config_path, ['api_keys' => $keys])) {
        return ['Could not write the configuration file.', null];
    }

    return ['API key for "'.$user.'" revoked.', $keys];
}
