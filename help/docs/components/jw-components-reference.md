# Superable Learning LMS — JW Web Components Reference Guide

> **Technical & Component Specification**: Framework-free, 100% WCAG 2.2 AA compliant web components for interactive e-learning modules. Automatically loaded in all course modules via `assets/components/jw-components.js`.

---

## 1. Overview & Accessibility Mandate

All JW Components are native Web Components (`customElements.define`) engineered with:
* **Keyboard Accessibility**: Full support for <kbd>Tab</kbd>, <kbd>Arrow Keys</kbd>, <kbd>Enter</kbd>, <kbd>Space</kbd>, and <kbd>Escape</kbd>.
* **Screen Reader Live Announcements**: Built-in ARIA live region support (`window.jwAnnounce()`).
* **Visible Focus Indicators**: High contrast `:focus-visible` rings.
* **Automatic xAPI Event Dispatch**: Emits xAPI statement payloads on interactions automatically.

---

## 2. Component Catalog & HTML Specifications

### 2.1 Accordion Component (`<jw-accordion>`)
Creates accessible collapsible panels matching WAI-ARIA Accordion design patterns.

```html
<jw-accordion level="3">
  <jw-accordion-item title="Prerequisites & Requirements" expanded>
    <p>No prior coding experience is required. All tools are web-based.</p>
  </jw-accordion-item>
  <jw-accordion-item title="Learning Objectives">
    <p>Understand WCAG 2.2 AA standards and build accessible web forms.</p>
  </jw-accordion-item>
</jw-accordion>
```
* **Attributes**:
  * `level` (optional, default `"3"`): Heading level (`<h1>`–`<h6>`) generated for accordion triggers for screen reader document outline compliance.
  * `expanded` (on `<jw-accordion-item>`): Sets item initially open.

---

### 2.2 Tabs Component (`<jw-tabs>`)
Creates accessible tabbed panels matching WAI-ARIA Tabs design patterns.

```html
<jw-tabs aria-label="Course Learning Options">
  <jw-tab label="Overview">
    <h2>Overview</h2>
    <p>Explore the fundamental principles of accessibility.</p>
  </jw-tab>
  <jw-tab label="Key Features">
    <h2>Key Features</h2>
    <p>Screen reader support, keyboard focus trapping, and ARIA roles.</p>
  </jw-tab>
  <jw-tab label="Resources">
    <h2>Resources</h2>
    <p>Download cheatsheets and documentation guides.</p>
  </jw-tab>
</jw-tabs>
```

---

### 2.3 Flip Card Component (`<jw-flip-card>`)
Creates interactive flashcards with accessible front/back reveal actions for self-assessment.

```html
<jw-flip-card 
  front="What does ARIA stand for?" 
  back="Accessible Rich Internet Applications. It provides semantics for assistive technology.">
</jw-flip-card>
```

---

### 2.4 Click-to-Reveal Component (`<jw-click-reveal>`)
Creates expandable solution reveal boxes with automatic xAPI event delegation.

```html
<jw-click-reveal 
  button-text="Reveal Sample Answer" 
  hint="Try answering before revealing the solution.">
  <p><strong>Sample Answer:</strong> WCAG 2.2 AA requires a minimum color contrast ratio of 4.5:1 for normal text.</p>
</jw-click-reveal>
```

---

### 2.5 Accessible Modal Dialog (`<jw-modal>`)
Triggers a WCAG 2.2 AA compliant modal dialog with focus trapping, <kbd>Escape</kbd> key closing, and automatic focus restoration.

```html
<jw-modal 
  trigger-text="View Accessibility Case Study" 
  title="Case Study: Screen Reader Audit Results">
  <p>In our 2026 audit, replacing custom span buttons with native <code>&lt;button&gt;</code> elements improved screen reader completion rates by 42%.</p>
</jw-modal>
```

---

### 2.6 Interactive Branching Scenario (`<jw-scenario>`)
Presents scenario-based decision trees for experiential learning.

```html
<jw-scenario title="Customer Accessibility Request">
  <p>A user requests closed captions for an embedded video. What should you do first?</p>
  <button type="button" class="scenario-choice" data-next="option-a">A. Provide automated captions immediately without manual review.</button>
  <button type="button" class="scenario-choice" data-next="option-b">B. Review captions for 99%+ accuracy and synchronized timing.</button>
</jw-scenario>
```

---

### 2.7 Interactive Timeline (`<jw-timeline>`)
Renders chronological events with keyboard navigation.

```html
<jw-timeline title="History of WCAG Standards">
  <div data-year="1999" data-title="WCAG 1.0">First web accessibility guidelines published by W3C.</div>
  <div data-year="2008" data-title="WCAG 2.0">Introduced POUR principles (Perceivable, Operable, Understandable, Robust).</div>
  <div data-year="2018" data-title="WCAG 2.1">Added mobile accessibility and low-vision criteria.</div>
  <div data-year="2023" data-title="WCAG 2.2">Added target size and focus appearance enhancements.</div>
</jw-timeline>
```

---

### 2.8 Interactive Multi-Step Wizard (`<jw-wizard>`)
Presents step-by-step instructions or multi-stage tasks.

```html
<jw-wizard title="Course Setup Wizard">
  <div data-step="Step 1: Planning">Define your learning objectives and course structure.</div>
  <div data-step="Step 2: Content Creation">Use JW Components to build interactive HTML fragments.</div>
  <div data-step="Step 3: Packaging">Use /packager.php to generate your 1-click LMS ZIP package.</div>
</jw-wizard>
```

---

### 2.9 Accessible Progress Bar (`<jw-progress-bar>`)
Renders accessible `role="progressbar"` status indicators.

```html
<jw-progress-bar value="75" max="100" label="Module Progress"></jw-progress-bar>
```

---

### 2.10 Multi-Column Grid (`<jw-multi-column>`)
Ensures accessible reading order across multi-column layouts.

```html
<jw-multi-column>
  <jw-column title="Phase 1: Preparation">
    <p>Outline lesson concepts and assets.</p>
  </jw-column>
  <jw-column title="Phase 2: Execution">
    <p>Generate HTML fragments block-by-block.</p>
  </jw-column>
</jw-multi-column>
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

### 2.12 Client-Side LC-JSON Quiz Engine Component (`<jw-quiz>`)
Dynamically fetches, renders, scores, and emits xAPI analytics for LC-JSON 1.0 `QuestionSet` and `Course` documents. Supports external JSON files (`src`), inline JSON payload attributes (`data-json`), or embedded `<script type="application/json">` blocks.

```html
<!-- External File Source -->
<jw-quiz src="quizzes/accessibility-assessment.lc.json"></jw-quiz>

<!-- Inline JSON Payload Script Tag -->
<jw-quiz>
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
</jw-quiz>
```

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
