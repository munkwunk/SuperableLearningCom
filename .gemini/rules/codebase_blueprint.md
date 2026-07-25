# Superable Learning LMS — Codebase Blueprint

This document acts as a high-level system overview and architectural map for Superable Learning LMS. It is placed in `.gemini/rules/` to be loaded automatically into the agent's context at the start of every session.

---

## 1. Core Architecture & Multi-Tenancy

Superable Learning is a **zero-dependency, file-isolated multi-tenant Learning Management System (LMS)** designed for high performance, compliance, and strict security isolation (complying with FERPA/HIPAA).

### Database & Storage Isolation
Unlike traditional monolithic software sharing tables, this system segregates client databases and assets into isolated files and directories:
- **Base Database Directory**: `../db/superablelearning/`
- **Tenant Databases**: `../db/superablelearning/tenants/{tenantKey}.sqlite`
- **Asset Storage**: `../storage/superablelearning/tenants/{tenantKey}/`
- **Uploaded Course Media**: `../courses/tenants/{tenantKey}/` (each course package includes a `course_structure.json` manifest).

---

## 2. Global Codebase Map

| File / Folder | Purpose / Responsibility |
| :--- | :--- |
| `index.php` | **Unified Entry Point**. Renders either the Platform Landing Page (if browsing `local-dev` with no query parameters) or the specific Client Tenant LMS Dashboard (if resolved to a client key). |
| `config.php` | **Platform Core Engine**. Handles tenant resolution (`resolveTenantKey`), database pooling, contrast calculations, and typography rendering. |
| `style.css` | **Global Design System**. Loaded by all pages. Implements CSS Custom Properties, layout containers, tailwind-style utilities, accessibility guards, navigation headers, responsive buttons, cards, and theme variables. |
| `player.php` | **Accessible Player Core**. Runs SCORM archives, HTML5 course packages, and interactive LC-JSON modules. Built-in video/audio speed selectors, transcripts, and focus trap locks. |
| `pricing.php` | **Platform Pricing Strategies**. Shows plans (Sandbox, Pro, Premium) and tenant limit details. |
| `help.php` | **Developer & Content Author Documentation**. Specs for course packaging (HTML5/SCORM) and LC-JSON components. |
| `admin.php` | **Tenant-Level Panel**. Allows client admins to manage branding colors, upload custom CSS, change logos, review storage meters, and provision invitation codes. |
| `platform_admin.php`| **Host Platform Panel**. Centrally monitors client tenants, upgrades plans, manages sandbox limits, and provisions database files. |
| `login.php` / `logout.php` | Isolated, tenant-aware user authentication. |
| `register.php` | Tenant-aware registration and course invitation key entry. |
| `course_importer.php` | Handles course ZIP uploads, validates folder integrity, and parses manifest structures. |
| `lc_json_converter.php`| Converts modular learning components (quizzes, interactive cards, media) to WCAG-compliant LC-JSON. |

---

## 3. Key Development Workflows

### Tenant Resolution Pipeline
Every web request resolves the tenant key via `resolveTenantKey()` in [config.php](file:///C:/Users/jacob/projects/superablelearning.com/config.php):
1. Checks subdomains (e.g. `acme.superablelearning.com`).
2. Checks query parameters (e.g. `index.php?tenant=acme`).
3. Checks custom domain mapping files (`custom_domains.json`).
4. If no tenant is resolved, it defaults to the host landing page (`local-dev`).

### Brand Style & Contrast Engine
When a tenant page loads, `renderTenantBrandingCss($tenantKey)` dynamically pulls configuration variables:
1. Loads the base theme and font family configurations.
2. Checks the client's custom primary brand color.
3. Automatically darkens light colors (progressive loop checking WCAG AA compliance `>= 4.5:1` against white backgrounds) to ensure high readability.
4. Generates an equivalent dark mode variant by shifting custom properties appropriately against `#0F172A`.

---

## 4. UI Standards

- **Strict Accessibility Compliance (WCAG 2.2 AA)**: All page elements, interactive features, forms, and custom components must strictly comply with WCAG 2.2 AA guidelines. This is non-negotiable (e.g. contrast ratios `>= 4.5:1` for standard text and `>= 3.0:1` for interactive borders and large headings).
- **Responsive Layout Design**: The interface must be completely responsive across mobile, tablet, and desktop viewports. Implement fluid margins, wrap nav links safely, and use flexbox/grid utilities instead of hardcoded pixel widths.
- **Accessible Focus**: Always preserve focus outlines. Elements inside `.site-header` and `.hero-banner` must use a solid white outline to pass contrast on dark backgrounds.
- **Touch Target**: Interactive components (`button`, `a.btn`, `a.cta-button`, input fields) must guarantee a minimum touch target size of `44px` (WCAG 2.2 AA / AAA ready).
- **Responsive Flex**: Layouts must wrap natively. Avoid hardcoded widths. Use `.grid` utility layouts (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`) to reflow sections smoothly.
- **Emoji Wrapping**: All decorative emojis (e.g. 🏢, ♿, 🛠️) must be wrapped in tags declaring `aria-hidden="true"` so text readers don't stutter on them.

---

## 5. Strategic Vision & Extensibility (SMB to Enterprise)

While Superable Learning is currently tailored to **SMBs, nonprofits, and community groups** who need a lightweight, low-complexity, and zero-dependency learning engine, all codebase modifications must be built with a **future-extensible, enterprise-grade architecture** in mind:

- **Database Extensibility**: Keep SQL database queries clean and standard. Avoid writing SQLite-specific dialects that would prevent easy migration to PostgreSQL or MySQL. Always enforce foreign keys (`PRAGMA foreign_keys = ON;`).
- **Separation of Concerns**: Write logic in modular components and service handlers rather than embedding inline script execution in HTML. This ensures key subsystems (like authentication or asset storage) can be swapped out later for enterprise equivalents (e.g., SAML/OIDC SSO, AWS S3, Azure Blob storage) without rewriting core layout components.
- **White-Label Customization**: Ensure custom CSS overrides (`custom.css`) and tenant configurations remain isolated. Custom web components (`sl-` with legacy `jw-` aliases) must accept branding colors dynamically through native CSS variables rather than hardcoded colors.

---

## 6. Strategic Roadmaps & Product Concepts

To understand the broader product features, upcoming enhancements, and concepts driving this system, refer to these documents:
- **Conceptual Architecture & Roadmap V2**: [Superable LMOS Concepts and Roadmap v2.md](file:///C:/Users/jacob/projects/superablelearning.com/Superable%20LMOS%20Concepts%20and%20Roadmap%20v2.md) outlines the core ideas behind the "Learning Management OS" paradigm, including offline-first syncing, peer-to-peer module sharing, and headless course player APIs.
- **Product Development Roadmap**: [ROADMAP.md](file:///C:/Users/jacob/projects/superablelearning.com/ROADMAP.md) details completed milestones, active sprint features, and future integration targets (e.g. SCORM tracking progress and LC-JSON editor additions).


