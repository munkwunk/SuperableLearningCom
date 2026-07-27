# 📺 Technical Specification: Presenter Mode, Teleprompter-Sync & Multi-Screen Orchestration

This specification outlines the technical blueprint, API payloads, and design patterns required to implement the Presenter Console, Teleprompter Sync, and Multi-Screen Orchestration features in the Superable Learning LMS.

---

## 1. Architectural Blueprint & View Partitioning

To facilitate dual-screen presentation, `player.php` will support two query parameters:
*   `player.php?course_id=[id]&view=presentation` (Audience / Screen Share View)
*   `player.php?course_id=[id]&view=presenter` (Presenter Console View)

### A. Audience / Screen Share View (`?view=presentation`)
*   **Purpose**: This window is fullscreened and shared on Zoom/Teams/Meet.
*   **UI Constraints**:
    *   Hide all main LMS dashboard navigation headers, sidebar modules lists, and account settings.
    *   Only render the active course module content `<main id="course-content">` and a small, minimal "Presenter Connected" indicator.
    *   No scrollbars or player layout chrome.

### B. Presenter Console View (`?view=presenter`)
*   **Purpose**: Kept private on the presenter's monitor or secondary device.
*   **UI Layout**:
    *   **Left Column (Interactive Outline)**: Clickable thumbnails/list of all course modules.
    *   **Center Column (Teleprompter/Notes)**: A scrollable, high-readability text pane containing the slide speaker notes.
    *   **Right Column (Presentation Controls)**:
        *   Timer (elapsed time & countdown).
        *   "Next Slide" preview thumbnail.
        *   Typography scale buttons (`Zoom Text + / -` for reading aid, up to 400% scale).
        *   Toggle controls for Auto-Scroll and Voice-Activation.

---

## 2. Local Dual-Window Synchronization (Phase 2)

For same-device dual-monitor setups, communication must happen **offline with zero latency**. We utilize the browser's native `BroadcastChannel` API.

### A. Channel Initialization
Create a shared channel named `sl-presenter-sync`:
```javascript
const syncChannel = new BroadcastChannel('sl-presenter-sync');
```

### B. Message Payload Schema

#### 1. Slide Navigation Command
Fired when the presenter advances notes or clicks a module button:
```json
{
  "action": "goToModule",
  "moduleId": "pour-sorting-trap",
  "index": 2,
  "timestamp": 1721935821000
}
```

#### 2. Ping/Heartbeat Status
Fired every 5 seconds to verify connection states:
```json
{
  "action": "heartbeat",
  "role": "presenter",
  "activeModuleId": "welcome"
}
```

---

## 3. Teleprompter Sync & Scroll Triggering

To sync the slides with the presenter's reading progress, the notes document is structured with marker nodes, and scrolled positions are observed.

### A. Markup Structure in Notes
```html
<div class="presenter-notes">
    <p>Welcome everyone to this presentation.</p>
    
    <!-- Slide Trigger Marker -->
    <span class="slide-trigger" data-target-module="pour-sorting-trap"></span>
    
    <p>Let's talk about the POUR sorting trap...</p>
</div>
```

### B. Intersection Observer Triggering
In the Presenter View, we initialize an `IntersectionObserver` targetting a "Reading Zone" threshold situated at the upper 30% of the scroll container:
```javascript
const observerOptions = {
    root: document.querySelector('.presenter-notes-container'),
    rootMargin: '-20% 0px -70% 0px', // Reading focus hot-spot
    threshold: 0.1
};

const triggerObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const targetModuleId = entry.target.getAttribute('data-target-module');
            
            // Broadcast slide shift
            syncChannel.postMessage({
                action: "goToModule",
                moduleId: targetModuleId
            });
        }
    });
}, observerOptions);

document.querySelectorAll('.slide-trigger').forEach(trigger => {
    triggerObserver.observe(trigger);
});
```

---

## 4. Single-Screen Setup: Document Picture-in-Picture

For presenters running on a single laptop screen, we utilize the browser's **Document Picture-in-Picture API** to pop out the audience slides:

```javascript
async function enterPresenterMode() {
    // 1. Spawns a floating PiP browser window
    const pipWindow = await documentPictureInPicture.requestWindow({
        width: 800,
        height: 600,
    });
    
    // 2. Clone stylesheets to PiP window
    Array.from(document.styleSheets).forEach((styleSheet) => {
        try {
            const cssRules = Array.from(styleSheet.cssRules)
                .map((rule) => rule.cssText)
                .join('');
            const style = document.createElement('style');
            style.textContent = cssRules;
            pipWindow.document.head.appendChild(style);
        } catch (e) {
            // Fallback for cross-origin link sheets
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = styleSheet.href;
            pipWindow.document.head.appendChild(link);
        }
    });

    // 3. Move the slide content element into the floating window
    const presentationContent = document.getElementById('course-content');
    pipWindow.document.body.appendChild(presentationContent);
    
    // 4. Leave notes and controls in main browser window
    document.body.classList.add('presenter-console-only');
    
    // 5. Restore elements when PiP window is closed
    pipWindow.addEventListener("pagehide", () => {
        const contentArea = document.getElementById('main-content-layout');
        contentArea.appendChild(presentationContent);
        document.body.classList.remove('presenter-console-only');
    });
}
```

---

## 5. Advanced Enhancements (Phase 3)

### A. Voice-Activated Advancing (Web Speech API)
Runs **locally** inside the browser with zero cloud translation costs:
```javascript
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
if (SpeechRecognition) {
    const recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = false;
    recognition.lang = 'en-US';

    recognition.onresult = (event) => {
        const transcript = event.results[event.results.length - 1][0].transcript.toLowerCase();
        
        // Scan notes triggers for keyword matching
        document.querySelectorAll('.slide-trigger[data-keywords]').forEach(trigger => {
            const keywords = trigger.getAttribute('data-keywords').split(',');
            const matched = keywords.some(kw => transcript.includes(kw.trim().toLowerCase()));
            
            if (matched) {
                syncChannel.postMessage({
                    action: "goToModule",
                    moduleId: trigger.getAttribute('data-target-module')
                });
            }
        });
    };
    recognition.start();
}
```

### B. WebSockets Cross-Device Sync
When a VPS droplet is active, a Node.js socket server or PHP WebSocket daemon will route events between a mobile/tablet Presenter console and a laptop Audience view using room authentication:
```javascript
// Connect to VPS WebSocket Room
const socket = io('https://superablelearning.com:3000');
socket.emit('join-presentation-room', { 
    roomCode: "PRES-ACC-101", 
    role: "presenter" 
});

// Emitting trigger events
socket.emit('slide-event', {
    roomCode: "PRES-ACC-101",
    action: "goToModule",
    moduleId: "pour-sorting-trap"
});
```

---

## 6. Accessibility Integration Mandates

1.  **Independent Layout Scaling**: The presenter's font size adjustments must not affect the audience's window. Use CSS custom properties (`--presenter-notes-font-size`) scoped strictly to the Presenter layout.
2.  **ARIA Announcements**: The Presentation View must maintain active keyboard and focus order so that screen readers navigating the slide view receive focus-shift notifications immediately as modules swap.
3.  **Audience Live Link Sync (Accommodation)**: Blind users in the webinar can open `player.php?view=presentation` directly in their own local browsers. The server will sync their active window view with the presenter's active slide. This enables blind learners to consume semantic, accessible HTML slides under their own local screen reader controls in real-time, bypassing the flat, inaccessible screen share.
