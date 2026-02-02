<?php
/**
 * WBCE Updater - Compatibility Check Endpoint
 *
 * AJAX endpoint for checking PHP compatibility with target WBCE version
 *
 * @category    module
 * @package     wbce_updater
 * @version     0.9.11
 * @author      WBCE Community
 * @copyright   2025 WBCE Community
 * @license     MIT License
 */

// Start output buffering to catch any unwanted output
ob_start();

// Error handling - don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include WBCE framework
$configFile = dirname(dirname(dirname(__FILE__))) . '/config.php';
if (!file_exists($configFile)) {
    ob_end_clean();
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Configuration file not found']));
}
require $configFile;

// Include compatibility checker
require_once __DIR__ . '/compatibility_checker.php';

// Check if user is logged in (session-based check)
if (!isset($_SESSION['USER_ID']) || !$_SESSION['USER_ID']) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Not authenticated']));
}

// Clear any output that might have been generated
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');

try {
    // Get target version from GET or POST
    $target_version = $_GET['version'] ?? $_POST['version'] ?? '';

    if (empty($target_version)) {
        throw new Exception('No version specified');
    }

    // Sanitize version string (allow only digits, dots, and 'v' prefix)
    $target_version = preg_replace('/[^0-9.v]/i', '', $target_version);

    if (empty($target_version)) {
        throw new Exception('Invalid version format');
    }

    // Check PHP compatibility
    $result = checkPhpCompatibility($target_version);

    // Add current PHP version info
    $result['php_version'] = PHP_VERSION;

    // Check EOL status
    $requirements = loadPhpRequirements();
    if ($requirements !== false) {
        $eolCheck = checkPhpEol(PHP_VERSION, $requirements);
        $result['php_eol'] = $eolCheck;
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
