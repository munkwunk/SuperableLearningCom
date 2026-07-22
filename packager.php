<?php
/**
 * Superable Learning - Web Course Packager & Modular Builder Tool
 * 
 * Modular block-by-block course builder and 1-click ZIP packager.
 * Designed for non-technical users and AI workflow optimization.
 * 100% WCAG 2.2 AA compliant with accessible ARIA Tablist pattern (4.1.2),
 * screen reader status announcements (4.1.3), accessible Help Modals with focus trapping
 * and focus restoration (2.4.3), keyboard focus visible indicators, and optimal forward
 * tab order for module previewing.
 */

require_once __DIR__ . '/config.php';
$tenantMetadata = getTenantMetadata();

// Determine URL Prefix depending on whether request comes through /packager/ or /packager.php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isSubfolderUrl = (strpos($requestUri, '/packager/') !== false);
$urlPrefix = $isSubfolderUrl ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modular Course Builder & Packager Tool — Superable Learning</title>
    <link rel="stylesheet" href="<?= $urlPrefix ?>style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- JSZip & FileSaver for Client-Side ZIP Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    
    <!-- JW Components for Interactive Preview rendering -->
    <script src="<?= $urlPrefix ?>assets/components/jw-components.js" defer></script>

    <style>
        :root {
            --builder-bg: #f8fafc;
            --builder-card-bg: #ffffff;
            --builder-border: #cbd5e1;
            --builder-primary: #319795;
            --builder-primary-hover: #2c7a7b;
            --builder-dark: #1a202c;
            --builder-accent: #3182ce;
        }

        body {
            background-color: var(--builder-bg);
            font-family: 'Atkinson Hyperlegible', system-ui, -apple-system, sans-serif;
            color: #2d3748;
        }

        .builder-header {
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            color: white;
            padding: 2.5rem 0 2rem 0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .builder-container {
            max-width: 1100px;
            padding: 2rem 1rem;
        }

        /* Accessible Tablist Styles */
        .tab-bar {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #cbd5e1;
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            color: #4a5568;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            color: var(--builder-primary);
        }

        .tab-btn:focus-visible {
            outline: 3px solid var(--builder-accent);
            outline-offset: -2px;
            background: rgba(49, 130, 206, 0.08);
            border-radius: 0.375rem 0.375rem 0 0;
        }

        .tab-btn[aria-selected="true"] {
            color: var(--builder-primary);
            border-bottom-color: var(--builder-primary);
            background: rgba(49, 151, 149, 0.08);
            border-radius: 0.375rem 0.375rem 0 0;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .tab-panel:focus-visible {
            outline: 2px solid var(--builder-accent);
            border-radius: 0.5rem;
        }

        .builder-card {
            background: white;
            border: 1px solid var(--builder-border);
            border-radius: 0.5rem;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease;
        }

        .builder-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .form-group {
            margin-bottom: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .form-group label {
            font-weight: 700;
            font-size: 0.95rem;
            color: #2d3748;
        }

        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            padding: 0.65rem 0.85rem;
            border: 1.5px solid var(--builder-border);
            border-radius: 0.375rem;
            font-family: inherit;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-group textarea {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.88rem;
            line-height: 1.45;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--builder-primary);
            outline: 3px solid rgba(49, 151, 149, 0.25);
        }

        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .btn:focus-visible {
            outline: 3px solid #2b6cb0;
            outline-offset: 2px;
        }

        .btn-primary { background: var(--builder-primary); color: white; }
        .btn-primary:hover { background: var(--builder-primary-hover); }

        .btn-secondary { background: #4a5568; color: white; }
        .btn-secondary:hover { background: #2d3748; }

        .btn-accent { background: #3182ce; color: white; }
        .btn-accent:hover { background: #2b6cb0; }

        .btn-outline { background: white; border: 1.5px solid var(--builder-border); color: #2d3748; }
        .btn-outline:hover { background: #edf2f7; border-color: #cbd5e1; }

        .btn-help {
            background: #ebf8ff;
            border: 1.5px solid #3182ce;
            color: #2b6cb0;
            padding: 0.25rem 0.65rem;
            font-size: 0.82rem;
            border-radius: 0.25rem;
            font-weight: 700;
        }
        .btn-help:hover {
            background: #3182ce;
            color: white;
        }

        .btn-danger { background: #e53e3e; color: white; }
        .btn-danger:hover { background: #c53030; }

        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.85rem; }

        .toolbar-sticky {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(8px);
            padding: 1rem 1.5rem;
            border: 1px solid var(--builder-border);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .module-card {
            border-left: 4px solid var(--builder-primary);
        }

        .module-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #edf2f7;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .module-badge {
            background: #e6fffa;
            color: #234e52;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            border: 1px solid #b2f5ea;
        }

        .prompt-box {
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        /* Modal Dialog Styles */
        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.75);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .preview-modal.active {
            display: flex;
        }

        .preview-content {
            background: white;
            width: 100%;
            max-width: 1200px;
            height: 90vh;
            border-radius: 0.75rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .preview-modal-header {
            background: #1a202c;
            color: white;
            padding: 0.85rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .preview-iframe {
            flex: 1;
            width: 100%;
            height: 100%;
            border: none;
        }

        .status-toast {
            display: none;
            padding: 0.85rem 1.25rem;
            border-radius: 0.375rem;
            margin-bottom: 1.25rem;
            font-weight: 700;
        }

        .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto !important;
            }
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
    <a href="#builder-main" class="skip-link">Skip to main content</a>

    <header class="builder-header">
        <div class="container mx-auto px-4 flex justify-between items-center max-w-6xl">
            <div>
                <h1 class="m-0 text-3xl font-bold" style="color: white;">
                    <i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i> Modular Course Builder & Packager
                </h1>
                <p class="m-0 mt-2 text-sm" style="color: #cbd5e0;">
                    Build courses block-by-block, test & preview modules live, and export ready-to-upload LMS ZIP packages.
                </p>
            </div>
            <div class="flex gap-4 items-center">
                <a href="<?= $urlPrefix ?>help.php" class="text-white font-bold text-sm">← Help Center</a>
                <a href="<?= $urlPrefix ?>admin.php" class="text-white font-bold text-sm" style="padding: 0.4rem 0.8rem; border: 2px solid white; border-radius: 0.25rem;">Admin Panel</a>
            </div>
        </div>
    </header>

    <main id="builder-main" class="container mx-auto builder-container">
        
        <!-- WCAG 2.2 AA Compliant Tablist Pattern (4.1.2 Name, Role, Value) -->
        <div role="tablist" aria-label="Course Builder Modes" class="tab-bar">
            <button type="button" 
                    role="tab" 
                    id="tab-btn-modular-builder" 
                    aria-selected="true" 
                    aria-controls="modular-builder-tab" 
                    tabindex="0" 
                    class="tab-btn active"
                    onclick="switchTab('modular-builder-tab')">
                <i class="fa-solid fa-puzzle-piece" aria-hidden="true"></i> Modular Builder (Block-by-Block)
            </button>
            <button type="button" 
                    role="tab" 
                    id="tab-btn-monolithic-json" 
                    aria-selected="false" 
                    aria-controls="monolithic-json-tab" 
                    tabindex="-1" 
                    class="tab-btn"
                    onclick="switchTab('monolithic-json-tab')">
                <i class="fa-solid fa-file-code" aria-hidden="true"></i> Direct Full JSON Paste / Upload
            </button>
            <button type="button" 
                    role="tab" 
                    id="tab-btn-ai-prompts" 
                    aria-selected="false" 
                    aria-controls="ai-prompts-tab" 
                    tabindex="-1" 
                    class="tab-btn"
                    onclick="switchTab('ai-prompts-tab')">
                <i class="fa-solid fa-robot" aria-hidden="true"></i> AI Modular Prompts Helper
            </button>
        </div>

        <!-- WCAG 4.1.3 Accessible Screen Reader Status Announcements -->
        <div id="status-box" role="status" aria-live="polite" class="status-toast"></div>

        <!-- ================= TAB 1: MODULAR BUILDER ================= -->
        <div id="modular-builder-tab" 
             role="tabpanel" 
             aria-labelledby="tab-btn-modular-builder" 
             tabindex="0" 
             class="tab-panel active">
            
            <!-- Sticky Action Toolbar -->
            <div class="toolbar-sticky" id="course-controls-toolbar">
                <div>
                    <span class="font-bold text-lg mr-2"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Course Controls</span>
                    <span id="module-count-badge" class="module-badge">0 Modules</span>
                </div>
                <div class="btn-group">
                    <button type="button" id="btn-save-draft" class="btn btn-outline btn-sm" onclick="saveDraftToLocalStorage()">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Draft
                    </button>
                    <button type="button" class="btn btn-accent btn-sm" onclick="previewFullCourse(this)">
                        <i class="fa-solid fa-play" aria-hidden="true"></i> Full Course Live Preview
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="exportDraftJSON()">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> Export Draft JSON
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="triggerImportJSON()">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Import Draft JSON
                    </button>
                    <input type="file" id="import-json-file" accept=".json" style="display: none;" onchange="importDraftJSON(event)">
                    
                    <button type="button" class="btn btn-primary" onclick="generateZipFromModularBuilder()">
                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Generate & Download Course ZIP
                    </button>
                </div>
            </div>

            <!-- Course Metadata Card -->
            <section class="builder-card" aria-labelledby="meta-heading">
                <div class="flex justify-between items-center mb-4">
                    <h2 id="meta-heading" class="m-0 text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-teal-600" aria-hidden="true"></i> Course Metadata & Styles
                    </h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="course-title">Course Title <span style="color:red;">*</span></label>
                        <input type="text" id="course-title" placeholder="e.g. Accessible Web Design 101" required>
                    </div>

                    <div class="form-group">
                        <div class="flex justify-between items-center mb-1">
                            <label for="course-access" class="m-0">Access Control</label>
                            <button type="button" class="btn btn-help btn-sm" onclick="openHelpModal('access', this)" aria-label="Help on Course Access Control modes">
                                <i class="fa-solid fa-circle-question" aria-hidden="true"></i> Access Help
                            </button>
                        </div>
                        <select id="course-access">
                            <option value="public">Public (Open to all visitors)</option>
                            <option value="protected">Protected (Requires User Login)</option>
                            <option value="teaser">Teaser Only (Displays Teaser Link)</option>
                            <option value="hidden">Hidden from Dashboard</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="course-description">Course Description</label>
                    <textarea id="course-description" rows="2" placeholder="Brief summary of what learners will gain from this course..."></textarea>
                </div>

                <details class="mt-4">
                    <summary class="font-bold cursor-pointer text-teal-700 hover:underline inline-flex items-center gap-2">
                        <i class="fa-solid fa-code" aria-hidden="true"></i> Advanced Custom Styles & Scripts (CSS / JS)
                    </summary>
                    <div class="mt-4">
                        <div class="flex justify-between items-center mb-3 p-3 bg-teal-50 border border-teal-200 rounded">
                            <span class="text-xs text-teal-900 font-bold">
                                <i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Style rules apply to all module pages inside the course container.
                            </span>
                            <button type="button" class="btn btn-help btn-sm" onclick="openHelpModal('css-js', this)" aria-label="Help on Custom CSS and JavaScript styling">
                                <i class="fa-solid fa-circle-question" aria-hidden="true"></i> Custom CSS / JS Help
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label for="course-css">Custom CSS (css/style.css)</label>
                                <textarea id="course-css" rows="6" placeholder="/* Custom CSS rules applied to all modules */"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="course-js">Custom JS (js/main.js)</label>
                                <textarea id="course-js" rows="6" placeholder="// Custom JavaScript logic"></textarea>
                            </div>
                        </div>
                    </div>
                </details>
            </section>

            <!-- Modules Container -->
            <section aria-labelledby="modules-heading">
                <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                        <h2 id="modules-heading" class="m-0 text-xl font-bold">
                            <i class="fa-solid fa-list-check" aria-hidden="true"></i> Course Modules Pages
                        </h2>
                        <button type="button" class="btn btn-help btn-sm" onclick="openHelpModal('modules', this)" aria-label="Help on Module Standards and Interactive Components">
                            <i class="fa-solid fa-circle-question" aria-hidden="true"></i> Module Standards Help
                        </button>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addModuleCard('', '', true)">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add New Module
                    </button>
                </div>

                <div id="modules-container">
                    <!-- Dynamic Module Cards will be inserted here -->
                </div>

                <div class="text-center py-6 flex justify-center items-center gap-4 flex-wrap">
                    <button type="button" class="btn btn-primary" onclick="addModuleCard('', '', true)">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Another Module
                    </button>
                    <button type="button" class="btn btn-outline" onclick="scrollToCourseControls()" aria-label="Return to Course Controls toolbar at top of page">
                        <i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Return to Course Controls
                    </button>
                </div>
            </section>
        </div>

        <!-- ================= TAB 2: DIRECT MONOLITHIC JSON ================= -->
        <div id="monolithic-json-tab" 
             role="tabpanel" 
             aria-labelledby="tab-btn-monolithic-json" 
             tabindex="-1" 
             class="tab-panel" 
             hidden>
            <section class="builder-card">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="m-0 text-2xl font-bold">Paste Single Course JSON or Upload File</h2>
                    <button type="button" class="btn btn-help btn-sm" onclick="openHelpModal('json-schema', this)" aria-label="Help on Full JSON Manifest Schema">
                        <i class="fa-solid fa-circle-question" aria-hidden="true"></i> JSON Schema Help
                    </button>
                </div>
                <p class="text-gray-600 mb-6">If you already have a single <code>course_data.json</code> file or full JSON payload, paste it below or upload the file directly.</p>

                <div class="form-group mb-4">
                    <label for="raw-json-input">Raw JSON Payload</label>
                    <textarea id="raw-json-input" rows="12" placeholder='{
  "properties": {
    "title": "My Accessible Course",
    "description": "Course description...",
    "access": { "type": "public" }
  },
  "modules": [
    {
      "id": "welcome",
      "title": "Welcome",
      "filename": "modules/welcome.html",
      "html_content": "<section><h1>Welcome</h1><p>Content...</p></section>"
    }
  ]
}'></textarea>
                </div>

                <div class="form-group mb-6">
                    <label for="raw-json-file">Upload JSON File</label>
                    <input type="file" id="raw-json-file" accept=".json">
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-primary" onclick="generateZipFromRawJSON()">
                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Generate & Download Course ZIP
                    </button>
                    <button type="button" class="btn btn-accent" onclick="loadRawJSONIntoBuilder()">
                        <i class="fa-solid fa-right-left" aria-hidden="true"></i> Load JSON into Modular Builder Cards
                    </button>
                </div>
            </section>
        </div>

        <!-- ================= TAB 3: AI MODULAR PROMPTS HELPER ================= -->
        <div id="ai-prompts-tab" 
             role="tabpanel" 
             aria-labelledby="tab-btn-ai-prompts" 
             tabindex="-1" 
             class="tab-panel" 
             hidden>
            <section class="prompt-box">
                <h2 class="m-0 text-xl font-bold text-blue-900 mb-2">
                    <i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Why Use the Modular Chunking Workflow?
                </h2>
                <p class="text-sm text-blue-800 m-0">
                    Free AI web interfaces (like ChatGPT, Gemini, or Claude free tiers) have output limits. Requesting a full 10-module course in a single prompt often leads to truncation or truncated HTML. By using these chunked prompts, you can generate 1 module at a time with 100% fidelity, rich interactive components, and WCAG accessibility!
                </p>
            </section>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Step A Prompt -->
                <div class="builder-card">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="m-0 font-bold text-lg text-teal-800">Step A: Course Metadata & Styles Prompt</h3>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyPrompt('prompt-meta-text', this, 'Step A Metadata Prompt')">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i> Copy Prompt
                        </button>
                    </div>
                    <textarea id="prompt-meta-text" readonly rows="8" class="w-full text-xs font-mono p-2 bg-gray-50 border rounded" aria-label="Step A Metadata Prompt Text">Please generate the metadata for a new e-learning course on [INSERT TOPIC HERE].
Output a valid JSON object with:
1. `title`: Concise title
2. `description`: Brief description
3. `css_content`: Custom CSS styles for accents, cards, and buttons
4. `js_content`: Custom JavaScript logic if needed.
Return ONLY valid JSON.</textarea>
                </div>

                <!-- Step B Prompt -->
                <div class="builder-card">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="m-0 font-bold text-lg text-teal-800">Step B: Module HTML Fragment Prompt</h3>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyPrompt('prompt-module-text', this, 'Step B Module Prompt')">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i> Copy Prompt
                        </button>
                    </div>
                    <textarea id="prompt-module-text" readonly rows="8" class="w-full text-xs font-mono p-2 bg-gray-50 border rounded" aria-label="Step B Module Prompt Text">Generate an accessible WCAG 2.2 AA HTML module fragment for:
Module Title: [INSERT MODULE TITLE]
Topic & Key Points: [INSERT TOPIC/CONCEPTS]

Requirements:
- Start with a single <h1> heading.
- Do NOT include <html>, <head>, or <body> tags. Output only <section> or <article> HTML.
- Include interactive JW components such as <jw-accordion>, <jw-tabs>, or <jw-flipcard> if relevant.
- Provide descriptive text and accessible semantic markup.
Return the clean HTML fragment.</textarea>
                </div>
            </div>
        </div>

    </main>

    <?= renderTenantFooter() ?>

    <!-- ================= ACCESSIBLE HELP MODAL DIALOG ================= -->
    <div id="help-modal" 
         class="preview-modal" 
         role="dialog" 
         aria-modal="true" 
         aria-labelledby="help-modal-title" 
         aria-describedby="help-modal-body">
        <div class="preview-content" style="max-width: 800px; height: auto; max-height: 85vh; overflow-y: auto;">
            <div class="preview-modal-header">
                <div>
                    <h3 id="help-modal-title" class="m-0 font-bold text-lg text-white">Help Guide</h3>
                    <p class="m-0 text-xs text-gray-300">Superable Learning Builder Documentation</p>
                </div>
                <button type="button" id="close-help-modal-btn" class="btn btn-danger btn-sm" onclick="closeHelpModal()" aria-label="Close help dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i> Close
                </button>
            </div>
            <div id="help-modal-body" class="p-6 text-gray-800 leading-relaxed text-sm">
                <!-- Dynamic Help Content Inserted Here -->
            </div>
        </div>
    </div>

    <!-- ================= LIVE PREVIEW MODAL ================= -->
    <div id="preview-modal" 
         class="preview-modal" 
         role="dialog" 
         aria-modal="true" 
         aria-labelledby="preview-title">
        <div class="preview-content">
            <div class="preview-modal-header">
                <div>
                    <h3 id="preview-title" class="m-0 font-bold text-lg text-white">Module Live Preview</h3>
                    <p id="preview-subtitle" class="m-0 text-xs text-gray-300">Rendering live component sandbox</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-outline btn-sm" onclick="openPreviewInNewTab()" style="background: white;" aria-label="Open preview in new browser tab">
                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> Open in New Tab
                    </button>
                    <button type="button" id="close-modal-btn" class="btn btn-danger btn-sm" onclick="closePreviewModal()" aria-label="Close preview modal">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i> Close Preview
                    </button>
                </div>
            </div>
            <iframe id="preview-iframe" class="preview-iframe" title="Live Module Preview Sandbox"></iframe>
        </div>
    </div>

    <!-- JavaScript Logic -->
    <script>
        let moduleCounter = 0;
        let currentPreviewBlobUrl = null;
        let lastFocusedElement = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize ARIA Tab Keyboard Navigation (ArrowLeft / ArrowRight / Home / End)
            const tabList = document.querySelector('[role="tablist"]');
            if (tabList) {
                const tabs = Array.from(tabList.querySelectorAll('[role="tab"]'));
                
                tabList.addEventListener('keydown', function(e) {
                    const currentTab = document.activeElement;
                    const currentIndex = tabs.indexOf(currentTab);

                    if (currentIndex === -1) return;

                    let newIndex = currentIndex;

                    if (e.key === 'ArrowRight') {
                        newIndex = (currentIndex + 1) % tabs.length;
                        e.preventDefault();
                    } else if (e.key === 'ArrowLeft') {
                        newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                        e.preventDefault();
                    } else if (e.key === 'Home') {
                        newIndex = 0;
                        e.preventDefault();
                    } else if (e.key === 'End') {
                        newIndex = tabs.length - 1;
                        e.preventDefault();
                    }

                    if (newIndex !== currentIndex) {
                        const targetTab = tabs[newIndex];
                        const targetPanelId = targetTab.getAttribute('aria-controls');
                        switchTab(targetPanelId, true);
                    }
                });
            }

            // Universal Modal Focus Trap & Keyboard Escape Handler (WCAG 2.1.2 & 2.4.3)
            document.addEventListener('keydown', function(e) {
                const helpModal = document.getElementById('help-modal');
                const previewModal = document.getElementById('preview-modal');
                
                const activeModal = helpModal.classList.contains('active') ? helpModal :
                                    (previewModal.classList.contains('active') ? previewModal : null);

                if (!activeModal) return;

                if (e.key === 'Escape') {
                    if (activeModal === helpModal) closeHelpModal();
                    if (activeModal === previewModal) closePreviewModal();
                    e.preventDefault();
                    return;
                }

                if (e.key === 'Tab') {
                    const focusableElements = Array.from(activeModal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'));
                    if (focusableElements.length === 0) return;

                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];

                    if (e.shiftKey) { // Shift + Tab
                        if (document.activeElement === firstElement) {
                            lastElement.focus();
                            e.preventDefault();
                        }
                    } else { // Tab
                        if (document.activeElement === lastElement) {
                            firstElement.focus();
                            e.preventDefault();
                        }
                    }
                }
            });

            // Initialize with Module #1 (starter content) and Module #2 (empty for pasting) if no draft exists
            if (!loadDraftFromLocalStorage()) {
                addModuleCard('Welcome & Overview', '<section class="content-area">\n  <h1>Welcome & Overview</h1>\n  <p>Welcome to this course! Explore the interactive modules in the builder.</p>\n  <jw-accordion>\n    <jw-accordion-item title="Course Prerequisites">\n      <p>No prior background required.</p>\n    </jw-accordion-item>\n  </jw-accordion>\n</section>');
                addModuleCard('', ''); // Module 2 starts completely clean and empty
            }

            // Raw JSON File listener
            const rawFileInput = document.getElementById('raw-json-file');
            if (rawFileInput) {
                rawFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            document.getElementById('raw-json-input').value = evt.target.result;
                        };
                        reader.readAsText(file);
                    }
                });
            }

            // Attach Escape key listener to preview iframe sandbox on load (WCAG 2.1.2 & 2.4.3)
            const previewIframe = document.getElementById('preview-iframe');
            if (previewIframe) {
                previewIframe.addEventListener('load', function() {
                    try {
                        const iframeDoc = previewIframe.contentDocument || previewIframe.contentWindow.document;
                        if (iframeDoc) {
                            iframeDoc.addEventListener('keydown', function(e) {
                                if (e.key === 'Escape') {
                                    closePreviewModal();
                                    e.preventDefault();
                                }
                            });
                        }
                    } catch(err) {}
                });
            }
        });

        // WCAG 4.1.2 & 4.1.3 Compliant Tab Switching Function
        function switchTab(targetTabId, setFocus = false) {
            const tabButtons = document.querySelectorAll('[role="tab"]');
            const tabPanels = document.querySelectorAll('[role="tabpanel"]');
            let activeTabButton = null;

            tabButtons.forEach(btn => {
                const controlsId = btn.getAttribute('aria-controls');
                if (controlsId === targetTabId) {
                    btn.setAttribute('aria-selected', 'true');
                    btn.setAttribute('tabindex', '0');
                    btn.classList.add('active');
                    activeTabButton = btn;
                } else {
                    btn.setAttribute('aria-selected', 'false');
                    btn.setAttribute('tabindex', '-1');
                    btn.classList.remove('active');
                }
            });

            tabPanels.forEach(panel => {
                if (panel.id === targetTabId) {
                    panel.classList.add('active');
                    panel.removeAttribute('hidden');
                    panel.setAttribute('tabindex', '0');
                } else {
                    panel.classList.remove('active');
                    panel.setAttribute('hidden', '');
                    panel.setAttribute('tabindex', '-1');
                }
            });

            if (setFocus && activeTabButton) {
                activeTabButton.focus();
            }

            if (activeTabButton) {
                const tabTitle = activeTabButton.textContent.trim();
                showStatus(`Switched view to ${tabTitle}.`, false);
            }
        }

        // Return to Course Controls Action with WCAG 2.3.3 Reduced Motion Support
        function scrollToCourseControls() {
            const saveBtn = document.getElementById('btn-save-draft');
            if (saveBtn) {
                saveBtn.focus();
            }
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
            showStatus('Returned focus to Course Controls toolbar.', false);
        }

        // Status Toast with WCAG 4.1.3 ARIA Live Region Announcement
        function showStatus(msg, isError, priority = 'polite') {
            const statusBox = document.getElementById('status-box');
            statusBox.setAttribute('aria-live', priority);
            
            // Clear text to force screen reader DOM mutation detection
            statusBox.textContent = '';
            
            setTimeout(() => {
                statusBox.style.display = 'block';
                statusBox.style.background = isError ? 'var(--color-critical-bg, #fff5f5)' : 'var(--color-success-bg, #f0fff4)';
                statusBox.style.border = isError ? '1px solid var(--color-critical-border, #feb2b2)' : '1px solid var(--color-success-border, #9ae6b4)';
                statusBox.style.color = isError ? 'var(--color-critical-text, #9b2c2c)' : 'var(--color-success-text, #22543d)';
                statusBox.innerHTML = (isError ? '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Error: ' : '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> ') + msg;
            }, 30);

            if (window.statusTimer) clearTimeout(window.statusTimer);
            window.statusTimer = setTimeout(() => {
                statusBox.style.display = 'none';
            }, 6000);
        }

        // Accessible Help Modal Dialog Engine
        function openHelpModal(topicKey, triggerBtn) {
            lastFocusedElement = triggerBtn || document.activeElement;
            const modal = document.getElementById('help-modal');
            const titleEl = document.getElementById('help-modal-title');
            const bodyEl = document.getElementById('help-modal-body');
            const closeBtn = document.getElementById('close-help-modal-btn');

            const topics = {
                'css-js': {
                    title: 'Custom CSS & JavaScript Authoring Guide',
                    html: `
                        <h4 class="text-base font-bold text-teal-800 mb-2">Custom CSS Rules (<code>css/style.css</code>)</h4>
                        <p class="mb-3">Styles defined here are packaged into your course ZIP and loaded whenever a learner views any module page in your course.</p>
                        <ul class="list-disc pl-5 mb-4 space-y-1">
                            <li><strong>Scope Your Styles:</strong> Avoid global resets (like <code>* { margin: 0; }</code>). Use custom class names (e.g. <code>.my-callout-card</code>) or target inside <code>.content-area</code>.</li>
                            <li><strong>Accessible Contrast:</strong> Ensure text colors meet minimum WCAG 4.5:1 contrast against their background colors.</li>
                            <li><strong>Focus Indicators:</strong> Do not remove <code>outline</code> on interactive elements without providing a high-visibility replacement.</li>
                        </ul>

                        <h4 class="text-base font-bold text-teal-800 mb-2">Custom JavaScript (<code>js/main.js</code>)</h4>
                        <p class="mb-3">Custom logic for course interactive elements, quizzes, or custom xAPI tracking events.</p>
                        <ul class="list-disc pl-5 mb-4 space-y-1">
                            <li><strong>Dynamic Page Loading (Event Delegation):</strong> Because the LMS player loads module HTML snippets dynamically, standard <code>window.onload</code> or <code>DOMContentLoaded</code> listeners won't trigger on page switches. Always use <strong>Event Delegation</strong> on the <code>document</code>:</li>
                        </ul>
                        <pre class="bg-gray-800 text-gray-100 p-3 rounded text-xs font-mono mb-3 overflow-x-auto"><code>document.addEventListener('click', function(e) {
    if (e.target.matches('.my-action-button')) {
        alert('Action triggered!');
    }
});</code></pre>
                        <ul class="list-disc pl-5 space-y-1">
                            <li><strong>Keyboard & ARIA Rules:</strong> Never attach click handlers to <code>&lt;div&gt;</code> or <code>&lt;a href="#"&gt;</code>. Always use native <code>&lt;button type="button"&gt;</code> elements.</li>
                        </ul>
                    `
                },
                'access': {
                    title: 'Course Access Control Modes',
                    html: `
                        <p class="mb-4">Superable Learning supports 4 distinct access control modes for published courses:</p>
                        <div class="space-y-3">
                            <div class="p-3 bg-gray-50 border rounded">
                                <strong class="text-teal-700 font-bold block mb-1">🌐 Public</strong>
                                <p class="m-0 text-xs">The course is fully open and accessible to all visitors and guest users. No login or registration required.</p>
                            </div>
                            <div class="p-3 bg-gray-50 border rounded">
                                <strong class="text-blue-700 font-bold block mb-1">🔒 Protected</strong>
                                <p class="m-0 text-xs">The course requires the learner to log in with an authorized user account or enter an active invitation key code.</p>
                            </div>
                            <div class="p-3 bg-gray-50 border rounded">
                                <strong class="text-amber-700 font-bold block mb-1">🏷️ Teaser Only</strong>
                                <p class="m-0 text-xs">The course displays a teaser card on the dashboard catalog with a custom <strong>Unlock Course / Info</strong> link pointing to your external registration page.</p>
                            </div>
                            <div class="p-3 bg-gray-50 border rounded">
                                <strong class="text-gray-700 font-bold block mb-1">👁️‍🗨️ Hidden</strong>
                                <p class="m-0 text-xs">The course is hidden from the main public dashboard catalog. It can only be launched via direct deep-link or LMS admin launcher.</p>
                            </div>
                        </div>
                    `
                },
                'modules': {
                    title: 'Module Standards & JW Interactive Components',
                    html: `
                        <h4 class="text-base font-bold text-teal-800 mb-2">HTML Module Fragment Rules</h4>
                        <ul class="list-disc pl-5 mb-4 space-y-1">
                            <li><strong>No Outer Tags:</strong> Module files must contain HTML content fragments only. Do NOT include <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code>, or <code>&lt;body&gt;</code> tags.</li>
                            <li><strong>Single Heading 1:</strong> Every module fragment MUST start with exactly one <code>&lt;h1&gt;</code> heading matching the module title for screen reader navigation.</li>
                        </ul>

                        <h4 class="text-base font-bold text-teal-800 mb-2">Full JW Interactive Web Component Suite</h4>
                        <p class="mb-3">The LMS player includes 12+ pre-styled, WCAG 2.2 AA compliant interactive components:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4 text-xs font-mono bg-gray-50 p-3 border rounded">
                            <div>• <code>&lt;jw-accordion&gt;</code></div>
                            <div>• <code>&lt;jw-tabs&gt;</code></div>
                            <div>• <code>&lt;jw-flip-card&gt;</code></div>
                            <div>• <code>&lt;jw-click-reveal&gt;</code></div>
                            <div>• <code>&lt;jw-modal&gt;</code></div>
                            <div>• <code>&lt;jw-scenario&gt;</code></div>
                            <div>• <code>&lt;jw-timeline&gt;</code></div>
                            <div>• <code>&lt;jw-wizard&gt;</code></div>
                            <div>• <code>&lt;jw-progress-bar&gt;</code></div>
                            <div>• <code>&lt;jw-multi-column&gt;</code></div>
                        </div>
                        <p class="mb-4 text-xs"><a href="help.php?doc=jw-components" target="_blank" class="text-teal-700 font-bold underline">📖 View Complete JW Components API & Syntax Reference Guide →</a></p>

                        <h4 class="text-base font-bold text-teal-800 mb-2">Declarative xAPI Analytics Tracking</h4>
                        <p class="mb-2">Add <code>data-xapi</code> attributes to any clickable element for automatic analytics tracking without writing custom JavaScript:</p>
                        <pre class="bg-gray-800 text-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>&lt;button type="button" 
        data-xapi-verb="PLAYED" 
        data-xapi-name="Intro Video" 
        data-xapi-desc="Learner started intro video."&gt;
  Play Video
&lt;/button&gt;</code></pre>
                    `
                },
                'json-schema': {
                    title: 'Full JSON Manifest Schema Guide',
                    html: `
                        <h4 class="text-base font-bold text-teal-800 mb-2">Structure of <code>course_structure.json</code></h4>
                        <p class="mb-3">When pasting a direct JSON payload or uploading a JSON file, the manifest schema requires:</p>
                        <pre class="bg-gray-800 text-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>{
  "properties": {
    "title": "Accessible Web Design 101",
    "description": "Master WCAG 2.2 AA compliant web interfaces.",
    "thumbnail": "images/thumb.svg",
    "access": { "type": "public" },
    "assets": {
      "css": ["css/style.css"],
      "js": ["js/main.js"]
    }
  },
  "modules": [
    {
      "id": "welcome",
      "title": "Welcome & Overview",
      "src": "modules/welcome.html",
      "html_content": "&lt;section&gt;&lt;h1&gt;Welcome&lt;/h1&gt;&lt;p&gt;Content...&lt;/p&gt;&lt;/section&gt;"
    }
  ]
}</code></pre>
                    `
                }
            };

            const topic = topics[topicKey] || topics['modules'];
            titleEl.textContent = topic.title;
            bodyEl.innerHTML = topic.html;

            modal.classList.add('active');
            closeBtn.focus();
            showStatus(`Opened ${topic.title} help dialog.`, false);
        }

        function closeHelpModal() {
            const modal = document.getElementById('help-modal');
            modal.classList.remove('active');
            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
            showStatus('Closed help dialog.', false);
        }

        // Add Module Card with Forward Keyboard Focus Flow
        function addModuleCard(initialTitle = '', initialContent = '', setFocus = false) {
            moduleCounter++;
            const moduleId = 'mod-' + moduleCounter;
            const container = document.getElementById('modules-container');

            const cardHtml = `
                <div class="builder-card module-card" id="${moduleId}">
                    <div class="module-card-header">
                        <div class="flex items-center gap-2">
                            <span class="module-number-badge font-bold text-gray-700">Module #${moduleCounter}</span>
                            <span class="module-badge">HTML Page</span>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline btn-sm" onclick="moveModuleUp('${moduleId}')" aria-label="Move Module up">
                                <i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Move Up
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="moveModuleDown('${moduleId}')" aria-label="Move Module down">
                                <i class="fa-solid fa-arrow-down" aria-hidden="true"></i> Move Down
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="duplicateModule('${moduleId}')" aria-label="Duplicate Module">
                                <i class="fa-solid fa-copy" aria-hidden="true"></i> Duplicate
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeModuleCard('${moduleId}')" aria-label="Remove Module">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="form-group">
                            <label for="${moduleId}-title">Module Title</label>
                            <input type="text" id="${moduleId}-title" class="module-title-input" value="${escapeHtml(initialTitle)}" placeholder="e.g. Module Title" oninput="updateModuleBadges()">
                        </div>
                        <div class="form-group">
                            <label for="${moduleId}-file">Filename / Target Path</label>
                            <input type="text" id="${moduleId}-file" class="module-file-input" value="modules/module${moduleCounter}.html" placeholder="modules/module1.html">
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <label for="${moduleId}-content">
                            Module Content (HTML Fragment or Module JSON)
                        </label>
                        <textarea id="${moduleId}-content" class="module-content-input" rows="8" placeholder="Paste HTML fragment (<section><h1>Title</h1><p>...</p></section>) or JSON block...">${escapeHtml(initialContent)}</textarea>
                    </div>

                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <button type="button" class="btn btn-accent" onclick="previewSingleModule('${moduleId}', this)" aria-label="Preview Module in sandbox">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i> Preview Module
                        </button>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', cardHtml);
            updateModuleBadges();

            if (setFocus) {
                const titleInput = document.getElementById(`${moduleId}-title`);
                if (titleInput) {
                    titleInput.focus();
                }
            }
        }

        // Remove Module Card
        function removeModuleCard(moduleId) {
            const card = document.getElementById(moduleId);
            if (card) {
                if (document.querySelectorAll('.module-card').length <= 1) {
                    showStatus('Courses must contain at least one module.', true, 'assertive');
                    return;
                }
                card.remove();
                updateModuleBadges();
                showStatus('Module removed.', false, 'assertive');
            }
        }

        // Duplicate Module Card
        function duplicateModule(moduleId) {
            const title = document.getElementById(moduleId + '-title').value;
            const content = document.getElementById(moduleId + '-content').value;
            addModuleCard(title + ' (Copy)', content, true);
        }

        // Move Module Up / Down with Assertive Position Status Announcement
        function moveModuleUp(moduleId) {
            const card = document.getElementById(moduleId);
            const cards = Array.from(document.querySelectorAll('.module-card'));
            const total = cards.length;
            const currentIndex = cards.indexOf(card);
            const titleInput = document.getElementById(moduleId + '-title');
            const titleStr = (titleInput && titleInput.value.trim()) ? `"${titleInput.value.trim()}"` : `Module #${currentIndex + 1}`;

            if (currentIndex > 0) {
                card.parentNode.insertBefore(card, card.previousElementSibling);
                updateModuleBadges();
                const newPos = currentIndex; // 1-based index after moving up
                showStatus(`Moved ${titleStr} up to position ${newPos} of ${total}.`, false, 'assertive');
            } else {
                showStatus(`${titleStr} is already at the top (position 1 of ${total}).`, false, 'assertive');
            }
        }

        function moveModuleDown(moduleId) {
            const card = document.getElementById(moduleId);
            const cards = Array.from(document.querySelectorAll('.module-card'));
            const total = cards.length;
            const currentIndex = cards.indexOf(card);
            const titleInput = document.getElementById(moduleId + '-title');
            const titleStr = (titleInput && titleInput.value.trim()) ? `"${titleInput.value.trim()}"` : `Module #${currentIndex + 1}`;

            if (currentIndex < total - 1) {
                card.parentNode.insertBefore(card.nextElementSibling, card);
                updateModuleBadges();
                const newPos = currentIndex + 2; // 1-based index after moving down
                showStatus(`Moved ${titleStr} down to position ${newPos} of ${total}.`, false, 'assertive');
            } else {
                showStatus(`${titleStr} is already at the bottom (position ${total} of ${total}).`, false, 'assertive');
            }
        }

        // Update & Dynamic Re-Index All Module Badges, Labels, and ARIA Attributes
        function updateModuleBadges() {
            const cards = document.querySelectorAll('.module-card');
            cards.forEach((card, index) => {
                const pos = index + 1;
                const total = cards.length;

                // Visual Badge
                const badge = card.querySelector('.module-number-badge');
                if (badge) badge.textContent = `Module #${pos}`;

                // Header Action Buttons
                const btnUp = card.querySelector('button[onclick*="moveModuleUp"]');
                if (btnUp) btnUp.setAttribute('aria-label', `Move Module #${pos} up`);

                const btnDown = card.querySelector('button[onclick*="moveModuleDown"]');
                if (btnDown) btnDown.setAttribute('aria-label', `Move Module #${pos} down`);

                const btnDup = card.querySelector('button[onclick*="duplicateModule"]');
                if (btnDup) btnDup.setAttribute('aria-label', `Duplicate Module #${pos}`);

                const btnDel = card.querySelector('button[onclick*="removeModuleCard"]');
                if (btnDel) btnDel.setAttribute('aria-label', `Remove Module #${pos}`);

                // Footer Preview Button
                const btnPrev = card.querySelector('button[onclick*="previewSingleModule"]');
                if (btnPrev) {
                    btnPrev.setAttribute('aria-label', `Preview Module #${pos} in sandbox`);
                    btnPrev.innerHTML = `<i class="fa-solid fa-eye" aria-hidden="true"></i> Preview Module #${pos}`;
                }
            });

            const countBadge = document.getElementById('module-count-badge');
            if (countBadge) {
                countBadge.textContent = `${cards.length} Module${cards.length === 1 ? '' : 's'}`;
            }
        }

        // Escape HTML helper
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Gather All Builder Data
        function gatherBuilderData() {
            const title = document.getElementById('course-title').value.trim();
            const description = document.getElementById('course-description').value.trim();
            const access = document.getElementById('course-access').value;
            const css = document.getElementById('course-css').value;
            const js = document.getElementById('course-js').value;

            const modules = [];
            document.querySelectorAll('.module-card').forEach((card, idx) => {
                const id = card.id;
                const mTitle = document.getElementById(id + '-title').value.trim() || `Module ${idx + 1}`;
                const mFile = document.getElementById(id + '-file').value.trim() || `modules/module${idx + 1}.html`;
                let rawContent = document.getElementById(id + '-content').value.trim();

                // If user pasted a JSON block for the module, parse it
                let htmlContent = rawContent;
                if (rawContent.startsWith('{') && rawContent.endsWith('}')) {
                    try {
                        const parsed = JSON.parse(rawContent);
                        if (parsed.html_content) htmlContent = parsed.html_content;
                    } catch (e) {
                        // Fallback to raw string if not valid JSON
                    }
                }

                modules.push({
                    id: mFile.replace(/[^a-z0-9]/gi, '-').toLowerCase(),
                    title: mTitle,
                    filename: mFile,
                    html_content: htmlContent
                });
            });

            return {
                title,
                description,
                access,
                css,
                js,
                modules
            };
        }

        // Local Storage Draft Saving
        function saveDraftToLocalStorage() {
            const data = gatherBuilderData();
            localStorage.setItem('superable_builder_draft', JSON.stringify(data));
            showStatus('Your course draft has been saved to browser storage.', false, 'assertive');
        }

        function loadDraftFromLocalStorage() {
            const saved = localStorage.getItem('superable_builder_draft');
            if (!saved) return false;
            try {
                const data = JSON.parse(saved);
                populateBuilderData(data);
                return true;
            } catch (e) {
                return false;
            }
        }

        function exportDraftJSON() {
            const data = gatherBuilderData();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            saveAs(blob, (data.title || 'course-draft').toLowerCase().replace(/[^a-z0-9]/g, '-') + '-builder.json');
            showStatus('Exported builder draft JSON file.', false);
        }

        function triggerImportJSON() {
            document.getElementById('import-json-file').click();
        }

        function importDraftJSON(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const data = JSON.parse(e.target.result);
                        populateBuilderData(data);
                        showStatus('Imported draft JSON successfully!', false);
                    } catch (err) {
                        showStatus('Invalid JSON file: ' + err.message, true);
                    }
                };
                reader.readAsText(file);
            }
        }

        function populateBuilderData(data) {
            if (data.title) document.getElementById('course-title').value = data.title;
            if (data.description) document.getElementById('course-description').value = data.description;
            if (data.access) document.getElementById('course-access').value = data.access;
            if (data.css) document.getElementById('course-css').value = data.css;
            if (data.js) document.getElementById('course-js').value = data.js;

            const container = document.getElementById('modules-container');
            container.innerHTML = '';
            moduleCounter = 0;

            if (Array.isArray(data.modules)) {
                data.modules.forEach(m => {
                    addModuleCard(m.title || '', m.html_content || m.content || '');
                });
            }
            updateModuleBadges();
        }

        // ================= PREVIEW ENGINE =================
        function getAbsoluteAssetUrls() {
            const pageUrl = window.location.href;
            const baseUrl = pageUrl.substring(0, pageUrl.lastIndexOf('/') + 1);
            const jwComponentsUrl = new URL('<?= $urlPrefix ?>assets/components/jw-components.js', baseUrl).href;
            const styleCssUrl = new URL('<?= $urlPrefix ?>style.css', baseUrl).href;
            return { jwComponentsUrl, styleCssUrl };
        }

        function generateModulePreviewDocument(moduleHtml, customCss, customJs) {
            const { jwComponentsUrl, styleCssUrl } = getAbsoluteAssetUrls();

            return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Sandbox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="${styleCssUrl}">
    <script src="${jwComponentsUrl}"><\/script>
    <style>
        body { padding: 2rem; background: #ffffff; font-family: 'Atkinson Hyperlegible', sans-serif; }
        .content-area { max-width: 900px; margin: 0 auto; }
        jw-flip-card, jw-flipcard { display: block; margin: 1rem 0; }
        ${customCss || ''}
    </style>
</head>
<body>
    <main class="content-area">
        ${moduleHtml || '<p>No content in this module.</p>'}
    </main>
    <script>
        // Universal Escape Key Relay to Parent Window (WCAG 2.1.2 & 2.4.3)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (window.parent && typeof window.parent.closePreviewModal === 'function') {
                    window.parent.closePreviewModal();
                    e.preventDefault();
                }
            }
        });
        ${customJs || ''}
    <\/script>
</body>
</html>`;
        }

        function previewSingleModule(moduleId, triggerBtn) {
            lastFocusedElement = triggerBtn || document.activeElement;

            const title = document.getElementById(moduleId + '-title').value || 'Module Preview';
            const rawContent = document.getElementById(moduleId + '-content').value;
            const customCss = document.getElementById('course-css').value;
            const customJs = document.getElementById('course-js').value;

            let htmlContent = rawContent;
            if (rawContent.trim().startsWith('{') && rawContent.trim().endsWith('}')) {
                try {
                    const parsed = JSON.parse(rawContent);
                    if (parsed.html_content) htmlContent = parsed.html_content;
                } catch(e) {}
            }

            const docStr = generateModulePreviewDocument(htmlContent, customCss, customJs);
            
            if (currentPreviewBlobUrl) URL.revokeObjectURL(currentPreviewBlobUrl);
            const blob = new Blob([docStr], { type: 'text/html' });
            currentPreviewBlobUrl = URL.createObjectURL(blob);

            document.getElementById('preview-title').textContent = title;
            document.getElementById('preview-subtitle').textContent = 'Live Module Component Preview';
            document.getElementById('preview-iframe').src = currentPreviewBlobUrl;
            
            const modal = document.getElementById('preview-modal');
            modal.classList.add('active');
            document.getElementById('close-modal-btn').focus();
        }

        function previewFullCourse(triggerBtn) {
            lastFocusedElement = triggerBtn || document.activeElement;

            const data = gatherBuilderData();
            if (!data.modules || data.modules.length === 0) {
                showStatus('Add at least one module to preview the full course.', true, 'assertive');
                return;
            }

            const { jwComponentsUrl, styleCssUrl } = getAbsoluteAssetUrls();

            // Encode course payload safely using Base64 to prevent unescaped newline JS syntax errors in template literal
            const jsonStr = JSON.stringify(data);
            const encodedData = btoa(encodeURIComponent(jsonStr));

            const fullPlayerHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>${escapeHtml(data.title || 'Course Player Preview')} - Live Sandbox</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="${styleCssUrl}">
    <script src="${jwComponentsUrl}"><\/script>
    <style>
        body { font-family: 'Atkinson Hyperlegible', sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .player-wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: white; border-right: 1px solid #e2e8f0; padding: 1.5rem; }
        .main-content { flex: 1; padding: 2.5rem; max-width: 900px; margin: 0 auto; }
        .nav-item { width: 100%; text-align: left; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; margin-bottom: 0.5rem; border-radius: 0.375rem; background: white; cursor: pointer; font-weight: 600; font-family: inherit; }
        .nav-item.active { background: #319795; color: white; border-color: #319795; }
        jw-flip-card, jw-flipcard { display: block; margin: 1rem 0; }
        ${data.css || ''}
    </style>
</head>
<body>
    <div class="player-wrapper">
        <aside class="sidebar">
            <h2 style="font-size: 1.1rem; margin-bottom: 1rem;">${escapeHtml(data.title || 'Course Preview')}</h2>
            <nav id="module-nav" aria-label="Course Module Navigation"></nav>
        </aside>
        <main class="main-content" id="module-viewport"></main>
    </div>
    <script>
        const rawPayload = decodeURIComponent(atob("${encodedData}"));
        const courseData = JSON.parse(rawPayload);
        const modules = courseData.modules || [];
        let activeIdx = 0;

        function renderNav() {
            const nav = document.getElementById('module-nav');
            if (!nav) return;
            nav.innerHTML = '';
            modules.forEach((m, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'nav-item' + (idx === activeIdx ? ' active' : '');
                btn.setAttribute('aria-current', idx === activeIdx ? 'page' : 'false');
                btn.textContent = (idx + 1) + '. ' + m.title;
                btn.onclick = () => loadMod(idx);
                nav.appendChild(btn);
            });
        }

        function loadMod(idx) {
            if (!modules[idx]) return;
            activeIdx = idx;
            renderNav();
            const viewport = document.getElementById('module-viewport');
            if (viewport) {
                viewport.innerHTML = modules[idx].html_content || '<p>Empty module.</p>';
                
                // Re-execute script elements embedded inside module HTML fragment (HTML5 innerHTML script execution fix)
                const scripts = Array.from(viewport.querySelectorAll('script'));
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }
        }

        renderNav();
        loadMod(0);

        // Universal Escape Key Relay to Parent Window (WCAG 2.1.2 & 2.4.3)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (window.parent && typeof window.parent.closePreviewModal === 'function') {
                    window.parent.closePreviewModal();
                    e.preventDefault();
                }
            }
        });
        ${data.js || ''}
    <\/script>
</body>
</html>`;

            if (currentPreviewBlobUrl) URL.revokeObjectURL(currentPreviewBlobUrl);
            const blob = new Blob([fullPlayerHtml], { type: 'text/html' });
            currentPreviewBlobUrl = URL.createObjectURL(blob);

            document.getElementById('preview-title').textContent = data.title || 'Full Course Live Preview';
            document.getElementById('preview-subtitle').textContent = 'Simulated LMS Course Navigation Player';
            document.getElementById('preview-iframe').src = currentPreviewBlobUrl;
            
            const modal = document.getElementById('preview-modal');
            modal.classList.add('active');
            document.getElementById('close-modal-btn').focus();
        }

        function closePreviewModal() {
            const modal = document.getElementById('preview-modal');
            modal.classList.remove('active');
            document.getElementById('preview-iframe').src = 'about:blank';
            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
        }

        function openPreviewInNewTab() {
            if (currentPreviewBlobUrl) {
                window.open(currentPreviewBlobUrl, '_blank');
            }
        }

        // ================= ZIP GENERATION ENGINE =================
        function generateZipFromModularBuilder() {
            const data = gatherBuilderData();
            if (!data.title) {
                showStatus('Please enter a Course Title before generating ZIP.', true);
                return;
            }

            if (!data.modules || data.modules.length === 0) {
                showStatus('Please add at least one module before generating ZIP.', true);
                return;
            }

            buildAndDownloadZip(data);
        }

        function loadRawJSONIntoBuilder() {
            const raw = document.getElementById('raw-json-input').value.trim();
            if (!raw) {
                showStatus('Please paste JSON output into the box first.', true);
                return;
            }

            try {
                let clean = raw;
                if (clean.includes('```json')) clean = clean.split('```json')[1].split('```')[0].trim();
                else if (clean.includes('```')) clean = clean.split('```')[1].split('```')[0].trim();
                
                const parsed = JSON.parse(clean);
                const title = parsed.properties?.title || parsed.title || 'Imported Course';
                const description = parsed.properties?.description || parsed.description || '';
                const access = parsed.properties?.access?.type || 'public';
                const css = parsed.css_content || '';
                const js = parsed.js_content || '';

                const modules = [];
                if (Array.isArray(parsed.modules)) {
                    parsed.modules.forEach(m => {
                        modules.push({
                            title: m.title || 'Module',
                            filename: m.filename || m.src || 'modules/module.html',
                            html_content: m.html_content || m.content || ''
                        });
                    });
                }

                populateBuilderData({ title, description, access, css, js, modules });
                switchTab('modular-builder-tab', true);
                showStatus('Loaded JSON into Modular Builder cards! You can now preview and edit each module.', false);
            } catch (err) {
                showStatus('Failed to parse JSON: ' + err.message, true);
            }
        }

        function generateZipFromRawJSON() {
            const raw = document.getElementById('raw-json-input').value.trim();
            if (!raw) {
                showStatus('Please paste JSON output into the box first.', true);
                return;
            }

            try {
                let clean = raw;
                if (clean.includes('```json')) clean = clean.split('```json')[1].split('```')[0].trim();
                else if (clean.includes('```')) clean = clean.split('```')[1].split('```')[0].trim();
                
                const parsed = JSON.parse(clean);
                buildAndDownloadZip({
                    title: parsed.properties?.title || 'Imported Course',
                    description: parsed.properties?.description || '',
                    access: parsed.properties?.access?.type || 'public',
                    css: parsed.css_content || '',
                    js: parsed.js_content || '',
                    modules: parsed.modules || []
                });
            } catch (err) {
                showStatus('Invalid JSON output: ' + err.message, true);
            }
        }

        function buildAndDownloadZip(data) {
            const courseTitle = data.title || 'course-package';
            const courseId = courseTitle.toLowerCase().replace(/[^a-z0-9\-]/g, '-').replace(/-+/g, '-');
            const zip = new JSZip();

            // 1. Construct course_structure.json manifest
            const manifest = {
                properties: {
                    title: data.title,
                    description: data.description || '',
                    thumbnail: 'images/thumb.svg',
                    access: { type: data.access || 'public' },
                    assets: { css: ['css/style.css'], js: ['js/main.js'] }
                },
                modules: []
            };

            if (Array.isArray(data.modules)) {
                data.modules.forEach((m, idx) => {
                    const filename = m.filename || (`modules/module${idx + 1}.html`);
                    manifest.modules.push({
                        id: m.id || (`mod-${idx + 1}`),
                        title: m.title || (`Module ${idx + 1}`),
                        src: filename
                    });

                    const htmlContent = m.html_content || (`<section><h1>${escapeHtml(m.title || 'Module')}</h1><p>Module content...</p></section>`);
                    zip.file(filename, htmlContent);
                });
            }

            zip.file('course_structure.json', JSON.stringify(manifest, null, 4));
            zip.file('css/style.css', data.css || '/* Course Custom Styles */\nbody { font-family: inherit; }\n');
            zip.file('js/main.js', data.js || '/* Course Custom Logic */\nconsole.log("Course loaded.");\n');

            const defaultSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#319795"/><text x="50" y="55" font-size="14" fill="white" text-anchor="middle" font-family="sans-serif">Course</text></svg>';
            zip.file('images/thumb.svg', defaultSvg);

            zip.generateAsync({ type: 'blob' }).then(function(content) {
                saveAs(content, courseId + '-package.zip');
                showStatus(`Course package <strong>${courseId}-package.zip</strong> generated and downloaded!`, false);
            });
        }

        // Copy Prompt Function with Fallback & Screen Reader Status Updates
        function copyPrompt(textareaId, btn, promptLabel = 'Prompt') {
            const area = document.getElementById(textareaId);
            if (!area) return;

            function onSuccess() {
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Copied!';
                setTimeout(() => btn.innerHTML = orig, 2500);
                showStatus(`${promptLabel} copied to clipboard! Ready to paste into your AI assistant.`, false);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(area.value).then(onSuccess).catch(() => fallbackCopyText(area.value, onSuccess));
            } else {
                fallbackCopyText(area.value, onSuccess);
            }
        }

        function fallbackCopyText(text, callback) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.top = '-99999px';
            textarea.style.left = '-99999px';
            textarea.setAttribute('readonly', '');
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                document.execCommand('copy');
                document.body.removeChild(textarea);
                if (callback) callback();
            } catch (e) {
                if (document.body.contains(textarea)) document.body.removeChild(textarea);
            }
        }
    </script>
</body>
</html>
