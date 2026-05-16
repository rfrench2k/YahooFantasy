<?php
/**
 * Common functions and initialization for Fantasy Football AI
 *
 * This file:
 * 1. Loads base functions (logging, error handling)
 * 2. Integrates with centralized AUTH system
 * 3. Sets up $GLOBALS['sessionUser'] for authenticated users
 * 4. Provides database connectivity and helper functions
 */

// Load base functions first (logging, error handling)
require_once __DIR__ . '/baseFunctions.php';

// Load database functions
require_once __DIR__ . '/aPRIV_DB.php';

// Load centralized AUTH functions
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

// Set timezone for consistent date/time handling
date_default_timezone_set('America/Los_Angeles');

$version = time(); // Use `time()` for a unique version on every reload

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Application Settings
define('APP_ROOT', __DIR__);
define('LOG_FILE_PATH', APP_ROOT . '/fantasy.log');

// Get authenticated user from centralized AUTH system
// This runs for all requests EXCEPT CLI scripts
if (php_sapi_name() !== 'cli') {
    $GLOBALS['sessionUser'] = auth_getAuthenticatedUser('FANTASY');
    // Note: We don't exit here if not authenticated
    // - HTML files: header.php will handle redirect
    // - Code.php files: getCurrentUserId() will return null

    if ($GLOBALS['sessionUser']) {
        logMessage("User authenticated: " . $GLOBALS['sessionUser']['user_id'], 'DEBUG');
    }
}

/**
 * Get current user's user_id from session
 *
 * @return string|null User ID from auth system, or null if not authenticated
 */
function getCurrentUserId() {
    if (isset($GLOBALS['sessionUser']) && isset($GLOBALS['sessionUser']['user_id'])) {
        return $GLOBALS['sessionUser']['user_id'];
    }
    return null;
}

/**
 * Get current user's email from session
 *
 * @return string|null User email from auth system, or null if not authenticated
 */
function getCurrentUserEmail() {
    if (isset($GLOBALS['sessionUser']) && isset($GLOBALS['sessionUser']['user_email'])) {
        return $GLOBALS['sessionUser']['user_email'];
    }
    return null;
}

/**
 * Get current user's name from session
 *
 * @return string|null User name from auth system, or null if not authenticated
 */
function getCurrentUserName() {
    if (isset($GLOBALS['sessionUser']) && isset($GLOBALS['sessionUser']['user_name'])) {
        return $GLOBALS['sessionUser']['user_name'];
    }
    return null;
}

// Generate unique ID for file names
function generateUniqueID() {
    $randomString = bin2hex(random_bytes(16));
    $uniqueInput = $randomString . microtime(true);
    return hash('sha256', $uniqueInput);
}

// Function to safely get POST/GET values with default
function getRequestParam($key, $default = null) {
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

// Initialize required directories
function initializeDirectories() {
    $directories = [
        dirname(LOG_FILE_PATH)
    ];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            logMessage("Created directory: $dir", 'INFO');
        }
    }
}

// executeQuery() is now defined in aPRIV_DB.php (included at top of this file)

/**
 * Simple API usage logging function for Yahoo and Claude API calls
 *
 * @param string $provider The API provider (yahoo, claude)
 * @param string $endpoint The endpoint path
 * @param string|null $params Parameters or symbol being requested (optional)
 * @param int $httpStatus HTTP response status code
 * @param int|null $responseTimeMs Response time in milliseconds (optional)
 * @param int $costCalls Number of API calls this request counts as (default 1)
 * @return bool Success status of logging (doesn't throw exceptions)
 */
function logApiUsage($provider, $endpoint, $params = null, $httpStatus = 200, $responseTimeMs = null, $costCalls = 1) {
    try {
        $sql = "INSERT INTO api_usage_log (provider, endpoint, params, http_status, cost_calls, requested_at, response_ms)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)";

        $queryParams = [$provider, $endpoint, $params, $httpStatus, $costCalls, $responseTimeMs];
        $types = 'sssiis';

        $result = executeQuery($sql, $queryParams, $types);

        if (!$result['success']) {
            logMessage("Failed to log API usage: " . $result['error'], 'WARNING');
            return false;
        }

        return true;

    } catch (Exception $e) {
        // Don't let API logging break the main functionality
        logMessage("Error in logApiUsage: " . $e->getMessage(), 'WARNING');
        return false;
    }
}

/**
 * Get API usage statistics for quota tracking
 *
 * @param string $provider The API provider to check
 * @param string $timeWindow 'today', 'hour', 'minute', 'second'
 * @return array Usage statistics
 */
function getApiUsage($provider, $timeWindow = 'today') {
    try {
        $whereClause = match($timeWindow) {
            'today' => "requested_at >= CURDATE()",
            'hour' => "requested_at >= (NOW() - INTERVAL 1 HOUR)",
            'minute' => "requested_at >= (NOW() - INTERVAL 1 MINUTE)",
            'second' => "requested_at >= (NOW() - INTERVAL 1 SECOND)",
            default => "requested_at >= CURDATE()"
        };

        $sql = "SELECT
                    COUNT(*) as total_requests,
                    SUM(cost_calls) as total_calls,
                    AVG(response_ms) as avg_response_ms,
                    COUNT(DISTINCT endpoint) as unique_endpoints,
                    COUNT(DISTINCT params) as unique_params
                FROM api_usage_log
                WHERE provider = ? AND $whereClause";

        $result = executeQuery($sql, [$provider], 's');

        if (!$result['success'] || empty($result['data'])) {
            return ['total_requests' => 0, 'total_calls' => 0, 'avg_response_ms' => 0, 'unique_endpoints' => 0, 'unique_params' => 0];
        }

        return $result['data'][0];

    } catch (Exception $e) {
        logMessage("Error getting API usage: " . $e->getMessage(), 'ERROR');
        return ['total_requests' => 0, 'total_calls' => 0, 'avg_response_ms' => 0, 'unique_endpoints' => 0, 'unique_params' => 0];
    }
}

?>
