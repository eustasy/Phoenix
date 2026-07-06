<?php

declare(strict_types=1);

////	admin_apikey_create_action
// Issues an API key for the posted user: validates the name, generates a key,
// stores only its SHA-256 hash (merged into the existing api_keys via
// config_write), and returns the one-time plaintext for the view to show once.
// Reusing an existing name rotates that user's key. Returns
// [message, plaintext_key|null, updated_api_keys|null] — the caller reflects the
// updated array into $settings so the list re-renders this request.
/**
 * @param PhoenixSettings $settings
 * @return array{0: string, 1: string|null, 2: array<string, string>|null}
 */
function admin_apikey_create_action(array $settings, string $config_path): array
{
    $user = isset($_POST['api_user']) && is_string($_POST['api_user']) ? strtolower(trim($_POST['api_user'])) : '';
    if ($user === '') {
        return ['A user name is required.', null, null];
    }
    if ($user !== '*' && preg_match('/^[a-z0-9._-]{1,64}$/', $user) !== 1) {
        return ['User names may contain only lowercase letters, digits, dot, underscore and hyphen — or "*" for the admin key.', null, null];
    }

    require_once __DIR__.'/../functions/api.key.generate.php';
    $key = api_key_generate();

    $keys = $settings['api_keys'];
    $existed = isset($keys[$user]);
    $keys[$user] = hash('sha256', $key);

    require_once __DIR__.'/../functions/config.write.php';
    if (! config_write($config_path, ['api_keys' => $keys])) {
        return ['Could not write the configuration file.', null, null];
    }

    $verb = $existed ? 'rotated' : 'created';

    return ['API key '.$verb.' for "'.$user.'". Copy it now — it is not stored and will not be shown again.', $key, $keys];
}
