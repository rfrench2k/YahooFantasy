// Fantasy Football Leagues JavaScript
class FantasyLeagues {
    constructor() {
        this.leagues = [];
        this.selectedLeague = null;
        this.init();
    }

    init() {
        console.log('Fantasy Leagues initialized');
        this.bindEvents();
        this.loadUserLeagues();
    }

    bindEvents() {
        document.getElementById('refreshLeaguesBtn')?.addEventListener('click', () => {
            this.loadUserLeagues();
        });

        document.getElementById('viewDashboardBtn')?.addEventListener('click', () => {
            window.location.href = '/fantasy/football/dashboardHTML.php';
        });

        document.getElementById('runAnalysisBtn')?.addEventListener('click', () => {
            if (this.selectedLeague) {
                window.location.href = '/fantasy/football/analysisHTML.php';
            }
        });

        document.getElementById('viewRosterBtn')?.addEventListener('click', () => {
            if (this.selectedLeague) {
                this.viewRoster();
            }
        });
    }

    async loadUserLeagues() {
        try {
            this.showLoading('leaguesContainer', 'Loading your fantasy leagues...');

            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'getUserLeagues'
                })
            });

            if (response.success) {
                this.leagues = response.leagues;
                this.displayLeagues(response.leagues);
            } else {
                this.showError('leaguesContainer', response.error || 'Failed to load leagues');
            }
        } catch (error) {
            console.error('Error loading leagues:', error);
            this.showError('leaguesContainer', 'Failed to load leagues. Please try again.');
        }
    }

    displayLeagues(leagues) {
        const container = document.getElementById('leaguesContainer');

        if (!leagues || leagues.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <h5>No Fantasy Leagues Found</h5>
                    <p>Make sure you're logged into Yahoo Fantasy Football and have joined leagues for the current season.</p>
                    <a href="https://football.fantasysports.yahoo.com/" target="_blank" class="btn btn-primary">
                        <i class="fab fa-yahoo"></i> Go to Yahoo Fantasy Football
                    </a>
                </div>
            `;
            return;
        }

        let html = '<div class="row">';

        leagues.forEach((league, index) => {
            const isActive = league.current_week && league.current_week > 0;

            html += `
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card league-card h-100 ${isActive ? 'border-success' : 'border-secondary'}"
                         data-league-index="${index}" style="cursor: pointer;">
                        <div class="card-header bg-${isActive ? 'success' : 'secondary'} text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-trophy"></i> ${league.name}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <strong>${league.num_teams}</strong>
                                        <small class="d-block text-muted">Teams</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <strong>Week ${league.current_week || 'N/A'}</strong>
                                        <small class="d-block text-muted">Current</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <strong>${this.capitalizeFirst(league.scoring_type)}</strong>
                                        <small class="d-block text-muted">Scoring</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary btn-sm select-league-btn">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-success quick-analysis-btn" title="Quick Analysis">
                                        <i class="fas fa-robot"></i>
                                    </button>
                                    <a href="${league.url || '#'}" target="_blank" class="btn btn-outline-secondary" title="Open in Yahoo">
                                        <i class="fab fa-yahoo"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-muted small">
                            League ID: ${league.league_id}
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Add click handlers
        this.bindLeagueEvents();
    }

    bindLeagueEvents() {
        // League card click handlers
        document.querySelectorAll('.select-league-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const card = e.target.closest('.league-card');
                const leagueIndex = parseInt(card.dataset.leagueIndex);
                this.selectLeague(this.leagues[leagueIndex], card);
            });
        });

        // Quick analysis buttons
        document.querySelectorAll('.quick-analysis-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const card = e.target.closest('.league-card');
                const leagueIndex = parseInt(card.dataset.leagueIndex);
                this.quickAnalysis(this.leagues[leagueIndex]);
            });
        });
    }

    selectLeague(league, cardEl) {
        console.log('Selected league:', league);
        this.selectedLeague = league;

        // Highlight selected league
        document.querySelectorAll('.league-card').forEach(card => {
            card.classList.remove('border-primary', 'bg-light');
        });
        if (cardEl) {
            cardEl.classList.add('border-primary', 'bg-light');
        }

        // Show league details
        this.displayLeagueDetails(league);
        document.getElementById('leagueDetailsSection').style.display = 'block';

        // Update action buttons
        document.getElementById('yahooLeagueLink').href = league.url || '#';
    }

    displayLeagueDetails(league) {
        document.getElementById('selectedLeagueName').innerHTML =
            `<i class="fas fa-trophy"></i> ${league.name}`;

        const detailsContent = document.getElementById('leagueDetailsContent');
        detailsContent.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>League Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>League Name:</strong></td>
                            <td>${league.name}</td>
                        </tr>
                        <tr>
                            <td><strong>Teams:</strong></td>
                            <td>${league.num_teams}</td>
                        </tr>
                        <tr>
                            <td><strong>Current Week:</strong></td>
                            <td>Week ${league.current_week || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Scoring:</strong></td>
                            <td>${this.capitalizeFirst(league.scoring_type)}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>AI Analysis Features</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Add/Drop recommendations</li>
                        <li><i class="fas fa-check text-success"></i> Roster optimization</li>
                        <li><i class="fas fa-check text-success"></i> Matchup analysis</li>
                        <li><i class="fas fa-check text-success"></i> Injury impact assessment</li>
                        <li><i class="fas fa-check text-success"></i> Waiver wire priorities</li>
                    </ul>
                </div>
            </div>
        `;

        // Display league settings
        this.displayLeagueSettings(league);
    }

    displayLeagueSettings(league) {
        const settingsContainer = document.getElementById('leagueSettings');

        settingsContainer.innerHTML = `
            <div class="small">
                <div class="mb-2">
                    <strong>League Key:</strong><br>
                    <code class="small">${league.league_key}</code>
                </div>
                <div class="mb-2">
                    <strong>League ID:</strong><br>
                    <code class="small">${league.league_id}</code>
                </div>
                <div class="mb-2">
                    <strong>Status:</strong><br>
                    <span class="badge ${league.current_week > 0 ? 'bg-success' : 'bg-secondary'}">
                        ${league.current_week > 0 ? 'Active' : 'Inactive'}
                    </span>
                </div>
            </div>
        `;
    }

    async viewRoster() {
        if (!this.selectedLeague) return;

        try {
            showToast('Loading Roster', 'Fetching your team roster...', 'info');

            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'getTeamRoster',
                    league_key: this.selectedLeague.league_key
                })
            });

            if (response.success) {
                this.displayRosterModal(response.roster, response.team);
            } else {
                showToast('Error', response.error || 'Failed to load roster', 'error');
            }
        } catch (error) {
            console.error('Error loading roster:', error);
            showToast('Error', 'Failed to load roster. Please try again.', 'error');
        }
    }

    displayRosterModal(roster, team) {
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="rosterModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-clipboard-list"></i> ${team ? team.name : 'My Team'} Roster
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${this.generateRosterHTML(roster)}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-success" onclick="window.location.href='/fantasy/football/analysisHTML.php'">
                                <i class="fas fa-robot"></i> Run AI Analysis
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('rosterModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('rosterModal'));
        modal.show();

        // Clean up modal after hiding
        document.getElementById('rosterModal').addEventListener('hidden.bs.modal', function () {
            this.remove();
        });
    }

    generateRosterHTML(roster) {
        if (!roster || roster.length === 0) {
            return '<p class="text-muted">No roster data available</p>';
        }

        // Group players by position
        const positions = ['QB', 'RB', 'WR', 'TE', 'K', 'DST'];
        const playersByPosition = {};

        positions.forEach(pos => {
            playersByPosition[pos] = roster.filter(player => player.position === pos);
        });

        let html = '<div class="row">';

        positions.forEach(position => {
            const players = playersByPosition[position] || [];

            html += `
                <div class="col-md-6 mb-3">
                    <h6>${formatPosition(position)}</h6>
            `;

            if (players.length === 0) {
                html += `<p class="text-muted small">No ${position} players</p>`;
            } else {
                players.forEach(player => {
                    const statusClass = this.getPlayerStatusClass(player.status);
                    html += `
                        <div class="card mb-2">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${player.name}</strong>
                                        <small class="text-muted d-block">
                                            ${player.team} - ${player.selected_position || 'Bench'}
                                        </small>
                                    </div>
                                    <span class="badge ${statusClass}">
                                        ${player.status || 'Active'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }

            html += '</div>';
        });

        html += '</div>';
        return html;
    }

    quickAnalysis(league) {
        console.log('Quick analysis for league:', league);
        showToast('Quick Analysis', 'Starting quick analysis for ' + league.name, 'info');

        // Store selected league and redirect to analysis page
        this.selectedLeague = league;
        setTimeout(() => {
            window.location.href = '/fantasy/football/analysisHTML.php';
        }, 1000);
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

    capitalizeFirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }

    showLoading(containerId, message) {
        showLoading(containerId, message);
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
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new FantasyLeagues();
});