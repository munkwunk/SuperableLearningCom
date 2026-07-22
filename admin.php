<?php
/**
 * Superable Learning LMS - Multi-Tenant Admin Dashboard & Course Manager
 * 
 * Features:
 * - Tabbed Admin Management Interface (Courses, Media, Branding, Users, Keys, Plan & Storage)
 * - User Management & Permission Assignment
 * - Invitation Key Management
 * - Multi-Tenant Course & Module Management Suite (ZIP Importer, Access Manager, Teaser & XCL Links)
 * - Custom Tenant Branding & Logo Manager with WCAG Contrast Verification
 * - Course Media Asset Manager for LLM / AI Course Authors
 * - Storage Quota Progress Meter (500 MB Cap)
 * - CSRF Security & Tenant Status Checks
 */

require_once 'config.php';
require_once 'course_importer.php';

$pdo = get_db_connection();

// Admin Security Check
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header("Location: index.php");
    exit;
}

$tenantMetadata = getTenantMetadata();
$activeTenant = resolveTenantKey();

// Account Status Check
if (isset($tenantMetadata['status']) && $tenantMetadata['status'] === 'suspended') {
    die("Account Suspended: This tenant account is currently suspended. Please contact platform support.");
}

$message = '';
$message_type = 'success';
$advisories = [];

// Handle Form Submissions with CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();

    switch ($_POST['action']) {
        case 'upload_course':
            if (isset($_FILES['course_zip']) && $_FILES['course_zip']['error'] === UPLOAD_ERR_OK) {
                $res = CourseImporter::importZip($_FILES['course_zip']['tmp_name']);
                $message = $res['message'];
                $message_type = $res['success'] ? 'success' : 'critical';
                if (!empty($res['advisories'])) {
                    $advisories = $res['advisories'];
                }
                logTenantActivity('Course Upload', 'ZIP file: ' . $_FILES['course_zip']['name'] . ' | Success: ' . ($res['success'] ? 'Yes' : 'No') . ' | Message: ' . $message);
            } else {
                $message = "Upload Error: Please select a valid ZIP course package file.";
                $message_type = 'critical';
                logTenantActivity('Course Upload Failed', 'No file uploaded');
            }
            break;

        case 'update_course_manifest':
            $course_id = trim($_POST['course_id'] ?? '');
            if ($course_id) {
                $status = $_POST['status'] ?? 'published';
                $tenantPlan = getTenantPlan();
                if ($tenantPlan !== 'premium') {
                    $status = 'published';
                }
                $res = CourseImporter::updateCourseManifest($course_id, [
                    'title'       => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'access_type' => $_POST['access_type'] ?? 'public',
                    'teaser_link' => $_POST['teaser_link'] ?? '',
                    'xcl_url'     => $_POST['xcl_url'] ?? '',
                    'status'      => $status
                ]);
                $message = $res['message'];
                $message_type = $res['success'] ? 'success' : 'critical';

                // Handle manual course sorting shift if a custom position is specified
                if ($res['success'] && isset($_POST['sort_position'])) {
                    $newPos = intval($_POST['sort_position']) - 1; // 1-indexed to 0-indexed
                    
                    $courses_list = [];
                    $courses_dir = getTenantCoursesDir();
                    if (is_dir($courses_dir)) {
                        foreach (scandir($courses_dir) as $folder) {
                            if ($folder === '.' || $folder === '..' || $folder === 'tenants' || $folder === '.backups') continue;
                            $manifest_path = $courses_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'course_structure.json';
                            if (file_exists($manifest_path)) {
                                $manifest = json_decode(file_get_contents($manifest_path), true);
                                $courses_list[] = [
                                    'id'    => $folder,
                                    'title' => $manifest['properties']['title'] ?? $folder
                                ];
                            }
                        }
                    }
                    
                    $meta = getTenantMetadata($activeTenant);
                    $sortMode = $meta['course_sort_mode'] ?? 'custom';
                    
                    if ($sortMode === 'alpha_asc') {
                        usort($courses_list, function($a, $b) { return strcasecmp($a['title'], $b['title']); });
                    } elseif ($sortMode === 'alpha_desc') {
                        usort($courses_list, function($a, $b) { return strcasecmp($b['title'], $a['title']); });
                    } elseif ($sortMode === 'newest') {
                        usort($courses_list, function($a, $b) use ($courses_dir) {
                            $timeA = @filemtime($courses_dir . DIRECTORY_SEPARATOR . $a['id']);
                            $timeB = @filemtime($courses_dir . DIRECTORY_SEPARATOR . $b['id']);
                            return $timeB <=> $timeA;
                        });
                    } else {
                        $customOrder = $meta['course_order'] ?? [];
                        if (!empty($customOrder) && is_array($customOrder)) {
                            $orderMap = array_flip($customOrder);
                            usort($courses_list, function($a, $b) use ($orderMap) {
                                $posA = $orderMap[$a['id']] ?? 9999;
                                $posB = $orderMap[$b['id']] ?? 9999;
                                if ($posA === $posB) return strcasecmp($a['title'], $b['title']);
                                return $posA <=> $posB;
                            });
                        } else {
                            usort($courses_list, function($a, $b) { return strcasecmp($a['title'], $b['title']); });
                        }
                    }
                    
                    $currentIds = array_map(function($c) { return $c['id']; }, $courses_list);
                    $currentIds = array_values(array_filter($currentIds, function($id) use ($course_id) {
                        return $id !== $course_id;
                    }));
                    
                    $newPos = max(0, min(count($currentIds), $newPos));
                    array_splice($currentIds, $newPos, 0, $course_id);
                    
                    $meta['course_order'] = $currentIds;
                    $meta['course_sort_mode'] = 'custom'; // Automatically shift to custom mode on manual rearrange
                    
                    $tenantsDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
                    $jsonPath = $tenantsDir . DIRECTORY_SEPARATOR . $activeTenant . '.json';
                    file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT));
                    
                    $message .= " Course position updated to " . ($_POST['sort_position']) . ".";
                    logTenantActivity('Course Sort', "Course ID: {$course_id} moved to position " . $_POST['sort_position']);
                }
            }
            break;

        case 'update_course_sort':
            $sortMode = trim($_POST['course_sort_mode'] ?? 'custom');
            $meta = getTenantMetadata($activeTenant);
            $meta['course_sort_mode'] = $sortMode;
            
            $tenantsDir = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants';
            $jsonPath = $tenantsDir . DIRECTORY_SEPARATOR . $activeTenant . '.json';
            file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT));
            
            $message = "Sorting preference updated to: " . str_replace('_', ' ', strtoupper($sortMode));
            $message_type = 'success';
            logTenantActivity('Sort Preference Update', "Preference set to: {$sortMode}");
            break;

        case 'delete_course':
            $course_id = trim($_POST['course_id'] ?? '');
            if ($course_id) {
                $res = CourseImporter::deleteCourse($course_id);
                $message = $res['message'];
                $message_type = $res['success'] ? 'success' : 'critical';
                logTenantActivity('Course Delete', 'Course ID: ' . $course_id . ' | Result: ' . $message);
            }
            break;

        case 'create_user':
            $name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $is_admin_flag = isset($_POST['is_admin']) ? 1 : 0;
            
            if ($name && $email && $password) {
                try {
                    $tenantPlan = getTenantPlan();
                    if ($is_admin_flag) {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1");
                        $adminCount = (int)$stmt->fetchColumn();
                        $adminLimit = ($tenantPlan === 'premium') ? 3 : 1;
                        if ($adminCount >= $adminLimit) {
                            $message = "Error creating user: Your plan (" . ucfirst($tenantPlan) . ") is limited to {$adminLimit} admin account" . ($adminLimit > 1 ? "s." : ".");
                            $message_type = 'critical';
                            break;
                        }
                    }
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $is_admin_flag]);
                    $message = "User '$name' created successfully.";
                } catch (PDOException $e) {
                    $message = "Error creating user: " . $e->getMessage();
                    $message_type = 'critical';
                }
            }
            break;

        case 'grant_permission':
            $user_id = (int)$_POST['user_id'];
            $course_id = trim($_POST['course_id']);
            if ($user_id && $course_id) {
                try {
                    $stmt = $pdo->prepare("INSERT OR IGNORE INTO user_permissions (user_id, course_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $course_id]);
                    $message = "Permission granted.";
                } catch (PDOException $e) {
                    $message = "Error granting permission: " . $e->getMessage();
                    $message_type = 'critical';
                }
            }
            break;

        case 'revoke_permission':
            $perm_id = (int)$_POST['permission_id'];
            if ($perm_id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE id = ?");
                    $stmt->execute([$perm_id]);
                    $message = "Permission revoked.";
                } catch (PDOException $e) {
                    $message = "Error revoking permission: " . $e->getMessage();
                    $message_type = 'critical';
                }
            }
            break;

        case 'reset_password':
            $user_id = (int)$_POST['user_id'];
            $new_pass = $_POST['new_password'];
            if ($user_id && $new_pass) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([password_hash($new_pass, PASSWORD_DEFAULT), $user_id]);
                    $message = "Password reset for user ID $user_id.";
                } catch (PDOException $e) {
                    $message = "Error resetting password: " . $e->getMessage();
                    $message_type = 'critical';
                }
            }
            break;

        case 'create_invitation_key':
            $key_code = strtoupper(trim($_POST['key_code']));
            $course_id = trim($_POST['course_id'] ?? '');
            $uses = (int)($_POST['uses_remaining'] ?? -1);

            if ($key_code) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO invitation_keys (key_code, course_id, uses_remaining) VALUES (?, ?, ?)");
                    $stmt->execute([$key_code, $course_id ?: null, $uses]);
                    $message = "Invitation key '$key_code' created successfully.";
                } catch (PDOException $e) {
                    $message = "Error creating key: " . $e->getMessage();
                    $message_type = 'critical';
                }
            }
            break;

        case 'update_key_uses':
            $key_id = (int)$_POST['key_id'];
            $uses = (int)$_POST['uses_remaining'];
            if ($key_id) {
                try {
                    $stmt = $pdo->prepare("UPDATE invitation_keys SET uses_remaining = ? WHERE id = ?");
                    $stmt->execute([$uses, $key_id]);
                    $message = "Usage count updated.";
                } catch (PDOException $e) {
                    $message = "Error updating uses: " . $e->getMessage();
                    $message_type = 'critical';
                }
            }
            break;

        case 'upload_logo':
            if (isset($_FILES['tenant_logo']) && $_FILES['tenant_logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['tenant_logo']['name'], PATHINFO_EXTENSION));
                $allowed = ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
                if (in_array($ext, $allowed)) {
                    if ($_FILES['tenant_logo']['size'] <= 5242880) { // 5MB limit
                        $tenantKey = resolveTenantKey();
                        $targetDir = getTenantCoursesDir();
                        if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
                        
                        $fileName = 'logo_' . time() . '.' . $ext;
                        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
                        
                        if (move_uploaded_file($_FILES['tenant_logo']['tmp_name'], $targetPath)) {
                            $webPath = getTenantCoursesWebPath() . '/' . $fileName;
                            $meta = getTenantMetadata();
                            $meta['logo_url'] = $webPath;
                            
                            $jsonPath = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey . '.json';
                            file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT));
                            
                            $message = "Tenant logo uploaded successfully!";
                            $message_type = 'success';
                            logTenantActivity('Logo Upload', 'Logo: ' . $_FILES['tenant_logo']['name']);
                        } else {
                            $message = "Failed to save uploaded logo file.";
                            $message_type = 'critical';
                            logTenantActivity('Logo Upload Failed', 'Save failed');
                        }
                    } else {
                        $message = "Logo file exceeds maximum size limit (5 MB).";
                        $message_type = 'critical';
                        logTenantActivity('Logo Upload Failed', 'File exceeds 5MB');
                    }
                } else {
                    $message = "Invalid logo format. Allowed types: SVG, PNG, JPG, GIF, WEBP.";
                    $message_type = 'critical';
                    logTenantActivity('Logo Upload Failed', 'Invalid format: ' . $ext);
                }
            } else {
                $message = "Please select a valid image file to upload.";
                $message_type = 'critical';
                logTenantActivity('Logo Upload Failed', 'No valid file');
            }
            break;

        case 'remove_logo':
            $meta = getTenantMetadata();
            unset($meta['logo_url']);
            $tenantKey = resolveTenantKey();
            $jsonPath = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey . '.json';
            file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT));
            $message = "Custom logo removed. Default logo restored.";
            $message_type = 'success';
            logTenantActivity('Logo Remove', 'Custom logo removed');
            break;

        case 'update_tenant_branding':
            $meta = getTenantMetadata();
            $tenantKey = resolveTenantKey();
            $meta['copyright_notice'] = trim($_POST['copyright_notice'] ?? '');
            $meta['website_url'] = trim($_POST['website_url'] ?? '');
            $meta['font_family'] = trim($_POST['font_family'] ?? 'Atkinson Hyperlegible');
            $meta['hero_headline'] = trim($_POST['hero_headline'] ?? '');
            $meta['hero_subheadline'] = trim($_POST['hero_subheadline'] ?? '');
            $meta['support_contact'] = trim($_POST['support_contact'] ?? '');
            $meta['terms_url'] = trim($_POST['terms_url'] ?? '');
            $meta['privacy_url'] = trim($_POST['privacy_url'] ?? '');
            $meta['branding'] = [
                'primary'   => trim($_POST['primary_color'] ?? ''),
                'secondary' => trim($_POST['secondary_color'] ?? ''),
                'accent'    => trim($_POST['accent_color'] ?? '')
            ];
            $jsonPath = getTenantBaseDir() . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $tenantKey . '.json';
            file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT));
            $message = "Branding & Logo settings saved successfully!";
            $message_type = 'success';
            logTenantActivity('Branding Update', 'Colors: ' . $_POST['primary_color'] . ', ' . $_POST['secondary_color'] . ', ' . $_POST['accent_color']);
            break;

        case 'upload_course_asset':
            $course_id = trim($_POST['course_id'] ?? '');
            if ($course_id && isset($_FILES['asset_file']) && $_FILES['asset_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['asset_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
                if (in_array($ext, $allowed)) {
                    $assetsDir = getTenantCoursesDir() . DIRECTORY_SEPARATOR . $course_id . DIRECTORY_SEPARATOR . 'assets';
                    if (!is_dir($assetsDir)) @mkdir($assetsDir, 0755, true);
                    
                    $cleanName = preg_replace('/[^a-z0-9_\-\.]/i', '_', $_FILES['asset_file']['name']);
                    $targetPath = $assetsDir . DIRECTORY_SEPARATOR . $cleanName;
                    
                    if (move_uploaded_file($_FILES['asset_file']['tmp_name'], $targetPath)) {
                        $message = "Course image uploaded! Relative path: assets/" . htmlspecialchars($cleanName) . " | HTML tag: &lt;img src=\"assets/" . htmlspecialchars($cleanName) . "\" alt=\"Description\"&gt;";
                        $message_type = 'success';
                    } else {
                        $message = "Failed to save course image asset.";
                        $message_type = 'critical';
                    }
                } else {
                    $message = "Invalid image asset format. Allowed: PNG, JPG, GIF, SVG, WEBP.";
                    $message_type = 'critical';
                }
            } else {
                $message = "Please select a valid course image asset.";
                $message_type = 'critical';
            }
            break;

        case 'update_custom_css':
            $tenantPlan = getTenantPlan();
            if ($tenantPlan === 'premium') {
                $customCss = $_POST['custom_css'] ?? '';
                
                $errors = [];
                if (validateCustomCss($customCss, $errors)) {
                    $tenantsCoursesDir = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $activeTenant;
                    if (!is_dir($tenantsCoursesDir)) {
                        @mkdir($tenantsCoursesDir, 0755, true);
                    }
                    
                    // Enforce prefers-reduced-motion and focus outlines at the bottom of the compiled CSS
                    $safetyBlock = "\n\n/* ========================================== \n   CORE ACCESSIBILITY SAFETY OVERRIDES \n   ========================================== */\n";
                    $safetyBlock .= "@media (prefers-reduced-motion: reduce) {\n";
                    $safetyBlock .= "    * {\n";
                    $safetyBlock .= "        animation-delay: 0s !important;\n";
                    $safetyBlock .= "        animation-duration: 0s !important;\n";
                    $safetyBlock .= "        animation-iteration-count: 1 !important;\n";
                    $safetyBlock .= "        transition-duration: 0s !important;\n";
                    $safetyBlock .= "        scroll-behavior: auto !important;\n";
                    $safetyBlock .= "    }\n";
                    $safetyBlock .= "}\n";
                    $safetyBlock .= "*:focus-visible, button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {\n";
                    $safetyBlock .= "    outline: 3px solid var(--color-accent) !important;\n";
                    $safetyBlock .= "    outline-offset: 2px !important;\n";
                    $safetyBlock .= "}\n";
                    
                    $customCssPath = $tenantsCoursesDir . DIRECTORY_SEPARATOR . 'custom.css';
                    file_put_contents($customCssPath, $customCss . $safetyBlock);
                    
                    $message = "Custom CSS override stylesheet saved successfully!";
                    $message_type = 'success';
                    logTenantActivity('CSS Override Update', 'Custom CSS stylesheet updated');
                } else {
                    $message = "Accessibility Validation Error: " . implode(" | ", $errors);
                    $message_type = 'critical';
                }
            } else {
                $message = "Custom CSS overrides require the Premium plan level.";
                $message_type = 'critical';
            }
            break;

        case 'request_audit':
            $course_id = trim($_POST['course_id'] ?? '');
            $audit_tier = trim($_POST['audit_tier'] ?? 'basic');
            
            if ($course_id) {
                // Find course title and module count
                $courses_dir = getTenantCoursesDir();
                $manifest_path = $courses_dir . DIRECTORY_SEPARATOR . $course_id . DIRECTORY_SEPARATOR . 'course_structure.json';
                $course_title = $course_id;
                $modules_count = 0;
                
                if (file_exists($manifest_path)) {
                    $manifest = json_decode(file_get_contents($manifest_path), true);
                    $course_title = $manifest['properties']['title'] ?? $course_id;
                    foreach (($manifest['modules'] ?? []) as $item) {
                        if (isset($item['group'])) {
                            $modules_count += count($item['items'] ?? []);
                        } else {
                            $modules_count++;
                        }
                    }
                }
                
                // Calculate price
                $base = 50;
                $extra = 10;
                $per_page = 15;
                $tier_name = 'Basic Review';
                if ($audit_tier === 'full') {
                    $base = 100;
                    $extra = 20;
                    $per_page = 30;
                    $tier_name = 'Full WCAG Audit';
                } elseif ($audit_tier === 'remediation') {
                    $base = 150;
                    $extra = 30;
                    $per_page = 40;
                    $tier_name = 'Full Audit & Remediation';
                }
                
                if ($modules_count < 5) {
                    $estimated_cost = min($modules_count * $per_page, $base);
                } else {
                    $extra_modules = max(0, $modules_count - 5);
                    $estimated_cost = $base + ($extra_modules * $extra);
                }
                
                $message = "Audit request submitted successfully for course '{$course_title}' (Tier: {$tier_name}, Modules: {$modules_count}, Est. Cost: \${$estimated_cost}).";
                $message_type = 'success';
                
                logTenantActivity('Audit Requested', "Course: {$course_title} (ID: {$course_id}) | Tier: {$tier_name} | Modules: {$modules_count} | Est. Cost: \${$estimated_cost}");
            }
            break;
    }
    // Refresh tenantMetadata variable to pick up any changes written during the POST action lifecycle
    $tenantMetadata = getTenantMetadata();
}

// Data Fetching
$user_page = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
$key_page = isset($_GET['key_page']) ? (int)$_GET['key_page'] : 1;
$limit = 10;

$user_offset = ($user_page - 1) * $limit;
$key_offset = ($key_page - 1) * $limit;

$courses = [];
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $users = $pdo->prepare("SELECT id, full_name, email, is_admin, created_at FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $users->execute([$limit, $user_offset]);
    $users = $users->fetchAll();

    $permissions = $pdo->query("
        SELECT p.id, u.full_name, p.course_id 
        FROM user_permissions p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY u.full_name ASC
    ")->fetchAll();

    $total_keys = $pdo->query("SELECT COUNT(*) FROM invitation_keys")->fetchColumn();
    $invitation_keys = $pdo->prepare("SELECT * FROM invitation_keys ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $invitation_keys->execute([$limit, $key_offset]);
    $invitation_keys = $invitation_keys->fetchAll();

    // Discover Courses for Active Tenant
    $courses_dir = getTenantCoursesDir();
    if (is_dir($courses_dir)) {
        foreach (scandir($courses_dir) as $folder) {
            if ($folder === '.' || $folder === '..' || $folder === 'tenants' || $folder === '.backups') continue;
            $manifest_path = $courses_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'course_structure.json';
            if (file_exists($manifest_path)) {
                $manifest = json_decode(file_get_contents($manifest_path), true);
                $total_modules = 0;
                foreach (($manifest['modules'] ?? []) as $item) {
                    if (isset($item['group'])) {
                        $total_modules += count($item['items'] ?? []);
                    } else {
                        $total_modules++;
                    }
                }
                $courses[] = [
                    'id'          => $folder,
                    'title'       => $manifest['properties']['title'] ?? $folder,
                    'description' => $manifest['properties']['description'] ?? '',
                    'access_type' => $manifest['properties']['access']['type'] ?? 'public',
                    'teaser_link' => $manifest['properties']['access']['teaser_link'] ?? '',
                    'xcl_url'     => $manifest['properties']['url'] ?? '',
                    'status'      => $manifest['properties']['status'] ?? 'published',
                    'total_modules'=> $total_modules
                ];
            }
        }
    }

    // Sort discovered courses based on the tenant's sorting preference
    $sortMode = $tenantMetadata['course_sort_mode'] ?? 'custom';
    if ($sortMode === 'alpha_asc') {
        usort($courses, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
    } elseif ($sortMode === 'alpha_desc') {
        usort($courses, function($a, $b) {
            return strcasecmp($b['title'], $a['title']);
        });
    } elseif ($sortMode === 'newest') {
        usort($courses, function($a, $b) use ($courses_dir) {
            $timeA = @filemtime($courses_dir . DIRECTORY_SEPARATOR . $a['id']);
            $timeB = @filemtime($courses_dir . DIRECTORY_SEPARATOR . $b['id']);
            return $timeB <=> $timeA;
        });
    } else {
        $customOrder = $tenantMetadata['course_order'] ?? [];
        if (!empty($customOrder) && is_array($customOrder)) {
            $orderMap = array_flip($customOrder);
            usort($courses, function($a, $b) use ($orderMap) {
                $posA = $orderMap[$a['id']] ?? 9999;
                $posB = $orderMap[$b['id']] ?? 9999;
                if ($posA === $posB) {
                    return strcasecmp($a['title'], $b['title']);
                }
                return $posA <=> $posB;
            });
        } else {
            usort($courses, function($a, $b) {
                return strcasecmp($a['title'], $b['title']);
            });
        }
    }
} catch (PDOException $e) {
    $message = "Database Error: " . $e->getMessage();
    $message_type = 'critical';
}

// Storage Quota Calculation
$tenantPlan = getTenantPlan();
$storageUsedBytes = getTenantStorageUsage();
$storageUsedMb = round($storageUsedBytes / 1048576, 2);
$storageQuotaMb = getTenantStorageQuota();
$storagePercent = min(100, round(($storageUsedBytes / ($storageQuotaMb * 1024 * 1024)) * 100, 1));
// Helper functions for custom CSS accessibility validation
function validateCustomCss($css, &$errors) {
    if (preg_match('/outline\s*:\s*(none|0|transparent|hidden)/i', $css) || 
        preg_match('/outline-width\s*:\s*(0|none)/i', $css) || 
        preg_match('/outline-color\s*:\s*(transparent)/i', $css)) {
        $errors[] = "Focus Indicators: Custom CSS is not allowed to hide focus outlines (e.g., using 'outline: none' or 'outline: 0').";
    }

    if (preg_match_all('/--color-primary\s*:\s*(#[a-f0-9]{3,6})/i', $css, $matches)) {
        foreach ($matches[1] as $color) {
            $hex = expandHexColor($color);
            if (getContrastRatio($hex, '#ffffff') < 4.5) {
                $errors[] = "Contrast Check: Your custom --color-primary override ({$color}) fails the WCAG 2.2 AA contrast ratio of 4.5:1 against white text.";
            }
        }
    }
    if (preg_match_all('/--color-accent\s*:\s*(#[a-f0-9]{3,6})/i', $css, $matches)) {
        foreach ($matches[1] as $color) {
            $hex = expandHexColor($color);
            if (getContrastRatio($hex, '#ffffff') < 4.5 && getContrastRatio($hex, '#0f172a') < 4.5) {
                $errors[] = "Contrast Check: Your custom --color-accent override ({$color}) does not meet 4.5:1 contrast against either light (#ffffff) or dark (#0f172a) backgrounds.";
            }
        }
    }
    
    return empty($errors);
}

function expandHexColor($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    return '#' . $hex;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Admin Dashboard — <?= htmlspecialchars($tenantMetadata['name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <?= renderTenantBrandingCss() ?>
    <style>
        .admin-tabpanel[hidden] { display: none !important; }
    </style>
</head>
<body>
    <?= renderDevToolbar() ?>
    <a href="#admin-main" class="skip-link">Skip to main content</a>

    <header class="bg-primary py-6 text-white">
        <div class="container-wide mx-auto flex justify-between items-center px-4">
            <div class="flex items-center gap-4">
                <?php $tenantLogo = !empty($tenantMetadata['logo_url']) ? $tenantMetadata['logo_url'] : 'Superable-Learning-Logo.svg'; ?>
                <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="<?= htmlspecialchars($tenantMetadata['name']) ?> Logo" class="brand-logo">
                <div>
                    <div class="m-0 text-lg font-bold" style="color: white;">LMS Admin Control Panel</div>
                    <p class="m-0 text-xs" style="color: #e2e8f0;">Portal Key: <code><?= htmlspecialchars($tenantMetadata['tenant_key']) ?></code></p>
                </div>
            </div>
            <div class="flex gap-4 items-center">
                <a href="<?= tenant_url('index.php') ?>" class="nav-link text-sm">← Learner View</a>
                <a href="<?= tenant_url('logout.php') ?>" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </header>

    <main id="admin-main" class="container-wide mx-auto px-4 py-8">
        
        <!-- ARIA Live Region for Status Announcements -->
        <div id="admin-upload-live-region" role="status" aria-live="assertive" class="sr-only"></div>

        <?php if ($message): ?>
            <div id="system-notice-banner" role="status" aria-live="polite" class="alert alert-<?= $message_type ?> mb-4">
                <strong>System Notice:</strong> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($tenantPlan === 'sandbox'): ?>
            <div role="region" aria-label="Upgrade Prompt" class="alert mb-4" style="background-color: var(--color-ocean-light); border: 1px solid var(--color-primary); color: var(--color-text-dark); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 0.75rem 1.25rem; border-radius: 0.5rem;">
                <div style="font-size: 0.9rem;">
                    <strong>Sandbox Free Tier:</strong> You are currently using a Sandbox workspace. Upgrade to <strong>Pro</strong> or <strong>Premium</strong> to unlock custom branding, more storage, and advanced accessibility auditing tools.
                </div>
                <a href="pricing.php" class="btn btn-sm btn-primary" style="white-space: nowrap; margin-left: auto; text-decoration: none; min-height: auto; padding: 0.4rem 0.8rem; font-size: 0.8rem;">Upgrade Plan &rarr;</a>
            </div>
        <?php elseif ($tenantPlan === 'pro'): ?>
            <div role="region" aria-label="Upgrade Prompt" class="alert mb-4" style="background-color: var(--color-ocean-light); border: 1px solid var(--color-primary); color: var(--color-text-dark); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 0.75rem 1.25rem; border-radius: 0.5rem;">
                <div style="font-size: 0.9rem;">
                    <strong>Pro Plan Active:</strong> Expand to multi-admin teams, automated WCAG linting heatmaps, screen reader simulations, and detailed remediation reports by upgrading to <strong>Premium</strong>.
                </div>
                <a href="pricing.php" class="btn btn-sm btn-primary" style="white-space: nowrap; margin-left: auto; text-decoration: none; min-height: auto; padding: 0.4rem 0.8rem; font-size: 0.8rem;">Upgrade to Premium &rarr;</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($advisories)): ?>
            <div role="region" aria-label="Accessibility Advisories" class="alert alert-warning mb-4">
                <div>
                    <strong>Automated Audit & Policy Advisories:</strong>
                    <ul class="m-0 pl-5 mt-1">
                        <?php foreach ($advisories as $adv): ?>
                            <li><?= htmlspecialchars($adv) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Accessible Admin Tab List -->
        <nav class="tab-bar mb-6" role="tablist" aria-label="Admin Dashboard Management Sections">
            <button class="tab-btn active" id="tab-courses-btn" role="tab" aria-selected="true" aria-controls="panel-courses" tabindex="0">
                📚 Courses & Packages
            </button>
            <button class="tab-btn" id="tab-media-btn" role="tab" aria-selected="false" aria-controls="panel-media" tabindex="-1">
                🖼️ Media Assets
            </button>
            <button class="tab-btn" id="tab-branding-btn" role="tab" aria-selected="false" aria-controls="panel-branding" tabindex="-1">
                🎨 Branding & Logo
            </button>
            <button class="tab-btn" id="tab-users-btn" role="tab" aria-selected="false" aria-controls="panel-users" tabindex="-1">
                👥 Users & Access
            </button>
            <button class="tab-btn" id="tab-keys-btn" role="tab" aria-selected="false" aria-controls="panel-keys" tabindex="-1">
                🔑 Course Codes
            </button>
            <button class="tab-btn" id="tab-billing-btn" role="tab" aria-selected="false" aria-controls="panel-billing" tabindex="-1">
                📊 Plan & Storage
            </button>
        </nav>

        <!-- ===================================================================
             TAB 1: COURSES & PACKAGES
             =================================================================== -->
        <div id="panel-courses" role="tabpanel" aria-labelledby="tab-courses-btn" class="admin-tabpanel">
            <section class="card mb-6">
                <div class="flex-between mb-3">
                    <div>
                        <h1 class="m-0 text-2xl" id="tab-heading-courses">Course Library & Package Importer</h1>
                        <p class="text-neutral-mid text-sm m-0">Upload e-learning ZIP packages, set teaser links, and manage access modes.</p>
                    </div>
                    <a href="packager.php" class="btn btn-teal btn-sm">Open Web Course Packager &rarr;</a>
                </div>

                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <!-- ZIP Package Upload Form -->
                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-neutral-mid);">
                        <h2>Upload New Course (.zip)</h2>
                        <form method="POST" enctype="multipart/form-data" id="upload-course-form">
                            <input type="hidden" name="action" value="upload_course">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="form-group mb-4">
                                <label for="course_zip" class="form-label">Select Package (.zip)</label>
                                <input type="file" name="course_zip" id="course_zip" accept=".zip" class="form-control" required>
                                <small class="text-neutral-mid mt-1">Package must contain <code>course_structure.json</code> or LC-JSON manifest in root.</small>
                            </div>
                            <button type="submit" id="upload-submit-btn" class="btn">Upload & Import Package</button>
                        </form>
                    </div>

                    <!-- Quick Guidance -->
                    <div style="background: #eff6ff; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #3b82f6;">
                        <h2 style="color: #1e40af;" class="m-0 mb-2">Package Requirements</h2>
                        <ul class="text-sm pl-4 m-0" style="color: #1e3a8a;">
                            <li class="mb-2"><strong>Standard Formats:</strong> HTML packages, BuildXCL bundles, or LC-JSON schemas.</li>
                            <li class="mb-2"><strong>Security Sandbox:</strong> Prohibited file extensions (.php, .sh, .exe) are blocked automatically.</li>
                            <li><strong>Media Links:</strong> Link to YouTube/Vimeo for video streams to conserve your storage quota.</li>
                        </ul>
                    </div>
                </div>

                <!-- Course List -->
                <!-- Course Sorting Preset Controls -->
                <div class="card mb-4" style="background: var(--color-bg-light); border: 1px solid var(--color-border); padding: 1rem;">
                    <form method="POST" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; justify-content: space-between; margin-bottom: 0;">
                        <input type="hidden" name="action" value="update_course_sort">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                            <div class="form-group mb-0">
                                <label for="course_sort_mode" class="form-label">Course Sort Ordering Mode</label>
                                <select name="course_sort_mode" id="course_sort_mode" class="form-select" style="width: auto;">
                                    <option value="custom" <?= ($tenantMetadata['course_sort_mode'] ?? 'custom') === 'custom' ? 'selected' : '' ?>>Custom Manual Reordering (Default)</option>
                                    <option value="alpha_asc" <?= ($tenantMetadata['course_sort_mode'] ?? '') === 'alpha_asc' ? 'selected' : '' ?>>Alphabetical (A to Z)</option>
                                    <option value="alpha_desc" <?= ($tenantMetadata['course_sort_mode'] ?? '') === 'alpha_desc' ? 'selected' : '' ?>>Alphabetical (Z to A)</option>
                                    <option value="newest" <?= ($tenantMetadata['course_sort_mode'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest Uploaded First</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm" style="background-color: var(--color-primary); color: white;">Apply Preset Order</button>
                        </div>
                        <div class="text-sm text-neutral-mid" style="max-width: 400px; line-height: 1.4;">
                            <strong>Tip:</strong> If set to <em>Custom Manual Reordering</em>, you can type a position number in any course card below and save to manually rearrange it.
                        </div>
                    </form>
                </div>

                <h2>Course Library (<?= count($courses) ?> Courses)</h2>
                <?php if (empty($courses)): ?>
                    <p class="text-neutral-mid">No courses are uploaded for this portal yet. Use the upload box above to add a course package.</p>
                <?php else: ?>
                    <div class="card-grid card-grid-1">
                        <?php foreach ($courses as $index => $c): ?>
                            <div class="p-4" style="border: 1px solid var(--color-border); border-radius: 0.5rem; background: white;">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="course_id" value="<?= htmlspecialchars($c['id']) ?>">
                                    
                                    <div class="flex-between mb-3">
                                        <h3 class="m-0 text-lg font-bold"><?= htmlspecialchars($c['title']) ?> <span class="badge badge-secondary">ID: <?= htmlspecialchars($c['id']) ?></span></h3>
                                        <button type="submit" name="action" value="delete_course" onclick="return confirm('Are you sure you want to delete this course folder?');" class="btn btn-sm" style="background: var(--color-critical-border);">Delete Course</button>
                                    </div>

                                    <div class="grid md:grid-cols-4 gap-4 mb-3">
                                        <div class="form-group">
                                            <label for="title_<?= htmlspecialchars($c['id']) ?>" class="form-label">Course Title</label>
                                            <input type="text" name="title" id="title_<?= htmlspecialchars($c['id']) ?>" class="form-control" value="<?= htmlspecialchars($c['title']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="access_type_<?= htmlspecialchars($c['id']) ?>" class="form-label">Access Mode</label>
                                            <select name="access_type" id="access_type_<?= htmlspecialchars($c['id']) ?>" class="form-select">
                                                <option value="public" <?= $c['access_type'] === 'public' ? 'selected' : '' ?>>Public (Open Access)</option>
                                                <option value="restricted" <?= $c['access_type'] === 'restricted' ? 'selected' : '' ?>>Restricted (Requires Login / Invite Key)</option>
                                                <option value="teaser" <?= $c['access_type'] === 'teaser' ? 'selected' : '' ?>>Teaser (Shows Preview Button)</option>
                                                <option value="hidden" <?= $c['access_type'] === 'hidden' ? 'selected' : '' ?>>Hidden (Admin Only)</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="status_<?= htmlspecialchars($c['id']) ?>" class="form-label">Course Status</label>
                                            <select name="status" id="status_<?= htmlspecialchars($c['id']) ?>" class="form-select" <?= ($tenantPlan !== 'premium') ? 'disabled' : '' ?>>
                                                <option value="published" <?= ($c['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published (Live)</option>
                                                <option value="draft" <?= ($c['status'] ?? 'published') === 'draft' ? 'selected' : '' ?>>Draft (Admin Only)</option>
                                            </select>
                                            <?php if ($tenantPlan !== 'premium'): ?>
                                                <small class="text-neutral-mid mt-1" style="display: block; font-size: 0.75rem;">Draft status requires <a href="pricing.php" style="font-weight: 700;">Premium</a>.</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <label for="sort_position_<?= htmlspecialchars($c['id']) ?>" class="form-label">Order Position</label>
                                            <input type="number" name="sort_position" id="sort_position_<?= htmlspecialchars($c['id']) ?>" class="form-control" value="<?= $index + 1 ?>" min="1" max="<?= count($courses) ?>" required>
                                        </div>
                                    </div>

                                    <div class="grid md:grid-cols-2 gap-4 mb-3">
                                        <div class="form-group">
                                            <label for="teaser_<?= htmlspecialchars($c['id']) ?>" class="form-label">Teaser Link URL (Optional)</label>
                                            <input type="text" name="teaser_link" id="teaser_<?= htmlspecialchars($c['id']) ?>" class="form-control" value="<?= htmlspecialchars($c['teaser_link']) ?>" placeholder="https://example.com/teaser">
                                        </div>
                                        <div class="form-group">
                                            <label for="xcl_<?= htmlspecialchars($c['id']) ?>" class="form-label">BuildXCL External Link (Optional)</label>
                                            <input type="text" name="xcl_url" id="xcl_<?= htmlspecialchars($c['id']) ?>" class="form-control" value="<?= htmlspecialchars($c['xcl_url']) ?>" placeholder="https://buildxcl.org/course/xyz">
                                        </div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="desc_<?= htmlspecialchars($c['id']) ?>" class="form-label">Description</label>
                                        <textarea name="description" id="desc_<?= htmlspecialchars($c['id']) ?>" class="form-textarea" rows="2"><?= htmlspecialchars($c['description']) ?></textarea>
                                    </div>

                                    <button type="submit" name="action" value="update_course_manifest" class="btn btn-sm">Save Course Settings</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ===================================================================
             TAB 2: MEDIA ASSETS
             =================================================================== -->
        <div id="panel-media" role="tabpanel" aria-labelledby="tab-media-btn" class="admin-tabpanel" hidden>
            <section class="card mb-6">
                <h1 class="m-0 text-2xl mb-2" id="tab-heading-media">Course Media & Image Asset Manager</h1>
                <p class="text-neutral-mid mb-4">Upload diagrams, screenshots, and infographics for your courses. Reference image relative paths in LLM prompts or pasted code blocks.</p>

                <!-- Dynamic Storage Usage Meter -->
                <div class="mb-6 p-4 rounded-xl" style="background: var(--color-bg-light); border: 1px solid var(--color-border);">
                    <div class="flex-between text-sm font-bold mb-1">
                        <span>Portal Storage Usage</span>
                        <span><?= $storageUsedMb ?> MB / <?= $storageQuotaMb ?> MB (<?= $storagePercent ?>% used)</span>
                    </div>
                    <div class="progress-meter" aria-label="Storage quota: <?= $storagePercent ?>% used">
                        <div class="progress-fill" style="width: <?= $storagePercent ?>%; background: <?= $storagePercent > 90 ? 'var(--color-critical-border)' : 'var(--color-primary)' ?>;"></div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border);">
                        <h2>Upload Course Image Asset</h2>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_course_asset">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="form-group mb-4">
                                <label for="asset_course_id" class="form-label">Target Course</label>
                                <select name="course_id" id="asset_course_id" class="form-select" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= htmlspecialchars($c['id']) ?>"><?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['id']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mb-4">
                                <label for="asset_file" class="form-label">Image File (PNG, JPG, SVG, WEBP, GIF)</label>
                                <input type="file" name="asset_file" id="asset_file" accept=".png,.jpg,.jpeg,.gif,.svg,.webp" class="form-control" required>
                            </div>

                            <button type="submit" class="btn">Upload Image Asset</button>
                        </form>
                    </div>

                    <div style="background: #eff6ff; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #3b82f6;">
                        <h2 style="color: #1e40af;" class="m-0 mb-2">How to Embed Images with AI</h2>
                        <ol class="text-sm pl-4 m-0" style="color: #1e3a8a;">
                            <li class="mb-2">Upload your image asset using the form on the left.</li>
                            <li class="mb-2">In your HTML course code, use: <code>&lt;img src="assets/image-name.png" alt="Description"&gt;</code></li>
                            <li class="mb-2">In Markdown course code, use: <code>![Description](assets/image-name.png)</code></li>
                            <li>When prompting AI assistants (ChatGPT, Claude, Gemini), instruct them to use <code>src="assets/your-image.png"</code> for diagrams.</li>
                        </ol>
                    </div>
                </div>
            </section>
        </div>

        <!-- ===================================================================
             TAB 3: BRANDING & LOGO
             =================================================================== -->
        <div id="panel-branding" role="tabpanel" aria-labelledby="tab-branding-btn" class="admin-tabpanel" hidden>
            <section class="card mb-6">
                <h1 class="m-0 text-2xl mb-2" id="tab-heading-branding">Custom Branding & Logo</h1>
                <?php if ($tenantPlan === 'sandbox'): ?>
                    <div style="background: white; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 3rem 1.5rem; text-align: center; max-width: 600px; margin: 2rem auto;">
                        <span style="font-size: 3rem;" aria-hidden="true">🔒</span>
                        <h2 style="color: var(--color-primary); margin-top: 1rem;">Branding Customization is Locked</h2>
                        <p class="text-neutral-mid mb-4">
                            Custom brand colors, typography selectors, and logo uploads are available on the <strong>Pro</strong> and <strong>Premium</strong> tiers.
                        </p>
                        <a href="pricing.php" class="btn btn-primary" style="background: var(--color-accent); border: none;">View Upgrade Options &rarr;</a>
                    </div>
                <?php else: ?>
                    <p class="text-neutral-mid mb-4">Customize your organization's logo and brand colors. All primary colors are automatically verified for WCAG 2.2 AA contrast compliance.</p>

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Logo Form -->
                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border);">
                        <h2>Organization Logo</h2>
                        <div class="mb-4 text-center p-4" style="background: white; border: 1px dashed var(--color-border); border-radius: 0.5rem;">
                            <p class="text-sm font-bold text-neutral-mid mb-2" style="margin-top:0;">Current Header Logo Preview:</p>
                            <img src="<?= htmlspecialchars($tenantLogo) ?>" alt="Tenant Logo Preview" class="brand-logo">
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_logo">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="form-group mb-4">
                                <label for="tenant_logo" class="form-label">Upload Custom Logo (SVG, PNG, JPG, GIF, WEBP)</label>
                                <input type="file" name="tenant_logo" id="tenant_logo" accept=".svg,.png,.jpg,.jpeg,.gif,.webp" class="form-control" required>
                                <small class="text-neutral-mid mt-1">Max size: 5 MB. Logistical CSS guardrails (`max-height: 44px; object-fit: contain`) prevent header overflow.</small>
                            </div>
                            
                            <div class="flex gap-4">
                                <button type="submit" class="btn">Upload Logo</button>
                                <?php if (!empty($tenantMetadata['logo_url'])): ?>
                                    <button type="submit" name="action" value="remove_logo" onclick="return confirm('Reset to default logo?');" class="btn btn-secondary btn-sm">Restore Default Logo</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Colors Form with Collapsible Accordions (Expanded by Default) -->
                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border);">
                        <h2>Brand Color Tokens</h2>
                        <p class="text-sm text-neutral-mid mb-4">Click any color section below to expand or collapse. Primary colors are automatically checked for WCAG 2.2 AA contrast compliance.</p>
                        <?php 
                        $b = $tenantMetadata['branding'] ?? [];
                        $pColor = $b['primary'] ?? '#3B7A57';
                        $sColor = $b['secondary'] ?? '#5F8F6B';
                        $aColor = $b['accent'] ?? '#946300';
                        ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_tenant_branding">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="form-group mb-4">
                                <label for="font_family" class="form-label">Accessible Brand Typography (Font Family)</label>
                                <?php $currentFont = $tenantMetadata['font_family'] ?? 'Atkinson Hyperlegible'; ?>
                                <select name="font_family" id="font_family" class="form-select">
                                    <option value="Atkinson Hyperlegible" <?= $currentFont === 'Atkinson Hyperlegible' ? 'selected' : '' ?>>Atkinson Hyperlegible (Default - Highest Readability)</option>
                                    <option value="Inter" <?= $currentFont === 'Inter' ? 'selected' : '' ?>>Inter (Modern Clean Sans-Serif)</option>
                                    <option value="Roboto" <?= $currentFont === 'Roboto' ? 'selected' : '' ?>>Roboto (Standard Clean Sans-Serif)</option>
                                    <option value="Open Sans" <?= $currentFont === 'Open Sans' ? 'selected' : '' ?>>Open Sans (Neutral Highly Legible)</option>
                                    <option value="Lexend" <?= $currentFont === 'Lexend' ? 'selected' : '' ?>>Lexend (Specialized Dyslexia-Friendly)</option>
                                </select>
                                <small class="text-neutral-mid mt-1">Selects a WCAG-verified Google Font for body copy, headings, and UI controls across your portal.</small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="hero_headline" class="form-label">Portal Welcome Headline</label>
                                <input type="text" name="hero_headline" id="hero_headline" class="form-control" value="<?= htmlspecialchars($tenantMetadata['hero_headline'] ?? '') ?>" placeholder="e.g. Welcome to Acme Academy">
                                <small class="text-neutral-mid mt-1">Displayed as the main hero title on your learner dashboard. Defaults to tenant name if empty.</small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="hero_subheadline" class="form-label">Portal Subheadline / Description</label>
                                <input type="text" name="hero_subheadline" id="hero_subheadline" class="form-control" value="<?= htmlspecialchars($tenantMetadata['hero_subheadline'] ?? '') ?>" placeholder="e.g. Accessible E-Learning Portal for Employee Onboarding">
                            </div>

                            <div class="form-group mb-4">
                                <label for="copyright_notice" class="form-label">Copyright Notice / Attribution Name</label>
                                <input type="text" name="copyright_notice" id="copyright_notice" class="form-control" value="<?= htmlspecialchars($tenantMetadata['copyright_notice'] ?? '') ?>" placeholder="e.g. Jacob Wood or Acme Corp">
                                <small class="text-neutral-mid mt-1">Specifies who holds the copyright for content inside your tenant portal (renders as <code>&copy; <?= date('Y') ?> <?= htmlspecialchars($tenantMetadata['copyright_notice'] ?? $tenantMetadata['name']) ?>. All rights reserved.</code> in the footer).</small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="website_url" class="form-label">Organization Website URL (External Main Site)</label>
                                <input type="url" name="website_url" id="website_url" class="form-control" value="<?= htmlspecialchars($tenantMetadata['website_url'] ?? '') ?>" placeholder="https://jacobwood.me">
                                <small class="text-neutral-mid mt-1">Optional. Links your footer copyright notice and footer nav back to your primary corporate website.</small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="support_contact" class="form-label">Support & Help Desk Contact (Email or URL)</label>
                                <input type="text" name="support_contact" id="support_contact" class="form-control" value="<?= htmlspecialchars($tenantMetadata['support_contact'] ?? '') ?>" placeholder="support@acmecorp.com or https://help.acmecorp.com">
                                <small class="text-neutral-mid mt-1">Directs learners to your internal support team or help desk.</small>
                            </div>

                            <div class="grid md:grid-cols-2 gap-4 mb-4">
                                <div class="form-group">
                                    <label for="terms_url" class="form-label">Terms of Service URL</label>
                                    <input type="url" name="terms_url" id="terms_url" class="form-control" value="<?= htmlspecialchars($tenantMetadata['terms_url'] ?? '') ?>" placeholder="https://acmecorp.com/terms">
                                </div>
                                <div class="form-group">
                                    <label for="privacy_url" class="form-label">Privacy Policy URL</label>
                                    <input type="url" name="privacy_url" id="privacy_url" class="form-control" value="<?= htmlspecialchars($tenantMetadata['privacy_url'] ?? '') ?>" placeholder="https://acmecorp.com/privacy">
                                </div>
                            </div>
                            
                            <!-- Primary Color Accordion -->
                            <div class="accordion-item mb-3">
                                <h3 class="accordion-header">
                                    <button type="button" class="accordion-trigger brand-accordion-btn" aria-expanded="true" aria-controls="brand-panel-primary">
                                        <span>🟢 Primary Color Settings</span>
                                        <span class="accordion-icon" aria-hidden="true">▼</span>
                                    </button>
                                </h3>
                                <div id="brand-panel-primary" class="accordion-panel">
                                    <div class="form-group mb-2">
                                        <label for="primary_color" class="form-label">Primary Color (Header Backgrounds & Buttons)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" id="primary_picker" value="<?= htmlspecialchars($pColor) ?>" onchange="document.getElementById('primary_color').value = this.value;" style="width: 44px; height: 44px; padding: 0; cursor: pointer;">
                                            <input type="text" name="primary_color" id="primary_color" value="<?= htmlspecialchars($pColor) ?>" class="form-control" required>
                                        </div>
                                        <small class="text-neutral-mid mt-1">Main brand color for site headers and primary buttons. Automatically checked for 4.5:1 WCAG AA contrast against white text.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Secondary Color Accordion -->
                            <div class="accordion-item mb-3">
                                <h3 class="accordion-header">
                                    <button type="button" class="accordion-trigger brand-accordion-btn" aria-expanded="true" aria-controls="brand-panel-secondary">
                                        <span>🌿 Secondary Color Settings</span>
                                        <span class="accordion-icon" aria-hidden="true">▼</span>
                                    </button>
                                </h3>
                                <div id="brand-panel-secondary" class="accordion-panel">
                                    <div class="form-group mb-2">
                                        <label for="secondary_color" class="form-label">Secondary Color (Accents, Borders & Subtitles)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" id="secondary_picker" value="<?= htmlspecialchars($sColor) ?>" onchange="document.getElementById('secondary_color').value = this.value;" style="width: 44px; height: 44px; padding: 0; cursor: pointer;">
                                            <input type="text" name="secondary_color" id="secondary_color" value="<?= htmlspecialchars($sColor) ?>" class="form-control" required>
                                        </div>
                                        <small class="text-neutral-mid mt-1">Used for secondary buttons, blockquote borders, and subtle structural accents.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Accent Color Accordion -->
                            <div class="accordion-item mb-4">
                                <h3 class="accordion-header">
                                    <button type="button" class="accordion-trigger brand-accordion-btn" aria-expanded="true" aria-controls="brand-panel-accent">
                                        <span>⚡ Accent Color Settings</span>
                                        <span class="accordion-icon" aria-hidden="true">▼</span>
                                    </button>
                                </h3>
                                <div id="brand-panel-accent" class="accordion-panel">
                                    <div class="form-group mb-2">
                                        <label for="accent_color" class="form-label">Accent Color (Focus Rings & Highlights)</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" id="accent_picker" value="<?= htmlspecialchars($aColor) ?>" onchange="document.getElementById('accent_color').value = this.value;" style="width: 44px; height: 44px; padding: 0; cursor: pointer;">
                                            <input type="text" name="accent_color" id="accent_color" value="<?= htmlspecialchars($aColor) ?>" class="form-control" required>
                                        </div>
                                        <small class="text-neutral-mid mt-1">High-visibility indicator color for keyboard focus rings (`:focus-visible`) and active item highlights.</small>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn">Save Branding & Logo Settings</button>
                        </form>
                    </div>
                </div>

                <?php if ($tenantPlan === 'premium'): ?>
                    <!-- Custom CSS Overrides (Premium Only) -->
                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border); margin-top: 1.5rem;">
                        <h2>Premium Custom CSS Overrides</h2>
                        <p class="text-sm text-neutral-mid mb-3">Add custom CSS stylesheet overrides to customize borders, headers, buttons, and accessibility font adjustments globally across your learner dashboard.</p>
                        
                        <?php
                        $customCssPath = LMS_ROOT . DIRECTORY_SEPARATOR . 'courses' . DIRECTORY_SEPARATOR . 'tenants' . DIRECTORY_SEPARATOR . $activeTenant . DIRECTORY_SEPARATOR . 'custom.css';
                        $currentCss = '';
                        if (file_exists($customCssPath)) {
                            $currentCss = file_get_contents($customCssPath);
                            // Strip out core accessibility safety override block so it is not visible in editor
                            $splitPos = strpos($currentCss, '/* ==========================================');
                            if ($splitPos !== false) {
                                $currentCss = substr($currentCss, 0, $splitPos);
                            }
                            $currentCss = trim($currentCss);
                        }
                        ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_custom_css">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="form-group mb-3">
                                <label for="custom_css_content" class="form-label">Stylesheet CSS Code</label>
                                <textarea name="custom_css" id="custom_css_content" class="form-textarea" rows="8" placeholder="/* Enter custom CSS rules here. e.g. .site-header { border-bottom: 3px double red; } */" style="font-family: monospace; font-size: 0.85rem; background: #0f172a; color: #34d399;"><?= htmlspecialchars($currentCss) ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn">Save CSS Stylesheet</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <!-- ===================================================================
             TAB 4: USERS & ACCESS
             =================================================================== -->
        <div id="panel-users" role="tabpanel" aria-labelledby="tab-users-btn" class="admin-tabpanel" hidden>
            <section class="card mb-6">
                <h1 class="m-0 text-2xl mb-4" id="tab-heading-users">User Management & Course Access</h1>
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h2>Create New User</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_user">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="form-group mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" required placeholder="Jane Doe">
                            </div>
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" required placeholder="jane@example.com">
                            </div>
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required minlength="8">
                            </div>
                            <div class="form-group mb-3" style="flex-direction: row; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="is_admin" id="is_admin" value="1" style="width: auto;">
                                <label for="is_admin" class="form-label" style="font-weight: normal;">Grant Admin Privileges</label>
                            </div>
                            <button type="submit" class="btn">Create User</button>
                        </form>
                    </div>

                    <div>
                        <h2>Grant Direct Course Permission</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="grant_permission">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="form-group mb-3">
                                <label for="user_id_perm" class="form-label">Select User</label>
                                <select name="user_id" id="user_id_perm" class="form-select" required>
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="course_id_perm" class="form-label">Select Course</label>
                                <select name="course_id" id="course_id_perm" class="form-select" required>
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= htmlspecialchars($course['id']) ?>"><?= htmlspecialchars($course['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn">Grant Access</button>
                        </form>
                    </div>
                </div>

                <h2>Registered Users (<?= $total_users ?> Users)</h2>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Reset Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= $u['is_admin'] ? '<span class="badge badge-success">Admin</span>' : '<span class="badge badge-secondary">Learner</span>' ?></td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 0.5rem;">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="password" name="new_password" class="form-control" placeholder="New Pass" required style="padding: 0.25rem 0.5rem; font-size: 0.85rem; width: 110px;" aria-label="New password for <?= htmlspecialchars($u['full_name']) ?>">
                                            <button type="submit" class="btn btn-sm">Reset</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ===================================================================
             TAB 5: COURSE CODES (INVITATION KEYS)
             =================================================================== -->
        <div id="panel-keys" role="tabpanel" aria-labelledby="tab-keys-btn" class="admin-tabpanel" hidden>
            <section class="card mb-6">
                <h1 class="m-0 text-2xl mb-4" id="tab-heading-keys">Invitation Keys (Course Access Codes)</h1>
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h2>Create Invitation Key</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_invitation_key">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="form-group mb-3">
                                <label for="key_code" class="form-label">Key Code (e.g. CRSCD123)</label>
                                <input type="text" name="key_code" id="key_code" class="form-control" required placeholder="CRSCD123" style="text-transform: uppercase;">
                            </div>
                            <div class="form-group mb-3">
                                <label for="key_course_id" class="form-label">Link to Specific Course (Optional)</label>
                                <select name="course_id" id="key_course_id" class="form-select">
                                    <option value="">-- All Courses / Generic Unlock --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= htmlspecialchars($course['id']) ?>"><?= htmlspecialchars($course['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="uses_remaining" class="form-label">Max Uses (-1 for Unlimited)</label>
                                <input type="number" name="uses_remaining" id="uses_remaining" class="form-control" value="-1" required>
                            </div>
                            <button type="submit" class="btn">Create Code</button>
                        </form>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Linked Course</th>
                                <th>Uses Remaining</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitation_keys as $key): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($key['key_code']) ?></code></td>
                                    <td><?= htmlspecialchars($key['course_id'] ?? 'Generic') ?></td>
                                    <td><?= $key['uses_remaining'] === -1 ? 'Unlimited' : $key['uses_remaining'] ?></td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                            <input type="hidden" name="action" value="update_key_uses">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                            <input type="number" name="uses_remaining" value="<?= $key['uses_remaining'] ?>" required class="form-control" style="padding: 0.25rem; width: 70px; font-size: 0.85rem;" aria-label="Uses remaining for code <?= htmlspecialchars($key['key_code']) ?>">
                                            <button type="submit" class="btn btn-sm">Update Uses</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ===================================================================
             TAB 6: PLAN & STORAGE QUOTA
             =================================================================== -->
        <div id="panel-billing" role="tabpanel" aria-labelledby="tab-billing-btn" class="admin-tabpanel" hidden>
            <section class="card mb-6">
                <h1 class="m-0 text-2xl mb-4" id="tab-heading-billing">Plan & Storage Quota</h1>
                
                <div class="grid md:grid-cols-3 gap-6 mb-6">
                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border);">
                        <h2>Subscription Plan</h2>
                        <p class="text-lg font-bold mb-1" style="color: var(--color-primary);">Plan Level: <?= htmlspecialchars(strtoupper($tenantPlan)) ?></p>
                        <p class="text-sm text-neutral-mid mb-3">Status: <span class="badge badge-success"><?= htmlspecialchars(strtoupper($tenantMetadata['status'])) ?></span></p>
                        <p class="text-sm text-neutral-mid m-0">Provisioned Domain: <code><?= htmlspecialchars($tenantMetadata['domain']) ?></code></p>
                    </div>

                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border);">
                        <h2>Storage Quota Usage</h2>
                        <div class="flex-between text-sm font-bold mb-1">
                            <span>Storage Usage Meter</span>
                            <span><?= $storageUsedMb ?> MB / <?= $storageQuotaMb ?> MB (<?= $storagePercent ?>%)</span>
                        </div>
                        <div class="progress-meter mb-3" aria-label="Storage meter: <?= $storagePercent ?>% used">
                            <div class="progress-fill" style="width: <?= $storagePercent ?>%; background: <?= $storagePercent > 90 ? 'var(--color-critical-border)' : 'var(--color-primary)' ?>;"></div>
                        </div>
                        <p class="text-xs text-neutral-mid m-0">Storage path: <code><?= htmlspecialchars(getTenantCoursesDir()) ?></code></p>
                    </div>

                    <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border);">
                        <h2>Platform Support SLA</h2>
                        <?php if ($tenantPlan === 'sandbox'): ?>
                            <p class="text-lg font-bold mb-1" style="color: var(--color-primary);">Community Only</p>
                            <p class="text-sm text-neutral-mid mb-3">Browse our documentation and standard help articles to solve setup queries.</p>
                            <a href="help.php" class="btn btn-sm" style="background: var(--color-primary); color: white; text-decoration: none; display: inline-block;">Open Help Center &rarr;</a>
                        <?php elseif ($tenantPlan === 'pro'): ?>
                            <p class="text-lg font-bold mb-1" style="color: var(--color-primary);">Priority Email SLA</p>
                            <p class="text-sm text-neutral-mid mb-3">Priority ticket routing with a 72-hour email response commitment.</p>
                            <a href="mailto:support@superablelearning.com?subject=Pro%20Support%20Request%20-%20<?= urlencode($tenantMetadata['tenant_key']) ?>" class="btn btn-sm btn-teal" style="text-decoration: none; color: white !important;">Submit Pro Ticket</a>
                        <?php else: ?>
                            <p class="text-lg font-bold mb-1" style="color: var(--color-primary);">Premium Support</p>
                            <p class="text-sm text-neutral-mid mb-3">Rapid routing (24-48h SLA) and troubleshooting Zoom scheduling.</p>
                            <a href="mailto:support@superablelearning.com?subject=Premium%20Support%20Request%20-%20<?= urlencode($tenantMetadata['tenant_key']) ?>" class="btn btn-sm" style="background: var(--color-accent); text-decoration: none; color: white !important;">Submit Premium Ticket</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="background: var(--color-bg-light); padding: 1.5rem; border-radius: 0.5rem; border: 1px solid var(--color-border); margin-top: 1.5rem;">
                    <h2>Request Accessibility Audit Review Add-On</h2>
                    <p class="text-sm text-neutral-mid mb-4">
                        Get professional accessibility feedback for your courses. Select a course and audit level to calculate your custom price.
                        Read more about our audit tiers in our <a href="help.php?doc=accessibility-reviews" target="_blank" style="font-weight: 700;">Accessibility Review Definitions & Scopes</a> help article.
                    </p>
                    
                    <form method="POST" id="audit-request-form">
                        <input type="hidden" name="action" value="request_audit">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div class="grid md:grid-cols-3 gap-4 mb-4">
                            <div class="form-group">
                                <label for="audit_course" class="form-label">Select Course to Audit</label>
                                <select name="course_id" id="audit_course" class="form-select" required onchange="calculateAuditPrice()">
                                    <option value="" data-modules="0">-- Select Course --</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= htmlspecialchars($c['id']) ?>" data-modules="<?= $c['total_modules'] ?>">
                                            <?= htmlspecialchars($c['title']) ?> (<?= $c['total_modules'] ?> modules)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="audit_tier" class="form-label">Select Audit Level</label>
                                <select name="audit_tier" id="audit_tier" class="form-select" required onchange="calculateAuditPrice()">
                                    <option value="basic" data-base="50" data-extra="10">Basic Review ($50 base, +$10/extra module)</option>
                                    <option value="full" data-base="100" data-extra="20">Full WCAG Audit ($100 base, +$20/extra module)</option>
                                    <option value="remediation" data-base="150" data-extra="30">Full Audit & Remediation ($150 base, +$30/extra module)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" style="display: block; font-weight: bold;">Estimated Cost</label>
                                <div id="audit-price-display" style="font-size: 1.5rem; font-weight: bold; color: var(--color-primary); padding-top: 0.25rem;">
                                    $0.00
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-teal">Submit Audit Request</button>
                    </form>
                </div>
            </section>
        </div>

    </main>

    <?= renderTenantFooter() ?>

    <!-- Accessible Admin Tab Controller Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = Array.from(document.querySelectorAll('.tab-bar [role="tab"]'));
            const panels = Array.from(document.querySelectorAll('.admin-tabpanel'));

            function activateTab(targetTab) {
                tabs.forEach(tab => {
                    const isTarget = (tab === targetTab);
                    tab.classList.toggle('active', isTarget);
                    tab.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                    tab.setAttribute('tabindex', isTarget ? '0' : '-1');
                });

                const targetPanelId = targetTab.getAttribute('aria-controls');
                panels.forEach(panel => {
                    const isTarget = (panel.id === targetPanelId);
                    if (isTarget) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', '');
                    }
                });

                try { localStorage.setItem('admin_active_tab_id', targetTab.id); } catch(e) {}
            }

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => activateTab(tab));

                tab.addEventListener('keydown', (e) => {
                    let newIndex = index;
                    if (e.key === 'ArrowRight') {
                        newIndex = (index + 1) % tabs.length;
                    } else if (e.key === 'ArrowLeft') {
                        newIndex = (index - 1 + tabs.length) % tabs.length;
                    } else if (e.key === 'Home') {
                        newIndex = 0;
                    } else if (e.key === 'End') {
                        newIndex = tabs.length - 1;
                    } else {
                        return;
                    }
                    e.preventDefault();
                    tabs[newIndex].focus();
                    activateTab(tabs[newIndex]);
                });
            });

            // Restore active tab after form submission reload
            try {
                const savedTabId = localStorage.getItem('admin_active_tab_id');
                if (savedTabId) {
                    const savedTab = document.getElementById(savedTabId);
                    if (savedTab) activateTab(savedTab);
                }
            } catch(e) {}

            // Accordion Toggle Controller for Brand Color Sections
            document.querySelectorAll('.brand-accordion-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const expanded = (btn.getAttribute('aria-expanded') === 'true');
                    btn.setAttribute('aria-expanded', !expanded);
                    const panelId = btn.getAttribute('aria-controls');
                    const panel = document.getElementById(panelId);
                    if (panel) {
                        if (expanded) {
                            panel.setAttribute('hidden', '');
                        } else {
                            panel.removeAttribute('hidden');
                        }
                    }
                });
            });
        });

        function calculateAuditPrice() {
            const courseSelect = document.getElementById('audit_course');
            const tierSelect = document.getElementById('audit_tier');
            const priceDisplay = document.getElementById('audit-price-display');
            
            if (!courseSelect || !tierSelect || !priceDisplay) return;
            
            const selectedCourseOption = courseSelect.options[courseSelect.selectedIndex];
            const modules = parseInt(selectedCourseOption.getAttribute('data-modules') || '0', 10);
            
            const tiers = {
                basic: { name: 'Basic Review', base: 50, extra: 10, perPage: 15 },
                full: { name: 'Full WCAG Audit', base: 100, extra: 20, perPage: 30 },
                remediation: { name: 'Full Audit & Remediation', base: 150, extra: 30, perPage: 40 }
            };

            // Dynamically rewrite dropdown option labels to match chosen course's pricing rules
            Array.from(tierSelect.options).forEach(opt => {
                const val = opt.value;
                if (!tiers[val]) return;
                const config = tiers[val];
                
                if (modules === 0) {
                    opt.textContent = `${config.name} ($${config.base} base, +$${config.extra}/extra module)`;
                } else if (modules < 5) {
                    const price = Math.min(modules * config.perPage, config.base);
                    opt.textContent = `${config.name} ($${config.perPage}/module — $${price.toFixed(2)} total)`;
                } else {
                    const extraModules = modules - 5;
                    const price = config.base + (extraModules * config.extra);
                    opt.textContent = `${config.name} ($${config.base} base + $${config.extra} extra — $${price.toFixed(2)} total)`;
                }
            });

            const selectedTier = tierSelect.value;
            if (modules === 0 || !tiers[selectedTier]) {
                priceDisplay.textContent = '$0.00';
                return;
            }

            const activeConfig = tiers[selectedTier];
            let total = 0;
            if (modules < 5) {
                total = Math.min(modules * activeConfig.perPage, activeConfig.base);
            } else {
                const extraModules = modules - 5;
                total = activeConfig.base + (extraModules * activeConfig.extra);
            }
            
            priceDisplay.textContent = '$' + total.toFixed(2);
        }
    </script>
</body>
</html>
