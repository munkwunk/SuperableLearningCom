<?php
/**
 * Superable Learning LMS - API Endpoint
 * 
 * Handles asynchronous requests from the frontend to sync progress
 * and retrieve course state.
 */

require_once 'config.php';
session_start();

$jsonBody = [];
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonBody = json_decode($rawInput, true) ?: [];
    }
}

$tenantKey = $_REQUEST['tenant'] ?? $jsonBody['tenant'] ?? null;
$action = $_REQUEST['action'] ?? $jsonBody['action'] ?? null;

$pdo = get_db_connection($tenantKey);

// Public actions (No session required)
if ($action === 'validate_code') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_REQUEST['code'] ?? $jsonBody['code'] ?? ''));
    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing code']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT course_id FROM invitation_keys WHERE key_code = ? AND (uses_remaining > 0 OR uses_remaining = -1)");
        $stmt->execute([$code]);
        $key = $stmt->fetch();
        if ($key) {
            echo json_encode(['valid' => true, 'course_id' => $key['course_id']]);
        } else {
            echo json_encode(['valid' => false, 'error' => 'Invalid or expired course code.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// Allow guest identification for state and progress
$is_guest = !isset($_SESSION['user_id']);
$user_id = $is_guest ? 'guest_' . session_id() : $_SESSION['user_id'];

// Ensure logged-in user exists in active tenant DB to satisfy foreign key constraints
if (!$is_guest && is_numeric($user_id)) {
    try {
        $uCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $uCheck->execute([$user_id]);
        if (!$uCheck->fetch()) {
            // Sync user details from main database if available
            $mainDb = get_db_connection('local-dev');
            $mStmt = $mainDb->prepare("SELECT * FROM users WHERE id = ?");
            $mStmt->execute([$user_id]);
            $mUser = $mStmt->fetch();
            
            if ($mUser) {
                $ins = $pdo->prepare("INSERT OR IGNORE INTO users (id, email, password_hash, full_name, is_admin) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$mUser['id'], $mUser['email'], $mUser['password_hash'], $mUser['full_name'], $mUser['is_admin']]);
            } else {
                $userEmail = $_SESSION['email'] ?? ('user_' . $user_id . '@tenant.local');
                $userName = $_SESSION['full_name'] ?? 'LMS User';
                $ins = $pdo->prepare("INSERT OR IGNORE INTO users (id, email, password_hash, full_name, is_admin) VALUES (?, ?, 'stub_hash', ?, 0)");
                $ins->execute([$user_id, $userEmail, $userName]);
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to sync user to tenant DB: " . $e->getMessage());
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_state') {
        $course_id = $_GET['course_id'] ?? '';
        if (empty($course_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing course_id']);
            exit;
        }

        // Guests have no persistent state in the database
        if ($is_guest) {
            echo json_encode(['completed' => []]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT module_id FROM module_progress WHERE user_id = ? AND course_id = ? AND is_completed = 1");
            $stmt->execute([$user_id, $course_id]);
            $completed = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode(['completed' => $completed]);
        } catch (PDOException $e) {
            error_log("API Database Error (get_state): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $jsonBody['course_id'] ?? $_POST['course_id'] ?? '';
    $module_id = $jsonBody['module_id'] ?? $_POST['module_id'] ?? '';
    $action = $jsonBody['action'] ?? $_POST['action'] ?? $action;

    if ($action === 'mark_complete') {
        if (empty($course_id) || empty($module_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing course_id or module_id']);
            exit;
        }

        // Guests cannot save progress to the database, but we return success to allow the UI to update
        if ($is_guest) {
            echo json_encode(['status' => 'success', 'guest' => true]);
            exit;
        }

        try {
            // Check if record exists
            $stmt = $pdo->prepare("SELECT id FROM module_progress WHERE user_id = ? AND course_id = ? AND module_id = ?");
            $stmt->execute([$user_id, $course_id, $module_id]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Update timestamp if already completed
                $stmt = $pdo->prepare("UPDATE module_progress SET is_completed = 1, timestamp = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$exists['id']]);
            } else {
                // Insert new completion record
                $stmt = $pdo->prepare("INSERT INTO module_progress (user_id, course_id, module_id, is_completed) VALUES (?, ?, ?, 1)");
                $stmt->execute([$user_id, $course_id, $module_id]);
            }
            
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            error_log("API Database Error (mark_complete): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action or request method']);
