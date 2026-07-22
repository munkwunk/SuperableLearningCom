# Accessibility, Pedagogy, and LMS Engineering Standards

## Screen Reader Form Navigation (NVDA/JAWS/VoiceOver Quick Keys)
- When building question cards or form fieldsets, place the question prompt text **inside** the `<legend>` element within a `<span class="lc-question-prompt">`.
- Always add `aria-describedby="prompt_[id]"` to form inputs (`radio`, `checkbox`, `text`) referencing the prompt ID so screen readers announce the full question prompt when users jump directly to inputs via quick-keys (<kbd>F</kbd>, <kbd>X</kbd>, <kbd>R</kbd>).

## Strict Sequential Heading Hierarchy (H1 ➔ H2 ➔ H3)
- In tabbed or single-page admin panels, set the primary title of the active tab panel to `<h1>` to enable screen reader users (NVDA/JAWS/VoiceOver) to jump past tab navigation straight to the main panel content.
- Ensure all nested section titles under `<h1>` sequentially use `<h2>` (e.g., form containers, table headers), and inner sub-items or accordion headers sequentially use `<h3>`. Never skip heading levels (e.g., jumping from `<h1>` directly to `<h3>`).
- Remove redundant brand text headers in site navigation banners when the brand logo image already contains an accessible `alt="[Brand Name] Logo"` description.

## Multi-Tenant Terminology & UI Guardrails
- Never expose internal multi-tenant developer jargon (e.g., "Tenant", "Tenant ID", "Tenant Logo") in client-facing admin panels or learner-facing UI. Use human-friendly terminology (e.g., "Admin Panel", "Organization Logo", "Storage Usage Meter").
- Form action submit buttons inside settings tabs must explicitly describe their action and tab context (e.g., `Save Branding & Logo Settings` instead of generic `Save` or partial `Save Brand Colors`).
- Settings forms within tabbed interfaces must persist the active tab ID (e.g., in `localStorage`) so that form reloads return the user to the active tab displaying the system status alert (`role="status"`, `aria-live="polite"`).

## White-Label Accessibility & Dark Mode Engine
- Do NOT use naive CSS color inversion (`filter: invert(1)`) for dark mode themes. Inversion corrupts uploaded brand logos, diagrams, and photos while failing WCAG contrast ratios.
- Implement dark mode using dedicated dark surface tokens (e.g., `#0F172A`) and run automated lightness calculation math (`lightenHexColor`) to dynamically adjust tenant brand colors to guarantee a minimum 4.5:1 WCAG AA contrast ratio against dark backgrounds.

## 3-Tiered Pedagogical & Andragogical Assessment Feedback
- In adult learning and formative assessments, NEVER label partial credit ($0 < \text{score} < \text{max}$) as "Correct".
- Implement three distinct evaluation tiers:
  1. **Full Credit** ($100\%$ mastery): Green styling (`#f0fdf4`, text `#166534`), `✓` icon, *"Correct! You earned X out of Y points."*
  2. **Partially Correct** ($0 < \text{score} < 100\%$ progressing): Warm Amber styling (`#fffbeb`, text `#b45309`), `⚠` icon, *"Partially Correct. You earned X out of Y points. Please review your selections."*
  3. **No Credit** ($0\%$ needs review): Red styling (`#fee2e2`, text `#991b1b`), `✗` icon, *"Incorrect. You earned 0 out of Y points. Please review the content and try again."*
- Announce all feedback via `window.jwAnnounce(message, 'assertive')`.

## Toggle Buttons vs. ARIA Live Regions
- Toggle buttons using `aria-expanded="true/false"` (accordions, disclosure panels, TOC toggles) are announced natively by screen readers.
- Do NOT fire secondary live region alerts (`window.jwAnnounce('expanded/collapsed')`) on button clicks to prevent double-reading. Reserve live regions for dynamic background updates and score announcements.

## Decorative Icons & Whitespace Nodes
- Always add `aria-hidden="true"` to decorative icon elements (e.g. `<i class="fa-solid ..." aria-hidden="true"></i>`).
- Never place a literal space character between an icon tag and a text node inside HTML (`<i></i> Text`). Manage visual spacing via CSS (`margin-right: 0.5rem` or flex gap) to prevent screen readers from reading *"Space [Text]"*.

## Multi-Tenant SQLite Foreign Key Sync
- In isolated multi-tenant SQLite architectures with foreign key enforcement (`PRAGMA foreign_keys = ON;`), verify and synchronize the user profile into the tenant database's `users` table before executing `module_progress` updates to prevent FK constraint failures (`SQLSTATE[23000]`).

## SPA Asset Cache-Busting
- In single-page application (SPA) players, append dynamic timestamp query parameters (`?v=<?= time() ?>`) to external script tags to prevent HTTP browser caching of updated JS libraries.
