// Fantasy Football Analysis JavaScript
class FantasyAnalysis {
    constructor() {
        this.selectedLeague = null;
        this.analysisProgress = null;
        this.init();
    }

    init() {
        console.log('Fantasy Analysis initialized');
        this.bindEvents();
        this.loadUserLeagues();
        this.loadAnalysisHistory();
    }

    bindEvents() {
        document.getElementById('leagueSelect')?.addEventListener('change', (e) => {
            const leagueData = e.target.value;
            if (leagueData) {
                this.selectedLeague = JSON.parse(leagueData);
                document.getElementById('runAnalysisBtn').disabled = false;
            } else {
                this.selectedLeague = null;
                document.getElementById('runAnalysisBtn').disabled = true;
            }
        });

        document.getElementById('runAnalysisBtn')?.addEventListener('click', () => {
            this.startAnalysis();
        });
    }

    async loadUserLeagues() {
        try {
            const response = await makeApiCall('/fantasy/football/dashboardCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'getUserLeagues'
                })
            });

            if (response.success) {
                this.populateLeagueSelect(response.leagues);
            } else {
                this.showError('Failed to load leagues: ' + (response.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading leagues:', error);
            this.showError('Failed to load leagues. Please try again.');
        }
    }

    populateLeagueSelect(leagues) {
        const select = document.getElementById('leagueSelect');

        if (!leagues || leagues.length === 0) {
            select.innerHTML = '<option value="">No leagues found</option>';
            return;
        }

        let html = '<option value="">Select a league...</option>';
        leagues.forEach(league => {
            html += `<option value='${JSON.stringify(league)}'>${league.name} (${league.num_teams} teams)</option>`;
        });

        select.innerHTML = html;
    }

    async startAnalysis() {
        if (!this.selectedLeague) {
            showToast('Error', 'Please select a league first', 'error');
            return;
        }

        try {
            const resultsContainer = document.getElementById('analysisResults');
            this.analysisProgress = new AnalysisProgress('analysisResults');
            this.analysisProgress.start();

            const response = await makeApiCall('/fantasy/football/analysisCode.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'startAnalysis',
                    league_key: this.selectedLeague.league_key
                })
            });

            if (response.success) {
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
        const maxAttempts = 30;
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
                        this.loadAnalysisHistory(); // Refresh history
                        return;
                    } else if (status === 'failed') {
                        this.analysisProgress.error('Analysis failed. Please try again.');
                        return;
                    } else if (status === 'processing') {
                        // Update progress step based on attempts
                        if (attempts <= 10) this.analysisProgress.updateStep(1);
                        else if (attempts <= 20) this.analysisProgress.updateStep(2);
                        else this.analysisProgress.updateStep(3);
                    }
                }

                if (attempts < maxAttempts) {
                    setTimeout(poll, 2000);
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

        let html = '<div class="col-12">';
        html += '<h5 class="text-success mb-3"><i class="fas fa-check-circle"></i> Analysis Complete!</h5>';
        html += displayRecommendations(recommendations);
        html += '</div>';

        this.analysisProgress.complete(html);
        showToast('Analysis Complete', 'Your AI-powered fantasy analysis is ready!', 'success');
    }

    async loadAnalysisHistory() {
        try {
            // For now, show placeholder
            const historyContainer = document.getElementById('analysisHistory');
            historyContainer.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-clock"></i>
                    <p>Analysis history feature coming soon!</p>
                    <p class="small">Your analysis history will be displayed here to track your AI recommendations over time.</p>
                </div>
            `;
        } catch (error) {
            console.error('Error loading analysis history:', error);
        }
    }

    showError(message) {
        const resultsContainer = document.getElementById('analysisResults');
        resultsContainer.innerHTML = `
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            </div>
        `;
    }
}

// Initialize analysis when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new FantasyAnalysis();
});