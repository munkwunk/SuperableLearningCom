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

        // Guests have no persistent progress, but we can resolve their last read module from telemetry
        if ($is_guest) {
            try {
                $stmtLast = $pdo->prepare("SELECT module_id FROM interaction_telemetry WHERE user_id = ? AND course_id = ? ORDER BY timestamp DESC LIMIT 1");
                $stmtLast->execute([$user_id, $course_id]);
                $lastActive = $stmtLast->fetchColumn() ?: null;
                
                // Fetch revealed accessible solutions for guest
                $stmtRev = $pdo->prepare("SELECT DISTINCT module_id FROM interaction_telemetry WHERE user_id = ? AND course_id = ? AND event_type = 'reveal_accessible'");
                $stmtRev->execute([$user_id, $course_id]);
                $revealed = $stmtRev->fetchAll(PDO::FETCH_COLUMN);

                echo json_encode([
                    'completed' => [],
                    'revealed' => $revealed,
                    'last_active_module_id' => $lastActive
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'completed' => [],
                    'revealed' => [],
                    'last_active_module_id' => null
                ]);
            }
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT module_id FROM module_progress WHERE user_id = ? AND course_id = ? AND is_completed = 1");
            $stmt->execute([$user_id, $course_id]);
            $completed = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Fetch revealed accessible solutions
            $stmtRev = $pdo->prepare("SELECT DISTINCT module_id FROM interaction_telemetry WHERE user_id = ? AND course_id = ? AND event_type = 'reveal_accessible'");
            $stmtRev->execute([$user_id, $course_id]);
            $revealed = $stmtRev->fetchAll(PDO::FETCH_COLUMN);

            // Fetch last active module ID (last completed or last telemetry action)
            $stmtLast = $pdo->prepare("
                SELECT module_id FROM (
                    SELECT module_id, timestamp FROM module_progress WHERE user_id = :uid AND course_id = :cid
                    UNION ALL
                    SELECT module_id, timestamp FROM interaction_telemetry WHERE user_id = :uid AND course_id = :cid
                ) ORDER BY timestamp DESC LIMIT 1
            ");
            $stmtLast->execute(['uid' => $user_id, 'cid' => $course_id]);
            $lastActive = $stmtLast->fetchColumn() ?: null;

            echo json_encode([
                'completed' => $completed,
                'revealed' => $revealed,
                'last_active_module_id' => $lastActive
            ]);
        } catch (PDOException $e) {
            error_log("API Database Error (get_state): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    } elseif ($action === 'get_user_metadata') {
        if ($is_guest) {
            echo json_encode([
                'id' => $user_id,
                'email' => 'guest@example.com',
                'full_name' => 'Guest User',
                'is_admin' => false,
                'role' => 'Guest'
            ]);
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, email, full_name, is_admin FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if ($user) {
                    echo json_encode([
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'full_name' => $user['full_name'],
                        'is_admin' => (bool)$user['is_admin'],
                        'role' => $user['is_admin'] ? 'Admin' : 'Student'
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
        exit;
    } elseif ($action === 'get_course_structure') {
        $course_id = $_GET['course_id'] ?? '';
        if (empty($course_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing course_id']);
            exit;
        }

        $course_dir = resolveCourseDir($course_id, $tenantKey);
        if (!$course_dir) {
            http_response_code(404);
            echo json_encode(['error' => 'Course not found']);
            exit;
        }

        $manifest_path = $course_dir . DIRECTORY_SEPARATOR . 'course_structure.json';
        if (!file_exists($manifest_path)) {
            http_response_code(404);
            echo json_encode(['error' => 'Course structure missing']);
            exit;
        }

        $content = file_get_contents($manifest_path);
        $manifest = json_decode($content, true);
        if ($manifest === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to parse course structure']);
            exit;
        }

        if (isset($manifest['modules'])) {
            pre_process_manifest_modules($manifest['modules'], $course_dir);
        }

        echo json_encode($manifest);
        exit;
    } elseif ($action === 'get_progress_logs') {
        $course_id = $_GET['course_id'] ?? '';
        if (empty($course_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing course_id']);
            exit;
        }

        $target_user = $user_id;
        $is_admin = false;

        if (!$is_guest && is_numeric($user_id)) {
            try {
                $uCheck = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
                $uCheck->execute([$user_id]);
                $uInfo = $uCheck->fetch();
                $is_admin = $uInfo && $uInfo['is_admin'];
            } catch (PDOException $e) {
                // Ignore
            }
        }

        if ($is_admin && !empty($_GET['target_user_id'])) {
            $target_user = $_GET['target_user_id'];
        }

        try {
            $stmt = $pdo->prepare("SELECT module_id, is_completed, timestamp FROM module_progress WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$target_user, $course_id]);
            $completions = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT module_id, event_type, event_value, timestamp FROM interaction_telemetry WHERE user_id = ? AND course_id = ? ORDER BY timestamp ASC");
            $stmt->execute([$target_user, $course_id]);
            $telemetry = $stmt->fetchAll();

            echo json_encode([
                'user_id' => $target_user,
                'course_id' => $course_id,
                'completions' => $completions,
                'telemetry' => $telemetry
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error retrieving logs: ' . $e->getMessage()]);
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
    } elseif ($action === 'log_interaction') {
        $course_id = $jsonBody['course_id'] ?? $_POST['course_id'] ?? '';
        $module_id = $jsonBody['module_id'] ?? $_POST['module_id'] ?? '';
        $event_type = $jsonBody['event_type'] ?? $_POST['event_type'] ?? '';
        $event_value = $jsonBody['event_value'] ?? $_POST['event_value'] ?? null;

        if (empty($course_id) || empty($module_id) || empty($event_type)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing course_id, module_id, or event_type']);
            exit;
        }

        if (is_array($event_value) || is_object($event_value)) {
            $event_value = json_encode($event_value);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO interaction_telemetry (user_id, course_id, module_id, event_type, event_value) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $course_id, $module_id, $event_type, $event_value]);

            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            error_log("API Database Error (log_interaction): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action or request method']);
