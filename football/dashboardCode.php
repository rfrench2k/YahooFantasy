<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/fantasy/common/common.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/fantasy/common/YahooAPI.php';

header('Content-Type: application/json');

// Get authenticated user from centralized AUTH system
$authUserId = getCurrentUserId();
if (!$authUserId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated with AUTH system']);
    exit;
}

// Get Yahoo user ID from database for this authenticated user
$sql = "SELECT yahoo_user_id FROM users WHERE user_id = ?";
$result = executeQuery($sql, [$authUserId], 's');

if (!$result['success'] || empty($result['data']) || empty($result['data'][0]['yahoo_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Yahoo account not linked']);
    exit;
}

$userId = $result['data'][0]['yahoo_user_id'];

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$action = $data['action'];

logMessage("Dashboard action requested: $action by user: $userId", 'INFO');

try {
    switch ($action) {
        case 'getUserLeagues':
            handleGetUserLeagues($userId);
            break;

        case 'getTeamRoster':
            if (!isset($data['league_key'])) {
                throw new Exception('League key required');
            }
            handleGetTeamRoster($userId, $data['league_key']);
            break;

        case 'getAvailablePlayers':
            if (!isset($data['league_key'])) {
                throw new Exception('League key required');
            }
            handleGetAvailablePlayers($userId, $data['league_key']);
            break;

        case 'getTeamStats':
            if (!isset($data['league_key'])) {
                throw new Exception('League key required');
            }
            handleGetTeamStats($userId, $data['league_key']);
            break;

        case 'optimizeRoster':
            if (!isset($data['league_key'])) {
                throw new Exception('League key required');
            }
            handleOptimizeRoster($userId, $data['league_key']);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    logMessage("Dashboard error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleGetUserLeagues($userId) {

    // Get user's access token
    $accessToken = getUserAccessToken($userId);
    if (!$accessToken) {
        throw new Exception('No valid access token found. Please re-authenticate.');
    }

    $yahooAPI = new YahooAPI();
    $leagues = $yahooAPI->getUserLeagues($accessToken);

    if ($leagues === false) {
        throw new Exception('Failed to fetch leagues from Yahoo Fantasy');
    }

    // Store leagues in database for future reference
    foreach ($leagues as $league) {
        storeLeague($userId, $league);
    }

    logMessage("Retrieved " . count($leagues) . " leagues for user: $userId", 'INFO');

    echo json_encode([
        'success' => true,
        'leagues' => $leagues
    ]);
}

function handleGetTeamRoster($userId, $leagueKey) {
    $accessToken = getUserAccessToken($userId);
    if (!$accessToken) {
        throw new Exception('No valid access token found. Please re-authenticate.');
    }

    $yahooAPI = new YahooAPI();

    // Get user's team for this league
    $team = $yahooAPI->getUserTeam($leagueKey, $accessToken);
    if (!$team) {
        throw new Exception('Could not find your team in this league');
    }

    // Get roster
    $roster = $yahooAPI->getTeamRoster($leagueKey, $team['team_key'], $accessToken);

    if ($roster === false) {
        throw new Exception('Failed to fetch roster from Yahoo Fantasy');
    }

    logMessage("Retrieved roster with " . count($roster) . " players for league: $leagueKey", 'INFO');

    echo json_encode([
        'success' => true,
        'roster' => $roster,
        'team' => $team
    ]);
}

function handleGetAvailablePlayers($userId, $leagueKey) {
    $accessToken = getUserAccessToken($userId);
    if (!$accessToken) {
        throw new Exception('No valid access token found. Please re-authenticate.');
    }

    $yahooAPI = new YahooAPI();
    $players = $yahooAPI->getAvailablePlayers($leagueKey, $accessToken, 50);

    if ($players === false) {
        throw new Exception('Failed to fetch available players from Yahoo Fantasy');
    }

    // Sort by ownership percentage (descending)
    usort($players, function($a, $b) {
        $aOwnership = (float)($a['ownership_percentage'] ?? 0);
        $bOwnership = (float)($b['ownership_percentage'] ?? 0);
        return $bOwnership <=> $aOwnership;
    });

    logMessage("Retrieved " . count($players) . " available players for league: $leagueKey", 'INFO');

    echo json_encode([
        'success' => true,
        'players' => $players
    ]);
}

function handleGetTeamStats($userId, $leagueKey) {
    $accessToken = getUserAccessToken($userId);
    if (!$accessToken) {
        throw new Exception('No valid access token found. Please re-authenticate.');
    }

    $yahooAPI = new YahooAPI();

    // Get user's team for this league
    $team = $yahooAPI->getUserTeam($leagueKey, $accessToken);
    if (!$team) {
        throw new Exception('Could not find your team in this league');
    }

    // Get team stats
    $stats = $yahooAPI->getTeamStats($leagueKey, $team['team_key'], $accessToken);

    if ($stats === false) {
        // Fallback to basic stats if detailed stats fail
        $stats = [
            'wins' => 0,
            'losses' => 0,
            'points_for' => '0.0',
            'points_against' => '0.0',
            'rank' => 'N/A',
            'waiver_priority' => 'N/A'
        ];
        logMessage("Using fallback team stats for league: $leagueKey", 'WARNING');
    } else {
        logMessage("Retrieved team stats for league: $leagueKey", 'INFO');

        // Get league standings to fill in missing wins/losses/rank
        $standings = $yahooAPI->getLeagueStandings($leagueKey, $accessToken);
        if ($standings !== false) {
            // Find this team in the standings
            foreach ($standings as $standing) {
                if ($standing['team_key'] === $team['team_key']) {
                    $stats['wins'] = $standing['wins'];
                    $stats['losses'] = $standing['losses'];
                    $stats['rank'] = $standing['rank'];
                    // Use standings points_for if it's better than what we have
                    if ($standing['points_for'] !== '0.0' && $stats['points_for'] === '0.0') {
                        $stats['points_for'] = number_format((float)$standing['points_for'], 1);
                    }
                    if ($standing['points_against'] !== '0.0' && $stats['points_against'] === '0.0') {
                        $stats['points_against'] = number_format((float)$standing['points_against'], 1);
                    }
                    logMessage("Merged standings data: wins={$stats['wins']}, losses={$stats['losses']}, rank={$stats['rank']}", 'INFO');
                    break;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

function getUserAccessToken($userId) {
    $sql = "SELECT access_token, refresh_token, token_expires FROM users WHERE yahoo_user_id = ? OR id = ?";
    $result = executeQuery($sql, [$userId, $userId], 'ss');

    if (!$result['success'] || empty($result['data'])) {
        logMessage("No user found for ID: $userId", 'WARNING');
        return false;
    }

    $user = $result['data'][0];
    $accessToken = $user['access_token'];
    $refreshToken = $user['refresh_token'];
    $tokenExpires = $user['token_expires'];

    // Check if token is expired
    if ($tokenExpires && strtotime($tokenExpires) <= time()) {
        logMessage("Access token expired for user: $userId, attempting refresh", 'INFO');

        if ($refreshToken) {
            $yahooAPI = new YahooAPI();
            $newTokenData = $yahooAPI->refreshToken($refreshToken);

            if ($newTokenData) {
                $newAccessToken = $newTokenData['access_token'];
                $newRefreshToken = $newTokenData['refresh_token'] ?? $refreshToken;
                $newExpires = date('Y-m-d H:i:s', time() + $newTokenData['expires_in']);

                // Update token in database
                $updateSql = "UPDATE users SET access_token = ?, refresh_token = ?, token_expires = ?, updated_at = NOW() WHERE yahoo_user_id = ? OR id = ?";
                $updateResult = executeQuery($updateSql, [$newAccessToken, $newRefreshToken, $newExpires, $userId, $userId], 'sssss');

                if ($updateResult['success']) {
                    logMessage("Token refreshed successfully for user: $userId", 'INFO');
                    return $newAccessToken;
                }
            }
        }

        logMessage("Failed to refresh token for user: $userId", 'ERROR');
        return false;
    }

    return $accessToken;
}

function storeLeague($userId, $leagueData) {
    // Get scoring type ID
    $scoringTypeSql = "SELECT id FROM scoring_types WHERE name = ?";
    $scoringResult = executeQuery($scoringTypeSql, [$leagueData['scoring_type']], 's');

    $scoringTypeId = 1; // Default to standard
    if ($scoringResult['success'] && !empty($scoringResult['data'])) {
        $scoringTypeId = $scoringResult['data'][0]['id'];
    }

    // Check if league already exists
    $checkSql = "SELECT id FROM leagues WHERE yahoo_league_id = ? AND user_id = (SELECT id FROM users WHERE yahoo_user_id = ? OR id = ?)";
    $checkResult = executeQuery($checkSql, [$leagueData['league_id'], $userId, $userId], 'sss');

    if ($checkResult['success'] && !empty($checkResult['data'])) {
        // Update existing league
        $leagueId = $checkResult['data'][0]['id'];
        $updateSql = "UPDATE leagues SET league_name = ?, scoring_type_id = ?, is_active = 1 WHERE id = ?";
        $updateResult = executeQuery($updateSql, [$leagueData['name'], $scoringTypeId, $leagueId], 'sii');

        if ($updateResult['success']) {
            logMessage("Updated existing league: " . $leagueData['name'], 'INFO');
        }
    } else {
        // Insert new league
        $insertSql = "INSERT INTO leagues (user_id, yahoo_league_id, league_name, scoring_type_id, roster_positions)
                     SELECT id, ?, ?, ?, ? FROM users WHERE yahoo_user_id = ? OR id = ?";

        $rosterPositions = json_encode(['QB' => 1, 'RB' => 2, 'WR' => 3, 'TE' => 1, 'K' => 1, 'DST' => 1, 'BN' => 6]);

        $insertResult = executeQuery($insertSql, [
            $leagueData['league_id'],
            $leagueData['name'],
            $scoringTypeId,
            $rosterPositions,
            $userId,
            $userId
        ], 'ssisss');

        if ($insertResult['success']) {
            logMessage("Stored new league: " . $leagueData['name'], 'INFO');
        }
    }
}

function handleOptimizeRoster($userId, $leagueKey) {
    $accessToken = getUserAccessToken($userId);
    if (!$accessToken) {
        throw new Exception('No valid access token found. Please re-authenticate.');
    }

    $yahooAPI = new YahooAPI();

    // Get user's team for this league
    $team = $yahooAPI->getUserTeam($leagueKey, $accessToken);
    if (!$team) {
        throw new Exception('Could not find your team in this league');
    }

    // Get current roster
    $roster = $yahooAPI->getTeamRoster($leagueKey, $team['team_key'], $accessToken);
    if ($roster === false) {
        throw new Exception('Failed to fetch roster from Yahoo Fantasy');
    }

    // Get available players for context
    $availablePlayers = $yahooAPI->getAvailablePlayers($leagueKey, $accessToken, 20);
    if ($availablePlayers === false) {
        $availablePlayers = [];
    }

    // Get league info for settings
    $leagueInfo = $yahooAPI->getLeagueInfo($leagueKey, $accessToken);
    $leagueSettings = [
        'league_name' => $leagueInfo['fantasy_content']['league']['name'] ?? 'Unknown League',
        'scoring_type' => $leagueInfo['fantasy_content']['league']['scoring_type'] ?? 'standard'
    ];

    // Run AI optimization analysis
    include_once '../common/ClaudeAPI.php';
    $claudeAPI = new ClaudeAPI();
    $optimization = $claudeAPI->optimizeLineup($userId, $roster, $availablePlayers, $leagueSettings);

    if (!$optimization || !$optimization['success']) {
        throw new Exception('Roster optimization failed: ' . ($optimization['error'] ?? 'Unknown error'));
    }

    logMessage("Roster optimization completed for league: $leagueKey", 'INFO');

    echo json_encode([
        'success' => true,
        'optimization' => $optimization['recommendations']
    ]);
}
?>