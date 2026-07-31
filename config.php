<?php
/**
 * Superable Learning LMS Core Configuration & Multi-Tenant Architecture
 * 
 * Defines core constants, security settings, database connection handling,
 * tenant resolution, tenant course paths, and tenant storage/metadata loaders.
 */

// Basic error reporting (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Error log must live OUTSIDE the public web root. A log inside the document root is
// fetchable over HTTP and leaks stack traces, absolute paths, and user identifiers.
$slLogDir = is_dir('/home/accessib')
    ? '/home/accessib/logs'
    : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($slLogDir)) {
    @mkdir($slLogDir, 0750, true);
}
ini_set('error_log', is_dir($slLogDir)
    ? $slLogDir . DIRECTORY_SEPARATOR . 'superablelearning-error.log'
    : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'superablelearning-error.log');
unset($slLogDir);

// Clean query parameters to handle external launch wrappers appending parameters with "?" instead of "&"
// This ensures that parameters like 'tenant' or 'course_id' are not contaminated.
if (isset($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], '?') !== false) {
    $first_question_mark = strpos($_SERVER['QUERY_STRING'], '?');
    $cleaned_query = substr_replace($_SERVER['QUERY_STRING'], '&', $first_question_mark, 1);
    parse_str($cleaned_query, $extra_params);
    $_GET = array_merge($_GET, $extra_params);
    $_REQUEST = array_merge($_REQUEST, $extra_params);
}

// Define core constants & paths
define('LMS_ROOT', __DIR__);
define('PRIMARY_DOMAIN', 'superablelearning.com');
define('MAX_TENANT_STORAGE_MB', 500); // 500 MB quota per tenant

// Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'None');
    } else {
        ini_set('session.cookie_samesite', 'Lax');
    }
    session_start();
    
    // Prevent browser caching of dynamic PHP pages (fixes stale login/guest page views)
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }
}

// Generate CSRF Token for Form Security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Verifies CSRF Token on sensitive POST requests.
 *
 * NOTE: session.cookie_samesite is deliberately 'None' over HTTPS so the course player
 * can run inside the Build Capable XCL cross-origin iframe wrapper. That means the
 * SameSite cookie attribute provides NO cross-site request protection here, and this
 * token is the only defence on state-changing requests. Every POST/write endpoint must
 * call this.
 */
function verify_csrf_token() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("Security Error: Invalid or expired CSRF security token. Please refresh the page and try again.");
    }
}

/**
 * Verifies a CSRF token supplied in a JSON request body (used by api.php).
 * Returns true on success; does not terminate, so callers can emit a JSON error.
 *
 * @param string|null $token
 * @return bool
 */
function check_csrf_token($token) {
    if (empty($token)) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Session keys that carry an authenticated identity and must never cross a tenant boundary.
 */
const SL_IDENTITY_KEYS = ['user_id', 'full_name', 'is_admin', 'email', 'is_dev', 'dev_override_plan'];

/**
 * Records an authenticated identity against a specific tenant.
 *
 * Tenant databases are fully isolated, so a user id only has meaning inside the tenant it
 * authenticated against. Identities are stored per tenant rather than as flat session
 * keys, which lets one browser session hold logins to several tenants at once (for
 * example a platform admin inspecting a client portal) without any of them leaking into
 * another tenant's context.
 *
 * Call immediately after a successful authentication.
 *
 * @param string $tenantKey
 * @param array $identity Map of SL_IDENTITY_KEYS values
 */
function bind_session_to_tenant($tenantKey, array $identity) {
    $tenantKey = sanitizeTenantKey($tenantKey);

    $stored = [];
    foreach (SL_IDENTITY_KEYS as $key) {
        if (array_key_exists($key, $identity)) {
            $stored[$key] = $identity[$key];
        }
    }

    if (!isset($_SESSION['tenant_identities']) || !is_array($_SESSION['tenant_identities'])) {
        $_SESSION['tenant_identities'] = [];
    }
    $_SESSION['tenant_identities'][$tenantKey] = $stored;

    // Hydrate the flat keys the rest of the application reads.
    foreach (SL_IDENTITY_KEYS as $key) {
        unset($_SESSION[$key]);
    }
    foreach ($stored as $key => $value) {
        $_SESSION[$key] = $value;
    }
    $_SESSION['auth_tenant_key'] = $tenantKey;
}

/**
 * Removes the stored identity for one tenant (used by logout).
 *
 * @param string $tenantKey
 */
function clear_tenant_identity($tenantKey) {
    $tenantKey = sanitizeTenantKey($tenantKey);
    if (isset($_SESSION['tenant_identities'][$tenantKey])) {
        unset($_SESSION['tenant_identities'][$tenantKey]);
    }
    foreach (SL_IDENTITY_KEYS as $key) {
        unset($_SESSION[$key]);
    }
    unset($_SESSION['auth_tenant_key']);
}

/**
 * Rehydrates the flat session identity keys from the identity belonging to the tenant
 * this request actually resolved to.
 *
 * Without this, `?tenant=<other>` carried a user's id and is_admin flag into another
 * client's database, allowing cross-tenant administration and data access. Any session
 * with no stored identity for the active tenant is treated as a guest there.
 *
 * Sessions created before per-tenant identities existed have no tenant_identities map;
 * their flat keys are untrusted and are cleared.
 */
function enforce_tenant_session_binding() {
    $activeTenant = resolveTenantKey();
    $identities = $_SESSION['tenant_identities'] ?? null;

    if (!is_array($identities) || empty($identities[$activeTenant])) {
        if (!empty($_SESSION['user_id'])) {
            error_log(sprintf(
                'Tenant session binding rejected: no identity stored for tenant [%s] (user id [%s])',
                $activeTenant,
                $_SESSION['user_id']
            ));
        }
        foreach (SL_IDENTITY_KEYS as $key) {
            unset($_SESSION[$key]);
        }
        unset($_SESSION['auth_tenant_key']);
        return;
    }

    foreach (SL_IDENTITY_KEYS as $key) {
        unset($_SESSION[$key]);
    }
    foreach ($identities[$activeTenant] as $key => $value) {
        if (in_array($key, SL_IDENTITY_KEYS, true)) {
            $_SESSION[$key] = $value;
        }
    }
    $_SESSION['auth_tenant_key'] = $activeTenant;
}

/**
 * Returns true when the current session is an administrator of the platform tenant
 * (platform), which is what gates platform_admin.php.
 *
 * The session's is_admin flag alone is NOT sufficient — it is set by logging into any
 * tenant, including one an attacker provisioned themselves.
 *
 * @return bool
 */
function is_platform_admin() {
    // Read the platform identity directly from the per-tenant map so this works no matter
    // which tenant the current request resolved to.
    $platformIdentity = $_SESSION['tenant_identities']['platform'] ?? null;
    if (!is_array($platformIdentity) || empty($platformIdentity['user_id'])) {
        return false;
    }

    // Re-verify the admin flag against the platform database rather than trusting the
    // session. The session flag alone is set by logging into ANY tenant, including one an
    // attacker provisioned for themselves.
    try {
        $platformDb = get_db_connection('platform');
        $stmt = $platformDb->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$platformIdentity['user_id']]);
        $row = $stmt->fetch();
        return $row && !empty($row['is_admin']);
    } catch (PDOException $e) {
        error_log("Platform admin verification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Sanitizes a tenant key to ensure safe file system usage.
 * Only allows lowercase alphanumeric characters, hyphens, and underscores.
 *
 * @param string $key
 * @return string
 */
function sanitizeTenantKey($key) {
    $clean = preg_replace('/[^a-z0-9\-_]/i', '', strtolower($key));
    if (in_array($clean, ['superableaccessibility', 'superable-accessibility', 'accessibility'])) {
        return 'superableaccessibility';
    }
    return !empty($clean) ? $clean : 'platform';
}

/**
 * Returns custom domain mappings array.
 * Reads from custom_domains.json if present, or returns static array.
 *
 * @return array
 */
function getCustomDomainMap() {
    $mapFile = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'custom_domains.json';
    if (file_exists($mapFile)) {
        $json = json_decode(file_get_contents($mapFile), true);
        if (is_array($json)) {
            return $json;
        }
    }
    return [];
}

/**
 * Tenant Resolution Function
 * 
 * Host & Query Resolution Hierarchy:
 * 1. Explicit ?tenant= parameter in URL (e.g. ?tenant=superableaccessibility)
 * 2. Custom domain map lookup (e.g. clientdomain.com -> tenantKey)
 * 3. Subdomain detection (e.g. tenant.superablelearning.com -> tenant)
 * 4. Local development subdomain regex (e.g. tenant.localhost -> tenant)
 * 5. Main domain fallback ('platform')
 *
 * @return string Mapped tenant key
 */
function resolveTenantKey() {
    // 1. Explicit query parameter override (highest priority for dev/testing)
    if (!empty($_GET['tenant'])) {
        $clean = sanitizeTenantKey($_GET['tenant']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['active_tenant_key'] = $clean;
        }
        return $clean;
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    
    // Strip port if present (e.g. localhost:8000 -> localhost)
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    $host = strtolower(trim($host));

    $baseDomain = PRIMARY_DOMAIN;

    // 2. Check Custom Domain Lookup
    $customMap = getCustomDomainMap();
    if (isset($customMap[$host])) {
        return sanitizeTenantKey($customMap[$host]);
    }

    // 3. Detect Subdomain (e.g. superableaccessibility.superablelearning.com)
    if (substr($host, -strlen('.' . $baseDomain)) === '.' . $baseDomain) {
        $subdomain = substr($host, 0, -strlen('.' . $baseDomain));
        if ($subdomain !== '' && $subdomain !== 'www') {
            return sanitizeTenantKey($subdomain);
        }
    }

    // 4. Local Development Subdomain Detection (e.g. superableaccessibility.localhost)
    if (preg_match('/^([a-z0-9\-]+)\.(localhost|test|local)$/i', $host, $matches)) {
        if (!in_array($matches[1], ['www', 'app', 'lms'])) {
            return sanitizeTenantKey($matches[1]);
        }
    }

    // 5. Active Session Tenant Key (preserves tenant during query-parameter navigation)
    //
    // This must never apply on a platform host. Previously the check was
    // `$host !== 'localhost' && $host !== PRIMARY_DOMAIN`, a whitelist of exactly two
    // names — so on www.superablelearning.com, 127.0.0.1, or any hosts-file alias, the
    // last tenant visited became sticky and the platform landing page was permanently
    // replaced by that tenant's dashboard for the rest of the session.
    if (!isPlatformHost($host)) {
        if (!empty($_SESSION['active_tenant_key'])) {
            return $_SESSION['active_tenant_key'];
        }
    } else {
        // On a platform host with no explicit ?tenant=, clear any session override so the
        // main platform landing page loads.
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_GET['tenant'])) {
            unset($_SESSION['active_tenant_key']);
        }
    }

    // 6. Default Fallback Key for Main Platform Site
    return 'platform';
}

/**
 * Returns true when a hostname addresses the platform itself rather than a client tenant.
 *
 * Covers the primary domain, its www form, and the loopback / development names used
 * when running the built-in PHP server.
 *
 * @param string $host Hostname with any port already stripped
 * @return bool
 */
function isPlatformHost($host) {
    $host = strtolower(trim($host));

    $platformHosts = [
        PRIMARY_DOMAIN,
        'www.' . PRIMARY_DOMAIN,
        'localhost',
        'www.localhost',
        '127.0.0.1',
        '::1',
        '[::1]',
        '0.0.0.0',
    ];

    return in_array($host, $platformHosts, true);
}

/**
 * Helper to construct internal URLs while preserving active tenant query parameter.
 *
 * @param string $path
 * @return string
 */
function tenant_url($path) {
    $activeTenant = resolveTenantKey();
    if ($activeTenant !== 'platform') {
        $sep = (strpos($path, '?') !== false) ? '&' : '?';
        return $path . $sep . 'tenant=' . urlencode($activeTenant);
    }
    return $path;
}

/**
 * Helper to construct clean permalinks for courses.
 * Format: /<tenant>/courses/<course_id> or /courses/<course_id> (prefixed with base path)
 *
 * @param string $course_id
 * @param string|null $tenantKey
 * @return string
 */
function course_url($course_id, $tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/';
    if ($tenantKey && $tenantKey !== 'platform') {
        return $base . urlencode($tenantKey) . "/courses/" . urlencode($course_id);
    }
    return $base . "courses/" . urlencode($course_id);
}

/**
 * Returns the dynamic base path for HTML <base href> tags.
 * e.g., "/" or "/superable-learning/"
 */
function get_base_href() {
    $dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    return ($dir === '/' || $dir === '\\') ? '/' : rtrim($dir, '/\\') . '/';
}

/**
 * Returns total storage space used by a tenant in bytes.
 *
 * @param string|null $tenantKey
 * @return int Total size in bytes
 */
function getTenantStorageUsage($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
    $totalBytes = 0;

    $coursesDir = getTenantCoursesDir($tenantKey);
    $storageDir = getStoragePath($tenantKey);

    if (is_dir($coursesDir)) {
        $totalBytes += getDirectorySizeRecursive($coursesDir);
    }
    if (is_dir($storageDir)) {
        $totalBytes += getDirectorySizeRecursive($storageDir);
    }

    return $totalBytes;
}

/**
 * Helper: Recursively calculates directory size in bytes.
 */
function getDirectorySizeRecursive($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path)) {
            $size += filesize($path);
        } elseif (is_dir($path)) {
            $size += getDirectorySizeRecursive($path);
        }
    }
    return $size;
}

/**
 * Returns the base database directory above the web root.
 * Server Path: /home/accessib/db/superablelearning
 *
 * @return string
 */
function getTenantBaseDir() {
    if (is_dir('/home/accessib')) {
        return '/home/accessib/db/superablelearning';
    }
    return dirname(LMS_ROOT) . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'superablelearning';
}

/**
 * Tenant-Aware Database Path Function
 * Returns /home/accessib/db/superablelearning/tenants/{tenantKey}.sqlite
 *
 * @param string|null $tenantKey
 * @return string
 */
function getDbPath($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
    $tenantsDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
    return $tenantsDir . DIRECTORY_SEPARATOR . $tenantKey . '.sqlite';
}

/**
 * Tenant Storage Path Function
 * Returns /home/accessib/storage/superablelearning/tenants/{tenantKey}
 *
 * @param string|null $tenantKey
 * @return string
 */
function getStoragePath($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();

    if (is_dir('/home/accessib')) {
        $baseStorage = '/home/accessib/storage/superablelearning';
    } else {
        $baseStorage = dirname(LMS_ROOT) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'superablelearning';
    }

    return $baseStorage . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey;
}

/**
 * Returns the absolute directory path where tenant courses reside.
 *
 * @param string|null $tenantKey
 * @return string
 */
function getTenantCoursesDir($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();

    // 1. Check web root tenant folder: courses/tenants/{tenantKey}
    $tenantWebCourses = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey;
    if (is_dir($tenantWebCourses)) {
        return $tenantWebCourses;
    }

    // 2. Check tenant storage directory
    $storageCourses = getStoragePath($tenantKey) . DIRECTORY_SEPARATOR . 'courses';
    if (is_dir($storageCourses)) {
        return $storageCourses;
    }

    // 3. Fallback web root courses directory
    return LMS_ROOT . DIRECTORY_SEPARATOR . 'courses';
}

/**
 * Returns the relative web URL path prefix for serving tenant course assets.
 *
 * @param string|null $tenantKey
 * @return string
 */
function getTenantCoursesWebPath($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();

    $tenantWebCourses = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey;
    if (is_dir($tenantWebCourses)) {
        return 'courses/tenants/' . $tenantKey;
    }

    return 'courses';
}

/**
 * Loads or creates tenant metadata JSON file.
 * Path: /home/accessib/db/superablelearning/tenants/{tenantKey}.json
 *
 * @param string|null $tenantKey
 * @return array
 */
function getTenantMetadata($tenantKey = null, $createIfMissing = false) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();

    $tenantsDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
    $jsonPath = $tenantsDir . DIRECTORY_SEPARATOR . $tenantKey . '.json';

    if (file_exists($jsonPath)) {
        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);
        if (is_array($data)) {
            if ($tenantKey === 'platform') {
                $data['name'] = 'Superable Learning';
            }
            return $data;
        }
    }

    $defaultMeta = [
        'tenant_key' => $tenantKey,
        'name'       => ($tenantKey === 'platform') ? 'Superable Learning' : ucfirst(str_replace(['-', '_'], ' ', $tenantKey)),
        'domain'     => ($tenantKey === 'platform') ? PRIMARY_DOMAIN : $tenantKey . '.' . PRIMARY_DOMAIN,
        'plan'       => 'standard',
        'created'    => date('Y-m-d H:i:s'),
        'status'     => 'active'
    ];

    // Only persist when a caller is explicitly provisioning. Writing on every read meant
    // any ?tenant=<anything> in a URL silently created a tenant metadata file on disk.
    if ($createIfMissing && (is_dir($tenantsDir) || @mkdir($tenantsDir, 0755, true))) {
        @file_put_contents($jsonPath, json_encode($defaultMeta, JSON_PRETTY_PRINT));
    }

    return $defaultMeta;
}

/**
 * Returns true when a tenant has actually been provisioned (metadata file on disk).
 *
 * @param string|null $tenantKey
 * @return bool
 */
function tenantExists($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
    if ($tenantKey === 'platform') {
        return true;
    }
    $jsonPath = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey . '.json';
    return file_exists($jsonPath);
}

/**
 * Discovers and returns a list of all active tenant accounts.
 *
 * @return array
 */
function getAvailableTenants() {
    $tenants = [];
    $dbDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';

    if (is_dir($dbDir)) {
        foreach (scandir($dbDir) as $file) {
            if (substr($file, -5) === '.json') {
                $key = substr($file, 0, -5);
                if ($key === 'platform') continue;

                // Skip malformed filenames such as a bare ".json". An empty key falls
                // through getTenantMetadata() to resolveTenantKey(), which made the file
                // appear in the portal list as a phantom duplicate of the current tenant.
                if ($key === '' || $key !== sanitizeTenantKey($key)) {
                    continue;
                }

                $meta = getTenantMetadata($key);
                if (($meta['status'] ?? 'active') === 'active') {
                    $tenants[] = $meta;
                }
            }
        }
    }
    return $tenants;
}

/**
 * Calculates relative luminance of a hex color for WCAG 2.1 contrast calculations.
 */
function getRelativeLuminance($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) return 0.5;

    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Calculates WCAG 2.1 contrast ratio between two hex colors.
 */
function getContrastRatio($hex1, $hex2) {
    $l1 = getRelativeLuminance($hex1);
    $l2 = getRelativeLuminance($hex2);
    $brightest = max($l1, $l2);
    $darkest = min($l1, $l2);
    return ($brightest + 0.05) / ($darkest + 0.05);
}

/**
 * Validates custom CSS for accessibility compliance (contrast, outlines, skip links) and security (imports, protocols, bindings).
 *
 * @param string $css
 * @param array &$errors
 * @return bool
 */
function validateCustomCss($css, &$errors) {
    // 1. Accessibility Checks: Focus Outlines
    if (preg_match('/outline\s*:\s*(none|0|transparent|hidden)/i', $css) || 
        preg_match('/outline-width\s*:\s*(0|none)/i', $css) || 
        preg_match('/outline-style\s*:\s*(none|hidden)/i', $css) || 
        preg_match('/outline-color\s*:\s*(transparent)/i', $css)) {
        $errors[] = "Focus Indicators: Custom CSS is not allowed to hide focus outlines (e.g., using 'outline: none' or 'outline: 0').";
    }

    // 2. Accessibility Checks: Hiding Skip Links & Screen Reader Text
    if (preg_match('/\.skip-link\b[^{]*\{[^}]*display\s*:\s*none/i', $css) ||
        preg_match('/\.skip-link\b[^{]*\{[^}]*visibility\s*:\s*hidden/i', $css) ||
        preg_match('/\.skip-link\b[^{]*\{[^}]*opacity\s*:\s*0/i', $css) ||
        preg_match('/\.sr-only\b[^{]*\{[^}]*display\s*:\s*none/i', $css) ||
        preg_match('/\.sr-only\b[^{]*\{[^}]*visibility\s*:\s*hidden/i', $css)) {
        $errors[] = "Accessibility: Hiding skip links (`.skip-link`) or screen-reader-only text (`.sr-only`) is prohibited.";
    }

    // 3. Security Checks: Block external stylesheet imports
    if (preg_match('/@import\s+/i', $css)) {
        $errors[] = "Security: Custom stylesheet imports (`@import`) are prohibited to prevent external assets from loading.";
    }

    // 4. Security Checks: Block malicious url(...) payloads (external protocols, tracking scripts, and javascript)
    if (preg_match_all('/url\s*\(([^)]+)\)/i', $css, $urlMatches)) {
        foreach ($urlMatches[1] as $rawUrl) {
            $cleanUrl = trim($rawUrl, " \t\n\r\0\x0B\"'");
            // Allow relative image paths but block absolute urls, javascript, and data-uris
            if (preg_match('/^(https?:|ftp:|javascript:|data:|chrome:|\/\/)/i', $cleanUrl)) {
                $errors[] = "Security: External resource URLs or data/script URIs inside `url()` are prohibited to prevent tracking, data leakage, and script execution.";
            }
        }
    }

    // 5. Security Checks: Block legacy browser CSS script injection tricks
    if (preg_match('/behavior\s*:/i', $css) || 
        preg_match('/expression\s*\(/i', $css) || 
        preg_match('/-moz-binding/i', $css)) {
        $errors[] = "Security: Legacy style-based script injections (such as `behavior`, `expression`, or `-moz-binding`) are prohibited.";
    }

    // 6. Contrast Checks: Brand Variable Color Contratios (against light and dark limits)
    if (preg_match_all('/--color-primary\s*:\s*(#[a-f0-9]{3,6})/i', $css, $matches)) {
        foreach ($matches[1] as $color) {
            $hex = expandHexColor($color);
            if (getContrastRatio($hex, '#ffffff') < 4.5) {
                $errors[] = "Contrast Check: Your custom --color-primary override ({$color}) fails the WCAG 2.2 AA contrast ratio of 4.5:1 against white text.";
            }
        }
    }
    if (preg_match_all('/--color-accent\s*:\s*(#[a-f0-9]{3,6})/i', $css, $matches)) {
        foreach ($matches[1] as $color) {
            $hex = expandHexColor($color);
            if (getContrastRatio($hex, '#ffffff') < 4.5 && getContrastRatio($hex, '#0f172a') < 4.5) {
                $errors[] = "Contrast Check: Your custom --color-accent override ({$color}) does not meet 4.5:1 contrast against either light (#ffffff) or dark (#0f172a) backgrounds.";
            }
        }
    }

    return empty($errors);
}

function expandHexColor($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    return '#' . $hex;
}

/**
 * Returns a normalised #rrggbb string, or null if the input is not a valid hex colour.
 *
 * Tenant branding values are written into a <style> block. htmlspecialchars() prevents an
 * HTML breakout but does nothing against CSS injection — a value such as
 * "red; } .skip-link { display:none } .x{" would previously pass through intact and could
 * disable the platform's own accessibility guards. Branding colours are whitelisted to
 * hex notation instead of escaped.
 *
 * @param mixed $value
 * @return string|null
 */
function sanitizeHexColor($value) {
    if (!is_string($value)) {
        return null;
    }
    $candidate = trim($value);
    if (!preg_match('/^#?([a-f0-9]{3}|[a-f0-9]{6})$/i', $candidate)) {
        return null;
    }
    return strtolower(expandHexColor($candidate));
}

/**
 * Returns a URL only if it uses a scheme safe to place in an href, otherwise null.
 *
 * Accepts absolute http(s) URLs and site-relative paths. Rejects javascript:, data:,
 * vbscript:, and protocol-relative URLs. Use for any URL that originates from tenant
 * metadata or an uploaded course manifest.
 *
 * @param mixed $value
 * @param bool $allowRelative
 * @return string|null
 */
function sanitizeUrl($value, $allowRelative = true) {
    if (!is_string($value)) {
        return null;
    }
    $candidate = trim($value);
    if ($candidate === '') {
        return null;
    }

    // Strip control characters and whitespace that can be used to smuggle a scheme
    // past a naive prefix check (e.g. "java\tscript:alert(1)").
    $normalised = strtolower(preg_replace('/[\x00-\x20]/', '', $candidate));

    if (preg_match('#^(javascript|data|vbscript|file|about|blob):#', $normalised)) {
        return null;
    }
    if (strpos($normalised, '//') === 0) {
        return null; // protocol-relative
    }
    if (preg_match('#^https?://#', $normalised)) {
        return $candidate;
    }
    if ($allowRelative && !preg_match('#^[a-z][a-z0-9+.\-]*:#', $normalised)) {
        return $candidate;
    }
    return null;
}

/**
 * Darkens a hex color by a given percentage to achieve compliant WCAG contrast.
 */
function darkenHexColor($hex, $percent = 0.15) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) return '#33684b';

    $r = max(0, min(255, (int)(hexdec(substr($hex, 0, 2)) * (1 - $percent))));
    $g = max(0, min(255, (int)(hexdec(substr($hex, 2, 2)) * (1 - $percent))));
    $b = max(0, min(255, (int)(hexdec(substr($hex, 4, 2)) * (1 - $percent))));

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Lightens a hex color by a given percentage for dark mode contrast against dark backgrounds.
 */
function lightenHexColor($hex, $percent = 0.35) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) return '#72b08a';

    $r = min(255, (int)(hexdec(substr($hex, 0, 2)) + (255 - hexdec(substr($hex, 0, 2))) * $percent));
    $g = min(255, (int)(hexdec(substr($hex, 2, 2)) + (255 - hexdec(substr($hex, 2, 2))) * $percent));
    $b = min(255, (int)(hexdec(substr($hex, 4, 2)) + (255 - hexdec(substr($hex, 4, 2))) * $percent));

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Renders dynamic CSS variable overrides based on active tenant branding metadata
 * with automated WCAG 2.2 AA contrast validation, font selection, and dark mode support.
 *
 * @param string|null $tenantKey
 * @return string HTML style or link tags
 */
function renderTenantBrandingCss($tenantKey = null) {
    $meta = getTenantMetadata($tenantKey);
    $out = '';
    
    // Font selection loading
    $fontFamily = $meta['font_family'] ?? 'Atkinson Hyperlegible';
    $fontMap = [
        'Atkinson Hyperlegible' => 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&display=swap',
        'Inter'                 => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        'Roboto'                => 'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;1,400&display=swap',
        'Open Sans'             => 'https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap',
        'Lexend'                => 'https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&display=swap'
    ];
    if (isset($fontMap[$fontFamily])) {
        $out .= '<link rel="stylesheet" href="' . htmlspecialchars($fontMap[$fontFamily]) . '">' . "\n";
    }
    
    $styles = [];
    $darkStyles = [];

    $styles[] = "--font-family-base: '" . htmlspecialchars($fontFamily) . "', sans-serif;";

    if (!empty($meta['branding']) && is_array($meta['branding'])) {
        $b = $meta['branding'];

        // Whitelist every branding value to hex notation before it reaches the <style> block.
        foreach (['primary', 'primary_hover', 'secondary', 'accent', 'bg_light', 'text_dark'] as $brandKey) {
            if (isset($b[$brandKey])) {
                $safeColor = sanitizeHexColor($b[$brandKey]);
                if ($safeColor === null) {
                    error_log("Rejected invalid tenant branding colour for '{$brandKey}' on tenant [" . ($meta['tenant_key'] ?? 'unknown') . "]");
                    unset($b[$brandKey]);
                } else {
                    $b[$brandKey] = $safeColor;
                }
            }
        }

        // Primary color contrast check against white (#FFFFFF)
        if (!empty($b['primary'])) {
            $primaryHex = $b['primary'];
            $ratio = getContrastRatio($primaryHex, '#ffffff');
            
            // If primary fails WCAG AA (4.5:1), progressively darken for light mode
            if ($ratio < 4.5) {
                $safePrimary = $primaryHex;
                for ($p = 0.1; $p <= 0.5; $p += 0.05) {
                    $candidate = darkenHexColor($primaryHex, $p);
                    if (getContrastRatio($candidate, '#ffffff') >= 4.5) {
                        $safePrimary = $candidate;
                        break;
                    }
                }
                $primaryHex = $safePrimary;
            }
            
            $styles[] = "--color-primary: " . htmlspecialchars($primaryHex) . ";";
            
            // Generate or validate primary_hover
            $hoverHex = !empty($b['primary_hover']) ? $b['primary_hover'] : darkenHexColor($primaryHex, 0.15);
            $styles[] = "--color-primary-hover: " . htmlspecialchars($hoverHex) . ";";
            
            // Calculate lightened primary for dark mode surface (#0F172A)
            $darkPrimary = lightenHexColor($primaryHex, 0.40);
            if (getContrastRatio($darkPrimary, '#0F172A') < 4.5) {
                for ($lp = 0.45; $lp <= 0.8; $lp += 0.05) {
                    $candidateDark = lightenHexColor($primaryHex, $lp);
                    if (getContrastRatio($candidateDark, '#0F172A') >= 4.5) {
                        $darkPrimary = $candidateDark;
                        break;
                    }
                }
            }
            $darkStyles[] = "--color-primary: " . htmlspecialchars($darkPrimary) . ";";
            $darkStyles[] = "--color-primary-hover: " . htmlspecialchars(lightenHexColor($darkPrimary, 0.15)) . ";";
        }

        if (!empty($b['secondary'])) $styles[] = "--color-secondary: " . htmlspecialchars($b['secondary']) . ";";
        if (!empty($b['accent'])) {
            $styles[] = "--color-accent: " . htmlspecialchars($b['accent']) . ";";
            $darkStyles[] = "--color-accent: " . htmlspecialchars(lightenHexColor($b['accent'], 0.25)) . ";";
        }
        if (!empty($b['bg_light'])) $styles[] = "--color-bg-light: " . htmlspecialchars($b['bg_light']) . ";";
        if (!empty($b['text_dark'])) $styles[] = "--color-text-dark: " . htmlspecialchars($b['text_dark']) . ";";
    }
    
    $out .= "<style>\n:root {\n    " . implode("\n    ", $styles) . "\n}\n";
    if (!empty($darkStyles)) {
        $out .= "[data-theme=\"dark\"] {\n    " . implode("\n    ", $darkStyles) . "\n}\n";
    }
    $out .= "</style>\n";
    
    $tenantKeyClean = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
    $tenantPlan = getTenantPlan($tenantKeyClean);
    if ($tenantPlan === 'premium') {
        $customCssPath = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKeyClean . DIRECTORY_SEPARATOR . 'custom.css';
        if (file_exists($customCssPath)) {
            $out .= '<link rel="stylesheet" href="courses/tenants/' . htmlspecialchars($tenantKeyClean) . '/custom.css">' . "\n";
        }
    }
    
    return $out;
}

/**
 * Renders the universal tenant footer with client copyright attribution, support, terms, privacy, and Superable Learning backlink.
 *
 * @param string|null $tenantKey
 * @return string HTML footer markup
 */
function renderTenantFooter($tenantKey = null) {
    $meta = getTenantMetadata($tenantKey);
    $tenantKeyClean = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
    
    // Determine copyright holder name & website link
    $copyrightName = !empty($meta['copyright_notice']) 
        ? $meta['copyright_notice'] 
        : (!empty($meta['name']) ? $meta['name'] : 'Superable Learning');
        
    // Tenant-supplied URLs are whitelisted to http(s) / relative before rendering.
    $websiteUrl = !empty($meta['website_url']) ? sanitizeUrl($meta['website_url'], false) : null;
    $supportContact = !empty($meta['support_contact']) ? $meta['support_contact'] : null;
    $termsUrl = !empty($meta['terms_url']) ? (sanitizeUrl($meta['terms_url']) ?? tenant_url('terms.php')) : tenant_url('terms.php');
    $privacyUrl = !empty($meta['privacy_url']) ? (sanitizeUrl($meta['privacy_url']) ?? tenant_url('privacy.php')) : tenant_url('privacy.php');
    $accessibilityUrl = tenant_url('accessibility.php');

    $year = date('Y');
    $platformUrl = 'https://superablelearning.com';
    
    ob_start();
    ?>
    <footer class="site-footer">
        <div class="container-wide">
            <ul class="footer-nav">
                <li><a href="<?= tenant_url('index.php') ?>" class="footer-link">LMS Portal Home</a></li>
                <?php if ($websiteUrl): ?>
                    <li><a href="<?= htmlspecialchars($websiteUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer-link">Organization Main Site ↗</a></li>
                <?php endif; ?>
                <?php
                // Only emit the support link for schemes we trust. Tenant metadata is
                // admin-supplied, and htmlspecialchars() does not neutralise a
                // "javascript:" or "data:" URL.
                $supportHref = null;
                if ($supportContact) {
                    if (strpos($supportContact, '@') !== false && stripos($supportContact, 'http') !== 0) {
                        $supportHref = 'mailto:' . $supportContact;
                    } elseif (preg_match('#^https?://#i', $supportContact)) {
                        $supportHref = $supportContact;
                    }
                }
                ?>
                <?php if ($supportHref): ?>
                    <li><a href="<?= htmlspecialchars($supportHref) ?>" class="footer-link">Contact Support</a></li>
                <?php endif; ?>
                <li><a href="<?= tenant_url('help.php') ?>" class="footer-link">Help & Docs</a></li>
                <li><a href="<?= htmlspecialchars($termsUrl) ?>" <?= (strpos($termsUrl, 'http') === 0) ? 'target="_blank" rel="noopener noreferrer"' : '' ?> class="footer-link">Terms of Service</a></li>
                <li><a href="<?= htmlspecialchars($privacyUrl) ?>" <?= (strpos($privacyUrl, 'http') === 0) ? 'target="_blank" rel="noopener noreferrer"' : '' ?> class="footer-link">Privacy Policy</a></li>
                <li><a href="<?= htmlspecialchars($accessibilityUrl) ?>" class="footer-link">Accessibility Statement</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                        <li><a href="<?= tenant_url('admin.php') ?>" class="footer-link">Admin Panel</a></li>
                    <?php endif; ?>
                    <li><a href="<?= tenant_url('logout.php') ?>" class="footer-link">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?= tenant_url('login.php') ?>" class="footer-link">Log In</a></li>
                    <li><a href="<?= tenant_url('register.php') ?>" class="footer-link">Register</a></li>
                <?php endif; ?>
            </ul>
            
            <p class="footer-copy mb-1">
                &copy; <?= $year ?> 
                <?php if ($websiteUrl): ?>
                    <a href="<?= htmlspecialchars($websiteUrl) ?>" target="_blank" rel="noopener noreferrer" style="color: #E2E8F0; text-decoration: underline;"><?= htmlspecialchars($copyrightName) ?></a>
                <?php else: ?>
                    <?= htmlspecialchars($copyrightName) ?>
                <?php endif; ?>. 
                All rights reserved.
            </p>
            <p class="footer-copy text-xs" style="color: #94A3B8;">Powered by <a href="<?= $platformUrl ?>" target="_blank" rel="noopener noreferrer" style="color: #CBD5E0; text-decoration: underline;">Superable Learning</a> — Accessible E-Learning Engine</p>
        </div>
    </footer>
    <?php
    return ob_get_clean();
}

// Backwards compatibility constant DB_PATH dynamically pointing to current tenant DB
define('DB_PATH', getDbPath());

/**
 * Validates database path security to ensure it isn't inside the web root.
 *
 * @param string $dbPath
 */
function validate_database_security($dbPath) {
    $dbDir = realpath(dirname($dbPath));
    $webRoot = realpath(LMS_ROOT);

    if ($dbDir && $webRoot && strpos($dbDir, $webRoot) === 0) {
        die("Security Error: The database folder must be located outside of the public web root.");
    }
}

/**
 * SuperableDatabase wrapper class that extends PDO.
 * Isolates SQL queries and provides a translation hook for database migrations (e.g. SQLite to PostgreSQL).
 */
class SuperableDatabase extends PDO {
    private $tenantKey;

    public function __construct($dsn, $username = null, $password = null, $options = null, $tenantKey = null) {
        parent::__construct($dsn, $username, $password, $options);
        $this->tenantKey = $tenantKey;
    }

    public function getTenantKey() {
        return $this->tenantKey;
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = []) {
        $translatedQuery = $this->translateQuery($query);
        return parent::prepare($translatedQuery, $options);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetchModeArgs) {
        $translatedQuery = $this->translateQuery($query);
        if ($fetchMode === null) {
            return parent::query($translatedQuery);
        }
        return parent::query($translatedQuery, $fetchMode, ...$fetchModeArgs);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement) {
        $translatedQuery = $this->translateQuery($statement);
        return parent::exec($translatedQuery);
    }

    /**
     * Translates database queries to handle SQL syntax variations across databases.
     * Ready to be expanded for PostgreSQL routing.
     */
    private function translateQuery($sql) {
        // Future translation mappings go here
        return $sql;
    }
}

/**
 * Returns a configured SuperableDatabase instance connected to the tenant SQLite database.
 *
 * @param string|null $tenantKey
 * @return SuperableDatabase
 */
function get_db_connection($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();

    $dbPath = getDbPath($tenantKey);
    $dbDir = dirname($dbPath);
    $storageDir = getStoragePath($tenantKey);
    
    if (!is_dir($dbDir)) {
        if (!@mkdir($dbDir, 0755, true)) {
            error_log("Failed to create database directory at: " . $dbDir);
            die("System Error: The database directory does not exist and could not be created automatically.");
        }
    }

    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }

    validate_database_security($dbPath);

    try {
        $pdo = new SuperableDatabase('sqlite:' . $dbPath, null, null, null, $tenantKey);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        ensure_tables_exist($pdo);
        
        // Auto-login from Remember Me Cookie (scoped to this connection's tenant)
        check_remember_me_cookie($pdo, $tenantKey);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Failed for tenant [{$tenantKey}]: " . $e->getMessage());
        die("System Error: Unable to connect to the LMS Engine database.");
    }
}

/**
 * Ensures all required database tables exist for the active tenant.
 * 
 * @param PDO $pdo
 */
function ensure_tables_exist($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            full_name TEXT NOT NULL,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            course_id TEXT NOT NULL,
            UNIQUE(user_id, course_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS invitation_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_code TEXT UNIQUE NOT NULL,
            course_id TEXT,
            uses_remaining INTEGER DEFAULT -1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS module_progress (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            course_id TEXT NOT NULL,
            module_id TEXT NOT NULL,
            is_completed INTEGER DEFAULT 0,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, course_id, module_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS interaction_telemetry (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            course_id TEXT NOT NULL,
            module_id TEXT NOT NULL,
            event_type TEXT NOT NULL,
            event_value TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS auth_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            ip_address TEXT,
            attempted_at INTEGER NOT NULL
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_auth_attempts_lookup ON auth_attempts (identifier, attempted_at)");

        // NOTE: No default administrator is seeded here.
        //
        // A tenant database is created on demand for any tenant key that appears in the
        // request, so seeding a known admin credential (previously id=1 with the password
        // 'password123') meant anyone could provision a tenant and immediately log into it
        // as an administrator. Provision real tenants through platform_admin.php, or the
        // CLI utilities setup_tenant.php / reset_admin.php.

    } catch (PDOException $e) {
        error_log("Schema Initialization Error: " . $e->getMessage());
    }
}

/**
 * Hostnames permitted to launch a restricted course through an external xAPI/LRS wrapper.
 *
 * Build Capable XCL wraps the player in its own frame and launches courses with standard
 * xAPI launch parameters. Previously the player granted access whenever an `endpoint` and
 * `auth` parameter were merely PRESENT — their values were never inspected — so appending
 * "&endpoint=x&auth=y" to any course URL unlocked it for anyone.
 *
 * A launch is now accepted only when the endpoint resolves to one of these hosts (or a
 * subdomain of one). Extend via the tenant metadata key `xapi_launch_hosts`.
 */
const SL_DEFAULT_XAPI_LAUNCH_HOSTS = [
    'buildxcl.com',
];

/**
 * Validates an external xAPI launch against the allowlist.
 *
 * @param array $query Usually $_GET
 * @param string|null $tenantKey
 * @return bool True when this is a trusted external launch
 */
function isTrustedXapiLaunch(array $query, $tenantKey = null) {
    $endpoint = $query['endpoint'] ?? $query['xAPILaunchService'] ?? null;
    $credential = $query['auth'] ?? $query['xAPILaunchKey'] ?? null;

    // Both an endpoint and a credential must be present and non-empty.
    if (empty($endpoint) || empty($credential) || !is_string($endpoint)) {
        return false;
    }

    $host = parse_url(trim($endpoint), PHP_URL_HOST);
    if (!$host) {
        return false;
    }
    $host = strtolower($host);

    $allowed = SL_DEFAULT_XAPI_LAUNCH_HOSTS;
    $meta = getTenantMetadata($tenantKey);
    if (!empty($meta['xapi_launch_hosts']) && is_array($meta['xapi_launch_hosts'])) {
        foreach ($meta['xapi_launch_hosts'] as $extraHost) {
            if (is_string($extraHost) && $extraHost !== '') {
                $allowed[] = strtolower(trim($extraHost));
            }
        }
    }

    foreach ($allowed as $allowedHost) {
        if ($host === $allowedHost || substr($host, -strlen('.' . $allowedHost)) === '.' . $allowedHost) {
            return true;
        }
    }

    error_log("Rejected external xAPI launch from unrecognised endpoint host [{$host}]. "
            . "If this is a legitimate launch platform, add it to SL_DEFAULT_XAPI_LAUNCH_HOSTS "
            . "in config.php or to the tenant's xapi_launch_hosts metadata array.");
    return false;
}

/**
 * Records a failed authentication attempt for throttling purposes.
 *
 * @param PDO $pdo Tenant database connection
 * @param string $identifier Email address or other subject of the attempt
 */
function record_auth_failure($pdo, $identifier) {
    try {
        $stmt = $pdo->prepare("INSERT INTO auth_attempts (identifier, ip_address, attempted_at) VALUES (?, ?, ?)");
        $stmt->execute([
            strtolower(trim($identifier)),
            $_SERVER['REMOTE_ADDR'] ?? null,
            time()
        ]);
    } catch (PDOException $e) {
        error_log("Failed to record auth attempt: " . $e->getMessage());
    }
}

/**
 * Clears recorded failures for an identifier after a successful authentication.
 */
function clear_auth_failures($pdo, $identifier) {
    try {
        $stmt = $pdo->prepare("DELETE FROM auth_attempts WHERE identifier = ?");
        $stmt->execute([strtolower(trim($identifier))]);
    } catch (PDOException $e) {
        error_log("Failed to clear auth attempts: " . $e->getMessage());
    }
}

/**
 * Returns the number of seconds a subject must wait before retrying, or 0 if not throttled.
 *
 * Counts failures against both the supplied identifier and the client IP inside the
 * window, so neither password spraying across accounts nor brute forcing one account
 * slips through.
 *
 * @param PDO $pdo Tenant database connection
 * @param string $identifier Email address being attempted
 * @param int $maxAttempts Failures permitted inside the window
 * @param int $windowSeconds Length of the sliding window
 * @return int Seconds remaining in the lockout, or 0
 */
function auth_throttle_retry_after($pdo, $identifier, $maxAttempts = 8, $windowSeconds = 900) {
    try {
        $cutoff = time() - $windowSeconds;

        // Opportunistically prune expired rows so the table cannot grow without bound.
        $prune = $pdo->prepare("DELETE FROM auth_attempts WHERE attempted_at < ?");
        $prune->execute([$cutoff]);

        $stmt = $pdo->prepare("
            SELECT MAX(attempted_at) AS newest, COUNT(*) AS failures
            FROM auth_attempts
            WHERE attempted_at >= ? AND (identifier = ? OR ip_address = ?)
        ");
        $stmt->execute([
            $cutoff,
            strtolower(trim($identifier)),
            $_SERVER['REMOTE_ADDR'] ?? '__no_ip__'
        ]);
        $row = $stmt->fetch();

        if ($row && (int)$row['failures'] >= $maxAttempts) {
            $retryAfter = ((int)$row['newest'] + $windowSeconds) - time();
            return max(1, $retryAfter);
        }
    } catch (PDOException $e) {
        error_log("Auth throttle check failed: " . $e->getMessage());
    }
    return 0;
}

/**
 * Returns the effective plan for the current tenant, respecting developer overrides.
 */
function getTenantPlan($tenantKey = null) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (isset($_SESSION['user_id']) && isset($_SESSION['dev_override_plan'])) {
        if (!empty($_SESSION['is_dev'])) {
            return $_SESSION['dev_override_plan'];
        }
    }
    
    $meta = getTenantMetadata($tenantKey);
    $plan = strtolower($meta['plan'] ?? 'sandbox');
    
    // Normalize plans to standard names: sandbox, pro, premium
    if ($plan === 'standard') {
        return 'pro';
    }
    if ($plan === 'developer' || $plan === 'sandbox') {
        return 'sandbox';
    }
    return $plan; // sandbox, pro, premium
}

/**
 * Returns the storage quota limit (in MB) for the active plan.
 */
function getTenantStorageQuota($tenantKey = null) {
    $plan = getTenantPlan($tenantKey);
    switch ($plan) {
        case 'sandbox':
            return 250;
        case 'pro':
            return 500;
        case 'premium':
            return 1000; // 1 GB
        default:
            return 500;
    }
}

/**
 * Renders the developer toolbar at the top of the page if active.
 */
function renderDevToolbar() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['is_dev'])) {
        return '';
    }
    
    // Handle dev override post requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_dev_plan') {
        // CSRF check
        $token = $_POST['csrf_token'] ?? '';
        if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            $selectedPlan = $_POST['dev_plan'] ?? 'default';
            if (in_array($selectedPlan, ['sandbox', 'pro', 'premium', 'default'])) {
                if ($selectedPlan === 'default') {
                    unset($_SESSION['dev_override_plan']);
                } else {
                    $_SESSION['dev_override_plan'] = $selectedPlan;
                }
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }
    
    $currentPlan = getTenantPlan();
    $overridePlan = $_SESSION['dev_override_plan'] ?? 'default';
    $csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '');
    
    $html = '
    <div style="background: #0f172a; border-bottom: 2px solid #3b7a57; padding: 0.5rem 1rem; font-family: Atkinson Hyperlegible, sans-serif; font-size: 0.85rem; color: #f8fafc; display: flex; align-items: center; justify-content: space-between; z-index: 99999; position: relative;">
        <div>
            <strong style="color: #38bdf8;">🔧 Developer Mode:</strong> 
            Active Plan View: <span style="background: #1e293b; color: #34d399; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-weight: bold; text-transform: uppercase; font-size: 0.75rem;">' . htmlspecialchars($currentPlan) . '</span>
            ' . ($overridePlan !== 'default' ? '<span style="color: #fca5a5; font-size: 0.75rem; margin-left: 0.5rem;">(Session Override Active)</span>' : '') . '
        </div>
        <form method="POST" action="" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <input type="hidden" name="action" value="set_dev_plan">
            <input type="hidden" name="csrf_token" value="' . $csrf . '">
            <label for="dev_plan" style="color: #94a3b8; font-weight: bold; margin-bottom: 0;">Switch View Tier:</label>
            <select name="dev_plan" id="dev_plan" style="background: #1e293b; color: #f8fafc; border: 1px solid #475569; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.8rem; cursor: pointer; font-family: inherit;">
                <option value="default" ' . ($overridePlan === 'default' ? 'selected' : '') . '>Default (From Tenant File)</option>
                <option value="sandbox" ' . ($overridePlan === 'sandbox' ? 'selected' : '') . '>Sandbox (Free)</option>
                <option value="pro" ' . ($overridePlan === 'pro' ? 'selected' : '') . '>Pro ($10/mo)</option>
                <option value="premium" ' . ($overridePlan === 'premium' ? 'selected' : '') . '>Premium ($20/mo)</option>
            </select>
            <button type="submit" class="btn btn-sm" style="background-color: #3b7a57; color: white; border: none; padding: 0.2rem 0.6rem; border-radius: 0.25rem; font-size: 0.8rem; font-weight: bold; cursor: pointer; font-family: inherit; line-height: 1.2;">Apply</button>
        </form>
    </div>
    ';
    return $html;
}

/**
 * Logs an admin activity if the plan is Premium.
 */
function logTenantActivity($action, $details = '') {
    $tenantPlan = getTenantPlan();
    if ($tenantPlan !== 'premium') {
        return; // Only log for Premium plan
    }
    
    $tenantKey = resolveTenantKey();
    $storageDir = getStoragePath($tenantKey);
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }
    
    $logFile = $storageDir . DIRECTORY_SEPARATOR . 'activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['full_name'] ?? 'System';
    $userId = $_SESSION['user_id'] ?? 0;
    
    $logLine = "[{$timestamp}] User: {$user} (ID: {$userId}) | Action: {$action} | Details: {$details}" . PHP_EOL;
    @file_put_contents($logFile, $logLine, FILE_APPEND);
}

/**
 * Resolves a course directory by checking the primary tenant directory and fallbacks.
 *
 * @param string $course_id
 * @param string|null $tenantKey
 * @return string|null Absolute path to course directory, or null if not found
 */
function resolveCourseDir($course_id, $tenantKey = null) {
    if (empty($course_id)) {
        return null;
    }
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
    $primaryCoursesDir = getTenantCoursesDir($tenantKey);
    $course_dir = $primaryCoursesDir . DIRECTORY_SEPARATOR . basename($course_id);

    if (is_dir($course_dir) && file_exists($course_dir . DIRECTORY_SEPARATOR . 'course_structure.json')) {
        return $course_dir;
    }

    $fallbackDirs = [
        LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . 'superableaccessibility',
        LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . 'platform',
        LMS_ROOT . DIRECTORY_SEPARATOR . 'courses'
    ];

    $tenantsBaseDir = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants';
    if (is_dir($tenantsBaseDir)) {
        foreach (scandir($tenantsBaseDir) as $tFolder) {
            if ($tFolder === '.' || $tFolder === '..') continue;
            $fallbackDirs[] = $tenantsBaseDir . DIRECTORY_SEPARATOR . $tFolder;
        }
    }

    foreach ($fallbackDirs as $fDir) {
        $candidate = $fDir . DIRECTORY_SEPARATOR . basename($course_id);
        if (is_dir($candidate) && file_exists($candidate . DIRECTORY_SEPARATOR . 'course_structure.json')) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Recursively resolves actual H1 titles for modules from their local files.
 *
 * @param array $items
 * @param string $course_dir
 */
function pre_process_manifest_modules(&$items, $course_dir) {
    if (!is_array($items)) {
        return;
    }
    foreach ($items as &$item) {
        if (isset($item['group'])) {
            pre_process_manifest_modules($item['items'], $course_dir);
        } else if (isset($item['src'])) {
            $file_path = $course_dir . DIRECTORY_SEPARATOR . $item['src'];
            if (file_exists($file_path)) {
                $html_content = file_get_contents($file_path);
                if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html_content, $matches)) {
                    $item['h1_title'] = strip_tags($matches[1]);
                }
            }
        }
    }
}

/**
 * Checks for a remember-me cookie and logs the user in if a valid token exists.
 * 
 * @param PDO $pdo
 */
function check_remember_me_cookie($pdo, $connectionTenantKey = null) {
    // If already logged in, do nothing
    if (isset($_SESSION['user_id'])) {
        return;
    }

    $tenantKey = $connectionTenantKey ? sanitizeTenantKey($connectionTenantKey) : resolveTenantKey();

    // Only auto-login for the tenant this request actually resolved to. Helper code opens
    // connections to other tenants (is_platform_admin() opens platform, for example), and
    // without this guard a remember-me cookie issued for tenant A would be validated
    // against tenant B's users table — where the same numeric id belongs to someone else.
    if ($tenantKey !== resolveTenantKey()) {
        return;
    }

    $cookieName = 'remember_me_' . $tenantKey;

    if (empty($_COOKIE[$cookieName])) {
        return;
    }

    $cookieValue = $_COOKIE[$cookieName];
    $parts = explode(':', $cookieValue, 2);
    if (count($parts) !== 2) {
        clear_remember_me_cookie($tenantKey);
        return;
    }

    list($userId, $token) = $parts;
    $userId = (int)$userId;
    $tokenHash = hash('sha256', $token);

    try {
        // Query the active tenant DB for token and user
        $stmt = $pdo->prepare("
            SELECT t.id as token_id, u.id as user_id, u.email, u.full_name, u.is_admin 
            FROM user_remember_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.user_id = ? AND t.token_hash = ? AND t.expires_at > datetime('now')
        ");
        $stmt->execute([$userId, $tokenHash]);
        $user = $stmt->fetch();

        if ($user) {
            // Re-establish session
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            // Bind the restored identity to the tenant whose cookie authenticated it,
            // so it cannot be carried into another tenant via ?tenant=.
            bind_session_to_tenant($tenantKey, [
                'user_id'   => $user['user_id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'is_admin'  => (bool)$user['is_admin'],
            ]);

            // Rotate the token (delete old, create new)
            $stmtDel = $pdo->prepare("DELETE FROM user_remember_tokens WHERE id = ?");
            $stmtDel->execute([$user['token_id']]);

            $newToken = bin2hex(random_bytes(32));
            $newTokenHash = hash('sha256', $newToken);
            // UTC, to match the datetime('now') comparison in the lookup query above.
            // Using local time here expired tokens early (or late) on any non-UTC server.
            $expires = gmdate('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

            $stmtIns = $pdo->prepare("INSERT INTO user_remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmtIns->execute([$user['user_id'], $newTokenHash, $expires]);

            set_remember_me_cookie($tenantKey, $user['user_id'], $newToken);
        } else {
            // Invalid or expired token
            clear_remember_me_cookie($tenantKey);
            $stmtClean = $pdo->prepare("DELETE FROM user_remember_tokens WHERE expires_at <= datetime('now')");
            $stmtClean->execute();
        }
    } catch (PDOException $e) {
        error_log("Remember me auto-login failed: " . $e->getMessage());
    }
}

/**
 * Helper to set a secure remember-me cookie.
 */
function set_remember_me_cookie($tenantKey, $userId, $token) {
    $cookieName = 'remember_me_' . $tenantKey;
    $cookieValue = $userId . ':' . $token;
    
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    $options = [
        'expires' => time() + (30 * 24 * 60 * 60), // 30 days
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => $is_https ? 'None' : 'Lax'
    ];
    
    setcookie($cookieName, $cookieValue, $options);
}

/**
 * Helper to clear the remember-me cookie.
 */
function clear_remember_me_cookie($tenantKey) {
    $cookieName = 'remember_me_' . $tenantKey;
    
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $options = [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => $is_https ? 'None' : 'Lax'
    ];

    setcookie($cookieName, '', $options);
}

// ---------------------------------------------------------------------------
// Tenant session binding enforcement
//
// Runs on every request that includes config.php, before any page logic reads
// $_SESSION['user_id'] or $_SESSION['is_admin']. A session authenticated against one
// tenant is demoted to guest when the request resolves to a different tenant.
// ---------------------------------------------------------------------------
enforce_tenant_session_binding();
