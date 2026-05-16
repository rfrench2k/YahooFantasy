<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/fantasy/common/common.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/fantasy/common/aPRIV_API.php';

session_start();

logMessage("Yahoo OAuth callback received", 'INFO');
logMessage("GET parameters: " . json_encode($_GET), 'DEBUG');
logMessage("Session data: " . json_encode($_SESSION), 'DEBUG');

// Check for errors
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? '';
    logMessage("OAuth error: $error - $errorDescription", 'ERROR');
    header('Location: loginHTML.php?error=' . urlencode($error));
    exit;
}

// Verify state parameter
$receivedState = $_GET['state'] ?? '';
$sessionState = $_SESSION['oauth_state'] ?? '';

logMessage("Received state: $receivedState", 'DEBUG');
logMessage("Session state: $sessionState", 'DEBUG');

// Verify the state matches the value we issued (CSRF protection)
if (empty($receivedState) || $receivedState !== $sessionState) {
    logMessage("State validation failed: received=$receivedState, session=$sessionState", 'ERROR');
    header('Location: loginHTML.php?error=invalid_state');
    exit;
}

// Get authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    logMessage("No authorization code received", 'ERROR');
    header('Location: loginHTML.php?error=no_code');
    exit;
}

logMessage("Starting token exchange for code: " . substr($code, 0, 10) . "...", 'DEBUG');

// Exchange code for access token
$tokenData = exchangeCodeForToken($code);
logMessage("Token exchange result: " . ($tokenData ? "SUCCESS" : "FAILED"), 'DEBUG');
if (!$tokenData) {
    logMessage("Failed to exchange code for token", 'ERROR');
    header('Location: loginHTML.php?error=token_exchange_failed');
    exit;
}

// Get user info from Yahoo APIs
logMessage("Attempting to get Yahoo user info with access token", 'DEBUG');
$userInfo = getYahooUserInfo($tokenData['access_token']);

if (!$userInfo) {
    logMessage("OpenID Connect failed, trying Fantasy Sports API", 'WARNING');
    $userInfo = getYahooUserViaFantasyAPI($tokenData['access_token']);

    if (!$userInfo) {
        logMessage("All user info methods failed", 'ERROR');
        header('Location: loginHTML.php?error=user_info_failed');
        exit;
    }
}

// Get authenticated user from centralized AUTH system
$authUserId = getCurrentUserId();
if (!$authUserId) {
    logMessage("User not authenticated in centralized AUTH system", 'ERROR');
    header('Location: /auth/login.php?redirect=' . urlencode('/fantasy/auth/yahoo_auth.php'));
    exit;
}

// Store/update Yahoo tokens for this authenticated user
$yahooUserId = $userInfo['user_id'] ?? $userInfo['sub'] ?? $userInfo['guid'] ?? 'unknown';
$success = storeUserWithAuth($authUserId, $yahooUserId, $tokenData);
if (!$success) {
    logMessage("Failed to link Yahoo account to user: $authUserId", 'ERROR');
    header('Location: loginHTML.php?error=store_user_failed');
    exit;
}

logMessage("Yahoo account linked successfully: auth_user=$authUserId, yahoo_user=$yahooUserId", 'INFO');

// Close popup and refresh parent window
?>
<!DOCTYPE html>
<html>
<head>
    <title>Yahoo Login Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f0f0f0;
        }
        .success {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 0 auto;
        }
        .btn {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="success">
        <h2>✓ Yahoo Account Linked!</h2>
        <p>Your Yahoo Fantasy account has been successfully connected.</p>
        <button class="btn" onclick="closeWindow()">Close Window</button>
    </div>
    <script>
        function closeWindow() {
            if (window.opener) {
                window.opener.location.reload();
                window.close();
            } else {
                window.location.href = '/fantasy/football/dashboardHTML.php';
            }
        }

        // Try to auto-close after a short delay
        setTimeout(function() {
            if (window.opener) {
                window.opener.location.reload();
                setTimeout(function() {
                    window.close();
                }, 500);
            } else {
                window.location.href = '/fantasy/football/dashboardHTML.php';
            }
        }, 1000);
    </script>
</body>
</html>
<?php
exit;

function exchangeCodeForToken($code) {
    $postData = [
        'client_id' => YAHOO_CLIENT_ID,
        'client_secret' => YAHOO_CLIENT_SECRET,
        'redirect_uri' => YAHOO_REDIRECT_URI,
        'code' => $code,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, YAHOO_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Skip API logging for now to avoid include issues
    // logApiUsage('yahoo', '/oauth2/get_token', null, $httpCode);

    if ($httpCode !== 200) {
        logMessage("Token exchange failed with HTTP $httpCode: $response", 'ERROR');
        return false;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['access_token'])) {
        logMessage("Invalid token response: $response", 'ERROR');
        return false;
    }

    return $data;
}

function getYahooUserInfo($accessToken) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.login.yahoo.com/openid/v1/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logMessage("OpenID Connect API response: HTTP $httpCode - " . substr($response, 0, 200), 'DEBUG');

    if ($httpCode !== 200) {
        logMessage("User info request failed with HTTP $httpCode: $response", 'ERROR');
        return false;
    }

    $userData = json_decode($response, true);
    if ($userData && isset($userData['sub'])) {
        return [
            'user_id' => $userData['sub'],
            'sub' => $userData['sub'],
            'email' => $userData['email'] ?? '',
            'name' => $userData['name'] ?? ''
        ];
    }

    return false;
}

function getYahooUserViaFantasyAPI($accessToken) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://fantasysports.yahooapis.com/fantasy/v2/users;use_login=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/xml'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logMessage("Fantasy API response: HTTP $httpCode - " . substr($response, 0, 300), 'DEBUG');

    if ($httpCode !== 200) {
        logMessage("Fantasy API user request failed with HTTP $httpCode: $response", 'ERROR');
        return false;
    }

    // Parse XML response
    $xml = simplexml_load_string($response);
    if (!$xml) {
        logMessage("Failed to parse Fantasy API XML response", 'ERROR');
        return false;
    }

    logMessage("Parsed XML structure: " . print_r($xml, true), 'DEBUG');

    // Try different ways to extract user ID from XML
    $userGuid = null;

    // Method 1: Direct access
    if (isset($xml->user->guid)) {
        $userGuid = (string)$xml->user->guid;
    }

    // Method 2: Check if it's in users array
    if (!$userGuid && isset($xml->users->user->guid)) {
        $userGuid = (string)$xml->users->user->guid;
    }

    // Method 3: Check attributes
    if (!$userGuid && isset($xml->user['guid'])) {
        $userGuid = (string)$xml->user['guid'];
    }

    if ($userGuid) {
        logMessage("Successfully extracted user GUID from Fantasy API: $userGuid", 'INFO');
        return [
            'user_id' => $userGuid,
            'sub' => $userGuid,
            'guid' => $userGuid
        ];
    }

    logMessage("No user GUID found in Fantasy API response", 'ERROR');
    return false;
}

function storeUserWithAuth($authUserId, $yahooUserId, $tokenData) {
    $expiresAt = date('Y-m-d H:i:s', time() + $tokenData['expires_in']);
    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null;

    // Check if user already exists for this auth user_id
    $sql = "SELECT id FROM users WHERE user_id = ?";
    $result = executeQuery($sql, [$authUserId], 's');

    if ($result['success'] && !empty($result['data'])) {
        // Update existing user with Yahoo credentials
        $updateSql = "UPDATE users SET yahoo_user_id = ?, access_token = ?, refresh_token = ?, token_expires = ?, updated_at = NOW() WHERE user_id = ?";
        $updateResult = executeQuery($updateSql, [$yahooUserId, $accessToken, $refreshToken, $expiresAt, $authUserId], 'sssss');

        if ($updateResult['success']) {
            logMessage("Updated Yahoo link for auth user: $authUserId -> Yahoo: $yahooUserId", 'INFO');
            return true;
        }
    } else {
        // Create new user record linking auth user to Yahoo
        $insertSql = "INSERT INTO users (user_id, yahoo_user_id, access_token, refresh_token, token_expires) VALUES (?, ?, ?, ?, ?)";
        $insertResult = executeQuery($insertSql, [$authUserId, $yahooUserId, $accessToken, $refreshToken, $expiresAt], 'sssss');

        if ($insertResult['success']) {
            logMessage("Created new Yahoo link for auth user: $authUserId -> Yahoo: $yahooUserId", 'INFO');
            return true;
        }
    }

    logMessage("Failed to link Yahoo account for auth user: $authUserId", 'ERROR');
    return false;
}
?>