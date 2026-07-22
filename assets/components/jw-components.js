/**
 * JW Components Library
 * Framework-free, accessible components for e-learning.
 * WCAG 2.2 AA Compliant.
 */

/**
 * Global Announcement Utility
 * Provides a centralized way to send messages to a live region.
 */
window.jwAnnounce = function(message, priority = 'polite') {
    let liveRegion = document.getElementById('jw-live-region');
    if (!liveRegion) {
        liveRegion = document.createElement('div');
        liveRegion.id = 'jw-live-region';
        liveRegion.className = 'sr-only';
        liveRegion.setAttribute('aria-live', 'polite');
        document.body.appendChild(liveRegion);
    }
    
    // Set priority
    liveRegion.setAttribute('aria-live', priority);
    
    // Clear and set to trigger announcement
    liveRegion.textContent = '';
    setTimeout(() => {
        liveRegion.textContent = message;
    }, 100);
};

/**
 * JW Accordion Component
 * A robust, accessible accordion that follows the ARIA design pattern.
 */
class JWAccordion extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const items = Array.from(this.querySelectorAll('jw-accordion-item'));
        items.forEach((item, index) => {
            const headerId = `jw-accordion-header-${index}`;
            const panelId = `jw-accordion-panel-${index}`;
            
            const title = item.getAttribute('title') || 'Accordion Item';
            const expanded = item.hasAttribute('expanded');
            const level = this.getAttribute('level') || '3';
            const noRegion = this.hasAttribute('no-region');
            
            const originalContent = item.innerHTML;
            
            item.innerHTML = `
                <div class="jw-accordion-item-wrapper">
                    <h${level} class="jw-accordion-header">
                        <button type="button" 
                                id="${headerId}" 
                                aria-expanded="${expanded}" 
                                aria-controls="${panelId}" 
                                class="jw-accordion-trigger">
                            <span class="jw-accordion-title">${title}</span>
                            <span class="jw-accordion-icon" aria-hidden="true"></span>
                        </button>
                    </h${level}>
                    <div id="${panelId}" 
                         ${noRegion ? '' : 'role="region" aria-labelledby="' + headerId + '"'} 
                         class="jw-accordion-panel" 
                         ${expanded ? '' : 'hidden'}>
                        <div class="jw-accordion-content">
                            ${originalContent}
                        </div>
                    </div>
                </div>
            `;

            const button = item.querySelector('.jw-accordion-trigger');
            const panel = item.querySelector('.jw-accordion-panel');

            button.addEventListener('click', () => {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', !isExpanded);
                panel.hidden = isExpanded;

                if (window.jwAnnounce) {
                    window.jwAnnounce(!isExpanded ? `${title} expanded` : `${title} collapsed`, 'polite');
                }

                // Dispatch event for tracking
                this.dispatchEvent(new CustomEvent('jw-accordion-toggle', {
                    detail: { title, expanded: !isExpanded },
                    bubbles: true,
                    composed: true
                }));
            });
        });
    }
}

/**
 * JW Tabs Component
 * A robust, accessible tabs component following the ARIA design pattern.
 */
class JWTabs extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const tabList = Array.from(this.querySelectorAll('jw-tab'));
        const idBase = Math.random().toString(36).substr(2, 9);
        const label = this.getAttribute('aria-label') || 'Tabs';

        const tabListHtml = tabList.map((tab, index) => {
            const tabLabel = tab.getAttribute('label');
            const selected = index === 0;
            return `
                <button role="tab" 
                        aria-selected="${selected}" 
                        aria-controls="panel-${idBase}-${index}" 
                        id="tab-${idBase}-${index}" 
                        tabindex="${selected ? '0' : '-1'}">
                    ${tabLabel}
                </button>
            `;
        }).join('');

        const panelsHtml = tabList.map((tab, index) => {
            const selected = index === 0;
            return `
                <div role="tabpanel" 
                     id="panel-${idBase}-${index}" 
                     aria-labelledby="tab-${idBase}-${index}" 
                     ${selected ? '' : 'hidden'}
                     tabindex="0">
                    <div class="jw-tab-content">
                        ${tab.innerHTML}
                    </div>
                    <div class="sr-only" aria-hidden="false">End of tabbed content for ${tab.getAttribute('label') || 'this section'}.</div>
                </div>
            `;
        }).join('');

        this.innerHTML = `
            <div class="jw-tabs">
                <div role="tablist" aria-label="${label}" aria-orientation="horizontal">
                    ${tabListHtml}
                </div>
                ${panelsHtml}
            </div>
        `;

        const tabs = this.querySelectorAll('[role="tab"]');
        const panels = this.querySelectorAll('[role="tabpanel"]');

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => {
                this.switchTab(index, tabs, panels);
            });

            tab.addEventListener('keydown', (e) => {
                let newIndex = index;
                if (e.key === 'ArrowRight') {
                    newIndex = (index + 1) % tabs.length;
                } else if (e.key === 'ArrowLeft') {
                    newIndex = (index - 1 + tabs.length) % tabs.length;
                } else if (e.key === 'Home') {
                    newIndex = 0;
                } else if (e.key === 'End') {
                    newIndex = tabs.length - 1;
                }

                if (newIndex !== index) {
                    e.preventDefault();
                    tabs[newIndex].focus();
                    this.switchTab(newIndex, tabs, panels);
                }
            });
        });
    }

    switchTab(index, tabs, panels) {
        tabs.forEach((t, i) => {
            const selected = i === index;
            t.setAttribute('aria-selected', selected);
            t.setAttribute('tabindex', selected ? '0' : '-1');
            panels[i].hidden = !selected;
        });

        const activeLabel = tabs[index].textContent.trim();
        if (window.jwAnnounce) {
            window.jwAnnounce(`${activeLabel} tab selected`, 'polite');
        }

        // Dispatch event for tracking
        this.dispatchEvent(new CustomEvent('jw-tab-select', {
            detail: { label: activeLabel },
            bubbles: true,
            composed: true
        }));
    }
}

/**
 * JW Flip Card Component
 * An accessible flip card that uses a button to toggle state.
 */
class JWFlipCard extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        
        let frontContent = this.querySelector('jw-front')?.innerHTML || this.getAttribute('front') || '';
        let backContent = this.querySelector('jw-back')?.innerHTML || this.getAttribute('back') || '';
        
        if (!frontContent && !backContent && this.innerHTML.trim()) {
            frontContent = this.innerHTML;
            backContent = 'Card Back Content';
        }

        const customTitle = this.getAttribute('title');
        const regionLabel = customTitle ? `Interactive Flipcard: ${customTitle}` : 'Interactive Flipcard';

        this.innerHTML = `
            <div class="jw-flip-card" role="region" aria-label="${regionLabel}" style="border: 1.5px solid #cbd5e1; border-radius: 0.5rem; padding: 1.25rem; background: #ffffff; margin: 1.25rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                <div class="jw-flip-card-inner">
                    <!-- Front Face -->
                    <div class="jw-flip-card-front" role="group" aria-label="Front of Card" tabindex="-1">
                        <div class="jw-flip-card-header" aria-hidden="true" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <span class="jw-flip-card-badge" style="background: #e6fffa; color: #234e52; font-weight: bold; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 0.25rem; border: 1px solid #b2f5ea;">Front of Card</span>
                        </div>
                        <div class="jw-flip-card-content mb-3">${frontContent}</div>
                        <button type="button" class="jw-flip-card-trigger cta-button" aria-expanded="false" style="background: #319795; color: white; padding: 0.45rem 0.9rem; font-size: 0.85rem; font-weight: bold; border: none; border-radius: 0.25rem; cursor: pointer;" aria-label="Flip card to show back">
                            Flip to Back
                        </button>
                    </div>
                    
                    <!-- Back Face -->
                    <div class="jw-flip-card-back" role="group" aria-label="Back of Card" hidden tabindex="-1">
                        <div class="jw-flip-card-header" aria-hidden="true" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <span class="jw-flip-card-badge" style="background: #edf2f7; color: #2d3748; font-weight: bold; font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 0.25rem; border: 1px solid #cbd5e1;">Back of Card</span>
                        </div>
                        <div class="jw-flip-card-content mb-3">${backContent}</div>
                        <button type="button" class="jw-flip-card-trigger cta-button" aria-expanded="true" style="background: #4a5568; color: white; padding: 0.45rem 0.9rem; font-size: 0.85rem; font-weight: bold; border: none; border-radius: 0.25rem; cursor: pointer;" aria-label="Flip card to show front">
                            Flip to Front
                        </button>
                    </div>
                </div>
            </div>
        `;
        this.setAttribute('rendered', '');

        const triggers = this.querySelectorAll('.jw-flip-card-trigger');
        const front = this.querySelector('.jw-flip-card-front');
        const back = this.querySelector('.jw-flip-card-back');

        triggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                const isBackCurrentlyVisible = !back.hidden;
                
                // Toggle visibility
                back.hidden = isBackCurrentlyVisible;
                front.hidden = !isBackCurrentlyVisible;
                
                const targetSide = isBackCurrentlyVisible ? front : back;
                targetSide.focus();

                if (window.jwAnnounce) {
                    window.jwAnnounce(isBackCurrentlyVisible ? "Card flipped to front" : "Card flipped to back", "assertive");
                }

                // Dispatch event for tracking
                this.dispatchEvent(new CustomEvent('jw-flip-card-toggle', {
                    detail: { title: customTitle, visibleSide: isBackCurrentlyVisible ? 'front' : 'back' },
                    bubbles: true,
                    composed: true
                }));
            });
        });
    }
}

/**
 * JW Drag & Drop Alternative (Sortable List)
 * A keyboard-accessible alternative to drag-and-drop.
 * Uses list/listitem semantics for better compatibility with nested buttons.
 */
class JWDragDropAlt extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const items = Array.from(this.querySelectorAll('jw-item')).map(item => item.textContent);
        const label = this.getAttribute('label') || 'Sortable List';

        this.innerHTML = `
            <div class="jw-sortable">
                <p id="jw-sortable-instructions" class="sr-only">
                    Use the Up and Down arrow buttons to move items in the list.
                </p>
                <ul role="list" aria-label="${label}" aria-describedby="jw-sortable-instructions">
                    ${items.map((item, index) => `
                        <li class="jw-sortable-item" tabindex="0" data-index="${index}">
                            <span class="jw-item-text">${item}</span>
                            <div class="jw-item-controls">
                                <button type="button" class="jw-move-up" aria-label="Move ${item} up">
                                    <span aria-hidden="true">↑</span>
                                </button>
                                <button type="button" class="jw-move-down" aria-label="Move ${item} down">
                                    <span aria-hidden="true">↓</span>
                                </button>
                            </div>
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;

        const list = this.querySelector('ul');
        list.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;

            const li = btn.closest('li');
            if (btn.classList.contains('jw-move-up')) {
                this.moveItem(li, -1);
            } else if (btn.classList.contains('jw-move-down')) {
                this.moveItem(li, 1);
            }
        });

        list.addEventListener('keydown', (e) => {
            const li = e.target.closest('li');
            if (!li) return;

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.moveItem(li, -1);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.moveItem(li, 1);
            }
        });
    }

    moveItem(li, direction) {
        const parent = li.parentNode;
        const index = Array.from(parent.children).indexOf(li);
        const newIndex = index + direction;

        if (newIndex >= 0 && newIndex < parent.children.length) {
            const target = direction === -1 ? parent.children[newIndex] : parent.children[newIndex].nextSibling;
            parent.insertBefore(li, target);
            
            // Focus the item that was moved
            li.focus();
            
            // Announce movement to screen readers
            const text = li.querySelector('.jw-item-text').textContent;
            this.announce(`${text} moved to position ${newIndex + 1} of ${parent.children.length}`);

            // Dispatch event for tracking
            this.dispatchEvent(new CustomEvent('jw-sortable-move', {
                detail: { text, newIndex, total: parent.children.length },
                bubbles: true,
                composed: true
            }));
        }
    }

    announce(message) {
        let liveRegion = document.getElementById('jw-live-region');
        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.id = 'jw-live-region';
            liveRegion.className = 'sr-only';
            liveRegion.setAttribute('aria-live', 'assertive');
            document.body.appendChild(liveRegion);
        }
        liveRegion.textContent = '';
        setTimeout(() => {
            liveRegion.textContent = message;
        }, 100);
    }
}

/**
 * JW Click-to-Reveal Component
 * A robust, accessible disclosure pattern for revealing hidden content.
 */
class JWClickReveal extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const title = this.getAttribute('title') || 'Click to Reveal';
        const expanded = this.hasAttribute('expanded');
        const idBase = Math.random().toString(36).substr(2, 9);
        const content = this.innerHTML;

        this.innerHTML = `
            <div class="jw-click-reveal">
                <button type="button" 
                        id="trigger-${idBase}" 
                        class="jw-click-reveal-trigger" 
                        aria-expanded="${expanded}" 
                        aria-controls="panel-${idBase}">
                    <span class="jw-click-reveal-title">${title}</span>
                </button>
                <div id="panel-${idBase}" 
                     class="jw-click-reveal-panel" 
                     role="region" 
                     aria-labelledby="trigger-${idBase}" 
                     ${expanded ? '' : 'hidden'}>
                    <div class="jw-click-reveal-content">
                        ${content}
                    </div>
                </div>
            </div>
        `;

        const button = this.querySelector('button');
        const panel = this.querySelector('.jw-click-reveal-panel');

        button.addEventListener('click', () => {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', !isExpanded);
            panel.hidden = isExpanded;

            if (window.jwAnnounce) {
                window.jwAnnounce(!isExpanded ? `${title} revealed` : `${title} collapsed`, 'polite');
            }

            // Dispatch event for tracking
            this.dispatchEvent(new CustomEvent('jw-click-reveal-toggle', {
                detail: { title, expanded: !isExpanded },
                bubbles: true,
                composed: true
            }));
        });
    }
}

/**
 * JW Hotspot Component
 * A robust, accessible hotspot image interaction.
 */
class JWHotspotContainer extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const alt = this.getAttribute('alt') || 'Diagram with interactive hotspots';
        const src = this.getAttribute('src');
        const idBase = Math.random().toString(36).substr(2, 9);
        
        // Move existing markers to a temporary fragment
        const markers = Array.from(this.querySelectorAll('jw-hotspot-marker'));
        
        this.innerHTML = `
            <div class="jw-hotspot-wrapper" style="position: relative; display: inline-block;">
                <img src="${src}" alt="${alt}" class="jw-hotspot-image">
                <div class="jw-hotspot-markers">
                    ${markers.map((marker, index) => {
                        const x = marker.getAttribute('x') || '0%';
                        const y = marker.getAttribute('y') || '0%';
                        const label = marker.getAttribute('label') || `Hotspot ${index + 1}`;
                        const markerId = `marker-${idBase}-${index}`;
                        const popupId = `popup-${idBase}-${index}`;
                        
                        return `
                            <button type="button" 
                                    class="jw-hotspot-marker" 
                                    id="${markerId}"
                                    aria-expanded="false" 
                                    aria-controls="${popupId}" 
                                    aria-label="${label}"
                                    style="position: absolute; left: ${x}; top: ${y};">
                                <span aria-hidden="true">${index + 1}</span>
                            </button>
                            <div id="${popupId}" 
                                 class="jw-hotspot-popup" 
                                 role="region" 
                                 aria-labelledby="${markerId}" 
                                 hidden 
                                 tabindex="-1">
                                <div class="jw-hotspot-popup-content">
                                    <h4>${label}</h4>
                                    ${marker.innerHTML}
                                    <button type="button" class="jw-hotspot-close" aria-label="Close details">×</button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;

        const buttons = this.querySelectorAll('.jw-hotspot-marker');
        const popups = this.querySelectorAll('.jw-hotspot-popup');

        buttons.forEach((btn, index) => {
            btn.addEventListener('click', () => {
                const popup = popups[index];
                const isVisible = !popup.hidden;
                const label = btn.getAttribute('aria-label') || `Hotspot ${index + 1}`;
                
                // Close all others
                popups.forEach((p, i) => {
                    p.hidden = true;
                    buttons[i].setAttribute('aria-expanded', 'false');
                });

                if (!isVisible) {
                    popup.hidden = false;
                    btn.setAttribute('aria-expanded', 'true');
                    popup.focus();

                    if (window.jwAnnounce) {
                        window.jwAnnounce(`${label} details opened`, 'polite');
                    }
                }
            });
        });

        this.querySelectorAll('.jw-hotspot-close').forEach((closeBtn, index) => {
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                popups[index].hidden = true;
                buttons[index].setAttribute('aria-expanded', 'false');
                buttons[index].focus();
            });
        });
    }
}

/**
 * JW Timeline Component
 * An accessible chronological timeline using the vertical Tablist pattern.
 */
class JWTimeline extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const items = Array.from(this.querySelectorAll('jw-timeline-item'));
        const idBase = Math.random().toString(36).substr(2, 9);
        const label = this.getAttribute('aria-label') || 'Chronological Timeline';

        this.innerHTML = `
            <div class="jw-timeline">
                <div role="tablist" aria-label="${label}" aria-orientation="vertical" class="jw-timeline-track">
                    ${items.map((item, index) => {
                        const date = item.getAttribute('date') || '';
                        const title = item.getAttribute('title') || `Event ${index + 1}`;
                        const selected = index === 0;
                        return `
                            <button role="tab" 
                                    aria-selected="${selected}" 
                                    aria-controls="panel-${idBase}-${index}" 
                                    id="tab-${idBase}-${index}" 
                                    class="jw-timeline-node"
                                    tabindex="${selected ? '0' : '-1'}">
                                <span class="jw-timeline-date">${date}</span>
                                <span class="jw-timeline-dot"></span>
                                <span class="jw-timeline-label">${title}</span>
                            </button>
                        `;
                    }).join('')}
                </div>
                <div class="jw-timeline-panels">
                    ${items.map((item, index) => {
                        const selected = index === 0;
                        return `
                            <div role="tabpanel" 
                                 id="panel-${idBase}-${index}" 
                                 aria-labelledby="tab-${idBase}-${index}" 
                                 ${selected ? '' : 'hidden'}
                                 tabindex="0"
                                 class="jw-timeline-panel">
                                ${item.innerHTML}
                                <div class="sr-only" aria-hidden="false">End of tabbed content for ${item.getAttribute('title') || 'this section'}.</div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;

        const tabs = this.querySelectorAll('[role="tab"]');
        const panels = this.querySelectorAll('[role="tabpanel"]');

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => this.switchEvent(index, tabs, panels, items));
            tab.addEventListener('keydown', (e) => {
                let newIndex = index;
                if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                    newIndex = (index + 1) % tabs.length;
                } else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                    newIndex = (index - 1 + tabs.length) % tabs.length;
                }

                if (newIndex !== index) {
                    e.preventDefault();
                    tabs[newIndex].focus();
                    this.switchEvent(newIndex, tabs, panels, items);
                }
            });
        });
    }

    switchEvent(index, tabs, panels, items) {
        tabs.forEach((t, i) => {
            const selected = i === index;
            t.setAttribute('aria-selected', selected);
            t.setAttribute('tabindex', selected ? '0' : '-1');
            panels[i].hidden = !selected;
        });

        const eventTitle = items[index] ? (items[index].getAttribute('title') || `Event ${index + 1}`) : `Event ${index + 1}`;
        if (window.jwAnnounce) {
            window.jwAnnounce(`Timeline event: ${eventTitle} selected`, 'polite');
        }
    }
}

/**
 * JW Matching Game Component
 * A robust, accessible alternative to drag-and-drop matching.
 * Uses paragraphs paired with dropdown menus (select elements).
 */
class JWMatchingGame extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const pairs = Array.from(this.querySelectorAll('jw-match-pair'));
        const label = this.getAttribute('label') || 'Matching Game';
        const idBase = Math.random().toString(36).substr(2, 9);

        // Collect all possible 'targets' for the dropdowns
        const targets = pairs.map(pair => pair.getAttribute('target'));
        const shuffledTargets = [...targets].sort(() => Math.random() - 0.5);

        this.innerHTML = `
            <div class="jw-matching-game">
                <p id="match-instructions-${idBase}" class="sr-only">
                    Match each item in the first column with the correct option from the dropdown menu.
                </p>
                <form id="match-form-${idBase}" aria-labelledby="match-instructions-${idBase}">
                    <ul class="jw-match-list" role="list">
                        ${pairs.map((pair, index) => {
                            const source = pair.getAttribute('source');
                            const correct = pair.getAttribute('target');
                            return `
                                <li class="jw-match-item">
                                    <span id="source-${idBase}-${index}" class="jw-match-source">${source}</span>
                                    <div class="jw-match-control">
                                        <label for="select-${idBase}-${index}" class="sr-only">Match for ${source}</label>
                                        <select id="select-${idBase}-${index}" 
                                                class="jw-match-select" 
                                                data-correct="${correct}"
                                                aria-describedby="source-${idBase}-${index}">
                                            <option value="">-- Select Match --</option>
                                            ${shuffledTargets.map(t => `<option value="${t}">${t}</option>`).join('')}
                                        </select>
                                        <span class="jw-match-feedback" aria-live="polite"></span>
                                    </div>
                                </li>
                            `;
                        }).join('')}
                    </ul>
                    <div class="jw-match-actions">
                        <button type="submit" class="jw-match-submit nav-btn">Check Answers</button>
                        <button type="button" class="jw-match-reset nav-btn" style="background-color: var(--color-neutral-mid);">Reset</button>
                    </div>
                    <div class="jw-summary-feedback" aria-live="polite" style="margin-top: 1.5rem; font-weight: 700; font-size: 1.1rem; display: none; padding: 0.75rem 1rem; border-radius: 6px;"></div>
                </form>
            </div>
        `;

        const form = this.querySelector('form');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.checkAnswers();
        });

        this.querySelector('.jw-match-reset').addEventListener('click', () => {
            form.reset();
            const summary = this.querySelector('.jw-summary-feedback');
            summary.style.display = 'none';
            this.querySelectorAll('.jw-match-feedback').forEach(f => f.textContent = '');
            this.querySelectorAll('.jw-match-select').forEach(s => {
                s.classList.remove('correct', 'incorrect');
                s.removeAttribute('aria-invalid');
            });
            if (window.jwAnnounce) {
                window.jwAnnounce("Matching exercise reset.", "polite");
            }
        });
    }

    checkAnswers() {
        const selects = this.querySelectorAll('.jw-match-select');
        const summary = this.querySelector('.jw-summary-feedback');
        let correctCount = 0;
        const totalCount = selects.length;

        selects.forEach(select => {
            const val = select.value;
            const correct = select.dataset.correct;
            const feedback = select.nextElementSibling;

            if (val === correct && val !== '') {
                select.classList.add('correct');
                select.classList.remove('incorrect');
                select.setAttribute('aria-invalid', 'false');
                feedback.textContent = '✓ Correct';
                feedback.style.color = '#166534';
                correctCount++;
            } else {
                select.classList.add('incorrect');
                select.classList.remove('correct');
                select.setAttribute('aria-invalid', 'true');
                feedback.textContent = val === '' ? 'Please select an option' : '✗ Incorrect';
                feedback.style.color = '#991b1b';
            }
        });

        let evalStatus = 'incorrect';
        let statusTitle = 'Incorrect.';

        if (correctCount === totalCount && totalCount > 0) {
            evalStatus = 'correct';
            statusTitle = 'Correct!';
        } else if (correctCount > 0 && correctCount < totalCount) {
            evalStatus = 'partially-correct';
            statusTitle = 'Partially Correct.';
        } else {
            evalStatus = 'incorrect';
            statusTitle = 'Incorrect.';
        }

        let scoreText = '';
        if (evalStatus === 'correct') {
            scoreText = `${statusTitle} You matched ${correctCount} out of ${totalCount} items correctly.`;
        } else if (evalStatus === 'partially-correct') {
            scoreText = `${statusTitle} You matched ${correctCount} out of ${totalCount} items correctly. Please review unmatched options.`;
        } else {
            scoreText = `${statusTitle} You matched 0 out of ${totalCount} items. Please review the material and try again.`;
        }

        summary.textContent = scoreText;
        summary.style.display = 'block';

        if (evalStatus === 'correct') {
            summary.style.background = '#f0fdf4';
            summary.style.color = '#166534';
            summary.style.border = '1px solid #bbf7d0';
        } else if (evalStatus === 'partially-correct') {
            summary.style.background = '#fffbeb';
            summary.style.color = '#b45309';
            summary.style.border = '1px solid #fef3c7';
        } else {
            summary.style.background = '#fee2e2';
            summary.style.color = '#991b1b';
            summary.style.border = '1px solid #fecaca';
        }

        if (window.jwAnnounce) {
            window.jwAnnounce(scoreText, 'assertive');
        }

        // Dispatch event for tracking
        this.dispatchEvent(new CustomEvent('jw-matching-game-submit', {
            detail: { score: correctCount, total: totalCount, status: evalStatus },
            bubbles: true,
            composed: true
        }));
    }
}

/**
 * JW Scenario Component
 * An accessible branching narrative component with focus management.
 */
class JWScenario extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        // Initial setup - find the first branch
        const initialBranch = this.querySelector('jw-branch[initial]') || this.querySelector('jw-branch');
        if (!initialBranch) return;

        // Hide all original templates
        this.querySelectorAll('jw-branch').forEach(b => b.style.display = 'none');

        // Create a persistent container for the active view
        this.viewContainer = document.createElement('div');
        this.viewContainer.className = 'jw-scenario-view';
        this.appendChild(this.viewContainer);

        this.setAttribute('current-branch', initialBranch.getAttribute('id'));
        this.showBranch(initialBranch.getAttribute('id'));
    }

    showBranch(branchId) {
        // Find the original branch template inside the component
        const branchTemplate = Array.from(this.querySelectorAll('jw-branch')).find(b => b.id === branchId);
        if (!branchTemplate) return;

        const title = branchTemplate.getAttribute('title') || 'Scenario';
        const choices = Array.from(branchTemplate.querySelectorAll('jw-choice'));

        // Clean the content of choice tags to avoid double-text
        const contentClone = branchTemplate.cloneNode(true);
        contentClone.querySelectorAll('jw-choice').forEach(choice => choice.remove());
        const contentHtml = contentClone.innerHTML;

        this.viewContainer.innerHTML = `
            <div class="jw-scenario-container">
                <h3 tabindex="-1" class="jw-scenario-title">${title}</h3>
                <div class="jw-scenario-content">
                    ${contentHtml}
                </div>
                <div class="jw-scenario-choices">
                    ${choices.map(choice => `
                        <button type="button" 
                                class="jw-scenario-choice nav-btn" 
                                data-next="${choice.getAttribute('next')}">
                            ${choice.textContent}
                        </button>
                    `).join('')}
                </div>
            </div>
        `;

        const heading = this.viewContainer.querySelector('h3');
        heading.focus();

        if (window.jwAnnounce) {
            window.jwAnnounce(`Scenario branch loaded: ${title}`, 'assertive');
        }

        this.viewContainer.querySelectorAll('.jw-scenario-choice').forEach(btn => {
            btn.addEventListener('click', () => {
                const nextId = btn.getAttribute('data-next');
                this.showBranch(nextId);
                
                // Dispatch event for tracking
                this.dispatchEvent(new CustomEvent('jw-scenario-choice', {
                    detail: { choice: btn.textContent.trim(), nextId },
                    bubbles: true,
                    composed: true
                }));
            });
        });
    }
}

/**
 * JW Wizard Component
 * A multi-step form with focus management and step orientation.
 */
class JWWizard extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const steps = Array.from(this.querySelectorAll('jw-step'));
        const totalSteps = steps.length;
        let currentStep = 0;

        this.innerHTML = `
            <div class="jw-wizard">
                <div class="jw-wizard-progress" role="progressbar" 
                     aria-valuenow="1" 
                     aria-valuemin="1" 
                     aria-valuemax="${totalSteps}"
                     aria-valuetext="Step 1 of ${totalSteps}">
                    <div class="jw-wizard-progress-inner" style="width: ${(1 / totalSteps) * 100}%"></div>
                </div>
                
                <div class="jw-wizard-step-container">
                    ${steps.map((step, index) => `
                        <div class="jw-wizard-step-view" id="step-view-${index}" ${index === 0 ? '' : 'hidden'}>
                            <h3 tabindex="-1" class="jw-wizard-step-title">
                                <span class="sr-only">Step ${index + 1} of ${totalSteps}:</span>
                                ${step.getAttribute('title') || `Step ${index + 1}`}
                            </h3>
                            <div class="jw-wizard-step-content ${step.classList.contains('jw-wizard-results') ? 'jw-wizard-results-area' : ''}">
                                ${step.innerHTML}
                            </div>
                        </div>
                    `).join('')}
                </div>

                <div class="jw-wizard-actions">
                    <button type="button" class="jw-wizard-prev nav-btn" disabled style="background-color: var(--color-neutral-mid);">Previous</button>
                    <button type="button" class="jw-wizard-next nav-btn">Next</button>
                </div>
            </div>
        `;

        const progress = this.querySelector('.jw-wizard-progress');
        const progressInner = this.querySelector('.jw-wizard-progress-inner');
        const stepViews = this.querySelectorAll('.jw-wizard-step-view');
        const prevBtn = this.querySelector('.jw-wizard-prev');
        const nextBtn = this.querySelector('.jw-wizard-next');

        const updateWizard = (index) => {
            // Check for results step
            if (stepViews[index].querySelector('.jw-wizard-results-area')) {
                this.populateResults(stepViews[index].querySelector('.jw-wizard-results-area'));
            }

            stepViews[currentStep].hidden = true;
            stepViews[index].hidden = false;
            currentStep = index;

            progress.setAttribute('aria-valuenow', currentStep + 1);
            progress.setAttribute('aria-valuetext', `Step ${currentStep + 1} of ${totalSteps}`);
            progressInner.style.width = `${((currentStep + 1) / totalSteps) * 100}%`;

            prevBtn.disabled = currentStep === 0;
            nextBtn.textContent = currentStep === totalSteps - 1 ? 'Finish' : 'Next';

            const heading = stepViews[currentStep].querySelector('h3');
            heading.focus();

            if (window.jwAnnounce) {
                window.jwAnnounce(`Wizard Step ${currentStep + 1} of ${totalSteps}: ${heading.textContent.trim()}`, 'assertive');
            }
        };

        prevBtn.addEventListener('click', () => {
            if (currentStep > 0) updateWizard(currentStep - 1);
        });

        nextBtn.addEventListener('click', () => {
            if (currentStep < totalSteps - 1) {
                updateWizard(currentStep + 1);
            } else {
                this.dispatchEvent(new CustomEvent('jw-wizard-complete', {
                    bubbles: true,
                    composed: true
                }));
                this.innerHTML = `<h3 tabindex="-1">Wizard Complete</h3><p>Thank you for completing the process.</p>`;
                this.querySelector('h3').focus();
            }
        });
    }

    populateResults(area) {
        const data = {};
        // Collect all inputs from previous steps
        this.querySelectorAll('input, select, textarea').forEach(input => {
            if (input.closest('.jw-wizard-results-area')) return;
            
            const name = input.name || input.id;
            if (!name) return;

            if (input.type === 'checkbox') {
                if (!data[name]) data[name] = [];
                if (input.checked) data[name].push(input.parentElement.textContent.trim());
            } else if (input.type === 'radio') {
                if (input.checked) data[name] = input.parentElement.textContent.trim();
            } else {
                data[name] = input.value;
            }
        });

        let html = '<div class="jw-summary-box" style="padding: 1rem; background: #f0f7f4; border-radius: 0.5rem; margin-top: 1rem;">';
        for (const [key, value] of Object.entries(data)) {
            const label = key.replace('wiz-', '').replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
            const displayValue = Array.isArray(value) ? (value.length > 0 ? value.join(', ') : 'None selected') : (value || 'Not provided');
            html += `<p><strong>${label}:</strong> ${displayValue}</p>`;
        }
        html += '</div>';
        area.innerHTML = html;
    }
}

/**
 * JW Notifications Component
 * Demonstrates ARIA live regions for polite and assertive updates.
 */
class JWNotifications extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        this.innerHTML = `
            <div class="jw-notifications-demo">
                <div class="jw-notification-triggers">
                    <button type="button" class="jw-notify-polite nav-btn">Send Polite Notification</button>
                    <button type="button" class="jw-notify-assertive nav-btn" style="background-color: var(--color-error);">Send Assertive Notification</button>
                </div>
                
                <div class="jw-live-regions">
                    <div id="jw-live-polite" class="sr-only" aria-live="polite"></div>
                    <div id="jw-live-assertive" class="sr-only" aria-live="assertive"></div>
                </div>

                <div class="jw-visual-feedback" aria-hidden="true">
                    <p><em>Notifications will appear below (and be read by NVDA).</em></p>
                    <div id="jw-notification-stack"></div>
                </div>
            </div>
        `;

        const politeBtn = this.querySelector('.jw-notify-polite');
        const assertiveBtn = this.querySelector('.jw-notify-assertive');
        const politeRegion = this.querySelector('#jw-live-polite');
        const assertiveRegion = this.querySelector('#jw-live-assertive');
        const stack = this.querySelector('#jw-notification-stack');

        const notify = (message, type) => {
            const region = type === 'assertive' ? assertiveRegion : politeRegion;
            
            // Clear and set to trigger announcement
            region.textContent = '';
            setTimeout(() => {
                region.textContent = message;
                
                // Visual feedback
                const toast = document.createElement('div');
                toast.className = `jw-toast jw-toast-${type}`;
                toast.textContent = message;
                stack.appendChild(toast);
                setTimeout(() => toast.remove(), 5000);
            }, 100);

            // Dispatch event for tracking
            this.dispatchEvent(new CustomEvent('jw-notification-sent', {
                detail: { message, type },
                bubbles: true,
                composed: true
            }));
        };

        politeBtn.addEventListener('click', () => {
            notify('Success: Your changes have been saved.', 'polite');
        });

        assertiveBtn.addEventListener('click', () => {
            notify('Error: Connection lost. Please check your network.', 'assertive');
        });
    }
}

/**
 * JW Interactive Table Component
 * A robust, accessible sortable table.
 */
class JWInteractiveTable extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const headers = Array.from(this.querySelectorAll('jw-header')).map(h => ({
            text: h.textContent,
            type: h.getAttribute('type') || 'string'
        }));
        const rows = Array.from(this.querySelectorAll('jw-row')).map(row => 
            Array.from(row.querySelectorAll('jw-cell')).map(cell => cell.innerHTML)
        );

        this.innerHTML = `
            <div class="jw-table-container">
                <table class="jw-table">
                    <thead>
                        <tr>
                            ${headers.map((h, i) => `
                                <th scope="col" aria-sort="none">
                                    <button type="button" class="jw-table-sort-btn" data-index="${i}">
                                        ${h.text}
                                        <span class="jw-sort-icon" aria-hidden="true"></span>
                                    </button>
                                </th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map(row => `
                            <tr>
                                ${row.map(cell => `<td>${cell}</td>`).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        const table = this.querySelector('table');
        const btns = this.querySelectorAll('.jw-table-sort-btn');

        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.index);
                const currentSort = btn.parentElement.getAttribute('aria-sort');
                const newSort = currentSort === 'ascending' ? 'descending' : 'ascending';

                // Reset all headers
                btns.forEach(b => b.parentElement.setAttribute('aria-sort', 'none'));
                btn.parentElement.setAttribute('aria-sort', newSort);

                this.sortTable(index, newSort === 'ascending');
                if (window.jwAnnounce) {
                    window.jwAnnounce(`Table sorted by ${headers[index].text}, ${newSort} order.`, 'polite');
                }

                // Dispatch event for tracking
                this.dispatchEvent(new CustomEvent('jw-table-sort', {
                    detail: { column: headers[index].text, direction: newSort },
                    bubbles: true,
                    composed: true
                }));
            });
        });
    }

    sortTable(index, ascending) {
        const tbody = this.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const valA = a.children[index].textContent.trim();
            const valB = b.children[index].textContent.trim();
            
            if (!isNaN(valA) && !isNaN(valB)) {
                return ascending ? valA - valB : valB - valA;
            }
            return ascending ? valA.localeCompare(valB) : valB.localeCompare(valA);
        });

        rows.forEach(row => tbody.appendChild(row));
    }
}

/**
 * JW Carousel Component
 * An accessible carousel pattern.
 */
class JWCarousel extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const slides = Array.from(this.querySelectorAll('jw-slide'));
        const label = this.getAttribute('aria-label') || 'Content Carousel';
        const idBase = Math.random().toString(36).substr(2, 9);
        let currentIndex = 0;

        this.innerHTML = `
            <section class="jw-carousel" aria-roledescription="carousel" aria-label="${label}">
                <div class="jw-carousel-slides-container" role="group" aria-atomic="false">
                    ${slides.map((slide, index) => `
                        <div class="jw-carousel-slide" 
                             id="slide-${idBase}-${index}" 
                             role="group" 
                             aria-roledescription="slide" 
                             aria-label="${index + 1} of ${slides.length}"
                             ${index === 0 ? '' : 'hidden'}>
                            ${slide.innerHTML}
                        </div>
                    `).join('')}
                </div>
                <div class="jw-carousel-controls">
                    <button type="button" class="jw-carousel-prev nav-btn" aria-label="Previous Slide">← Previous</button>
                    <button type="button" class="jw-carousel-next nav-btn" aria-label="Next Slide">Next →</button>
                </div>
            </section>
        `;

        const slideElements = this.querySelectorAll('.jw-carousel-slide');
        const prevBtn = this.querySelector('.jw-carousel-prev');
        const nextBtn = this.querySelector('.jw-carousel-next');

        const showSlide = (index) => {
            slideElements[currentIndex].hidden = true;
            slideElements[index].hidden = false;
            currentIndex = index;
            
            // Move focus to the slide for linear reading
            slideElements[currentIndex].setAttribute('tabindex', '-1');
            slideElements[currentIndex].focus();

            if (window.jwAnnounce) {
                window.jwAnnounce(`Slide ${currentIndex + 1} of ${slides.length}`, 'polite');
            }

            // Dispatch event for tracking
            this.dispatchEvent(new CustomEvent('jw-carousel-slide-change', {
                detail: { index: currentIndex + 1, total: slides.length },
                bubbles: true,
                composed: true
            }));
        };

        prevBtn.addEventListener('click', () => {
            const index = (currentIndex - 1 + slides.length) % slides.length;
            showSlide(index);
        });

        nextBtn.addEventListener('click', () => {
            const index = (currentIndex + 1) % slides.length;
            showSlide(index);
        });
    }
}

/**
 * JW Modal Component
 * An accessible modal using the native <dialog> element.
 */
class JWModal extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const triggerText = this.getAttribute('trigger-text') || 'Open Modal';
        const title = this.getAttribute('title') || 'Information';
        const idBase = Math.random().toString(36).substr(2, 9);
        const content = this.innerHTML;

        this.innerHTML = `
            <div class="jw-modal-component">
                <button type="button" class="jw-modal-trigger nav-btn">${triggerText}</button>
                <dialog id="dialog-${idBase}" class="jw-dialog" aria-labelledby="title-${idBase}">
                    <div class="jw-dialog-content">
                        <div class="jw-dialog-header">
                            <h3 id="title-${idBase}">${title}</h3>
                            <button type="button" class="jw-dialog-close" aria-label="Close modal">×</button>
                        </div>
                        <div class="jw-dialog-body">
                            ${content}
                        </div>
                    </div>
                </dialog>
            </div>
        `;

        const dialog = this.querySelector('dialog');
        const trigger = this.querySelector('.jw-modal-trigger');
        const closeBtn = this.querySelector('.jw-dialog-close');

        trigger.addEventListener('click', () => {
            dialog.showModal();
            if (window.jwAnnounce) {
                window.jwAnnounce(`Modal dialog opened: ${title}`, 'assertive');
            }
            // Dispatch event for tracking
            this.dispatchEvent(new CustomEvent('jw-modal-open', {
                detail: { title },
                bubbles: true,
                composed: true
            }));
        });

        closeBtn.addEventListener('click', () => {
            dialog.close();
            trigger.focus();
            if (window.jwAnnounce) {
                window.jwAnnounce(`Modal dialog closed`, 'polite');
            }
        });

        dialog.addEventListener('close', () => {
            trigger.focus();
        });

        // Close on clicking backdrop
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) {
                dialog.close();
            }
        });
    }
}

/**
 * JW Form Validation Component
 * A robust form that demonstrates accessible error handling and focus management.
 */
class JWFormValidation extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const idBase = Math.random().toString(36).substr(2, 9);
        this.innerHTML = `
            <form id="form-${idBase}" class="jw-validated-form" novalidate>
                <div id="summary-${idBase}" class="jw-error-summary" role="alert" aria-live="assertive" hidden>
                    <h4 tabindex="-1">Please correct the following errors:</h4>
                    <ul id="error-list-${idBase}"></ul>
                </div>

                <div class="jw-form-group">
                    <label for="name-${idBase}">Username (Required)</label>
                    <input type="text" id="name-${idBase}" name="username" required 
                           aria-describedby="error-name-${idBase}">
                    <span id="error-name-${idBase}" class="jw-error-message" hidden>Username is required.</span>
                </div>
                
                <div class="jw-form-group">
                    <label for="email-${idBase}">Email Address (Required)</label>
                    <input type="email" id="email-${idBase}" name="email" required
                           aria-describedby="error-email-${idBase}">
                    <span id="error-email-${idBase}" class="jw-error-message" hidden>Please enter a valid email address.</span>
                </div>

                <div class="jw-form-group">
                    <label for="pass-${idBase}">Password (Required)</label>
                    <input type="password" id="pass-${idBase}" name="password" required
                           aria-describedby="error-pass-${idBase}">
                    <span id="error-pass-${idBase}" class="jw-error-message" hidden>Password is required.</span>
                </div>

                <div class="jw-form-actions">
                    <button type="submit" class="nav-btn">Register</button>
                    <button type="reset" class="nav-btn" style="background-color: var(--color-neutral-mid);">Clear Form</button>
                </div>
            </form>
        `;

        const form = this.querySelector('form');
        const summary = this.querySelector('.jw-error-summary');
        const summaryHeading = summary.querySelector('h4');
        const errorList = this.querySelector('ul');

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const inputs = Array.from(form.querySelectorAll('input'));
            let errors = [];
            errorList.innerHTML = '';

            inputs.forEach(input => {
                const errorSpan = document.getElementById(input.getAttribute('aria-describedby'));
                if (!input.checkValidity()) {
                    input.setAttribute('aria-invalid', 'true');
                    errorSpan.hidden = false;
                    const label = form.querySelector(`label[for="${input.id}"]`).textContent.replace(' (Required)', '');
                    errors.push({ id: input.id, message: `${label}: ${errorSpan.textContent}` });
                } else {
                    input.setAttribute('aria-invalid', 'false');
                    errorSpan.hidden = true;
                }
            });

            // Pedagogical requirement: Demonstrate complex validation failure simultaneously
            const nameInput = form.querySelector('input[name="username"]');
            if (nameInput && nameInput.value.trim() !== '') {
                const nameError = document.getElementById(nameInput.getAttribute('aria-describedby'));
                nameInput.setAttribute('aria-invalid', 'true');
                nameError.textContent = 'This username is already taken. Please choose another.';
                nameError.hidden = false;
                
                // Avoid duplicating the error if it was already caught by checkValidity (e.g. empty)
                if (!errors.find(e => e.id === nameInput.id)) {
                    errors.push({ id: nameInput.id, message: `Username: ${nameError.textContent}` });
                } else {
                    // Update existing error message to the more specific one
                    const existing = errors.find(e => e.id === nameInput.id);
                    existing.message = `Username: ${nameError.textContent}`;
                }
            }

            if (errors.length > 0) {
                summary.hidden = false;
                errors.forEach(err => {
                    const li = document.createElement('li');
                    li.innerHTML = `<a href="#${err.id}">${err.message}</a>`;
                    li.querySelector('a').addEventListener('click', (e) => {
                        e.preventDefault();
                        document.getElementById(err.id).focus();
                    });
                    errorList.appendChild(li);
                });
                // Focus the summary heading
                summaryHeading.focus();
            }
        });

        form.addEventListener('reset', () => {
            summary.hidden = true;
            Array.from(form.querySelectorAll('input')).forEach(input => {
                input.removeAttribute('aria-invalid');
                const errorSpan = document.getElementById(input.getAttribute('aria-describedby'));
                if (errorSpan) {
                    errorSpan.hidden = true;
                    // Reset to default message
                    if (input.name === 'username') errorSpan.textContent = 'Username is required.';
                }
            });
        });
    }
}

/**
 * JW Tooltip Component
 * An accessible tooltip following WCAG 2.1 1.4.13 (Content on Hover or Focus).
 */
class JWTooltip extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const text = this.getAttribute('text') || 'Additional info';
        const idBase = Math.random().toString(36).substr(2, 9);
        const triggerContent = this.innerHTML;

        this.innerHTML = `
            <span class="jw-tooltip-wrapper">
                <button type="button" 
                        class="jw-tooltip-trigger" 
                        aria-expanded="false"
                        aria-controls="tooltip-${idBase}"
                        aria-describedby="tooltip-${idBase}">
                    ${triggerContent || 'i'}
                </button>
                <span id="tooltip-${idBase}" 
                      class="jw-tooltip-content" 
                      role="region" 
                      hidden>
                    ${text}
                </span>
            </span>
        `;

        const trigger = this.querySelector('.jw-tooltip-trigger');
        const tooltip = this.querySelector('.jw-tooltip-content');

        const show = (announce = false) => {
            if (tooltip.hidden) {
                tooltip.hidden = false;
                trigger.setAttribute('aria-expanded', 'true');
                if (announce) {
                    this.announce(text);
                }
            }
        };

        const hide = () => {
            if (!tooltip.hidden) {
                tooltip.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }
        };

        const toggle = () => {
            if (tooltip.hidden) show(true);
            else hide();
        };

        trigger.addEventListener('mouseenter', () => show(false));
        trigger.addEventListener('focus', () => show(false));
        trigger.addEventListener('mouseleave', hide);
        trigger.addEventListener('blur', hide);
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            toggle();
        });

        // Dismiss on Escape
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !tooltip.hidden) {
                hide();
                trigger.focus();
            }
        });
    }

    announce(message) {
        let liveRegion = document.getElementById('jw-live-region');
        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.id = 'jw-live-region';
            liveRegion.className = 'sr-only';
            liveRegion.setAttribute('aria-live', 'polite');
            document.body.appendChild(liveRegion);
        }
        liveRegion.textContent = '';
        setTimeout(() => {
            liveRegion.textContent = message;
        }, 100);
    }
}

/**
 * JW Button Demo Component
 * Contrasts inaccessible fake buttons with native ones.
 */
class JWButtonDemo extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        this.innerHTML = `
            <div class="jw-button-comparison">
                <div class="jw-demo-box">
                    <h5>1. Inaccessible Fake Button (Div)</h5>
                    <div class="fake-btn-div" onclick="alert('Clicked Div!')">
                        Click Me (Mouse Only)
                    </div>
                </div>

                <div class="jw-demo-box">
                    <h5>2. ARIA "Polyfilled" Button (Div)</h5>
                    <div class="fake-btn-aria" 
                         role="button" 
                         tabindex="0" 
                         onclick="alert('Clicked ARIA Div!')"
                         onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); alert('Activated via Keyboard!'); }">
                        Click Me (Accessible-ish)
                    </div>
                </div>

                <div class="jw-demo-box">
                    <h5>3. Native Semantic Button</h5>
                    <button type="button" class="nav-btn" onclick="alert('Clicked Native Button!')">
                        Click Me (Correct)
                    </button>
                </div>
            </div>
        `;
    }
}

/**
 * JW Progress Bar Component
 * A standalone, accessible progress bar.
 */
class JWProgressBar extends HTMLElement {
    static get observedAttributes() { return ['value', 'max', 'label']; }

    connectedCallback() {
        this.render();
    }

    attributeChangedCallback() {
        this.render();
    }

    render() {
        const value = parseFloat(this.getAttribute('value')) || 0;
        const max = parseFloat(this.getAttribute('max')) || 100;
        const label = this.getAttribute('label') || 'Progress';
        const percent = Math.min(Math.max((value / max) * 100, 0), 100);

        this.innerHTML = `
            <div class="jw-progress-container">
                <div class="jw-progress-label" id="pb-label-${this.id || 'gen'}">${label}: ${Math.round(percent)}%</div>
                <div class="jw-progress-track" 
                     role="progressbar" 
                     aria-valuenow="${value}" 
                     aria-valuemin="0" 
                     aria-valuemax="${max}" 
                     aria-labelledby="pb-label-${this.id || 'gen'}">
                    <div class="jw-progress-fill" style="width: ${percent}%"></div>
                </div>
            </div>
        `;
    }
}

/**
 * JW Multi-Column Layout Component
 * Demonstrates accessible reading order in complex layouts.
 */
class JWMultiColumn extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const columns = Array.from(this.querySelectorAll('jw-column'));
        this.innerHTML = `
            <div class="jw-multi-column-grid" role="region" aria-label="Multi-column information">
                ${columns.map((col, index) => `
                    <div class="jw-column-box">
                        <h4 tabindex="-1">Column ${index + 1}: ${col.getAttribute('title') || ''}</h4>
                        <div class="jw-column-content">
                            ${col.innerHTML}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
}

/**
 * JW Link Demo Component
 * Contrasts inaccessible fake links with native ones.
 */
class JWLinkDemo extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.render();
        this.setAttribute('rendered', '');
    }

    render() {
        const msg = "Link activated! (This is a demo, so no actual navigation occurred.)";
        this.innerHTML = `
            <div class="jw-button-comparison">
                <div class="jw-demo-box">
                    <h5>1. Inaccessible Fake Link (Span)</h5>
                    <p>To learn more, <span class="fake-link-span" onclick="alert('${msg}')">view our digital accessibility guidelines</span>.</p>
                </div>

                <div class="jw-demo-box">
                    <h5>2. ARIA "Polyfilled" Link (Span)</h5>
                    <p>To learn more, <span class="fake-link-aria" 
                         role="link" 
                         tabindex="0" 
                         onclick="alert('${msg}')"
                         onkeydown="if(event.key === 'Enter') { event.preventDefault(); alert('${msg}'); }">
                        view our digital accessibility guidelines
                    </span>.</p>
                </div>

                <div class="jw-demo-box">
                    <h5>3. Native Semantic Link</h5>
                    <p>To learn more, <a href="#" onclick="event.preventDefault(); alert('${msg}');">view our digital accessibility guidelines</a>.</p>
                </div>
            </div>
        `;
    }
}

// Register Components
customElements.define('jw-accordion', JWAccordion);
customElements.define('jw-tabs', JWTabs);
customElements.define('jw-flip-card', JWFlipCard);
if (!customElements.get('jw-flipcard')) customElements.define('jw-flipcard', JWFlipCard);
customElements.define('jw-dragdrop-alt', JWDragDropAlt);
customElements.define('jw-matching-game', JWMatchingGame);
customElements.define('jw-click-reveal', JWClickReveal);
customElements.define('jw-hotspot-container', JWHotspotContainer);
customElements.define('jw-timeline', JWTimeline);
customElements.define('jw-scenario', JWScenario);
customElements.define('jw-wizard', JWWizard);
customElements.define('jw-notifications', JWNotifications);
customElements.define('jw-interactive-table', JWInteractiveTable);
customElements.define('jw-carousel', JWCarousel);
customElements.define('jw-modal', JWModal);
customElements.define('jw-form-validation', JWFormValidation);
customElements.define('jw-tooltip', JWTooltip);
customElements.define('jw-button-demo', JWButtonDemo);
customElements.define('jw-progress-bar', JWProgressBar);
customElements.define('jw-multi-column', JWMultiColumn);
customElements.define('jw-link-demo', JWLinkDemo);

/**
 * JW Quiz Component (<jw-quiz>)
 * Native Web Component for rendering LC-JSON 1.0 Course and QuestionSet documents.
 * 100% WCAG 2.2 AA Compliant with automatic xAPI tracking.
 */
class JWQuiz extends HTMLElement {
    connectedCallback() {
        if (this.hasAttribute('rendered')) return;
        this.init();
        this.setAttribute('rendered', '');
    }

    async init() {
        let lcData = null;

        const src = this.getAttribute('src');
        if (src) {
            try {
                const resp = await fetch(src);
                if (resp.ok) {
                    lcData = await resp.json();
                }
            } catch (err) {
                console.error("JWQuiz: Failed to fetch LC-JSON src:", src, err);
            }
        }

        if (!lcData && this.hasAttribute('data-json')) {
            try {
                lcData = JSON.parse(this.getAttribute('data-json'));
            } catch (e) {}
        }

        if (!lcData) {
            const scriptTag = this.querySelector('script[type="application/json"]');
            if (scriptTag) {
                try {
                    lcData = JSON.parse(scriptTag.textContent);
                } catch (e) {}
            }
        }

        if (!lcData) {
            this.innerHTML = `
                <div class="jw-quiz-error" role="alert" style="padding: 1rem; background: #fee2e2; color: #991b1b; border-radius: 6px;">
                    <strong>JWQuiz Error:</strong> No valid LC-JSON specification payload found. Provide a <code>src</code> attribute or inline JSON payload.
                </div>
            `;
            return;
        }

        this.renderQuiz(lcData);
    }

    renderQuiz(lcData) {
        const title = lcData.title || (lcData.documentType === 'Course' ? 'LC-JSON Course' : 'Interactive Assessment');
        const description = lcData.description || '';
        
        let questions = [];
        if (Array.isArray(lcData.questions)) {
            questions = lcData.questions;
        } else if (Array.isArray(lcData.units)) {
            lcData.units.forEach(unit => {
                if (Array.isArray(unit.lessons)) {
                    unit.lessons.forEach(lesson => {
                        if (Array.isArray(lesson.questions)) {
                            questions = questions.concat(lesson.questions);
                        }
                        if (Array.isArray(lesson.items)) {
                            lesson.items.forEach(item => {
                                if (item.question) questions.push(item.question);
                                else if (item.type) questions.push(item);
                            });
                        }
                    });
                }
            });
        }

        const totalPoints = questions.reduce((acc, q) => acc + (parseFloat(q.points) || 1.0), 0);

        let html = `
            <div class="jw-quiz-wrapper" role="region" aria-label="Interactive Assessment: ${this.escapeHtml(title)}">
                <div class="jw-quiz-header" style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #e2e8f0;">
                    <h2 class="jw-quiz-title" style="margin: 0 0 0.5rem 0; font-size: 1.75rem; color: #0f172a;">${this.escapeHtml(title)}</h2>
                    ${description ? `<p class="jw-quiz-desc" style="margin: 0 0 1rem 0; color: #475569;">${this.escapeHtml(description)}</p>` : ''}
                    <div class="jw-quiz-stats-bar" style="display: flex; gap: 1rem; background: #f8fafc; padding: 0.75rem 1rem; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600;">
                        <span>Questions: ${questions.length}</span>
                        <span>Total Points: ${totalPoints}</span>
                        <span id="jw-quiz-score-badge" style="color: #2563eb;">Score: 0 / ${totalPoints} (0%)</span>
                    </div>
                </div>

                <form class="jw-quiz-form" onsubmit="return false;">
        `;

        questions.forEach((q, idx) => {
            html += this.renderQuestionItem(q, idx + 1);
        });

        html += `
                </form>
                <div class="jw-quiz-footer" style="margin-top: 2rem; text-align: center;">
                    <button type="button" class="jw-quiz-submit-all" style="background: #166534; color: white; padding: 0.75rem 2rem; font-size: 1.1rem; font-weight: 700; border: none; border-radius: 6px; cursor: pointer;">
                        Submit & Score Complete Quiz
                    </button>
                </div>
            </div>
        `;

        this.innerHTML = html;
        this.bindEvents(questions, totalPoints);
    }

    renderQuestionItem(q, index) {
        const type = q.type || 'multipleChoice';
        const globalId = q.globalId || (`q-${index}`);
        const title = q.title || `Question ${index}`;
        const prompt = q.prompt || '';
        const points = parseFloat(q.points) || 1.0;

        let bodyHtml = '';
        switch (type) {
            case 'multipleChoice':
                bodyHtml = this.renderMultipleChoice(q, globalId);
                break;
            case 'trueFalseQuestion':
                bodyHtml = this.renderTrueFalse(q, globalId);
                break;
            case 'simpleGapFill':
                bodyHtml = this.renderSimpleGapFill(q, globalId);
                break;
            case 'wordBankCloze':
                bodyHtml = this.renderWordBankCloze(q, globalId);
                break;
            case 'shortAnswer':
            case 'essay':
                bodyHtml = this.renderTextEntry(q, globalId, type);
                break;
            case 'matching':
                bodyHtml = this.renderMatching(q, globalId);
                break;
            case 'ordering':
                bodyHtml = this.renderOrdering(q, globalId);
                break;
            default:
                bodyHtml = `<p><em>(Question type '${this.escapeHtml(type)}' in standard delivery)</em></p>`;
                break;
        }

        const cleanGlobalId = globalId.replace(/[^a-zA-Z0-9]/g, '');
        const promptId = `jw_prompt_${cleanGlobalId}`;

        return `
            <fieldset class="jw-quiz-question-card" data-global-id="${this.escapeHtml(globalId)}" data-points="${points}" data-qtype="${type}" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <legend class="jw-quiz-legend" style="font-weight: 700; font-size: 1.1rem; padding: 0 0.5rem; color: #0f172a; display: block;">
                    <span>${this.escapeHtml(title)}</span>
                    <span style="font-size: 0.85rem; color: #64748b; font-weight: normal; margin-left: 0.5rem;">(${points} ${points === 1 ? 'point' : 'points'})</span>
                    ${prompt ? `<span id="${promptId}" class="jw-quiz-prompt" style="display: block; font-size: 1.05rem; font-weight: normal; margin: 0.75rem 0 0.5rem 0; color: #1e293b;">${this.escapeHtml(prompt)}</span>` : ''}
                </legend>
                
                ${bodyHtml}
                
                ${q.hint ? `
                    <jw-click-reveal button-text="Show Hint" hint="Request help for this item">
                        <p><strong>Hint:</strong> ${this.escapeHtml(q.hint)}</p>
                    </jw-click-reveal>
                ` : ''}
                
                <div class="jw-quiz-feedback" role="status" aria-live="polite" style="margin-top: 1rem; display: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 600;"></div>
            </fieldset>
        `;
    }

    renderMultipleChoice(q, globalId) {
        let options = q.options || [];
        const optionsAndPoints = q.optionsAndPoints || {};
        const allowMultiple = q.allowMultipleCorrect || false;
        const inputType = allowMultiple ? 'checkbox' : 'radio';
        const cleanGlobalId = globalId.replace(/[^a-zA-Z0-9]/g, '');
        const inputName = `jw_mc_${cleanGlobalId}`;
        const promptId = `jw_prompt_${cleanGlobalId}`;

        if (q.shuffleOptions) {
            options = [...options].sort(() => Math.random() - 0.5);
        }

        const labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        let html = `<div class="jw-quiz-options-group" style="display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1rem; margin-top: 0.5rem;">`;
        options.forEach((opt, idx) => {
            const optId = `${inputName}_${idx}`;
            const pts = optionsAndPoints[opt] !== undefined ? optionsAndPoints[opt] : 0.0;
            const letterLabel = q.showLetterLabels && labels[idx] ? `<strong>${labels[idx]}.</strong> ` : '';
            const choiceFb = (q.feedback && q.feedback.choiceFeedback && q.feedback.choiceFeedback[opt]) ? q.feedback.choiceFeedback[opt] : '';

            html += `
                <div class="jw-quiz-option-item" style="display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
                    <input type="${inputType}" id="${optId}" name="${inputName}" value="${this.escapeHtml(opt)}" data-pts="${pts}" data-choice-fb="${this.escapeHtml(choiceFb)}" class="jw-quiz-input" aria-describedby="${promptId}">
                    <label for="${optId}" style="cursor: pointer; width: 100%; font-size: 1rem; color: #1e293b;">${letterLabel}${this.escapeHtml(opt)}</label>
                </div>
            `;
        });
        html += `</div>`;
        html += `<button type="button" class="jw-quiz-check-btn" style="background: #2563eb; color: white; border: none; padding: 0.6rem 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: 6px; cursor: pointer;">Check Answer</button>`;
        return html;
    }

    renderTrueFalse(q, globalId) {
        const cleanGlobalId = globalId.replace(/[^a-zA-Z0-9]/g, '');
        const inputName = `jw_tf_${cleanGlobalId}`;
        const promptId = `jw_prompt_${cleanGlobalId}`;
        const correctVal = (q.correctAnswer === true) ? 'true' : 'false';
        const displayStyle = q.displayStyle || 'TrueFalse';

        let trueText = 'True', falseText = 'False';
        if (displayStyle === 'CorrectIncorrect') { trueText = 'Correct'; falseText = 'Incorrect'; }
        else if (displayStyle === 'CheckmarkX') { trueText = '✓ Valid'; falseText = '✗ Invalid'; }

        return `
            <div class="jw-quiz-options-group" style="display: flex; gap: 1rem; margin-bottom: 1rem; margin-top: 0.5rem;">
                <div class="jw-quiz-option-item" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
                    <input type="radio" id="${inputName}_t" name="${inputName}" value="true" data-correct="${correctVal}" class="jw-quiz-input" aria-describedby="${promptId}">
                    <label for="${inputName}_t" style="cursor: pointer; font-weight: 600;">${trueText}</label>
                </div>
                <div class="jw-quiz-option-item" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
                    <input type="radio" id="${inputName}_f" name="${inputName}" value="false" data-correct="${correctVal}" class="jw-quiz-input" aria-describedby="${promptId}">
                    <label for="${inputName}_f" style="cursor: pointer; font-weight: 600;">${falseText}</label>
                </div>
            </div>
            <button type="button" class="jw-quiz-check-btn" style="background: #2563eb; color: white; border: none; padding: 0.6rem 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: 6px; cursor: pointer;">Check Answer</button>
        `;
    }

    renderSimpleGapFill(q, globalId) {
        const sentence = q.sentence || '@@@';
        const accepted = JSON.stringify(q.acceptedAnswers || []);
        const caseSensitive = q.caseSensitive ? 'true' : 'false';

        const inputHtml = `<input type="text" class="jw-gap-input" aria-label="Fill in blank" data-accepted='${this.escapeHtml(accepted)}' data-case="${caseSensitive}" style="padding: 0.4rem 0.6rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 1rem;">`;
        const rendered = this.escapeHtml(sentence).replace('@@@', inputHtml);

        return `
            <div class="jw-gap-passage" style="margin-bottom: 1rem; font-size: 1.05rem;">
                <p>${rendered}</p>
            </div>
            <button type="button" class="jw-quiz-check-btn" style="background: #2563eb; color: white; border: none; padding: 0.6rem 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: 6px; cursor: pointer;">Check Answer</button>
        `;
    }

    renderWordBankCloze(q, globalId) {
        const passage = q.passage || '';
        const wordBank = q.wordBank || [];
        const answers = JSON.stringify(q.gapAcceptedAnswers || {});

        let html = `
            <div class="jw-word-bank-box" style="padding: 0.75rem; background: #f1f5f9; border-radius: 6px; margin-bottom: 1rem;">
                <span style="font-weight: 700; font-size: 0.9rem; color: #475569; display: block; margin-bottom: 0.4rem;">Word Bank:</span>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    ${wordBank.map(w => `<span class="jw-bank-word" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 0.25rem 0.6rem; border-radius: 4px; font-weight: 600;">${this.escapeHtml(w)}</span>`).join('')}
                </div>
            </div>
        `;

        let rendered = this.escapeHtml(passage);
        rendered = rendered.replace(/@@@(\d+)/g, (match, gapNum) => {
            return `<input type="text" class="jw-gap-input" aria-label="Gap ${gapNum}" data-gap="${gapNum}" data-answers='${this.escapeHtml(answers)}' style="padding: 0.3rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 1rem; width: 120px;">`;
        });

        html += `<div class="jw-gap-passage" style="margin-bottom: 1rem; font-size: 1.05rem;"><p>${rendered}</p></div>`;
        html += `<button type="button" class="jw-quiz-check-btn" style="background: #2563eb; color: white; border: none; padding: 0.6rem 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: 6px; cursor: pointer;">Check Answers</button>`;
        return html;
    }

    renderTextEntry(q, globalId, type) {
        const inputName = `jw_text_${globalId.replace(/[^a-zA-Z0-9]/g, '')}`;
        return `
            <div style="margin-bottom: 1rem;">
                ${type === 'essay' 
                    ? `<textarea id="${inputName}" class="jw-text-input" rows="6" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem;" aria-label="Your response"></textarea>` 
                    : `<input type="text" id="${inputName}" class="jw-text-input" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem;" aria-label="Your response">`}
            </div>
            <button type="button" class="jw-quiz-check-btn" style="background: #2563eb; color: white; border: none; padding: 0.6rem 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: 6px; cursor: pointer;">Submit Response</button>
        `;
    }

    renderMatching(q, globalId) {
        const pairs = q.pairs || [];
        const targets = pairs.map(p => p.target).sort(() => Math.random() - 0.5);

        let html = `<div style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1rem;">`;
        pairs.forEach((pair) => {
            html += `
                <div class="jw-matching-row" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.6rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span style="font-weight: 600; width: 45%;">${this.escapeHtml(pair.source)}</span>
                    <select class="jw-matching-select" data-expected="${this.escapeHtml(pair.target)}" style="width: 50%; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.95rem;">
                        <option value="">-- Select Match --</option>
                        ${targets.map(t => `<option value="${this.escapeHtml(t)}">${this.escapeHtml(t)}</option>`).join('')}
                    </select>
                </div>
            `;
        });
        html += `</div>`;
        html += `<button type="button" class="jw-quiz-check-btn" style="background: #2563eb; color: white; border: none; padding: 0.6rem 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: 6px; cursor: pointer;">Check Matching</button>`;
        return html;
    }

    renderOrdering(q, globalId) {
        const items = q.items || [];
        let html = `<div style="margin-bottom: 1rem;"><p style="font-size: 0.9rem; color: #64748b;">Assign ordinal rank to sequence items correctly:</p>`;
        items.forEach((item, idx) => {
            html += `
                <div class="jw-order-row" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; padding: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <select class="jw-order-select" data-item="${this.escapeHtml(item)}" data-expected-rank="${idx + 1}" style="padding: 0.3rem 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px;">
                        ${items.map((_, i) => `<option value="${i + 1}">Position ${i + 1}</option>`).join('')}
                    </select>
                    <span>${this.escapeHtml(item)}</span>
                </div>
            `;
        });
        html += `</div>`;
        html += `<button type="button" class="jw-quiz-check-btn" style="background: #2563eb; color: white; border: none; padding: 0.6rem 1.25rem; font-size: 0.95rem; font-weight: 600; border-radius: 6px; cursor: pointer;">Check Order</button>`;
        return html;
    }

    bindEvents(questions, totalPoints) {
        const cards = this.querySelectorAll('.jw-quiz-question-card');
        
        cards.forEach(card => {
            const checkBtn = card.querySelector('.jw-quiz-check-btn');
            const feedbackBox = card.querySelector('.jw-quiz-feedback');
            const qtype = card.dataset.qtype;
            const globalId = card.dataset.globalId;
            const maxPts = parseFloat(card.dataset.points) || 1.0;

            if (!checkBtn) return;

            checkBtn.addEventListener('click', () => {
                let isCorrect = false;
                let earnedPts = 0;
                let feedbackText = '';

                if (qtype === 'multipleChoice') {
                    const checked = card.querySelectorAll('.jw-quiz-input:checked');
                    let pts = 0;
                    checked.forEach(inp => {
                        pts += parseFloat(inp.dataset.pts || 0);
                        if (inp.dataset.choiceFb) {
                            feedbackText += ` (${inp.dataset.choiceFb})`;
                        }
                    });
                    earnedPts = Math.max(0, pts);
                    isCorrect = earnedPts > 0;
                } else if (qtype === 'trueFalseQuestion') {
                    const checked = card.querySelector('.jw-quiz-input:checked');
                    if (checked) {
                        const isTrue = checked.value === 'true';
                        const expected = checked.dataset.correct === 'true';
                        isCorrect = (isTrue === expected);
                        earnedPts = isCorrect ? maxPts : 0;
                    }
                } else if (qtype === 'simpleGapFill') {
                    const gap = card.querySelector('.jw-gap-input');
                    if (gap) {
                        const val = gap.value.trim();
                        const accepted = JSON.parse(gap.dataset.accepted || '[]');
                        const isCase = gap.dataset.case === 'true';

                        isCorrect = accepted.some(ans => isCase ? ans === val : ans.toLowerCase() === val.toLowerCase());
                        earnedPts = isCorrect ? maxPts : 0;
                    }
                } else if (qtype === 'wordBankCloze') {
                    const gaps = card.querySelectorAll('.jw-gap-input');
                    let correctCount = 0;
                    gaps.forEach(gap => {
                        const num = gap.dataset.gap;
                        const answersObj = JSON.parse(gap.dataset.answers || '{}');
                        const validArr = answersObj[num] || [];
                        const val = gap.value.trim();
                        if (validArr.some(a => a.toLowerCase() === val.toLowerCase())) {
                            correctCount++;
                        }
                    });
                    isCorrect = (correctCount === gaps.length && gaps.length > 0);
                    earnedPts = isCorrect ? maxPts : (correctCount / gaps.length) * maxPts;
                } else if (qtype === 'matching') {
                    const selects = card.querySelectorAll('.jw-matching-select');
                    let correctCount = 0;
                    selects.forEach(sel => {
                        if (sel.value && sel.value === sel.dataset.expected) correctCount++;
                    });
                    isCorrect = (correctCount === selects.length && selects.length > 0);
                    earnedPts = isCorrect ? maxPts : 0;
                } else if (qtype === 'ordering') {
                    const selects = card.querySelectorAll('.jw-order-select');
                    let correctCount = 0;
                    selects.forEach(sel => {
                        if (parseInt(sel.value, 10) === parseInt(sel.dataset.expectedRank, 10)) correctCount++;
                    });
                    isCorrect = (correctCount === selects.length && selects.length > 0);
                    earnedPts = isCorrect ? maxPts : 0;
                } else {
                    isCorrect = true;
                    earnedPts = maxPts;
                }

                card.dataset.score = earnedPts;

                let evalStatus = 'incorrect';
                let statusIcon = '✗';
                let statusTitle = 'Incorrect.';

                if (earnedPts >= maxPts && maxPts > 0) {
                    evalStatus = 'correct';
                    statusIcon = '✓';
                    statusTitle = 'Correct!';
                } else if (earnedPts > 0 && earnedPts < maxPts) {
                    evalStatus = 'partially-correct';
                    statusIcon = '⚠';
                    statusTitle = 'Partially Correct.';
                } else {
                    evalStatus = 'incorrect';
                    statusIcon = '✗';
                    statusTitle = 'Incorrect.';
                }

                let message = '';
                if (evalStatus === 'correct') {
                    message = `${statusTitle} You earned ${earnedPts} out of ${maxPts} point(s).${feedbackText}`;
                } else if (evalStatus === 'partially-correct') {
                    message = `${statusTitle} You earned ${earnedPts} out of ${maxPts} point(s). Please review your selections.${feedbackText}`;
                } else {
                    message = `${statusTitle} You earned 0 out of ${maxPts} point(s). Please review the content and try again.${feedbackText}`;
                }

                if (feedbackBox) {
                    feedbackBox.style.display = 'block';
                    if (evalStatus === 'correct') {
                        feedbackBox.style.background = '#f0fdf4';
                        feedbackBox.style.color = '#166534';
                        feedbackBox.style.border = '1px solid #bbf7d0';
                    } else if (evalStatus === 'partially-correct') {
                        feedbackBox.style.background = '#fffbeb';
                        feedbackBox.style.color = '#b45309';
                        feedbackBox.style.border = '1px solid #fef3c7';
                    } else {
                        feedbackBox.style.background = '#fee2e2';
                        feedbackBox.style.color = '#991b1b';
                        feedbackBox.style.border = '1px solid #fecaca';
                    }
                    feedbackBox.textContent = `${statusIcon} ${message}`;
                }

                if (window.jwAnnounce) {
                    window.jwAnnounce(message, 'assertive');
                }

                if (window.xapi) {
                    window.xapi.sendStatement({
                        verb: {
                            id: "http://adlnet.gov/expapi/verbs/answered",
                            display: { "en-US": "answered" }
                        },
                        object: {
                            id: window.location.href + "#" + globalId,
                            definition: { name: { "en-US": "LC-JSON Question (" + globalId + ")" } }
                        },
                        result: {
                            score: { raw: earnedPts, max: maxPts },
                            success: (evalStatus === 'correct')
                        }
                    });
                }

                this.updateTotalScore(totalPoints);
            });
        });

        const submitAllBtn = this.querySelector('.jw-quiz-submit-all');
        if (submitAllBtn) {
            submitAllBtn.addEventListener('click', () => {
                cards.forEach(c => {
                    const btn = c.querySelector('.jw-quiz-check-btn');
                    if (btn) btn.click();
                });
                this.updateTotalScore(totalPoints, true);
            });
        }
    }

    updateTotalScore(totalPoints, isFinal = false) {
        const cards = this.querySelectorAll('.jw-quiz-question-card');
        let currentTotal = 0;
        cards.forEach(c => {
            currentTotal += parseFloat(c.dataset.score || 0);
        });

        const pct = totalPoints > 0 ? Math.round((currentTotal / totalPoints) * 100) : 0;
        const badge = this.querySelector('#jw-quiz-score-badge');
        if (badge) {
            badge.textContent = `Score: ${currentTotal} / ${totalPoints} (${pct}%)`;
            badge.style.color = pct >= 70 ? '#166534' : (pct >= 50 ? '#d97706' : '#dc2626');
        }

        if (isFinal) {
            if (window.jwAnnounce) {
                window.jwAnnounce(`Quiz completed! Final score: ${currentTotal} out of ${totalPoints} points (${pct}%).`);
            }
            if (window.xapi) {
                window.xapi.sendStatement({
                    verb: {
                        id: "http://adlnet.gov/expapi/verbs/completed",
                        display: { "en-US": "completed" }
                    },
                    object: {
                        id: window.location.href + "#jw-quiz-completed",
                        definition: { name: { "en-US": "Complete Quiz Assessment" } }
                    },
                    result: {
                        score: { raw: currentTotal, max: totalPoints, scaled: (pct / 100) },
                        success: pct >= 70,
                        completion: true
                    }
                });
            }
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

customElements.define('jw-link-demo', JWLinkDemo);
customElements.define('jw-quiz', JWQuiz);

/**
 * Global Event Delegation Engine for LC-JSON (.lc-question-card) Widgets.
 * Guarantees screen reader announcements (NVDA, JAWS) and xAPI tracking for dynamically loaded HTML module fragments.
 */
document.addEventListener('click', (e) => {
    const submitBtn = e.target.closest('.lc-btn-submit');
    if (!submitBtn) return;

    const card = submitBtn.closest('.lc-question-card');
    if (!card) return;

    let earnedPoints = 0;
    const totalPoints = parseFloat(card.dataset.points || 1.0);
    const feedbackRegion = card.querySelector('.lc-feedback-region');

    if (card.classList.contains('lc-qtype-multipleChoice')) {
        const checked = card.querySelectorAll('.lc-option-input:checked');
        let pts = 0;
        checked.forEach(inp => {
            pts += parseFloat(inp.dataset.pts || 0);
        });
        earnedPoints = Math.max(0, pts);
    } else if (card.classList.contains('lc-qtype-trueFalseQuestion')) {
        const checked = card.querySelector('.lc-option-input:checked');
        if (checked) {
            const isTrue = checked.value === 'true';
            const expected = checked.dataset.correct === 'true';
            earnedPoints = (isTrue === expected) ? totalPoints : 0;
        }
    } else if (card.classList.contains('lc-qtype-simpleGapFill')) {
        const gap = card.querySelector('.lc-gap-input');
        if (gap) {
            const val = gap.value.trim();
            const accepted = JSON.parse(gap.dataset.accepted || '[]');
            const isCaseSensitive = gap.dataset.case === 'true';

            const match = accepted.some(ans => {
                return isCaseSensitive ? ans === val : ans.toLowerCase() === val.toLowerCase();
            });
            earnedPoints = match ? totalPoints : 0;
        }
    } else {
        earnedPoints = totalPoints;
    }

    let evalStatus = 'incorrect';
    let statusIcon = '✗';
    let statusTitle = 'Incorrect.';

    if (earnedPoints >= totalPoints && totalPoints > 0) {
        evalStatus = 'correct';
        statusIcon = '✓';
        statusTitle = 'Correct!';
    } else if (earnedPoints > 0 && earnedPoints < totalPoints) {
        evalStatus = 'partially-correct';
        statusIcon = '⚠';
        statusTitle = 'Partially Correct.';
    } else {
        evalStatus = 'incorrect';
        statusIcon = '✗';
        statusTitle = 'Incorrect.';
    }

    let message = '';
    if (evalStatus === 'correct') {
        message = `${statusTitle} You earned ${earnedPoints} out of ${totalPoints} point(s).`;
    } else if (evalStatus === 'partially-correct') {
        message = `${statusTitle} You earned ${earnedPoints} out of ${totalPoints} point(s). Please review your selections.`;
    } else {
        message = `${statusTitle} You earned 0 out of ${totalPoints} point(s). Please review the content and try again.`;
    }

    if (feedbackRegion) {
        feedbackRegion.className = 'lc-feedback-region ' + evalStatus;
        feedbackRegion.textContent = `${statusIcon} ${message}`;
    }

    if (window.jwAnnounce) {
        window.jwAnnounce(message, 'assertive');
    }

    if (window.xapi) {
        const globalId = card.dataset.globalId || 'lc-item';
        window.xapi.sendStatement({
            verb: {
                id: "http://adlnet.gov/expapi/verbs/answered",
                display: { "en-US": "answered" }
            },
            object: {
                id: window.location.href + "#" + globalId,
                definition: {
                    name: { "en-US": "LC-JSON Question (" + globalId + ")" }
                }
            },
            result: {
                score: { raw: earnedPoints, max: totalPoints },
                success: (evalStatus === 'correct')
            }
        });
    }
});


