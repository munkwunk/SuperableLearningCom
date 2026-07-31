<?php
/**
 * Superable Learning - Accessibility Statement Page
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
    <title>Accessibility Statement — <?= htmlspecialchars($tenantMetadata['name']) ?></title>
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
            <h1>Accessibility Statement</h1>
            <p class="text-sm text-neutral-mid mb-8" style="margin-top: -0.5rem;">Last Updated: July 23, 2026</p>
            
            <p class="text-lg mb-8 text-neutral-mid">
                We believe that learning is for everyone. Superable Learning is committed to providing a digital learning environment that is accessible, inclusive, and usable by learners of all abilities.
            </p>
            
            <section class="mb-8">
                <h2>Conformance Status</h2>
                <p class="mb-4">
                    The Web Content Accessibility Guidelines (WCAG) define requirements for designers and developers to improve accessibility for people with disabilities. It defines three levels of conformance: Level A, Level AA, and Level AAA.
                </p>
                <p class="mb-4">
                    Superable Learning is engineered to be conformant with <strong>WCAG 2.2 Level AA</strong> standards.
                </p>
            </section>
            
            <section class="mb-8">
                <h2>Built-in Accessibility Features</h2>
                <p class="mb-4">
                    Our platform core integrates specific structural controls to guarantee a compliant learning experience:
                </p>
                <ul class="standard-list mb-4">
                    <li><strong>Assistive Technology Support:</strong> Structured layout markup optimized for screen readers (NVDA, JAWS, VoiceOver) including descriptive element roles and keyboard navigation focus guards.</li>
                    <li><strong>Dyslexia-Friendly Typography:</strong> Built-in support for Atkinson Hyperlegible fonts (developed by the Braille Institute) designed to increase character recognition and readability.</li>
                    <li><strong>Dynamic Contrast Guards:</strong> Our design system automatically darkens light brand colors to preserve a minimum 4.5:1 text-to-background contrast ratio in light mode, and shifts surface tokens appropriately in dark mode.</li>
                    <li><strong>Keyboard Navigation:</strong> All learning modules, interactive buttons, settings, and player timelines are fully operable via standard keyboard controls. Explicit visual focus indicator outlines are maintained at all times.</li>
                    <li><strong>Accessible Multimedia Player:</strong> Our course player supports closed-caption integrations, transcript overlays, speed controls, and absolute keyboard focus traps.</li>
                </ul>
            </section>
            
            <section class="mb-8">
                <h2>Technical Specifications</h2>
                <p class="mb-4">
                    The accessibility of Superable Learning relies on the following technologies to work with the particular combination of web browser and any assistive technologies or plugins installed on your computer:
                </p>
                <ul class="standard-list mb-4">
                    <li>HTML5</li>
                    <li>WAI-ARIA</li>
                    <li>CSS / Custom Properties</li>
                    <li>Vanilla JavaScript</li>
                </ul>
            </section>
            
            <section class="mb-8">
                <h2>Feedback & Accessibility Support</h2>
                <p class="mb-4">
                    We welcome your feedback on the accessibility of this platform. If you encounter any accessibility barriers while using Superable Learning, please let us know:
                </p>
                <ul class="standard-list mb-4">
                    <li><strong>Platform Support Desk:</strong> Contact your organization's LMS admin or submit a documentation query in the Help Center.</li>
                    <li>We aim to review accessibility queries and respond to feedback within 3 business days.</li>
                </ul>
            </section>
        </article>
    </main>

    <?= renderTenantFooter($activeTenant) ?>
</body>
</html>
