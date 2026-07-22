<?php
/**
 * Superable Learning LMS - Login Page
 */

require_once 'config.php';
$pdo = get_db_connection();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    $tenant_param = !empty($_GET['tenant']) ? '?tenant=' . urlencode($_GET['tenant']) : '';
    header("Location: index.php" . $tenant_param);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            $stmt = $pdo->prepare("SELECT id, password_hash, full_name, is_admin FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful Login
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                
                $tenant_param = !empty($_GET['tenant']) ? '?tenant=' . urlencode($_GET['tenant']) : '';
                header("Location: index.php" . $tenant_param);
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "System error. Please try again later.";
        }
    } else {
        $error = "Please enter both email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Superable Learning</title>
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss() ?>
</head>
<body>
    <a href="#login-main" class="skip-link">Skip to main content</a>
    
    <main id="login-main" class="form-container">
        <h1 class="text-center mb-3">Login to Superable Learning</h1>

        <?php if ($error): ?>
            <div role="alert" class="alert alert-critical">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-full">Login</button>
        </form>

        <p class="text-center mt-3 mb-0">
            Need an account? <a href="<?= tenant_url('register.php') ?>">Register with a course code</a>
        </p>

        <?php 
        $activeTenant = resolveTenantKey();
        if ($activeTenant === 'local-dev' && empty($_GET['tenant'])): 
            $availableTenants = getAvailableTenants();
        ?>
            <div class="mt-4 pt-3" style="border-top: 1px dashed #CBD5E1; text-align: left;">
                <h2 class="text-sm font-bold mb-1" style="color: var(--color-primary);">Looking for your Organization Portal?</h2>
                <p class="text-xs text-neutral-mid mb-2">
                    End-users should log in via their parent organization's portal code or subdomain:
                </p>
                <form action="index.php" method="GET" class="flex gap-2">
                    <input type="text" name="tenant" class="form-control text-sm" placeholder="Organization code (e.g. acme)" required>
                    <button type="submit" class="btn btn-sm btn-primary" style="white-space: nowrap;">Go &rarr;</button>
                </form>
                <?php if (!empty($availableTenants)): ?>
                    <div class="mt-2">
                        <select class="form-control text-xs" onchange="if(this.value) window.location.href='login.php?tenant=' + encodeURIComponent(this.value);">
                            <option value="">-- Or select active portal --</option>
                            <?php foreach ($availableTenants as $t): ?>
                                <option value="<?= htmlspecialchars($t['tenant_key']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="text-center mt-3 mb-0">
            <a href="index.php">&larr; Return to Home</a>
        </p>
    </main>

    <?= renderTenantFooter() ?>
</body>
</html>
