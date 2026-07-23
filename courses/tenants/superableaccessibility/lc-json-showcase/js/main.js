/**
 * Superable Learning LMS — LC-JSON Client Runtime & Evaluation Engine
 */
document.addEventListener('click', (e) => {
    const submitBtn = e.target.closest('.lc-btn-submit');
    if (!submitBtn) return;

    const card = submitBtn.closest('.lc-question-card');
    if (!card) return;

    let isCorrect = false;
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
        isCorrect = earnedPoints > 0;
    } else if (card.classList.contains('lc-qtype-trueFalseQuestion')) {
        const checked = card.querySelector('.lc-option-input:checked');
        if (checked) {
            const isTrue = checked.value === 'true';
            const expected = checked.dataset.correct === 'true';
            isCorrect = (isTrue === expected);
            earnedPoints = isCorrect ? totalPoints : 0;
        }
    } else if (card.classList.contains('lc-qtype-simpleGapFill')) {
        const gap = card.querySelector('.lc-gap-input');
        if (gap) {
            const val = gap.value.trim();
            const accepted = JSON.parse(gap.dataset.accepted || '[]');
            const isCaseSensitive = gap.dataset.case === 'true';

            isCorrect = accepted.some(ans => {
                return isCaseSensitive ? ans === val : ans.toLowerCase() === val.toLowerCase();
            });
            earnedPoints = isCorrect ? totalPoints : 0;
        }
    } else {
        isCorrect = true;
        earnedPoints = totalPoints;
    }

    const message = isCorrect 
        ? `Correct! You earned ${earnedPoints} out of ${totalPoints} point(s).` 
        : `Incorrect. Please review your selection and try again.`;

    if (feedbackRegion) {
        feedbackRegion.className = 'lc-feedback-region ' + (isCorrect ? 'correct' : 'incorrect');
        feedbackRegion.textContent = (isCorrect ? '✓ ' : '✗ ') + message;
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
                success: isCorrect
            }
        });
    }
});