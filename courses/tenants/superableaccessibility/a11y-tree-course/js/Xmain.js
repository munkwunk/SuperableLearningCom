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
    
    const pageTitles = [ 'Welcome', 'Module 1', 'Module 2', 'Module 3', 'Module 4' ];

    async function loadPage(pageIndex) {
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
                mermaid.initialize({ startOnLoad: false, theme: 'base', themeVariables: {
                    primaryColor: '#F9F9F7', primaryTextColor: '#1F1F1F', primaryBorderColor: '#5F8F6B',
                    lineColor: '#3B7A57', secondaryColor: '#5F8F6B', tertiaryColor: '#fff'
                }});
                mermaid.init(undefined, courseContent.querySelectorAll('.mermaid'));
            }
            if (pageIndex === 4) initModule4();

            updateNavigation();
            updateTOC();
        } catch (error) {
            console.error('Failed to load page:', error);
            courseContent.innerHTML = `<p class="text-center">Error: Could not load content.</p>`;
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
        const modal = document.getElementById('code-modal');
        const mainContentArea = document.querySelector('main');
        const openBtn = document.getElementById('reveal-btn');
        const closeBtn = document.getElementById('close-modal-btn');
        let lastFocusedElement;

        const openModal = () => {
            lastFocusedElement = document.activeElement;
            modal.classList.remove('hidden');
            mainContentArea.setAttribute('aria-hidden', 'true');
            closeBtn.focus();
            document.addEventListener('keydown', handleKeydown);
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            mainContentArea.removeAttribute('aria-hidden');
            if (lastFocusedElement) lastFocusedElement.focus();
            document.removeEventListener('keydown', handleKeydown);
        };

        const handleKeydown = (e) => {
            if (e.key === 'Escape') closeModal();
        };

        openBtn?.addEventListener('click', openModal);
        closeBtn?.addEventListener('click', closeModal);
    }

    function initModule4() {
        const quizForm = document.getElementById('quiz-form');
        const modal = document.getElementById('quiz-results-modal');
        const mainContentArea = document.querySelector('main');
        const scoreMessage = document.getElementById('quiz-score-message');
        const actionBtn = document.getElementById('quiz-modal-action-btn');
        const modalTitle = document.getElementById('quiz-results-title');
        let lastFocusedElement;
        
        const correctAnswers = { q1: 'false', q2: 'a', q3: 'd', q4: 'b', q5: 'b' };
        
        const openModal = () => {
            lastFocusedElement = document.activeElement;
            modal.classList.remove('hidden');
            mainContentArea.setAttribute('aria-hidden', 'true');
            modalTitle.setAttribute('tabindex', '-1');
            modalTitle.focus();
            document.addEventListener('keydown', handleKeydown);
        };

        const closeModal = () => {
            modal.classList.add('hidden');
            mainContentArea.removeAttribute('aria-hidden');
            if (lastFocusedElement) lastFocusedElement.focus();
            document.removeEventListener('keydown', handleKeydown);
        };
        
        const handleKeydown = (e) => {
            if (e.key === 'Escape') closeModal();
        };

        quizForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(quizForm);
            let score = 0;
            for (const [key, value] of formData.entries()) {
                if (correctAnswers[key] === value) {
                    score++;
                }
            }
            
            const percentage = (score / Object.keys(correctAnswers).length) * 100;
            const passed = percentage >= 80;

            modalTitle.textContent = passed ? 'Congratulations, You Passed!' : 'Keep Trying!';
            scoreMessage.textContent = `You scored ${percentage}%.`;

            if (passed) {
                actionBtn.textContent = 'Continue';
                actionBtn.onclick = closeModal;
            } else {
                actionBtn.textContent = 'Try Again';
                actionBtn.onclick = () => {
                    closeModal();
                    quizForm.reset();
                    quizForm.querySelector('input, button, select, textarea').focus();
                };
            }
            openModal();
        });
    }

    // Initialize the first page
    loadPage(0);

    // Event listeners for global navigation
    prevBtn?.addEventListener('click', () => {
        if (currentPageIndex > 1) loadPage(currentPageIndex - 1);
    });

    nextBtn?.addEventListener('click', () => {
        if (currentPageIndex < totalModules) loadPage(currentPageIndex + 1);
    });

    tocNav?.addEventListener('click', (e) => {
        if (e.target.matches('.toc-link')) {
            const index = parseInt(e.target.dataset.index, 10);
            loadPage(index);
        }
    });
});

