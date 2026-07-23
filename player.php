<?php
/**
 * Superable Learning LMS - Course Player
 * 
 * Dynamic wrapper that loads module content and handles progress tracking.
 */

// 1. Session Check
require_once 'config.php';
$pdo = get_db_connection();
session_start();

// Allow guest access for public courses
if (!isset($_SESSION['user_id'])) {
    $user_id = 'guest_' . session_id(); 
    $user_name = 'Guest User';
    $user_email = 'guest@example.com';
} else {
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['full_name'] ?? 'LMS User';
    $user_email = 'guest@example.com';
    try {
        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        if ($user_data) {
            $user_name = $user_data['full_name'];
            $user_email = $user_data['email'];
        }
    } catch (PDOException $e) {
        // Fallback
    }
}

// 2. Get Requested Course
$course_id = $_GET['course_id'] ?? '';

// Handle cases where external launchers (like XCL) append parameters incorrectly using '?' instead of '&'
if (strpos($course_id, '?') !== false) {
    list($clean_course_id, $extra_query) = explode('?', $course_id, 2);
    $course_id = $clean_course_id;
    
    // Parse the extra query parameters and merge them into $_GET
    parse_str($extra_query, $extra_params);
    $_GET = array_merge($_GET, $extra_params);
}

// 3. Security Check & Course Validation
$activeTenant = resolveTenantKey();
$course_dir = resolveCourseDir($course_id, $activeTenant);

if (empty($course_id) || !$course_dir) {
    // Basic friendly error matching UI specs
    $error_html = '
    <!DOCTYPE html>
    <html lang="en">
    <head><title>Course Not Found</title><link rel="stylesheet" href="../style.css"></head>
    <body>
        <main class="container mx-auto px-4 py-8">
            <div role="alert" class="p-6" style="background-color: var(--color-critical-bg); border: 1px solid var(--color-critical-border); color: var(--color-critical-text); border-radius: 0.5rem;">
                <h1 class="m-0 text-xl font-bold" style="color: var(--color-critical-text);">Course Not Found</h1>
                <p class="mt-4">The requested course could not be located or does not exist.</p>
                <p class="mt-2 text-sm" style="color: var(--color-critical-text); opacity: 0.8;">Requested Course ID: <code>' . htmlspecialchars($course_id) . '</code></p>
                <p class="mt-1 text-sm" style="color: var(--color-critical-text); opacity: 0.8;">Request URI: <code>' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . '</code></p>
                <a href="index.php" class="cta-button mt-4" style="background-color: var(--color-text-dark);">Return to Dashboard</a>
            </div>
        </main>
    </body>
    </html>';
    die($error_html);
}

// 4. Locate and Decode Manifest
$manifest_path = $course_dir . DIRECTORY_SEPARATOR . 'course_structure.json';
if (!file_exists($manifest_path)) {
    die("Error: Course manifest missing.");
}
$manifest = json_decode(file_get_contents($manifest_path), true);
if ($manifest === null) {
    die("Error: Course manifest has an invalid format (JSON syntax error).");
}
if (isset($manifest['modules'])) {
    pre_process_manifest_modules($manifest['modules'], $course_dir);
}

// Access Control Check
$access = $manifest['properties']['access'] ?? ['type' => 'public'];
$has_permission = false;
try {
    $stmt = $pdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    $has_permission = (bool)$stmt->fetch();
} catch (PDOException $e) {
    // Fail closed on DB error
}

// Bypass local LMS access restrictions if loaded via an external xAPI/LRS launch (e.g., Build Capable XCL)
$is_xapi_launch = (isset($_GET['endpoint']) && isset($_GET['auth'])) 
    || (isset($_GET['xAPILaunchService']) && isset($_GET['xAPILaunchKey']));

if (!$has_permission && $access['type'] !== 'public' && !$is_xapi_launch) {
    $teaser_link = $access['teaser_link'] ?? 'index.php';
    die('
    <!DOCTYPE html>
    <html lang="en">
    <head><title>Access Denied</title><link rel="stylesheet" href="../style.css"></head>
    <body>
        <main class="container mx-auto px-4 py-8">
            <div role="alert" class="p-6" style="background-color: var(--color-warning-bg); border: 1px solid var(--color-warning-border); color: var(--color-warning-text); border-radius: 0.5rem;">
                <h1 class="m-0 text-xl font-bold" style="color: var(--color-warning-text);">Access Restricted</h1>
                <p class="mt-4">You do not have permission to access this course content.</p>
                <a href="'.htmlspecialchars($teaser_link).'" class="cta-button mt-4">Unlock this Course</a>
            </div>
        </main>
    </body>
    </html>');
}

$modules = $manifest['modules'] ?? [];
$course_title = $manifest['properties']['title'] ?? 'Untitled Course';
$assets = $manifest['properties']['assets'] ?? [];


// 5. Context Injection setup happens in HTML <head>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course_title) ?> - Superable Learning</title>
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Link to master stylesheet -->
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    
    <!-- Dynamic Course Assets -->
    <?php if (isset($assets['css'])): foreach ($assets['css'] as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(getTenantCoursesWebPath()) ?>/<?= htmlspecialchars($course_id) ?>/<?= htmlspecialchars($css) ?>">
    <?php endforeach; endif; ?>
    
    <!-- Always load Superable custom web components for rich interactivity support -->
    <script src="assets/components/sl-components.js?v=<?= time() ?>" defer></script>

    <!-- xAPI Service (Always load, defaults to console.log if no LMS launch) -->
    <script src="<?= htmlspecialchars(getTenantCoursesWebPath()) ?>/<?= htmlspecialchars($course_id) ?>/js/xapi-service.js?v=<?= time() ?>"></script>
    
    <style>
        .player-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        @media(min-width: 900px) {
            .player-layout {
                grid-template-columns: 320px 1fr; /* Slightly wider for nested nav */
            }
        }

        /* Sidebar Nav Styles */
        .sidebar-nav {
            background: white;
            border-radius: 0.5rem;
            border: 1px solid #e5e5e5;
            padding: 1.5rem;
            height: fit-content;
            /* Sticky positioning for long reading */
            position: sticky;
            top: 2rem;
        }

        /* Accordion Overrides for Sidebar */
        .sidebar-nav jw-accordion {
            display: block;
            margin-top: 1rem;
        }
        
        .sidebar-nav .jw-accordion-header {
            margin: 0;
            font-size: 1rem;
        }

        .sidebar-nav .jw-accordion-trigger {
            padding: 0.75rem;
            background: var(--color-bg-light);
            border: 1px solid #e5e5e5;
            border-radius: 0.25rem;
            font-weight: 700;
        }

        .sidebar-nav .jw-accordion-panel {
            padding: 0.5rem 0 0.5rem 1rem;
            border-left: 2px solid var(--color-primary);
        }

        .module-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .module-btn {
            width: 100%;
            text-align: left;
            padding: 0.75rem;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--color-text-dark);
            transition: all 0.2s ease;
            position: relative;
        }

        .module-btn:hover, .module-btn:focus-visible {
            background-color: var(--color-bg-light);
            border-color: var(--color-primary);
            outline-offset: -2px;
        }

        .module-btn[aria-current="true"] {
            background-color: var(--color-primary);
            color: white;
            font-weight: 700;
        }
        
        /* Add a checkmark visual for completed items */
        .module-btn.completed::after {
            content: "✓";
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-success-text);
            font-weight: bold;
        }
        
        .module-btn[aria-current="true"].completed::after {
            color: white; /* Contrast fix when active */
        }

        /* Content Area Styles */
        .content-area {
            background: white;
            border-radius: 0.5rem;
            border: 1px solid #e5e5e5;
            padding: 2rem;
            min-height: 50vh;
        }

        .completion-container {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px dashed #e5e5e5;
            text-align: right;
        }
        
        .content-loading {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Module Navigation Row */
        .module-nav-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px dashed #e5e5e5;
        }

        .nav-btn {
            background-color: var(--color-bg-light);
            color: var(--color-text-dark);
            border: 2px solid var(--color-neutral-mid);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .nav-btn:hover:not(:disabled) {
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            visibility: hidden; /* Hide completely when at boundaries */
        }
    </style>

    <script>
        const LMS_CONTEXT = { 
            courseId: <?= json_encode($course_id) ?>, 
            coursesWebPath: <?= json_encode(getTenantCoursesWebPath($activeTenant)) ?>,
            tenantKey: <?= json_encode($activeTenant) ?>,
            userId: <?= json_encode($user_id) ?>,
            userName: <?= json_encode($user_name) ?>,
            userEmail: <?= json_encode($user_email) ?>,
            manifest: <?= json_encode($manifest) ?>
        };
    </script>
</head>
<body class="bg-bg-light">
    <a href="#course-content" class="skip-link">Skip to main content</a>

    <!-- Header / Top Nav -->
    <header class="bg-primary pt-4 pb-4 px-4 text-white" role="banner">
        <div class="container-wide mx-auto flex items-center justify-between">
            <a href="index.php" class="text-white font-bold text-sm" style="text-decoration: none;" aria-label="Return to Dashboard">← Return to Dashboard</a>
            <div class="m-0 text-xl font-bold" style="color: white;"><?= htmlspecialchars($course_title) ?></div>
            <div style="width: 150px;"></div> <!-- Spacer for flexbox centering -->
        </div>
    </header>

    <!-- Application Layout -->
    <div class="player-layout">
        
        <!-- Sidebar for Module Links -->
        <aside class="sidebar bg-light">
            <nav class="sidebar-nav" id="course-nav" aria-label="Course Navigation">
                <h2 class="text-lg m-0 mb-4 text-neutral-mid">Course Navigation</h2>
                <!-- Rendered dynamically via JavaScript -->
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main id="course-content" class="content-area" aria-busy="false">
            <!-- Content gets injected here via JS -->
            <h2>Loading Course...</h2>
        </main>

    </div>

    <?= renderTenantFooter($activeTenant) ?>

    <!-- Player Logic Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Dynamic Sidebar Generation
            const navContainer = document.getElementById('course-nav');
            let sidebarModCount = 1;

            function renderSidebarModules(items, parentElement) {
                const list = document.createElement('ul');
                list.className = 'module-list';

                items.forEach(item => {
                    const li = document.createElement('li');
                    if (item.group !== undefined) {
                        // Render Accordion Group
                        const accordion = document.createElement('jw-accordion');
                        accordion.setAttribute('level', '3');
                        accordion.setAttribute('no-region', '');

                        const accordionItem = document.createElement('jw-accordion-item');
                        accordionItem.setAttribute('title', item.group);
                        if (item.expanded) {
                            accordionItem.setAttribute('expanded', '');
                        }

                        renderSidebarModules(item.items, accordionItem);
                        accordion.appendChild(accordionItem);
                        li.appendChild(accordion);
                    } else {
                        // Render Module Button
                        const btn = document.createElement('button');
                        btn.className = 'module-btn';
                        btn.setAttribute('data-module-id', item.id);
                        btn.setAttribute('data-src', item.src);
                        btn.setAttribute('aria-current', 'false');

                        let rawTitle = item.h1_title ? item.h1_title : (item.title || '');
                        rawTitle = rawTitle.replace(/^Module\s*\d+:\s*/i, '').trim();

                        let formattedTitle = '';
                        if (rawTitle.toLowerCase() === 'module ' + sidebarModCount || rawTitle === '') {
                            formattedTitle = `Module ${sidebarModCount}`;
                        } else {
                            formattedTitle = `Module ${sidebarModCount}: ${rawTitle}`;
                        }

                        btn.textContent = formattedTitle;
                        sidebarModCount++;

                        btn.addEventListener('click', () => {
                            if (btn.getAttribute('aria-current') !== 'true') {
                                loadModuleContent(btn);
                            }
                        });

                        li.appendChild(btn);
                    }
                    list.appendChild(li);
                });

                parentElement.appendChild(list);
            }

            // Build the sidebar
            if (LMS_CONTEXT.manifest && LMS_CONTEXT.manifest.modules) {
                renderSidebarModules(LMS_CONTEXT.manifest.modules, navContainer);
            }

            // Keep a real array of buttons to easily find index by context
            const moduleBtnsArray = Array.from(document.querySelectorAll('.module-btn'));
            const contentContainer = document.getElementById('course-content');
            let currentModuleId = null;
            let currentModuleIndex = 0;
            let moduleStartTime = Date.now();

            // Local Telemetry API Logging Helper
            async function logLocalInteraction(moduleId, eventType, eventValue = null) {
                if (!moduleId) return;
                try {
                    await fetch(`api.php?tenant=${LMS_CONTEXT.tenantKey}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'log_interaction',
                            course_id: LMS_CONTEXT.courseId,
                            module_id: moduleId,
                            event_type: eventType,
                            event_value: eventValue
                        })
                    });
                } catch (error) {
                    console.error("Local telemetry log failed:", error);
                }
            }
            
            // Dynamic JS Asset Loading
            const courseAssets = {
                js: <?= json_encode($assets['js'] ?? []) ?>
            };

            function loadCourseScripts() {
                courseAssets.js.forEach(src => {
                    const script = document.createElement('script');
                    script.src = `${LMS_CONTEXT.coursesWebPath}/${LMS_CONTEXT.courseId}/${src}?v=${Date.now()}`;
                    script.defer = true;
                    document.body.appendChild(script);
                });
            }
            
            loadCourseScripts();

            // Handle clicks via delegation (Reveal Accessible Version, Link clicks, etc.)
            contentContainer.addEventListener('click', (e) => {
                // 1. Track Download eBook, Workshop details and other link clicks
                const link = e.target.closest('a');
                if (link) {
                    const href = link.getAttribute('href') || '';
                    const linkText = link.textContent.trim();
                    
                    // Track if it's an external link, a workshop link, a download link, or has target="_blank"
                    const isTrackedLink = link.hasAttribute('download') || 
                                          link.classList.contains('btn') ||
                                          link.classList.contains('cta-button') ||
                                          href.includes('content.buildxcl.com') ||
                                          href.includes('workshops.html') ||
                                          link.getAttribute('target') === '_blank';

                    if (isTrackedLink) {
                        const isDownload = href.includes('download') || href.includes('buildxcl.com') || link.hasAttribute('download');
                        
                        // Local Telemetry Log
                        logLocalInteraction(currentModuleId, isDownload ? 'download_file' : 'click_link', {
                            text: linkText,
                            href: link.href
                        });

                        // xAPI Tracking
                        if (window.xapi) {
                            const verb = {
                                "id": "http://adlnet.gov/expapi/verbs/interacted",
                                "display": { "en-US": isDownload ? "downloaded" : "accessed" }
                            };

                            // Generate a clean sub-activity ID under the course namespace to avoid CORS/LRS rejection
                            let cleanLinkId = '';
                            if (isDownload) {
                                cleanLinkId = 'download-ebook';
                            } else if (href.includes('workshops.html')) {
                                cleanLinkId = 'view-workshops';
                            } else {
                                try {
                                    const urlObj = new URL(link.href);
                                    cleanLinkId = (urlObj.hostname + urlObj.pathname).replace(/[^a-zA-Z0-9]/g, '-');
                                } catch (err) {
                                    cleanLinkId = href.replace(/[^a-zA-Z0-9]/g, '-');
                                }
                            }
                            const activityId = `${window.xapi.courseId}/links/${cleanLinkId}`;

                            window.xapi.sendStatement(verb, {
                                "id": activityId,
                                "definition": {
                                    "name": { "en-US": linkText },
                                    "description": { "en-US": `User clicked link: ${linkText} (${link.href})` },
                                    "type": isDownload ? "http://adlnet.gov/expapi/activities/file" : "http://adlnet.gov/expapi/activities/link"
                                }
                            });
                        }
                    }
                }

                // 2. Handle Reveal Accessible Version clicks
                if (e.target.matches('.reveal-btn')) {
                    const container = e.target.closest('.pattern-container');
                    const accessibleVersion = container.querySelector('.accessible-version');
                    const inaccessibleVersion = container.querySelector('.inaccessible-version');
                    
                    if (accessibleVersion && inaccessibleVersion) {
                        inaccessibleVersion.hidden = true;
                        accessibleVersion.hidden = false;
                        e.target.hidden = true; // Hide the button once revealed
                        
                        const moduleTitle = moduleBtnsArray[currentModuleIndex] ? moduleBtnsArray[currentModuleIndex].textContent.replace('(Completed)', '').trim() : '';

                        // Local Telemetry Log (Only log if user-initiated)
                        if (e.isTrusted) {
                            logLocalInteraction(currentModuleId, 'reveal_accessible', {
                                module_title: moduleTitle
                            });
                        }

                        // xAPI Tracking (Only track if user-initiated)
                        if (window.xapi && e.isTrusted) {
                            window.xapi.sendStatement(window.xapi.verbs.INTERACTED, {
                                "id": `${window.xapi.courseId}/modules/${currentModuleId}/reveal-accessible`,
                                "definition": {
                                    "name": { "en-US": `Reveal Accessible Version: ${moduleTitle}` },
                                    "description": { "en-US": `User revealed the accessible solution for the ${moduleTitle} module.` },
                                    "type": "http://adlnet.gov/expapi/activities/interaction"
                                }
                            });
                        }

                        // Focus Management
                        const accessibleHeading = accessibleVersion.querySelector('h1, h2, h3');
                        if (accessibleHeading) {
                            accessibleHeading.setAttribute('tabindex', '-1');
                            accessibleHeading.focus();
                        }

                        // Screen Reader Announcement
                        const announcement = document.createElement('div');
                        announcement.setAttribute('aria-live', 'polite');
                        announcement.className = 'sr-only';
                        announcement.textContent = 'Accessible version revealed.';
                        document.body.appendChild(announcement);
                        setTimeout(() => announcement.remove(), 1000);
                    }
                }
            });

            const revealedModules = new Set();

            // 1. Initial State Sync (Check what's already completed)
            async function syncProgressState() {
                try {
                    const response = await fetch(`api.php?action=get_state&course_id=${LMS_CONTEXT.courseId}&tenant=${LMS_CONTEXT.tenantKey}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();
                    
                    if (data.completed && Array.isArray(data.completed)) {
                        data.completed.forEach(modId => {
                            const btn = document.querySelector(`.module-btn[data-module-id="${modId}"]`);
                            if (btn && !btn.classList.contains('completed')) {
                                btn.classList.add('completed');
                                btn.innerHTML = `${btn.textContent.trim()} <span class="sr-only">(Completed)</span>`;
                            }
                        });
                    }

                    if (data.revealed && Array.isArray(data.revealed)) {
                        data.revealed.forEach(modId => revealedModules.add(modId));
                    }

                    return data;
                } catch (error) {
                    console.error("Failed to sync progress:", error);
                    return null;
                }
            }

            // 2. Fetch HTML Content
            async function loadModuleContent(button) {
                const src = button.getAttribute('data-src');
                const moduleId = button.getAttribute('data-module-id');

                // Log dwell time for the module we are leaving
                if (currentModuleId && currentModuleId !== moduleId) {
                    const durationSeconds = Math.round((Date.now() - moduleStartTime) / 1000);
                    logLocalInteraction(currentModuleId, 'dwell_time', { seconds: durationSeconds });
                }
                moduleStartTime = Date.now();
                
                // Determine our new index
                currentModuleIndex = moduleBtnsArray.indexOf(button);
                const isFirst = currentModuleIndex === 0;
                const isLast = currentModuleIndex === moduleBtnsArray.length - 1;
                
                if (!src) return;

                // Update UI state
                contentContainer.setAttribute('aria-busy', 'true');
                contentContainer.classList.add('content-loading');
                
                moduleBtnsArray.forEach(btn => btn.setAttribute('aria-current', 'false'));
                button.setAttribute('aria-current', 'true');
                currentModuleId = moduleId;
                
                // Auto-expand parent accordion if exists
                let parentAccordionItem = button.closest('jw-accordion-item');
                if (parentAccordionItem && !parentAccordionItem.hasAttribute('expanded')) {
                    parentAccordionItem.setAttribute('expanded', '');
                }

                try {
                    const url = `${LMS_CONTEXT.coursesWebPath}/${LMS_CONTEXT.courseId}/${src}`;
                    const response = await fetch(url);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    
                    const html = await response.text();
                    
                    // xAPI Module Experience Tracking
                    if (window.xapi) {
                        window.xapi.sendStatement(window.xapi.verbs.EXPERIENCED, {
                            "id": `${window.xapi.courseId}/modules/${moduleId}`,
                            "definition": {
                                "name": { "en-US": button.textContent.replace('(Completed)', '').trim() },
                                "type": "http://adlnet.gov/expapi/activities/module"
                            }
                        });
                    }

                    // Determine what the primary action button should look like based on current completion
                    const isAlreadyCompleted = button.classList.contains('completed');
                    
                    // Determine next and prev button labels for robust navigation
                    let prevLabel = "&larr; Previous";
                    let nextLabel = "Next &rarr;";
                    let prevTitle = "";
                    let nextTitle = "";

                    if (!isFirst) {
                        prevTitle = moduleBtnsArray[currentModuleIndex - 1].textContent.replace('(Completed)', '').trim();
                        prevLabel = `&larr; <span class="sr-only">Previous: </span>${prevTitle}`;
                    }

                    // The main right-side element (Next or Finish)
                    let primaryActionHTML = '';
                    
                    if (!isLast) {
                        nextTitle = moduleBtnsArray[currentModuleIndex + 1].textContent.replace('(Completed)', '').trim();
                        nextLabel = `<span class="sr-only">Next: </span>${nextTitle} &rarr;`;
                        primaryActionHTML = `
                            <button id="btn-next-module" class="nav-btn" aria-label="Go to Next Module: ${nextTitle}">
                                ${nextLabel}
                            </button>
                        `;
                    } else {
                        if (isAlreadyCompleted) {
                            primaryActionHTML = `
                                <div class="p-4" style="background-color: var(--color-success-bg); border: 2px solid var(--color-success-border); border-radius: 0.5rem; text-align: left; margin-left: auto;">
                                    <h3 id="completion-heading" class="m-0 text-lg" style="color: var(--color-success-text);">Course Completed</h3>
                                    <a href="index.php" class="cta-button mt-4" style="background-color: var(--color-success-border);">Return to Dashboard</a>
                                </div>
                            `;
                        } else {
                            primaryActionHTML = `
                                <button id="btn-mark-complete" class="nav-btn" style="background-color: var(--color-primary); color: white;" aria-label="Mark Course Complete">
                                    Mark Course Complete
                                </button>
                            `;
                        }
                    }
                    
                    // Construct bottom navigation row
                    const navHTML = `
                        <div id="completion-container" aria-live="polite" class="mt-8 text-right"></div>
                        <nav aria-label="Module Navigation" class="module-nav-row mt-4" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="nav-previous" style="flex: 1;">
                                ${!isFirst ? `<button id="btn-prev-module" class="nav-btn" aria-label="Go to Previous Module: ${prevTitle}">${prevLabel}</button>` : ''}
                            </div>
                            
                            <div class="nav-indicator" style="flex: 1; text-align: center; font-weight: 600; color: var(--color-neutral-mid);">
                                Module ${currentModuleIndex + 1} of ${moduleBtnsArray.length}
                            </div>

                            <div class="nav-next" style="flex: 1; display: flex; justify-content: flex-end; align-items: center;">
                                ${primaryActionHTML}
                            </div>
                        </nav>
                    `;
                    
                    contentContainer.innerHTML = html + navHTML;
                    
                    // Re-execute script elements embedded inside module HTML fragment (HTML5 innerHTML script execution fix)
                    contentContainer.querySelectorAll('script').forEach(oldScript => {
                        const newScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        newScript.textContent = oldScript.textContent;
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                    
                    // Process external and learning links inside course content for accessibility and target tab
                    contentContainer.querySelectorAll('a').forEach(link => {
                        const href = link.getAttribute('href') || '';
                        const isLearningLink = href.includes('content.buildxcl.com');
                        const isExternal = href.startsWith('http') && !href.startsWith(window.location.origin);
                        
                        // Check if it is a link that should open in a new tab
                        if (isLearningLink || isExternal || link.getAttribute('target') === '_blank') {
                            // Ensure it opens in a new tab
                            link.setAttribute('target', '_blank');
                            link.setAttribute('rel', 'noopener noreferrer');
                            
                            // Check if it already has screen reader text to avoid duplicating it
                            const hasSrText = link.querySelector('.sr-only') && 
                                              link.querySelector('.sr-only').textContent.toLowerCase().includes('new tab');
                            
                            if (!hasSrText) {
                                // Add screen reader accessible text
                                const srSpan = document.createElement('span');
                                srSpan.className = 'sr-only';
                                srSpan.textContent = ' (opens in a new tab)';
                                link.appendChild(srSpan);
                                
                                // Enrich aria-label if it exists to also announce it
                                const ariaLabel = link.getAttribute('aria-label');
                                if (ariaLabel && !ariaLabel.toLowerCase().includes('new tab')) {
                                    link.setAttribute('aria-label', `${ariaLabel} (opens in a new tab)`);
                                }
                            }
                        }
                    });
                    
                    // Bind interaction listeners
                    if (!isAlreadyCompleted && isLast) {
                        document.getElementById('btn-mark-complete').addEventListener('click', markCurrentComplete);
                    }
                    if (!isFirst) {
                        document.getElementById('btn-prev-module').addEventListener('click', navigatePrevious);
                    }
                    if (!isLast) {
                        document.getElementById('btn-next-module').addEventListener('click', navigateNext);
                    }

                    // Restore previously revealed accessible version state
                    if (revealedModules.has(moduleId)) {
                        const revealBtn = contentContainer.querySelector('.reveal-btn');
                        if (revealBtn) {
                            revealBtn.click();
                        }
                    }

                    // Accessibility: Focus Management
                    const firstHeading = contentContainer.querySelector('h1, h2');
                    if (firstHeading) {
                        firstHeading.setAttribute('tabindex', '-1');
                        firstHeading.focus();
                        firstHeading.style.outline = 'none'; 
                    } else {
                        contentContainer.setAttribute('tabindex', '-1');
                        contentContainer.focus();
                    }

                } catch (error) {
                    console.error("Failed to load module:", error);
                    contentContainer.innerHTML = `
                        <div role="alert">
                            <h2>Failed to load content</h2>
                            <p>We could not load the requested module. Please try again later.</p>
                        </div>
                    `;
                } finally {
                    contentContainer.setAttribute('aria-busy', 'false');
                    contentContainer.classList.remove('content-loading');
                }
            }

            // 3. Complete Module API call
            async function markCurrentComplete() {
                if (!currentModuleId) return;
                
                const btn = document.getElementById('btn-mark-complete');
                const container = document.getElementById('completion-container');
                btn.textContent = "Saving...";
                btn.disabled = true;
                btn.focus(); // Hold focus during async call

                try {
                    const response = await fetch(`api.php?tenant=${LMS_CONTEXT.tenantKey}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'mark_complete',
                            course_id: LMS_CONTEXT.courseId,
                            module_id: currentModuleId,
                            tenant: LMS_CONTEXT.tenantKey
                        })
                    });

                    if (!response.ok) throw new Error("Failed to save progress");
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        // Local Telemetry Log
                        logLocalInteraction(currentModuleId, 'course_completed');

                        // xAPI Course Completion Tracking
                        if (window.xapi) {
                            window.xapi.sendStatement(window.xapi.verbs.COMPLETED, {
                                "id": window.xapi.courseId,
                                "definition": {
                                    "name": { "en-US": <?= json_encode($course_title) ?> },
                                    "type": "http://adlnet.gov/expapi/activities/course"
                                }
                            }, {
                                "completion": true,
                                "success": true
                            });
                        }

                        // Update UI to show completion and move focus to a meaningful next step
                        // UX Update: Do not redirect to dashboard immediately. Show success and let them click "Return to Dashboard".
                        const nextActionHTML = `<a href="index.php" class="cta-button mt-4" style="background-color: var(--color-success-border);">Return to Dashboard</a>`;
                        const headingText = "Course Completed Successfully";

                        container.innerHTML = `
                            <div class="p-4 mb-4" style="background-color: var(--color-success-bg); border: 2px solid var(--color-success-border); border-radius: 0.5rem; text-align: left;">
                                <h3 id="completion-heading" class="m-0 text-lg" style="color: var(--color-success-text);">${headingText}</h3>
                                ${nextActionHTML}
                            </div>
                        `;
                        
                        // Hide the mark complete button entirely
                        if (btn) btn.style.display = 'none';
                        
                        // Shift focus to the success heading so the screen reader reads the success message natively
                        const successHeading = document.getElementById('completion-heading');
                        successHeading.setAttribute('tabindex', '-1');
                        successHeading.focus();
                        successHeading.style.outline = 'none';
                        
                        // Update Sidebar
                        const navBtn = moduleBtnsArray[currentModuleIndex];
                        if (navBtn && !navBtn.classList.contains('completed')) {
                            navBtn.classList.add('completed');
                            const cleanText = navBtn.textContent.replace('(Completed)', '').trim();
                            navBtn.innerHTML = `${cleanText} <span class="sr-only">(Completed)</span>`;
                        }
                    }
                } catch(error) {
                    console.error("Completion error:", error);
                    container.innerHTML = `
                        <div role="alert" class="p-4" style="background-color: var(--color-critical-bg); border: 1px solid var(--color-critical-border); border-radius: 0.5rem; text-align: left;">
                            <p class="m-0" style="color: var(--color-critical-text);">There was an error saving your progress. Please check your connection and try again.</p>
                            <button id="btn-retry-complete" class="cta-button mt-4" style="background-color: var(--color-critical-border);">Retry</button>
                        </div>
                    `;
                    document.getElementById('btn-retry-complete').addEventListener('click', markCurrentComplete);
                    
                    const alertBox = container.querySelector('[role="alert"]');
                    alertBox.setAttribute('tabindex', '-1');
                    alertBox.focus();
                    alertBox.style.outline = 'none';
                }
            }
            
            // 4. Navigation Handlers
            function navigatePrevious() {
                if (currentModuleIndex > 0) {
                    loadModuleContent(moduleBtnsArray[currentModuleIndex - 1]);
                }
            }
            
            async function navigateNext() {
                if (currentModuleIndex < moduleBtnsArray.length - 1) {
                    const currentBtn = moduleBtnsArray[currentModuleIndex];
                    
                    // Progress save phase
                    if (currentBtn && !currentBtn.classList.contains('completed')) {
                        const nextBtn = document.getElementById('btn-next-module');
                        const originalNextBtnHTML = nextBtn ? nextBtn.innerHTML : '';
                        
                        // Disable buttons to avoid race conditions
                        if (nextBtn) {
                            nextBtn.textContent = "Saving...";
                            nextBtn.disabled = true;
                        }
                        const completeBtn = document.getElementById('btn-mark-complete');
                        if (completeBtn) completeBtn.disabled = true;

                        try {
                            const response = await fetch(`api.php?tenant=${LMS_CONTEXT.tenantKey}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    action: 'mark_complete',
                                    course_id: LMS_CONTEXT.courseId,
                                    module_id: currentModuleId,
                                    tenant: LMS_CONTEXT.tenantKey
                                })
                            });

                            if (!response.ok) throw new Error("Failed to save progress");
                            const data = await response.json();
                            
                            if (data.status === 'success') {
                                // Mark as completed in sidebar
                                currentBtn.classList.add('completed');
                                const cleanText = currentBtn.textContent.replace('(Completed)', '').trim();
                                currentBtn.innerHTML = `${cleanText} <span class="sr-only">(Completed)</span>`;
                            } else {
                                throw new Error("API returned failure status");
                            }
                        } catch (error) {
                            console.error("Completion error:", error);
                            const container = document.getElementById('completion-container');
                            if (container) {
                                container.innerHTML = `
                                    <div role="alert" class="p-4" style="background-color: var(--color-critical-bg); border: 1px solid var(--color-critical-border); border-radius: 0.5rem; text-align: left;">
                                        <p class="m-0" style="color: var(--color-critical-text);">There was an error saving your progress. Please check your connection and try again.</p>
                                    </div>
                                `;
                                const alertBox = container.querySelector('[role="alert"]');
                                if (alertBox) {
                                    alertBox.setAttribute('tabindex', '-1');
                                    alertBox.focus();
                                    alertBox.style.outline = 'none';
                                }
                            }
                            
                            // Restore next button so user can retry
                            if (nextBtn) {
                                nextBtn.innerHTML = originalNextBtnHTML;
                                nextBtn.disabled = false;
                            }
                            if (completeBtn) completeBtn.disabled = false;
                            
                            // Stop execution here to prevent loading next module
                            return; 
                        }
                    }

                    // Loading next module phase (this naturally shifts focus to the newly loaded h1)
                    loadModuleContent(moduleBtnsArray[currentModuleIndex + 1]);
                }
            }



            // 6. Init (Sync progress, then load first module)
            syncProgressState().then((data) => {
                // Send xAPI Course Initialized Statement to register course ID and metadata in LRS
                if (window.xapi) {
                    const alreadyInitialized = window.xapiService && window.xapiService.isInitialized;
                    if (!alreadyInitialized) {
                        window.xapi.sendStatement(window.xapi.verbs.INITIALIZED, {
                            "id": window.xapi.courseId,
                            "definition": {
                                "name": { "en-US": <?= json_encode($course_title) ?> },
                                "description": { "en-US": "Course player initialized." },
                                "type": "http://adlnet.gov/expapi/activities/course"
                            }
                        });
                    }
                }

                // Restore last read active module (Present choice card to user)
                let targetModule = null;
                if (data && data.last_active_module_id) {
                    targetModule = document.querySelector(`.module-btn[data-module-id="${data.last_active_module_id}"]`);
                }

                if (targetModule && targetModule !== moduleBtnsArray[0]) {
                    const moduleTitle = targetModule.textContent.replace('(Completed)', '').trim();
                    contentContainer.innerHTML = `
                        <div class="resume-prompt-card" role="status" style="background-color: #f8fafc; border: 2px solid var(--color-primary); border-radius: 0.5rem; padding: 2rem; max-width: 600px; margin: 4rem auto; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); text-align: left;">
                            <h2 class="text-xl font-bold m-0 mb-4" style="color: var(--color-text-dark);">Welcome back!</h2>
                            <p class="m-0 mb-6 text-neutral-dark" style="font-size: 1.05rem; line-height: 1.5; color: var(--color-text-dark);">Would you like to pick up where you left off on <strong>${moduleTitle}</strong>?</p>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <button id="btn-resume-yes" class="nav-btn" style="background-color: var(--color-primary); color: white; border-color: var(--color-primary); cursor: pointer;" aria-label="Resume course at ${moduleTitle}">
                                    Yes, Resume Course
                                </button>
                                <button id="btn-resume-no" class="nav-btn" style="cursor: pointer;" aria-label="Start course from the beginning">
                                    Start from Beginning
                                </button>
                            </div>
                        </div>
                    `;

                    document.getElementById('btn-resume-yes').addEventListener('click', () => {
                        loadModuleContent(targetModule);
                    });

                    document.getElementById('btn-resume-no').addEventListener('click', () => {
                        if (moduleBtnsArray[0]) {
                            loadModuleContent(moduleBtnsArray[0]);
                        }
                    });

                    const yesBtn = document.getElementById('btn-resume-yes');
                    if (yesBtn) {
                        yesBtn.focus();
                    }
                } else {
                    const firstModule = document.querySelector('.module-btn[aria-current="true"]') || moduleBtnsArray[0];
                    if (firstModule) {
                        loadModuleContent(firstModule);
                    }
                }
            });

            // Log final dwell time when user leaves/closes page
            window.addEventListener('pagehide', () => {
                if (currentModuleId) {
                    const durationSeconds = Math.round((Date.now() - moduleStartTime) / 1000);
                    const payload = {
                        action: 'log_interaction',
                        course_id: LMS_CONTEXT.courseId,
                        module_id: currentModuleId,
                        event_type: 'dwell_time',
                        event_value: JSON.stringify({ seconds: durationSeconds })
                    };
                    const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                    navigator.sendBeacon(`api.php?tenant=${LMS_CONTEXT.tenantKey}`, blob);
                }
            });

        });
    </script>
</body>
</html>
