<?php
/**
 * Fantasy Football AI - Main Entry Point
 *
 * Flow:
 * 1. Check centralized AUTH - redirect to /auth/login.php if not authenticated
 * 2. Check Yahoo account linked - redirect to loginHTML.php if not linked
 * 3. If both complete - redirect to dashboard
 */

// Load common functions (includes centralized AUTH check)
// common.php already includes all database functions - don't duplicate
require_once __DIR__ . '/common/common.php';

// Check if user is authenticated via centralized AUTH
if (!$GLOBALS['sessionUser']) {
    // Not logged in - redirect to centralized auth
    header('Location: /auth/login.php?program=FANTASY&redirect=/fantasy/');
    exit;
}

$userId = $GLOBALS['sessionUser']['user_id'];
$userEmail = $GLOBALS['sessionUser']['user_email'];
$userName = $GLOBALS['sessionUser']['user_name'] ?? $userEmail;

// Check if user has linked Yahoo account
$sql = "SELECT id FROM users WHERE user_id = ? AND access_token IS NOT NULL";
$result = executeQuery($sql, [$userId], 's');
$yahooLinked = ($result['success'] && !empty($result['data']));

// If Yahoo account is not linked, show the linking page
if (!$yahooLinked) {
    header('Location: /fantasy/auth/loginHTML.php');
    exit;
}

// Both centralized AUTH and Yahoo are complete - go to dashboard
header('Location: /fantasy/football/dashboardHTML.php');
exit;
?>