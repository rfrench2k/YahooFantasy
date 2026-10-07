<?php
/**
 * Base Functions for Fantasy Football AI
 *
 * This file provides core logging and error handling functionality.
 * It does NOT handle authentication (that's in common.php).
 *
 * Used by: common.php and all other fantasy app files
 */

/**
 * Log a message to the fantasy application log file
 *
 * @param string $message The message to log
 * @param string $level Log level: INFO, WARNING, ERROR, DEBUG
 * @return void
 */
function logMessage($message, $level = 'INFO') {
    $logFile = 'D:/AdvancedVentures/logs/fantasy/fantasy.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Custom error handler
 * Logs PHP errors to fantasy.log
 *
 * @param int $errno Error number
 * @param string $errstr Error message
 * @param string $errfile File where error occurred
 * @param int $errline Line number where error occurred
 * @return bool Returns false to allow PHP's internal error handler to run as well
 */
function errorHandler($errno, $errstr, $errfile, $errline) {
    $message = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    logMessage($message, 'ERROR');
    return false; // Let PHP handle it normally
}

// Set custom error handler
set_error_handler('errorHandler');

?>
