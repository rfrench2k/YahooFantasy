// Fantasy Football Dashboard JavaScript
class FantasyDashboard {
    constructor() {
        this.selectedLeague = null;
        this.userTeam = null;
        this.analysisProgress = null;
        this.init();
    }

    init() {
        console.log('Fantasy Dashboard initialized');

        // Bind event listeners
        this.bindEvents();

        // Load initial data
        this.loadUserLeagues();
    }

    bindEvents() {
        // Teams refresh
        document.getElementById('refreshTeamsBtn')?.addEventListener('click', () => {
            this.loadUserLeagues();
        });

        // Team selector dropdown
        document.getElementById('teamSelector')?.addEventListener('change', (e) => {
            const selectedValue = e.target.value;
            if (selectedValue) {
                const leagueData = JSON.parse(selectedValue);
                this.selectLeague(leagueData);
                // Save selection to localStorage
                localStorage.setItem('selectedFantasyTeam', selectedValue);
            }
        });

        // Roster refresh
        document.getElementById('refreshRosterBtn')?.addEventListener('click', () => {
            if (this.selectedLeague) {
                this.loadTeamRoster(this.selectedLeague.league_key);
            }
        });

        // Available players refresh
        document.getElementById('refreshPlayersBtn')?.addEventListener('click', () => {
            if (this.selectedLeague) {
                this.loadAvailablePlayers(this.selectedLeague.league_key);
            }
        });

        // Position filter
        document.getElementById('positionFilter')?.addEventListener('change', (e) => {
            this.filterPlayersByPosition(e.target.value);
        });

        // Analysis buttons
        document.getElementById('startAnalysisBtn')?.addEventListener('click', () => {
            this.startFullAnalysis();
        });

        document.getElementById('optimizeRosterBtn')?.addEventListener('click', () => {
            this.optimizeRoster();
        });
    }

    async loadUserLeagues() {
        try {
            this.showLoading('teamsContainer', 'Loading your fantasy teams...');

            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'getUserLeagues'
                })
            });

            if (response.success) {
                this.displayLeaguesInDropdown(response.leagues);
            } else {
                // Check if it's an auth error
                if (response.error && (response.error.includes('access token') || response.error.includes('authenticate'))) {
                    const reconnectBtn = document.getElementById('reconnectYahooBtn');
                    if (reconnectBtn) reconnectBtn.style.display = 'inline-block';
                }
                this.showError('teamsContainer', response.error || 'Failed to load teams');
            }
        } catch (error) {
            console.error('Error loading leagues:', error);
            this.showError('teamsContainer', 'Failed to load teams. Please try again.');
        }
    }

    async displayLeaguesInDropdown(leagues) {
        const container = document.getElementById('teamsContainer');
        const dropdown = document.getElementById('teamSelector');

        if (!leagues || leagues.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p>No fantasy leagues found. Make sure you're logged into Yahoo Fantasy Football.</p>
                    <a href="https://football.fantasysports.yahoo.com/" target="_blank" class="btn btn-outline-primary">
                        Go to Yahoo Fantasy Football
                    </a>
                </div>
            `;
            return;
        }

        // Get team names for each league
        const leaguesWithTeams = [];

        for (const league of leagues) {
            try {
                const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'getTeamRoster',
                        league_key: league.league_key
                    })
                });

                if (response.success) {
                    leaguesWithTeams.push({
                        ...league,
                        teamName: response.team?.name || 'My Team'
                    });
                } else {
                    leaguesWithTeams.push({
                        ...league,
                        teamName: 'My Team'
                    });
                }
            } catch (error) {
                leaguesWithTeams.push({
                    ...league,
                    teamName: 'My Team'
                });
            }
        }

        // Populate dropdown
        dropdown.innerHTML = '<option value="">Select Team...</option>';
        leaguesWithTeams.forEach(league => {
            const option = document.createElement('option');
            option.value = JSON.stringify(league);
            option.textContent = `${league.name} - ${league.teamName}`;
            dropdown.appendChild(option);
        });

        dropdown.style.display = 'block';
        container.innerHTML = '<p class="text-muted mb-0">Select a team from the dropdown above to view stats and manage your roster.</p>';

        // Check for saved selection
        const savedSelection = localStorage.getItem('selectedFantasyTeam');
        if (savedSelection) {
            dropdown.value = savedSelection;
            const leagueData = JSON.parse(savedSelection);
            // Auto-select the saved team
            setTimeout(() => {
                this.selectLeague(leagueData);
            }, 100);
        }
    }

    async selectLeague(league) {
        console.log('League selected:', league);
        this.selectedLeague = league;

        // Show team stats container and tabbed content sections
        document.getElementById('teamStatsContainer').style.display = 'block';
        document.getElementById('tabbedContentSection').style.display = 'block';

        // Load league data
        await Promise.all([
            this.loadTeamRoster(league.league_key),
            this.loadAvailablePlayers(league.league_key),
            this.loadTeamStats(league.league_key)
        ]);
    }

    async loadTeamRoster(leagueKey) {
        try {
            this.showLoading('rosterContainer', 'Loading your roster...');

            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'getTeamRoster',
                    league_key: leagueKey
                })
            });

            if (response.success) {
                this.displayRoster(response.roster, response.team);
                this.userTeam = response.team;
            } else {
                this.showError('rosterContainer', response.error || 'Failed to load roster');
            }
        } catch (error) {
            console.error('Error loading roster:', error);
            this.showError('rosterContainer', 'Failed to load roster. Please try again.');
        }
    }

    displayRoster(roster, team) {
        if (!roster || roster.length === 0) {
            document.getElementById('rosterContainer').innerHTML = `
                <div class="text-center text-muted">
                    <p>No roster data available</p>
                </div>
            `;
            return;
        }

        // Group players by position
        const positions = ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'];
        const playersByPosition = {};

        positions.forEach(pos => {
            playersByPosition[pos] = roster.filter(player => {
                // Handle both DEF and DST for defense
                if (pos === 'DEF') {
                    return player.position === 'DEF' || player.position === 'DST';
                }
                return player.position === pos;
            });
        });

        let html = `
            <div class="mb-3">
                <h6><i class="fas fa-users"></i> ${team ? team.name : 'My Team'}</h6>
            </div>
            <div class="roster-grid">
        `;

        positions.forEach(position => {
            const players = playersByPosition[position] || [];

            html += `
                <div class="position-group mb-3">
                    <h6 class="position-header">${formatPosition(position)}</h6>
            `;

            if (players.length === 0) {
                html += `<p class="text-muted small">No ${position} players</p>`;
            } else {
                players.forEach(player => {
                    const statusClass = this.getPlayerStatusClass(player.status);

                    // Only show badge for bench players
                    const selectedPos = player.selected_position || 'BN';
                    const isBench = selectedPos === 'BN' || selectedPos === 'Bench';
                    const positionBadge = isBench
                        ? '<span class="badge bg-secondary"><i class="fas fa-chair"></i> Bench</span>'
                        : '';

                    html += `
                        <div class="player-card mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${escHtml(player.name)}</strong>
                                    <small class="text-muted d-block">${escHtml(player.team)}</small>
                                </div>
                                <div class="text-end">
                                    ${positionBadge}
                                    <span class="badge ${statusClass} ms-1">${escHtml(player.status || 'Active')}</span>
                                    <button class="btn btn-outline-info btn-sm ms-1" onclick="showPlayerInsights('${escJs(player.name)}', '${escJs(player.position)}')">
                                        <i class="fas fa-info"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }

            html += '</div>';
        });

        html += '</div>';
        document.getElementById('rosterContainer').innerHTML = html;
    }

    async loadAvailablePlayers(leagueKey) {
        try {
            this.showLoading('availablePlayersContainer', 'Loading available players...');

            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'getAvailablePlayers',
                    league_key: leagueKey
                })
            });

            if (response.success) {
                this.displayAvailablePlayers(response.players);
                this.availablePlayers = response.players; // Store for filtering
            } else {
                this.showError('availablePlayersContainer', response.error || 'Failed to load available players');
            }
        } catch (error) {
            console.error('Error loading available players:', error);
            this.showError('availablePlayersContainer', 'Failed to load available players.');
        }
    }

    displayAvailablePlayers(players) {
        if (!players || players.length === 0) {
            document.getElementById('availablePlayersContainer').innerHTML = `
                <div class="text-center text-muted">
                    <p>No available players found</p>
                </div>
            `;
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Position</th>
                            <th>Team</th>
                            <th>Owned %</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        players.slice(0, 20).forEach(player => { // Show top 20
            // Determine if player is on waivers or free agent
            const eligibilityBadge = player.eligibility === 'W'
                ? '<span class="badge bg-warning text-dark">Waivers</span>'
                : '<span class="badge bg-success">Free Agent</span>';

            html += `
                <tr>
                    <td><strong>${escHtml(player.name)}</strong></td>
                    <td>${formatPosition(player.position)}</td>
                    <td>${escHtml(player.team)}</td>
                    <td>${player.ownership_percentage}%</td>
                    <td>${eligibilityBadge}</td>
                    <td>
                        <button class="btn btn-success btn-sm" onclick="addPlayer('${escJs(player.player_id)}', '${escJs(player.name)}')">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="showPlayerInsights('${escJs(player.name)}', '${escJs(player.position)}')">
                            <i class="fas fa-info"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        document.getElementById('availablePlayersContainer').innerHTML = html;
    }

    async loadTeamStats(leagueKey) {
        try {
            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'getTeamStats',
                    league_key: leagueKey
                })
            });

            if (response.success) {
                this.displayTeamStats(response.stats);
            } else {
                document.getElementById('teamStatsContainer').innerHTML = `
                    <p class="text-muted">Stats not available</p>
                `;
            }
        } catch (error) {
            console.error('Error loading team stats:', error);
            document.getElementById('teamStatsContainer').innerHTML = `
                <p class="text-muted">Failed to load stats</p>
            `;
        }
    }

    displayTeamStats(stats) {
        const container = document.getElementById('teamStatsContainer');

        if (!stats) {
            container.innerHTML = '<p class="text-muted">No stats available</p>';
            return;
        }

        // Format points for & against
        const pointsForAgainst = `${stats.points_for || '0.0'} - ${stats.points_against || '0.0'}`;

        let html = `
            <div class="row text-center mt-3">
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h6 class="card-title mb-0">${stats.wins || 0}-${stats.losses || 0}</h6>
                            <small class="text-muted">Record</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h6 class="card-title mb-0">${pointsForAgainst}</h6>
                            <small class="text-muted">Points For & Against</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h6 class="card-title mb-0">${stats.rank || 'N/A'}</h6>
                            <small class="text-muted">League Rank</small>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <h6 class="card-title mb-0">${stats.waiver_priority || 'N/A'}</h6>
                            <small class="text-muted">Waiver Priority</small>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    async startFullAnalysis() {
        if (!this.selectedLeague) {
            showToast('Error', 'Please select a league first', 'error');
            return;
        }

        try {
            const analysisContainer = document.getElementById('analysisContainer');
            this.analysisProgress = new AnalysisProgress('analysisContainer');
            this.analysisProgress.start();

            // Get focus area from input field
            const focusArea = document.getElementById('analysisFocus')?.value.trim() || '';

            // Get selected positions
            const selectedPositions = Array.from(document.querySelectorAll('.position-filter:checked'))
                .map(cb => cb.value);

            // Require at least one position
            if (selectedPositions.length === 0) {
                showToast('Error', 'Please select at least one position to analyze', 'error');
                this.analysisProgress.error('No positions selected');
                return;
            }

            const response = await makeApiCall('/fantasy/football/analysisCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'startAnalysis',
                    league_key: this.selectedLeague.league_key,
                    focus_area: focusArea,
                    positions: selectedPositions
                })
            });

            if (response.success) {
                // Poll for analysis completion
                this.pollAnalysisStatus(response.request_id);
            } else {
                this.analysisProgress.error(response.error || 'Failed to start analysis');
            }
        } catch (error) {
            console.error('Error starting analysis:', error);
            this.analysisProgress.error('Failed to start analysis. Please try again.');
        }
    }

    async pollAnalysisStatus(requestId) {
        const maxAttempts = 30; // 30 attempts * 2 seconds = 1 minute max
        let attempts = 0;

        const poll = async () => {
            try {
                attempts++;

                const response = await makeApiCall('/fantasy/football/analysisCode.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'getAnalysisStatus',
                        request_id: requestId
                    })
                });

                if (response.success) {
                    const status = response.status;

                    if (status === 'completed') {
                        this.displayAnalysisResults(response.recommendations);
                        return;
                    } else if (status === 'failed') {
                        this.analysisProgress.error('Analysis failed. Please try again.');
                        return;
                    } else if (status === 'processing') {
                        // Update progress step based on status
                        if (attempts <= 10) this.analysisProgress.updateStep(1);
                        else if (attempts <= 20) this.analysisProgress.updateStep(2);
                        else this.analysisProgress.updateStep(3);
                    }
                }

                if (attempts < maxAttempts) {
                    setTimeout(poll, 2000); // Poll every 2 seconds
                } else {
                    this.analysisProgress.error('Analysis is taking longer than expected. Please try again.');
                }
            } catch (error) {
                console.error('Error polling analysis status:', error);
                this.analysisProgress.error('Error checking analysis status.');
            }
        };

        poll();
    }

    displayAnalysisResults(recommendations) {
        if (!recommendations) {
            this.analysisProgress.error('No recommendations received');
            return;
        }

        const html = displayRecommendations(recommendations);
        this.analysisProgress.complete(html);
    }

    filterPlayersByPosition(position) {
        if (!this.availablePlayers) return;

        const filteredPlayers = position
            ? this.availablePlayers.filter(player => {
                // Handle both DEF and DST for defense
                if (position === 'DEF') {
                    return player.position === 'DEF' || player.position === 'DST';
                }
                return player.position === position;
            })
            : this.availablePlayers;

        this.displayAvailablePlayers(filteredPlayers);
    }


    async optimizeRoster() {
        if (!this.selectedLeague) {
            showToast('Error', 'Please select a league first', 'error');
            return;
        }

        try {
            showToast('Roster Optimizer', 'Analyzing your lineup...', 'info');

            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'optimizeRoster',
                    league_key: this.selectedLeague.league_key
                })
            });

            if (response.success) {
                this.showOptimizationResults(response.optimization);
            } else {
                showToast('Error', response.error || 'Failed to optimize roster', 'error');
            }
        } catch (error) {
            console.error('Error optimizing roster:', error);
            showToast('Error', 'Failed to optimize roster. Please try again.', 'error');
        }
    }

    showOptimizationResults(optimization) {
        let html = `
            <div class="optimization-results">
                <div class="alert alert-success">
                    <h6><i class="fas fa-magic"></i> Lineup Optimization Complete</h6>
                    <p class="mb-2">Based on matchups, projections, and AI analysis:</p>
                </div>
        `;

        // Lineup Changes
        if (optimization.lineup_changes && optimization.lineup_changes.length > 0) {
            html += `
                <div class="mb-4">
                    <h6 class="text-warning"><i class="fas fa-exchange-alt"></i> Recommended Lineup Changes</h6>
            `;
            optimization.lineup_changes.forEach(change => {
                const priorityClass = change.priority === 'High' ? 'danger' : change.priority === 'Medium' ? 'warning' : 'info';
                html += `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${change.action}</strong>: ${change.player_name} (${change.position})
                                    <div class="small text-muted">${change.reasoning}</div>
                                </div>
                                <span class="badge bg-${priorityClass}">${change.priority}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
        }

        // Start/Sit Recommendations
        if (optimization.start_sit_recommendations && optimization.start_sit_recommendations.length > 0) {
            html += `
                <div class="mb-4">
                    <h6 class="text-success"><i class="fas fa-users"></i> Start/Sit Recommendations</h6>
            `;
            optimization.start_sit_recommendations.forEach(rec => {
                const actionClass = rec.action === 'start' ? 'success' : 'warning';
                html += `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${rec.action.toUpperCase()}</strong>: ${rec.player_name} (${rec.position})
                                    <div class="small text-muted">${rec.reasoning}</div>
                                </div>
                                <span class="badge bg-${actionClass}">${rec.matchup_grade || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;
        }

        // Injury Concerns
        if (optimization.injury_concerns && optimization.injury_concerns.length > 0) {
            html += `
                <div class="mb-4">
                    <h6 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Injury Concerns</h6>
            `;
            optimization.injury_concerns.forEach(concern => {
                html += `
                    <div class="alert alert-warning mb-2">
                        <strong>${concern.player_name}</strong>: ${concern.status}
                        <div class="small">${concern.recommendation}</div>
                    </div>
                `;
            });
            html += `</div>`;
        }

        // Overall Strategy
        if (optimization.overall_strategy) {
            html += `
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb"></i> Overall Strategy</h6>
                    <p class="mb-0">${optimization.overall_strategy}</p>
                </div>
            `;
        }

        html += `
                <div class="text-muted small">
                    <i class="fas fa-info-circle"></i> Optimization based on current week matchups, weather, and injury reports.
                </div>
            </div>
        `;

        // Create and show modal
        this.showModal('Roster Optimization Results', html);
    }

    showModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="window.open('https://football.fantasysports.yahoo.com/', '_blank')">
                            Go to Yahoo Fantasy
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();

        // Clean up modal when hidden
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    getPlayerStatusClass(status) {
        switch (status?.toLowerCase()) {
            case 'out':
            case 'ir':
                return 'bg-danger';
            case 'questionable':
            case 'doubtful':
                return 'bg-warning';
            case 'probable':
                return 'bg-info';
            default:
                return 'bg-success';
        }
    }

    showLoading(containerId, message) {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">${message}</span>
                    </div>
                    <p class="mt-2 text-muted">${message}</p>
                </div>
            `;
        }
    }

    showError(containerId, message) {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `;
        }
    }

    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
}

// Global functions for player actions - handled by common.js

window.showPlayerInsights = async function(playerName, position) {
    console.log('Show player insights:', playerName, position);

    // Show loading modal immediately
    const loadingModal = `
        <div class="modal fade" id="playerInsightsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-chart-line"></i> ${playerName} Insights
                            <span class="badge bg-light text-dark ms-2">${position}</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted">Getting latest insights for ${playerName}...</p>
                        <small class="text-muted">This may take a few seconds</small>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', loadingModal);
    const modal = new bootstrap.Modal(document.getElementById('playerInsightsModal'));
    modal.show();

    try {
        const response = await makeApiCall('/fantasy/football/analysisCode.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'getPlayerInsights',
                player_name: playerName,
                position: position
            })
        });

        // Remove loading modal
        modal.hide();
        document.getElementById('playerInsightsModal')?.remove();

        console.log('=== API RESPONSE ===', response);
        console.log('Success?', response.success);
        console.log('Insights:', response.insights);
        console.log('Insights type:', typeof response.insights);
        console.log('Insights length:', response.insights ? response.insights.length : 0);

        if (response.success) {
            if (!response.insights || response.insights.trim().length === 0) {
                console.error('ERROR: Insights is empty or whitespace only!');
                showToast('Error', 'No insights data received from server', 'error');
                return;
            }
            console.log('Calling displayPlayerInsightsModal...');
            displayPlayerInsightsModal(playerName, position, response.insights);
        } else {
            console.error('API Error:', response.error);
            showToast('Error', response.error || 'Failed to get player insights', 'error');
        }
    } catch (error) {
        console.error('Error getting player insights:', error);
        modal.hide();
        document.getElementById('playerInsightsModal')?.remove();
        showToast('Error', 'Failed to get player insights. Please try again.', 'error');
    }
};

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.fantasyDashboard = new FantasyDashboard();
});