<?php
/**
 * TEMPORARY DIAGNOSTIC SCRIPT - DELETE AFTER USE
 * Upload this to the server and call it directly to test the update flow
 * GET: https://app.airigojobs.com/public/debug_job_update.php
 */

// Show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../src/bootstrap.php';

$result = [];

// 1. Check PHP version
$result['php_version'] = PHP_VERSION;

// 2. Check temp dir is writable
$tmpDir = sys_get_temp_dir();
$result['tmp_dir'] = $tmpDir;
$result['tmp_dir_writable'] = is_writable($tmpDir);

// 3. Check if we can write a test file to tmp
$testFile = $tmpDir . '/test_write_' . uniqid() . '.txt';
$writeResult = file_put_contents($testFile, 'test');
$result['tmp_write_test'] = $writeResult !== false ? 'OK' : 'FAILED';
if ($writeResult !== false) {
    unlink($testFile);
}

// 4. Check Firebase config
try {
    $firebaseStorage = new \Firebase\FirebaseStorageService();
    $result['firebase_service'] = 'instantiated OK';
    $result['firebase_bucket'] = \App\Config\AppConfig::get('firebase.storage_bucket');
    $result['firebase_project'] = \App\Config\AppConfig::get('firebase.project_id');
    $result['firebase_client_email'] = \App\Config\AppConfig::get('firebase.client_email');
    $privateKey = \App\Config\AppConfig::get('firebase.private_key');
    $result['firebase_private_key_length'] = strlen($privateKey ?? '');
    $result['firebase_private_key_starts'] = substr($privateKey ?? '', 0, 30);
} catch (\Throwable $e) {
    $result['firebase_error'] = $e->getMessage();
    $result['firebase_trace'] = $e->getTraceAsString();
}

// 5. Check DB connection
try {
    $repo = new \App\Repositories\JobRepository();
    $job = $repo->findById(46);
    $result['db_connection'] = 'OK';
    $result['job_46_exists'] = $job ? true : false;
    if ($job) {
        $result['job_46_logo'] = $job['company_logo_url'] ?? 'null';
        $result['job_46_recruiter'] = $job['recruiter_user_id'] ?? 'null';
    }
} catch (\Throwable $e) {
    $result['db_error'] = $e->getMessage();
}

// 6. Check if JobController update method exists and can be reflected
try {
    $ref = new ReflectionMethod(\App\Core\Http\Controllers\JobController::class, 'update');
    $result['controller_update_method'] = 'exists';
    // Get the file and line to confirm which version is loaded
    $result['controller_file'] = $ref->getFileName();
    $result['controller_start_line'] = $ref->getStartLine();
} catch (\Throwable $e) {
    $result['controller_error'] = $e->getMessage();
}

// 7. Check OPcache - if enabled, old code may be cached
if (function_exists('opcache_get_status')) {
    $opcache = opcache_get_status(false);
    $result['opcache_enabled'] = $opcache['opcache_enabled'] ?? false;
    $result['opcache_cached_scripts'] = $opcache['opcache_statistics']['num_cached_scripts'] ?? 0;
} else {
    $result['opcache_enabled'] = 'function not available';
}

// 8. Try to reset OPcache
if (function_exists('opcache_reset')) {
    $resetResult = opcache_reset();
    $result['opcache_reset'] = $resetResult ? 'reset OK' : 'reset failed';
}

echo json_encode($result, JSON_PRETTY_PRINT);
