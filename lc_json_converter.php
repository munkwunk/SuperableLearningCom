<?php
/**
 * Superable Learning LMS — LC-JSON 1.0 Specification Converter & Renderer
 * 
 * Converts LC-JSON Course and QuestionSet documents into WCAG 2.2 AA compliant
 * Superable Learning course packages (course_structure.json + modules/*.html).
 */

class LCJsonConverter {

    /**
     * Determines whether the provided JSON data conforms to LC-JSON structure.
     *
     * @param array|string $jsonData
     * @return bool
     */
    public static function isLCJson($jsonData) {
        if (is_string($jsonData)) {
            $jsonData = json_decode($jsonData, true);
        }
        if (!is_array($jsonData)) {
            return false;
        }

        // 1. Explicit schema or documentType check
        if (isset($jsonData['$schema']) && strpos($jsonData['$schema'], 'lc-json.org') !== false) {
            return true;
        }
        if (isset($jsonData['documentType']) && in_array($jsonData['documentType'], ['Course', 'QuestionSet'])) {
            return true;
        }

        // 2. Structural heuristic check (Course with units/lessons, or QuestionSet with questions)
        if (isset($jsonData['units']) && is_array($jsonData['units'])) {
            return true;
        }
        if (isset($jsonData['questions']) && is_array($jsonData['questions']) && !empty($jsonData['questions'])) {
            $first = reset($jsonData['questions']);
            if (is_array($first) && (isset($first['globalId']) || isset($first['type']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts an LC-JSON document into a full Superable Learning LMS course structure.
     *
     * @param array|string $lcJson
     * @param string $outputDir Destination directory path
     * @param array &$advisories Reference array for audit advisories
     * @return array Result array with status, title, and generated file list
     */
    public static function convert($lcJson, $outputDir, &$advisories = []) {
        if (is_string($lcJson)) {
            $lcJson = json_decode($lcJson, true);
        }

        if (!is_array($lcJson)) {
            return ['success' => false, 'message' => 'Invalid LC-JSON payload provided.'];
        }

        $docType = $lcJson['documentType'] ?? (isset($lcJson['units']) ? 'Course' : 'QuestionSet');
        $title = $lcJson['title'] ?? ($docType === 'Course' ? 'LC-JSON Course' : 'LC-JSON Question Set');
        $description = $lcJson['description'] ?? 'Imported from LC-JSON format.';

        // Ensure target directory structure exists
        $modulesDir = $outputDir . DIRECTORY_SEPARATOR . 'modules';
        $cssDir = $outputDir . DIRECTORY_SEPARATOR . 'css';
        $jsDir = $outputDir . DIRECTORY_SEPARATOR . 'js';

        if (!is_dir($modulesDir)) mkdir($modulesDir, 0755, true);
        if (!is_dir($cssDir)) mkdir($cssDir, 0755, true);
        if (!is_dir($jsDir)) mkdir($jsDir, 0755, true);

        // Generate default style.css if missing
        $defaultCssPath = $cssDir . DIRECTORY_SEPARATOR . 'style.css';
        if (!file_exists($defaultCssPath)) {
            file_put_contents($defaultCssPath, self::generateDefaultCss());
        }

        // Generate default main.js for interactive question evaluation & xAPI
        $defaultJsPath = $jsDir . DIRECTORY_SEPARATOR . 'main.js';
        if (!file_exists($defaultJsPath)) {
            file_put_contents($defaultJsPath, self::generateDefaultJs());
        }

        $modulesManifest = [];
        $generatedFiles = [];

        // Check for Superable custom extensions
        $accessConfig = $lcJson['x-superable-access'] ?? [
            'type' => 'public',
            'teaser_link' => ''
        ];
        $assetsConfig = $lcJson['x-superable-assets'] ?? [
            'css' => ['css/style.css'],
            'js' => ['js/main.js']
        ];

        if ($docType === 'Course' && isset($lcJson['units']) && is_array($lcJson['units'])) {
            $unitIdx = 1;
            foreach ($lcJson['units'] as $unit) {
                $unitTitle = $unit['title'] ?? ("Unit " . $unitIdx);
                $unitItems = [];

                if (isset($unit['lessons']) && is_array($unit['lessons'])) {
                    $lessonIdx = 1;
                    foreach ($unit['lessons'] as $lesson) {
                        $lessonTitle = $lesson['title'] ?? ("Lesson " . $lessonIdx);
                        $fileId = "u{$unitIdx}-l{$lessonIdx}";
                        
                        // Check custom src override or assign default module filename
                        $srcPath = $lesson['x-superable-src'] ?? ("modules/" . $fileId . ".html");
                        $targetHtmlFile = $outputDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $srcPath);

                        $htmlContent = self::renderLessonHtml($lesson, $lessonTitle, $fileId);
                        file_put_contents($targetHtmlFile, $htmlContent);
                        $generatedFiles[] = $srcPath;

                        $unitItems[] = [
                            'id' => $fileId,
                            'title' => htmlspecialchars($lessonTitle, ENT_QUOTES, 'UTF-8'),
                            'src' => $srcPath
                        ];

                        $lessonIdx++;
                    }
                }

                $modulesManifest[] = [
                    'group' => htmlspecialchars($unitTitle, ENT_QUOTES, 'UTF-8'),
                    'expanded' => ($unitIdx === 1),
                    'items' => $unitItems
                ];

                $unitIdx++;
            }
        } elseif ($docType === 'QuestionSet' || isset($lcJson['questions'])) {
            // Render standalone QuestionSet as an interactive quiz module
            $fileId = "quiz";
            $srcPath = "modules/quiz.html";
            $targetHtmlFile = $outputDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $srcPath);

            $questions = $lcJson['questions'] ?? [];
            $htmlContent = self::renderQuestionSetHtml($title, $description, $questions, $fileId);
            file_put_contents($targetHtmlFile, $htmlContent);
            $generatedFiles[] = $srcPath;

            $modulesManifest[] = [
                'group' => 'Interactive Assessment',
                'expanded' => true,
                'items' => [
                    [
                        'id' => $fileId,
                        'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                        'src' => $srcPath
                    ]
                ]
            ];
        }

        // Build master course_structure.json manifest
        $manifestData = [
            'properties' => [
                'title' => $title,
                'description' => $description,
                'thumbnail' => $lcJson['thumbnail'] ?? '',
                'access' => $accessConfig,
                'assets' => $assetsConfig,
                'lc_json' => [
                    'specVersion' => $lcJson['specVersion'] ?? '1.0',
                    'converted' => true,
                    'converted_at' => date('c')
                ]
            ],
            'modules' => $modulesManifest
        ];

        $manifestPath = $outputDir . DIRECTORY_SEPARATOR . 'course_structure.json';
        file_put_contents($manifestPath, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $generatedFiles[] = 'course_structure.json';

        $advisories[] = "[LC-JSON Import] Successfully converted LC-JSON '{$docType}' standard document into Superable LMS course package.";

        return [
            'success' => true,
            'title' => $title,
            'generated_files' => $generatedFiles,
            'manifest' => $manifestData
        ];
    }

    /**
     * Renders a single LC-JSON Lesson into a WCAG 2.2 AA compliant HTML module snippet.
     */
    private static function renderLessonHtml($lesson, $lessonTitle, $fileId) {
        $html = "<section class=\"lc-module-container\" id=\"module-{$fileId}\">\n";
        $html .= "  <h1 class=\"module-title\">" . htmlspecialchars($lessonTitle, ENT_QUOTES, 'UTF-8') . "</h1>\n";

        if (!empty($lesson['summary'])) {
            $html .= "  <p class=\"module-summary\">" . htmlspecialchars($lesson['summary'], ENT_QUOTES, 'UTF-8') . "</p>\n";
        }

        if (!empty($lesson['body'])) {
            $html .= "  <div class=\"module-body-text\">\n";
            $html .= "    " . nl2br(htmlspecialchars($lesson['body'], ENT_QUOTES, 'UTF-8')) . "\n";
            $html .= "  </div>\n";
        }

        // Render embedded items/questions if present in lesson
        if (isset($lesson['items']) && is_array($lesson['items'])) {
            foreach ($lesson['items'] as $item) {
                if (isset($item['question'])) {
                    $html .= self::renderQuestionHtml($item['question']);
                } elseif (isset($item['type'])) {
                    $html .= self::renderQuestionHtml($item);
                }
            }
        }

        if (isset($lesson['questions']) && is_array($lesson['questions'])) {
            foreach ($lesson['questions'] as $q) {
                $html .= self::renderQuestionHtml($q);
            }
        }

        $html .= "</section>\n";
        return $html;
    }

    /**
     * Renders an LC-JSON QuestionSet into a complete HTML quiz page.
     */
    private static function renderQuestionSetHtml($title, $description, $questions, $fileId) {
        $html = "<section class=\"lc-module-container lc-quiz-container\" id=\"module-{$fileId}\">\n";
        $html .= "  <h1 class=\"module-title\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h1>\n";
        
        if (!empty($description)) {
            $html .= "  <p class=\"quiz-description\">" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "</p>\n";
        }

        $html .= "  <form class=\"lc-quiz-form\" onsubmit=\"return false;\">\n";
        $idx = 1;
        foreach ($questions as $q) {
            $html .= self::renderQuestionHtml($q, $idx);
            $idx++;
        }
        $html .= "  </form>\n";
        $html .= "</section>\n";

        return $html;
    }

    /**
     * Renders an individual LC-JSON Question object into WCAG 2.2 AA compliant markup.
     */
    public static function renderQuestionHtml($q, $index = 1) {
        $type = $q['type'] ?? 'multipleChoice';
        $globalId = $q['globalId'] ?? ('q-' . uniqid());
        $title = $q['title'] ?? ("Question " . $index);
        $prompt = $q['prompt'] ?? '';
        $points = $q['points'] ?? 1.0;
        $difficulty = $q['difficulty'] ?? 5.0;

        $cleanGlobalId = preg_replace('/[^a-zA-Z0-9]/', '', $globalId);
        $promptId = "prompt_" . $cleanGlobalId;

        $html = "  <fieldset class=\"lc-question-card lc-qtype-{$type}\" data-global-id=\"" . htmlspecialchars($globalId, ENT_QUOTES, 'UTF-8') . "\" data-points=\"{$points}\" data-difficulty=\"{$difficulty}\">\n";
        $html .= "    <legend class=\"lc-question-legend\" style=\"display: block;\">\n";
        $html .= "      <span class=\"lc-question-title\">" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</span>\n";
        $html .= "      <span class=\"lc-points-badge\">(" . $points . " " . ($points == 1 ? "point" : "points") . ")</span>\n";

        if (!empty($prompt)) {
            $html .= "      <span id=\"{$promptId}\" class=\"lc-question-prompt\" style=\"display: block; font-weight: normal; margin-top: 0.5rem;\">" . htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8') . "</span>\n";
        }
        $html .= "    </legend>\n";

        switch ($type) {
            case 'multipleChoice':
                $html .= self::renderMultipleChoiceBody($q, $globalId);
                break;
            case 'trueFalseQuestion':
                $html .= self::renderTrueFalseBody($q, $globalId);
                break;
            case 'simpleGapFill':
                $html .= self::renderSimpleGapFillBody($q, $globalId);
                break;
            case 'wordBankCloze':
                $html .= self::renderWordBankClozeBody($q, $globalId);
                break;
            case 'shortAnswer':
            case 'essay':
                $html .= self::renderTextEntryBody($q, $globalId, $type);
                break;
            default:
                $html .= self::renderGenericQuestionBody($q, $globalId);
                break;
        }

        // Hint render if present
        if (!empty($q['hint'])) {
            $html .= "    <jw-click-reveal button-text=\"Show Hint\" hint=\"Request help for this item\">\n";
            $html .= "      <p><strong>Hint:</strong> " . htmlspecialchars($q['hint'], ENT_QUOTES, 'UTF-8') . "</p>\n";
            $html .= "    </jw-click-reveal>\n";
        }

        // Live ARIA feedback region
        $html .= "    <div class=\"lc-feedback-region\" role=\"status\" aria-live=\"polite\"></div>\n";
        $html .= "  </fieldset>\n";

        return $html;
    }

    private static function renderMultipleChoiceBody($q, $globalId) {
        $options = $q['options'] ?? [];
        $optionsAndPoints = $q['optionsAndPoints'] ?? [];
        $allowMultiple = $q['allowMultipleCorrect'] ?? false;
        $inputType = $allowMultiple ? 'checkbox' : 'radio';
        $cleanGlobalId = preg_replace('/[^a-zA-Z0-9]/', '', $globalId);
        $inputName = "lc_input_" . $cleanGlobalId;
        $promptId = "prompt_" . $cleanGlobalId;

        $html = "    <div class=\"lc-options-group\">\n";
        $optIdx = 0;
        foreach ($options as $opt) {
            $optId = $inputName . "_" . $optIdx;
            $pts = $optionsAndPoints[$opt] ?? 0.0;
            $html .= "      <div class=\"lc-option-item\">\n";
            $html .= "        <input type=\"{$inputType}\" id=\"{$optId}\" name=\"{$inputName}\" value=\"" . htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . "\" data-pts=\"{$pts}\" class=\"lc-option-input\" aria-describedby=\"{$promptId}\">\n";
            $html .= "        <label for=\"{$optId}\" class=\"lc-option-label\">" . htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . "</label>\n";
            $html .= "      </div>\n";
            $optIdx++;
        }
        $html .= "    </div>\n";

        $html .= "    <button type=\"button\" class=\"lc-btn-submit\" data-xapi-verb=\"ANSWERED\" data-xapi-name=\"LC-JSON Question " . htmlspecialchars($globalId, ENT_QUOTES, 'UTF-8') . "\">Check Answer</button>\n";
        return $html;
    }

    private static function renderTrueFalseBody($q, $globalId) {
        $cleanGlobalId = preg_replace('/[^a-zA-Z0-9]/', '', $globalId);
        $inputName = "lc_input_" . $cleanGlobalId;
        $promptId = "prompt_" . $cleanGlobalId;
        $correctVal = (isset($q['correctAnswer']) && $q['correctAnswer'] === true) ? 'true' : 'false';

        $html = "    <div class=\"lc-options-group lc-tf-group\">\n";
        $html .= "      <div class=\"lc-option-item\">\n";
        $html .= "        <input type=\"radio\" id=\"{$inputName}_t\" name=\"{$inputName}\" value=\"true\" data-correct=\"{$correctVal}\" class=\"lc-option-input\" aria-describedby=\"{$promptId}\">\n";
        $html .= "        <label for=\"{$inputName}_t\" class=\"lc-option-label\">True</label>\n";
        $html .= "      </div>\n";
        $html .= "      <div class=\"lc-option-item\">\n";
        $html .= "        <input type=\"radio\" id=\"{$inputName}_f\" name=\"{$inputName}\" value=\"false\" data-correct=\"{$correctVal}\" class=\"lc-option-input\" aria-describedby=\"{$promptId}\">\n";
        $html .= "        <label for=\"{$inputName}_f\" class=\"lc-option-label\">False</label>\n";
        $html .= "      </div>\n";
        $html .= "    </div>\n";

        $html .= "    <button type=\"button\" class=\"lc-btn-submit\" data-xapi-verb=\"ANSWERED\" data-xapi-name=\"True/False Question\">Check Answer</button>\n";
        return $html;
    }

    private static function renderSimpleGapFillBody($q, $globalId) {
        $sentence = $q['sentence'] ?? '@@@';
        $accepted = json_encode($q['acceptedAnswers'] ?? []);
        $caseSensitive = !empty($q['caseSensitive']) ? 'true' : 'false';

        $inputHtml = "<input type=\"text\" class=\"lc-gap-input\" aria-label=\"Fill in blank\" data-accepted='" . htmlspecialchars($accepted, ENT_QUOTES, 'UTF-8') . "' data-case=\"{$caseSensitive}\">";
        $escapedSentence = htmlspecialchars($sentence, ENT_QUOTES, 'UTF-8');
        $renderedSentence = str_replace('@@@', $inputHtml, $escapedSentence);

        $html = "    <div class=\"lc-gap-passage\">\n";
        $html .= "      <p>" . $renderedSentence . "</p>\n";
        $html .= "    </div>\n";

        $html .= "    <button type=\"button\" class=\"lc-btn-submit\" data-xapi-verb=\"ANSWERED\" data-xapi-name=\"Gap Fill Question\">Check Answer</button>\n";
        return $html;
    }

    private static function renderWordBankClozeBody($q, $globalId) {
        $passage = $q['passage'] ?? '';
        $wordBank = $q['wordBank'] ?? [];
        $answers = json_encode($q['gapAcceptedAnswers'] ?? []);

        $html = "    <div class=\"lc-word-bank-container\">\n";
        $html .= "      <span class=\"lc-word-bank-label\">Word Bank:</span>\n";
        $html .= "      <div class=\"lc-word-bank\">\n";
        foreach ($wordBank as $w) {
            $html .= "        <span class=\"lc-bank-word\">" . htmlspecialchars($w, ENT_QUOTES, 'UTF-8') . "</span>\n";
        }
        $html .= "      </div>\n";
        $html .= "    </div>\n";

        $renderedPassage = htmlspecialchars($passage, ENT_QUOTES, 'UTF-8');
        $renderedPassage = preg_replace_callback('/@@@(\d+)/', function($m) use ($answers) {
            $gapNum = $m[1];
            return "<input type=\"text\" class=\"lc-gap-input\" aria-label=\"Gap {$gapNum}\" data-gap=\"{$gapNum}\" data-answers='" . htmlspecialchars($answers, ENT_QUOTES, 'UTF-8') . "'>";
        }, $renderedPassage);

        $html .= "    <div class=\"lc-gap-passage\">\n";
        $html .= "      <p>{$renderedPassage}</p>\n";
        $html .= "    </div>\n";

        $html .= "    <button type=\"button\" class=\"lc-btn-submit\" data-xapi-verb=\"ANSWERED\" data-xapi-name=\"Word Bank Cloze\">Check Answers</button>\n";
        return $html;
    }

    private static function renderTextEntryBody($q, $globalId, $type) {
        $inputName = "lc_input_" . preg_replace('/[^a-zA-Z0-9]/', '', $globalId);
        $html = "    <div class=\"lc-text-entry-group\">\n";
        if ($type === 'essay') {
            $html .= "      <textarea id=\"{$inputName}\" name=\"{$inputName}\" class=\"lc-textarea\" rows=\"6\" aria-label=\"Your essay response\"></textarea>\n";
        } else {
            $html .= "      <input type=\"text\" id=\"{$inputName}\" name=\"{$inputName}\" class=\"lc-text-input\" aria-label=\"Your answer\">\n";
        }
        $html .= "    </div>\n";

        $html .= "    <button type=\"button\" class=\"lc-btn-submit\" data-xapi-verb=\"ANSWERED\" data-xapi-name=\"Text Entry\">Submit Response</button>\n";
        return $html;
    }

    private static function renderGenericQuestionBody($q, $globalId) {
        $html = "    <div class=\"lc-generic-question-body\">\n";
        $html .= "      <p><em>(Question type '" . htmlspecialchars($q['type'] ?? 'unknown', ENT_QUOTES, 'UTF-8') . "' rendered in standard interaction mode)</em></p>\n";
        $html .= "    </div>\n";
        $html .= "    <button type=\"button\" class=\"lc-btn-submit\" data-xapi-verb=\"ANSWERED\">Submit</button>\n";
        return $html;
    }

    private static function generateDefaultCss() {
        return <<<'CSS'
/* Superable Learning LMS — LC-JSON Default Component Styling */
.lc-module-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 1.5rem;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    color: #1e293b;
    line-height: 1.6;
}

.module-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
}

.module-summary {
    font-size: 1.125rem;
    color: #475569;
    margin-bottom: 1.5rem;
}

.lc-question-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.lc-question-legend {
    font-weight: 600;
    font-size: 1.1rem;
    padding: 0 0.5rem;
    color: #1e293b;
}

.lc-points-badge {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: normal;
    margin-left: 0.5rem;
}

.lc-question-prompt {
    font-size: 1.05rem;
    margin: 0.75rem 0 1rem 0;
}

.lc-options-group {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.lc-option-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f8fafc;
    transition: background-color 0.15s ease;
}

.lc-option-item:hover {
    background: #f1f5f9;
}

.lc-option-input {
    width: 1.2rem;
    height: 1.2rem;
    cursor: pointer;
}

.lc-option-input:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

.lc-option-label {
    cursor: pointer;
    width: 100%;
    font-size: 1rem;
}

.lc-btn-submit {
    background: #2563eb;
    color: #ffffff;
    border: none;
    padding: 0.6rem 1.25rem;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.lc-btn-submit:hover {
    background: #1d4ed8;
}

.lc-btn-submit:focus-visible {
    outline: 3px solid #93c5fd;
    outline-offset: 2px;
}

.lc-feedback-region {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-weight: 600;
}

.lc-feedback-region.correct {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.lc-feedback-region.partially-correct {
    background-color: #fffbeb;
    color: #b45309;
    border: 1px solid #fef3c7;
}

.lc-feedback-region.incorrect {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
    border: 1px solid #fecaca;
}

.lc-word-bank {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f1f5f9;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.lc-bank-word {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 0.25rem 0.6rem;
    border-radius: 4px;
    font-weight: 600;
}

.lc-gap-input, .lc-text-input, .lc-textarea {
    padding: 0.5rem;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 1rem;
}

.lc-textarea {
    width: 100%;
    box-sizing: border-box;
}
CSS;
    }

    private static function generateDefaultJs() {
        return <<<'JS'
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
JS;
    }
}
