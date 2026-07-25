# Superable Learning LMS — SL Web Components Reference Guide

> **Technical & Component Specification**: Framework-free, 100% WCAG 2.2 AA compliant web components for interactive e-learning modules. Automatically loaded in all course modules via `assets/components/sl-components.js`.

---

## 1. Overview & Accessibility Mandate

All SL Components are native Web Components (`customElements.define`) engineered with:
* **Keyboard Accessibility**: Full support for <kbd>Tab</kbd>, <kbd>Arrow Keys</kbd>, <kbd>Enter</kbd>, <kbd>Space</kbd>, and <kbd>Escape</kbd>.
* **Screen Reader Live Announcements**: Built-in ARIA live region support (`window.slAnnounce()`, backward-compatible with `window.jwAnnounce()`).
* **Visible Focus Indicators**: High contrast `:focus-visible` rings.
* **Automatic xAPI Event Dispatch**: Emits xAPI statement payloads on interactions automatically.

---

## 2. Component Catalog & HTML Specifications

### 2.1 Accordion Component (`<sl-accordion>`)
Creates accessible collapsible panels matching WAI-ARIA Accordion design patterns.

```html
<sl-accordion level="3">
  <sl-accordion-item title="Prerequisites & Requirements" expanded>
    <p>No prior coding experience is required. All tools are web-based.</p>
  </sl-accordion-item>
  <sl-accordion-item title="Learning Objectives">
    <p>Understand WCAG 2.2 AA standards and build accessible web forms.</p>
  </sl-accordion-item>
</sl-accordion>
```
* **Attributes**:
  * `level` (optional, default `"3"`): Heading level (`<h1>`–`<h6>`) generated for accordion triggers for screen reader document outline compliance.
  * `expanded` (on `<sl-accordion-item>`): Sets item initially open.

---

### 2.2 Tabs Component (`<sl-tabs>`)
Creates accessible tabbed panels matching WAI-ARIA Tabs design patterns.

```html
<sl-tabs aria-label="Course Learning Options">
  <sl-tab label="Overview">
    <h2>Overview</h2>
    <p>Explore the fundamental principles of accessibility.</p>
  </sl-tab>
  <sl-tab label="Key Features">
    <h2>Key Features</h2>
    <p>Screen reader support, keyboard focus trapping, and ARIA roles.</p>
  </sl-tab>
  <sl-tab label="Resources">
    <h2>Resources</h2>
    <p>Download cheatsheets and documentation guides.</p>
  </sl-tab>
</sl-tabs>
```

---

### 2.3 Flip Card Component (`<sl-flip-card>`)
Creates interactive flashcards with accessible front/back reveal actions for self-assessment.

```html
<!-- Attribute Form -->
<sl-flip-card 
  front="What does ARIA stand for?" 
  back="Accessible Rich Internet Applications. It provides semantics for assistive technology.">
</sl-flip-card>

<!-- Child Tag Form -->
<sl-flip-card title="ARIA Roles">
  <sl-front><p>What does role="region" do?</p></sl-front>
  <sl-back><p>Identifies a landmark section for assistive technologies.</p></sl-back>
</sl-flip-card>
```

---

### 2.4 Click-to-Reveal Component (`<sl-click-reveal>`)
Creates expandable solution reveal boxes with automatic xAPI event delegation.

```html
<sl-click-reveal 
  button-text="Reveal Sample Answer" 
  hint="Try answering before revealing the solution.">
  <p><strong>Sample Answer:</strong> WCAG 2.2 AA requires a minimum color contrast ratio of 4.5:1 for normal text.</p>
</sl-click-reveal>
```

---

### 2.5 Accessible Modal Dialog (`<sl-modal>`)
Triggers a WCAG 2.2 AA compliant modal dialog with focus trapping, <kbd>Escape</kbd> key closing, and automatic focus restoration.

```html
<sl-modal 
  trigger-text="View Accessibility Case Study" 
  title="Case Study: Screen Reader Audit Results">
  <p>In our 2026 audit, replacing custom span buttons with native <code>&lt;button&gt;</code> elements improved screen reader completion rates by 42%.</p>
</sl-modal>
```

---

### 2.6 Interactive Branching Scenario (`<sl-scenario>`)
Presents scenario-based decision trees for experiential learning.

```html
<sl-scenario title="Customer Accessibility Request">
  <p>A user requests closed captions for an embedded video. What should you do first?</p>
  <button type="button" class="scenario-choice" data-next="option-a">A. Provide automated captions immediately without manual review.</button>
  <button type="button" class="scenario-choice" data-next="option-b">B. Review captions for 99%+ accuracy and synchronized timing.</button>
</sl-scenario>
```

---

### 2.7 Interactive Timeline (`<sl-timeline>`)
Renders chronological events with keyboard navigation.

```html
<sl-timeline title="History of WCAG Standards">
  <div data-year="1999" data-title="WCAG 1.0">First web accessibility guidelines published by W3C.</div>
  <div data-year="2008" data-title="WCAG 2.0">Introduced POUR principles (Perceivable, Operable, Understandable, Robust).</div>
  <div data-year="2018" data-title="WCAG 2.1">Added mobile accessibility and low-vision criteria.</div>
  <div data-year="2023" data-title="WCAG 2.2">Added target size and focus appearance enhancements.</div>
</sl-timeline>
```

---

### 2.8 Interactive Multi-Step Wizard (`<sl-wizard>`)
Presents step-by-step instructions or multi-stage tasks.

```html
<sl-wizard title="Course Setup Wizard">
  <div data-step="Step 1: Planning">Define your learning objectives and course structure.</div>
  <div data-step="Step 2: Content Creation">Use SL Components to build interactive HTML fragments.</div>
  <div data-step="Step 3: Packaging">Use /packager.php to generate your 1-click LMS ZIP package.</div>
</sl-wizard>
```

---

### 2.9 Accessible Progress Bar (`<sl-progress-bar>`)
Renders accessible `role="progressbar"` status indicators.

```html
<sl-progress-bar value="75" max="100" label="Module Progress"></sl-progress-bar>
```

---

### 2.10 Multi-Column Grid (`<sl-multi-column>`)
Ensures accessible reading order across multi-column layouts.

```html
<sl-multi-column>
  <sl-column title="Phase 1: Preparation">
    <p>Outline lesson concepts and assets.</p>
  </sl-column>
  <sl-column title="Phase 2: Execution">
    <p>Generate HTML fragments block-by-block.</p>
  </sl-column>
</sl-multi-column>
```

---

### 2.11 LC-JSON Interactive Assessment Components (`.lc-question-card`)
Rendered automatically by `LCJsonConverter` for LC-JSON 1.0 question types (`multipleChoice`, `trueFalseQuestion`, `simpleGapFill`, `wordBankCloze`, `shortAnswer`). Includes WCAG 2.2 AA ARIA live region feedback and automatic xAPI statement hooks.

```html
<fieldset class="lc-question-card lc-qtype-multipleChoice" data-global-id="550e8400-e29b-41d4-a716-446655440003" data-points="1.0">
  <legend class="lc-question-legend">
    <span class="lc-question-title">Multiple Choice Question</span>
    <span class="lc-points-badge">(1.0 point)</span>
  </legend>
  <p class="lc-question-prompt">Which of the following are accessible web standards?</p>
  
  <div class="lc-options-group">
    <div class="lc-option-item">
      <input type="radio" id="opt_1" name="q1" value="WCAG" data-pts="1.0" class="lc-option-input">
      <label for="opt_1" class="lc-option-label">WCAG 2.2 AA</label>
    </div>
  </div>
  
  <button type="button" class="lc-btn-submit" data-xapi-verb="ANSWERED" data-xapi-name="LC-JSON Question">Check Answer</button>
  <div class="lc-feedback-region" role="status" aria-live="polite"></div>
</fieldset>
```

---

### 2.12 Client-Side LC-JSON Quiz Engine Component (`<sl-quiz>`)
Dynamically fetches, renders, scores, and emits xAPI analytics for LC-JSON 1.0 `QuestionSet` and `Course` documents. Supports external JSON files (`src`), inline JSON payload attributes (`data-json`), or embedded `<script type="application/json">` blocks.

```html
<!-- External File Source -->
<sl-quiz src="quizzes/accessibility-assessment.lc.json"></sl-quiz>

<!-- Inline JSON Payload Script Tag -->
<sl-quiz>
  <script type="application/json">
    {
      "$schema": "https://lc-json.org/1.0/schemas/questionset.schema.json",
      "documentType": "QuestionSet",
      "title": "WCAG 2.2 Quick Quiz",
      "questions": [
        {
          "type": "trueFalseQuestion",
          "globalId": "550e8400-e29b-41d4-a716-446655440001",
          "prompt": "WCAG 2.2 AA requires a minimum 4.5:1 color contrast ratio for normal text.",
          "correctAnswer": true,
          "points": 1.0
        }
      ]
    }
  </script>
</sl-quiz>
```
* **Attributes**:
  * `src` (optional): URL path to an external LC-JSON specification file.
* **Custom Events**:
  * `sl-quiz-completed` (and legacy `jw-quiz-completed`): Dispatched when the quiz is successfully submitted and graded.
    * `detail` payload structure:
      ```json
      {
        "raw": 4.0,       // Raw points scored
        "max": 5.0,       // Maximum possible points
        "scaled": 0.8,    // Scaled score between 0.0 and 1.0
        "success": true,  // true if scaled score >= 0.7 (70% passing threshold)
        "completion": true
      }
      ```

---

### 2.13 Hotspot Image Component (`<sl-hotspot-container>`)
Creates an interactive image hotspot layout. Users can select marked hotspots to trigger popups with detailed textual explanations. Fully accessible via keyboard navigation, screen reader outline focus, and dynamic `aria-expanded` toggle states.

```html
<sl-hotspot-container src="images/eye-anatomy.png" alt="Anatomy diagram of the human eye">
  <sl-hotspot-marker x="20%" y="30%" label="Cornea">
    <p>The cornea is the transparent front part of the eye that covers the iris and pupil.</p>
  </sl-hotspot-marker>
  <sl-hotspot-marker x="45%" y="55%" label="Retina">
    <p>The retina is the light-sensitive layer of tissue at the back of the eyeball.</p>
  </sl-hotspot-marker>
</sl-hotspot-container>
```
* **Attributes**:
  * `src` (required): URL path to the base image.
  * `alt` (required): Accessibility description of the overall image context.
  * `x`, `y` (on `<sl-hotspot-marker>`): Absolute coordinates (e.g. `20%`, `55%`) where the interactive marker is positioned over the image.
  * `label` (on `<sl-hotspot-marker>`): Accessible label used as the marker's button label and detailed popup title.

---

### 2.14 Matching Game / Drag-and-Drop Alt Component (`<sl-matching-game>`)
A keyboard-accessible alternative to drag-and-drop match widgets. Uses dropdown select lists and politeness alerts to match terms with their correct definitions.

```html
<sl-matching-game label="CSS Selectors Matching challenge">
  <sl-match-pair source="h1" target="Selects all level 1 headings."></sl-match-pair>
  <sl-match-pair source=".highlight" target="Selects elements with a class named highlight."></sl-match-pair>
  <sl-match-pair source="#main" target="Selects the element with the ID main."></sl-match-pair>
</sl-matching-game>
```
* **Attributes**:
  * `label` (optional): Accessible description of the matching list's objective.
  * `source` (on `<sl-match-pair>`): The term or concept to match.
  * `target` (on `<sl-match-pair>`): The corresponding definition or target value.

---

### 2.15 Accessible Content Carousel (`<sl-carousel>`)
Renders sliding presentation slide panels with linear keyboard reading order, slide focus announcements, and pagination buttons.

```html
<sl-carousel aria-label="Course Highlights">
  <sl-slide>
    <h3>Slide 1: Dynamic Data</h3>
    <p>Learn how server-side variables adapt branding settings.</p>
  </sl-slide>
  <sl-slide>
    <h3>Slide 2: Focus Management</h3>
    <p>Understand how outline focus loops prevent tab escaping.</p>
  </sl-slide>
</sl-carousel>
```
* **Attributes**:
  * `aria-label` (required): Accessible description of the carousel slides context.

---

## 3. Declarative xAPI Analytics Tracking

Add `data-xapi` attributes to any clickable button or element to emit analytics tracking without custom JavaScript:

```html
<button type="button" 
        data-xapi-verb="PLAYED" 
        data-xapi-name="Accessibility Video Demo" 
        data-xapi-desc="Learner played the screen reader video demonstration.">
  Play Video Demo
</button>
```

* **Supported Attributes**:
  * `data-xapi-verb`: Verb name (e.g. `PLAYED`, `SKIPPED`, `COMPLETED`, `ANSWERED`, `INTERACTED`).
  * `data-xapi-name`: Name of the activity/component.
  * `data-xapi-desc`: Detailed description of the event.
