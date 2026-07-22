<?php
/**
 * Superable Learning LMS - Registration Page
 * 
 * Secure self-registration gated by Course Codes.
 */

require_once 'config.php';
$pdo = get_db_connection();
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $course_code = strtoupper(trim($_POST['course_code'] ?? ''));

    if ($full_name && $email && $password) {
        try {
            $key_data = null;
            if ($course_code) {
                // 1. Validate Course Code if provided
                $stmt = $pdo->prepare("SELECT * FROM invitation_keys WHERE key_code = ? AND (uses_remaining > 0 OR uses_remaining = -1)");
                $stmt->execute([$course_code]);
                $key_data = $stmt->fetch();
                
                if (!$key_data) {
                    $error = "The course code you entered is invalid or expired. You can leave it blank to create a free account.";
                }
            }

            if (!$error) {
                // 2. Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "An account with this email already exists.";
                } else {
                    // 3. Create User
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
                    $stmt->execute([$full_name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $new_user_id = $pdo->lastInsertId();

                    // 4. Grant Course Permission if key is linked to a course
                    $course_title = '';
                    if ($key_data && $key_data['course_id']) {
                        $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, course_id) VALUES (?, ?)");
                        $stmt->execute([$new_user_id, $key_data['course_id']]);

                        // Fetch course title for the success message
                        $course_dir = getTenantCoursesDir() . DIRECTORY_SEPARATOR . $key_data['course_id'];
                        $manifest_path = $course_dir . DIRECTORY_SEPARATOR . 'course_structure.json';
                        if (file_exists($manifest_path)) {
                            $manifest = json_decode(file_get_contents($manifest_path), true);
                            $course_title = $manifest['properties']['title'] ?? $key_data['course_id'];
                        }
                    }

                    // 5. Update Key usage
                    if ($key_data && $key_data['uses_remaining'] > 0) {
                        $stmt = $pdo->prepare("UPDATE invitation_keys SET uses_remaining = uses_remaining - 1 WHERE id = ?");
                        $stmt->execute([$key_data['id']]);
                    }

                    $pdo->commit();
                    $success = "Account created successfully!";
                    if ($course_title) {
                        $success .= " You now have access to <strong>" . htmlspecialchars($course_title) . "</strong>.";
                    }
                    $success .= " You can now log in.";
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "System error during registration. Please try again.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Superable Learning</title>
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss() ?>
    <style>
        .step-hidden { display: none !important; }
        fieldset { border: none; padding: 0; margin: 0; }
    </style>
</head>
<body>
    <a href="#register-main" class="skip-link">Skip to main content</a>

    <main id="register-main" class="form-container">
        <h1 class="text-center mb-3">Create Your Account</h1>

        <?php if ($error): ?>
            <div role="alert" class="alert alert-critical"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div role="alert" class="alert alert-success flex-column">
                <div><?= $success ?></div>
                <div class="mt-2"><a href="login.php" class="btn btn-sm">Log In Now</a></div>
            </div>
        <?php else: ?>
            <div id="registration-wizard">
                <div class="progress-meter mb-3" role="progressbar" aria-valuemin="1" aria-valuemax="2" aria-valuenow="1" id="wizard-progress">
                    <div class="progress-fill" style="width: 50%;"></div>
                </div>

                <form method="POST" id="reg-form">
                    <!-- Step 1: Course Code -->
                    <fieldset id="step-1">
                        <legend class="sr-only">Step 1: Course Access (Optional)</legend>
                        <h2 class="text-xl mb-2" id="step-1-heading">Step 1: Course Access (Optional)</h2>
                        <div class="form-group">
                            <label for="course_code" class="form-label">Course Code</label>
                            <p class="text-sm text-neutral-mid" id="course-code-hint">If you have an invite code for a specific course, enter it here. Otherwise, leave blank to create an account for public courses.</p>
                            <input type="text" name="course_code" id="course_code" class="form-control" placeholder="e.g. CRSCD123" value="<?= htmlspecialchars($_POST['course_code'] ?? '') ?>" aria-describedby="course-code-hint course-code-error">
                            <div id="course-code-error" class="text-sm" style="color: var(--color-critical-text); font-weight: bold; margin-top: 0.25rem;" aria-live="polite"></div>
                        </div>
                        <button type="button" class="btn btn-full" id="btn-next">Continue</button>
                    </fieldset>

                    <!-- Step 2: Account Details -->
                    <fieldset id="step-2" class="step-hidden">
                        <legend class="sr-only">Step 2: Account Details</legend>
                        <h2 class="text-xl mb-2" id="step-2-heading">Step 2: Account Details</h2>
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control" required placeholder="Jane Doe" autocomplete="name">
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" required placeholder="jane@example.com" autocomplete="email">
                        </div>
                        <div class="form-group">
                            <label for="password" class="form-label">Choose Password</label>
                            <input type="password" name="password" id="password" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="flex-between gap-md">
                            <button type="button" class="btn btn-secondary" id="btn-prev">Back</button>
                            <button type="submit" class="btn" style="flex-grow: 1;">Create Account</button>
                        </div>
                    </fieldset>
                </form>
            </div>
            <p class="text-center text-sm mt-3 mb-0">
                Already have an account? <a href="login.php">Log in here</a>
            </p>
            <p class="text-center text-sm mt-1">
                <a href="index.php">&larr; Return to Home</a>
            </p>
        <?php endif; ?>
    </main>

    <?= renderTenantFooter() ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            const btnNext = document.getElementById('btn-next');
            const btnPrev = document.getElementById('btn-prev');
            const progressBar = document.getElementById('wizard-progress');
            const progressFill = progressBar.querySelector('.progress-fill');
            const courseCodeInput = document.getElementById('course_code');
            const step2Heading = document.getElementById('step-2-heading');
            const step1Heading = document.getElementById('step-1-heading');

            const courseCodeError = document.getElementById('course-code-error');

            function showStep(step) {
                if (step === 1) {
                    step1.classList.remove('step-hidden');
                    step2.classList.add('step-hidden');
                    progressBar.setAttribute('aria-valuenow', '1');
                    progressFill.style.width = '50%';
                    step1Heading.setAttribute('tabindex', '-1');
                    step1Heading.focus();
                } else {
                    step1.classList.add('step-hidden');
                    step2.classList.remove('step-hidden');
                    progressBar.setAttribute('aria-valuenow', '2');
                    progressFill.style.width = '100%';
                    step2Heading.setAttribute('tabindex', '-1');
                    step2Heading.focus();
                }
            }

            // Clear error when typing
            courseCodeInput.addEventListener('input', () => {
                courseCodeError.textContent = '';
                courseCodeInput.removeAttribute('aria-invalid');
            });

            btnNext.addEventListener('click', async () => {
                const code = courseCodeInput.value.trim();
                courseCodeError.textContent = ''; // Clear previous
                courseCodeInput.removeAttribute('aria-invalid');

                // Allow empty code for public access registration
                if (!code) {
                    showStep(2);
                    return;
                }

                // Optional: Real-time validation via API
                btnNext.disabled = true;
                btnNext.textContent = 'Validating...';

                try {
                    const response = await fetch(`api.php?action=validate_code&code=${encodeURIComponent(code)}`);
                    const data = await response.json();

                    if (data.valid) {
                        showStep(2);
                    } else {
                        courseCodeError.textContent = data.error || 'Invalid course code.';
                        courseCodeInput.setAttribute('aria-invalid', 'true');
                        courseCodeInput.focus();
                    }
                } catch (e) {
                    // Fallback to local validation if API fails
                    showStep(2);
                } finally {
                    btnNext.disabled = false;
                    btnNext.textContent = 'Continue';
                }
            });

            btnPrev.addEventListener('click', () => {
                showStep(1);
            });

            // If there was a server-side error, we might want to stay on step 2 if they had already progressed.
            // But usually PHP re-renders step 1. For simplicity, we'll let it reset but the value is preserved.
            <?php if ($error && !empty($_POST['course_code'])): ?>
                // If there's an error and we have a course code, they probably already hit "Next"
                // But let's check if they filled out other fields too.
                <?php if (!empty($_POST['full_name'])): ?>
                    showStep(2);
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
