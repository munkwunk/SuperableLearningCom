# 🧭 Superable Learning — Development Roadmap

This document outlines the engineering and service roadmap for the Superable Learning platform. It categorizes the pricing page features by development complexity and implementation timeline (Quick Wins, Core Development, and Long-Term Enhancements), cross-referenced by product tier.

**Before working any item below:** see [AUDIT-2026-07-29.md](AUDIT-2026-07-29.md) for known security and accessibility defects in the current codebase, kept as a separate remediation list rather than merged here since it's fixing what's already shipped, not building what's next. Its critical security findings (C-1–C-6) should be treated as done-before-anything-else, independent of roadmap sequencing.

---

## 🎯 Vision & Mission

**Vision:** A world where the barriers to teaching are as superable as the barriers to learning — where disabled people are no more locked out of sharing what they know than anyone else.

**Mission:** Superable Learning builds accessibility-guardrailed authoring, delivery, and facilitation tools that make the barriers to teaching as superable as the barriers to learning. We're enabling you to get it right without already being an accessibility expert — the tools carry that knowledge so you don't have to. We build for the people with knowledge to share, not the people auditing for compliance.

*(Adopted 2026-07-31. Everything below is prioritized in service of this.)*

---

## 📌 Priority Notes (2026-07-31 Reprioritization)

Most of this roadmap was written when the platform was positioned as an LMS competing on management and compliance features. Following a strategy review, priority has shifted toward tools that empower creators and facilitators, away from features that primarily serve org-level tracking and compliance monitoring. Items below are tagged inline rather than physically reordered, so tier-gating and historical context stay intact — **build in the order below, not in phase-number order.**

**⭐ Build first — the keystone:** Decouple Course Player (Phase 2). It unlocks the open-source player, headless rendering/verification, and the authoring loop below — nothing else on this list works cleanly until this is done.

**⭐ Promote from Phase 3 (long-term) to now — this is the mission, not a future bet:**
- BYOK AI Integration Module + BYOK AI-Assisted Authoring — the accessibility-guardrailed authoring loop.
- Node.js Rendering Service (Puppeteer/Playwright) — pairs with the player decoupling; lets the audit engine check *actual rendered output*, not just JSON structure. Needed by both the authoring loop's retry logic and the verification badge below.
- Automated WCAG Linting & Heatmaps, NVDA/JAWS Simulation Mode, Accessibility Regression Testing, Automated Report Generation — together these become an automated, continuous verification badge, distinct from (and cheaper/more scalable than) the human-reviewed audit add-on, which stays as the premium tier.

**🔁 Keep building, but pitch differently:**
- xAPI/LRS Integration — this is the handoff that lets us *not* own tracking, not a compliance feature. Frame it that way publicly.
- LMS Migration Pipeline — "get your content out of an inaccessible legacy LMS and made accessible," not "replace Canvas/Blackboard/Moodle."
- Integration Marketplace — the concrete mechanism for "we plug into the LMS you already have," not a competing system.

**⏸ Deprioritized — not wrong to have, just not where effort goes right now:**
- Role-Based Access Control (RBAC), Shared Course Library & Brand Settings — org-permission scaffolding for the compliance-monitor buyer we've stepped away from.
- Cognitive Load Engine's telemetry/ML pieces (Interaction Data Processor, Adaptive Player Interface) — only revisit if reframed as adaptive UX for the learner, not usage tracking; complex to build for a currently-speculative payoff.
- Tenant-Level Scholarship Workflows — on-mission (removes a cost barrier) but it's checkout plumbing, not differentiated capability. Fine to pick up opportunistically, not urgent.

**✅ Already correctly prioritized, no change:** MultAbilities & Dignity-First Learning (both phases), Accommodation Passport, Teleprompter/Presenter Console items — genuinely on-mission, keep as scheduled.

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

## 🛠️ Phase 2: Core Platform & LMOS Foundation (Requires Additional Work)
These features require changes to the database schemas, new operational flows, or third-party integrations, spanning several development cycles. They also build the foundation for the LMOS kernel.

### Architecture & LMOS Readiness
*   **Database Abstraction Layer**: Refactor raw PDO instances in `config.php` and `api.php` behind a database connector wrapper to allow SQLite-to-PostgreSQL routing in the future.
*   **API-First Endpoints**: Implement JSON API endpoints for user metadata, course structures, and progress logs to prepare for external Node.js/Python runners.
*   **Decouple Course Player**: Restructure `player.php` into a controller that outputs course JSON to a unified client-side rendering player, facilitating Puppeteer headless tests. *(⭐ Build first — see Priority Notes)*
*   **Telemetry Buffer Endpoint**: Add an interaction tracking API endpoint (`api.php?action=log_interaction`) to buffer player telemetry in the SQLite databases for cognitive load heuristics.

### Sandbox (Free)
*   **Automatic Cleanup Routines**: Implement a background cleanup script (runnable via cron) that automatically flags or purges assets of Sandbox accounts inactive for over 90 days.

### Pro ($10/mo)
*   **Billing & Subscription Enforcement**: Integrate Stripe subscription webhook endpoints or implement manual subscription validation checks in `platform_admin.php`.
*   **Internal Usage Logs**: Implement usage tracking to monitor active user accounts and file bandwidth to prevent abuse.

### Premium ($20/mo)
*   **Role-Based Access Control (RBAC)**: Extend the database schema to support three user roles (Admin, Editor, Viewer) and restrict actions in `admin.php` and course editing views based on these roles. *(⏸ Deprioritized — see Priority Notes)*
*   **Shared Course Library & Brand Settings**: Enable multi-admin synchronization so all 3 Premium admins can collaborate on the same course files and branding templates. *(⏸ Deprioritized — see Priority Notes)*
*   [x] **Advanced JSON Validation**: Integrate a JSON schema validator in `course_importer.php` to perform deep structure checks on LC-JSON manifests.
*   **Component-Level Previews**: Create a modal preview wrapper in the packager/builder to test single UI web components (e.g., contrast check individual buttons or text inputs).
*   [x] **Light Analytics Dashboard**: Build a database-backed analytics view tracking completion rates, average time spent per module, and quiz pass/fail statistics.
*   **xAPI/LRS Integration**: Build an LRS connection wizard to transmit standardized xAPI statements to external Learning Record Stores. *(🔁 Reframe as the handoff that lets us not own tracking — see Priority Notes)*
*   [x] **Universal Player Tracking & xAPI Video Analytics**: Update the player engine (`player.php`) and HTML importer validator (`course_importer.php`) to whitelist and support `<mux-player>` elements and YouTube/Vimeo iframe embeds, automatically binding to their respective APIs (Mux events, YouTube IFrame Player API, and Vimeo SDK) to emit detailed xAPI video statements (plays, pauses, progress percentages, completion) to the active LRS and telemetry log.
*   **Dual-Screen Presenter Console & Teleprompter Sync (Local)**: Leverage the browser's client-side `BroadcastChannel` API to synchronize a fullscreen slide window (shared on Zoom/Teams) with a scrollable teleprompter/notes window on the same machine. Scrolling the notes past trigger markers automatically advances the audience's slides with zero server overhead. See the [Technical Specification](help/docs/specs/presenter-mode-teleprompter-sync.md).

### Branding (Cross‑Tier)
*   [x] **Safe Custom CSS Injection**: Implement a PHP-based CSS parser to validate custom stylesheet uploads for Premium tenants, blocking potential security exploits (e.g., CSS injection) and ensuring focus ring states are not hidden.

### Accessibility Add‑Ons (Operational Services)
*   **Subscription Review Credit Tracking**: Update the database schema to allocate and track monthly review credits for subscription customers, carrying over or resetting quotas.
*   **Review Report Delivery UI**: Create an interactive portal section or a PDF report generator allowing human accessibility auditors to securely deliver audits to the client workspace.
*   **Billing Workflow**: Integrate add-on purchase checkouts and invoice generations for individual courses or recurring auditing subscriptions.

### MultAbilities & Dignity-First Learning
*   **Low-Output Branching Scenarios**: Implement branching decision-tree components in `jw-components.js` and the LC-JSON parser/converter.
*   **No-Shame Resume Engine**: Build state machine patterns in `player.php` and `api.php` that welcome returning users after long pauses without warning screens, days-since-active meters, or gamified penalties (e.g., streaks).

---

## 🚀 Phase 3: LMOS Services & Enterprise Orchestration (Long-Term / Strategic)
Strategic features and optional tooling designed to establish the platform as a full-fledged, accessibility-first Learning Management Operating System.

### Accessibility Kernel & Microservices
*   **Node.js Rendering Service**: Deploy Puppeteer/Playwright microservice to capture course screenshots, extract accessibility trees, and run WCAG audits. *(⭐ Promoted to now — see Priority Notes)*
*   **BYOK AI Integration Module**: Integrate a secure backend proxy to connect to external LLM APIs (e.g., Gemini or OpenAI) using client-supplied API keys for automated image analysis, alt-text generation, and reading complexity indexing. *(⭐ Promoted to now — see Priority Notes)*
*   **Event/Queue System Integration**: Deploy Redis or RabbitMQ queues to handle asynchronous auditing and ingestion tasks without blocking web workers.

### Cognitive Load Engine
*   **Interaction Data Processor**: Consume telemetry metrics (dwell time, navigation paths, pause intervals) to score student fatigue and cognitive burden. *(⏸ Deprioritized until reframed as adaptive UX, not tracking — see Priority Notes)*
*   **Adaptive Player Interface**: Enable dynamic content chunking and interface pacing based on cognitive heuristics. *(⏸ Deprioritized until reframed as adaptive UX, not tracking — see Priority Notes)*
*   **Collaborative Co-Authoring Queue**: Build backend tables and admin interfaces (`admin.php` / `api.php`) to queue, edit, format, and approve messy student contributions.

### Migration & Interoperability
*   **LMS Migration Pipeline**: Support importing zip files from Canvas, Blackboard, and Moodle, converting course structures to LC-JSON on ingestion. *(🔁 Reframe as "get your content out and made accessible," not "replace your LMS" — see Priority Notes)*
*   **Multi-Disability Accommodation Profile**: Introduce the "Accommodation Passport" enabling users to set system-wide preferences (Fatigue mode, Screen-reader focus mode, Motor accessibility mode, and Fatigue/Cognitive-Load CSS overrides that simplify the UI layout and reduce visual weight).

### MultAbilities & Dignity-First Learning
*   **Confidence-Based Self-Assessments**: Self-checks where students gauge their understanding, offering immediate, low-pressure path adjustments.
*   **Categorization Checkers**: Accessible keyboard-driven sorting grids asking "Is this an example of X?".
*   **Tenant-Level Scholarship Workflows**: Support automated "Request Scholarship" fee-waiver pipelines at course checkout with single-click admin approval queues in `admin.php` for tenants utilizing integrated payment gateways. *(⏸ Low priority — on-mission but checkout plumbing, not differentiated — see Priority Notes)*

### Sandbox (Free)
*   **Onboarding & Interactive Walkthrough**: Implement a step-by-step tour for new creators showcasing players, LC-JSON packages, and accessibility validation tools.
*   **Read-Only Premium Feature Demo**: Create a sandbox simulation allowing users to view (but not modify or save) Premium features like heatmaps or advanced validation.
*   **BYOK AI-Assisted Authoring**: Connect LLM prompts to the Modular Course Builder/Editor using client-supplied API keys (stored in secure client configurations) to automatically generate accessibility-first course components. *(⭐ Promoted to now — see Priority Notes)*

### Pro ($10/mo)
*   **Smart Import Fixer**: Auto-remedy common format discrepancies (e.g., missing alt text placeholders, syntax issues) during course package imports.

### Premium ($20/mo)
*   **Automated WCAG Linting & Heatmaps**: Build a JavaScript scan tool that generates visual highlights (heatmaps) over elements violating contrast, touch target size, or screen reader guidelines. *(⭐ Promoted — part of the automated verification badge — see Priority Notes)*
*   **NVDA/JAWS Simulation Mode**: Build a CSS/JS overlay simulating screen reader focus order and text speech bubbles directly in the preview player. *(⭐ Promoted — part of the automated verification badge — see Priority Notes)*
*   **Accessibility Regression Testing**: Implement an automated test runner checking uploaded updates against previous accessibility baselines. *(⭐ Promoted — part of the automated verification badge — see Priority Notes)*
*   **Integration Marketplace**: Add an integrations panel to map custom third-party LMS providers and standard SCORM engines. *(🔁 Reframe as "we plug into the LMS you already have" — see Priority Notes)*
*   **BYOK Mux.com Direct-to-Cloud Video Uploads**: Build an administrative console mapping tenant-owned Mux API credentials (stored securely in `tenants/{tenantKey}.json`). Integrate client-side direct-to-Mux upload flows within the Web Course Packager (`packager.php`) to upload video files directly from the browser to Mux, completely bypassing LMS server storage limits and local bandwidth constraints.
*   **Voice-Activated Advancing & Cross-Device Remote Control**: Integrate the browser's local Web Speech API to listen to the presenter's microphone and auto-advance slides upon matching keywords in the notes. Utilize a WebSocket service (made possible by VPS migration) to allow a smartphone/tablet to act as a remote notes controller syncing with the main presentation screen. See the [Technical Specification](help/docs/specs/presenter-mode-teleprompter-sync.md).

### Branding (Cross‑Tier)
*   **Brand Style Presets**: Build pre-curated, high-contrast typography and color schemes matching different aesthetic styles (corporate, academic, dark mode) that meet WCAG 2.2 standards out of the box.
*   **Multi-Brand Profiles**: Allow organizations to maintain and toggle between multiple active branding profiles for different courses or sub-departments.

### Accessibility Add‑Ons (Operational Services)
*   **Automated Report Generation**: Build an internal auditing engine to auto-populate basic audit reports from automated WCAG scans, leaving only specific remediation notes for human review. *(⭐ Promoted — part of the automated verification badge — see Priority Notes)*
