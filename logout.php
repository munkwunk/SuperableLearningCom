<?php
/**
 * Superable Learning LMS - Logout Page
 */

require_once 'config.php';
$tenant = resolveTenantKey();

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

$tenantQuery = ($tenant && $tenant !== 'local-dev') ? '?tenant=' . urlencode($tenant) : '';
header("Location: login.php" . $tenantQuery);
exit;
