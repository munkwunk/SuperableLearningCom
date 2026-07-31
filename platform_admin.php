<?php
/**
 * Superable Learning - Platform Super Admin Client Manager
 * 
 * Allows platform administrators to provision new client tenants, view tenant disk
 * usage, manage custom domain mappings, and suspend/activate client subscriptions.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/setup_tenant.php';

// Force connection to main platform database (platform) for Super Admin authentication
$platformTenant = 'platform';
$pdo = get_db_connection($platformTenant);

// Security Check: Require Super Admin on the PLATFORM database.
//
// This previously checked only $_SESSION['is_admin'], a flag set by logging into ANY
// tenant. Because a tenant database was created on demand for any ?tenant= value and was
// seeded with a known admin credential, that made every tenant admin — and in effect any
// anonymous visitor — a platform super administrator. is_platform_admin() requires an
// identity authenticated against platform specifically, and re-verifies the admin flag
// against the platform database instead of trusting the session.
if (!is_platform_admin()) {
    header("Location: login.php?tenant=platform");
    exit;
}

$message = '';
$message_type = 'success';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();

    switch ($_POST['action']) {
        case 'provision_tenant':
            $tenantKey  = sanitizeTenantKey($_POST['tenant_key'] ?? '');
            $clientName = trim($_POST['client_name'] ?? '');
            $domain     = trim($_POST['domain'] ?? '');
            $plan       = trim($_POST['plan'] ?? 'sandbox');
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPass  = $_POST['admin_password'] ?? '';

            if (empty($tenantKey) || empty($clientName) || empty($adminEmail)) {
                $message = "Error: Tenant Key, Client Name, and Admin Email are required.";
                $message_type = 'critical';
            } elseif (strlen($adminPass) < 12) {
                // No shared default password. Every provisioned tenant gets an explicit,
                // unique administrator credential chosen at provisioning time.
                $message = "Error: An administrator password of at least 12 characters is required to provision a tenant.";
                $message_type = 'critical';
            } else {
                $dbDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
                $jsonPath = $dbDir . DIRECTORY_SEPARATOR . $tenantKey . '.json';
                $sqlitePath = $dbDir . DIRECTORY_SEPARATOR . $tenantKey . '.sqlite';

                if (file_exists($jsonPath) || file_exists($sqlitePath)) {
                    $message = "Error: Tenant key '{$tenantKey}' already exists. Please use a unique key.";
                    $message_type = 'critical';
                } else {
                    $res = provision_tenant_account($tenantKey, $clientName, $domain, $plan, $adminEmail, $adminPass);
                    $message = $res['message'];
                    $message_type = $res['success'] ? 'success' : 'critical';
                }
            }
            break;

        case 'toggle_tenant_status':
            $targetKey = sanitizeTenantKey($_POST['tenant_key'] ?? '');
            $newStatus = $_POST['new_status'] === 'suspended' ? 'suspended' : 'active';
            $dbDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
            $jsonPath = $dbDir . DIRECTORY_SEPARATOR . $targetKey . '.json';

            if (file_exists($jsonPath)) {
                $meta = json_decode(file_get_contents($jsonPath), true);
                $meta['status'] = $newStatus;
                file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT));
                $message = "Tenant '{$targetKey}' status updated to " . strtoupper($newStatus) . ".";
                $message_type = 'success';
            }
            break;

        case 'change_tenant_plan':
            $targetKey = sanitizeTenantKey($_POST['tenant_key'] ?? '');
            $newPlan   = trim($_POST['plan'] ?? 'sandbox');

            if (in_array($newPlan, ['sandbox', 'pro', 'premium'])) {
                $dbDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
                $jsonPath = $dbDir . DIRECTORY_SEPARATOR . $targetKey . '.json';

                if (file_exists($jsonPath)) {
                    $meta = json_decode(file_get_contents($jsonPath), true);
                    $meta['plan'] = $newPlan;
                    file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT));
                    $message = "Tenant '{$targetKey}' plan updated to " . strtoupper($newPlan) . ".";
                    $message_type = 'success';
                } else {
                    $message = "Error: Tenant metadata file not found.";
                    $message_type = 'critical';
                }
            } else {
                $message = "Error: Invalid plan selection.";
                $message_type = 'critical';
            }
            break;

        case 'save_custom_domain':
            $customDomain = strtolower(trim($_POST['custom_domain'] ?? ''));
            $targetKey    = sanitizeTenantKey($_POST['tenant_key'] ?? '');

            if ($customDomain && $targetKey) {
                $map = getCustomDomainMap();
                $map[$customDomain] = $targetKey;
                $mapFile = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'custom_domains.json';
                @file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));
                $message = "Mapped domain '{$customDomain}' -> tenant '{$targetKey}'.";
                $message_type = 'success';
            }
            break;
    }
}

// Discover All Provisioned Tenants
$tenants = [];
$dbDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';

if (is_dir($dbDir)) {
    foreach (scandir($dbDir) as $file) {
        if (substr($file, -5) === '.json') {
            $key = substr($file, 0, -5);
            $meta = getTenantMetadata($key);
            $bytes = getTenantStorageUsage($key);
            $meta['storage_mb'] = round($bytes / 1048576, 2);
            $tenants[] = $meta;
        }
    }
}

// Get Custom Domain Mappings
$customDomainMap = getCustomDomainMap();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Super Admin — Client Manager</title>
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss() ?>
</head>
<body>
    <a href="#admin-main" class="skip-link">Skip to main content</a>

    <header class="bg-primary py-8 text-white" style="background: #1a202c;">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div>
                <h1 class="m-0 text-2xl" style="color: white;">Platform Super Admin Dashboard</h1>
                <p class="m-0 mt-1 text-sm" style="color: #FFFFFF;">Superable Learning SaaS Client & Tenant Management</p>
            </div>
            <div class="flex gap-6 items-center">
                <a href="index.php" class="text-white font-bold">← Platform Homepage</a>
                <a href="logout.php" class="text-white font-bold" style="padding: 0.5rem 1rem; border: 2px solid white; border-radius: 0.25rem;">Logout Super Admin</a>
            </div>
        </div>
    </header>

    <main id="admin-main" class="container mx-auto px-4 admin-container">
        
        <?php if ($message): ?>
            <div role="status" aria-live="polite" class="p-4 mb-8" style="border-radius: 0.5rem; background: var(--color-<?= $message_type ?>-bg); border: 1px solid var(--color-<?= $message_type ?>-border); color: var(--color-<?= $message_type ?>-text);">
                <strong>Platform Notice:</strong> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Provision New Client Tenant -->
        <section class="admin-section content-card">
            <h2>Provision New Client Tenant</h2>
            <p class="text-neutral-mid mb-6">Create an isolated SQLite database, tenant storage, metadata, and administrator account for a new SaaS client.</p>

            <form method="POST">
                <input type="hidden" name="action" value="provision_tenant">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="tenant_key">Tenant Key (Slug / Directory Identifier)</label>
                        <input type="text" name="tenant_key" id="tenant_key" required placeholder="e.g. acme-corp" pattern="[a-zA-Z0-9\-_]+" title="Only letters, numbers, hyphens, and underscores allowed.">
                        <small class="text-neutral-mid">Subdomain will be: <code>tenantkey.superablelearning.com</code></small>
                    </div>

                    <div class="form-group">
                        <label for="client_name">Client / Organization Name</label>
                        <input type="text" name="client_name" id="client_name" required placeholder="e.g. Acme Corporation">
                    </div>

                    <div class="form-group">
                        <label for="domain">Custom Domain / Subdomain (Optional)</label>
                        <input type="text" name="domain" id="domain" placeholder="e.g. acme.superablelearning.com">
                    </div>

                    <div class="form-group">
                        <label for="plan">Subscription Plan</label>
                        <select name="plan" id="plan">
                            <option value="sandbox">Sandbox (Free - 250 MB Cap)</option>
                            <option value="pro">Pro ($10/mo - 500 MB Cap)</option>
                            <option value="premium">Premium ($20/mo - 1 GB Cap)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="admin_email">Client Admin Email</label>
                        <input type="email" name="admin_email" id="admin_email" required placeholder="admin@acme.com">
                    </div>

                    <div class="form-group">
                        <label for="admin_password">Client Admin Initial Password</label>
                        <p class="text-sm" id="admin-password-hint" style="margin: 0.25rem 0 0.5rem 0;">
                            At least 12 characters. Use a unique, randomly generated value for each client and share it through a secure channel. There is deliberately no default.
                        </p>
                        <input type="text" name="admin_password" id="admin_password" value="" required minlength="12"
                               autocomplete="off" spellcheck="false" aria-describedby="admin-password-hint">
                    </div>
                </div>

                <button type="submit" class="cta-button" style="background: #2b6cb0;">Provision Client Tenant</button>
            </form>
        </section>

        <!-- Active Client Tenants List -->
        <section class="admin-section content-card">
            <h2>Active Platform Tenants (<?= count($tenants) ?> Clients)</h2>

            <table>
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Tenant Key</th>
                        <th>Domain / Subdomain</th>
                        <th>Plan</th>
                        <th>Storage Used</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($t['tenant_key']) ?></code></td>
                            <td><?= htmlspecialchars($t['domain']) ?></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 0.25rem; align-items: center; margin: 0; min-width: 140px;">
                                    <input type="hidden" name="action" value="change_tenant_plan">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="tenant_key" value="<?= htmlspecialchars($t['tenant_key']) ?>">
                                    <select name="plan" aria-label="Change plan for <?= htmlspecialchars($t['name']) ?>" style="padding: 0.25rem; font-size: 0.85rem; margin: 0; border: 1px solid var(--color-neutral-mid); border-radius: 0.25rem; background-color: var(--color-bg-light); color: var(--color-text-dark); cursor: pointer; width: auto; height: auto;">
                                        <?php 
                                        $currentPlan = getTenantPlan($t['tenant_key']); 
                                        ?>
                                        <option value="sandbox" <?= $currentPlan === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                        <option value="pro" <?= $currentPlan === 'pro' ? 'selected' : '' ?>>Pro</option>
                                        <option value="premium" <?= $currentPlan === 'premium' ? 'selected' : '' ?>>Premium</option>
                                    </select>
                                    <button type="submit" class="cta-button" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; margin: 0; background: #2b6cb0; min-height: auto; height: auto; border: none; border-radius: 0.25rem; color: white; cursor: pointer; line-height: 1.2;">Set</button>
                                </form>
                            </td>
                            <td><?= $t['storage_mb'] ?> MB / <?= getTenantStorageQuota($t['tenant_key']) ?> MB</td>
                            <td>
                                <span class="badge badge-<?= $t['status'] === 'active' ? 'active' : 'suspended' ?>">
                                    <?= htmlspecialchars(strtoupper($t['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="index.php?tenant=<?= urlencode($t['tenant_key']) ?>" target="_blank" class="cta-button" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">View LMS</a>
                                    <a href="admin.php?tenant=<?= urlencode($t['tenant_key']) ?>" target="_blank" class="cta-button" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background: #4a5568;">Manage</a>

                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_tenant_status">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="tenant_key" value="<?= htmlspecialchars($t['tenant_key']) ?>">
                                        <input type="hidden" name="new_status" value="<?= $t['status'] === 'active' ? 'suspended' : 'active' ?>">
                                        <button type="submit" class="cta-button" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background: <?= $t['status'] === 'active' ? '#c53030' : '#276749' ?>;">
                                            <?= $t['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <!-- Custom Domain Mapping -->
        <section class="admin-section content-card">
            <h2>Custom Domain Mapping (Bring Your Own Domain)</h2>
            <p class="text-neutral-mid mb-6">Map custom client domains (e.g. <code>clientdomain.com</code>) to a specific tenant key.</p>

            <form method="POST" class="grid md:grid-cols-3 gap-4 mb-6">
                <input type="hidden" name="action" value="save_custom_domain">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="custom_domain">Custom Domain</label>
                    <input type="text" name="custom_domain" id="custom_domain" placeholder="e.g. clientdomain.com" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="target_tenant_key">Target Tenant Key</label>
                    <select name="tenant_key" id="target_tenant_key" required>
                        <option value="">-- Select Tenant --</option>
                        <?php foreach ($tenants as $t): ?>
                            <option value="<?= htmlspecialchars($t['tenant_key']) ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['tenant_key']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="cta-button">Map Custom Domain</button>
                </div>
            </form>

            <?php if (!empty($customDomainMap)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Custom Domain</th>
                            <th>Mapped Tenant Key</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customDomainMap as $cdomain => $tkey): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($cdomain) ?></code></td>
                                <td><code><?= htmlspecialchars($tkey) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
