document.addEventListener('DOMContentLoaded', () => {
    const mainContentArea = document.querySelector('main');
    const courseContent = document.getElementById('course-content');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const progressIndicator = document.getElementById('progress-indicator');
    const courseFooter = document.querySelector('.course-footer');
    const tocNav = document.getElementById('toc-nav');

    const totalModules = 4;
    let currentPageIndex = 0;

    const pageTitles = ['Welcome', 'Module 1', 'Module 2', 'Module 3', 'Module 4'];

    // Initialize xAPI
    if (window.xapiService) {
        window.xapiService.init();
    }

    // Stop any speech synthesis when the page unloads/navigates away
    window.addEventListener('beforeunload', () => {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    });

    async function loadPage(pageIndex) {
        // Cancel any ongoing speech before loading a new page
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }

        currentPageIndex = pageIndex;
        const fileName = pageIndex === 0 ? 'welcome' : `module${pageIndex}`;
        try {
            const response = await fetch(`modules/${fileName}.html`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            courseContent.innerHTML = await response.text();

            const mainHeading = courseContent.querySelector('h1');
            if (mainHeading) {
                mainHeading.setAttribute('tabindex', '-1');
                mainHeading.focus();
            }

            // Page-specific initializations
            if (pageIndex === 0) initWelcomePage();
            if (pageIndex === 1) initModule1();
            if (pageIndex === 3 && typeof mermaid !== 'undefined') {
                mermaid.initialize({
                    startOnLoad: false, theme: 'base', themeVariables: {
                        primaryColor: '#F9F9F7', primaryTextColor: '#1F1F1F', primaryBorderColor: '#5F8F6B',
                        lineColor: '#3B7A57', secondaryColor: '#5F8F6B', tertiaryColor: '#fff'
                    }
                });
                mermaid.init(undefined, courseContent.querySelectorAll('.mermaid'));
            }
            if (pageIndex === 4) initModule4();

            updateNavigation();
            updateTOC();

            // Track page view
            if (window.xapiService) {
                const title = pageTitles[pageIndex] || `Module ${pageIndex}`;
                window.xapiService.sendStatement(
                    window.xapiService.verbs.experienced,
                    window.xapiService.getPageObject(pageIndex, title)
                );
            }

            // Track link clicks and configure accessibility for external links
            courseContent.querySelectorAll('a').forEach(link => {
                const href = link.getAttribute('href') || '';
                const isLearningLink = href.includes('content.buildxcl.com');
                const isExternal = href.startsWith('http') && !href.startsWith(window.location.origin);
                
                // Process link for new tab opening with accessibility attributes
                if (isLearningLink || isExternal || link.getAttribute('target') === '_blank') {
                    link.setAttribute('target', '_blank');
                    link.setAttribute('rel', 'noopener noreferrer');
                    
                    const hasSrText = link.querySelector('.sr-only') && 
                                      link.querySelector('.sr-only').textContent.toLowerCase().includes('new tab');
                    
                    if (!hasSrText) {
                        const srSpan = document.createElement('span');
                        srSpan.className = 'sr-only';
                        srSpan.textContent = ' (opens in a new tab)';
                        link.appendChild(srSpan);
                        
                        const ariaLabel = link.getAttribute('aria-label');
                        if (ariaLabel && !ariaLabel.toLowerCase().includes('new tab')) {
                            link.setAttribute('aria-label', `${ariaLabel} (opens in a new tab)`);
                        }
                    }
                }

                link.addEventListener('click', () => {
                    if (window.xapiService) {
                        window.xapiService.sendStatement(
                            window.xapiService.verbs.experienced,
                            {
                                id: link.href,
                                definition: {
                                    name: { "en-US": link.textContent.trim() },
                                    type: "http://adlnet.gov/expapi/activities/link"
                                }
                            }
                        );
                    }
                });
            });
        } catch (error) {
            console.error('Failed to load page:', error);
            courseContent.innerHTML = `<p class="center-text">Error: Could not load content.</p>`;
        }
    }

    function updateNavigation() {
        courseFooter.hidden = currentPageIndex === 0;
        if (currentPageIndex > 0) {
            progressIndicator.textContent = `Module ${currentPageIndex} of ${totalModules}`;
            prevBtn.disabled = currentPageIndex === 1;
            nextBtn.disabled = currentPageIndex === totalModules;
        }
    }

    function updateTOC() {
        tocNav.innerHTML = pageTitles.map((title, index) => {
            const shortTitle = index === 0 ? 'Home' : `Module ${index}`;
            const isActive = index === currentPageIndex;
            return `<button class="toc-link ${isActive ? 'active' : ''}" data-index="${index}" aria-current="${isActive ? 'page' : 'false'}">${shortTitle}</button>`;
        }).join('');
    }

    function initWelcomePage() {
        document.getElementById('start-course-btn')?.addEventListener('click', () => loadPage(1));
    }

    function initModule1() {
        // --- Modal Logic ---
        const modal = document.getElementById('code-modal');
        const openBtns = document.querySelectorAll('.reveal-trigger-btn');
        const closeBtn = document.getElementById('close-modal-btn');
        const modalTitle = document.getElementById('modal-title');
        let lastFocusedElement;

        function trapFocus(e) {
            const focusableElements = modal.querySelectorAll('h3[tabindex="-1"], button');
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.key === 'Tab') {
                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        }

        const openModal = (e) => {
            const btnType = e.currentTarget.dataset.type;
            const headingContent = document.getElementById('modal-content-heading');
            const buttonContent = document.getElementById('modal-content-button');

            // Track interaction
            if (window.xapiService) {
                const name = btnType === 'button' ? 'Reveal Code: Button Structure' : 'Reveal Code: Heading Comparison';
                window.xapiService.sendStatement(
                    window.xapiService.verbs.interacted,
                    window.xapiService.getInteractionObject(1, `reveal-code-${btnType}`, name)
                );
            }

            // Toggle content based on button type
            if (btnType === 'button') {
                headingContent.classList.add('hidden');
                buttonContent.classList.remove('hidden');
            } else {
                // Default to heading content (or strictly check for 'heading')
                headingContent.classList.remove('hidden');
                buttonContent.classList.add('hidden');
            }

            lastFocusedElement = document.activeElement;
            modal.classList.remove('hidden');
            mainContentArea.setAttribute('aria-hidden', 'true');
            modalTitle.focus();
            document.addEventListener('keydown', handleKeydown);
            modal.addEventListener('keydown', trapFocus);
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            mainContentArea.removeAttribute('aria-hidden');
            if (lastFocusedElement) lastFocusedElement.focus();
            document.removeEventListener('keydown', handleKeydown);
            modal.removeEventListener('keydown', trapFocus);
        };

        const handleKeydown = (e) => {
            if (e.key === 'Escape') closeModal();
        };

        openBtns.forEach(btn => {
            btn.addEventListener('click', openModal);
        });
        closeBtn?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // --- TTS Simulation Logic ---
        const playBtns = document.querySelectorAll('.tts-play-btn');

        if ('speechSynthesis' in window) {
            playBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const textToSpeak = btn.dataset.text;

                    // Track interaction
                    if (window.xapiService) {
                        const isSemantic = textToSpeak.includes('Heading Level');
                        const name = isSemantic ? 'Play TTS: Semantic' : 'Play TTS: Non-Semantic';
                        window.xapiService.sendStatement(
                            window.xapiService.verbs.interacted,
                            window.xapiService.getInteractionObject(1, `tts-${isSemantic ? 'semantic' : 'non-semantic'}`, name)
                        );
                    }

                    const utterance = new SpeechSynthesisUtterance(textToSpeak);
                    window.speechSynthesis.cancel(); // Stop any previous speech
                    window.speechSynthesis.speak(utterance);
                });
            });
        } else {
            document.querySelector('.tts-container')?.classList.add('hidden');
        }
    }

    function initModule4() {
        const quizForm = document.getElementById('quiz-form');
        const modal = document.getElementById('quiz-results-modal');
        const closeBtn = document.getElementById('quiz-close-modal-btn');
        const modalTitle = document.getElementById('quiz-modal-title');
        const modalFeedback = document.getElementById('quiz-modal-feedback');
        const modalActionBtn = document.getElementById('quiz-modal-action-btn');
        let lastFocusedElement;

        const correctAnswers = ['false', 'a', 'd', 'b', 'b'];

        function trapFocus(e) {
            const focusableElements = modal.querySelectorAll('h3[tabindex="-1"], button');
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            if (e.key === 'Tab') {
                if (e.shiftKey && document.activeElement === firstElement) { e.preventDefault(); lastElement.focus(); }
                else if (!e.shiftKey && document.activeElement === lastElement) { e.preventDefault(); firstElement.focus(); }
            }
        }

        const openModal = () => {
            lastFocusedElement = document.activeElement;
            modal.classList.remove('hidden');
            mainContentArea.setAttribute('aria-hidden', 'true');
            modalTitle.focus();
            document.addEventListener('keydown', handleKeydown);
            modal.addEventListener('keydown', trapFocus);
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            mainContentArea.removeAttribute('aria-hidden');
            if (lastFocusedElement) lastFocusedElement.focus();
            document.removeEventListener('keydown', handleKeydown);
            modal.removeEventListener('keydown', trapFocus);
        };

        const handleKeydown = (e) => {
            if (e.key === 'Escape') closeModal();
        };

        quizForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(quizForm);
            let score = 0;

            for (let i = 0; i < correctAnswers.length; i++) {
                const questionNum = i + 1;
                const userAnswer = formData.get(`q${questionNum}`);
                const isCorrect = userAnswer === correctAnswers[i];

                if (isCorrect) {
                    score++;
                }

                // Track individual question answer
                if (window.xapiService) {
                    window.xapiService.sendStatement(
                        window.xapiService.verbs.answered,
                        window.xapiService.getQuestionObject(4, questionNum, `Question ${questionNum}`),
                        {
                            response: userAnswer,
                            success: isCorrect
                        }
                    );
                }
            }

            const percentage = (score / correctAnswers.length) * 100;

            if (percentage >= 80) {
                modalTitle.textContent = "Congratulations, you passed!";
                modalFeedback.textContent = `You scored ${percentage}%. You have a great foundational knowledge of accessibility.`;
                modalActionBtn.textContent = "Continue";
                modalActionBtn.onclick = () => { closeModal(); nextBtn.focus(); };
            } else {
                modalTitle.textContent = "Not Quite!";
                modalFeedback.textContent = `You scored ${percentage}%. Why not try again to solidify your knowledge?`;
                modalActionBtn.textContent = "Try Again";
                modalActionBtn.textContent = "Try Again";
                modalActionBtn.onclick = () => { closeModal(); quizForm.reset(); quizForm.querySelector('input').focus(); };
            }

            // Track Quiz Result
            if (window.xapiService) {
                const verb = percentage >= 80 ? window.xapiService.verbs.passed : window.xapiService.verbs.failed;
                const result = {
                    score: {
                        scaled: percentage / 100,
                        raw: score,
                        min: 0,
                        max: correctAnswers.length
                    },
                    success: percentage >= 80
                };
                window.xapiService.sendStatement(verb, window.xapiService.getCourseObject(), result);

                if (percentage >= 80) {
                    window.xapiService.sendStatement(window.xapiService.verbs.completed, window.xapiService.getCourseObject());
                }
            }
            openModal();
        });

        closeBtn?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    // Dynamic event delegation for TOC
    tocNav.addEventListener('click', (e) => {
        if (e.target.matches('.toc-link')) {
            const pageIndex = parseInt(e.target.dataset.index, 10);
            if (pageIndex !== currentPageIndex) {
                loadPage(pageIndex);
            }
        }
    });

    // Navigation button listeners
    prevBtn.addEventListener('click', () => {
        if (currentPageIndex > 1) loadPage(currentPageIndex - 1);
    });
    nextBtn.addEventListener('click', () => {
        if (currentPageIndex < totalModules) loadPage(currentPageIndex + 1);
    });

    // Initial Load
    loadPage(0);
});

