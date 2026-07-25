<?php
/**
 * Superable Learning - Secure CLI Password Reset Utility
 * 
 * This script CANNOT be run via web browsers. It can only be executed
 * directly from the command line (CLI) for system security.
 */

if (php_sapi_name() !== 'cli') {
    header("HTTP/1.1 403 Forbidden");
    die("Forbidden: This utility is restricted to command-line (CLI) execution only.");
}

require_once __DIR__ . '/config.php';

echo "=== Superable Learning CLI Password Reset Tool ===\n\n";

// Get inputs
echo "Enter Tenant Key (default: superableaccessibility): ";
$tenantInput = trim(fgets(STDIN));
$tenantKey = empty($tenantInput) ? 'superableaccessibility' : $tenantInput;

echo "Enter User Email: ";
$email = trim(fgets(STDIN));
if (empty($email)) {
    die("Error: Email is required.\n");
}

echo "Enter New Password (min 8 chars): ";
$password = trim(fgets(STDIN));
if (strlen($password) < 8) {
    die("Error: Password must be at least 8 characters long.\n");
}

try {
    $pdo = get_db_connection($tenantKey);
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Offer to create if it doesn't exist
        echo "User '{$email}' not found. Would you like to create this user? (y/n): ";
        $choice = strtolower(trim(fgets(STDIN)));
        if ($choice === 'y') {
            echo "Enter Full Name: ";
            $fullName = trim(fgets(STDIN));
            if (empty($fullName)) { $fullName = 'Admin User'; }
            
            echo "Make this user an administrator? (y/n): ";
            $isAdmin = strtolower(trim(fgets(STDIN))) === 'y' ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, is_admin) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $fullName, $isAdmin]);
            echo "Success: Created user '{$email}' (Name: {$fullName}, Admin: " . ($isAdmin ? 'Yes' : 'No') . ") and set password.\n";
        } else {
            echo "Operation cancelled.\n";
        }
    } else {
        // Update password
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
        echo "Success: Updated password for user '{$user['full_name']}' ({$email}) in tenant '{$tenantKey}'.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
