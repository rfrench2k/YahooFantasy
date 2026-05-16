<?php
// Don't include common.php or aPRIV_DB.php - parent file already includes common.php
// common.php already includes aPRIV_DB.php
include_once __DIR__ . '/aPRIV_API.php';

class YahooAPI {
    private $baseUrl = 'https://fantasysports.yahooapis.com/fantasy/v2';

    public function getUserLeagues($accessToken) {
        enforceYahooRateLimit();

        $url = $this->baseUrl . '/users;use_login=1/games;game_keys=nfl/leagues';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, getYahooHeaders($accessToken));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logApiUsage('yahoo', '/users/games/leagues', null, $httpCode);

        logMessage("Leagues API response: HTTP $httpCode - " . substr($response, 0, 500), 'DEBUG');

        if ($httpCode !== 200) {
            logMessage("Failed to get user leagues. HTTP: $httpCode, Response: $response", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);
        if (!$data) {
            logMessage("Failed to parse JSON response, trying XML...", 'DEBUG');
            // Try XML parsing like we do for user info
            $xml = simplexml_load_string($response);
            if ($xml) {
                logMessage("XML leagues response: " . print_r($xml, true), 'DEBUG');
                return $this->parseLeaguesFromXML($xml);
            }
        } else {
            logMessage("JSON leagues data structure: " . print_r($data, true), 'DEBUG');
            return $this->parseLeagues($data);
        }

        return [];
    }

    public function getLeagueInfo($leagueKey, $accessToken) {
        enforceYahooRateLimit();

        $url = $this->baseUrl . "/league/$leagueKey";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, getYahooHeaders($accessToken));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logApiUsage('yahoo', "/league/$leagueKey", $leagueKey, $httpCode);

        logMessage("League info API response: HTTP $httpCode - " . substr($response, 0, 500), 'DEBUG');

        if ($httpCode !== 200) {
            logMessage("Failed to get league info for $leagueKey. HTTP: $httpCode, Response: $response", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);
        if (!$data) {
            logMessage("Failed to parse JSON response for league info, trying XML...", 'DEBUG');
            $xml = simplexml_load_string($response);
            if ($xml) {
                logMessage("XML league info response: " . print_r($xml, true), 'DEBUG');
                return $this->convertXmlToArray($xml);
            }
        } else {
            logMessage("JSON league info data structure: " . print_r($data, true), 'DEBUG');
            return $data;
        }

        return false;
    }

    public function getTeamRoster($leagueKey, $teamKey, $accessToken) {
        enforceYahooRateLimit();

        $url = $this->baseUrl . "/team/$teamKey/roster";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, getYahooHeaders($accessToken));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logApiUsage('yahoo', "/team/$teamKey/roster", $teamKey, $httpCode);

        logMessage("Team roster API response: HTTP $httpCode - " . substr($response, 0, 500), 'DEBUG');

        if ($httpCode !== 200) {
            logMessage("Failed to get team roster for $teamKey. HTTP: $httpCode, Response: $response", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);
        if (!$data) {
            logMessage("Failed to parse JSON response for roster, trying XML...", 'DEBUG');
            $xml = simplexml_load_string($response);
            if ($xml) {
                logMessage("XML roster response: " . print_r($xml, true), 'DEBUG');
                return $this->parseRosterFromXML($xml);
            }
        } else {
            logMessage("JSON roster data structure: " . print_r($data, true), 'DEBUG');
            return $this->parseRoster($data);
        }

        return false;
    }

    public function getAvailablePlayers($leagueKey, $accessToken, $limit = 50) {
        enforceYahooRateLimit();

        // status=A gets ALL available players (both Free Agents and Waivers)
        // status=FA gets only Free Agents (excluding waivers)
        // status=W gets only Waivers
        $url = $this->baseUrl . "/league/$leagueKey/players;status=A;start=0;count=$limit/percent_owned";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, getYahooHeaders($accessToken));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logApiUsage('yahoo', "/league/$leagueKey/players", "$leagueKey:$limit", $httpCode);

        logMessage("Available players API response: HTTP $httpCode - " . substr($response, 0, 500), 'DEBUG');

        if ($httpCode !== 200) {
            logMessage("Failed to get available players for $leagueKey. HTTP: $httpCode, Response: $response", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);
        if (!$data) {
            logMessage("Failed to parse JSON response for players, trying XML...", 'DEBUG');
            $xml = simplexml_load_string($response);
            if ($xml) {
                logMessage("XML players response: " . print_r($xml, true), 'DEBUG');
                return $this->parsePlayersFromXML($xml);
            }
        } else {
            logMessage("JSON players data structure: " . print_r($data, true), 'DEBUG');
            return $this->parsePlayers($data);
        }

        return [];
    }

    public function getUserTeam($leagueKey, $accessToken) {
        enforceYahooRateLimit();

        $url = $this->baseUrl . "/users;use_login=1/games;game_keys=nfl/teams";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, getYahooHeaders($accessToken));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logApiUsage('yahoo', '/users/games/teams', $leagueKey, $httpCode);

        logMessage("User team API response: HTTP $httpCode - " . substr($response, 0, 500), 'DEBUG');

        if ($httpCode !== 200) {
            logMessage("Failed to get user team. HTTP: $httpCode, Response: $response", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);
        if (!$data) {
            logMessage("Failed to parse JSON response for user team, trying XML...", 'DEBUG');
            $xml = simplexml_load_string($response);
            if ($xml) {
                logMessage("XML user team response: " . print_r($xml, true), 'DEBUG');
                return $this->parseUserTeamFromXML($xml, $leagueKey);
            }
        } else {
            logMessage("JSON user team data structure: " . print_r($data, true), 'DEBUG');
            return $this->parseUserTeam($data, $leagueKey);
        }

        return false;
    }

    public function generateAddPlayerUrl($leagueKey, $playerId) {
        return "https://football.fantasysports.yahoo.com/f1/$leagueKey/addplayer?apid=$playerId";
    }

    public function generateDropPlayerUrl($leagueKey, $playerId) {
        return "https://football.fantasysports.yahoo.com/f1/$leagueKey/dropplayer?dpid=$playerId";
    }

    public function getTeamStats($leagueKey, $teamKey, $accessToken) {
        enforceYahooRateLimit();

        $url = $this->baseUrl . "/team/$teamKey/stats;type=season";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, getYahooHeaders($accessToken));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logApiUsage('yahoo', "/team/$teamKey/stats", $teamKey, $httpCode);

        logMessage("Team stats API response: HTTP $httpCode - " . substr($response, 0, 500), 'DEBUG');

        if ($httpCode !== 200) {
            logMessage("Failed to get team stats for $teamKey. HTTP: $httpCode, Response: $response", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);
        if (!$data) {
            logMessage("Failed to parse JSON response for team stats, trying XML...", 'DEBUG');
            $xml = simplexml_load_string($response);
            if ($xml) {
                logMessage("XML team stats response: " . print_r($xml, true), 'DEBUG');
                return $this->parseTeamStatsFromXML($xml);
            }
        } else {
            logMessage("JSON team stats data structure: " . print_r($data, true), 'DEBUG');
            return $this->parseTeamStats($data);
        }

        return false;
    }

    public function getLeagueStandings($leagueKey, $accessToken) {
        enforceYahooRateLimit();

        $url = $this->baseUrl . "/league/$leagueKey/standings";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, getYahooHeaders($accessToken));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        logApiUsage('yahoo', "/league/$leagueKey/standings", $leagueKey, $httpCode);

        logMessage("League standings API response: HTTP $httpCode - " . substr($response, 0, 500), 'DEBUG');

        if ($httpCode !== 200) {
            logMessage("Failed to get league standings for $leagueKey. HTTP: $httpCode, Response: $response", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);
        if (!$data) {
            logMessage("Failed to parse JSON response for league standings, trying XML...", 'DEBUG');
            $xml = simplexml_load_string($response);
            if ($xml) {
                logMessage("XML league standings response: " . print_r($xml, true), 'DEBUG');
                return $this->parseLeagueStandingsFromXML($xml);
            }
        } else {
            logMessage("JSON league standings data structure: " . print_r($data, true), 'DEBUG');
            return $this->parseLeagueStandings($data);
        }

        return false;
    }


    private function parseLeagues($data) {
        $leagues = [];

        if (!isset($data['fantasy_content']['users']['0']['user']['games'])) {
            logMessage("No leagues found in response", 'WARNING');
            return $leagues;
        }

        $games = $data['fantasy_content']['users']['0']['user']['games'];

        foreach ($games as $gameKey => $game) {
            if (!is_numeric($gameKey)) continue;

            if (isset($game['game']['leagues'])) {
                foreach ($game['game']['leagues'] as $leagueKey => $league) {
                    if (!is_numeric($leagueKey)) continue;

                    $leagueData = $league['league'];
                    $leagues[] = [
                        'league_key' => $leagueData['league_key'],
                        'league_id' => $leagueData['league_id'],
                        'name' => $leagueData['name'],
                        'url' => $leagueData['url'],
                        'scoring_type' => $leagueData['scoring_type'] ?? 'standard',
                        'num_teams' => $leagueData['num_teams'],
                        'current_week' => $leagueData['current_week'] ?? 1
                    ];
                }
            }
        }

        logMessage("Parsed " . count($leagues) . " leagues", 'INFO');
        return $leagues;
    }

    private function parseLeaguesFromXML($xml) {
        $leagues = [];

        if (!isset($xml->users->user->games->game->leagues->league)) {
            logMessage("No leagues found in XML response", 'WARNING');
            return $leagues;
        }

        $leagueData = $xml->users->user->games->game->leagues->league;

        // Handle single league (not an array)
        if (!is_array($leagueData)) {
            $league = [
                'league_key' => (string)$leagueData->league_key,
                'league_id' => (string)$leagueData->league_id,
                'name' => (string)$leagueData->name,
                'url' => (string)$leagueData->url,
                'scoring_type' => (string)($leagueData->scoring_type ?? 'standard'),
                'num_teams' => (int)$leagueData->num_teams,
                'current_week' => (int)($leagueData->current_week ?? 1)
            ];
            $leagues[] = $league;
        } else {
            // Handle multiple leagues
            foreach ($leagueData as $league) {
                $leagues[] = [
                    'league_key' => (string)$league->league_key,
                    'league_id' => (string)$league->league_id,
                    'name' => (string)$league->name,
                    'url' => (string)$league->url,
                    'scoring_type' => (string)($league->scoring_type ?? 'standard'),
                    'num_teams' => (int)$league->num_teams,
                    'current_week' => (int)($league->current_week ?? 1)
                ];
            }
        }

        logMessage("Parsed " . count($leagues) . " leagues from XML", 'INFO');
        return $leagues;
    }

    private function parseRoster($data) {
        $roster = [];

        if (!isset($data['fantasy_content']['team']['roster']['players'])) {
            logMessage("No roster found in response", 'WARNING');
            return $roster;
        }

        $players = $data['fantasy_content']['team']['roster']['players'];

        foreach ($players as $playerKey => $player) {
            if (!is_numeric($playerKey)) continue;

            $playerData = $player['player'];
            $roster[] = [
                'player_key' => $playerData['player_key'],
                'player_id' => $playerData['player_id'],
                'name' => $playerData['name']['full'],
                'position' => $playerData['primary_position'],
                'team' => $playerData['editorial_team_abbr'] ?? '',
                'status' => $playerData['status'] ?? '',
                'selected_position' => $playerData['selected_position']['position'] ?? ''
            ];
        }

        logMessage("Parsed roster with " . count($roster) . " players", 'INFO');
        return $roster;
    }

    private function parseRosterFromXML($xml) {
        $roster = [];

        if (!isset($xml->team->roster->players->player)) {
            logMessage("No roster found in XML response", 'WARNING');
            return $roster;
        }

        $players = $xml->team->roster->players->player;

        // Log debug info about the players structure
        logMessage("Players object type: " . get_class($players), 'DEBUG');
        logMessage("Players count: " . count($players), 'DEBUG');

        // Iterate through all players
        for ($i = 0; $i < count($players); $i++) {
            $player = $players[$i];
            logMessage("Processing player $i: " . (string)$player->name->full, 'DEBUG');

            $roster[] = [
                'player_key' => (string)$player->player_key,
                'player_id' => (string)$player->player_id,
                'name' => (string)$player->name->full,
                'position' => (string)$player->primary_position,
                'team' => (string)($player->editorial_team_abbr ?? ''),
                'status' => (string)($player->status ?? ''),
                'selected_position' => (string)($player->selected_position->position ?? '')
            ];
        }

        logMessage("Parsed roster from XML with " . count($roster) . " players", 'INFO');
        return $roster;
    }

    private function parsePlayers($data) {
        $players = [];

        if (!isset($data['fantasy_content']['league']['players'])) {
            logMessage("No players found in response", 'WARNING');
            return $players;
        }

        $playersData = $data['fantasy_content']['league']['players'];

        foreach ($playersData as $playerKey => $player) {
            if (!is_numeric($playerKey)) continue;

            $playerData = $player['player'];

            $ownershipPct = 0;
            if (isset($playerData['percent_owned']['value'])) {
                $ownershipPct = (float)$playerData['percent_owned']['value'];
            }

            $players[] = [
                'player_key' => $playerData['player_key'],
                'player_id' => $playerData['player_id'],
                'name' => $playerData['name']['full'],
                'position' => $playerData['primary_position'],
                'team' => $playerData['editorial_team_abbr'] ?? '',
                'status' => $playerData['status'] ?? '',
                'ownership_percentage' => $ownershipPct
            ];
        }

        logMessage("Parsed " . count($players) . " available players", 'INFO');
        return $players;
    }

    private function parsePlayersFromXML($xml) {
        $players = [];

        if (!isset($xml->league->players->player)) {
            logMessage("No players found in XML response", 'WARNING');
            return $players;
        }

        $playersData = $xml->league->players->player;

        // Log debug info about the players structure
        logMessage("Available players object type: " . get_class($playersData), 'DEBUG');
        logMessage("Available players count: " . count($playersData), 'DEBUG');

        // Iterate through all players
        for ($i = 0; $i < count($playersData); $i++) {
            $player = $playersData[$i];

            // Debug: log first player's full structure
            if ($i === 0) {
                logMessage("First player full XML structure: " . print_r($player, true), 'DEBUG');
            }

            $ownershipPct = 0;
            if (isset($player->percent_owned->value)) {
                $ownershipPct = (float)$player->percent_owned->value;
            }

            // Check for eligibility status (FA, W, or other)
            $eligibility = 'FA'; // Default to Free Agent
            if (isset($player->status_full)) {
                $eligibility = (string)$player->status_full;
            } elseif (isset($player->eligibility)) {
                $eligibility = (string)$player->eligibility;
            }

            $players[] = [
                'player_key' => (string)$player->player_key,
                'player_id' => (string)$player->player_id,
                'name' => (string)$player->name->full,
                'position' => (string)$player->primary_position,
                'team' => (string)($player->editorial_team_abbr ?? ''),
                'status' => (string)($player->status ?? ''),
                'ownership_percentage' => $ownershipPct,
                'eligibility' => $eligibility
            ];
        }

        logMessage("Parsed " . count($players) . " available players from XML", 'INFO');
        return $players;
    }

    private function parseUserTeam($data, $targetLeagueKey) {
        if (!isset($data['fantasy_content']['users']['0']['user']['games'])) {
            return false;
        }

        $games = $data['fantasy_content']['users']['0']['user']['games'];

        foreach ($games as $gameKey => $game) {
            if (!is_numeric($gameKey)) continue;

            if (isset($game['game']['teams'])) {
                foreach ($game['game']['teams'] as $teamKey => $team) {
                    if (!is_numeric($teamKey)) continue;

                    $teamData = $team['team'];
                    $leagueKey = $teamData['team_key'];

                    // Extract league key from team key (format: nfl.l.123456.t.1)
                    if (strpos($leagueKey, $targetLeagueKey) !== false) {
                        return [
                            'team_key' => $teamData['team_key'],
                            'team_id' => $teamData['team_id'],
                            'name' => $teamData['name'],
                            'league_key' => $targetLeagueKey
                        ];
                    }
                }
            }
        }

        return false;
    }

    private function parseUserTeamFromXML($xml, $targetLeagueKey) {
        if (!isset($xml->users->user->games->game->teams->team)) {
            logMessage("No teams found in XML response", 'WARNING');
            return false;
        }

        $teams = $xml->users->user->games->game->teams->team;

        // Handle single team (not an array)
        if (!is_array($teams)) {
            $teams = [$teams];
        }

        foreach ($teams as $team) {
            $teamKey = (string)$team->team_key;

            // Extract league key from team key (format: nfl.l.123456.t.1)
            if (strpos($teamKey, $targetLeagueKey) !== false) {
                logMessage("Found matching team: $teamKey for league: $targetLeagueKey", 'INFO');
                return [
                    'team_key' => $teamKey,
                    'team_id' => (string)$team->team_id,
                    'name' => (string)$team->name,
                    'league_key' => $targetLeagueKey
                ];
            }
        }

        logMessage("No team found for league: $targetLeagueKey", 'WARNING');
        return false;
    }

    public function refreshToken($refreshToken) {
        $postData = [
            'client_id' => YAHOO_CLIENT_ID,
            'client_secret' => YAHOO_CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
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

        logApiUsage('yahoo', '/oauth2/get_token', 'refresh', $httpCode);

        if ($httpCode !== 200) {
            logMessage("Token refresh failed with HTTP $httpCode: $response", 'ERROR');
            return false;
        }

        return json_decode($response, true);
    }


    private function parseTeamStats($data) {
        $stats = [
            'wins' => 0,
            'losses' => 0,
            'points_for' => '0.0',
            'points_against' => '0.0',
            'rank' => 'N/A',
            'waiver_priority' => 'N/A'
        ];

        // Log basic team stats info
        logMessage("Processing team stats data", 'DEBUG');

        // Check different possible paths for team stats
        $teamStatsData = null;
        if (isset($data['fantasy_content']['team']['team_stats']['stats']['stat'])) {
            $teamStatsData = $data['fantasy_content']['team']['team_stats']['stats']['stat'];
        } elseif (isset($data['fantasy_content']['team']['team_stats']['stats'])) {
            $teamStatsData = $data['fantasy_content']['team']['team_stats']['stats'];
        }

        if ($teamStatsData) {
            // Handle both single stat and array of stats
            if (isset($teamStatsData['stat_id'])) {
                // Single stat
                $teamStatsData = [$teamStatsData];
            }

            foreach ($teamStatsData as $stat) {
                $statData = isset($stat['stat']) ? $stat['stat'] : $stat;
                $statId = $statData['stat_id'] ?? '';
                $value = $statData['value'] ?? '';


                switch ($statId) {
                    case '0': // Wins
                    case 0:
                        $stats['wins'] = (int)$value;
                        break;
                    case '1': // Losses
                    case 1:
                        $stats['losses'] = (int)$value;
                        break;
                    case '60': // Points For
                    case 60:
                        $stats['points_for'] = number_format((float)$value, 1);
                        break;
                    case '61': // Points Against
                    case 61:
                        $stats['points_against'] = number_format((float)$value, 1);
                        break;
                }
            }
        }

        // Try to get rank from standings
        if (isset($data['fantasy_content']['team']['team_standings']['rank'])) {
            $stats['rank'] = $data['fantasy_content']['team']['team_standings']['rank'];
        }

        // Try to get waiver priority
        if (isset($data['fantasy_content']['team']['waiver_priority'])) {
            $stats['waiver_priority'] = $data['fantasy_content']['team']['waiver_priority'];
        }

        return $stats;
    }

    private function parseTeamStatsFromXML($xml) {
        $stats = [
            'wins' => 0,
            'losses' => 0,
            'points_for' => '0.0',
            'points_against' => '0.0',
            'rank' => 'N/A',
            'waiver_priority' => 'N/A'
        ];


        // Get points for from team_points total
        if (isset($xml->team->team_points->total)) {
            $stats['points_for'] = number_format((float)$xml->team->team_points->total, 1);
        }

        // Get waiver priority
        if (isset($xml->team->waiver_priority)) {
            $stats['waiver_priority'] = (string)$xml->team->waiver_priority;
        }

        // Get previous season rank (if current season rank not available)
        if (isset($xml->team->previous_season_team_rank)) {
            $stats['rank'] = (string)$xml->team->previous_season_team_rank . " (prev)";
        }

        // Check for actual team stats (wins/losses might be in a different structure)
        if (isset($xml->team->team_stats->stats->stat)) {
            foreach ($xml->team->team_stats->stats->stat as $stat) {
                $statId = (string)$stat->stat_id;
                $value = (string)$stat->value;


                switch ($statId) {
                    case '0': // Wins
                        $stats['wins'] = (int)$value;
                        break;
                    case '1': // Losses
                        $stats['losses'] = (int)$value;
                        break;
                    case '60': // Points For
                        $stats['points_for'] = number_format((float)$value, 1);
                        break;
                    case '61': // Points Against
                        $stats['points_against'] = number_format((float)$value, 1);
                        break;
                }
            }
        }

        return $stats;
    }

    private function parseLeagueStandings($data) {
        $standings = [];

        if (isset($data['fantasy_content']['league']['standings']['teams'])) {
            $teams = $data['fantasy_content']['league']['standings']['teams'];

            foreach ($teams as $teamKey => $team) {
                if (!is_numeric($teamKey)) continue;

                $teamData = $team['team'];
                $standings[] = [
                    'team_key' => $teamData['team_key'],
                    'team_name' => $teamData['name'],
                    'rank' => $teamData['team_standings']['rank'] ?? 'N/A',
                    'wins' => $teamData['team_standings']['outcome_totals']['wins'] ?? 0,
                    'losses' => $teamData['team_standings']['outcome_totals']['losses'] ?? 0,
                    'ties' => $teamData['team_standings']['outcome_totals']['ties'] ?? 0,
                    'points_for' => $teamData['team_standings']['points_for'] ?? '0.0',
                    'points_against' => $teamData['team_standings']['points_against'] ?? '0.0'
                ];
            }
        }

        return $standings;
    }

    private function parseLeagueStandingsFromXML($xml) {
        $standings = [];

        if (isset($xml->league->standings->teams->team)) {
            foreach ($xml->league->standings->teams->team as $team) {
                $standings[] = [
                    'team_key' => (string)$team->team_key,
                    'team_name' => (string)$team->name,
                    'rank' => (string)($team->team_standings->rank ?? 'N/A'),
                    'wins' => (int)($team->team_standings->outcome_totals->wins ?? 0),
                    'losses' => (int)($team->team_standings->outcome_totals->losses ?? 0),
                    'ties' => (int)($team->team_standings->outcome_totals->ties ?? 0),
                    'points_for' => (string)($team->team_standings->points_for ?? '0.0'),
                    'points_against' => (string)($team->team_standings->points_against ?? '0.0')
                ];
            }
        }

        return $standings;
    }

    private function convertXmlToArray($xml) {
        $array = json_decode(json_encode($xml), true);

        // Wrap the league data in the expected fantasy_content structure
        if (isset($array['league'])) {
            return [
                'fantasy_content' => [
                    'league' => $array['league']
                ]
            ];
        }

        return $array;
    }
}
?>