<?php
/**
 * Superable Learning - Dynamic Help Center & Documentation Portal
 * 
 * Auto-discovers and renders markdown documentation files inside /help/docs/,
 * supporting categories, live search, accordion-style Table of Contents,
 * syntax highlighting, prompt extraction, and WCAG 2.2 AA accessibility.
 */

require_once __DIR__ . '/config.php';

$tenantMetadata = getTenantMetadata();

// Determine URL Prefix depending on whether request comes through /help/ or /help.php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isSubfolderUrl = (strpos($requestUri, '/help/') !== false);
$urlPrefix = $isSubfolderUrl ? '../' : '';

/**
 * Recursively scans /help/docs/ for all markdown documentation files.
 */
function discoverHelpDocuments() {
    $docsDir = __DIR__ . DIRECTORY_SEPARATOR . 'help' . DIRECTORY_SEPARATOR . 'docs';
    if (!is_dir($docsDir)) {
        // Fallback to local docs folder
        $docsDir = __DIR__ . DIRECTORY_SEPARATOR . 'docs';
    }

    $documents = [];
    if (!is_dir($docsDir)) {
        return $documents;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docsDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            $realPath = $file->getRealPath();
            $relativePath = ltrim(str_replace($docsDir, '', $realPath), '/\\');
            $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
            
            $categoryKey = count($pathParts) > 1 ? $pathParts[0] : 'general';
            $filename = basename($realPath);
            $slugBase = strtolower(pathinfo($filename, PATHINFO_FILENAME));
            
            // Generate clean human title, description, and slug
            $content = file_get_contents($realPath);
            $parsed = parseDocumentHeader($content, $filename, $categoryKey);

            $documents[$parsed['slug']] = [
                'slug'       => $parsed['slug'],
                'alias_slugs'=> $parsed['aliases'],
                'title'      => $parsed['title'],
                'category'   => $parsed['category'],
                'category_key' => $categoryKey,
                'desc'       => $parsed['desc'],
                'filepath'   => $realPath,
                'relpath'    => $relativePath
            ];
        }
    }

    return $documents;
}

/**
 * Parses title, description, and aliases from markdown headers.
 */
function parseDocumentHeader($markdown, $filename, $categoryKey) {
    $title = '';
    $desc = '';
    $aliases = [];
    $slug = strtolower(pathinfo($filename, PATHINFO_FILENAME));

    // Map Category Keys to Human Readable Titles
    $categoryNames = [
        'administration' => 'Admin & Portal Management',
        'branding'       => 'Branding & Theme Overrides',
        'authoring'      => 'Authoring & AI Prompts',
        'packaging'      => 'Packaging & Uploads',
        'components'     => 'UI Web Components',
        'integrations'   => 'Standards & Integrations',
        'general'        => 'General Documentation'
    ];
    $categoryName = $categoryNames[$categoryKey] ?? ucfirst($categoryKey);

    // Extract H1 title
    if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
        $title = trim(strip_tags($matches[1]));
    }

    // Extract description (first blockquote > ... or first paragraph)
    if (preg_match('/^>\s*(.+)$/m', $markdown, $descMatches)) {
        $desc = trim(strip_tags($descMatches[1]));
    } else {
        $lines = explode("\n", $markdown);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && strpos($trimmed, '#') !== 0 && strpos($trimmed, '```') !== 0) {
                $desc = trim(strip_tags($trimmed));
                if (strlen($desc) > 160) {
                    $desc = substr($desc, 0, 157) . '...';
                }
                break;
            }
        }
    }

    if (empty($title)) {
        $title = ucwords(str_replace(['-', '_'], ' ', $slug));
    }
    if (empty($desc)) {
        $desc = "Documentation and technical specifications for {$title}.";
    }

    // Legacy Slug Alias Mapping for backward compatibility
    if ($slug === 'llm-course-builder-instructions' || $slug === 'llm-instructions') {
        $aliases[] = 'llm-prompt';
    } elseif ($slug === 'course-packaging-and-upload-guide') {
        $aliases[] = 'packaging-guide';
    } elseif ($slug === 'course-builder-guide') {
        $aliases[] = 'builder-guide';
    } elseif ($slug === 'sl-components-reference') {
        $aliases[] = 'sl-components';
        $aliases[] = 'jw-components';
        $aliases[] = 'jw-components-reference';
    } elseif ($slug === 'lc-json-integration-guide') {
        $aliases[] = 'lc-json';
    }

    return [
        'slug'     => $slug,
        'aliases'  => $aliases,
        'title'    => $title,
        'category' => $categoryName,
        'desc'     => $desc
    ];
}

$allDocs = discoverHelpDocuments();
$docKey = strtolower($_GET['doc'] ?? '');

// Resolve Active Document by slug or legacy alias
$activeDoc = null;
if (!empty($docKey)) {
    if (isset($allDocs[$docKey])) {
        $activeDoc = $allDocs[$docKey];
    } else {
        foreach ($allDocs as $d) {
            if (in_array($docKey, $d['alias_slugs'])) {
                $activeDoc = $d;
                break;
            }
        }
    }
}

$rawMarkdown = '';
if ($activeDoc && file_exists($activeDoc['filepath'])) {
    $rawMarkdown = file_get_contents($activeDoc['filepath']);
}

// Group Documents by Category for the Hub View
$groupedDocs = [];
foreach ($allDocs as $doc) {
    $cat = $doc['category'];
    if (!isset($groupedDocs[$cat])) {
        $groupedDocs[$cat] = [];
    }
    $groupedDocs[$cat][] = $doc;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $activeDoc ? htmlspecialchars($activeDoc['title']) . ' — ' : '' ?>Superable Learning Help Center</title>
    <link rel="stylesheet" href="<?= $urlPrefix ?>style.css">
    <?= renderTenantBrandingCss() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Marked.js for Accessible Client-Side Markdown Rendering -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <!-- Highlight.js for Code Block Syntax Highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    
    <style>
        .help-header { background: #1a202c; color: white; padding: 2.5rem 0; }
        .help-container { padding: 2.5rem 0; }
        .search-box { max-width: 600px; margin: 0 auto 2.5rem auto; position: relative; }
        .search-input {
            width: 100%;
            padding: 0.85rem 1.2rem 0.85rem 3rem;
            font-size: 1.05rem;
            border: 2px solid #cbd5e1;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            font-family: inherit;
            transition: all 0.2s ease;
        }
        .search-input:focus {
            outline: none;
            border-color: #319795;
            box-shadow: 0 0 0 3px rgba(49, 151, 149, 0.25);
        }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.1rem; }

        .category-title { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; }
        
        .doc-card { background: white; border: 1px solid var(--color-neutral-mid); border-radius: 0.5rem; padding: 1.75rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .doc-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .category-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 0.75rem; margin-bottom: 0.75rem; }
        
        /* Accordion-Style Table of Contents (TOC) Component */
        .toc-accordion {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .toc-accordion-trigger {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1.25rem;
            background: #edf2f7;
            border: none;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e293b;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s ease;
            text-align: left;
        }
        .toc-accordion-trigger:hover { background: #e2e8f0; }
        .toc-accordion-trigger:focus-visible {
            outline: 3px solid #319795;
            outline-offset: -3px;
        }
        .toc-arrow { transition: transform 0.2s ease; }
        .toc-accordion-trigger[aria-expanded="true"] .toc-arrow { transform: rotate(180deg); }

        .toc-accordion-content {
            padding: 1rem 1.5rem 1.25rem 1.5rem;
            display: block;
            transition: max-height 0.25s ease;
        }
        .toc-accordion-content.collapsed { display: none; }
        .toc-list { list-style: none; padding-left: 0; margin: 0; }
        .toc-list li { margin-bottom: 0.4rem; }
        .toc-list li.toc-h3 { padding-left: 1.25rem; font-size: 0.95em; }
        .toc-list a { color: #2b6cb0; text-decoration: none; font-weight: 600; }
        .toc-list a:hover { text-decoration: underline; color: #1a365d; }

        .markdown-content { background: white; padding: 2.5rem; border-radius: 0.5rem; border: 1px solid var(--color-neutral-mid); line-height: 1.7; position: relative; }
        .markdown-content h1 { border-bottom: 2px solid var(--color-neutral-mid); padding-bottom: 0.5rem; margin-top: 0; }
        .markdown-content h2 { border-bottom: 1px solid #e2e8f0; padding-bottom: 0.3rem; margin-top: 2rem; color: var(--color-primary); }
        .markdown-content pre { position: relative; background: #2d3748; padding: 1.25rem; border-radius: 0.5rem; overflow-x: auto; margin: 1.5rem 0; }
        .markdown-content code { font-family: 'Courier New', Courier, monospace; }
        .markdown-content p code { background: #edf2f7; color: #2d3748; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-size: 0.9em; }
        .markdown-content blockquote { border-left: 4px solid var(--color-primary); margin-left: 0; padding-left: 1rem; color: #4a5568; background: #ebf8ff; padding: 0.75rem 1rem; border-radius: 0 0.25rem 0.25rem 0; }
        .markdown-content table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        .markdown-content th, .markdown-content td { text-align: left; padding: 0.75rem; border: 1px solid #e2e8f0; }
        .markdown-content th { background: #f7fafc; }
        
        .copy-btn {
            background: #319795;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 0.25rem;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .copy-btn:hover { background: #2c7a7b; }
        .copy-btn-secondary { background: #4a5568; }
        .copy-btn-secondary:hover { background: #2d3748; }

        .code-copy-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        .code-copy-btn:hover { background: rgba(255, 255, 255, 0.3); color: white; }

        .sr-only { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; border: 0 !important; }
    </style>
</head>
<body class="bg-bg-light">
    <a href="#help-main" class="skip-link">Skip to main content</a>

    <header class="help-header">
        <div class="container mx-auto px-4 flex justify-between items-center max-w-6xl flex-wrap gap-4">
            <div>
                <h1 class="m-0 text-3xl font-bold" style="color: white;">Help Center & Documentation Portal</h1>
                <p class="m-0 mt-2 text-sm" style="color: #cbd5e0;">Superable Learning LMS Guides, Technical Specifications & Component APIs</p>
            </div>
            <div class="flex gap-4 items-center">
                <a href="<?= $urlPrefix ?>index.php" class="text-white font-bold text-sm">← Platform Homepage</a>
                <a href="<?= $urlPrefix ?>admin.php" class="cta-button" style="background: transparent; border: 2px solid white; color: white;">Admin Panel</a>
            </div>
        </div>
    </header>

    <main id="help-main" class="container mx-auto px-4 help-container max-w-6xl">

        <?php if (!$activeDoc): ?>
            <!-- Help Center Overview / Directory Hub -->
            <div class="max-w-3xl mx-auto text-center mb-8">
                <h2 class="text-3xl font-bold mb-3">How can we help you today?</h2>
                <p class="text-neutral-mid text-lg">Browse our documentation guides or search for specific topics below.</p>
            </div>

            <!-- Instant Search Bar -->
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="help-search-input" class="search-input" placeholder="Search documentation, guides, components, prompts..." aria-label="Search documentation guides">
            </div>

            <!-- Featured Tool: Modular Web Course Packager -->
            <div class="max-w-5xl mx-auto mb-10" id="featured-tool-card">
                <article class="doc-card flex flex-col justify-between" style="border: 2px solid #319795;">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <span class="category-badge" style="background: #e6fffa; color: #234e52;">INTERACTIVE WEB APPLICATION</span>
                            <h3 class="text-2xl font-bold mb-2">Modular Course Builder & Packager Tool</h3>
                            <p class="text-neutral-mid m-0">Build courses block-by-block, test & preview modules live in a sandbox, and export ready-to-upload LMS ZIP packages with zero coding required!</p>
                        </div>
                        <a href="<?= $urlPrefix ?>packager.php" class="cta-button text-center whitespace-nowrap" style="background: #319795; padding: 0.8rem 1.6rem;" aria-label="Open Web Course Packager Tool">
                            Open Modular Builder &rarr;
                        </a>
                    </div>
                </article>
            </div>

            <!-- Categorized Help Articles Directory -->
            <div class="max-w-5xl mx-auto" id="docs-directory">
                <?php foreach ($groupedDocs as $categoryName => $docs): ?>
                    <section class="doc-category-group mb-10">
                        <h3 class="category-title"><?= htmlspecialchars($categoryName) ?></h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <?php foreach ($docs as $doc): ?>
                                <article class="doc-card flex flex-col justify-between doc-search-item" data-search-text="<?= htmlspecialchars(strtolower($doc['title'] . ' ' . $doc['desc'] . ' ' . $doc['category'])) ?>">
                                    <div>
                                        <span class="category-badge" style="background: #ebf8ff; color: #2b6cb0;"><?= htmlspecialchars(strtoupper($doc['category_key'])) ?></span>
                                        <h4 class="text-xl font-bold mb-2"><?= htmlspecialchars($doc['title']) ?></h4>
                                        <p class="text-neutral-mid mb-6"><?= htmlspecialchars($doc['desc']) ?></p>
                                    </div>
                                    <a href="?doc=<?= urlencode($doc['slug']) ?>" class="cta-button text-center" style="background: #2b6cb0;" aria-label="View <?= htmlspecialchars($doc['title']) ?>">
                                        Read Guide &rarr;
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <div id="no-search-results" style="display: none;" class="text-center py-12">
                    <p class="text-xl font-bold text-neutral-mid">No documentation guides matched your search query.</p>
                    <p class="text-sm text-neutral-mid">Try searching for keywords like "prompt", "packaging", "components", or "lc-json".</p>
                </div>
            </div>

            <script>
                // Instant Live Search Filter
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('help-search-input');
                    const items = document.querySelectorAll('.doc-search-item');
                    const groups = document.querySelectorAll('.doc-category-group');
                    const noResults = document.getElementById('no-search-results');
                    const featuredCard = document.getElementById('featured-tool-card');

                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            const query = this.value.toLowerCase().trim();
                            let totalVisible = 0;

                            if (featuredCard) {
                                featuredCard.style.display = query.length > 0 ? 'none' : 'block';
                            }

                            groups.forEach(group => {
                                let groupVisibleCount = 0;
                                const groupItems = group.querySelectorAll('.doc-search-item');

                                groupItems.forEach(item => {
                                    const text = item.getAttribute('data-search-text') || '';
                                    if (query === '' || text.includes(query)) {
                                        item.style.display = 'flex';
                                        groupVisibleCount++;
                                        totalVisible++;
                                    } else {
                                        item.style.display = 'none';
                                    }
                                });

                                group.style.display = groupVisibleCount > 0 ? 'block' : 'none';
                            });

                            noResults.style.display = (totalVisible === 0 && query.length > 0) ? 'block' : 'none';
                        });
                    }
                });
            </script>

        <?php else: ?>
            <!-- Document Viewer Mode -->
            <div class="mb-6 flex justify-between items-center flex-wrap gap-3 max-w-5xl mx-auto">
                <a href="?" class="font-bold text-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    ← Back to Help Center Hub
                </a>

                <div class="flex gap-3 items-center flex-wrap">
                    <?php if (in_array('llm-prompt', $activeDoc['alias_slugs']) || $activeDoc['slug'] === 'llm-course-builder-instructions'): ?>
                        <button type="button" id="copy-prompt-btn" class="copy-btn" aria-label="Copy Master AI Prompt section only to clipboard">
                            <i class="fa-solid fa-robot"></i> Copy Master AI Prompt
                        </button>
                    <?php endif; ?>
                    <button type="button" id="copy-doc-btn" class="copy-btn copy-btn-secondary" aria-label="Copy full document content to clipboard">
                        <i class="fa-solid fa-file-lines"></i> Copy Full Document
                    </button>
                </div>
            </div>

            <!-- Accessibility Live Region for Screen Readers -->
            <div id="copy-status" role="status" aria-live="polite" class="sr-only"></div>
            <div id="a11y-live-region" role="status" aria-live="polite" class="sr-only"></div>

            <div class="max-w-5xl mx-auto">
                <!-- Accordion-Style Table of Contents (TOC) Component -->
                <div class="toc-accordion" id="toc-accordion-container" style="display: none;">
                    <button type="button" class="toc-accordion-trigger" id="toc-toggle-btn" aria-expanded="true" aria-controls="toc-content-list">
                        <span><i class="fa-solid fa-list-ol" aria-hidden="true" style="margin-right: 0.5rem;"></i>Table of Contents</span>
                        <i class="fa-solid fa-chevron-down toc-arrow" aria-hidden="true"></i>
                    </button>
                    <div id="toc-content-list" class="toc-accordion-content" role="region" aria-labelledby="toc-toggle-btn">
                        <ul class="toc-list" id="toc-ul-list"></ul>
                    </div>
                </div>

                <article class="markdown-content" id="markdown-viewer">
                    <!-- Noscript Accessible Fallback -->
                    <noscript>
                        <h1><?= htmlspecialchars($activeDoc['title']) ?></h1>
                        <pre><code><?= htmlspecialchars($rawMarkdown) ?></code></pre>
                    </noscript>
                </article>
            </div>

            <!-- Raw Markdown Container for JavaScript Renderer -->
            <textarea id="raw-md-data" style="display: none;"><?= htmlspecialchars($rawMarkdown) ?></textarea>

            <script>
                function extractMasterPrompt(rawMd) {
                    if (!rawMd) return '';
                    const fenceMatch = rawMd.match(/```(?:markdown)?\s*\n([\s\S]*?)\n```/i);
                    if (fenceMatch && fenceMatch[1]) {
                        return fenceMatch[1].trim();
                    }
                    return rawMd.trim();
                }

                function announceToScreenReader(msg) {
                    const statusBox = document.getElementById('copy-status') || document.getElementById('a11y-live-region');
                    if (statusBox) {
                        statusBox.textContent = '';
                        setTimeout(() => { statusBox.textContent = msg; }, 100);
                    }
                }

                function copyToClipboard(text, btnElement, successMsg, srAnnouncement) {
                    const originalHTML = btnElement ? btnElement.innerHTML : '';
                    
                    function onSuccess() {
                        if (btnElement) {
                            btnElement.innerHTML = successMsg || '<i class="fa-solid fa-check"></i> Copied!';
                            const origBg = btnElement.style.background;
                            btnElement.style.background = '#276749';
                            setTimeout(function() {
                                btnElement.innerHTML = originalHTML;
                                btnElement.style.background = origBg;
                            }, 2500);
                        }
                        if (srAnnouncement) announceToScreenReader(srAnnouncement);
                    }

                    function onFailure() {
                        const textarea = document.createElement('textarea');
                        textarea.value = text;
                        textarea.style.position = 'fixed';
                        textarea.style.top = '-99999px';
                        document.body.appendChild(textarea);
                        textarea.focus();
                        textarea.select();
                        
                        try {
                            if (document.execCommand('copy')) {
                                onSuccess();
                            } else {
                                throw new Error('execCommand failed');
                            }
                        } catch (err) {
                            if (btnElement) btnElement.innerHTML = '⚠️ Press Ctrl+C';
                            announceToScreenReader('Copy to clipboard failed. Please select text manually.');
                        } finally {
                            if (document.body.contains(textarea)) document.body.removeChild(textarea);
                        }
                    }

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text).then(onSuccess).catch(onFailure);
                    } else {
                        onFailure();
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const rawMdData = document.getElementById('raw-md-data');
                    const viewer = document.getElementById('markdown-viewer');
                    const rawMd = rawMdData ? rawMdData.value : '';

                    if (typeof marked !== 'undefined' && rawMd) {
                        viewer.innerHTML = marked.parse(rawMd);
                        
                        if (typeof hljs !== 'undefined') {
                            viewer.querySelectorAll('pre code').forEach((block) => {
                                hljs.highlightElement(block);
                            });
                        }

                        // Attach Copy Button to Code Blocks
                        viewer.querySelectorAll('pre').forEach((preBlock, idx) => {
                            const copyBtn = document.createElement('button');
                            copyBtn.type = 'button';
                            copyBtn.className = 'code-copy-btn';
                            copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy Code';
                            copyBtn.setAttribute('aria-label', `Copy code snippet ${idx + 1} to clipboard`);
                            copyBtn.addEventListener('click', function() {
                                const codeText = preBlock.querySelector('code') ? preBlock.querySelector('code').innerText : preBlock.innerText;
                                copyToClipboard(codeText, copyBtn, '<i class="fa-solid fa-check"></i> Code Copied!', 'Code snippet copied.');
                            });
                            preBlock.appendChild(copyBtn);
                        });

                        // Build Accordion-Style Table of Contents (TOC)
                        const headings = viewer.querySelectorAll('h2, h3');
                        const tocContainer = document.getElementById('toc-accordion-container');
                        const tocUl = document.getElementById('toc-ul-list');
                        const tocToggleBtn = document.getElementById('toc-toggle-btn');
                        const tocContent = document.getElementById('toc-content-list');

                        if (headings.length > 0 && tocContainer && tocUl) {
                            headings.forEach((h, idx) => {
                                if (!h.id) {
                                    h.id = 'heading-' + idx;
                                }
                                const li = document.createElement('li');
                                li.className = h.tagName.toLowerCase() === 'h3' ? 'toc-h3' : 'toc-h2';
                                
                                const link = document.createElement('a');
                                link.href = '#' + h.id;
                                link.textContent = h.textContent;
                                li.appendChild(link);
                                tocUl.appendChild(li);
                            });

                            tocContainer.style.display = 'block';

                            // Accordion Expand / Collapse Control
                            if (tocToggleBtn && tocContent) {
                                tocToggleBtn.addEventListener('click', function() {
                                    const isExpanded = this.getAttribute('aria-expanded') === 'true';
                                    const newExpanded = !isExpanded;
                                    
                                    this.setAttribute('aria-expanded', newExpanded ? 'true' : 'false');
                                    if (newExpanded) {
                                        tocContent.classList.remove('collapsed');
                                    } else {
                                        tocContent.classList.add('collapsed');
                                    }
                                });
                            }
                        }
                    }

                    // Copy Buttons
                    const copyPromptBtn = document.getElementById('copy-prompt-btn');
                    if (copyPromptBtn && rawMd) {
                        copyPromptBtn.addEventListener('click', function() {
                            const promptOnlyText = extractMasterPrompt(rawMd);
                            copyToClipboard(promptOnlyText, copyPromptBtn, '<i class="fa-solid fa-check"></i> AI Prompt Copied!', 'Master AI prompt copied to clipboard.');
                        });
                    }

                    const copyDocBtn = document.getElementById('copy-doc-btn');
                    if (copyDocBtn && rawMd) {
                        copyDocBtn.addEventListener('click', function() {
                            copyToClipboard(rawMd, copyDocBtn, '<i class="fa-solid fa-check"></i> Full Document Copied!', 'Full document copied.');
                        });
                    }
                });
            </script>
        <?php endif; ?>

    </main>

    <?= renderTenantFooter() ?>
</body>
</html>
