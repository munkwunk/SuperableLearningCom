/**
 * A11y Tree Course - LMS Integration Script
 * Uses event delegation to handle module-specific interactivity
 * without conflicting with the LMS player's navigation.
 */

(function() {
    const contentArea = document.getElementById('course-content');
    if (!contentArea) return;

    // Helper: Manage Aria Hidden for landmarks outside the modal
    function setLandmarksHidden(hidden) {
        const header = document.querySelector('header[role="banner"]');
        const sidebar = document.querySelector('aside.sidebar');
        const footer = document.getElementById('footer-placeholder');
        
        [header, sidebar, footer].forEach(el => {
            if (el) {
                if (hidden) el.setAttribute('aria-hidden', 'true');
                else el.removeAttribute('aria-hidden');
            }
        });
    }

    let lastFocusedElement;

    // 1. General Modal Logic (Reveal Code & Quiz Results)
    document.addEventListener('click', (e) => {
        // --- Reveal Code Modal (Module 1) ---
        if (e.target.classList.contains('reveal-trigger-btn')) {
            const btnType = e.target.dataset.type;
            const modal = document.getElementById('code-modal');
            const headingContent = document.getElementById('modal-content-heading');
            const buttonContent = document.getElementById('modal-content-button');
            const modalTitle = document.getElementById('modal-title');

            if (!modal) return;

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
                headingContent.classList.remove('hidden');
                buttonContent.classList.add('hidden');
            }

            lastFocusedElement = document.activeElement;
            modal.classList.remove('hidden');
            setLandmarksHidden(true);
            
            setTimeout(() => {
                modalTitle.setAttribute('tabindex', '-1');
                modalTitle.focus();
            }, 50);
        }

        // --- Close Logic for all Modals ---
        if (e.target.id === 'close-modal-btn' || e.target.id === 'code-modal' || e.target.id === 'quiz-close-modal-btn') {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
                setLandmarksHidden(false);
                if (lastFocusedElement) lastFocusedElement.focus();
            }
        }
    });

    // 2. Module 1: TTS Simulation
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('tts-play-btn')) {
            const textToSpeak = e.target.dataset.text;

            // Track interaction
            if (window.xapiService) {
                const isSemantic = textToSpeak.includes('Heading Level');
                const name = isSemantic ? 'Play TTS: Semantic' : 'Play TTS: Non-Semantic';
                window.xapiService.sendStatement(
                    window.xapiService.verbs.interacted,
                    window.xapiService.getInteractionObject(1, `tts-${isSemantic ? 'semantic' : 'non-semantic'}`, name)
                );
            }

            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(textToSpeak);
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(utterance);
            }
        }
    });

    // 3. Module 4: Quiz Logic
    document.addEventListener('submit', (e) => {
        if (e.target.id === 'quiz-form') {
            e.preventDefault();
            const quizForm = e.target;
            const formData = new FormData(quizForm);
            const correctAnswers = ['false', 'a', 'd', 'b', 'b'];
            let score = 0;

            for (let i = 0; i < correctAnswers.length; i++) {
                const questionNum = i + 1;
                const userAnswer = formData.get(`q${questionNum}`);
                const isCorrect = userAnswer === correctAnswers[i];
                if (isCorrect) score++;

                // Track individual question answer
                if (window.xapiService) {
                    window.xapiService.sendStatement(
                        window.xapiService.verbs.answered,
                        window.xapiService.getQuestionObject(4, questionNum, `Question ${questionNum}`),
                        {
                            response: userAnswer || '',
                            success: isCorrect
                        }
                    );
                }
            }

            const percentage = (score / correctAnswers.length) * 100;

            // Track Quiz Result (passed or failed) and Completion
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
            const modal = document.getElementById('quiz-results-modal');
            const modalTitle = document.getElementById('quiz-modal-title');
            const modalFeedback = document.getElementById('quiz-modal-feedback');
            const modalActionBtn = document.getElementById('quiz-modal-action-btn');

            if (!modal) {
                alert(`You scored ${percentage}%`);
                return;
            }

            lastFocusedElement = document.activeElement;

            if (percentage >= 80) {
                modalTitle.textContent = "Congratulations, you passed!";
                modalFeedback.textContent = `You scored ${percentage}%. You have a great foundational knowledge of accessibility.`;
                modalActionBtn.textContent = "Continue";
                modalActionBtn.onclick = () => { 
                    modal.classList.add('hidden');
                    setLandmarksHidden(false);
                    // Trigger the LMS player's mark complete logic
                    const completeBtn = document.getElementById('btn-mark-complete');
                    if (completeBtn) completeBtn.click();
                    else {
                        // If mark complete isn't there (maybe already completed), try next
                        document.getElementById('btn-next-module')?.click();
                    }
                };
            } else {
                modalTitle.textContent = "Not Quite!";
                modalFeedback.textContent = `You scored ${percentage}%. Why not try again to solidify your knowledge?`;
                modalActionBtn.textContent = "Try Again";
                modalActionBtn.onclick = () => { 
                    modal.classList.add('hidden');
                    setLandmarksHidden(false);
                    quizForm.reset();
                    // Return focus to first question
                    quizForm.querySelector('input')?.focus();
                };
            }

            modal.classList.remove('hidden');
            setLandmarksHidden(true);
            
            setTimeout(() => {
                modalTitle.setAttribute('tabindex', '-1');
                modalTitle.focus();
            }, 50);
        }
    });

    // 4. Mermaid Initialization (Observer for Module 3)
    const initMermaid = () => {
        if (document.querySelector('.mermaid')) {
            if (typeof mermaid !== 'undefined') {
                mermaid.initialize({
                    startOnLoad: false, theme: 'base', themeVariables: {
                        primaryColor: '#F9F9F7', primaryTextColor: '#1F1F1F', primaryBorderColor: '#5F8F6B',
                        lineColor: '#3B7A57', secondaryColor: '#5F8F6B', tertiaryColor: '#fff'
                    }
                });
                mermaid.init(undefined, document.querySelectorAll('.mermaid'));
            }
        }
    };

    // Run once on load and then observe for dynamic changes
    initMermaid();
    const observer = new MutationObserver(initMermaid);
    observer.observe(contentArea, { childList: true });

    // 5. ESC Key to close modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal:not(.hidden)');
            if (openModal) {
                openModal.classList.add('hidden');
                setLandmarksHidden(false);
                if (lastFocusedElement) lastFocusedElement.focus();
            }
        }
    });
})();
