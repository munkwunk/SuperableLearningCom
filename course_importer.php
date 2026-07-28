<?php
/**
 * Superable Learning - Multi-Tenant Course Importer & Security Validator
 * 
 * Handles course ZIP package inspection, security whitelisting, path traversal
 * prevention, video restrictions (YouTube/Vimeo only), SVG sanitization,
 * quota limits (500MB max), auto-backups, and accessibility auditing.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lc_json_converter.php';

class CourseImporter {

    private static $allowedExtensions = [
        'json', 'html', 'htm', 'css', 'js',
        'png', 'jpg', 'jpeg', 'svg', 'webp', 'gif', 'ico',
        'vtt', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'md', 'txt'
    ];

    // Explicitly prohibit executable scripts AND native video files
    private static $forbiddenExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'htaccess', 'htpasswd', 'sh', 'bat', 'cmd', 'exe',
        'cgi', 'pl', 'py', 'env', 'ini', 'phar',
        'mp4', 'webm', 'ogg', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'm4v', '3gp'
    ];

    /**
     * Imports and extracts an uploaded ZIP course package for a tenant.
     * Supports both native Superable course packages and LC-JSON 1.0 course/question-set packages.
     *
     * @param string $zipTmpPath
     * @param string $tenantKey
     * @return array Result containing status, message, course_id, and advisories
     */
    public static function importZip($zipTmpPath, $tenantKey = null) {
        $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
        $targetCoursesDir = getTenantCoursesDir($tenantKey);
        $advisories = [];

        if (!is_file($zipTmpPath)) {
            return ['success' => false, 'message' => 'Uploaded file is invalid or missing.'];
        }

        // 1. Quota Check (Dynamic based on Tier)
        $currentUsageBytes = getTenantStorageUsage($tenantKey);
        $zipSizeBytes = filesize($zipTmpPath);
        $quotaMb = getTenantStorageQuota($tenantKey);
        $maxBytes = $quotaMb * 1024 * 1024;

        if (($currentUsageBytes + $zipSizeBytes) > $maxBytes) {
            $usedMb = round($currentUsageBytes / 1048576, 1);
            return [
                'success' => false,
                'message' => "Storage Quota Exceeded: Your tenant currently uses {$usedMb} MB of the {$quotaMb} MB storage limit. This import would exceed your quota."
            ];
        }

        // Ensure target directory exists and write .htaccess protection
        if (!is_dir($targetCoursesDir)) {
            mkdir($targetCoursesDir, 0755, true);
        }
        self::ensureHtaccessProtection($targetCoursesDir);

        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'PHP ZipArchive extension is required on the server to import ZIP courses.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipTmpPath) !== true) {
            return ['success' => false, 'message' => 'Failed to open uploaded ZIP file. File may be corrupted.'];
        }

        $manifestIndex = -1;
        $lcJsonIndex = -1;
        $rootPrefix = '';

        // Scan ZIP contents for security, video blocks, course_structure.json and LC-JSON manifests
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = $stat['name'];

            // Path Traversal Guard
            if (strpos($entryName, '../') !== false || strpos($entryName, '..\\') !== false) {
                $zip->close();
                return ['success' => false, 'message' => "Security Error: Path traversal attempt detected in zip entry [{$entryName}]."];
            }

            if (substr($entryName, -1) === '/' || substr($entryName, -1) === '\\') {
                continue;
            }

            $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

            // Prohibited Extensions Check (PHP, Scripts & Video Files)
            if (in_array($ext, self::$forbiddenExtensions)) {
                $zip->close();
                if (in_array($ext, ['mp4', 'webm', 'ogg', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'm4v', '3gp'])) {
                    return ['success' => false, 'message' => "Video Upload Error: Direct video upload (.{$ext}) is disabled in entry [{$entryName}]. Please embed videos via authorized platforms (YouTube, Vimeo, Mux, Panopto, Loom, Zoom, Wistia)."];
                }
                return ['success' => false, 'message' => "Security Error: Prohibited file type '.{$ext}' found in zip entry [{$entryName}]. Executable scripts are strictly forbidden."];
            }

            if (!empty($ext) && !in_array($ext, self::$allowedExtensions)) {
                $zip->close();
                return ['success' => false, 'message' => "Security Error: Unsupported file extension '.{$ext}' in zip entry [{$entryName}]."];
            }

            $baseName = basename($entryName);
            if ($baseName === 'course_structure.json') {
                $manifestIndex = $i;
                $rootPrefix = dirname($entryName);
                if ($rootPrefix === '.' || $rootPrefix === '/' || $rootPrefix === '\\') {
                    $rootPrefix = '';
                } else {
                    $rootPrefix = trim($rootPrefix, '/\\') . '/';
                }
            } elseif ($ext === 'json') {
                // Check if entry is an LC-JSON document
                $content = $zip->getFromIndex($i);
                if ($content && LCJsonConverter::isLCJson($content)) {
                    $lcJsonIndex = $i;
                    if ($manifestIndex === -1) {
                        $rootPrefix = dirname($entryName);
                        if ($rootPrefix === '.' || $rootPrefix === '/' || $rootPrefix === '\\') {
                            $rootPrefix = '';
                        } else {
                            $rootPrefix = trim($rootPrefix, '/\\') . '/';
                        }
                    }
                }
            }
        }

        if ($manifestIndex === -1 && $lcJsonIndex === -1) {
            $zip->close();
            return ['success' => false, 'message' => 'Validation Error: Neither `course_structure.json` nor a valid `LC-JSON` specification manifest was found inside the ZIP package.'];
        }

        $isLcJsonImport = false;
        $lcJsonContent = null;

        if ($manifestIndex !== -1) {
            $manifestContent = $zip->getFromIndex($manifestIndex);
            if (LCJsonConverter::isLCJson($manifestContent)) {
                $isLcJsonImport = true;
                $lcJsonContent = $manifestContent;
                
                $valResult = self::validateLCJsonStructure($lcJsonContent);
                if (!$valResult['valid']) {
                    $zip->close();
                    return ['success' => false, 'message' => $valResult['message']];
                }

                $manifestData = json_decode($manifestContent, true);
                $courseTitle = $manifestData['title'] ?? 'LC-JSON Course';
            } else {
                $valResult = self::validateCourseStructure($manifestContent, $zip, $rootPrefix);
                if (!$valResult['valid']) {
                    $zip->close();
                    return ['success' => false, 'message' => $valResult['message']];
                }
                if (!empty($valResult['advisories'])) {
                    $advisories = array_merge($advisories, $valResult['advisories']);
                }

                $manifestData = json_decode($manifestContent, true);
                $courseTitle = $manifestData['properties']['title'];
            }
        } else {
            $isLcJsonImport = true;
            $lcJsonContent = $zip->getFromIndex($lcJsonIndex);

            $valResult = self::validateLCJsonStructure($lcJsonContent);
            if (!$valResult['valid']) {
                $zip->close();
                return ['success' => false, 'message' => $valResult['message']];
            }

            $manifestData = json_decode($lcJsonContent, true);
            $courseTitle = $manifestData['title'] ?? 'LC-JSON Course';
        }
        $courseId = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $courseTitle)));
        if (empty($courseId)) {
            $courseId = 'course-' . time();
        }

        $destDir = $targetCoursesDir . DIRECTORY_SEPARATOR . $courseId;

        // Auto-Backup existing course directory before overwriting
        if (is_dir($destDir)) {
            self::backupCourseDirectory($destDir, $targetCoursesDir, $courseId);
        }

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Extract allowed files into isolated tenant destination
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = $stat['name'];

            if (substr($entryName, -1) === '/' || substr($entryName, -1) === '\\') {
                continue;
            }

            $relativePath = $entryName;
            if (!empty($rootPrefix) && strpos($entryName, $rootPrefix) === 0) {
                $relativePath = substr($entryName, strlen($rootPrefix));
            }

            $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            if (!in_array($ext, self::$allowedExtensions)) {
                continue;
            }

            $targetFilePath = $destDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            $targetDir = dirname($targetFilePath);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $fileData = $zip->getFromIndex($i);

            // SVG Script Sanitization
            if ($ext === 'svg') {
                $fileData = self::sanitizeSvgContent($fileData);
            }

            // Auto-Fix HTML Accessibility (inject missing html/lang/title)
            if ($ext === 'html' || $ext === 'htm') {
                $fileData = self::autoFixHtmlAccessibility($fileData, $relativePath, $courseTitle, $manifestData);
            }

            file_put_contents($targetFilePath, $fileData);

            // Automated HTML Accessibility & Video Audit
            if ($ext === 'html' || $ext === 'htm') {
                $auditResults = self::auditHtmlAccessibilityAndVideos($fileData, $relativePath);
                if (!empty($auditResults)) {
                    $advisories = array_merge($advisories, $auditResults);
                }
            }
        }

        $zip->close();

        // If an LC-JSON manifest was detected, convert it into standard Superable course structure & modules
        if ($isLcJsonImport && !empty($lcJsonContent)) {
            $convResult = LCJsonConverter::convert($lcJsonContent, $destDir, $advisories);
            if (!$convResult['success']) {
                return [
                    'success' => false,
                    'message' => 'LC-JSON Conversion Error: ' . ($convResult['message'] ?? 'Failed to parse LC-JSON manifest.')
                ];
            }
        }

        return [
            'success' => true,
            'message' => "Course '{$courseTitle}' imported successfully into tenant!",
            'course_id' => $courseId,
            'advisories' => $advisories
        ];
    }

    /**
     * Updates course manifest configuration (Title, Access Type, Teaser Link, XCL Link).
     */
    public static function updateCourseManifest($courseId, $updates, $tenantKey = null) {
        $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
        $courseDir = getTenantCoursesDir($tenantKey) . DIRECTORY_SEPARATOR . basename($courseId);
        $manifestPath = $courseDir . DIRECTORY_SEPARATOR . 'course_structure.json';

        if (!file_exists($manifestPath)) {
            return ['success' => false, 'message' => 'Manifest file not found.'];
        }

        $data = json_decode(file_get_contents($manifestPath), true);
        if (!$data) {
            return ['success' => false, 'message' => 'Invalid JSON in manifest.'];
        }

        if (isset($updates['title'])) {
            $data['properties']['title'] = trim($updates['title']);
        }
        if (isset($updates['description'])) {
            $data['properties']['description'] = trim($updates['description']);
        }
        if (isset($updates['access_type'])) {
            $data['properties']['access']['type'] = trim($updates['access_type']);
        }
        if (isset($updates['teaser_link'])) {
            $data['properties']['access']['teaser_link'] = trim($updates['teaser_link']);
        }
        if (isset($updates['xcl_url'])) {
            $xcl = trim($updates['xcl_url']);
            if (empty($xcl)) {
                unset($data['properties']['url']);
            } else {
                $data['properties']['url'] = $xcl;
            }
        }
        if (isset($updates['status'])) {
            $data['properties']['status'] = trim($updates['status']);
        }

        file_put_contents($manifestPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ['success' => true, 'message' => 'Course manifest updated successfully!'];
    }

    /**
     * Deletes a course folder for a tenant.
     */
    public static function deleteCourse($courseId, $tenantKey = null) {
        $tenantKey = $tenantKey ? sanitizeTenantKey($tenantKey) : resolveTenantKey();
        $courseDir = getTenantCoursesDir($tenantKey) . DIRECTORY_SEPARATOR . basename($courseId);

        if (!is_dir($courseDir)) {
            return ['success' => false, 'message' => 'Course directory does not exist.'];
        }

        self::deleteDirectoryRecursive($courseDir);
        return ['success' => true, 'message' => "Course '{$courseId}' deleted successfully."];
    }

    /**
     * Sanitizes SVG content to remove embedded JavaScript and event handlers.
     */
    private static function sanitizeSvgContent($svg) {
        $svg = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svg);
        $svg = preg_replace('/on[a-z]+\s*=\s*"[^"]*"/i', '', $svg);
        $svg = preg_replace('/on[a-z]+\s*=\s*\'[^\']*\'/i', '', $svg);
        $svg = preg_replace('/javascript:/i', '', $svg);
        return $svg;
    }

    /**
     * Automatically wraps or updates HTML module fragments to ensure
     * valid <html>, lang="en", and <title> tags exist for accessibility.
     */
    private static function autoFixHtmlAccessibility($htmlContent, $relativePath, $courseTitle, $manifestData) {
        $moduleTitle = null;
        if (isset($manifestData['modules'])) {
            $moduleTitle = self::findModuleTitleInManifest($manifestData['modules'], $relativePath);
        }
        
        $titleStr = $moduleTitle ? htmlspecialchars($moduleTitle) : basename($relativePath, '.' . pathinfo($relativePath, PATHINFO_EXTENSION));
        $titleStr = ucwords(str_replace(['-', '_'], ' ', $titleStr));
        
        $hasHtml = (bool)preg_match('/<html\b/i', $htmlContent);
        
        if (!$hasHtml) {
            // It's an HTML fragment/stub. Wrap it fully.
            $htmlContent = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title>{$titleStr}</title>\n</head>\n<body>\n" . trim($htmlContent) . "\n</body>\n</html>";
        } else {
            // It has <html> but might be missing lang attribute
            if (!preg_match('/<html\b[^>]*\blang=/i', $htmlContent)) {
                $htmlContent = preg_replace('/<html\b/i', '<html lang="en"', $htmlContent, 1);
            }
            
            // It might be missing <title>
            if (!preg_match('/<title\b/i', $htmlContent)) {
                $titleTag = "<title>{$titleStr}</title>";
                if (preg_match('/<\/head>/i', $htmlContent)) {
                    $htmlContent = preg_replace('/<\/head>/i', "    {$titleTag}\n</head>", $htmlContent, 1);
                } elseif (preg_match('/<head\b[^>]*>/i', $htmlContent)) {
                    $htmlContent = preg_replace('/<head\b[^>]*>/i', "$0\n    {$titleTag}", $htmlContent, 1);
                } else {
                    $htmlContent = preg_replace('/<html\b[^>]*>/i', "$0\n<head>\n    {$titleTag}\n</head>", $htmlContent, 1);
                }
            }
        }
        
        return $htmlContent;
    }

    /**
     * Helper to recursively scan manifest modules (flat list or nested groups) to find a module's title.
     */
    private static function findModuleTitleInManifest($modules, $srcPath) {
        if (!is_array($modules)) {
            return null;
        }
        foreach ($modules as $m) {
            if (isset($m['group']) && is_array($m['items'])) {
                $title = self::findModuleTitleInManifest($m['items'], $srcPath);
                if ($title !== null) {
                    return $title;
                }
            } elseif (isset($m['src']) && str_replace('\\', '/', $m['src']) === str_replace('\\', '/', $srcPath)) {
                return $m['title'] ?? null;
            }
        }
        return null;
    }

    /**
     * Performs automated accessibility & YouTube/Vimeo embed audits on HTML module files.
     */
    private static function auditHtmlAccessibilityAndVideos($htmlContent, $filename) {
        $issues = [];

        // 1. Accessibility Checks
        if (!preg_match('/<html\b[^>]*\blang=["\'][a-z]{2}(-[a-z]{2})?["\']/i', $htmlContent)) {
            $issues[] = "[Accessibility Notice] {$filename}: Missing valid `lang` attribute on `<html>` tag.";
        }

        if (!preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $htmlContent)) {
            $issues[] = "[Accessibility Notice] {$filename}: Missing `<title>` tag.";
        }

        if (preg_match_all('/<img\b(?![^>]*\balt=)[^>]*>/i', $htmlContent, $matches)) {
            $count = count($matches[0]);
            $issues[] = "[Accessibility Notice] {$filename}: Contains {$count} `<img>` tag(s) missing an `alt` attribute.";
        }

        // 2. Video Restrictions Check (Authorized Embed Platforms Only)
        if (preg_match('/<video\b[^>]*>/i', $htmlContent)) {
            $issues[] = "[Video Policy Alert] {$filename}: Direct `<video>` elements found. Direct video files are disabled; please use authorized video embeds (YouTube, Vimeo, Mux, Panopto, Loom, Zoom, Wistia).";
        }

        if (preg_match_all('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $htmlContent, $iframeMatches)) {
            foreach ($iframeMatches[1] as $src) {
                if (!preg_match('/^https?:\/\/([a-z0-9-]+\.)?(youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com|player\.vimeo\.com|mux\.com|stream\.mux\.com|player\.mux\.com|panopto\.com|panopto\.eu|loom\.com|zoom\.us|wistia\.com|wistia\.net)/i', $src)) {
                    $issues[] = "[Video Policy Alert] {$filename}: Embedded iframe `{$src}` is not authorized. Only YouTube, Vimeo, Mux, Panopto, Loom, Zoom, and Wistia embeds are permitted.";
                }
            }
        }

        return $issues;
    }

    /**
     * Creates a backup of an existing course directory before overwriting.
     */
    private static function backupCourseDirectory($sourceDir, $targetCoursesDir, $courseId) {
        $backupsDir = $targetCoursesDir . DIRECTORY_SEPARATOR . '.backups';
        if (!is_dir($backupsDir)) {
            @mkdir($backupsDir, 0755, true);
        }
        $backupPath = $backupsDir . DIRECTORY_SEPARATOR . $courseId . '_' . date('Ymd_His');
        @rename($sourceDir, $backupPath);
    }

    /**
     * Ensures an .htaccess file prohibiting PHP execution exists inside target directory.
     */
    private static function ensureHtaccessProtection($dir) {
        $htaccessPath = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $rules = "# Security Protection: Disable Execution of Scripts\n"
                   . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps|cgi|pl|py|sh|bat|cmd)$\">\n"
                   . "    Order Deny,Allow\n"
                   . "    Deny from all\n"
                   . "</FilesMatch>\n"
                   . "Options -Indexes\n";
            @file_put_contents($htaccessPath, $rules);
        }
    }

    /**
     * Deeply validates a Superable course_structure.json manifest and verifies referenced files exist.
     *
     * @param string $manifestContent
     * @param ZipArchive $zip
     * @param string $rootPrefix
     * @return array Array containing 'valid' => bool, 'message' => string, 'advisories' => array
     */
    private static function validateCourseStructure($manifestContent, $zip, $rootPrefix = '') {
        $data = json_decode($manifestContent, true);
        if ($data === null) {
            return [
                'valid' => false, 
                'message' => 'JSON Syntax Error in course_structure.json: ' . json_last_error_msg()
            ];
        }

        if (!isset($data['properties']) || !is_array($data['properties'])) {
            return ['valid' => false, 'message' => 'Schema Error: Missing top-level "properties" object in course_structure.json.'];
        }

        $props = $data['properties'];
        if (empty($props['title']) || !is_string($props['title'])) {
            return ['valid' => false, 'message' => 'Schema Error: "properties.title" is required and must be a non-empty string.'];
        }

        // Validate access mode
        if (isset($props['access']['type'])) {
            $allowedAccess = ['public', 'protected', 'teaser', 'hidden'];
            if (!in_array($props['access']['type'], $allowedAccess)) {
                return ['valid' => false, 'message' => 'Schema Error: "properties.access.type" must be one of: ' . implode(', ', $allowedAccess) . '.'];
            }
        }

        if (!isset($data['modules']) || !is_array($data['modules'])) {
            return ['valid' => false, 'message' => 'Schema Error: Missing "modules" array in course_structure.json.'];
        }

        if (count($data['modules']) === 0) {
            return ['valid' => false, 'message' => 'Schema Error: The "modules" array must contain at least one module.'];
        }

        $seenIds = [];
        $advisories = [];

        $modulesValidation = self::validateModulesList($data['modules'], $zip, $rootPrefix, $seenIds, $advisories);
        if (!$modulesValidation['valid']) {
            return $modulesValidation;
        }

        // Validate optional asset file existence
        if (isset($props['assets']) && is_array($props['assets'])) {
            $assets = $props['assets'];
            foreach (['css', 'js'] as $type) {
                if (isset($assets[$type]) && is_array($assets[$type])) {
                    foreach ($assets[$type] as $assetPath) {
                        $zipAssetPath = $rootPrefix . str_replace('\\', '/', $assetPath);
                        if ($zip->locateName($zipAssetPath) === false) {
                            $advisories[] = "[Package Warning] Asset file '{$assetPath}' listed in manifest properties.assets.{$type} was not found inside the ZIP package.";
                        }
                    }
                }
            }
        }

        return ['valid' => true, 'advisories' => $advisories];
    }

    /**
     * Recursively validates the modules list, supporting both flat modules and grouped modules.
     */
    private static function validateModulesList($items, $zip, $rootPrefix, &$seenIds, &$advisories, $parentPath = '') {
        if (!is_array($items)) {
            return ['valid' => false, 'message' => 'Schema Error: "modules" or group "items" must be an array.'];
        }

        foreach ($items as $index => $m) {
            $num = $index + 1;
            $currentPath = $parentPath === '' ? "Module #{$num}" : "{$parentPath} -> Sub-item #{$num}";

            if (isset($m['group'])) {
                if (empty($m['group']) || !is_string($m['group'])) {
                    return ['valid' => false, 'message' => "Schema Error: {$currentPath} has a group, but the group title is missing or not a string."];
                }
                if (!isset($m['items']) || !is_array($m['items'])) {
                    return ['valid' => false, 'message' => "Schema Error: {$currentPath} (Group '{$m['group']}') is missing its 'items' array."];
                }
                $subRes = self::validateModulesList($m['items'], $zip, $rootPrefix, $seenIds, $advisories, "{$currentPath} (Group '{$m['group']}')");
                if (!$subRes['valid']) {
                    return $subRes;
                }
            } else {
                // Leaf module validation
                if (empty($m['id']) || !is_string($m['id'])) {
                    return ['valid' => false, 'message' => "Schema Error: {$currentPath} is missing a valid 'id' string."];
                }
                if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $m['id'])) {
                    return ['valid' => false, 'message' => "Schema Error: {$currentPath} 'id' ('{$m['id']}') is invalid. Only alphanumeric characters, hyphens, and underscores are permitted."];
                }
                if (in_array($m['id'], $seenIds)) {
                    return ['valid' => false, 'message' => "Schema Error: Duplicate module 'id' ('{$m['id']}') found in course_structure.json."];
                }
                $seenIds[] = $m['id'];

                if (empty($m['title']) || !is_string($m['title'])) {
                    return ['valid' => false, 'message' => "Schema Error: {$currentPath} ('{$m['id']}') is missing a valid 'title' string."];
                }

                if (empty($m['src']) || !is_string($m['src'])) {
                    return ['valid' => false, 'message' => "Schema Error: {$currentPath} ('{$m['id']}') is missing a valid 'src' path."];
                }

                $src = $m['src'];
                $zipSrcPath = str_replace('\\', '/', $src);
                $ext = strtolower(pathinfo($zipSrcPath, PATHINFO_EXTENSION));
                if (!in_array($ext, ['html', 'htm'])) {
                    return ['valid' => false, 'message' => "Schema Error: {$currentPath} ('{$m['id']}') 'src' ('{$src}') must point to an HTML file (.html or .htm)."];
                }

                $fullZipPath = $rootPrefix . $zipSrcPath;
                if ($zip->locateName($fullZipPath) === false) {
                    return [
                        'valid' => false,
                        'message' => "Package Validation Error: The module source file '{$src}' referenced in course_structure.json does not exist in the ZIP package."
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Validates an LC-JSON specification manifest structure.
     *
     * @param string $jsonContent
     * @return array Array containing 'valid' => bool, 'message' => string
     */
    private static function validateLCJsonStructure($jsonContent) {
        $data = json_decode($jsonContent, true);
        if ($data === null) {
            return [
                'valid' => false,
                'message' => 'JSON Syntax Error in LC-JSON manifest: ' . json_last_error_msg()
            ];
        }

        if (empty($data['documentType']) || !is_string($data['documentType'])) {
            return ['valid' => false, 'message' => 'LC-JSON Schema Error: Missing "documentType" property.'];
        }

        $docType = $data['documentType'];
        if (!in_array($docType, ['Course', 'QuestionSet'])) {
            return ['valid' => false, 'message' => 'LC-JSON Schema Error: "documentType" must be "Course" or "QuestionSet".'];
        }

        if (empty($data['title']) || !is_string($data['title'])) {
            return ['valid' => false, 'message' => 'LC-JSON Schema Error: "title" is required and must be a non-empty string.'];
        }

        if ($docType === 'Course') {
            if (!isset($data['units']) || !is_array($data['units'])) {
                return ['valid' => false, 'message' => 'LC-JSON Course Error: Missing "units" array.'];
            }
            if (count($data['units']) === 0) {
                return ['valid' => false, 'message' => 'LC-JSON Course Error: The "units" array must contain at least one unit.'];
            }
            foreach ($data['units'] as $uIndex => $unit) {
                $uNum = $uIndex + 1;
                if (empty($unit['title'])) {
                    return ['valid' => false, 'message' => "LC-JSON Course Error: Unit #{$uNum} is missing a 'title'."];
                }
                if (!isset($unit['lessons']) || !is_array($unit['lessons'])) {
                    return ['valid' => false, 'message' => "LC-JSON Course Error: Unit #{$uNum} is missing a 'lessons' array."];
                }
                foreach ($unit['lessons'] as $lIndex => $lesson) {
                    $lNum = $lIndex + 1;
                    if (empty($lesson['title'])) {
                        return ['valid' => false, 'message' => "LC-JSON Course Error: Unit #{$uNum}, Lesson #{$lNum} is missing a 'title'."];
                    }
                }
            }
        } else {
            // QuestionSet validation
            if (!isset($data['questions']) || !is_array($data['questions'])) {
                return ['valid' => false, 'message' => 'LC-JSON QuestionSet Error: Missing "questions" array.'];
            }
            foreach ($data['questions'] as $qIndex => $q) {
                $qNum = $qIndex + 1;
                if (empty($q['type']) || !is_string($q['type'])) {
                    return ['valid' => false, 'message' => "LC-JSON QuestionSet Error: Question #{$qNum} is missing a 'type'."];
                }
                if (empty($q['globalId']) || !is_string($q['globalId'])) {
                    return ['valid' => false, 'message' => "LC-JSON QuestionSet Error: Question #{$qNum} is missing a 'globalId'."];
                }
                if (empty($q['prompt']) || !is_string($q['prompt'])) {
                    return ['valid' => false, 'message' => "LC-JSON QuestionSet Error: Question #{$qNum} is missing a 'prompt'."];
                }
            }
        }

        return ['valid' => true];
    }

    private static function deleteDirectoryRecursive($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::deleteDirectoryRecursive($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
