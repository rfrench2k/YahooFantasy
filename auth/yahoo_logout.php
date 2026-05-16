<?php
/**
 * Yahoo Logout - Clear the current user's Yahoo OAuth tokens only.
 * Keeps the centralized AUTH session intact so the user can re-link Yahoo.
 */
include_once '../common/common.php';

logMessage("Yahoo re-login requested", 'INFO');

$userId = getCurrentUserId();
if ($userId) {
    executeQuery(
        "UPDATE users SET access_token = NULL, refresh_token = NULL, token_expires = NULL WHERE user_id = ?",
        [$userId],
        's'
    );
}

// Redirect to the login page to re-link Yahoo
header('Location: /fantasy/auth/loginHTML.php');
exit;
?>
