# LMS Course Builder & Technical Blueprint Guide

This guide provides the technical blueprint, sitemap, component reference, and accessibility standards for creating, packaging, and integrating courses into the `superable-learning` LMS.

---

## 1. Directory & Package Structure

Every course package must follow this standard directory hierarchy:

```text
/superable-learning/courses/[course-id]/
├── course_structure.json    # Manifest file (Required)
├── modules/                 # HTML content fragments
│   ├── welcome.html
│   ├── module1.html
│   └── ...
├── css/                     # Course-specific styles
│   └── style.css
├── js/                      # Course-specific logic
│   └── main.js
├── images/                  # Media assets (thumbnails, SVGs)
└── [course-id].md           # Source markdown (Optional)
```

---

## 2. Manifest Schema (`course_structure.json`)

The LMS player (`player.php`) uses this manifest to construct sidebar navigation, access control, and tracking:

```json
{
    "properties": {
        "title": "Accessible Web Design 101",
        "description": "Master WCAG 2.2 AA compliant web interfaces.",
        "thumbnail": "images/thumb.svg",
        "access": {
            "type": "public" 
        },
        "assets": {
            "css": ["css/style.css"],
            "js": ["js/main.js"] 
        }
    },
    "modules": [
        {
            "id": "welcome",
            "title": "Welcome & Overview",
            "src": "modules/welcome.html"
        },
        {
            "id": "mod-1",
            "title": "Module 1: Core Concepts",
            "src": "modules/module1.html"
        }
    ]
}
```

* **Access Modes**: `"public"`, `"protected"`, `"teaser"`, `"hidden"`.
* **Assets**: Custom CSS and JS files listed in `assets` are loaded across all course module views.

---

## 3. Web Modular Course Builder (`packager.php`)

To eliminate AI prompt token truncation when using free web-based LLMs (Gemini, ChatGPT, Claude), use the **Modular Course Builder** at `/packager.php`:

1. **Step A (Metadata & Styles)**: Define course title, access mode, custom CSS, and JS.
2. **Step B (Block-by-Block Module Authoring)**: Paste HTML fragments one module at a time.
3. **Media Asset Insertion Toolbar**:
   * Each module content textarea is equipped with a media toolbar: `[📷 + Image]`, `[📎 + Download]`, and `[🎵 + Audio]`.
   * **In-Memory Asset Caching**: Selecting a local file caches it in browser memory (`globalZipAssets`). On final package generation, the builder dynamically packages these assets inside the course ZIP under `images/`, `files/`, or `audio/` with zero backend server upload delays.
   * **Enforced Alt-Text & Input Prompts**: Inserting an image prompts the author to input a screen reader description (enforcing accessibility). Inserting a file prompts the author for descriptive link text before generating the HTML anchor tag.
   * **Keyboard-First Navigation Layout**: The buttons are placed immediately preceding the textarea in the DOM tree, but styled visually using flexbox `row-reverse`. When a screen reader user presses `Shift + Tab` backwards from the content area, the cursor lands directly on **`+ Image`** (most common), followed by `+ Download` and `+ Audio`. Upon selecting a file and completing the inputs, focus is programmatically restored to the textarea (auto-entering screen reader Focus Mode) right after the new tag.
   * **Placeholder / Selection Insertion**: Authors who aren't HTML-savvy can instruct the LLM to write placeholders like `[INSERT INFOGRAPHIC HERE]`. Highlighting this text in the content card and clicking `+ Image` replaces the highlighted selection with the correct HTML tag. If no text is selected, the tag is safely appended to the end of the file.
4. **Dynamic Re-Indexing**: Add, delete, duplicate, and reorder modules with automatic 1-based ordinal re-indexing (`Module #1`, `Module #2`, etc.).
5. **Live Sandbox Previews**: Test single module components (`👁️ Preview Module`) or full-course player navigation (`▶️ Full Course Live Preview`).
6. **Draft Export & ZIP Generation**: Save drafts locally, copy/export JSON manifests, and generate 1-click LMS upload packages.

---

## 4. WAI-ARIA APG Flip Card Pattern & Accessibility Specs

JW Flip Cards (`<jw-flip-card>`) follow strict WAI-ARIA Authoring Practices Guide standards:

### HTML Syntax Options
```html
<!-- Attribute Form -->
<jw-flip-card 
  title="WCAG Contrast" 
  front="What is the minimum contrast ratio for normal text in WCAG 2.2 AA?" 
  back="4.5:1 for normal text, and 3:1 for large text (18pt+ or 14pt+ bold).">
</jw-flip-card>

<!-- Child Tag Form -->
<jw-flip-card title="ARIA Roles">
  <jw-front><p>What does role="region" do?</p></jw-front>
  <jw-back><p>Identifies a landmark section for assistive technologies.</p></jw-back>
</jw-flip-card>
```

### Accessibility Architecture Specs
1. **Landmark Region**: Outer container uses `role="region"` + `aria-label="Interactive Flipcard"` (or `Interactive Flipcard: [Title]`).
2. **Card Faces**: Front and Back containers use `role="group"` + `aria-label="Front of Card"` and `aria-label="Back of Card"`.
3. **Emoji & Redundancy Prevention**: Visual badge headers (`<div class="jw-flip-card-header" aria-hidden="true">`) are marked `aria-hidden="true"` so screen readers announce group names ONCE without duplicate text node reading.
4. **State**: Flip buttons use `aria-expanded="false"` (Front) and `aria-expanded="true"` (Back) with explicit `aria-label`s.
5. **Focus Shift**: Activating a flip button shifts focus directly to the revealed side (`targetSide.focus()`), placing the virtual cursor at the top of the revealed content.

---

## 5. Technical Discoveries & Iframe Sandbox Rules

### 1. Blob URL Asset Resolution
Inside Blob iframe sandboxes (`blob:http://...`), relative paths (`assets/components/jw-components.js`) fail to resolve against the origin. Always compute **absolute URLs** (`new URL('assets/components/jw-components.js', baseUrl).href`).

### 2. HTML5 `innerHTML` Script Execution
Per HTML5 specifications, scripts inserted into a container via `container.innerHTML = html_string` are dormant by default. To execute scripts dynamically during SPA module swaps or iframe renders:
```javascript
container.querySelectorAll('script').forEach(oldScript => {
    const newScript = document.createElement('script');
    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
    newScript.textContent = oldScript.textContent;
    oldScript.parentNode.replaceChild(newScript, oldScript);
});
```

### 3. Iframe Keyboard Escape Key Relay (WCAG 2.1.2 & 2.4.3)
Keyboard events originating inside an `<iframe>` do not bubble to parent window listeners. Inject a keydown listener inside the iframe document to relay <kbd>Escape</kbd> key presses:
```javascript
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && window.parent && typeof window.parent.closePreviewModal === 'function') {
        window.parent.closePreviewModal();
        e.preventDefault();
    }
});
```

### 4. Reduced Motion & Vestibular Support (WCAG 2.3.3)
Respect system motion preferences:
```javascript
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
```

### 5. Status Messages & Assertive Priority (WCAG 4.1.3)
To ensure screen readers announce status updates (like reordering, deletions, and boundary warnings) during rapid interactions:
* Reset `statusBox.textContent = ''` before setting new text to force a fresh DOM mutation event.
* Use `aria-live="assertive"` for reordering/deletions so status speech immediately interrupts button-focus audio.

---

## 6. Full Superable Learning (SL) Components Library

For complete API specifications, code samples, and attributes for all 12+ web components (`<sl-accordion>`, `<sl-tabs>`, `<sl-flip-card>`, `<sl-click-reveal>`, `<sl-modal>`, `<sl-scenario>`, `<sl-timeline>`, `<sl-wizard>`, `<sl-progress-bar>`, `<sl-multi-column>`), refer to [sl-components-reference.md](file:///C:/Users/jacob/projects/superablelearning.com/help/docs/components/sl-components-reference.md).
