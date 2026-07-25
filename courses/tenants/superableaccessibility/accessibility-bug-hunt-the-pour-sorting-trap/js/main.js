/* ============================================================
   The POUR Sorting Trap — course logic
   All listeners are bound via event delegation on `document`,
   since modules are injected dynamically by the LMS player and
   won't exist at script load time.
   ============================================================ */

(function () {
    'use strict';

    var draggedCard = null;

    /* ---------- Mouse drag-and-drop (this works correctly) ---------- */

    document.addEventListener('dragstart', function (e) {
        if (e.target.matches('.pour-card')) {
            draggedCard = e.target;
            e.dataTransfer.setData('text/plain', e.target.dataset.principle || '');
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    document.addEventListener('dragover', function (e) {
        if (e.target.closest('.zone-drop-area')) {
            e.preventDefault();
        }
    });

    document.addEventListener('drop', function (e) {
        var zone = e.target.closest('.zone-drop-area');
        if (zone && draggedCard) {
            e.preventDefault();
            zone.classList.remove('zone-correct', 'zone-incorrect');
            zone.appendChild(draggedCard);
            draggedCard = null;
        }
    });

    /* -----------------------------------------------------------------
       BUG (Operable — keyboard trap-ish failure): Space toggles a
       "lifted" visual state, matching the instructions. Arrow keys and
       Escape, both promised in the on-screen instructions, are
       intentionally NOT implemented anywhere in this file. A keyboard
       user can lift a card but has no way to actually move it into a
       drop zone or cancel out cleanly.
       ----------------------------------------------------------------- */
    document.addEventListener('keydown', function (e) {
        if (!e.target.matches('.pour-card')) {
            return;
        }

        if (e.code === 'Space' || e.key === ' ') {
            e.preventDefault();
            e.target.classList.toggle('lifted');
            e.target.setAttribute(
                'aria-pressed',
                e.target.classList.contains('lifted') ? 'true' : 'false'
            );
        }

        // Intentionally no handling for ArrowUp/ArrowDown/ArrowLeft/
        // ArrowRight or Escape here — this is the demo's core bug.
    });

    /* ---------- Submit / grading ---------- */

    document.addEventListener('click', function (e) {
        if (e.target.matches('#pourSubmitBtn')) {
            gradeBoard();
        } else if (e.target.matches('#pourModalCloseBtn')) {
            var modal = document.getElementById('pourFeedbackModal');
            if (modal) {
                modal.hidden = true;
            }
        } else if (e.target.matches('#fakeAltLink')) {
            // Flavor for mouse users who do click it, to land the joke
            // during debrief: it "works" for a mouse, just not a keyboard.
            window.alert(
                'You found the "keyboard alternative" \u2014 with your mouse. ' +
                'A keyboard-only user could never Tab to this element.'
            );
        }
    });

    function gradeBoard() {
        var zones = document.querySelectorAll('.zone-drop-area');
        var totalCards = document.querySelectorAll('.pour-card').length;
        var correctCount = 0;
        var placedCount = 0;

        zones.forEach(function (zone) {
            var expected = zone.dataset.zone;
            zone.classList.remove('zone-correct', 'zone-incorrect');

            var cards = zone.querySelectorAll('.pour-card');
            if (cards.length === 0) {
                return;
            }

            var zoneIsFullyCorrect = true;
            cards.forEach(function (card) {
                placedCount++;
                if (card.dataset.principle === expected) {
                    correctCount++;
                } else {
                    zoneIsFullyCorrect = false;
                }
            });

            // BUG (color alone conveys meaning): correctness is shown
            // purely via border/background color class, no icon or text.
            zone.classList.add(zoneIsFullyCorrect ? 'zone-correct' : 'zone-incorrect');
        });

        var scoreEl = document.getElementById('pourScoreText');
        if (scoreEl) {
            scoreEl.textContent =
                'You placed ' + correctCount + ' of ' + totalCards + ' cards correctly (' +
                placedCount + ' of ' + totalCards + ' sorted so far).';
        }

        var modal = document.getElementById('pourFeedbackModal');
        if (modal) {
            modal.hidden = false;
            // BUG (focus management): intentionally NOT moving focus into
            // the modal and NOT trapping focus inside it. Focus stays on
            // the Submit button, so screen reader and keyboard users get
            // no signal that a dialog has appeared.
        }

        if (window.xapi) {
            window.xapi.sendStatement({
                verb: {
                    id: 'http://adlnet.gov/expapi/verbs/answered',
                    display: { 'en-US': 'answered' }
                },
                object: {
                    id: window.location.href + '#pour-sorting-trap',
                    definition: {
                        name: { 'en-US': 'POUR Sorting Trap' },
                        description: { 'en-US': 'Sorted accessibility examples into POUR categories.' }
                    }
                },
                result: {
                    score: {
                        raw: correctCount,
                        min: 0,
                        max: totalCards,
                        scaled: totalCards > 0 ? correctCount / totalCards : 0
                    },
                    completion: placedCount === totalCards,
                    success: correctCount === totalCards
                }
            });
        }
    }
})();
