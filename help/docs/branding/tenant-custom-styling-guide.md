---
title: "Custom Branding, Logo Uploads & Dark Mode Styling Guide"
category: "branding"
description: "Learn how to upload custom organization logos, configure brand colors, select accessible typography, manage dark mode themes, and maintain WCAG 2.2 AA compliance."
---

# Custom Branding, Logo Uploads & Dark Mode Styling Guide

Superable Learning allows client organizations to fully brand their LMS environment while enforcing strict, platform-level accessibility guardrails to guarantee **WCAG 2.2 AA compliance**, layout predictability, and screen reader safety.

---

## 🖼️ Organization Logo Uploads & Resolution Guardrails

Organizations can upload their custom logo directly from the **Admin Dashboard** under **Custom Branding & Logo**.

### Supported Logo Formats
- **Formats**: `SVG`, `PNG`, `JPG`, `JPEG`, `GIF`, `WEBP`
- **Maximum File Size**: 5 MB

### Automated Display Guardrails
To prevent uploaded logos from stretching, overflowing headers, or breaking mobile layouts, Superable Learning applies strict CSS layout guardrails (`.brand-logo`):

```css
.brand-logo {
    max-height: 44px;
    max-width: 240px;
    width: auto;
    height: auto;
    object-fit: contain;
    vertical-align: middle;
    display: inline-block;
}
```

*Note: WCAG 2.2 AA guidelines permit flexibility for brand logos regarding color contrast. However, we recommend selecting or uploading a high-contrast logo variant that remains visible against your header background color.*

---

## 🌙 Automated Dark Mode Engine & Contrast Adjustment

Superable Learning features an automated dark mode surface engine with persistent learner theme toggles (`🌙 Theme` / `☀️ Light Mode`).

### How Dark Mode Protection Works:
1. **No Naive Color Inversion**: We do NOT use naive CSS inversion (`filter: invert(1)`), which corrupts logos, photos, and diagrams.
2. **Dedicated Surface Tokens**: Dark mode renders off-black slate backgrounds (`#0F172A`), dark card containers (`#1E293B`), and soft white body copy (`#F8FAFC`).
3. **Dynamic Contrast Calculation**: Our contrast engine automatically calculates relative luminance math (`lightenHexColor`) to dynamically adjust brand primary and accent colors to meet a minimum **4.5:1 WCAG AA contrast ratio** against dark backgrounds.

---

## 🔤 Accessible Brand Typography (Font Family Selector)

Select a WCAG-verified Google Font from the **Custom Branding & Logo** tab to set global typography across body copy, headings, buttons, and navigation:

- **Atkinson Hyperlegible** *(Default - Highest Readability & Letterform Distinction)*
- **Inter** *(Clean Modern Sans-Serif)*
- **Roboto** *(Standard Neo-Grotesque)*
- **Open Sans** *(Neutral Highly Legible)*
- **Lexend** *(Specialized Dyslexia-Friendly Layouts)*

---

## 📸 Course Media Asset Manager (Working with AI & LLMs)

When creating courses using AI assistants (like ChatGPT, Claude, or Gemini) or pasting code blocks into the Web Course Packager, course creators can upload and embed diagrams, screenshots, or infographics.

### How Course Image Management Works:
1. **Upload Images**: In the Admin Dashboard under **Course Media & Image Asset Manager**, select your target course and upload your image file (`.png`, `.jpg`, `.svg`, `.webp`, `.gif`).
2. **Relative Pathing**: Files are stored directly in your course’s isolated asset folder (`courses/tenants/{portalKey}/{courseId}/assets/`).
3. **Pasting Image Tags in LLM Content**:
   - **HTML Modules**: Use standard relative tags:
     ```html
     <img src="assets/diagram-1.png" alt="Accessible description of the diagram">
     ```
   - **Markdown Modules**: Use standard markdown tags:
     ```markdown
     ![Accessible description of the diagram](assets/diagram-1.png)
     ```

---

## 🛡️ Platform Accessibility Guardrails (Locked Rules)

To ensure that custom brand styling never breaks accessibility for disabled users or screen reader users, the following core platform rules are **locked system-wide**:

1. **Focus Ring Visibility (`:focus-visible`)**: Focus indicators (`3px solid var(--color-accent)`) cannot be hidden, suppressed, or removed.
2. **Screen Reader Utility Locking (`.sr-only`)**: Screen reader text utilities are enforced with `!important` to prevent custom CSS from hiding accessible label text.
3. **Minimum Touch Target Size (WCAG 2.5.5 / 2.5.8)**: Interactive buttons and tab triggers maintain a minimum touch target height of **44px**.
4. **Automated Contrast Engine**: Primary colors submitted in the Admin Dashboard are automatically tested for relative luminance. If a primary hex color falls below **4.5:1 WCAG AA contrast** against white text or dark slate, the system automatically darkens or lightens the rendered output to a compliant shade.

---

## 🎨 Allowed Overrides & Customization Tokens

Organizations can customize brand aesthetics using our CSS Custom Properties:

| CSS Variable | Default Value | Description / Usage |
| :--- | :--- | :--- |
| `--color-primary` | `#3B7A57` | Main brand header background, primary buttons (`.cta-button`, `.btn`), main title headings (`h1`–`h4`), links. *(Subject to 4.5:1 contrast check)* |
| `--color-primary-hover` | `#33684b` | Darker hover state for primary buttons. |
| `--color-secondary` | `#5F8F6B` | Secondary buttons, blockquote borders, badge accents. |
| `--color-accent` | `#946300` | High-visibility focus rings (`:focus-visible`), active link indicators, highlight markers. |
| `--color-bg-light` | `#F9F9F7` | Warm off-white page background and card containers. |
| `--color-text-dark` | `#1F1F1F` | Dark charcoal text color for high-readability body copy. |
| `--color-neutral-mid` | `#595959` | Muted subtitle text and secondary indicators. |

---

## 🛠️ Customization Methods

### Method 1: Admin Dashboard (Recommended)
Use the **Admin Dashboard** under **Custom Branding & Logo** to upload your logo, select your font family, set welcome text, and save your brand colors with **Save Branding & Logo Settings**.

### Method 2: Custom CSS File Overrides
Place a `custom.css` file inside your portal folder:
```
courses/tenants/{portalKey}/custom.css
```
Superable Learning will load `custom.css` directly after the master stylesheet. All custom rules remain subject to the platform’s locked accessibility guardrails.
