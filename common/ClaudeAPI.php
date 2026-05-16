<?php
include_once __DIR__ . '/aPRIV_API.php';
include_once __DIR__ . '/common.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/ai_functions.php';

class ClaudeAPI {
    private $model = 'claude-sonnet-4-20250514';

    public function analyzeTeam($userId, $rosterData, $availablePlayers, $leagueSettings, $focusArea = '') {
        enforceClaudeRateLimit();

        $prompt = $this->buildAnalysisPrompt($rosterData, $availablePlayers, $leagueSettings, $focusArea);

        // Use centralized AI system
        $aiResult = ai_makeRequest([
            'user_id' => $userId,
            'program_id' => 'FANTASY',
            'feature_code' => 'team_analysis',
            'prompt' => $prompt,
            'options' => [
                'max_tokens' => 4000,
                'temperature' => 0.7,
                'system' => 'You are an expert fantasy football analyst with access to web search for current NFL data and player information.'
            ]
        ]);

        if (!$aiResult['success']) {
            logMessage("AI request failed: " . ($aiResult['error'] ?? 'Unknown error'), 'ERROR');
            return false;
        }

        $analysisText = $aiResult['response'];
        return $this->parseAnalysisResponse($analysisText);
    }



    private function buildAnalysisPrompt($rosterData, $availablePlayers, $leagueSettings, $focusArea = '') {
        $currentWeek = date('W');
        $currentSeason = date('Y');
        $currentDate = date('F j, Y');

        // Count positions
        $positionCounts = [];
        foreach ($rosterData as $player) {
            $pos = $player['position'];
            $positionCounts[$pos] = ($positionCounts[$pos] ?? 0) + 1;
        }

        $prompt = "⚠️ MANDATORY: USE WEB SEARCH FOR CURRENT DATA ⚠️\n\n";
        $prompt .= "You MUST search the web for:\n";
        $prompt .= "- Current $currentSeason NFL season stats and performance\n";
        $prompt .= "- Recent injury reports and news (Week $currentWeek)\n";
        $prompt .= "- Latest target shares, snap counts, role changes\n";
        $prompt .= "- Upcoming matchups and schedules\n";
        $prompt .= "- Recent player news and trends\n\n";
        $prompt .= "DO NOT rely on training data - use fresh web searches!\n\n";
        $prompt .= "CRITICAL INSTRUCTIONS - READ FIRST:\n\n";

        // User's specific request goes FIRST
        if (!empty($focusArea)) {
            $prompt .= "🎯 USER SPECIFICALLY WANTS: \"$focusArea\"\n";
            $prompt .= "THIS IS YOUR #1 PRIORITY. Give them 3-5 options for what they asked for.\n";
            $prompt .= "DO NOT waste recommendations on positions they didn't ask about.\n\n";
        }

        $prompt .= "ROSTER DEPTH CHECK - DO NOT IGNORE:\n";
        foreach ($positionCounts as $pos => $count) {
            $prompt .= "- $pos: $count player(s)";
            if ($count >= 2) {
                $prompt .= " ← ALREADY HAVE ENOUGH - DO NOT RECOMMEND MORE $pos";
            }
            $prompt .= "\n";
        }
        $prompt .= "\n";

        $prompt .= "ABSOLUTE RULES:\n";
        $prompt .= "1. If I have 2+ QBs and they're healthy: DO NOT recommend QBs\n";
        $prompt .= "2. If I have 2+ Kickers: DO NOT recommend Kickers (suggest dropping one instead)\n";
        $prompt .= "3. ONLY recommend NFL starters who are actively playing, NOT backups\n";
        $prompt .= "4. When user asks for a position, give them 3-5 options at that position\n";
        $prompt .= "5. Use ONLY $currentSeason season data (ignore 2024, 2023, etc.)\n\n";

        $prompt .= "TODAY: $currentDate | SEASON: $currentSeason | WEEK: $currentWeek\n";
        $prompt .= "Scoring: {$leagueSettings['scoring_type']}\n\n";

        $prompt .= "MY ROSTER:\n";
        foreach ($rosterData as $player) {
            $prompt .= "- {$player['name']} ({$player['position']}) - {$player['team']}";
            if (!empty($player['player_key'])) {
                $prompt .= " [KEY: {$player['player_key']}]";
            }
            $prompt .= "\n";
        }
        $prompt .= "\n";

        $prompt .= "AVAILABLE PLAYERS (" . count($availablePlayers) . " total";

        // Note which positions are included
        $positions = array_unique(array_column($availablePlayers, "position"));
        if (count($positions) < 6) {
            $prompt .= " - FILTERED FOR: " . implode(", ", $positions);
        }
        $prompt .= "):\n";

        $topPlayers = array_slice($availablePlayers, 0, 100);  // Use ALL filtered players
        foreach ($topPlayers as $player) {
            $prompt .= "- {$player['name']} ({$player['position']}) - {$player['team']} - " .
                      ($player['ownership_percentage'] ?? 0) . "% owned [KEY: {$player['player_key']}]\n";
        }
        $prompt .= "\n";

        if (!empty($focusArea)) {
            $prompt .= "REMINDER: User wants \"$focusArea\" - THIS IS YOUR PRIORITY!\n\n";
        }

        $prompt .= "Provide recommendations in this JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "add_recommendations": [' . "\n";
        $prompt .= '    {"player_name": "Name", "player_key": "exact_key_from_above", "position": "WR", "team": "LAR", "reasoning": "why", "priority": "High"}' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "drop_candidates": [' . "\n";
        $prompt .= '    {"player_name": "Name", "player_key": "exact_key_from_roster", "position": "K", "reasoning": "why drop"}' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "roster_optimizations": ["suggestion 1", "suggestion 2"],' . "\n";
        $prompt .= '  "general_analysis": "brief overall assessment"' . "\n";
        $prompt .= "}\n\n";

        $prompt .= "FINAL CHECK BEFORE RESPONDING:\n";
        $prompt .= "- Did I give 3-5 options for the user's focus area?\n";
        $prompt .= "- Did I avoid recommending positions where they already have 2+ players?\n";
        $prompt .= "- Are all recommendations for NFL starters (not backups)?\n";
        $prompt .= "- Am I using $currentSeason data only?\n";

        return $prompt;
    }

    private function parseAnalysisResponse($responseText) {
        // Strip markdown code fences if present
        $responseText = preg_replace('/^```json\s*/', '', $responseText);
        $responseText = preg_replace('/\s*```$/', '', $responseText);
        $responseText = trim($responseText);

        // Try to extract JSON from the response
        $jsonStart = strpos($responseText, '{');
        $jsonEnd = strrpos($responseText, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            logMessage("No JSON found in Claude response", 'ERROR');
            return [
                'success' => false,
                'error' => 'Could not parse AI response'
            ];
        }

        $jsonText = substr($responseText, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("JSON decode error: " . json_last_error_msg(), 'ERROR');
            return [
                'success' => false,
                'error' => 'Invalid JSON in AI response'
            ];
        }

        // Validate required fields
        $requiredFields = ['add_recommendations', 'drop_candidates', 'roster_optimizations', 'general_analysis'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $data[$field] = [];
            }
        }

        logMessage("Successfully parsed Claude analysis response", 'INFO');

        return [
            'success' => true,
            'recommendations' => $data,
            'raw_response' => $responseText
        ];
    }

    public function getPlayerInsights($userId, $playerName, $position) {
        enforceClaudeRateLimit();

        $currentDate = date("F j, Y");
        $currentWeek = date("W");
        $currentSeason = date("Y");

        $prompt = "🌐 WEB SEARCH REQUIRED - USE CURRENT DATA 🌐\n\n";
        $prompt .= "TODAY: $currentDate\n";
        $prompt .= "SEASON: $currentSeason NFL Season\n";
        $prompt .= "WEEK: Week $currentWeek\n\n";

        $prompt .= "Research $playerName ($position) using web search and provide a fantasy football analysis:\n";
        $prompt .= "1. Search for his RECENT performance (last 2-3 weeks of $currentSeason)\n";
        $prompt .= "2. Check CURRENT injury status and reports\n";
        $prompt .= "3. Look up upcoming opponent matchups for Week $currentWeek\n";
        $prompt .= "4. Find latest news, target shares, snap counts, role changes\n\n";

        $prompt .= "Provide actionable insights based on CURRENT $currentSeason data (not historical). ";
        $prompt .= "Keep under 250 words. Format with clear sections using **bold headers**.";

        // Use centralized AI system
        $aiResult = ai_makeRequest([
            'user_id' => $userId,
            'program_id' => 'FANTASY',
            'feature_code' => 'player_insights',
            'prompt' => $prompt,
            'options' => [
                'max_tokens' => 1024,
                'temperature' => 0.5,
                'system' => 'You are an expert fantasy football analyst with access to web search for current NFL player data.'
            ]
        ]);

        if (!$aiResult['success']) {
            logMessage("Player insights AI request failed: " . ($aiResult['error'] ?? 'Unknown error'), 'ERROR');
            return false;
        }

        return $aiResult['response'];
    }

    public function analyzeMatchups($userId, $teams, $week) {
        enforceClaudeRateLimit();

        $prompt = "Analyze the fantasy football matchups for Week $week. ";
        $prompt .= "Teams playing: " . implode(', ', $teams) . ". ";
        $prompt .= "Provide insights on offensive/defensive matchups, weather concerns, ";
        $prompt .= "and which positions/players might benefit or struggle. ";
        $prompt .= "Format as a brief analysis suitable for fantasy decision making.";

        // Use centralized AI system
        $aiResult = ai_makeRequest([
            'user_id' => $userId,
            'program_id' => 'FANTASY',
            'feature_code' => 'matchup_analysis',
            'prompt' => $prompt,
            'options' => [
                'max_tokens' => 1500,
                'temperature' => 0.6,
                'system' => 'You are an expert fantasy football analyst with access to web search for current NFL matchup data.'
            ]
        ]);

        if (!$aiResult['success']) {
            logMessage("Matchup analysis AI request failed: " . ($aiResult['error'] ?? 'Unknown error'), 'ERROR');
            return false;
        }

        return $aiResult['response'];
    }

    public function optimizeRoster($userId, $rosterData, $availablePlayers, $leagueSettings) {
        enforceClaudeRateLimit();

        $prompt = $this->buildOptimizationPrompt($rosterData, $availablePlayers, $leagueSettings);

        // Use centralized AI system
        $aiResult = ai_makeRequest([
            'user_id' => $userId,
            'program_id' => 'FANTASY',
            'feature_code' => 'lineup_optimization',
            'prompt' => $prompt,
            'options' => [
                'max_tokens' => 3000,
                'temperature' => 0.3,
                'system' => 'You are an expert fantasy football lineup optimizer with access to web search for current NFL data.'
            ]
        ]);

        if (!$aiResult['success']) {
            logMessage("Lineup optimization AI request failed: " . ($aiResult['error'] ?? 'Unknown error'), 'ERROR');
            return false;
        }

        $optimizationText = $aiResult['response'];
        return $this->parseOptimizationResponse($optimizationText);
    }

    private function buildOptimizationPrompt($rosterData, $availablePlayers, $leagueSettings) {
        $currentWeek = date('W');

        $prompt = "You are an expert fantasy football lineup optimizer. Analyze the current roster and provide optimal start/sit recommendations for this week.\n\n";

        $prompt .= "LEAGUE SETTINGS:\n";
        $prompt .= "- Scoring: {$leagueSettings['scoring_type']}\n";
        $prompt .= "- Current Week: $currentWeek\n\n";

        $prompt .= "CURRENT ROSTER:\n";
        foreach ($rosterData as $player) {
            $status = $player['selected_position'] ?? 'Bench';
            $prompt .= "- {$player['name']} ({$player['position']}) - {$player['team']} - Currently: {$status} - Status: {$player['status']}\n";
        }
        $prompt .= "\n";

        if (!empty($availablePlayers)) {
            $prompt .= "TOP AVAILABLE PLAYERS (for emergency replacements):\n";
            $topPlayers = array_slice($availablePlayers, 0, 10);
            foreach ($topPlayers as $player) {
                $ownership = $player['ownership_percentage'] ?? 0;
                $prompt .= "- {$player['name']} ({$player['position']}) - {$player['team']} - {$ownership}% owned\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "OPTIMIZATION REQUIREMENTS:\n";
        $prompt .= "1. Analyze this week's matchups for each player\n";
        $prompt .= "2. Consider weather conditions for outdoor games\n";
        $prompt .= "3. Check for any injury concerns or questionable statuses\n";
        $prompt .= "4. Recommend optimal starting lineup for this week\n";
        $prompt .= "5. Identify any players who should be benched due to poor matchups\n";
        $prompt .= "6. Flag any injury risks that need attention\n\n";

        $prompt .= "Provide recommendations in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "lineup_changes": [' . "\n";
        $prompt .= '    {' . "\n";
        $prompt .= '      "action": "Start|Bench",' . "\n";
        $prompt .= '      "player_name": "Player Name",' . "\n";
        $prompt .= '      "position": "RB",' . "\n";
        $prompt .= '      "reasoning": "Why this change is recommended",' . "\n";
        $prompt .= '      "priority": "High|Medium|Low"' . "\n";
        $prompt .= '    }' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "start_sit_recommendations": [' . "\n";
        $prompt .= '    {' . "\n";
        $prompt .= '      "action": "start|sit",' . "\n";
        $prompt .= '      "player_name": "Player Name",' . "\n";
        $prompt .= '      "position": "WR",' . "\n";
        $prompt .= '      "reasoning": "Matchup analysis and reasoning",' . "\n";
        $prompt .= '      "matchup_grade": "A+|A|B+|B|C+|C|D"' . "\n";
        $prompt .= '    }' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "injury_concerns": [' . "\n";
        $prompt .= '    {' . "\n";
        $prompt .= '      "player_name": "Player Name",' . "\n";
        $prompt .= '      "status": "Injury status description",' . "\n";
        $prompt .= '      "recommendation": "What to do about this player"' . "\n";
        $prompt .= '    }' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "weather_concerns": [' . "\n";
        $prompt .= '    "Weather-related recommendations"' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "overall_strategy": "General strategy for this week"' . "\n";
        $prompt .= "}\n\n";

        $prompt .= "Focus on THIS WEEK's optimal lineup. Be specific about matchups and provide actionable recommendations.";

        return $prompt;
    }

    private function parseOptimizationResponse($responseText) {
        // Strip markdown code fences if present
        $responseText = preg_replace('/^```json\s*/', '', $responseText);
        $responseText = preg_replace('/\s*```$/', '', $responseText);
        $responseText = trim($responseText);

        // Try to extract JSON from the response
        $jsonStart = strpos($responseText, '{');
        $jsonEnd = strrpos($responseText, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            logMessage("No JSON found in Claude optimization response", 'ERROR');
            return [
                'success' => false,
                'error' => 'Could not parse optimization response'
            ];
        }

        $jsonString = substr($responseText, $jsonStart, $jsonEnd - $jsonStart + 1);
        $optimization = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("JSON decode error in optimization response: " . json_last_error_msg(), 'ERROR');
            return [
                'success' => false,
                'error' => 'Invalid JSON in optimization response'
            ];
        }

        return [
            'success' => true,
            'recommendations' => $optimization
        ];
    }

    // Alias method for lineup optimization (same functionality as optimizeRoster)
    public function optimizeLineup($userId, $rosterData, $availablePlayers, $leagueSettings) {
        return $this->optimizeRoster($userId, $rosterData, $availablePlayers, $leagueSettings);
    }
}
?>
