<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/fantasy/common/common.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/fantasy/common/aPRIV_API.php';

session_start();

logMessage("Yahoo OAuth initiation requested", 'INFO');

// Generate state parameter for security
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Yahoo OAuth URL parameters
$params = [
    'client_id' => YAHOO_CLIENT_ID,
    'redirect_uri' => YAHOO_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'fspt-r', // Fantasy Sports read permission
    'state' => $state
];

$authUrl = YAHOO_AUTH_URL . '?' . http_build_query($params);

logMessage("Redirecting to Yahoo OAuth: " . $authUrl, 'INFO');

header('Location: ' . $authUrl);
exit;
?>