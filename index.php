<?php
/**
 * Superable Learning LMS - Dashboard & Platform Homepage
 * 
 * Renders the Platform Landing Page ("Coming Soon" + Admin Login) when browsing
 * the main site, or the Tenant LMS Dashboard when browsing a client tenant.
 */

require_once 'config.php';
$pdo = get_db_connection();

$activeTenant = resolveTenantKey();
$isPlatformSite = ($activeTenant === 'local-dev' && empty($_GET['tenant']));

$is_guest = !isset($_SESSION['user_id']);
$user_id = $is_guest ? 'guest_' . session_id() : $_SESSION['user_id'];
$current_user_name = $_SESSION['full_name'] ?? "Guest";
$is_admin = $_SESSION['is_admin'] ?? false;

// If browsing Platform Site (Superable Learning Homepage)
if ($isPlatformSite) {
    $availableTenants = getAvailableTenants();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superable Learning — Accessible Multi-Tenant LMS Engine</title>
    <meta name="description" content="Superable Learning is an accessible, isolated multi-tenant learning management system built for WCAG 2.2 AA compliance, rapid HTML/SCORM course delivery, and custom branding.">
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss('local-dev') ?>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Main Platform Header Navigation -->
    <header class="site-header">
        <div class="container-wide header-inner">
            <div class="brand-group">
                <a href="index.php" class="brand-title">Superable Learning</a>
                <span class="badge-platform">PLATFORM</span>
            </div>
            <nav class="nav-links" aria-label="Platform Main Navigation">
                <a href="index.php" class="nav-link active" aria-current="page">Home</a>
                <a href="#features" class="nav-link">Features</a>
                <a href="#architecture" class="nav-link">Architecture</a>
                <a href="pricing.php" class="nav-link">Pricing</a>
                <a href="help.php" class="nav-link">Help & Docs</a>
                <a href="#workspace-finder" class="nav-link">Find Workspace</a>
                <?php if (!$is_guest && $is_admin): ?>
                    <a href="platform_admin.php" class="btn btn-teal btn-sm">Platform Admin</a>
                <?php endif; ?>
                <?php if ($is_guest): ?>
                    <a href="login.php?tenant=local-dev" class="btn btn-outline-light btn-sm">Sign In</a>
                <?php else: ?>
                    <span class="text-sm" style="color: white;">Logged in as <strong><?= htmlspecialchars($current_user_name) ?></strong></span>
                    <a href="logout.php" class="nav-link text-sm">Logout</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Platform Hero Section -->
    <section class="hero-banner">
        <div class="container text-center">
            <div class="mb-3">
                <span class="badge-wcag">
                    <span class="badge-wcag-dot"></span> WCAG 2.2 AA Certified Engine
                </span>
            </div>
            <h1 style="color: white; margin-bottom: 1rem;" class="text-4xl md:text-5xl font-bold">Empowering Accessible E-Learning for Every Organization</h1>
            <p class="hero-subtitle max-w-3xl" style="margin-left: auto; margin-right: auto;">
                The high-performance multi-tenant learning management system engineered for WCAG 2.2 AA accessibility, isolated database security, screen readers, and seamless HTML/SCORM course delivery.
            </p>

            <div class="hero-actions flex flex-wrap justify-center gap-4 mt-4">
                <a href="#workspace-finder" class="btn btn-lg">Find Your Workspace</a>
                <a href="pricing.php" class="btn btn-outline-light btn-lg">Explore Pricing</a>
                <?php if ($is_guest): ?>
                    <a href="login.php?tenant=local-dev" class="btn btn-teal btn-lg">Platform Admin Login</a>
                <?php else: ?>
                    <a href="platform_admin.php" class="btn btn-teal btn-lg">Open Client Manager</a>
                <?php endif; ?>
            </div>

            <!-- Active Tenant Quick Launcher Chips -->
            <?php if (!empty($availableTenants)): ?>
                <div class="mt-6 pt-4" style="border-top: 1px solid rgba(255,255,255,0.15);">
                    <p class="text-xs uppercase tracking-wide text-white mb-2" style="opacity: 0.9;">Explore Active Client Portals:</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <?php foreach ($availableTenants as $t): ?>
                            <a href="index.php?tenant=<?= urlencode($t['tenant_key']) ?>" class="tenant-chip" title="Launch portal for <?= htmlspecialchars($t['name']) ?>">
                                <span>🏢</span> <?= htmlspecialchars($t['name']) ?>
                            </a>
                        <?php endforeach; ?>
                        <a href="index.php?tenant=superableaccessibility" class="tenant-chip" title="Launch Accessibility Showcase Tenant">
                            <span>♿</span> Superable Accessibility Showcase
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Main Platform Content Body -->
    <main id="main-content" class="container-wide main-content">

        <!-- Interactive Workspace Finder / Tenant Switcher Section -->
        <section id="workspace-finder" class="workspace-finder-card">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="max-w-2xl">
                    <h2 class="h3-style" style="margin-top: 0; color: var(--color-primary);">Find Your Organization Workspace</h2>
                    <p class="text-neutral-mid mb-3">
                        Enter your company's subdomain or select an active client portal below to log into your organization's learning portal.
                    </p>
                </div>
            </div>

            <form action="index.php" method="GET" class="workspace-input-group mt-2" onsubmit="return handleWorkspaceRedirect(event);">
                <div class="form-group mb-0" style="flex: 1;">
                    <label for="tenant-input" class="sr-only">Enter Organization Subdomain or Code</label>
                    <input type="text" id="tenant-input" name="tenant" class="form-control" placeholder="e.g. superableaccessibility, acme, techcorp" required>
                </div>
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Launch Tenant Portal &rarr;</button>
            </form>

            <?php if (!empty($availableTenants)): ?>
                <div class="mt-4 pt-3 flex flex-wrap items-center gap-3" style="border-top: 1px dashed #CBD5E1;">
                    <span class="text-xs font-bold text-neutral-mid uppercase">Or Select Active Tenant:</span>
                    <select id="quick-tenant-select" class="form-control" style="width: auto; padding: 0.35rem 0.75rem; font-size: 0.875rem;" onchange="if(this.value) window.location.href='index.php?tenant=' + encodeURIComponent(this.value);">
                        <option value="">-- Choose Organization Portal --</option>
                        <?php foreach ($availableTenants as $t): ?>
                            <option value="<?= htmlspecialchars($t['tenant_key']) ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['tenant_key']) ?>)</option>
                        <?php endforeach; ?>
                        <option value="superableaccessibility">Superable Accessibility Showcase</option>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Multitenant Auth Architecture Guidance Callout -->
            <div class="mt-4 p-3 rounded-xl" style="background-color: var(--color-bg-light); border: 1px solid #E2E8F0;">
                <h3 class="text-sm font-bold" style="margin-top: 0; color: var(--color-primary);">How End-User Multitenant Login Works</h3>
                <p class="text-xs text-neutral-mid mb-0">
                    To maintain strict data security and compliance (FERPA/HIPAA), each client organization maintains its own isolated database. End-users (learners and instructors) log in directly through their organization's tenant portal or enter their course invitation code upon registration. Platform admins log in centrally via the Platform Admin Sign In link above.
                </p>
            </div>
        </section>

        <!-- Platform Features Grid -->
        <section id="features" class="mb-8">
            <div class="text-center mb-6">
                <h2 class="text-3xl font-bold" style="color: var(--color-primary);">Core Engine Capabilities</h2>
                <p class="text-neutral-mid max-w-2xl" style="margin: 0 auto;">Designed from the ground up for total compliance, security, and effortless e-learning delivery.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="feature-card">
                    <div class="feature-icon-box">🔒</div>
                    <h3>Multi-Tenant Isolation</h3>
                    <p class="card-text">Each organization operates inside a dedicated SQLite database file and isolated storage container. User records and course assets are never commingled.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box">♿</div>
                    <h3>WCAG 2.2 AA Certified</h3>
                    <p class="card-text">Audited and optimized for screen readers (NVDA, JAWS, VoiceOver), keyboard-only navigation, dyslexia-friendly Atkinson Hyperlegible fonts, and automated contrast guards.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box">📦</div>
                    <h3>Universal Course Package Engine</h3>
                    <p class="card-text">Deploy standalone HTML packages, SCORM archives, interactive LC-JSON modules, or external BuildXCL learning links seamlessly with real-time tracking.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box">🎨</div>
                    <h3>Dynamic Tenant Branding</h3>
                    <p class="card-text">Customize brand colors, logos, and typography per tenant. Built-in WCAG contrast validation automatically adjusts dark and light mode palette compliance.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box">🔑</div>
                    <h3>Invitation Code & Progress System</h3>
                    <p class="card-text">Provision course access keys with single-use or unlimited caps. Track module progress and completion stats per user and tenant.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box">⚡</div>
                    <h3>Zero-Dependency Performance</h3>
                    <p class="card-text">Built on modern, lightweight PHP and vanilla CSS with zero heavy JavaScript framework overhead for sub-second page loads everywhere.</p>
                </div>
            </div>
        </section>

        <!-- Platform Architecture & Security Deep Dive -->
        <section id="architecture" class="card mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div>
                    <h2 class="h3-style" style="margin-top: 0; color: var(--color-primary);">Enterprise Security & Multitenant Architecture</h2>
                    <p class="text-neutral-mid mb-3">
                        Unlike traditional monolithic LMS software that shares single database tables across clients, Superable Learning uses a file-based isolated architecture.
                    </p>
                    <ul class="standard-list">
                        <li><strong>Database Isolation:</strong> Separate SQLite files per tenant (<code>db/superablelearning/tenants/{tenant}.sqlite</code>).</li>
                        <li><strong>Storage Isolation:</strong> Dedicated asset directories per tenant (<code>storage/superablelearning/tenants/{tenant}/</code>).</li>
                        <li><strong>Domain Resolution:</strong> Subdomain, query parameter, and custom domain mapping (<code>custom_domains.json</code>).</li>
                        <li><strong>Accessible Player Core:</strong> Accessible HTML5 video/audio controls, transcript overlays, and focus traps.</li>
                    </ul>
                </div>
                <div class="p-4 rounded-xl" style="background-color: #0F172A; color: #F8FAFC; font-family: monospace; font-size: 0.85rem; overflow-x: auto; border: 1px solid #334155;">
                    <div style="color: #38BDF8; font-weight: bold; margin-bottom: 0.5rem;">// Tenant Resolution & Connection Engine</div>
                    <div style="color: #94A3B8;">1. Host / Query Inspection</div>
                    <div style="color: #34D399;">$tenantKey = resolveTenantKey();</div>
                    <br>
                    <div style="color: #94A3B8;">2. Secure DB Path Mapping</div>
                    <div style="color: #F472B6;">$dbPath = getDbPath($tenantKey);</div>
                    <div style="color: #94A3B8;">// Returns .../tenants/{tenantKey}.sqlite</div>
                    <br>
                    <div style="color: #94A3B8;">3. Dynamic Branding CSS Engine</div>
                    <div style="color: #FBBF24;"><?= renderTenantBrandingCss('demo') ?></div>
                </div>
            </div>
        </section>

        <!-- Pricing Teaser Banner -->
        <section class="card mb-8" style="background: linear-gradient(135deg, var(--color-primary), #1e4d35); color: white;">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <h2 class="h3-style text-white" style="margin-top: 0;">Flexible Pricing Strategy & Tenant Limits</h2>
                    <p class="mb-0 text-white" style="opacity: 0.95; max-width: 42rem;">
                        Whether you need a free developer sandbox, a dedicated organizational portal, or an enterprise multi-tenant cluster, Superable Learning offers flexible plans and modular quota limits.
                    </p>
                </div>
                <div>
                    <a href="pricing.php" class="btn btn-lg btn-accent" style="white-space: nowrap; background-color: var(--color-accent); color: white; border: none;">View Pricing & Plans &rarr;</a>
                </div>
            </div>
        </section>

        <!-- Developer Documentation & Guides Section -->
        <section class="mb-8">
            <h2 class="h3-style text-center mb-4" style="color: var(--color-primary);">Developer & Content Author Guides</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="card">
                    <h3>Course Packaging Guide</h3>
                    <p class="text-sm text-neutral-mid">Learn how to package HTML5, SCORM, and BuildXCL modules for instant deployment.</p>
                    <a href="help.php" class="btn btn-outline-light text-sm" style="color: var(--color-primary); border-color: var(--color-primary);">View Guide &rarr;</a>
                </div>
                <div class="card">
                    <h3>LC-JSON Component Spec</h3>
                    <p class="text-sm text-neutral-mid">Explore JSON schemas for interactive quizzes, flashcards, videos, and accessible media.</p>
                    <a href="help.php" class="btn btn-outline-light text-sm" style="color: var(--color-primary); border-color: var(--color-primary);">View Spec &rarr;</a>
                </div>
                <div class="card">
                    <h3>Tenant Setup & API</h3>
                    <p class="text-sm text-neutral-mid">Understand how to provision tenants, configure custom domains, and override CSS tokens.</p>
                    <a href="help.php" class="btn btn-outline-light text-sm" style="color: var(--color-primary); border-color: var(--color-primary);">View Documentation &rarr;</a>
                </div>
            </div>
        </section>

    </main>

    <!-- Universal Platform Footer -->
    <?= renderTenantFooter('local-dev') ?>

    <script>
        function handleWorkspaceRedirect(e) {
            e.preventDefault();
            const input = document.getElementById('tenant-input').value.trim();
            if (input) {
                window.location.href = 'index.php?tenant=' + encodeURIComponent(input);
            }
            return false;
        }
    </script>
</body>
</html>
<?php
    exit;
}


// Below code runs for Client Tenant Dashboards
$message = '';
$message_type = 'success';
$unlock_message = '';
$unlock_message_type = 'success';
$password_message = '';
$password_message_type = 'success';

// Handle Password Change (Registered Users Only)
if (!$is_guest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    verify_csrf_token();
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if ($new_pass && $new_pass === $confirm_pass) {
        if (strlen($new_pass) >= 8) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([password_hash($new_pass, PASSWORD_DEFAULT), $user_id]);
                $password_message = "Password updated successfully.";
                $password_message_type = 'success';
            } catch (PDOException $e) {
                $password_message = "Error updating password.";
                $password_message_type = 'critical';
            }
        } else {
            $password_message = "Password must be at least 8 characters.";
            $password_message_type = 'critical';
        }
    } else {
        $password_message = "Passwords do not match.";
        $password_message_type = 'critical';
    }
}

// Handle Course Unlock (Registered Users Only)
if (!$is_guest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_course') {
    verify_csrf_token();
    $course_code = strtoupper(trim($_POST['course_code'] ?? ''));
    if ($course_code) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM invitation_keys WHERE key_code = ? AND (uses_remaining > 0 OR uses_remaining = -1)");
            $stmt->execute([$course_code]);
            $key_data = $stmt->fetch();
            
            if ($key_data) {
                $course_id = $key_data['course_id'];
                if ($course_id) {
                    $stmt = $pdo->prepare("SELECT id FROM user_permissions WHERE user_id = ? AND course_id = ?");
                    $stmt->execute([$user_id, $course_id]);
                    if ($stmt->fetch()) {
                        $unlock_message = "You have already unlocked this course.";
                        $unlock_message_type = 'warning';
                    } else {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, course_id) VALUES (?, ?)");
                        $stmt->execute([$user_id, $course_id]);
                        
                        if ($key_data['uses_remaining'] > 0) {
                            $stmt = $pdo->prepare("UPDATE invitation_keys SET uses_remaining = uses_remaining - 1 WHERE id = ?");
                            $stmt->execute([$key_data['id']]);
                        }
                        $pdo->commit();
                        
                        $course_title = $course_id;
                        $course_dir = getTenantCoursesDir() . DIRECTORY_SEPARATOR . $course_id;
                        $manifest_path = $course_dir . DIRECTORY_SEPARATOR . 'course_structure.json';
                        if (file_exists($manifest_path)) {
                            $manifest = json_decode(file_get_contents($manifest_path), true);
                            $course_title = $manifest['properties']['title'] ?? $course_id;
                        }
                        
                        $unlock_message = "Success! You have unlocked the course: " . $course_title;
                        $unlock_message_type = 'success';
                    }
                } else {
                    $unlock_message = "This code is valid, but it is not linked to any course.";
                    $unlock_message_type = 'warning';
                }
            } else {
                $unlock_message = "Invalid or expired course code.";
                $unlock_message_type = 'critical';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $unlock_message = "System error during course unlock. Please try again.";
            $unlock_message_type = 'critical';
        }
    } else {
        $unlock_message = "Please enter a course code.";
        $unlock_message_type = 'critical';
    }
}

// Get completed module counts and permissions per course for this user
$completed_modules = [];
$user_permissions = [];
try {
    $stmt = $pdo->prepare("SELECT course_id, module_id FROM module_progress WHERE user_id = ? AND is_completed = 1");
    $stmt->execute([$user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $completed_modules[$row['course_id']][] = $row['module_id'];
    }

    $stmt = $pdo->prepare("SELECT course_id FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignore database errors gracefully
}

// Define tenant-aware base paths
$courses_dir = getTenantCoursesDir();
$courses_web_path = getTenantCoursesWebPath();
$courses = [];

if (is_dir($courses_dir)) {
    $scan = scandir($courses_dir);
    foreach ($scan as $folder) {
        if ($folder === '.' || $folder === '..' || $folder === 'tenants' || $folder === '.backups') continue;
        
        $manifest_path = $courses_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'course_structure.json';
        if (file_exists($manifest_path)) {
            $json = file_get_contents($manifest_path);
            $manifest = json_decode($json, true);
            if ($manifest && isset($manifest['properties'])) {
                
                $access = $manifest['properties']['access'] ?? ['type' => 'public'];
                $has_permission = $is_admin || in_array($folder, $user_permissions);
                
                if (!$has_permission && $access['type'] === 'hidden') {
                    continue;
                }

                $total_modules = 0;
                foreach (($manifest['modules'] ?? []) as $item) {
                    if (isset($item['group'])) {
                        $total_modules += count($item['items'] ?? []);
                    } else {
                        $total_modules++;
                    }
                }

                $completed_count = isset($completed_modules[$folder]) ? count($completed_modules[$folder]) : 0;
                $is_completed = ($total_modules > 0 && $completed_count >= $total_modules);
                
                $thumb_rel = $manifest['properties']['thumbnail'] ?? 'assets/default_thumb.jpg';
                $thumb_url = (strpos($thumb_rel, 'http') === 0) ? $thumb_rel : $courses_web_path . '/' . $folder . '/' . $thumb_rel;

                $courses[] = [
                    'id' => $folder,
                    'title' => htmlspecialchars($manifest['properties']['title'] ?? 'Untitled Course'),
                    'description' => htmlspecialchars($manifest['properties']['description'] ?? 'No description available.'),
                    'thumbnail' => htmlspecialchars($thumb_url),
                    'url' => $manifest['properties']['url'] ?? '',
                    'total_modules' => $total_modules,
                    'completed_count' => $completed_count,
                    'is_completed' => $is_completed,
                    'has_permission' => $has_permission,
                    'access' => $access
                ];
            }
        }
    }
}

$tenantMetadata = getTenantMetadata();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tenantMetadata['name']) ?> — Learning Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss($activeTenant) ?>
</head>
<body>
    <a href="#dashboard-main" class="skip-link">Skip to main content</a>

    <header class="site-header">
        <div class="container-wide header-inner">
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
                    <p class="text-sm" style="margin:0; color: #CBD5E0;"><?= htmlspecialchars($heroSub) ?></p>
                </div>
            </div>
            <div class="nav-links">
                <button type="button" id="theme-toggle-btn" class="btn btn-outline-light btn-sm" aria-label="Toggle dark mode theme">🌙 Theme</button>
                <a href="<?= tenant_url('help.php') ?>" class="nav-link">Help & Docs</a>
                <?php if (!$is_guest): ?>
                    <span class="text-sm">Hello, <strong><?= htmlspecialchars($current_user_name) ?></strong></span>
                    <?php if ($is_admin): ?>
                        <a href="<?= tenant_url('admin.php') ?>" class="btn btn-teal btn-sm">Admin Panel</a>
                    <?php endif; ?>
                    <a href="<?= tenant_url('logout.php') ?>" class="btn btn-outline-light btn-sm">Logout</a>
                <?php else: ?>
                    <a href="<?= tenant_url('login.php') ?>" class="btn btn-sm">Log In</a>
                    <a href="<?= tenant_url('register.php') ?>" class="nav-link">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main id="dashboard-main" class="container-wide main-content">
        
        <?php if ($is_guest): ?>
            <div role="region" aria-label="Guest Notification" class="alert alert-info mb-4">
                <div>
                    <p class="font-bold text-lg mb-1" style="margin-top:0;">Welcome to <?= htmlspecialchars($tenantMetadata['name']) ?></p>
                    <p style="margin:0;">You are browsing as a guest. You can view all <strong>Public</strong> courses below. To track your progress and unlock certified training, please <a href="login.php">log in</a> or <a href="register.php">create an account</a>.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex-between mb-3">
            <h2 style="margin: 0;">Available Courses</h2>
        </div>

        <?php if (empty($courses)): ?>
            <div class="card text-center" style="padding: 3rem 1rem;">
                <p class="font-bold" style="margin: 0;">No courses are currently available.</p>
                <p class="text-neutral-mid mt-1" style="margin: 0;">Course folders must be uploaded to the tenant's course directory.</p>
            </div>
        <?php else: ?>
            <div class="card-grid card-grid-3">
                <?php foreach ($courses as $course): ?>
                    <article class="card">
                        <h3 class="card-title"><?= $course['title'] ?></h3>
                        
                        <?php if (!$is_guest): ?>
                            <?php if ($course['is_completed']): ?>
                                <div class="mb-2 badge badge-success" aria-label="Course Completed">
                                    <span aria-hidden="true">✓</span> Course Complete
                                </div>
                            <?php else: ?>
                                <div class="mb-2 text-neutral-mid text-sm">
                                    Progress: <?= $course['completed_count'] ?> of <?= $course['total_modules'] ?> modules <span class="sr-only">completed</span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mb-2">
                                <?php if ($course['access']['type'] === 'public'): ?>
                                    <span class="badge badge-success">Public Access</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">🔒 Locked</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <p class="card-text"><?= $course['description'] ?></p>
                        
                        <div class="card-actions">
                        <?php if ($course['has_permission'] || $course['access']['type'] === 'public'): 
                            $activeTenantKey = resolveTenantKey();
                            $course_url = "player.php?course_id=" . urlencode($course['id']) . "&tenant=" . urlencode($activeTenantKey);
                            $is_external = false;
                            if (!empty($course['url'])) {
                                $course_url = $course['url'];
                                if (preg_match('/^https?:\/\//', $course_url)) {
                                    $is_external = true;
                                } else {
                                    $course_url = $courses_web_path . "/" . $course['id'] . "/" . $course_url;
                                }
                            }
                        ?>
                            <a href="<?= $course_url ?>" 
                               class="btn btn-sm" 
                               aria-label="Start course: <?= $course['title'] ?><?= $is_external ? ' (opens in a new tab)' : '' ?>"
                               <?= $is_external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                                Start Course
                                <?php if ($is_external): ?>
                                    <span class="sr-only">(opens in a new tab)</span>
                                <?php endif; ?>
                            </a>
                        <?php elseif ($course['access']['type'] === 'teaser'): ?>
                            <a href="<?= htmlspecialchars($course['access']['teaser_link'] ?? '#') ?>" 
                               class="btn btn-secondary btn-sm" 
                               aria-label="Learn more about <?= $course['title'] ?>">
                                Course Info
                            </a>
                            <?php if (!$is_guest): ?>
                                <a href="#course_code" 
                                   class="btn btn-sm" 
                                   onclick="document.getElementById('course_code').focus();"
                                   aria-label="Enter code to unlock <?= $course['title'] ?>">
                                    <span aria-hidden="true">🔒</span> Enter Code
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$is_guest): ?>
        <section class="mt-4 pt-4" style="border-top: 1px solid var(--color-border);" aria-labelledby="settings-heading">
            <h2 id="settings-heading" class="mb-3">Account Settings & Actions</h2>
            
            <div class="card-grid card-grid-2">
                <div class="card">
                    <h3>Unlock Course with Invitation Key</h3>
                    <?php if ($unlock_message): ?>
                        <div role="status" aria-live="polite" class="alert alert-<?= $unlock_message_type ?> mb-2">
                            <?= htmlspecialchars($unlock_message) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="unlock_course">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="form-group">
                            <label for="course_code" class="form-label">Invitation Key / Course Code</label>
                            <input type="text" name="course_code" id="course_code" class="form-control" required placeholder="e.g. CRSCD123" style="text-transform: uppercase;">
                        </div>
                        <button type="submit" class="btn">Unlock Course</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Change Password</h3>
                    <?php if ($password_message): ?>
                        <div role="status" aria-live="polite" class="alert alert-<?= $password_message_type ?> mb-2">
                            <?= htmlspecialchars($password_message) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password (8+ characters)</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8">
                        </div>
                        <button type="submit" class="btn">Update Password</button>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <?= renderTenantFooter($activeTenant) ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeBtn = document.getElementById('theme-toggle-btn');
            const savedTheme = localStorage.getItem('lms_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            
            function applyTheme(theme) {
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    if (themeBtn) themeBtn.textContent = '☀️ Light Mode';
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    if (themeBtn) themeBtn.textContent = '🌙 Dark Mode';
                }
            }

            applyTheme(savedTheme);

            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    const currentTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                    const newTheme = (currentTheme === 'dark') ? 'light' : 'dark';
                    localStorage.setItem('lms_theme', newTheme);
                    applyTheme(newTheme);
                });
            }
        });
    </script>
</body>
</html>
