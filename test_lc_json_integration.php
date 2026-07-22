<?php
/**
 * Superable Learning LMS — Automated LC-JSON Integration Test Suite
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lc_json_converter.php';
require_once __DIR__ . '/course_importer.php';

echo "====================================================\n";
echo " Superable Learning LMS — LC-JSON Test Suite\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($condition, $testName) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$testName}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$testName}\n";
        $failCount++;
    }
}

// Test 1: LCJsonConverter::isLCJson Detection
$validCourseJson = json_encode([
    '$schema' => 'https://lc-json.org/1.0/schemas/course.schema.json',
    'documentType' => 'Course',
    'title' => 'Test Course',
    'units' => []
]);
assertTest(LCJsonConverter::isLCJson($validCourseJson) === true, "isLCJson detects valid LC-JSON Course schema");

$validQuestionSetJson = json_encode([
    'documentType' => 'QuestionSet',
    'title' => 'Test Quiz',
    'questions' => [
        ['type' => 'trueFalseQuestion', 'globalId' => '123', 'prompt' => 'Is this a test?', 'correctAnswer' => true]
    ]
]);
assertTest(LCJsonConverter::isLCJson($validQuestionSetJson) === true, "isLCJson detects valid QuestionSet artifact");

$invalidJson = json_encode(['foo' => 'bar']);
assertTest(LCJsonConverter::isLCJson($invalidJson) === false, "isLCJson rejects non-LC-JSON payload");

// Test 2: LCJsonConverter::convert Execution
$sampleLcCourse = [
    '$schema' => 'https://lc-json.org/1.0/schemas/course.schema.json',
    'documentType' => 'Course',
    'specVersion' => '1.0',
    'title' => 'LC-JSON Master Test Course',
    'description' => 'Comprehensive test course for all LC-JSON question types.',
    'x-superable-access' => ['type' => 'public'],
    'units' => [
        [
            'title' => 'Unit 1: Core Accessibility',
            'lessons' => [
                [
                    'title' => 'Lesson 1: ARIA Basics',
                    'summary' => 'Introduction to ARIA patterns.',
                    'body' => 'Learn how ARIA attributes enhance accessibility.',
                    'questions' => [
                        [
                            'type' => 'multipleChoice',
                            'globalId' => 'q-mc-1',
                            'title' => 'Multiple Choice Test',
                            'prompt' => 'Which attribute indicates accordion state?',
                            'options' => ['aria-expanded', 'aria-hidden', 'aria-live'],
                            'optionsAndPoints' => ['aria-expanded' => 1.0, 'aria-hidden' => 0.0, 'aria-live' => 0.0],
                            'points' => 1.0
                        ],
                        [
                            'type' => 'trueFalseQuestion',
                            'globalId' => 'q-tf-1',
                            'title' => 'True/False Test',
                            'prompt' => 'Native <button> elements handle Space and Enter keys automatically.',
                            'correctAnswer' => true,
                            'points' => 1.0
                        ]
                    ]
                ]
            ]
        ]
    ]
];

$testOutputDir = __DIR__ . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . 'superableaccessibility' . DIRECTORY_SEPARATOR . 'lc-json-showcase';
if (!is_dir($testOutputDir)) {
    mkdir($testOutputDir, 0755, true);
}

$advisories = [];
$convResult = LCJsonConverter::convert($sampleLcCourse, $testOutputDir, $advisories);

assertTest($convResult['success'] === true, "LCJsonConverter converts Course structure successfully");
assertTest(file_exists($testOutputDir . '/course_structure.json'), "course_structure.json manifest file generated");
assertTest(file_exists($testOutputDir . '/modules/u1-l1.html'), "Lesson HTML fragment u1-l1.html generated");
assertTest(file_exists($testOutputDir . '/css/style.css'), "Default CSS asset generated");
assertTest(file_exists($testOutputDir . '/js/main.js'), "Default JS asset generated");

// Inspect generated module HTML for accessibility & render features
$htmlContent = file_get_contents($testOutputDir . '/modules/u1-l1.html');
assertTest(strpos($htmlContent, 'class="lc-question-card lc-qtype-multipleChoice"') !== false, "MultipleChoice question card rendered");
assertTest(strpos($htmlContent, 'class="lc-question-card lc-qtype-trueFalseQuestion"') !== false, "TrueFalse question card rendered");
assertTest(strpos($htmlContent, 'role="status"') !== false, "ARIA live region present in rendered module");

echo "\n----------------------------------------------------\n";
echo " Test Results: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================\n";

if ($failCount > 0) {
    exit(1);
}
