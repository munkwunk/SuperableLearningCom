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
    <meta name="description" content="Transparent, accessible pricing plans for Superable Learning LMS. Sandbox, Pro, and Premium tiers with optional accessibility review packages.">
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss('local-dev') ?>
    <style>
        /* Custom layout styling for the pricing pages */
        .pricing-section-title {
            text-align: center;
            color: var(--color-primary);
            margin: 3rem 0 1.5rem;
            font-size: 2rem;
            position: relative;
        }
        .pricing-section-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--color-primary);
            margin: 0.5rem auto 0;
            border-radius: 2px;
        }
        .addon-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (min-width: 768px) {
            .addon-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        .addon-card {
            background-color: var(--color-bg-light, #f8f9fa);
            border: 1px solid var(--color-border, #e2e8f0);
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .addon-header {
            margin-bottom: 1rem;
        }
        .addon-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-primary);
            margin-top: 0;
            margin-bottom: 0.5rem;
        }
        .addon-desc {
            font-size: 0.875rem;
            color: var(--color-text-dark, #4a5568);
            line-height: 1.4;
            margin-bottom: 1rem;
        }
        .addon-rates {
            border-top: 1px dashed var(--color-border, #e2e8f0);
            padding-top: 1rem;
            margin-top: auto;
        }
        .rate-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .rate-row:last-child {
            margin-bottom: 0;
        }
        .rate-label {
            font-weight: 600;
            color: var(--color-text-dark, #2d3748);
        }
        .rate-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--color-primary);
        }
        .rate-sub {
            font-size: 0.75rem;
            color: var(--color-text-muted, #718096);
        }
        .audit-info-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--color-primary);
            text-decoration: underline;
        }
    </style>
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

        <h2 class="pricing-section-title">LMS Platform Tiers</h2>

        <!-- Pricing Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8" id="pricing-grid">
            
            <!-- Tier 1: Sandbox -->
            <div class="pricing-card">
                <h3 style="font-size: 1.5rem; margin-top: 0; color: var(--color-primary);">Sandbox</h3>
                <p class="text-neutral-mid text-sm">For learners, testers, accessibility students, and creators exploring the platform.</p>
                <div class="pricing-price">$0 <span>/ free</span></div>
                <p class="text-xs text-neutral-mid">Free forever for single local/dev tenant</p>

                <ul class="pricing-features">
                    <li><strong>250 MB storage</strong></li>
                    <li><strong>1 admin account</strong></li>
                    <li>Unlimited learners</li>
                    <li>Unlimited courses (to quota limit)</li>
                    <li>LC-JSON & HTML import</li>
                    <li>Basic Accessibility Tools:
                        <ul style="padding-left: 1rem; margin-top: 0.25rem;">
                            <li>Contrast checker</li>
                            <li>Reading-order preview</li>
                            <li>Basic HTML linting</li>
                            <li>Basic JSON validation</li>
                            <li>CSS guardrails</li>
                        </ul>
                    </li>
                </ul>

                <a href="index.php?tenant=local-dev" class="btn btn-outline-light text-center" style="color: var(--color-primary); border-color: var(--color-primary); margin-top: auto;">Explore Local Demo</a>
            </div>

            <!-- Tier 2: Pro (Featured) -->
            <div class="pricing-card featured">
                <span class="pricing-card-badge">Most Popular</span>
                <h3 style="font-size: 1.5rem; margin-top: 0; color: var(--color-primary);">Pro</h3>
                <p class="text-neutral-mid text-sm">For freelancers, small orgs, and accessibility-first creators who need more control.</p>
                <div class="pricing-price">$10 <span>/ month</span></div>
                <p class="text-xs text-neutral-mid">All Sandbox tools plus custom branding</p>

                <ul class="pricing-features">
                    <li><strong>500 MB storage</strong></li>
                    <li><strong>1 admin account</strong></li>
                    <li>Unlimited learners & courses</li>
                    <li><strong>Branding controls:</strong>
                        <ul style="padding-left: 1rem; margin-top: 0.25rem;">
                            <li>CSS variables styling</li>
                            <li>Logo upload</li>
                            <li>Theme toggles</li>
                        </ul>
                    </li>
                    <li>Priority email support (72h response)</li>
                    <li>Help center & docs access</li>
                </ul>

                <a href="index.php#workspace-finder" class="btn btn-primary text-center" style="margin-top: auto;">Get Started / Find Workspace</a>
            </div>

            <!-- Tier 3: Premium -->
            <div class="pricing-card">
                <h3 style="font-size: 1.5rem; margin-top: 0; color: var(--color-primary);">Premium</h3>
                <p class="text-neutral-mid text-sm">For small teams and departments needing collaboration, analytics, and advanced tools.</p>
                <div class="pricing-price">$20 <span>/ month</span></div>
                <p class="text-xs text-neutral-mid">Full team capabilities & advanced WCAG tools</p>

                <ul class="pricing-features">
                    <li><strong>1 GB storage</strong></li>
                    <li><strong>3 admin accounts</strong> (RBAC permissions)</li>
                    <li><strong>Customization:</strong>
                        <ul style="padding-left: 1rem; margin-top: 0.25rem;">
                            <li>Full Custom CSS (guardrails enforced)</li>
                            <li>Custom fonts subsetting</li>
                            <li>Course-level branding overrides</li>
                        </ul>
                    </li>
                    <li><strong>Advanced Accessibility Tools:</strong>
                        <ul style="padding-left: 1rem; margin-top: 0.25rem;">
                            <li>Automated WCAG linting</li>
                            <li>Accessibility heatmaps</li>
                            <li>NVDA simulation mode</li>
                            <li>Interactive remediation</li>
                        </ul>
                    </li>
                    <li>Shared course library & brand settings</li>
                    <li>Light analytics dashboard (CSV export)</li>
                    <li>Priority support (24-48h response)</li>
                </ul>

                <a href="help.php" class="btn btn-outline-light text-center" style="color: var(--color-primary); border-color: var(--color-primary); margin-top: auto;">Contact Platform Team</a>
            </div>

        </div>

        <!-- Add-on Section: Accessibility Review Package -->
        <section class="card mb-8">
            <h2 class="h3-style" style="margin-top: 0; color: var(--color-primary);">Accessibility Review Add-On Options</h2>
            <p class="text-neutral-mid mb-4">
                Affordable accessibility auditing designed for creators and small organizations. Typical WCAG audits cost <strong>$75–$120/hr</strong> — we price ours accessibly because accessibility should be accessible.
            </p>

            <div class="addon-grid">
                
                <!-- Add-on Type 1: Basic Review -->
                <div class="addon-card">
                    <div class="addon-header">
                        <h3 class="addon-title">Basic Review</h3>
                        <p class="addon-desc">A quick, high‑level accessibility check that identifies surface‑level issues without WCAG mapping or remediation guidance.</p>
                    </div>
                    <div class="addon-rates">
                        <div class="rate-row">
                            <span class="rate-label">Per Module:</span>
                            <span class="rate-value">$15</span>
                        </div>
                        <div class="rate-row">
                            <span class="rate-label">Per Course:</span>
                            <div>
                                <span class="rate-value">$50</span>
                                <span class="rate-sub">(5 modules)</span>
                            </div>
                        </div>
                        <div class="rate-row">
                            <span class="rate-label">Subscription:</span>
                            <span class="rate-value">$45<span style="font-size:0.75rem; font-weight:normal;">/mo</span></span>
                        </div>
                    </div>
                </div>

                <!-- Add-on Type 2: Full WCAG Audit -->
                <div class="addon-card" style="border: 2px solid var(--color-accent-blue, #3182ce);">
                    <div class="addon-header">
                        <h3 class="addon-title" style="color: var(--color-accent-blue, #3182ce);">Full WCAG Audit</h3>
                        <p class="addon-desc">A complete diagnostic that maps all found issues directly to WCAG 2.2 AA success criteria with severity ratings and impact explanations.</p>
                    </div>
                    <div class="addon-rates">
                        <div class="rate-row">
                            <span class="rate-label">Per Module:</span>
                            <span class="rate-value">$30</span>
                        </div>
                        <div class="rate-row">
                            <span class="rate-label">Per Course:</span>
                            <div>
                                <span class="rate-value">$100</span>
                                <span class="rate-sub">(5 modules)</span>
                            </div>
                        </div>
                        <div class="rate-row">
                            <span class="rate-label">Subscription:</span>
                            <span class="rate-value">$90<span style="font-size:0.75rem; font-weight:normal;">/mo</span></span>
                        </div>
                    </div>
                </div>

                <!-- Add-on Type 3: Full Audit + Remediation Plan -->
                <div class="addon-card">
                    <div class="addon-header">
                        <h3 class="addon-title">Full Audit + Remediation</h3>
                        <p class="addon-desc">A full WCAG audit plus detailed remediation instructions, code‑level guidance, custom component fixes, and regression testing steps.</p>
                    </div>
                    <div class="addon-rates">
                        <div class="rate-row">
                            <span class="rate-label">Per Module:</span>
                            <span class="rate-value">$40</span>
                        </div>
                        <div class="rate-row">
                            <span class="rate-label">Per Course:</span>
                            <div>
                                <span class="rate-value">$150</span>
                                <span class="rate-sub">(5 modules)</span>
                            </div>
                        </div>
                        <div class="rate-row">
                            <span class="rate-label">Subscription:</span>
                            <span class="rate-value">$125<span style="font-size:0.75rem; font-weight:normal;">/mo</span></span>
                        </div>
                    </div>
                </div>

            </div>

            <div style="text-align: center; border-top: 1px dashed var(--color-border, #e2e8f0); padding-top: 1rem;">
                <span class="text-xs text-neutral-mid">For course limits exceeding 5 modules, additional modules are billed at: Basic ($10/mod), Full ($20/mod), Audit + Remediation ($30/mod). Subscription options require a 2-month minimum commitment.</span>
                <a href="help.php?doc=administration/accessibility-reviews" class="audit-info-link">View Detailed Accessibility Review Definitions & Scopes &rarr;</a>
            </div>
        </section>

        <!-- Detailed Feature Matrix Table -->
        <section class="card mb-8">
            <h2 class="h3-style" style="margin-top: 0;">Detailed Plan Feature Matrix</h2>
            <p class="text-neutral-mid mb-4">Compare capabilities across all Superable Learning architecture tiers.</p>
            
            <div style="overflow-x: auto;">
                <table class="table-styled" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--color-primary); text-align: left;">
                            <th style="padding: 0.75rem;">Feature / Capability</th>
                            <th style="padding: 0.75rem;">Sandbox</th>
                            <th style="padding: 0.75rem;">Pro</th>
                            <th style="padding: 0.75rem;">Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Monthly Price</td>
                            <td style="padding: 0.75rem;">$0</td>
                            <td style="padding: 0.75rem;">$10</td>
                            <td style="padding: 0.75rem;">$20</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Storage Limit</td>
                            <td style="padding: 0.75rem;">250 MB</td>
                            <td style="padding: 0.75rem;">500 MB</td>
                            <td style="padding: 0.75rem;">1 GB</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Admin Accounts</td>
                            <td style="padding: 0.75rem;">1 Account</td>
                            <td style="padding: 0.75rem;">1 Account</td>
                            <td style="padding: 0.75rem;">3 Accounts (RBAC)</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Database Isolation</td>
                            <td style="padding: 0.75rem;">Dedicated Tenant SQLite</td>
                            <td style="padding: 0.75rem;">Dedicated Tenant SQLite</td>
                            <td style="padding: 0.75rem;">Dedicated Tenant SQLite</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">WCAG 2.2 AA Compliance</td>
                            <td style="padding: 0.75rem;">✓ Full Engine</td>
                            <td style="padding: 0.75rem;">✓ Full Engine</td>
                            <td style="padding: 0.75rem;">✓ Full Engine + Advanced Linting</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Branding Customization</td>
                            <td style="padding: 0.75rem;">None</td>
                            <td style="padding: 0.75rem;">CSS Variables, Logo Upload, Theme Toggle</td>
                            <td style="padding: 0.75rem;">Full Custom CSS, Custom Fonts, Course-level Overrides</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Analytics & Reports</td>
                            <td style="padding: 0.75rem;">None</td>
                            <td style="padding: 0.75rem;">Basic completion status</td>
                            <td style="padding: 0.75rem;">Analytics dashboard, Completion rates, CSV export, LRS connector</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #E2E8F0;">
                            <td style="padding: 0.75rem; font-weight: 700;">Support Level</td>
                            <td style="padding: 0.75rem;">Community Forums</td>
                            <td style="padding: 0.75rem;">Priority email support (72h)</td>
                            <td style="padding: 0.75rem;">Priority support (24-48h), Zoom Troubleshooting</td>
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
                    <p>Yes! Premium plans support custom domain mapping via our built-in <code>custom_domains.json</code> router. For example, your learners can access courses at <code>learning.yourcompany.com</code> seamlessly.</p>
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
