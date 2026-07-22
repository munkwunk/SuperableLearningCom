# 🧭 Superable Learning LMS — Development Roadmap

This document outlines the engineering and service roadmap for the Superable Learning LMS platform. It categorizes the pricing page features by development complexity and implementation timeline (Quick Wins, Core Development, and Long-Term Enhancements), cross-referenced by product tier.

---

## 📅 Phase 1: Quick Wins (Doable in 1–2 Sittings) [COMPLETED]
These tasks leverage the existing codebase and database connections, focusing on feature gating, UI indicators, and configuration changes that have been successfully integrated.

### Sandbox (Free)
*   [x] **Dynamic Storage Quota Enforcement**: Replaced `MAX_TENANT_STORAGE_MB` constant checks with dynamic loader from the tenant's active plan view (Sandbox = 250 MB).
*   [x] **Admin Dashboard Quota UI**: Dynamic storage meter accurately matches active Sandbox view.
*   [x] **Sandbox Feature Gating**: Replaced custom branding panel with a locked teaser screen when on Sandbox.
*   [x] **Non-Intrusive Upgrade Prompts**: Rendered high-contrast upgrade notices in header area for Sandbox view.
*   [x] **Sandbox Limitation Documentation**: (Included in help documentation and pricing matrix).

### Pro ($10/mo)
*   [x] **Dynamic Storage Quota Enforcement**: Set storage limit to 500 MB when Pro is active.
*   [x] **Pro Feature Gating**: Enabled variable colors and logo uploads, but locked Premium custom CSS file injection.
*   [x] **Priority Support Form Integration**: Added Priority Support SLA box mapping the 72-hour email response commitment.
*   [x] **Pro Limitation Documentation**: (Included in help center articles).

### Premium ($20/mo)
*   [x] **Dynamic Storage Quota Enforcement**: Enabled 1 GB storage limit on Premium.
*   [x] **Draft vs. Published States**: Implemented draft selector and restricted index.php / player.php access for non-admins.
*   [x] **Light Activity Logging**: Appended all administrative actions to isolated tenant log file under `storage/.../activity.log`.
*   [x] **Multi-Admin Cap Check**: Enforced 3-admin account database limit upon creating administrators.
*   [x] **Premium Support Routing**: Rendered high-priority Zoom and rapid routing SLA routing box on Billing tab.

### Branding (Cross‑Tier)
*   [x] **Sandbox Gating**: Hidden branding tabs for Sandbox, replaced with Pro/Premium upgrade options.
*   [x] **Branding CSS Variable Preview**: Built-in color picker inputs in branding tab allow instant local previews before saving.

### Accessibility Add‑Ons (Operational Services)
*   [x] **Operational Definitions Code**: Dynamic per-module pricing calculators for courses with >5 modules.
*   [x] **Auditing Request Submission UI**: Interactive request panel inside the plan tab calculates cost on client selection.
*   [x] **Auditing Checklists & Templates**: Structured scopes added to administration help documents.

---

## 🛠️ Phase 2: Core Platform Development (Requires Additional Work)
These features require changes to the database schemas, new operational flows, or third-party integrations, spanning several development cycles.

### Sandbox (Free)
*   **Automatic Cleanup Routines**: Implement a background cleanup script (runnable via cron) that automatically flags or purges assets of Sandbox accounts inactive for over 90 days.

### Pro ($10/mo)
*   **Billing & Subscription Enforcement**: Integrate Stripe subscription webhook endpoints or implement manual subscription validation checks in `platform_admin.php`.
*   **Internal Usage Logs**: Implement usage tracking to monitor active user accounts and file bandwidth to prevent abuse.

### Premium ($20/mo)
*   **Role-Based Access Control (RBAC)**: Extend the database schema to support three user roles (Admin, Editor, Viewer) and restrict actions in `admin.php` and course editing views based on these roles.
*   **Shared Course Library & Brand Settings**: Enable multi-admin synchronization so all 3 Premium admins can collaborate on the same course files and branding templates.
*   **Advanced JSON Validation**: Integrate a JSON schema validator in `course_importer.php` to perform deep structure checks on LC-JSON manifests.
*   **Component-Level Previews**: Create a modal preview wrapper in the packager/builder to test single UI web components (e.g., contrast check individual buttons or text inputs).
*   **Light Analytics Dashboard**: Build a database-backed analytics view tracking completion rates, average time spent per module, and quiz pass/fail statistics.
*   **xAPI/LRS Integration**: Build an LRS connection wizard to transmit standardized xAPI statements to external Learning Record Stores.

### Branding (Cross‑Tier)
*   **Safe Custom CSS Injection**: Implement a PHP-based CSS parser to validate custom stylesheet uploads for Premium tenants, blocking potential security exploits (e.g., CSS injection) and ensuring focus ring states are not hidden.

### Accessibility Add‑Ons (Operational Services)
*   **Subscription Review Credit Tracking**: Update the database schema to allocate and track monthly review credits for subscription customers, carrying over or resetting quotas.
*   **Review Report Delivery UI**: Create an interactive portal section or a PDF report generator allowing human accessibility auditors to securely deliver audits to the client workspace.
*   **Billing Workflow**: Integrate add-on purchase checkouts and invoice generations for individual courses or recurring auditing subscriptions.

---

## 🚀 Phase 3: Future & Long-Term Roadmap (Optional / Future Enhancements)
Strategic features and optional tooling designed to enhance value across all tiers over the long term.

### Sandbox (Free)
*   **Onboarding & Interactive Walkthrough**: Implement a step-by-step tour for new creators showcasing players, LC-JSON packages, and accessibility validation tools.
*   **Read-Only Premium Feature Demo**: Create a sandbox simulation allowing users to view (but not modify or save) Premium features like heatmaps or advanced validation.

### Pro ($10/mo)
*   **AI-Assisted LC-JSON Authoring**: Connect LLM prompts to the Modular Course Builder to automatically generate accessibility-first course components based on user input.
*   **Smart Import Fixer**: Auto-remedy common format discrepancies (e.g., missing alt text placeholders, syntax issues) during course package imports.

### Premium ($20/mo)
*   **Automated WCAG Linting & Heatmaps**: Build a JavaScript scan tool that generates visual highlights (heatmaps) over elements violating contrast, touch target size, or screen reader guidelines.
*   **NVDA/JAWS Simulation Mode**: Build a CSS/JS overlay simulating screen reader focus order and text speech bubbles directly in the preview player.
*   **Accessibility Regression Testing**: Implement an automated test runner checking uploaded updates against previous accessibility baselines.
*   **Integration Marketplace**: Add an integrations panel to map custom third-party LMS providers and standard SCORM engines.

### Branding (Cross‑Tier)
*   **Brand Style Presets**: Build pre-curated, high-contrast typography and color schemes matching different aesthetic styles (corporate, academic, dark mode) that meet WCAG 2.2 standards out of the box.
*   **Multi-Brand Profiles**: Allow organizations to maintain and toggle between multiple active branding profiles for different courses or sub-departments.

### Accessibility Add‑Ons (Operational Services)
*   **Automated Report Generation**: Build an internal auditing engine to auto-populate basic audit reports from automated WCAG scans, leaving only specific remediation notes for human review.
