<?php
/**
 * Superable Learning LMS - Flexible Pricing & Strategy Overview
 */

require_once 'config.php';

$activeTenant = resolveTenantKey();
$availableTenants = getAvailableTenants();

$is_guest = !isset($_SESSION['user_id']);
$current_user_name = $_SESSION['full_name'] ?? "Guest";
$is_admin = $_SESSION['is_admin'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing & Plans — Superable Learning LMS</title>
    <meta name="description" content="Transparent, accessible pricing plans for Superable Learning LMS. Isolated multi-tenant databases, WCAG 2.2 AA compliance, and flexible course engine.">
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss('local-dev') ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Navigation Header -->
    <header class="site-header">
        <div class="container-wide header-inner">
            <div class="brand-group">
                <a href="index.php" class="brand-title">Superable Learning</a>
                <span class="badge-platform">PLATFORM</span>
            </div>
            <nav class="nav-links" aria-label="Main Navigation">
                <a href="index.php" class="nav-link">Home</a>
                <a href="index.php#features" class="nav-link">Features</a>
                <a href="pricing.php" class="nav-link active" aria-current="page" style="color: var(--color-accent); font-weight: 700;">Pricing</a>
                <a href="help.php" class="nav-link">Help & Docs</a>
                <a href="index.php#workspace-finder" class="nav-link">Find Workspace</a>
                <?php if (!$is_guest && $is_admin): ?>
                    <a href="platform_admin.php" class="btn btn-teal btn-sm">Platform Admin</a>
                <?php endif; ?>
                <?php if ($is_guest): ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm">Sign In</a>
                <?php else: ?>
                    <span class="text-sm" style="color: white;">Logged in as <strong><?= htmlspecialchars($current_user_name) ?></strong></span>
                    <a href="logout.php" class="nav-link text-sm">Logout</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Pricing Hero Banner -->
    <section class="hero-banner">
        <div class="container text-center">
            <div class="mb-3">
                <span class="badge-wcag">
                    <span class="badge-wcag-dot"></span> WCAG 2.2 AA Certified Engine
                </span>
            </div>
            <h1 style="color: white; margin-bottom: 1rem;" class="text-4xl md:text-5xl font-bold">Predictable Plans for Every Scale</h1>
            <p class="hero-subtitle max-w-3xl" style="margin-left: auto; margin-right: auto;">
                Isolated multi-tenant SQLite database architecture, WCAG 2.2 AA accessibility built-in, and zero hidden fees. Choose a plan or customize limits for your enterprise deployment.
            </p>
        </div>
    </section>

    <!-- Main Content Area -->
    <main id="main-content" class="container-wide main-content">
        
        <!-- Configurable Strategy Callout Banner -->
        <div role="region" aria-label="Strategy Notice" class="card mb-4" style="background-color: var(--color-ocean-light); border: 2px dashed var(--color-primary);">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="h3-style" style="margin-top: 0; color: var(--color-primary);">Strategy Configurator Active</h2>
                    <p class="mb-0 text-sm" style="color: var(--color-text-dark);">
                        Note: Storage quotas, learner seat limits, and billing frequencies are fully configurable per tenant deployment. Platform administrators can alter tenant plans and quotas directly inside the <strong>Platform Client Manager</strong>.
                    </p>
                </div>
                <div>
                    <?php if (!$is_guest && $is_admin): ?>
                        <a href="platform_admin.php" class="btn btn-primary" style="white-space: nowrap;">Configure Tenant Limits &rarr;</a>
                    <?php else: ?>
                        <a href="login.php?tenant=local-dev" class="btn btn-primary" style="white-space: nowrap;">Admin Sign In &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pricing Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8" id="pricing-grid">
            
            <!-- Tier 1: Developer / Single Tenant -->
            <div class="pricing-card">
                <h2 style="font-size: 1.5rem; margin-top: 0; color: var(--color-primary);">Developer & Sandbox</h2>
                <p class="text-neutral-mid text-sm">Perfect for course creators, testing HTML/SCORM packages, and single-tenant trials.</p>
                <div class="pricing-price">$0 <span>/ month</span></div>
                <p class="text-xs text-neutral-mid">Free forever for single local/dev tenant</p>

                <ul class="pricing-features">
                    <li>1 Isolated Tenant Database</li>
                    <li>WCAG 2.2 AA Accessible Player</li>
                    <li>Up to 50 Active Learners</li>
                    <li>250 MB Storage Quota</li>
                    <li>Standard HTML & LC-JSON Importer</li>
                    <li>Community & Markdown Docs</li>
                </ul>

                <a href="index.php?tenant=local-dev" class="btn btn-outline-light text-center" style="color: var(--color-primary); border-color: var(--color-primary);">Explore Local Demo</a>
            </div>

            <!-- Tier 2: Standard Organization (Featured) -->
            <div class="pricing-card featured">
                <span class="pricing-card-badge">Most Popular</span>
                <h2 style="font-size: 1.5rem; margin-top: 0; color: var(--color-primary);">Standard Organization</h2>
                <p class="text-neutral-mid text-sm">Dedicated tenant isolation for schools, non-profits, and growing companies.</p>
                <div class="pricing-price">$199 <span>/ month</span></div>
                <p class="text-xs text-neutral-mid">Billed annually or $229/mo billed monthly</p>

                <ul class="pricing-features">
                    <li>Dedicated Tenant SQLite Database</li>
                    <li>500 MB Isolated Storage Quota</li>
                    <li>Up to 500 Active Learners</li>
                    <li>Dynamic Brand CSS Customization</li>
                    <li>Custom Subdomain Access</li>
                    <li>Invitation Course Code Management</li>
                    <li>NVDA, JAWS & VoiceOver Tested</li>
                    <li>Email & Ticket Support</li>
                </ul>

                <a href="index.php#workspace-finder" class="btn btn-primary text-center">Get Started / Find Workspace</a>
            </div>

            <!-- Tier 3: Enterprise Multi-Tenant -->
            <div class="pricing-card">
                <h2 style="font-size: 1.5rem; margin-top: 0; color: var(--color-primary);">Enterprise Multi-Tenant</h2>
                <p class="text-neutral-mid text-sm">For multi-division enterprises, government agencies, and custom LMS networks.</p>
                <div class="pricing-price">$499 <span>/ month</span></div>
                <p class="text-xs text-neutral-mid">Custom SLA & provisioning available</p>

                <ul class="pricing-features">
                    <li>Unlimited Tenant Databases</li>
                    <li>Custom Domain Mapping (JSON/DNS)</li>
                    <li>Custom CSS Stylesheet Overrides</li>
                    <li>Unlimited Learners & Courses</li>
                    <li>5 GB Base Storage (Expandable)</li>
                    <li>Automated Tenant Provisioning API</li>
                    <li>Priority Accessibility Auditing</li>
                    <li>Dedicated Account Manager</li>
                </ul>

                <a href="help.php" class="btn btn-outline-light text-center" style="color: var(--color-primary); border-color: var(--color-primary);">Contact Platform Team</a>
            </div>

        </div>

        <!-- Detailed Feature Matrix Table -->
        <section class="card mb-8">
            <h2 class="h3-style" style="margin-top: 0;">Detailed Plan Feature Matrix</h2>
            <p class="text-neutral-mid mb-4">Compare capabilities across all Superable Learning architecture tiers.</p>
            
            <div style="overflow-x: auto;">
                <table class="table-styled" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--color-primary); text-align: left;">
                            <th style="padding: 0.75rem;">Feature / Capability</th>
                            <th style="padding: 0.75rem;">Developer</th>
                            <th style="padding: 0.75rem;">Standard Org</th>
                            <th style="padding: 0.75rem;">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Database Isolation</td>
                            <td style="padding: 0.75rem;">Shared Sandbox SQLite</td>
                            <td style="padding: 0.75rem;">Dedicated Tenant SQLite</td>
                            <td style="padding: 0.75rem;">Dedicated Tenant SQLite</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">WCAG 2.2 AA Compliance</td>
                            <td style="padding: 0.75rem;">✓ Full Engine</td>
                            <td style="padding: 0.75rem;">✓ Full Engine</td>
                            <td style="padding: 0.75rem;">✓ Full Engine + Custom Audits</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Custom Subdomain & Domains</td>
                            <td style="padding: 0.75rem;">Localhost / Query Param</td>
                            <td style="padding: 0.75rem;">Subdomain (org.superablelearning.com)</td>
                            <td style="padding: 0.75rem;">Custom Domain (learn.client.com)</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Brand CSS Overrides</td>
                            <td style="padding: 0.75rem;">Basic Colors</td>
                            <td style="padding: 0.75rem;">Dynamic CSS Variables</td>
                            <td style="padding: 0.75rem;">Full custom.css + Fonts</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Course Formats Supported</td>
                            <td style="padding: 0.75rem;">HTML / LC-JSON</td>
                            <td style="padding: 0.75rem;">HTML / SCORM / LC-JSON / BuildXCL</td>
                            <td style="padding: 0.75rem;">All Formats + Custom Converter</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Multitenant User Auth Model</td>
                            <td style="padding: 0.75rem;">Tenant Portal Login</td>
                            <td style="padding: 0.75rem;">Tenant Portal + Code Access</td>
                            <td style="padding: 0.75rem;">Tenant Portal + Custom SSO / SAML</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Pricing FAQ Accordion -->
        <section class="card mb-8">
            <h2 class="h3-style" style="margin-top: 0; color: var(--color-primary);">Frequently Asked Questions</h2>
            
            <div class="faq-item">
                <button class="faq-trigger" onclick="toggleFaq(this)" aria-expanded="false">
                    <span>How does tenant database isolation work?</span>
                    <span aria-hidden="true">+</span>
                </button>
                <div class="faq-content hidden">
                    <p>Every tenant receives an independent SQLite database file stored outside the public web root (e.g. <code>db/superablelearning/tenants/{tenantKey}.sqlite</code>). User credentials, progress records, and invitation keys never cross database boundaries, guaranteeing total privacy and regulatory compliance.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" onclick="toggleFaq(this)" aria-expanded="false">
                    <span>How do end-users sign in across different tenants?</span>
                    <span aria-hidden="true">+</span>
                </button>
                <div class="faq-content hidden">
                    <p>Users sign in directly via their organization's tenant portal (e.g., <code>acme.superablelearning.com</code> or using their course invitation code). On the main <strong>superablelearning.com</strong> homepage, learners can use the <strong>Workspace Finder</strong> tool to search for their organization and jump directly to their portal.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" onclick="toggleFaq(this)" aria-expanded="false">
                    <span>Can we use our custom domain name?</span>
                    <span aria-hidden="true">+</span>
                </button>
                <div class="faq-content hidden">
                    <p>Yes! Enterprise plans include full custom domain mapping via our built-in <code>custom_domains.json</code> router. For example, your learners can access courses at <code>learning.yourcompany.com</code> seamlessly.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" onclick="toggleFaq(this)" aria-expanded="false">
                    <span>Is WCAG 2.2 AA accessibility guaranteed?</span>
                    <span aria-hidden="true">+</span>
                </button>
                <div class="faq-content hidden">
                    <p>Yes. The entire LMS UI core—including skip links, screen reader attributes, focus indicators, font sizes, and dynamic contrast ratios—is built to meet WCAG 2.2 AA standards. Dynamic contrast validation automatically darkens or lightens client branding colors if they fail contrast rules against light or dark backgrounds.</p>
                </div>
            </div>

        </section>

    </main>

    <?= renderTenantFooter('local-dev') ?>

    <script>
        function toggleFaq(btn) {
            const content = btn.nextElementSibling;
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', !expanded);
            content.classList.toggle('hidden');
            btn.querySelector('span:last-child').textContent = expanded ? '+' : '−';
        }
    </script>
</body>
</html>
