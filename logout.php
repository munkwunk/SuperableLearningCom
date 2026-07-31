<?php
/**
 * Superable Learning LMS - Logout Page
 */

require_once 'config.php';
$tenant = resolveTenantKey();

$pdo = get_db_connection();

// If remember cookie exists, clear it and delete the db token
$cookieName = 'remember_me_' . $tenant;
if (isset($_COOKIE[$cookieName]) && isset($_SESSION['user_id'])) {
    $cookieValue = $_COOKIE[$cookieName];
    $parts = explode(':', $cookieValue, 2);
    if (count($parts) === 2) {
        $token = $parts[1];
        $tokenHash = hash('sha256', $token);
        try {
            $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ? AND token_hash = ?");
            $stmt->execute([$_SESSION['user_id'], $tokenHash]);
        } catch (PDOException $e) {
            // Ignore db errors during logout
        }
    }
}
clear_remember_me_cookie($tenant);

// Sign out of THIS tenant only. Identities are stored per tenant, so a platform admin
// signing out of a client portal they were inspecting keeps their platform session.
clear_tenant_identity($tenant);

// If no tenant identities remain, tear the session down completely.
if (empty($_SESSION['tenant_identities'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
} else {
    // Rotate the session id so the signed-out tenant's session identifier cannot be replayed.
    session_regenerate_id(true);
}

$tenantQuery = ($tenant && $tenant !== 'platform') ? '?tenant=' . urlencode($tenant) : '';
header("Location: login.php" . $tenantQuery);
exit;
