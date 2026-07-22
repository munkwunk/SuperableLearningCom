# **Project Overview: Complex Interactions Micro‑Course (SPA) + Accessible Component Library**

A modular, pattern‑based single‑page application (SPA) that teaches learners how to test complex interactive components with NVDA. The SPA mirrors the structure of the *A11y Tree Course* and loads separate HTML modules into a main content area. Each module demonstrates:

- An intentionally inaccessible version of a pattern  
- A “Reveal Accessible Version” interaction  
- A correct, accessible version built from a standalone component library  
- NVDA output describing expected announcements  
- A practice task  

All accessible components must meet **WCAG 2.2 AA** and be implemented in a standalone, framework‑free JavaScript file so they can be reused outside the SPA.  
All course content must include **meta‑pedagogy**, similar to `good_example.html`, explicitly teaching learners *how* and *why* the component works, what NVDA announces, and what accessibility principles are being demonstrated.

The SPA shell itself demonstrates a multi‑column layout, semantic regions, focus management, and dynamic content loading. Navigation is pattern‑based, and each module is a separate HTML file loaded into the main content area.

---

# **Instructions: What to Build**

## **SPA Structure**
- Create a new SPA called **“Complex Interactions Micro‑Course”** modeled after the structure of the *A11y Tree Course* at `/a11y-tree-course/index.html`.
- Use a two‑column layout:
  - Left column: navigation (`<nav>`)
  - Right column: dynamic content area (`<main>`)
- Load module content from separate HTML files located in `/modules/`.
- Use pure HTML, CSS, and vanilla JavaScript. No frameworks or build tools.

## **Component Library**
Create a standalone file:

```
/components/jw-components.js
```

This file must export accessible, reusable components:

- `jw-flipcard`
- `jw-tabs`
- `jw-accordion`
- `jw-dragdrop-alt`

Each component must be:

- Fully accessible  
- Keyboard‑operable  
- Screen‑reader friendly  
- WCAG **2.2 AA** compliant  
- Framework‑free  
- Usable outside the SPA  

## **Initial Modules to Build**
Create the following HTML files in `/modules/`:

- `flipcard.html`
- `tabs.html`
- `accordion.html`
- `dragdrop-alt.html`

Each module must follow the module template below.

## **Module Template (Required for Every Pattern)**
Each module HTML file must contain:

1. **Heading: Pattern Name**  
2. **Section: Inaccessible Example**  
   - Provide a broken version of the pattern  
   - Include incorrect roles, missing labels, poor keyboard behavior, etc.  
   - Include meta‑pedagogy explaining *why* it is broken  
3. **Button: “Reveal Accessible Version”**  
   - When activated, replace the broken DOM with the accessible component  
   - Move focus appropriately  
   - Include meta‑pedagogy describing what changed  
4. **Section: Accessible Example**  
   - Render the accessible component from `/components/jw-components.js`  
   - Include meta‑pedagogy describing how the component works and why it is accessible  
5. **Section: NVDA Output**  
   - Provide a short transcript of expected NVDA announcements  
   - Include meta‑pedagogy explaining what the announcements mean  
6. **Section: Practice Task**  
   - Provide a short prompt for learners to document or test the pattern  

## **Navigation**
The left‑hand navigation must include:

- Flip Cards  
- Tabs  
- Accordion  
- Drag‑and‑Drop Alternative  

Each nav item loads its corresponding HTML file into the main content area.

## **Pedagogical Requirements**
All modules must:

- Teach the pattern through interaction  
- Demonstrate the broken version first  
- Reveal the accessible version  
- Explain what NVDA announces and why  
- Include meta‑commentary similar to `good_example.html`  
- Provide a practice task  

---

# **Instructions: How to Build It**

## **File Structure**
```
/complex-interactions/
    index.html
    /modules/
        flipcard.html
        tabs.html
        accordion.html
        dragdrop-alt.html
    /components/
        jw-components.js
    /css/
        styles.css
    /js/
        loader.js
```

## **SPA Shell Requirements**
- Reuse the SPA wrapper pattern from the *A11y Tree Course*.
- `loader.js` must:
  - Load module HTML files into `<main>`
  - Update the URL hash
  - Highlight the active nav item
  - Manage focus after content loads

## **Component Library Requirements**
- All components must be implemented in `/components/jw-components.js`.
- Components must not depend on the SPA.
- Components must be reusable in any HTML page.
- Components must expose a clean API (e.g., custom elements or factory functions).
- Components must meet **WCAG 2.2 AA** requirements, including:
  - Keyboard operability  
  - Visible focus  
  - Correct ARIA roles/states  
  - Logical reading order  
  - Robust announcements  

## **Module Behavior Requirements**
- Each module loads independently via the SPA.
- The “Reveal Accessible Version” button must:
  - Remove or hide the broken example
  - Insert the accessible component
  - Move focus to the first interactive element in the accessible version
  - Trigger any necessary ARIA announcements
- Meta‑pedagogy must be embedded in the content:
  - Explain what is broken  
  - Explain what is fixed  
  - Explain what NVDA announces  
  - Explain why the accessible version meets WCAG 2.2 AA  

## **Content Requirements**
- All examples must be realistic and reflect common e‑learning patterns.
- Broken examples must intentionally violate WCAG 2.2 AA.
- Accessible examples must demonstrate correct implementation using the component library.
- NVDA transcripts must be accurate and concise.
- Practice tasks must reinforce testing and documentation skills.




# **Completed Interactions**
The following patterns have been implemented in the component library and integrated as pedagogical modules:

### E-Learning Patterns
- **Tier 1 (Essential):** Click-to-Reveal Panels, Hotspot Image Interaction, Matching Interaction (Drag-and-Drop Alt), Scenario-Based Branching, Timeline Interaction, Flip Cards, Tabs, Accordion.
- **Tier 2 (High-Value):** Multi-Step Wizard, Live Region Notifications, Progress Bar, Carousel / Slider, Multi-Column Layouts, Interactive Tables (Sortable).

### Web-App Utilities & Anti-Patterns
- **Utilities:** Modal Dialog, Tooltip / Info Hover, Form Validation + Error Messaging.
- **Anti-Patterns:** Fake Buttons (Div), Fake Links (Span).

---

# **Future Interaction Roadmap**
Remaining tasks for the course expansion.

### E‑Learning Interaction Patterns (Highest Priority)
#### Tier 1 — Essential
- Flip Card Variants (Matching cards, 3D effects)
- Tabs with Nested Interactions
- Accordion Variants

#### Tier 2 — High‑Value
- [All high-value items currently implemented]

#### Tier 3 — Anti‑Patterns
- Incorrect ARIA Roles
- Over‑announcing / Redundant Labels
- Keyboard Traps
- Hidden Content Still in the Accessibility Tree
- Incorrect aria-live Usage

### General Web‑App Interaction Patterns (Secondary Priority)
#### Tier 1 — High‑Value
- Menu Button / Disclosure Button (Dropdown menu)
- Pagination Component
- Breadcrumb Navigation

#### Tier 2 — Advanced
- Tree View
- Editable / Interactive Data Table
- Virtualized List / Infinite Scroll
- Drag‑to‑Resize Panels
- Split View / Pane Navigation

#### Tier 3 — Specialized
- Calendar / Date Picker
- Accessible Charts / Data Visualization
