<?php
include_once '../common/common.php';
include_once '../common/YahooAPI.php';
include_once '../common/ClaudeAPI.php';

header('Content-Type: application/json');

// Get authenticated user from centralized AUTH (set in common.php)
$userId = getCurrentUserId();

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// NOW: $userId = 'your_user_id' (user_id from AUTH, NOT Yahoo GUID)

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$action = $data['action'];

logMessage("Analysis action requested: $action by user: $userId", 'INFO');

try {
    switch ($action) {
        case 'startAnalysis':
            if (!isset($data['league_key'])) {
                throw new Exception('League key required');
            }
            $focusArea = $data['focus_area'] ?? '';
            $positions = $data['positions'] ?? [];
            handleStartAnalysis($userId, $data['league_key'], $focusArea, $positions);
            break;

        case 'getAnalysisStatus':
            if (!isset($data['request_id'])) {
                throw new Exception('Request ID required');
            }
            handleGetAnalysisStatus($data['request_id']);
            break;

        case 'getPlayerInsights':
            if (!isset($data['player_name']) || !isset($data['position'])) {
                throw new Exception('Player name and position required');
            }
            handleGetPlayerInsights($userId, $data['player_name'], $data['position']);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    logMessage("Analysis error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleStartAnalysis($userId, $leagueKey, $focusArea = '', $positions = []) {
    // Get user's database ID
    $userDbId = getUserDbId($userId);
    if (!$userDbId) {
        throw new Exception('User not found in database');
    }

    // Get league ID from database
    $leagueId = getLeagueId($userDbId, $leagueKey);
    if (!$leagueId) {
        throw new Exception('League not found. Please select the league from the dashboard first.');
    }

    // Create analysis request
    $requestId = createAnalysisRequest($userDbId, $leagueId);

    // Start background processing
    processAnalysisInBackground($requestId, $userId, $leagueKey, $focusArea, $positions);

    logMessage("Started analysis request: $requestId for league: $leagueKey", 'INFO');

    echo json_encode([
        'success' => true,
        'request_id' => $requestId,
        'message' => 'Analysis started'
    ]);
}

function handleGetAnalysisStatus($requestId) {
    $sql = "SELECT ar.*, ast.name as status_name
            FROM analysis_requests ar
            JOIN analysis_status_types ast ON ar.status_id = ast.id
            WHERE ar.id = ?";

    $result = executeQuery($sql, [$requestId], 'i');

    if (!$result['success'] || empty($result['data'])) {
        throw new Exception('Analysis request not found');
    }

    $analysis = $result['data'][0];

    $response = [
        'success' => true,
        'status' => $analysis['status_name'],
        'created_at' => $analysis['created_at'],
        'completed_at' => $analysis['completed_at']
    ];

    // If completed, include recommendations
    if ($analysis['status_name'] === 'completed' && $analysis['recommendations']) {
        $recommendations = json_decode($analysis['recommendations'], true);
        $response['recommendations'] = $recommendations;
    }

    echo json_encode($response);
}

function handleGetPlayerInsights($userId, $playerName, $position) {
    logMessage("=== PLAYER INSIGHTS REQUEST ===", 'INFO');
    logMessage("User: $userId", 'INFO');
    logMessage("Player: $playerName", 'INFO');
    logMessage("Position: $position", 'INFO');

    $claudeAPI = new ClaudeAPI();
    $insights = $claudeAPI->getPlayerInsights($userId, $playerName, $position);

    logMessage("Result type: " . gettype($insights), 'DEBUG');
    logMessage("Result is false? " . ($insights === false ? 'YES' : 'NO'), 'DEBUG');
    logMessage("Result is empty? " . (empty($insights) ? 'YES' : 'NO'), 'DEBUG');

    if ($insights && is_string($insights)) {
        logMessage("Insights length: " . strlen($insights) . " characters", 'DEBUG');
        logMessage("First 300 chars: " . substr($insights, 0, 300), 'DEBUG');
    }

    if ($insights === false || empty($insights)) {
        logMessage("ERROR: No insights data returned from Claude API", 'ERROR');
        throw new Exception('Failed to get player insights - no data returned');
    }

    logMessage("SUCCESS: Returning insights to client", 'INFO');
    logMessage("=== END PLAYER INSIGHTS ===", 'INFO');

    echo json_encode([
        'success' => true,
        'insights' => $insights,
        'player_name' => $playerName,
        'position' => $position
    ]);
}

function processAnalysisInBackground($requestId, $userId, $leagueKey, $focusArea = '', $positions = []) {
    // In a production environment, this would be handled by a background job queue
    // For now, we'll process it immediately but mark as processing

    // Update status to processing
    updateAnalysisStatus($requestId, 'processing');

    try {
        // Get access token
        $accessToken = getUserAccessToken($userId);
        if (!$accessToken) {
            throw new Exception('No valid access token found');
        }

        $yahooAPI = new YahooAPI();

        // Get user's team
        $team = $yahooAPI->getUserTeam($leagueKey, $accessToken);
        if (!$team) {
            throw new Exception('Could not find user team in league');
        }

        // Get roster data
        $rosterData = $yahooAPI->getTeamRoster($leagueKey, $team['team_key'], $accessToken);
        if ($rosterData === false) {
            throw new Exception('Failed to fetch roster data');
        }

        // Get available players
        $availablePlayers = $yahooAPI->getAvailablePlayers($leagueKey, $accessToken, 100);
        if ($availablePlayers === false) {
            throw new Exception('Failed to fetch available players');
        }

        // Get league info for settings
        $leagueInfo = $yahooAPI->getLeagueInfo($leagueKey, $accessToken);
        $leagueSettings = [
            'league_name' => $leagueInfo['fantasy_content']['league']['name'] ?? 'Unknown League',
            'scoring_type' => $leagueInfo['fantasy_content']['league']['scoring_type'] ?? 'standard'
        ];

        // Store raw data in analysis request
        $updateDataSql = "UPDATE analysis_requests SET roster_data = ?, available_players_data = ? WHERE id = ?";
        $updateDataResult = executeQuery($updateDataSql, [
            json_encode($rosterData),
            json_encode($availablePlayers),
            $requestId
        ], 'ssi');

        // Get user_id for AI tracking
        // Note: $userId is passed from processAnalysisInBackground()
        // It's the user_id from centralized AUTH (e.g., 'your_user_id')

        // Run AI analysis
        $claudeAPI = new ClaudeAPI();
        $analysisResult = $claudeAPI->analyzeTeam($userId, $rosterData, $availablePlayers, $leagueSettings, $focusArea);

        if (!$analysisResult || !$analysisResult['success']) {
            throw new Exception('AI analysis failed: ' . ($analysisResult['error'] ?? 'Unknown error'));
        }

        // Store recommendations and mark as completed
        $recommendations = $analysisResult['recommendations'];
        $updateSql = "UPDATE analysis_requests SET recommendations = ?, status_id = (SELECT id FROM analysis_status_types WHERE name = 'completed'), completed_at = NOW() WHERE id = ?";
        $updateResult = executeQuery($updateSql, [json_encode($recommendations), $requestId], 'si');

        if (!$updateResult['success']) {
            throw new Exception('Failed to store analysis results');
        }

        logMessage("Analysis completed successfully for request: $requestId", 'INFO');

    } catch (Exception $e) {
        logMessage("Analysis failed for request $requestId: " . $e->getMessage(), 'ERROR');

        // Mark as failed
        updateAnalysisStatus($requestId, 'failed');

        // Store error in recommendations field for debugging
        $errorData = ['error' => $e->getMessage(), 'timestamp' => date('Y-m-d H:i:s')];
        $updateSql = "UPDATE analysis_requests SET recommendations = ? WHERE id = ?";
        executeQuery($updateSql, [json_encode($errorData), $requestId], 'si');
    }
}

function createAnalysisRequest($userDbId, $leagueId) {
    $sql = "INSERT INTO analysis_requests (user_id, league_id, status_id)
            VALUES (?, ?, (SELECT id FROM analysis_status_types WHERE name = 'pending'))";

    $result = executeQuery($sql, [$userDbId, $leagueId], 'ii');

    if (!$result['success']) {
        throw new Exception('Failed to create analysis request');
    }

    return $result['insert_id'];
}

function updateAnalysisStatus($requestId, $status) {
    $sql = "UPDATE analysis_requests SET status_id = (SELECT id FROM analysis_status_types WHERE name = ?) WHERE id = ?";
    $result = executeQuery($sql, [$status, $requestId], 'si');

    if (!$result['success']) {
        logMessage("Failed to update analysis status to $status for request $requestId", 'ERROR');
    }
}

/**
 * Get user's database ID from user_id
 *
 * @param string $userId User ID from centralized AUTH
 * @return int|false Database ID on success, false on failure
 */
function getUserDbId($userId) {
    // $userId is user_id from centralized AUTH (e.g., 'your_user_id'), NOT yahoo_user_id
    $sql = "SELECT id FROM users WHERE user_id = ?";
    $result = executeQuery($sql, [$userId], 's');

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0]['id'];
    }

    logMessage("User not found in database for user_id: $userId", 'ERROR');
    return false;
}

function getLeagueId($userDbId, $leagueKey) {
    // Extract league ID from league key (format: nfl.l.123456)
    $parts = explode('.', $leagueKey);
    $yahooLeagueId = end($parts);

    // First try with the extracted ID
    $sql = "SELECT id FROM leagues WHERE user_id = ? AND yahoo_league_id = ?";
    $result = executeQuery($sql, [$userDbId, $yahooLeagueId], 'is');

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0]['id'];
    }

    // If not found, try with the full league key
    $result = executeQuery($sql, [$userDbId, $leagueKey], 'is');

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0]['id'];
    }

    // If still not found, log for debugging and create the league entry
    logMessage("League not found in database for user $userDbId, league key: $leagueKey, extracted ID: $yahooLeagueId", 'WARNING');

    // Try to get user access token and create the league entry
    $userId = getUserIdFromDbId($userDbId);
    if ($userId) {
        $accessToken = getUserAccessToken($userId);
        if ($accessToken) {
            $yahooAPI = new YahooAPI();
            $leagueInfo = $yahooAPI->getLeagueInfo($leagueKey, $accessToken);

            if ($leagueInfo && isset($leagueInfo['fantasy_content']['league'])) {
                $league = $leagueInfo['fantasy_content']['league'];
                $scoringType = $league['scoring_type'] ?? 'standard';
                // Map Yahoo scoring types to our database values
                if ($scoringType === 'head') {
                    $scoringType = 'standard'; // head-to-head maps to standard
                }

                $leagueData = [
                    'league_id' => $yahooLeagueId,
                    'name' => $league['name'] ?? 'Unknown League',
                    'scoring_type' => $scoringType
                ];

                // Store the league
                storeLeague($userId, $leagueData);

                // Try to get the ID again
                $result = executeQuery($sql, [$userDbId, $yahooLeagueId], 'is');
                if ($result['success'] && !empty($result['data'])) {
                    return $result['data'][0]['id'];
                }
            }
        }
    }

    return false;
}

/**
 * Get user's Yahoo access token (with automatic refresh if expired)
 *
 * @param string $userId User ID from centralized AUTH
 * @return string|false Access token on success, false on failure
 */
function getUserAccessToken($userId) {
    // $userId is user_id from centralized AUTH (e.g., 'your_user_id'), NOT yahoo_user_id
    $sql = "SELECT access_token, refresh_token, token_expires FROM users WHERE user_id = ?";
    $result = executeQuery($sql, [$userId], 's');

    if (!$result['success'] || empty($result['data'])) {
        logMessage("No Yahoo tokens found for user_id: $userId", 'ERROR');
        return false;
    }

    $user = $result['data'][0];
    $accessToken = $user['access_token'];
    $refreshToken = $user['refresh_token'];
    $tokenExpires = $user['token_expires'];

    // Check if token is expired and refresh if needed
    if ($tokenExpires && strtotime($tokenExpires) <= time()) {
        logMessage("Access token expired for user_id: $userId, refreshing...", 'INFO');

        if ($refreshToken) {
            $yahooAPI = new YahooAPI();
            $newTokenData = $yahooAPI->refreshToken($refreshToken);

            if ($newTokenData) {
                $newAccessToken = $newTokenData['access_token'];
                $newRefreshToken = $newTokenData['refresh_token'] ?? $refreshToken;
                $newExpires = date('Y-m-d H:i:s', time() + $newTokenData['expires_in']);

                $updateSql = "UPDATE users SET access_token = ?, refresh_token = ?, token_expires = ? WHERE user_id = ?";
                executeQuery($updateSql, [$newAccessToken, $newRefreshToken, $newExpires, $userId], 'ssss');

                logMessage("Token refreshed successfully for user_id: $userId", 'INFO');
                return $newAccessToken;
            }
        }

        logMessage("Token refresh failed for user_id: $userId", 'ERROR');
        return false;
    }

    return $accessToken;
}

function getUserIdFromDbId($userDbId) {
    $sql = "SELECT user_id FROM users WHERE id = ?";
    $result = executeQuery($sql, [$userDbId], 'i');

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0]['user_id'];
    }

    return false;
}

function storeLeague($userId, $leagueData) {
    // Get the actual database user ID first
    $userDbId = getUserDbId($userId);
    if (!$userDbId) {
        logMessage("Cannot store league - user not found: $userId", 'ERROR');
        return false;
    }

    // Get scoring type ID
    $scoringTypeSql = "SELECT id FROM scoring_types WHERE name = ?";
    $scoringResult = executeQuery($scoringTypeSql, [$leagueData['scoring_type']], 's');

    $scoringTypeId = 1; // Default to standard
    if ($scoringResult['success'] && !empty($scoringResult['data'])) {
        $scoringTypeId = $scoringResult['data'][0]['id'];
    }

    // Check if league already exists
    $checkSql = "SELECT id FROM leagues WHERE yahoo_league_id = ? AND user_id = ?";
    $checkResult = executeQuery($checkSql, [$leagueData['league_id'], $userDbId], 'si');

    if ($checkResult['success'] && !empty($checkResult['data'])) {
        // Update existing league
        $leagueId = $checkResult['data'][0]['id'];
        $updateSql = "UPDATE leagues SET league_name = ?, scoring_type_id = ?, is_active = 1 WHERE id = ?";
        $updateResult = executeQuery($updateSql, [$leagueData['name'], $scoringTypeId, $leagueId], 'sii');

        if ($updateResult['success']) {
            logMessage("Updated existing league: " . $leagueData['name'], 'INFO');
        }
        return true;
    } else {
        // Insert new league
        $insertSql = "INSERT INTO leagues (user_id, yahoo_league_id, league_name, scoring_type_id, roster_positions) VALUES (?, ?, ?, ?, ?)";

        $rosterPositions = json_encode(['QB' => 1, 'RB' => 2, 'WR' => 3, 'TE' => 1, 'K' => 1, 'DST' => 1, 'BN' => 6]);

        $insertResult = executeQuery($insertSql, [
            $userDbId,
            $leagueData['league_id'],
            $leagueData['name'],
            $scoringTypeId,
            $rosterPositions
        ], 'issss');

        if ($insertResult['success']) {
            logMessage("Stored new league: " . $leagueData['name'], 'INFO');
            return true;
        }
    }

    return false;
}

function addPlayerKeysToRecommendations($recommendations, $rosterData, $availablePlayers) {
    // Create lookup arrays for player names to player keys
    $availablePlayersLookup = [];
    foreach ($availablePlayers as $player) {
        $availablePlayersLookup[strtolower($player['name'])] = $player['player_key'];
    }

    $rosterPlayersLookup = [];
    foreach ($rosterData as $player) {
        $rosterPlayersLookup[strtolower($player['name'])] = $player['player_key'] ?? '';
    }

    // Add player_key to add_recommendations
    if (isset($recommendations['add_recommendations'])) {
        foreach ($recommendations['add_recommendations'] as &$rec) {
            $playerName = strtolower($rec['player_name'] ?? '');
            if (isset($availablePlayersLookup[$playerName])) {
                $rec['player_key'] = $availablePlayersLookup[$playerName];
            }
        }
    }

    // Add player_key to drop_candidates
    if (isset($recommendations['drop_candidates'])) {
        foreach ($recommendations['drop_candidates'] as &$rec) {
            $playerName = strtolower($rec['player_name'] ?? '');
            if (isset($rosterPlayersLookup[$playerName])) {
                $rec['player_key'] = $rosterPlayersLookup[$playerName];
            }
        }
    }

    return $recommendations;
}
?>