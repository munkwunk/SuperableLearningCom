# Superable Learning LMS — LC-JSON 1.0 Specification Integration Guide

> **Technical Guide & Specification Reference**: Complete developer and course author guide for using **LC-JSON (Learning Content JSON)** standard documents inside Superable Learning LMS.

---

## 1. Executive Overview

Superable Learning LMS provides **full Phase 1 & Phase 2 native support** for the **LC-JSON 1.0 Specification** ([github.com/lc-json/specification](https://github.com/lc-json/specification)):

* **Phase 1 (Server-Side Conversion Engine)**: Automatically inspects uploaded `.zip` or `.json` packages, detects LC-JSON `Course` and `QuestionSet` documents, and converts them into 100% WCAG 2.2 AA compliant Superable Learning course packages ([lc_json_converter.php](file:///C:/Users/jacob/projects/superablelearning.com/lc_json_converter.php)).
* **Phase 2 (Client-Side `<jw-quiz>` Web Component)**: A native Web Component (`<jw-quiz>`) built directly into [assets/components/jw-components.js](file:///C:/Users/jacob/projects/superablelearning.com/assets/components/jw-components.js) that dynamically fetches, renders, scores, and emits xAPI statement tracking for LC-JSON files live in the browser.

---

## 2. Supported LC-JSON Artifact Types

Superable Learning LMS supports both top-level LC-JSON artifact types:

1. **`Course` Artifact**:
   - Represents hierarchical learning content: `Course` $\rightarrow$ `Units` $\rightarrow$ `Lessons` $\rightarrow$ `Items` $\rightarrow$ `Questions`.
   - Converted into Superable module groups and individual HTML module fragment files.

2. **`QuestionSet` Artifact**:
   - Represents a flat list of assessment items or question banks.
   - Converted into an interactive assessment module with dynamic scoring, ARIA live region announcements, and xAPI tracking hooks.

---

## 3. Supported LC-JSON Question Types

The `LCJsonConverter` engine automatically converts the following LC-JSON question types into WCAG 2.2 AA compliant interactive HTML widgets:

| LC-JSON Question Type | UI Widget Rendered | Accessibility & ARIA Features |
| :--- | :--- | :--- |
| `multipleChoice` | Radio button or Checkbox list | `<fieldset>`, `<legend>`, high-contrast `:focus-visible` rings, ARIA live region feedback |
| `trueFalseQuestion` | Binary choice action buttons | Single source of truth scoring (`correctAnswer: true/false`), ARIA status announcement |
| `simpleGapFill` | Fill-in-the-blank text inputs | Sentence containing `@@@` replaced with accessible `<input type="text">` |
| `wordBankCloze` | Passage with word bank pool | Styled word bank pool + inline inputs with gap feedback |
| `shortAnswer` / `essay` | Text input or `<textarea>` | Accessible labels, word count validation, and submission hooks |

---

## 4. Vendor Extension Properties (`x-superable-*`)

To preserve Superable Learning LMS-specific features (access control, asset manifests, custom page links) while remaining 100% compliant with standard LC-JSON schemas, use the `x-superable-` property namespace:

```json
{
  "$schema": "https://lc-json.org/1.0/schemas/course.schema.json",
  "documentType": "Course",
  "specVersion": "1.0",
  "title": "Accessible Web Patterns with LC-JSON",
  "description": "Mastering ARIA design patterns.",

  "x-superable-access": {
    "type": "protected",
    "teaser_link": "https://example.com/course-info"
  },
  "x-superable-assets": {
    "css": ["css/style.css"],
    "js": ["js/main.js"]
  },

  "units": [
    {
      "title": "Unit 1: Introduction",
      "lessons": [
        {
          "title": "Lesson 1: Getting Started",
          "x-superable-src": "modules/u1-l1.html",
          "body": "Welcome to the LC-JSON course!"
        }
      ]
    }
  ]
}
```

### Supported Extension Keys:
- `x-superable-access`: Sets access mode (`public`, `protected`, `teaser`, `hidden`) and `teaser_link`.
- `x-superable-assets`: Lists custom CSS (`css/style.css`) and JavaScript (`js/main.js`) files.
- `x-superable-src`: Explicitly specifies the relative HTML module filename to generate for a lesson.

---

## 5. Automated xAPI Statement Emission

All LC-JSON interactive question widgets rendered by Superable Learning LMS automatically emit xAPI analytics statements on student interaction.

When a student checks an answer, the client engine ([main.js](js/main.js)) fires a structured statement:

```javascript
window.xapi.sendStatement({
    verb: {
        id: "http://adlnet.gov/expapi/verbs/answered",
        display: { "en-US": "answered" }
    },
    object: {
        id: window.location.href + "#" + question.globalId,
        definition: {
            name: { "en-US": "LC-JSON Question (" + question.globalId + ")" }
        }
    },
    result: {
        score: { raw: earnedPoints, max: totalPoints },
        success: isCorrect
    }
});
```

---

## 6. How to Import an LC-JSON Course Package

1. Create or export your LC-JSON course file as `course.lc.json` or `course.json`.
2. Compress the file into a `.zip` archive (or place it alongside any images/assets).
3. Log into your Superable Learning **Tenant Admin Panel** (`/admin.php`).
4. Select **Upload New Course (ZIP)**, choose your archive, and click **Upload & Import Package**.
5. The system will automatically detect the LC-JSON format, execute security validation, generate `course_structure.json` and module HTML fragments, and register the course in your Tenant Library!

---

## 7. Dynamic Client-Side Quiz Component (`<jw-quiz>`)

For dynamic browser-side rendering without pre-converting files during upload, use the `<jw-quiz>` Web Component:

```html
<!-- Load LC-JSON file dynamically via URL -->
<jw-quiz src="quizzes/wcag-assessment.lc.json"></jw-quiz>

<!-- Embed inline LC-JSON QuestionSet -->
<jw-quiz>
  <script type="application/json">
    {
      "$schema": "https://lc-json.org/1.0/schemas/questionset.schema.json",
      "documentType": "QuestionSet",
      "title": "Interactive ARIA Check",
      "questions": [
        {
          "type": "trueFalseQuestion",
          "globalId": "550e8400-e29b-41d4-a716-446655440001",
          "prompt": "The aria-expanded attribute must be toggled dynamically on accordion triggers.",
          "correctAnswer": true,
          "points": 1.0
        }
      ]
    }
  </script>
</jw-quiz>
```

