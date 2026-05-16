<?php
include_once '../common/common.php';

logMessage("User logout requested", 'INFO');

// Clear this user's Yahoo connection so logout actually disconnects Yahoo
$logoutUserId = getCurrentUserId();
if ($logoutUserId) {
    executeQuery(
        "UPDATE users SET access_token = NULL, refresh_token = NULL, token_expires = NULL WHERE user_id = ?",
        [$logoutUserId],
        's'
    );
}

// Destroy the PHP session if one is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();

logMessage("User logged out successfully", 'INFO');

// Redirect to login
header('Location: loginHTML.php?message=logged_out');
exit;
?>