<?php
/**
 * Superable Learning - Tenant Provisioning & Migration Helper
 * 
 * CLI Usage:
 *   php setup_tenant.php <tenant_key> [client_name] [domain] [plan] [admin_email] [admin_password]
 * 
 * Examples:
 *   php setup_tenant.php local-dev "Local Dev" superablelearning.com standard admin@superablelearning.com password123
 *   php setup_tenant.php tenant-001 "Tenant 001" tenant1.superablelearning.com premium admin@tenant1.com password123
 */

require_once __DIR__ . '/config.php';

/**
 * Programmatically provisions a new client tenant (directories, metadata, database, and admin user).
 *
 * @return array Result array with success status and message
 */
function provision_tenant_account($tenantKey, $clientName = null, $domain = null, $plan = 'standard', $adminEmail = null, $adminPassword = 'password123') {
    $tenantKey = sanitizeTenantKey($tenantKey);
    $clientName = $clientName ? trim($clientName) : ucfirst(str_replace(['-', '_'], ' ', $tenantKey));
    $adminEmail = $adminEmail ? trim($adminEmail) : 'admin@' . ($domain ?: $tenantKey . '.' . PRIMARY_DOMAIN);

    $dbDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
    $jsonPath = $dbDir . DIRECTORY_SEPARATOR . $tenantKey . '.json';
    $sqlitePath = $dbDir . DIRECTORY_SEPARATOR . $tenantKey . '.sqlite';
    $storagePath = getStoragePath($tenantKey);
    $coursesPath = getTenantCoursesDir($tenantKey);

    // 1. Ensure directories
    if (!is_dir($dbDir)) @mkdir($dbDir, 0755, true);
    if (!is_dir($storagePath)) @mkdir($storagePath, 0755, true);
    if (!is_dir($coursesPath)) @mkdir($coursesPath, 0755, true);

    // 2. Create/Update Metadata JSON
    $metadata = [
        'tenant_key' => $tenantKey,
        'name'       => $clientName,
        'domain'     => $domain ? $domain : (($tenantKey === 'local-dev') ? PRIMARY_DOMAIN : $tenantKey . '.' . PRIMARY_DOMAIN),
        'plan'       => $plan,
        'created'    => date('Y-m-d H:i:s'),
        'status'     => 'active'
    ];
    file_put_contents($jsonPath, json_encode($metadata, JSON_PRETTY_PRINT));

    // 3. Initialize Database Schema and Admin User
    try {
        $pdo = get_db_connection($tenantKey);
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO users (id, email, password_hash, full_name, is_admin) VALUES (1, ?, ?, ?, 1)");
        $stmt->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $clientName . ' Admin']);

        return [
            'success' => true,
            'message' => "Tenant '{$clientName}' ({$tenantKey}) provisioned successfully!",
            'tenant_key' => $tenantKey,
            'db_path' => $sqlitePath,
            'admin_email' => $adminEmail
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Database initialization failed: " . $e->getMessage()
        ];
    }
}

// Only execute CLI output if run directly from command line
if (php_sapi_name() === 'cli' && isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === 'setup_tenant.php') {
    $tenantKey  = $argv[1] ?? 'local-dev';
    $clientName = $argv[2] ?? null;
    $domain     = $argv[3] ?? null;
    $plan       = $argv[4] ?? 'standard';
    $adminEmail = $argv[5] ?? null;
    $adminPass  = $argv[6] ?? 'password123';

    echo "==========================================" . PHP_EOL;
    echo "  Superable Learning Tenant Provisioning  " . PHP_EOL;
    echo "==========================================" . PHP_EOL;
    
    $res = provision_tenant_account($tenantKey, $clientName, $domain, $plan, $adminEmail, $adminPass);
    if ($res['success']) {
        echo "Tenant Key:   {$res['tenant_key']}" . PHP_EOL;
        echo "DB Path:      {$res['db_path']}" . PHP_EOL;
        echo "Admin Email:  {$res['admin_email']}" . PHP_EOL;
        echo "[+] Provisioning Complete!" . PHP_EOL;
    } else {
        echo "[!] Error: {$res['message']}" . PHP_EOL;
    }
    echo "==========================================" . PHP_EOL;
}
