## Architectural Specification: "Alt-Text Architect: The Sea Cucumber Chronicles"

This document establishes the production blueprint for an interactive, scenario-based simulation game. The module tests intermediate instructional designers on their ability to make strategic accessibility decisions based on page context rather than rigid rules.

---

## Technical & Interface Architecture

The game runs as a lightweight, client-side state machine. It uses semantic HTML structures and explicit ARIA live regions to demonstrate the direct consequences of code choices in real time.

```
+------------------------------------------------------------------------+
|                      [Course Header & Score State]                     |
+------------------------------------------------------------------------+
|                                                                        |
|  [Simulated Slide Environment]        [Context Analysis Panel]         |
|  Renders the mock course interface     Displays the structural intent, |
|  containing the sea cucumber asset.    surrounding text, and target    |
|                                        learning objective.             |
|                                                                        |
+------------------------------------------------------------------------+
|                                                                        |
|  [Interactive Decision Node]                                           |
|  Keyboard-navigable tactical options (HTML `<button>` elements).       |
|                                                                        |
+------------------------------------------------------------------------+
|                                                                        |
|  [Auditory Simulation Matrix]                                          |
|  Aria-live region mirroring screen reader output + diagnostic critique.|
|                                                                        |
+------------------------------------------------------------------------+

```

### Component & State Requirements

* **Centralized State Machine:** A unified data array manages the layout injections, target text nodes, validation logic, and diagnostic strings.
* **Screen Reader Emulation Node:** An explicit live region container (aria-live="polite") mirrors the exact auditory feedback an assistive technology user would receive based on the player's choice.
* **Accessibility Baseline:** Custom focus-visible indicators ensure smooth keyboard navigation, preventing focus traps across state transitions.

---

## Scenario Simulation Matrix

### Level 1: The Decorative Graphic Dilemma

* **Instructional Context:** The user is reviewing a layout titled *"The Mysterious Ocean Floor."* A cartoon sea cucumber wearing a tiny monocle sits on a horizontal line divider.
* **Surrounding Page Copy:** *"The echinoderm family contains some of the ocean's most fascinating inhabitants."*
* **Target Core Competency:** Differentiating between stylistic flair and instructional information to maintain user reading momentum.

#### Code Architecture Node & Simulation Output

| Option Selection | Applied Code Attribute | Simulated Screen Reader Delivery | Diagnostic Critique |
| --- | --- | --- | --- |
| **Option A:** Explicit Description | alt="Cartoon sea cucumber wearing a monocle on a line" | *"Graphic: Cartoon sea cucumber wearing a monocle on a line"* | **Fail.** While descriptive, this graphic has no instructional value. Forcing the screen reader to announce it distracts from the primary lesson content. |
| **Option B:** Element Classification | alt="Decorative divider" | *"Graphic: Decorative divider"* | **Fail.** Labeling an element as "decorative" defeats the purpose. It adds auditory clutter without clarifying the course content. |
| **Option C:** Null Implementation | alt="" | *[Complete Silence — Focus passes seamlessly to the next heading element]* | **Success!** Leaving the field entirely blank creates a silent image. Assistive technologies skip the asset smoothly, preserving the user's focus. |

---

### Level 2: The Redundant Information Trap

* **Instructional Context:** A biographical card profiles a prominent marine researcher. The slide heading reads: *"Dr. Eleanor Vance, Pioneer of Holothurian Research."* Below the heading sits a photo of Dr. Vance holding a massive sea cucumber.
* **Surrounding Page Copy:** *"Dr. Eleanor Vance spent forty years documenting deep-sea feeding habits."*
* **Target Core Competency:** Preventing audio duplication when visual context is already communicated by the adjacent layout text.

#### Code Architecture Node & Simulation Output

| Option Selection | Applied Code Attribute | Simulated Screen Reader Delivery | Diagnostic Critique |
| --- | --- | --- | --- |
| **Option A:** Full Identification | alt="Dr. Eleanor Vance holding a sea cucumber" | *"Graphic: Dr. Eleanor Vance holding a sea cucumber. Heading Level 2: Dr. Eleanor Vance..."* | **Fail.** The name is announced twice in a row. Since the layout text already identifies the researcher, the alt text introduces annoying redundancy. |
| **Option B:** Generic Placement | alt="Photograph of the researcher" | *"Graphic: Photograph of the researcher"* | **Partial Success.** Cleaner, but it states the obvious. A more specific contextual note improves reading efficiency. |
| **Option C:** Supplemental Context | alt="Holding a giant sea cucumber." | *"Graphic: Holding a giant sea cucumber."* | **Success!** Because the page text already identifies the doctor, the alt text only provides the missing context: what she is doing in the photo. This creates a clean flow without repeating information. |

---

### Level 3: The Complex Data Matrix

* **Instructional Context:** A technical screen explains *"The Sea Cucumber Defense Mechanism."* The asset is a complex flow chart mapping how a sea cucumber ejects its internal organs (cuvierian tubules) to confuse predators, complete with chemical triggers and organ regeneration timelines.
* **Surrounding Page Copy:** *"Evisceration is a unique survival tactic."*
* **Target Core Competency:** Managing complex instructional diagrams by linking short summaries to data-rich alternatives.

#### Code Architecture Node & Simulation Output

| Option Selection | Applied Code Attribute | Simulated Screen Reader Delivery | Diagnostic Critique |
| --- | --- | --- | --- |
| **Option A:** Text-Heavy Ingestion | alt="Flowchart showing evisceration steps, chemical triggers, organ regeneration timelines, and predator distraction rates." | *"Graphic: Flowchart showing evisceration steps, chemical triggers..."* | **Fail.** Cramming a complex data chart into a standard alt string creates an unbroken wall of text. The screen reader user cannot easily pause, replay, or skim individual points. |
| **Option B:** Absolute Omission | alt="" | *[Silence]* | **Fail.** This completely hides critical instructional data that exists only inside the chart file, preventing the user from accessing the core lesson. |
| **Option C:** Structured Association | alt="Flowchart of the evisceration defense process. Fully described in the data table below." + Data Table Link | *"Graphic: Flowchart of the evisceration defense process. Fully described in the data table below."* | **Success!** A concise summary identifies the chart's purpose, while an accessible text breakdown or data table below gives the user full, structured access to the data. |

---

### Level 4: The Functional Interface Action

* **Instructional Context:** A navigation panel at the end of a block contains a stylized image of a sea cucumber curled into the shape of a question mark. Clicking this image opens the course glossary popup.
* **Surrounding Page Copy:** *"Stuck on terminology? Review our index."*
* **Target Core Competency:** Ensuring interactive graphics prioritize action and function over visual descriptions.

#### Code Architecture Node & Simulation Output

| Option Selection | Applied Code Attribute | Simulated Screen Reader Delivery | Diagnostic Critique |
| --- | --- | --- | --- |
| **Option A:** Visual Description | alt="Sea cucumber curled into a question mark" | *"Link, Graphic: Sea cucumber curled into a question mark"* | **Fail.** This describes what the image looks like, but fails to tell the user what the link actually *does*. |
| **Option B:** Mixed Execution | alt="Question mark icon that opens the glossary" | *"Link, Graphic: Question mark icon that opens the glossary"* | **Partial Success.** Clearer, but overly wordy. The screen reader already announces the element as a link, making "icon that opens" redundant. |
| **Option C:** Functional Label | alt="Glossary" | *"Link, Graphic: Glossary"* | **Success!** For interactive elements, the text alternative must state the destination or function. The visual metaphor matters less than the action it performs. |

---

### Level 5: The Mood and Setting Exception

* **Instructional Context:** The introductory title slide introduces a historic expedition: *"The 1873 HMS Challenger Expedition."* The background image is a classic oil painting of a stormy ocean with a stylized, glowing sea cucumber hiding in the waves. The intent of the slide is to set a dramatic tone for the case study.
* **Surrounding Page Copy:** *"The crew set out to map the unknown depths of the global oceans."*
* **Target Core Competency:** Identifying when an image contributes to the emotional context or tone of a learning experience.

#### Code Architecture Node & Simulation Output

| Option Selection | Applied Code Attribute | Simulated Screen Reader Delivery | Diagnostic Critique |
| --- | --- | --- | --- |
| **Option A:** Null Implementation | alt="" | *[Silence]* | **Fail.** Because the painting sets the mood and tone of the historical narrative, hiding it strips away part of the shared learning experience. |
| **Option B:** Technical Inventory | alt="An ocean painting with waves and a sea creature" | *"Graphic: An ocean painting with waves and a sea creature"* | **Partial Success.** Accurate, but dry. It misses the emotional impact and atmosphere the image is meant to convey. |
| **Option C:** Evocative Context | alt="Dramatic, stormy sea oil painting, setting a tense tone for the voyage." | *"Graphic: Dramatic, stormy sea oil painting, setting a tense tone for the voyage."* | **Success!** When an image is used to establish mood or setting, the alt text should focus on that atmosphere. This ensures all learners share the same contextual framing. |

---

## Centralized Game State Engine Schema

This JSON schema drives the dynamic template injection inside your workspace engine.js script file.

```json
{
  "scenarios": [
    {
      "id": 1,
      "title": "Level 1: The Decorative Graphic Dilemma",
      "context": "The instructional designer is building an introductory layout titled 'The Mysterious Ocean Floor.' At the top of the screen, a cartoon sea cucumber wearing a tiny monocle sits on a line divider.",
      "pageCopy": "The echinoderm family contains some of the ocean's most fascinating inhabitants.",
      "imageAsset": {
        "url": "assets/monocle-cucumber.svg",
        "fallbackAlt": "Decorative element"
      },
      "options": [
        {
          "id": "A",
          "label": "Explicit Description",
          "code": "alt=\"Cartoon sea cucumber wearing a monocle on a line\"",
          "output": "Graphic: Cartoon sea cucumber wearing a monocle on a line",
          "status": "fail",
          "critique": "While descriptive, this graphic has no instructional value. Forcing the screen reader to announce it distracts from the primary lesson content."
        },
        {
          "id": "B",
          "label": "Element Classification",
          "code": "alt=\"Decorative divider\"",
          "output": "Graphic: Decorative divider",
          "status": "fail",
          "critique": "Labeling an element as 'decorative' defeats the purpose. It adds auditory clutter without clarifying the course content."
        },
        {
          "id": "C",
          "label": "Null Implementation",
          "code": "alt=\"\"",
          "output": "[Complete Silence]",
          "status": "success",
          "critique": "Leaving the field entirely blank creates a silent image. Assistive technologies skip the asset smoothly, preserving the user's focus."
        }
      ]
    }
  ]
}

```

<!-- Technical Details for Visual Assets -->
Here are the technical specifications and production prompts for your design document.

Since you are vibe-coding this natively with standard web technologies, these specifications are optimized for **Programmatic SVG Generation**. This keeps your assets lightweight, fully scalable, and embedded directly within your code base without requiring external file management.

---

## Visual Asset Production Specifications

The following blocks can be appended directly to your design document. They provide the precise geometric layouts, color hex codes, and structural components that your CLI tool can use to render the graphics cleanly.

### Level 1: The Decorative Graphic

* **Asset Name:** decorative-monocle-cucumber.svg
* **Visual Target:** A clean, minimalist, flat-vector cartoon sea cucumber sitting centered on a horizontal divider line. It wears an oversized, slightly absurd monocle.
* **Technical Dimensions:** viewBox="0 0 400 100"

#### Structural Prompts for Generation

> Generate a semantic, responsive inline SVG block. Create a horizontal divider line spanning the width of the canvas using a thin  element in a muted gray (#D1D5DB).
> Centered perfectly on top of this line, draw a cartoon sea cucumber using a smooth, rounded  or  with a high border-radius. Use a soft moss-green color (#4B5563 or #10B981).
> On the right side of the shape, add a simple face: two small dark circles for eyes, and an oversized gold circle (#F59E0B) with no fill and a thick stroke to represent a classic monocle, complete with a tiny gold chain hanging down toward the divider line.
> Ensure the root  contains focusable="false" and aria-hidden="true" as it is a decorative element.

---

### Level 2: The Redundant Information Card

* **Asset Name:** researcher-profile-card.svg
* **Visual Target:** A simulated black-and-white field photograph framing a marine scientist in gear, holding a large, distinct sea cucumber specimen.
* **Technical Dimensions:** viewBox="0 0 300 300"

#### Structural Prompts for Generation

> Generate a flat-design, stylized SVG illustration that mimics a framed photograph. Use a solid background rectangle in a light gray tone (#F3F4F6) with a clean border to establish the photo frame.
> Inside, use minimalist geometric shapes (triangles and polygons) in shades of gray to suggest a rugged coastline or research boat background.
> In the foreground, draw the silhouette or simple shape of a person wearing a distinct field hat or hood.
> Across the person’s hands, render a prominent, elongated, textured oblong shape in a darker contrast shade (#374151) to clearly represent a giant sea cucumber being held up for inspection.

---

### Level 3: The Complex Data Flowchart

* **Asset Name:** evisceration-defense-chart.svg
* **Visual Target:** A multi-step technical workflow diagram containing three distinct process nodes, directional arrows, a timeline scale, and text labels.
* **Technical Dimensions:** viewBox="0 0 600 350"

#### Structural Prompts for Generation

> Generate a highly structured, clean flowchart diagram entirely in native SVG.
> * **Node 1 (Left):** A bounding box labeled "1. Predator Aggression Trigger". Use a warning-colored border (#EF4444).
> * **Node 2 (Center):** A bounding box labeled "2. Cuvierian Tubules Ejection". Connect Node 1 to Node 2 with a crisp directional arrow line (#6B7280). Below this node, draw a small cluster of stylized, looping white/cream lines to represent the tubules.
> * **Node 3 (Right):** A bounding box labeled "3. Organ Regeneration Timeline (30-50 Days)". Connect Node 2 to Node 3 with a directional arrow.
> Use high-contrast text elements () inside or immediately adjacent to each node using standard system font stacks (e.g., Arial, sans-serif). Ensure all paths are clean, vector-snapped, and highly visible.
> 
> 

---

### Level 4: The Functional Interface Button

* **Asset Name:** glossary-button-icon.svg
* **Visual Target:** A stylized, friendly sea cucumber bent dynamically into the sharp shape of a question mark. It acts as an interactive button target.
* **Technical Dimensions:** viewBox="0 0 80 80"

#### Structural Prompts for Generation

> Generate an iconic, highly stylized inline SVG graphic to be nested inside an interactive HTML  element.
> The graphic must feature a single, continuous, thick vector path (stroke-width="8", stroke-linecap="round") in a vibrant teal blue (#06B6D4).
> Curvaceously shape this path so it forms the upper hook of a question mark. At the base, separate from the main body path, place a perfect circle () to serve as the bottom dot of the question mark.
> Keep the design completely clean, clear, and un-cluttered so it reads instantly as a functional icon even at small interface resolutions.

---

### Level 5: The Mood and Setting Canvas

* **Asset Name:** hms-challenger-mood.svg
* **Visual Target:** A moody, dramatic, deep-sea-toned abstract graphic that evokes a historical ocean voyage into the dark, unknown depths.
* **Technical Dimensions:** viewBox="0 0 500 250"

#### Structural Prompts for Generation

> Generate a dramatic, atmosphere-focused abstract vector graphic. Use a deep, dark oceanic color palette dominated by rich navy blues (#1E3A8A), deep indigos (#312E81), and dark teals.
> Implement a series of overlapping, semi-transparent layered waves using curved paths to create depth and a sense of a turbulent, heavy sea.
> In the upper third, add a faint, stylized silhouette of a multi-masted 19th-century research vessel (the HMS Challenger).
> In the deep lower third of the canvas, embed a faint, glowing, ethereal neon-green outline (#34D399) of a sea cucumber resting on the ocean floor to symbolize the mysterious deep-sea life waiting to be discovered. Keep the composition artistic, evocative, and narrative-focused.
