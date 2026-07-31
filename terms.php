<?php
/**
 * Superable Learning - Terms of Service Page
 */

require_once 'config.php';
$pdo = get_db_connection();

$activeTenant = resolveTenantKey();
$isPlatformSite = ($activeTenant === 'platform' && empty($_GET['tenant']));

$is_guest = !isset($_SESSION['user_id']);
$user_id = $is_guest ? 'guest_' . session_id() : $_SESSION['user_id'];
$current_user_name = $_SESSION['full_name'] ?? "Guest";
$is_admin = $_SESSION['is_admin'] ?? false;

$tenantMetadata = getTenantMetadata($activeTenant);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service — <?= htmlspecialchars($tenantMetadata['name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss($activeTenant) ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Unified Header Navigation -->
    <header class="site-header">
        <div class="container-wide header-inner">
            <?php if ($activeTenant === 'platform'): ?>
                <div class="brand-group">
                    <a href="index.php" class="brand-title">Superable Learning</a>
                    <span class="badge-platform">PLATFORM</span>
                </div>
                <nav class="nav-links" aria-label="Platform Main Navigation">
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="pricing.php" class="nav-link">Pricing</a>
                    <a href="help.php" class="nav-link">Help & Docs</a>
                    <?php if (!$is_guest && $is_admin): ?>
                        <a href="platform_admin.php" class="btn btn-teal btn-sm">Platform Admin</a>
                    <?php endif; ?>
                    <?php if ($is_guest): ?>
                        <a href="login.php?tenant=platform" class="btn btn-outline-light btn-sm">Sign In</a>
                    <?php else: ?>
                        <span class="text-sm" style="color: white; margin-right: 0.5rem;">Logged in as <strong><?= htmlspecialchars($current_user_name) ?></strong></span>
                        <a href="logout.php" class="nav-link text-sm">Logout</a>
                    <?php endif; ?>
                </nav>
            <?php else: ?>
                <div class="brand-group">
                    <?php 
                    $tenantLogo = !empty($tenantMetadata['logo_url']) ? $tenantMetadata['logo_url'] : 'Superable-Learning-Logo.svg';
                    $heroTitle = !empty($tenantMetadata['hero_headline']) ? $tenantMetadata['hero_headline'] : $tenantMetadata['name'];
                    $heroSub = !empty($tenantMetadata['hero_subheadline']) ? $tenantMetadata['hero_subheadline'] : 'Accessible E-Learning Portal';
                    $websiteUrl = !empty($tenantMetadata['website_url']) ? $tenantMetadata['website_url'] : null;
                    ?>
                    <?php if ($websiteUrl): ?>
                        <a href="<?= htmlspecialchars($websiteUrl) ?>" target="_blank" rel="noopener noreferrer" title="Visit <?= htmlspecialchars($tenantMetadata['name']) ?> Main Site">
                            <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="<?= htmlspecialchars($tenantMetadata['name']) ?> Logo" class="brand-logo">
                        </a>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="<?= htmlspecialchars($tenantMetadata['name']) ?> Logo" class="brand-logo">
                    <?php endif; ?>
                    <div>
                        <h1 class="brand-title"><?= htmlspecialchars($heroTitle) ?></h1>
                        <p class="text-sm" style="margin:0; color: #FFFFFF;"><?= htmlspecialchars($heroSub) ?></p>
                    </div>
                </div>
                <div class="nav-links">
                    <a href="<?= tenant_url('index.php') ?>" class="nav-link">Dashboard</a>
                    <a href="<?= tenant_url('help.php') ?>" class="nav-link">Help & Docs</a>
                    <?php if (!$is_guest): ?>
                        <span class="text-sm">Hello, <strong><?= htmlspecialchars($current_user_name) ?></strong></span>
                        <?php if ($is_admin): ?>
                            <a href="<?= tenant_url('admin.php') ?>" class="btn btn-teal btn-sm">Admin Panel</a>
                        <?php endif; ?>
                        <a href="<?= tenant_url('logout.php') ?>" class="btn btn-outline-light btn-sm">Logout</a>
                    <?php else: ?>
                        <a href="<?= tenant_url('login.php') ?>" class="btn btn-sm">Log In</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main id="main-content" class="container-wide main-content py-8">
        <article class="card p-8" style="background: white;">
            <h1>Terms of Service</h1>
            <p class="text-sm text-neutral-mid mb-8" style="margin-top: -0.5rem;">Last Updated: July 23, 2026</p>
            
            <p class="text-lg mb-8 text-neutral-mid">
                Welcome to Superable Learning. By accessing this learning portal, you agree to comply with and be bound by the following terms of use.
            </p>
            
            <section class="mb-8">
                <h2>1. Acceptance of Terms</h2>
                <p class="mb-4">
                    By using this service, you represent that you have read and understood these Terms of Service. If you are browsing on behalf of an organization, you agree that your organization accepts these terms.
                </p>
            </section>
            
            <section class="mb-8">
                <h2>2. Account Security & Verification</h2>
                <p class="mb-4">
                    To access private course materials, you must register an account and enter a valid Invitation Key provided by your organization admin. You are solely responsible for:
                </p>
                <ul class="standard-list mb-4">
                    <li>Maintaining the confidentiality of your credentials.</li>
                    <li>Restricting unauthorized access to your devices.</li>
                    <li>Promptly notifying administrators if you suspect an account compromise.</li>
                </ul>
            </section>
            
            <section class="mb-8">
                <h2>3. Acceptable Use Guidelines</h2>
                <p class="mb-4">
                    When using this platform, you agree NOT to:
                </p>
                <ul class="standard-list mb-4">
                    <li>Upload malicious course files, viruses, or custom scripts designed to exploit database structures.</li>
                    <li>Attempt to bypass database isolation container guards or access other tenant folders.</li>
                    <li>Scrape content or engage in automated interactions that degrade platform stability.</li>
                    <li>Violate the intellectual property rights of content authors or course publishers.</li>
                </ul>
            </section>
            
            <section class="mb-8">
                <h2>4. Intellectual Property</h2>
                <p class="mb-4">
                    All course content, packages, SCORM manifests, and organization logos uploaded to this dashboard remain the property of their respective owners. The core LMS software, custom player structures, and accessible web components (`sl-` / `jw-`) are intellectual property owned by Superable Learning.
                </p>
            </section>
            
            <section class="mb-8">
                <h2>5. Subscription Billing & Quota Enforcement</h2>
                <p class="mb-4">
                    For system administrators: active plan tiers (Sandbox, Pro, Premium) dictate usage thresholds (storage caps and admin seat limitations). Exceeding these quotas will result in prompts to upgrade or temporary restriction of upload privileges.
                </p>
            </section>
            
            <section class="mb-8">
                <h2>6. Limitation of Liability</h2>
                <p class="mb-4">
                    This platform is provided on an "as is" and "as available" basis without warranties of any kind. Superable Learning does not guarantee that the service will be entirely uninterrupted or error-free. Under no circumstances shall we be liable for lost progress details, service downtimes, or indirect operational damages.
                </p>
            </section>
        </article>
    </main>

    <?= renderTenantFooter($activeTenant) ?>
</body>
</html>
