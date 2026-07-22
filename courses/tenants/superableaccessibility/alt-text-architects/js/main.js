/**
 * Alt-Text Architect: The Sea Cucumber Chronicles
 * Game Engine logic
 */

const GameState = {
    currentLevel: 0,
    score: 0,
    scenarios: [
        {
            id: 1,
            title: "Level 1: The Decorative Graphic Dilemma",
            context: "The instructional designer is building an introductory layout titled 'The Mysterious Ocean Floor.' At the top of the screen, a cartoon sea cucumber wearing a tiny monocle sits on a line divider.",
            pageCopy: "The echinoderm family contains some of the ocean's most fascinating inhabitants.",
            imageAsset: {
                id: "decorative-monocle",
                type: "svg",
                render: () => `<svg viewBox="0 0 400 100" class="slide-asset" focusable="false" aria-hidden="true">
                    <line x1="10" y1="70" x2="390" y2="70" stroke="#D1D5DB" stroke-width="2" />
                    <rect x="175" y="40" width="50" height="30" rx="15" fill="#10B981" />
                    <circle cx="215" cy="50" r="3" fill="#111827" />
                    <circle cx="205" cy="50" r="3" fill="#111827" />
                    <circle cx="215" cy="50" r="8" fill="none" stroke="#F59E0B" stroke-width="2" />
                    <line x1="223" y1="50" x2="230" y2="70" stroke="#F59E0B" stroke-width="1" />
                </svg>`
            },
            options: [
                {
                    id: "A",
                    label: "Explicit Description",
                    code: 'alt="Cartoon sea cucumber wearing a monocle on a line"',
                    output: "Graphic: Cartoon sea cucumber wearing a monocle on a line",
                    status: "fail",
                    critique: "While descriptive, this graphic has no instructional value. Forcing the screen reader to announce it distracts from the primary lesson content."
                },
                {
                    id: "B",
                    label: "Element Classification",
                    code: 'alt="Decorative divider"',
                    output: "Graphic: Decorative divider",
                    status: "fail",
                    critique: "Labeling an element as 'decorative' defeats the purpose. It adds auditory clutter without clarifying the course content."
                },
                {
                    id: "C",
                    label: "Null Implementation",
                    code: 'alt=""',
                    output: "[Complete Silence — Focus passes seamlessly to the next heading element]",
                    status: "success",
                    critique: "Leaving the field entirely blank creates a silent image. Assistive technologies skip the asset smoothly, preserving the user's focus."
                }
            ]
        },
        {
            id: 2,
            title: "Level 2: The Redundant Information Trap",
            context: "A biographical card profiles a prominent marine researcher. The slide heading reads: 'Dr. Eleanor Vance, Pioneer of Holothurian Research.' Below the heading sits a photo of Dr. Vance holding a massive sea cucumber.",
            pageCopy: "Dr. Eleanor Vance spent forty years documenting deep-sea feeding habits.",
            imageAsset: {
                id: "researcher-profile",
                type: "svg",
                render: () => `<svg viewBox="0 0 300 300" class="slide-asset" role="img">
                    <rect x="10" y="10" width="280" height="280" fill="#F3F4F6" stroke="#D1D5DB" stroke-width="2" />
                    <polygon points="10,200 100,100 200,250 280,150 290,290 10,290" fill="#E5E7EB" />
                    <circle cx="150" cy="100" r="40" fill="#374151" />
                    <rect x="130" y="140" width="40" height="80" fill="#374151" />
                    <ellipse cx="150" cy="180" rx="60" ry="15" fill="#4B5563" transform="rotate(-10 150 180)" />
                </svg>`
            },
            options: [
                {
                    id: "A",
                    label: "Full Identification",
                    code: 'alt="Dr. Eleanor Vance holding a sea cucumber"',
                    output: "Graphic: Dr. Eleanor Vance holding a sea cucumber. Heading Level 2: Dr. Eleanor Vance...",
                    status: "fail",
                    critique: "The name is announced twice in a row. Since the layout text already identifies the researcher, the alt text introduces annoying redundancy."
                },
                {
                    id: "B",
                    label: "Generic Placement",
                    code: 'alt="Photograph of the researcher"',
                    output: "Graphic: Photograph of the researcher",
                    status: "fail",
                    critique: "Cleaner, but it states the obvious. A more specific contextual note improves reading efficiency."
                },
                {
                    id: "C",
                    label: "Supplemental Context",
                    code: 'alt="Holding a giant sea cucumber."',
                    output: "Graphic: Holding a giant sea cucumber.",
                    status: "success",
                    critique: "Because the page text already identifies the doctor, the alt text only provides the missing context: what she is doing in the photo. This creates a clean flow without repeating information."
                }
            ]
        },
        {
            id: 3,
            title: "Level 3: The Complex Data Matrix",
            context: "A technical screen explains 'The Sea Cucumber Defense Mechanism.' The asset is a complex flow chart mapping how a sea cucumber ejects its internal organs (cuvierian tubules) to confuse predators.",
            pageCopy: "Evisceration is a unique survival tactic.",
            imageAsset: {
                id: "defense-chart",
                type: "svg",
                render: () => `<svg viewBox="0 0 600 350" class="slide-asset" role="img">
                    <rect x="20" y="50" width="150" height="60" rx="5" fill="none" stroke="#EF4444" stroke-width="2" />
                    <text x="95" y="85" text-anchor="middle" font-size="12" fill="#1F2937">1. Predator Aggression</text>
                    
                    <line x1="170" y1="80" x2="220" y2="80" stroke="#6B7280" stroke-width="2" marker-end="url(#arrow)" />
                    
                    <rect x="225" y="50" width="150" height="60" rx="5" fill="none" stroke="#3B82F6" stroke-width="2" />
                    <text x="300" y="85" text-anchor="middle" font-size="12" fill="#1F2937">2. Tubule Ejection</text>
                    
                    <path d="M280,120 Q300,150 320,120 T360,120" fill="none" stroke="#D1D5DB" stroke-width="2" />
                    
                    <line x1="375" y1="80" x2="425" y2="80" stroke="#6B7280" stroke-width="2" marker-end="url(#arrow)" />
                    
                    <rect x="430" y="50" width="150" height="60" rx="5" fill="none" stroke="#10B981" stroke-width="2" />
                    <text x="505" y="85" text-anchor="middle" font-size="12" fill="#1F2937">3. Organ Regeneration</text>
                    
                    <defs>
                        <marker id="arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
                            <path d="M0,0 L0,6 L9,3 z" fill="#6B7280" />
                        </marker>
                    </defs>
                </svg>`
            },
            options: [
                {
                    id: "A",
                    label: "Text-Heavy Ingestion",
                    code: 'alt="Flowchart showing evisceration steps, chemical triggers, organ regeneration timelines, and predator distraction rates."',
                    output: "Graphic: Flowchart showing evisceration steps, chemical triggers...",
                    status: "fail",
                    critique: "Cramming a complex data chart into a standard alt string creates an unbroken wall of text. The screen reader user cannot easily pause, replay, or skim individual points."
                },
                {
                    id: "B",
                    label: "Absolute Omission",
                    code: 'alt=""',
                    output: "[Silence]",
                    status: "fail",
                    critique: "This completely hides critical instructional data that exists only inside the chart file, preventing the user from accessing the core lesson."
                },
                {
                    id: "C",
                    label: "Structured Association",
                    code: 'alt="Flowchart of the evisceration defense process. Fully described in the data table below."',
                    output: "Graphic: Flowchart of the evisceration defense process. Fully described in the data table below.",
                    status: "success",
                    critique: "A concise summary identifies the chart's purpose, while an accessible text breakdown or data table below gives the user full, structured access to the data."
                }
            ]
        },
        {
            id: 4,
            title: "Level 4: The Functional Interface Action",
            context: "A navigation panel contains a stylized image of a sea cucumber curled into the shape of a question mark. Clicking this image opens the course glossary popup.",
            pageCopy: "Stuck on terminology? Review our index.",
            imageAsset: {
                id: "glossary-icon",
                type: "svg",
                render: () => `<svg viewBox="0 0 80 80" class="slide-asset" role="img">
                    <path d="M20,30 C20,10 60,10 60,30 C60,45 40,40 40,55" fill="none" stroke="#06B6D4" stroke-width="8" stroke-linecap="round" />
                    <circle cx="40" cy="70" r="5" fill="#06B6D4" />
                </svg>`
            },
            options: [
                {
                    id: "A",
                    label: "Visual Description",
                    code: 'alt="Sea cucumber curled into a question mark"',
                    output: "Link, Graphic: Sea cucumber curled into a question mark",
                    status: "fail",
                    critique: "This describes what the image looks like, but fails to tell the user what the link actually does."
                },
                {
                    id: "B",
                    label: "Mixed Execution",
                    code: 'alt="Question mark icon that opens the glossary"',
                    output: "Link, Graphic: Question mark icon that opens the glossary",
                    status: "fail",
                    critique: "Clearer, but overly wordy. The screen reader already announces the element as a link, making 'icon that opens' redundant."
                },
                {
                    id: "C",
                    label: "Functional Label",
                    code: 'alt="Glossary"',
                    output: "Link, Graphic: Glossary",
                    status: "success",
                    critique: "For interactive elements, the text alternative must state the destination or function. The visual metaphor matters less than the action it performs."
                }
            ]
        },
        {
            id: 5,
            title: "Level 5: The Mood and Setting Exception",
            context: "The introductory title slide introduces a historic expedition: 'The 1873 HMS Challenger Expedition.' The background image is a classic oil painting setting a dramatic tone.",
            pageCopy: "The crew set out to map the unknown depths of the global oceans.",
            imageAsset: {
                id: "hms-challenger",
                type: "svg",
                render: () => `<svg viewBox="0 0 500 250" class="slide-asset" role="img">
                    <rect width="500" height="250" fill="#1E3A8A" />
                    <path d="M0,150 Q125,100 250,150 T500,150 V250 H0 Z" fill="#312E81" opacity="0.7" />
                    <path d="M0,200 Q125,180 250,220 T500,200 V250 H0 Z" fill="#1E3A8A" opacity="0.9" />
                    <rect x="240" y="80" width="20" height="60" fill="#E5E7EB" />
                    <rect x="210" y="90" width="15" height="50" fill="#E5E7EB" />
                    <rect x="275" y="90" width="15" height="50" fill="#E5E7EB" />
                    <circle cx="250" cy="220" r="10" fill="#34D399" opacity="0.5" filter="blur(2px)" />
                </svg>`
            },
            options: [
                {
                    id: "A",
                    label: "Null Implementation",
                    code: 'alt=""',
                    output: "[Silence]",
                    status: "fail",
                    critique: "Because the painting sets the mood and tone of the historical narrative, hiding it strips away part of the shared learning experience."
                },
                {
                    id: "B",
                    label: "Technical Inventory",
                    code: 'alt="An ocean painting with waves and a sea creature"',
                    output: "Graphic: An ocean painting with waves and a sea creature",
                    status: "fail",
                    critique: "Accurate, but dry. It misses the emotional impact and atmosphere the image is meant to convey."
                },
                {
                    id: "C",
                    label: "Evocative Context",
                    code: 'alt="Dramatic, stormy sea oil painting, setting a tense tone for the voyage."',
                    output: "Graphic: Dramatic, stormy sea oil painting, setting a tense tone for the voyage.",
                    status: "success",
                    critique: "When an image is used to establish mood or setting, the alt text should focus on that atmosphere. This ensures all learners share the same contextual framing."
                }
            ]
        }
    ],

    init() {
        console.log("Alt-Text Architect Engine Initialized");
        this.userSelections = {};
        this.shuffledOptionsPerLevel = {};
        this.renderLevel();
        
        // Enable Submit button when a radio option is selected
        document.addEventListener('change', (e) => {
            if (e.target.name === 'alt-text-option') {
                // Prevent change if the current level is already solved
                const solvedOptionId = this.userSelections[this.currentLevel];
                const isSolved = solvedOptionId !== undefined;
                if (isSolved) {
                    const solvedRadio = document.getElementById(`option-${solvedOptionId}`);
                    if (solvedRadio) {
                        solvedRadio.checked = true;
                    }
                    return;
                }

                const submitBtn = document.getElementById('submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            }
        });
        
        document.addEventListener('click', (e) => {
            if (e.target.id === 'submit-btn') {
                this.submitAnswer(e);
            } else if (e.target.id === 'prev-btn') {
                this.prevLevel();
            } else if (e.target.id === 'next-btn') {
                this.nextLevel();
            } else if (e.target.id === 'modal-close-btn' || e.target.id === 'modal-backdrop') {
                this.closeModal();
            }
        });
    },

    renderLevel() {
        // Ensure modal is closed and cleaned up when rendering a new level
        const modal = document.getElementById('feedback-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
        if (this.modalKeydownHandler) {
            document.removeEventListener('keydown', this.modalKeydownHandler);
            this.modalKeydownHandler = null;
        }

        const scenario = this.scenarios[this.currentLevel];
        
        // Update Header
        document.getElementById('current-level').textContent = this.currentLevel + 1;
        document.getElementById('progress-indicator').setAttribute('aria-valuenow', this.currentLevel + 1);
        
        // Render Slide
        const slideContent = document.getElementById('slide-content');
        slideContent.innerHTML = `
            <h3 id="slide-heading" tabindex="-1" style="outline: none;">${scenario.title}</h3>
            <div class="asset-container">
                ${scenario.imageAsset.render()}
            </div>
            <p>${scenario.pageCopy}</p>
        `;
        
        // Render Context
        document.getElementById('context-content').innerHTML = `
            <p>${scenario.context}</p>
        `;
        
        // Render Options
        const optionsContainer = document.getElementById('options-container');
        
        // Cache randomized option order to maintain consistency on back/forward review
        if (!this.shuffledOptionsPerLevel) {
            this.shuffledOptionsPerLevel = {};
        }
        if (!this.userSelections) {
            this.userSelections = {};
        }
        
        if (!this.shuffledOptionsPerLevel[this.currentLevel]) {
            this.shuffledOptionsPerLevel[this.currentLevel] = [...scenario.options].sort(() => Math.random() - 0.5);
        }
        const shuffledOptions = this.shuffledOptionsPerLevel[this.currentLevel];
        
        const solvedOptionId = this.userSelections[this.currentLevel];
        const isSolved = solvedOptionId !== undefined;
        
        optionsContainer.innerHTML = `
            <fieldset class="options-fieldset">
                <legend class="sr-only">Choose the best alt text option</legend>
                <div class="radio-group">
                    ${shuffledOptions.map(opt => {
                        const isChecked = isSolved && opt.id === solvedOptionId;
                        const isDisabled = isSolved;
                        return `
                            <label class="radio-option" for="option-${opt.id}">
                                <input type="radio" id="option-${opt.id}" name="alt-text-option" value="${opt.id}" class="radio-input"
                                    ${isChecked ? 'checked' : ''} ${isDisabled ? 'aria-disabled="true"' : ''}>
                                <div class="option-text">
                                    <span class="label">${opt.label}</span>
                                    <code class="code">${opt.code}</code>
                                </div>
                            </label>
                        `;
                    }).join('')}
                </div>
            </fieldset>
        `;
        
        // Submit Button State
        const submitBtn = document.getElementById('submit-btn');
        if (submitBtn) {
            submitBtn.disabled = !isSolved;
            if (isSolved) {
                submitBtn.disabled = true;
            }
        }
        
        // Navigation Buttons State
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        
        if (this.currentLevel > 0) {
            prevBtn.classList.remove('hidden');
        } else {
            prevBtn.classList.add('hidden');
        }
        
        if (isSolved) {
            nextBtn.classList.remove('hidden');
            if (this.currentLevel === this.scenarios.length - 1) {
                nextBtn.textContent = "Finish the Activity";
            } else {
                nextBtn.textContent = "Go to Next Task";
            }
        } else {
            nextBtn.classList.add('hidden');
        }
        
        // Reset Feedback
        const srEmulation = document.getElementById('sr-emulation');
        const critiqueArea = document.getElementById('diagnostic-critique');
        
        if (isSolved) {
            const solvedOption = scenario.options.find(o => o.id === solvedOptionId);
            srEmulation.textContent = solvedOption.output;
            critiqueArea.innerHTML = `<strong>Result: Correct</strong><br>${solvedOption.critique}`;
            critiqueArea.className = "critique-area success";
        } else {
            srEmulation.textContent = "Waiting for your choice...";
            critiqueArea.innerHTML = "";
            critiqueArea.className = "critique-area";
        }
        
        // Announce Level Change for Screen Readers
        srEmulation.textContent = `Task ${this.currentLevel + 1}: ${scenario.title}. Read the page details and make your choice.`;
    },

    submitAnswer(e) {
        const checkedRadio = document.querySelector('input[name="alt-text-option"]:checked');
        if (!checkedRadio) return;
        this.handleOptionSelect(checkedRadio.value, e);
    },

    handleOptionSelect(optionId, e) {
        const scenario = this.scenarios[this.currentLevel];
        const option = scenario.options.find(o => o.id === optionId);
        
        if (!option) return;
        
        // Send xAPI statement for answered option
        if (window.xapi) {
            const isCorrect = option.status === 'success';
            window.xapi.sendStatement(window.xapi.verbs.ANSWERED, {
                "id": `${window.xapi.courseId}/level/${this.currentLevel + 1}/option/${optionId}`,
                "definition": {
                    "name": { "en-US": `Question ${this.currentLevel + 1}: ${scenario.title}` },
                    "description": { "en-US": `User selected option: ${option.label} (${option.code})` },
                    "type": "http://adlnet.gov/expapi/activities/cmi.interaction"
                }
            }, {
                "success": isCorrect,
                "response": optionId
            });
        }
        
        if (option.status === 'success') {
            // Save correct selection and update score if not previously solved
            if (this.userSelections[this.currentLevel] === undefined) {
                this.score += 20;
                document.getElementById('current-score').textContent = this.score;
            }
            this.userSelections[this.currentLevel] = optionId;
            
            // Disable options and submit button
            const radioInputs = document.querySelectorAll('.radio-input');
            radioInputs.forEach(input => {
                input.setAttribute('aria-disabled', 'true');
            });
            document.getElementById('submit-btn').disabled = true;
            
            // Show Next navigation button
            const nextBtn = document.getElementById('next-btn');
            nextBtn.classList.remove('hidden');
            if (this.currentLevel === this.scenarios.length - 1) {
                nextBtn.textContent = "Finish the Activity";
            } else {
                nextBtn.textContent = "Go to Next Task";
            }
        }
        
        // Open the modal with feedback
        this.openModal(option, scenario, e);
    },

    openModal(option, scenario, e) {
        const modal = document.getElementById('feedback-modal');
        const modalContent = document.getElementById('modal-content-container');
        const modalBody = document.getElementById('modal-feedback-body');
        
        // Style content box according to outcome
        modalContent.className = 'modal-content';
        modalContent.classList.add(option.status);
        
        // Fill feedback info
        modalBody.innerHTML = `
            <div class="modal-feedback-result ${option.status}">
                <strong>Result: ${option.status === 'success' ? 'Correct' : 'Incorrect'}</strong>
            </div>
            <div class="modal-feedback-output" aria-label="What Screen Reader hears">
                <strong>Screen Reader Output:</strong> ${option.output}
            </div>
            <p>${option.critique}</p>
        `;
        
        // Position modal content where the user interacted to prevent scrolling/clipping bugs inside cross-origin iframes
        let topPos = 0;
        if (e && e.pageY) {
            // Center the modal content vertically around the click/activation coordinate
            topPos = Math.max(20, e.pageY - 150);
        } else {
            const scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
            topPos = Math.max(20, scrollY + 50);
        }
        
        // Position the modal wrapper as absolute container covering the full page
        modal.style.position = 'absolute';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100%';
        modal.style.height = 'auto';
        modal.style.minHeight = '100%';
        modal.style.display = 'flex';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'flex-start';
        modal.style.paddingTop = `${topPos}px`;

        // Show modal
        modal.classList.remove('hidden');
        
        // Trap focus setup
        this.focusedElementBeforeModal = document.activeElement;
        
        // Use a setTimeout to ensure the modal content is fully rendered in the DOM before focusing it
        setTimeout(() => {
            if (modalContent) {
                modalContent.focus();
            } else {
                const closeBtn = document.getElementById('modal-close-btn');
                if (closeBtn) closeBtn.focus();
            }
        }, 50);
        
        // Keyboard handler for focus trap and escape key
        this.modalKeydownHandler = (event) => {
            const isTabPressed = event.key === 'Tab' || event.keyCode === 9;
            const isEscPressed = event.key === 'Escape' || event.keyCode === 27;
            
            if (isEscPressed) {
                this.closeModal();
                event.preventDefault();
                return;
            }
            
            if (isTabPressed) {
                const focusableElements = modal.querySelectorAll('button, [tabindex]:not([tabindex="-1"])');
                const firstFocusable = focusableElements[0];
                const lastFocusable = focusableElements[focusableElements.length - 1];
                
                if (event.shiftKey) { // Shift + Tab
                    if (document.activeElement === firstFocusable || document.activeElement === modalContent) {
                        lastFocusable.focus();
                        event.preventDefault();
                    }
                } else { // Tab
                    if (document.activeElement === lastFocusable) {
                        firstFocusable.focus();
                        event.preventDefault();
                    }
                }
            }
        };
        
        document.addEventListener('keydown', this.modalKeydownHandler);
    },

    closeModal() {
        const modal = document.getElementById('feedback-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
        
        if (this.modalKeydownHandler) {
            document.removeEventListener('keydown', this.modalKeydownHandler);
            this.modalKeydownHandler = null;
        }
        
        // Return focus to the element active before modal (or Next button if solved and Submit is disabled)
        const solvedOptionId = this.userSelections[this.currentLevel];
        const isSolved = solvedOptionId !== undefined;
        
        if (isSolved) {
            const nextBtn = document.getElementById('next-btn');
            if (nextBtn && !nextBtn.classList.contains('hidden')) {
                nextBtn.focus();
            } else {
                const submitBtn = document.getElementById('submit-btn');
                if (submitBtn) submitBtn.focus();
            }
        } else {
            const submitBtn = document.getElementById('submit-btn');
            if (submitBtn) submitBtn.focus();
        }
        this.focusedElementBeforeModal = null;
    },

    prevLevel() {
        if (this.currentLevel > 0) {
            this.currentLevel--;
            this.renderLevel();
            setTimeout(() => {
                const heading = document.getElementById('slide-heading');
                if (heading) heading.focus();
            }, 50);
        }
    },

    nextLevel() {
        if (this.currentLevel < this.scenarios.length - 1) {
            this.currentLevel++;
            this.renderLevel();
            setTimeout(() => {
                const heading = document.getElementById('slide-heading');
                if (heading) heading.focus();
            }, 50);
        } else {
            // Activity Complete - Navigate to CTA module
            let parentLMS = false;
            try {
                if (window.parent && window.parent.LMS) {
                    parentLMS = true;
                }
            } catch (e) {
                // Cross-origin access blocked
            }

            if (parentLMS) {
                try {
                    const nextBtn = window.parent.document.getElementById('btn-next-module');
                    if (nextBtn) {
                        nextBtn.click();
                    }
                } catch (e) {
                    // Safe fallback
                    this.showCompletion();
                }
            } else {
                // Fallback for standalone / cross-origin iframe
                this.showCompletion();
            }
        }
    },

    showCompletion() {
        const gameContainer = document.getElementById('game-container');
        gameContainer.innerHTML = `
            <div class="completion-screen">
                <h2>Activity Complete!</h2>
                <p>You have finished all the tasks. You now have a better understanding of how to write alt text.</p>
                <div class="final-score">Total Points: ${this.score}/100</div>
                <p><em>Please proceed to the next module to finish the course.</em></p>
            </div>
        `;
        
        // Dispatch xAPI Completion via the global xapi service if available
        if (window.xapi) {
            window.xapi.sendStatement(window.xapi.verbs.COMPLETED, {
                "id": window.xapi.courseId,
                "definition": {
                    "name": { "en-US": "Alt-Text Architect: The Sea Cucumber Chronicles" },
                    "type": "http://adlnet.gov/expapi/activities/course"
                }
            }, {
                "score": { "scaled": this.score / 100, "raw": this.score },
                "completion": true,
                "success": true
            });
        }
    }
};

// Start the game when the DOM is ready
// Since modules are loaded dynamically, we check if the container exists
function initOnLoad() {
    if (document.getElementById('game-container')) {
        GameState.init();
    } else {
        // Retry or use a MutationObserver if needed, but the player usually injects then we run scripts
        setTimeout(initOnLoad, 100);
    }
}

initOnLoad();
