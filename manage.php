<?php
/**
 * Superable Learning — Platform Management CLI
 *
 * Command-line administration for tenants and accounts. Deliberately CLI-only: these
 * operations delete client databases and reset credentials, and must not be reachable
 * over HTTP.
 *
 * Usage:
 *   php manage.php tenants                              List every provisioned tenant
 *   php manage.php users <tenant>                       List accounts in a tenant
 *   php manage.php audit                                Report weak/default passwords everywhere
 *   php manage.php set-password <tenant> <email>        Set a password (prompts, hidden input)
 *   php manage.php promote <tenant> <email>             Grant administrator rights
 *   php manage.php demote <tenant> <email>              Revoke administrator rights
 *   php manage.php delete-user <tenant> <email>         Delete one account
 *   php manage.php delete-tenant <tenant>               Delete a tenant (backs up first)
 *   php manage.php rename-tenant <old> <new>            Rename a tenant's data on disk
 *
 * The platform ("superuser") tenant is 'platform'. Administrators there — and only
 * there — can reach platform_admin.php.
 */

if (php_sapi_name() !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    die("Forbidden: This utility is restricted to command-line (CLI) execution only.\n");
}

require_once __DIR__ . '/config.php';

const PLATFORM_TENANT = 'platform';

/** Passwords that must never survive in a live system. */
const KNOWN_WEAK_PASSWORDS = [
    'password123', 'password', 'admin123', 'changeme', '12345678', 'letmein',
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function out($line = '')  { fwrite(STDOUT, $line . PHP_EOL); }
function fail($line)      { fwrite(STDERR, '[!] ' . $line . PHP_EOL); exit(1); }

function tenantsDir() {
    return getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
}

/**
 * Returns every provisioned tenant key, skipping malformed filenames.
 */
function allTenantKeys() {
    $keys = [];
    $dir = tenantsDir();
    if (!is_dir($dir)) {
        return $keys;
    }
    foreach (scandir($dir) as $file) {
        if (substr($file, -5) !== '.json') continue;
        $key = substr($file, 0, -5);
        if ($key === '' || $key !== sanitizeTenantKey($key)) continue;
        $keys[] = $key;
    }
    sort($keys);
    return $keys;
}

/**
 * Reads a line from STDIN with terminal echo disabled where the platform allows it.
 */
function promptSecret($label) {
    fwrite(STDOUT, $label);

    // Windows has no stty; fall back to visible input with a warning.
    if (DIRECTORY_SEPARATOR === '\\') {
        fwrite(STDOUT, PHP_EOL . '    (input will be visible in this terminal)' . PHP_EOL . '> ');
        return rtrim(fgets(STDIN), "\r\n");
    }

    @shell_exec('stty -echo 2>/dev/null');
    $value = rtrim(fgets(STDIN), "\r\n");
    @shell_exec('stty echo 2>/dev/null');
    fwrite(STDOUT, PHP_EOL);
    return $value;
}

function promptLine($label) {
    fwrite(STDOUT, $label);
    return trim(fgets(STDIN));
}

function confirmPhrase($phrase) {
    $typed = promptLine("Type exactly \"{$phrase}\" to confirm: ");
    return $typed === $phrase;
}

/**
 * Opens a tenant database, refusing to create one that does not already exist.
 */
function openTenant($tenantKey) {
    $tenantKey = sanitizeTenantKey($tenantKey);
    if (!tenantExists($tenantKey)) {
        fail("No tenant named '{$tenantKey}'. Run: php manage.php tenants");
    }
    return [get_db_connection($tenantKey), $tenantKey];
}

function findUser($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id, email, full_name, is_admin, password_hash FROM users WHERE email = ?");
    $stmt->execute([trim($email)]);
    return $stmt->fetch();
}

/**
 * Rejects passwords that are too short or on the known-weak list.
 */
function validateNewPassword($password, $minLength = 12) {
    if (strlen($password) < $minLength) {
        return "Password must be at least {$minLength} characters.";
    }
    if (in_array(strtolower($password), KNOWN_WEAK_PASSWORDS, true)) {
        return "That password is on the known-weak list. Choose something unique.";
    }
    return null;
}

// ---------------------------------------------------------------------------
// Commands
// ---------------------------------------------------------------------------

function cmdTenants() {
    $keys = allTenantKeys();
    if (!$keys) {
        out("No tenants provisioned.");
        return;
    }

    out(str_pad("TENANT", 28) . str_pad("PLAN", 12) . str_pad("STATUS", 10) . str_pad("USERS", 7) . "NAME");
    out(str_repeat('-', 92));

    foreach ($keys as $key) {
        $meta = getTenantMetadata($key);
        $userCount = '-';
        $dbPath = getDbPath($key);
        if (file_exists($dbPath)) {
            try {
                $pdo = get_db_connection($key);
                $userCount = (string)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            } catch (Exception $e) {
                $userCount = 'err';
            }
        }
        $label = ($key === PLATFORM_TENANT) ? $key . ' *' : $key;
        out(str_pad($label, 28)
          . str_pad($meta['plan'] ?? '-', 12)
          . str_pad($meta['status'] ?? 'active', 10)
          . str_pad($userCount, 7)
          . ($meta['name'] ?? ''));
    }
    out();
    out("* platform (superuser) tenant — administrators here can reach platform_admin.php");
}

function cmdUsers($tenantKey) {
    list($pdo, $tenantKey) = openTenant($tenantKey);
    out("Accounts in tenant '{$tenantKey}':");
    out();
    out(str_pad("ID", 6) . str_pad("ROLE", 9) . str_pad("EMAIL", 34) . str_pad("NAME", 24) . "PASSWORD");
    out(str_repeat('-', 92));

    foreach ($pdo->query("SELECT id, email, full_name, is_admin, password_hash FROM users ORDER BY id") as $u) {
        $weak = '';
        foreach (KNOWN_WEAK_PASSWORDS as $candidate) {
            if (password_verify($candidate, $u['password_hash'])) {
                $weak = 'WEAK (' . $candidate . ')';
                break;
            }
        }
        if ($weak === '' && $u['password_hash'] === 'stub_hash') {
            $weak = 'STUB (cannot log in)';
        }
        out(str_pad((string)$u['id'], 6)
          . str_pad($u['is_admin'] ? 'admin' : 'learner', 9)
          . str_pad($u['email'], 34)
          . str_pad(substr($u['full_name'], 0, 22), 24)
          . $weak);
    }
}

function cmdAudit() {
    $problems = 0;
    out("Scanning every tenant for default and unusable credentials...");
    out();

    foreach (allTenantKeys() as $key) {
        if (!file_exists(getDbPath($key))) continue;
        try {
            $pdo = get_db_connection($key);
            $rows = $pdo->query("SELECT id, email, is_admin, password_hash FROM users ORDER BY id");
        } catch (Exception $e) {
            out("  [{$key}] could not open database: " . $e->getMessage());
            continue;
        }

        foreach ($rows as $u) {
            foreach (KNOWN_WEAK_PASSWORDS as $candidate) {
                if (password_verify($candidate, $u['password_hash'])) {
                    $role = $u['is_admin'] ? 'ADMIN' : 'learner';
                    out(sprintf("  [%-24s] %-7s %-32s password is '%s'", $key, $role, $u['email'], $candidate));
                    out(sprintf("  %26s fix: php manage.php set-password %s %s", '', $key, $u['email']));
                    $problems++;
                    break;
                }
            }
            if ($u['password_hash'] === 'stub_hash') {
                out(sprintf("  [%-24s] stub    %-32s created by the old cross-tenant sync; safe to delete", $key, $u['email']));
                out(sprintf("  %26s fix: php manage.php delete-user %s %s", '', $key, $u['email']));
                $problems++;
            }
        }
    }

    out();
    out($problems === 0
        ? "No default or stub credentials found."
        : "{$problems} issue(s) found. Address each before the platform carries real learner data.");
}

function cmdSetPassword($tenantKey, $email) {
    list($pdo, $tenantKey) = openTenant($tenantKey);
    $user = findUser($pdo, $email);
    if (!$user) {
        fail("No account '{$email}' in tenant '{$tenantKey}'. Run: php manage.php users {$tenantKey}");
    }

    out("Setting password for {$user['full_name']} <{$user['email']}> in tenant '{$tenantKey}'.");
    $password = promptSecret("New password (min 12 chars): ");
    $error = validateNewPassword($password);
    if ($error) {
        fail($error);
    }
    $confirm = promptSecret("Confirm password: ");
    if ($password !== $confirm) {
        fail("Passwords did not match. No change made.");
    }

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);

    // Any remember-me token predates this password and should not survive the reset.
    try {
        $del = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
        $del->execute([$user['id']]);
    } catch (PDOException $e) {
        // Table may not exist on an older tenant database.
    }

    out("[+] Password updated. Existing 'remember me' sessions for this account were revoked.");
}

function cmdSetAdmin($tenantKey, $email, $makeAdmin) {
    list($pdo, $tenantKey) = openTenant($tenantKey);
    $user = findUser($pdo, $email);
    if (!$user) {
        fail("No account '{$email}' in tenant '{$tenantKey}'.");
    }

    if (!$makeAdmin) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1 AND id != ?");
        $stmt->execute([$user['id']]);
        if ((int)$stmt->fetchColumn() === 0) {
            fail("Refusing to demote the last administrator of '{$tenantKey}'. Promote someone else first.");
        }
    }

    $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $stmt->execute([$makeAdmin ? 1 : 0, $user['id']]);
    out("[+] {$user['email']} is now " . ($makeAdmin ? "an administrator" : "a learner") . " in '{$tenantKey}'.");
}

function cmdDeleteUser($tenantKey, $email) {
    list($pdo, $tenantKey) = openTenant($tenantKey);
    $user = findUser($pdo, $email);
    if (!$user) {
        fail("No account '{$email}' in tenant '{$tenantKey}'.");
    }

    if ($user['is_admin']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1 AND id != ?");
        $stmt->execute([$user['id']]);
        if ((int)$stmt->fetchColumn() === 0) {
            fail("Refusing to delete the last administrator of '{$tenantKey}'.");
        }
    }

    out("About to delete {$user['full_name']} <{$user['email']}> from tenant '{$tenantKey}',");
    out("along with their progress records and course permissions.");
    if (!confirmPhrase($user['email'])) {
        out("Cancelled. Nothing was deleted.");
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    out("[+] Account deleted.");
}

function cmdDeleteTenant($tenantKey) {
    $tenantKey = sanitizeTenantKey($tenantKey);

    if ($tenantKey === PLATFORM_TENANT) {
        fail("The platform tenant '" . PLATFORM_TENANT . "' cannot be deleted.");
    }
    if (!tenantExists($tenantKey)) {
        fail("No tenant named '{$tenantKey}'.");
    }

    $meta      = getTenantMetadata($tenantKey);
    $jsonPath  = tenantsDir() . DIRECTORY_SEPARATOR . $tenantKey . '.json';
    $dbPath    = getDbPath($tenantKey);
    $storage   = getStoragePath($tenantKey);
    $coursesIn = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey;

    $userCount = 'unknown';
    if (file_exists($dbPath)) {
        try {
            $pdo = get_db_connection($tenantKey);
            $userCount = (string)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        } catch (Exception $e) {
            // leave as unknown
        }
    }

    out("Tenant to delete:  {$tenantKey}  (\"" . ($meta['name'] ?? '') . "\")");
    out("  Accounts:        {$userCount}");
    out("  Metadata:        " . (file_exists($jsonPath) ? $jsonPath : '(none)'));
    out("  Database:        " . (file_exists($dbPath) ? $dbPath : '(none)'));
    out("  Storage:         " . (is_dir($storage) ? $storage : '(none)'));
    out("  Course folder:   " . (is_dir($coursesIn) ? $coursesIn : '(none)'));
    out();

    if ((int)$userCount > 0) {
        out("This tenant contains {$userCount} account(s) and their learner records.");
    }
    out("Everything above will be moved into a timestamped backup, then removed from service.");
    out();

    if (!confirmPhrase("delete {$tenantKey}")) {
        out("Cancelled. Nothing was changed.");
        return;
    }

    // Move rather than unlink, so a mistake is recoverable.
    $backupRoot = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'deleted-tenants'
                . DIRECTORY_SEPARATOR . $tenantKey . '-' . date('Ymd-His');
    if (!@mkdir($backupRoot, 0700, true)) {
        fail("Could not create backup directory at {$backupRoot}. Nothing was deleted.");
    }

    $moved = [];
    foreach ([['metadata.json', $jsonPath], ['database.sqlite', $dbPath]] as $item) {
        list($label, $path) = $item;
        if (file_exists($path) && @rename($path, $backupRoot . DIRECTORY_SEPARATOR . $label)) {
            $moved[] = $path;
        }
    }
    foreach ([['storage', $storage], ['courses', $coursesIn]] as $item) {
        list($label, $path) = $item;
        if (is_dir($path) && @rename($path, $backupRoot . DIRECTORY_SEPARATOR . $label)) {
            $moved[] = $path;
        }
    }

    // Drop any custom domain mappings that pointed at this tenant.
    $mapFile = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'custom_domains.json';
    if (file_exists($mapFile)) {
        $map = json_decode(file_get_contents($mapFile), true);
        if (is_array($map)) {
            $before = count($map);
            $map = array_filter($map, function ($v) use ($tenantKey) { return $v !== $tenantKey; });
            if (count($map) !== $before) {
                file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));
                out("[+] Removed " . ($before - count($map)) . " custom domain mapping(s).");
            }
        }
    }

    out("[+] Tenant '{$tenantKey}' removed. " . count($moved) . " item(s) backed up to:");
    out("    {$backupRoot}");
    out("    Delete that directory yourself once you are satisfied.");
}

function cmdRenameTenant($oldKey, $newKey) {
    $oldKey = sanitizeTenantKey($oldKey);
    $newKey = sanitizeTenantKey($newKey);

    if (!tenantExists($oldKey)) {
        fail("No tenant named '{$oldKey}'. Run: php manage.php tenants");
    }
    if ($newKey === '') {
        fail("New tenant key is invalid after sanitization.");
    }
    if (tenantExists($newKey)) {
        fail("A tenant named '{$newKey}' already exists. Choose a different key or delete it first.");
    }

    $oldJson    = tenantsDir() . DIRECTORY_SEPARATOR . $oldKey . '.json';
    $oldDb      = getDbPath($oldKey);
    $oldStorage = getStoragePath($oldKey);
    $oldCourses = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $oldKey;

    $newJson    = tenantsDir() . DIRECTORY_SEPARATOR . $newKey . '.json';
    $newDb      = getDbPath($newKey);
    $newStorage = getStoragePath($newKey);
    $newCourses = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $newKey;

    $meta = getTenantMetadata($oldKey);

    out("Renaming tenant '{$oldKey}' to '{$newKey}':");
    out("  Metadata:  " . (file_exists($oldJson) ? $oldJson : '(none)') . " -> {$newJson}");
    out("  Database:  " . (file_exists($oldDb) ? $oldDb : '(none)') . " -> {$newDb}");
    out("  Storage:   " . (is_dir($oldStorage) ? $oldStorage : '(none)') . " -> {$newStorage}");
    out("  Courses:   " . (is_dir($oldCourses) ? "{$oldCourses} -> {$newCourses}" : '(none, not a webroot course tenant)'));
    out();
    out("This moves data in place (renames, not a copy) and updates the metadata's internal");
    out("tenant_key plus any custom_domains.json entries pointing at '{$oldKey}'. Application code");
    out("that hardcodes the old key elsewhere must be updated and deployed separately — this");
    out("command only touches data on disk.");
    out();

    if (!confirmPhrase("rename {$oldKey} to {$newKey}")) {
        out("Cancelled. Nothing was changed.");
        return;
    }

    $renamed = [];
    if (file_exists($oldDb) && @rename($oldDb, $newDb)) {
        $renamed[] = 'database';
    }
    if (file_exists($oldJson)) {
        $meta['tenant_key'] = $newKey;
        file_put_contents($oldJson, json_encode($meta, JSON_PRETTY_PRINT));
        if (@rename($oldJson, $newJson)) {
            $renamed[] = 'metadata';
        }
    }
    if (is_dir($oldStorage) && @rename($oldStorage, $newStorage)) {
        $renamed[] = 'storage';
    }
    if (is_dir($oldCourses) && @rename($oldCourses, $newCourses)) {
        $renamed[] = 'courses';
    }

    $mapFile = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'custom_domains.json';
    if (file_exists($mapFile)) {
        $map = json_decode(file_get_contents($mapFile), true);
        if (is_array($map)) {
            $changed = false;
            foreach ($map as $domain => $mappedTenant) {
                if ($mappedTenant === $oldKey) {
                    $map[$domain] = $newKey;
                    $changed = true;
                }
            }
            if ($changed) {
                file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));
                out("[+] Updated custom_domains.json entries pointing at '{$oldKey}'.");
            }
        }
    }

    out("[+] Renamed: " . (empty($renamed) ? '(nothing found to move)' : implode(', ', $renamed)) . ".");
    out("Remember: application code that hardcodes '{$oldKey}' must be updated and deployed separately.");
}

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'tenants':
        cmdTenants();
        break;

    case 'users':
        if (empty($argv[2])) fail("Usage: php manage.php users <tenant>");
        cmdUsers($argv[2]);
        break;

    case 'audit':
        cmdAudit();
        break;

    case 'set-password':
        if (empty($argv[2]) || empty($argv[3])) fail("Usage: php manage.php set-password <tenant> <email>");
        cmdSetPassword($argv[2], $argv[3]);
        break;

    case 'promote':
        if (empty($argv[2]) || empty($argv[3])) fail("Usage: php manage.php promote <tenant> <email>");
        cmdSetAdmin($argv[2], $argv[3], true);
        break;

    case 'demote':
        if (empty($argv[2]) || empty($argv[3])) fail("Usage: php manage.php demote <tenant> <email>");
        cmdSetAdmin($argv[2], $argv[3], false);
        break;

    case 'delete-user':
        if (empty($argv[2]) || empty($argv[3])) fail("Usage: php manage.php delete-user <tenant> <email>");
        cmdDeleteUser($argv[2], $argv[3]);
        break;

    case 'delete-tenant':
        if (empty($argv[2])) fail("Usage: php manage.php delete-tenant <tenant>");
        cmdDeleteTenant($argv[2]);
        break;

    case 'rename-tenant':
        if (empty($argv[2]) || empty($argv[3])) fail("Usage: php manage.php rename-tenant <old-tenant> <new-tenant>");
        cmdRenameTenant($argv[2], $argv[3]);
        break;

    default:
        out("Superable Learning — Platform Management CLI");
        out();
        out("  php manage.php tenants                        List every provisioned tenant");
        out("  php manage.php users <tenant>                 List accounts, flagging weak passwords");
        out("  php manage.php audit                          Scan every tenant for default credentials");
        out("  php manage.php set-password <tenant> <email>  Set a password (prompted, not on the command line)");
        out("  php manage.php promote <tenant> <email>       Grant administrator rights");
        out("  php manage.php demote <tenant> <email>        Revoke administrator rights");
        out("  php manage.php delete-user <tenant> <email>   Delete one account");
        out("  php manage.php delete-tenant <tenant>         Delete a tenant (backed up first)");
        out("  php manage.php rename-tenant <old> <new>      Rename a tenant's data on disk");
        out();
        out("The platform (superuser) tenant is '" . PLATFORM_TENANT . "'. Administrators there,");
        out("and only there, can reach platform_admin.php.");
        break;
}
