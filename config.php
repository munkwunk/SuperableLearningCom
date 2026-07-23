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
ini_set('error_log', __DIR__ . '/error.log');

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
}

// Generate CSRF Token for Form Security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Verifies CSRF Token on sensitive POST requests.
 */
function verify_csrf_token() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("Security Error: Invalid or expired CSRF security token. Please refresh the page and try again.");
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
    return !empty($clean) ? $clean : 'local-dev';
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
 * 5. Main domain fallback ('local-dev')
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

    // 5. Active Session Tenant Key (preserves tenant during local query param navigation)
    if ($host !== 'localhost' && $host !== PRIMARY_DOMAIN) {
        if (!empty($_SESSION['active_tenant_key'])) {
            return $_SESSION['active_tenant_key'];
        }
    } else {
        // If on primary domain and no explicit tenant parameter is provided in query,
        // clear any session overrides to allow loading the main index.php platform landing page.
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_GET['tenant'])) {
            unset($_SESSION['active_tenant_key']);
        }
    }

    // 6. Default Fallback Key for Main Platform Site
    return 'local-dev';
}

/**
 * Helper to construct internal URLs while preserving active tenant query parameter.
 *
 * @param string $path
 * @return string
 */
function tenant_url($path) {
    $activeTenant = resolveTenantKey();
    if ($activeTenant !== 'local-dev') {
        $sep = (strpos($path, '?') !== false) ? '&' : '?';
        return $path . $sep . 'tenant=' . urlencode($activeTenant);
    }
    return $path;
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
function getTenantMetadata($tenantKey = null) {
    $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();

    $tenantsDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
    $jsonPath = $tenantsDir . DIRECTORY_SEPARATOR . $tenantKey . '.json';

    if (file_exists($jsonPath)) {
        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }
    }

    $defaultMeta = [
        'tenant_key' => $tenantKey,
        'name'       => ucfirst(str_replace(['-', '_'], ' ', $tenantKey)),
        'domain'     => ($tenantKey === 'local-dev') ? PRIMARY_DOMAIN : $tenantKey . '.' . PRIMARY_DOMAIN,
        'plan'       => 'standard',
        'created'    => date('Y-m-d H:i:s'),
        'status'     => 'active'
    ];

    if (is_dir($tenantsDir) || @mkdir($tenantsDir, 0755, true)) {
        @file_put_contents($jsonPath, json_encode($defaultMeta, JSON_PRETTY_PRINT));
    }

    return $defaultMeta;
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
                if ($key === 'local-dev') continue;
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
        
    $websiteUrl = !empty($meta['website_url']) ? $meta['website_url'] : null;
    $supportContact = !empty($meta['support_contact']) ? $meta['support_contact'] : null;
    $termsUrl = !empty($meta['terms_url']) ? $meta['terms_url'] : null;
    $privacyUrl = !empty($meta['privacy_url']) ? $meta['privacy_url'] : null;

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
                <?php if ($supportContact): ?>
                    <?php 
                    $supportHref = (strpos($supportContact, '@') !== false && strpos($supportContact, 'http') === false) 
                        ? 'mailto:' . htmlspecialchars($supportContact) 
                        : htmlspecialchars($supportContact);
                    ?>
                    <li><a href="<?= $supportHref ?>" class="footer-link">Contact Support</a></li>
                <?php endif; ?>
                <li><a href="<?= tenant_url('help.php') ?>" class="footer-link">Help & Docs</a></li>
                <?php if ($termsUrl): ?>
                    <li><a href="<?= htmlspecialchars($termsUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer-link">Terms of Service</a></li>
                <?php endif; ?>
                <?php if ($privacyUrl): ?>
                    <li><a href="<?= htmlspecialchars($privacyUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer-link">Privacy Policy</a></li>
                <?php endif; ?>
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

        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (id, email, password_hash, full_name, is_admin) VALUES (1, 'jacob@jacobwood.me', ?, 'Jacob Wood', 1)");
            $stmt->execute([password_hash('password123', PASSWORD_DEFAULT)]);
        }

    } catch (PDOException $e) {
        error_log("Schema Initialization Error: " . $e->getMessage());
    }
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
        LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . 'local-dev',
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
