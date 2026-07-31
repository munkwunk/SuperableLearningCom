<?php
/**
 * Superable Learning - Privacy Policy Page
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
    <title>Privacy Policy — <?= htmlspecialchars($tenantMetadata['name']) ?></title>
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
            <h1>Privacy Policy</h1>
            <p class="text-sm text-neutral-mid mb-8" style="margin-top: -0.5rem;">Last Updated: July 23, 2026</p>
            
            <p class="text-lg mb-8 text-neutral-mid">
                Your privacy is important to us. This policy explains what information Superable Learning collects, why we collect it, and how your data is protected.
            </p>
            
            <section class="mb-8">
                <h2>1. Who are we?</h2>
                <p class="mb-4">
                    We are Superable Learning (SuperableLearning.com), an accessibility-first multi-tenant learning management system. If you have any privacy-related questions or need to exercise your data rights, you can reach out directly via your organization's support contact or our platform support links in the documentation.
                </p>
            </section>
            
            <section class="mb-8">
                <h2>2. What data do we collect?</h2>
                <p class="mb-4">
                    We collect information necessary to provide secure learning portals, course package rendering, and learner progress tracking. This includes:
                </p>
                <ul class="standard-list mb-4">
                    <li><strong>Contact Information:</strong> Names, email addresses, and account credentials (such as encrypted password hashes).</li>
                    <li><strong>Progress & Telemetry Data:</strong> Course completions, quiz responses, time spent per module, and user engagement logs.</li>
                    <li><strong>Technical Logs:</strong> IP addresses, browser types, and standard access details required to maintain platform security and performance.</li>
                    <li><strong>Payment Information:</strong> For system subscriptions, payments are processed securely by Stripe. We do not store credit card numbers on our servers.</li>
                </ul>
            </section>
            
            <section class="mb-8">
                <h2>3. Why do we collect this data?</h2>
                <ul class="standard-list mb-4">
                    <li><strong>To deliver training:</strong> To manage course enrollments, track learner certifications, and maintain progress indicators.</li>
                    <li><strong>To optimize accessibility:</strong> To ensure that standard screens, custom components, and players load correctly for screen readers and assistive devices.</li>
                    <li><strong>To enforce security:</strong> To maintain strict isolated multi-tenant database access (ensuring user records are never commingled between organizations).</li>
                </ul>
            </section>
            
            <section class="mb-8">
                <h2>4. Third-Party Services</h2>
                <p class="mb-4">
                    We utilize secure third-party integrations to run our infrastructure:
                </p>
                <ul class="standard-list mb-4">
                    <li><strong>Stripe:</strong> Processes payment checkout sessions. See <a href="https://stripe.com/privacy" target="_blank" rel="noopener noreferrer">Stripe's Privacy Policy</a>.</li>
                    <li><strong>SQLite Integration:</strong> Data is written into isolated client-specific database container files. We do not sell or monetize personal data to third parties.</li>
                </ul>
            </section>
            
            <section class="mb-8">
                <h2>5. Your Rights</h2>
                <p class="mb-4">
                    Depending on your location, you may have rights under the GDPR, CCPA, or regional compliance frameworks, including:
                </p>
                <ul class="standard-list mb-4">
                    <li><strong>Right to Access:</strong> Requesting a copy of your personal data stored in our system.</li>
                    <li><strong>Right to Rectification:</strong> Updating inaccurate account or profile information.</li>
                    <li><strong>Right to Erasure:</strong> Requesting the deletion of your account records.</li>
                    <li><strong>Right to Data Portability:</strong> Transferring progress and enrollment histories.</li>
                </ul>
                <p class="mb-4">
                    To exercise these rights, please contact your organization's LMS Administrator or Superable Learning platform support.
                </p>
            </section>
            
            <section class="mb-8">
                <h2>6. Updates to this Policy</h2>
                <p class="mb-4">
                    We may update this policy periodically to reflect platform upgrades or operational adjustments. Any changes will be posted on this page, and the "Last Updated" date will be revised accordingly.
                </p>
            </section>
        </article>
    </main>

    <?= renderTenantFooter($activeTenant) ?>
</body>
</html>
