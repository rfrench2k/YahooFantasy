// Fantasy Football AI - Common JavaScript Functions

// Escape a value for safe insertion as HTML text or an attribute value.
function escHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Escape a value for safe insertion inside a single-quoted JS string
// that itself sits inside a double-quoted HTML attribute (e.g. onclick="...").
function escJs(value) {
    return String(value ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, "\\'");
}

// Global utility functions
function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

// API call wrapper with error handling
async function makeApiCall(url, options = {}) {
    try {
        console.log('Making API call to:', url);

        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('API response:', data);
        return data;
    } catch (error) {
        console.error('API call failed:', error);
        showToast('API Error', error.message, 'error');
        throw error;
    }
}

// Show toast notifications
function showToast(title, message, type = 'info') {
    const toastHtml = `
        <div class="toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong><br>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastElement);
    toast.show();

    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Show loading spinner
function showLoading(element, text = 'Loading...') {
    if (typeof element === 'string') {
        element = document.getElementById(element);
    }

    if (element) {
        element.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">${text}</p>
            </div>
        `;
    }
}

// Format player position with badge styling
function formatPosition(position) {
    const positionColors = {
        'QB': 'danger',
        'RB': 'success',
        'WR': 'primary',
        'TE': 'warning',
        'K': 'secondary',
        'DST': 'dark',
        'DEF': 'dark'
    };

    const color = positionColors[position] || 'secondary';
    return `<span class="badge bg-${color}">${position}</span>`;
}

// Format player name with team
function formatPlayerName(player) {
    if (player.team) {
        return `${player.name} <small class="text-muted">(${player.team})</small>`;
    }
    return player.name;
}

// Analysis progress tracking
class AnalysisProgress {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.steps = [
            'Fetching roster data...',
            'Getting available players...',
            'Running AI analysis...',
            'Generating recommendations...'
        ];
        this.currentStep = 0;
    }

    start() {
        if (!this.container) return;

        this.container.innerHTML = `
            <div class="progress mb-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                     role="progressbar" style="width: 0%"></div>
            </div>
            <div id="stepText" class="text-center"></div>
        `;

        this.updateStep(0);
    }

    updateStep(stepIndex) {
        this.currentStep = stepIndex;
        const progressBar = this.container.querySelector('.progress-bar');
        const stepText = this.container.querySelector('#stepText');

        if (progressBar && stepText) {
            const percentage = ((stepIndex + 1) / this.steps.length) * 100;
            progressBar.style.width = `${percentage}%`;
            stepText.textContent = this.steps[stepIndex] || 'Complete!';
        }
    }

    complete(content) {
        if (this.container) {
            this.container.innerHTML = content;
        }
    }

    error(message) {
        if (this.container) {
            this.container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Analysis Failed:</strong> ${message}
                </div>
            `;
        }
    }
}

// Recommendation display helpers
function displayRecommendations(recommendations) {
    let html = '';

    if (recommendations.add_recommendations && recommendations.add_recommendations.length > 0) {
        html += '<div class="mb-4">';
        html += '<h5 class="text-success"><i class="fas fa-plus-circle"></i> Recommended Adds</h5>';
        html += '<div class="table-responsive">';
        html += '<table class="table table-hover">';
        html += '<thead><tr><th>Player</th><th>Position</th><th>Priority</th><th>Reasoning</th><th>Action</th></tr></thead>';
        html += '<tbody>';

        recommendations.add_recommendations.forEach(rec => {
            const playerName = rec.player_name || rec.name || 'Unknown Player';
            const playerTeam = rec.team || '';
            html += `<tr>
                <td><strong>${escHtml(playerName)}</strong>${playerTeam ? ` <small class="text-muted">(${escHtml(playerTeam)})</small>` : ''}</td>
                <td>${formatPosition(rec.position)}</td>
                <td><span class="badge bg-${rec.priority === 'High' ? 'danger' : rec.priority === 'Medium' ? 'warning' : 'secondary'}">${rec.priority}</span></td>
                <td><small>${escHtml(rec.reasoning)}</small></td>
                <td>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info btn-sm" onclick="showPlayerInsights('${escJs(playerName)}', '${escJs(rec.position)}')" title="Player Insights">
                            <i class="fas fa-info"></i>
                        </button>
                        <a href="https://football.fantasysports.yahoo.com/f1/${window.fantasyDashboard?.selectedLeague?.league_key?.split('.')[2] || 'LEAGUE_ID'}/addplayer?apid=${rec.player_id || (rec.player_key || '').split('.').pop() || ''}" target="_blank" class="btn btn-success btn-sm" title="Add Player">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div></div>';
    }

    if (recommendations.drop_candidates && recommendations.drop_candidates.length > 0) {
        html += '<div class="mb-4">';
        html += '<h5 class="text-danger"><i class="fas fa-minus-circle"></i> Drop Candidates</h5>';
        html += '<div class="table-responsive">';
        html += '<table class="table table-hover">';
        html += '<thead><tr><th>Player</th><th>Position</th><th>Reasoning</th><th>Action</th></tr></thead>';
        html += '<tbody>';

        recommendations.drop_candidates.forEach(rec => {
            const playerName = rec.player_name || rec.name || 'Unknown Player';
            const playerTeam = rec.team || '';
            html += `<tr>
                <td><strong>${escHtml(playerName)}</strong>${playerTeam ? ` <small class="text-muted">(${escHtml(playerTeam)})</small>` : ''}</td>
                <td>${formatPosition(rec.position)}</td>
                <td><small>${escHtml(rec.reasoning)}</small></td>
                <td>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info btn-sm" onclick="showPlayerInsights('${escJs(playerName)}', '${escJs(rec.position)}')" title="Player Insights">
                            <i class="fas fa-info"></i>
                        </button>
                        <a href="https://football.fantasysports.yahoo.com/f1/${window.fantasyDashboard?.selectedLeague?.league_key?.split('.')[2] || 'LEAGUE_ID'}/dropplayer?dpid=${rec.player_id || (rec.player_key || '').split('.').pop() || ''}" target="_blank" class="btn btn-outline-danger btn-sm" title="Drop Player">
                            <i class="fas fa-minus"></i>
                        </a>
                    </div>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div></div>';
    }

    if (recommendations.roster_optimizations && recommendations.roster_optimizations.length > 0) {
        html += '<div class="alert alert-info">';
        html += '<h6><i class="fas fa-lightbulb"></i> Roster Optimizations</h6>';
        html += '<ul class="mb-0">';
        recommendations.roster_optimizations.forEach(opt => {
            html += `<li>${escHtml(opt)}</li>`;
        });
        html += '</ul></div>';
    }

    if (recommendations.general_analysis) {
        html += '<div class="alert alert-secondary">';
        html += '<h6><i class="fas fa-chart-line"></i> General Analysis</h6>';
        html += `<p class="mb-0">${escHtml(recommendations.general_analysis)}</p>`;
        html += '</div>';
    }

    return html;
}

// Player action functions
function addPlayer(playerIdOrName, playerKeyOrName) {
    console.log('Add player:', playerIdOrName, playerKeyOrName);

    // Get the current league key from the dashboard
    const dashboard = window.fantasyDashboard;
    if (dashboard && dashboard.selectedLeague) {
        const leagueKey = dashboard.selectedLeague.league_key;
        // Extract league ID from league key (format: 461.l.861736)
        const leagueId = leagueKey.split('.')[2];

        let playerId;
        let playerName;

        // Handle single parameter (just player ID) or two parameters
        if (arguments.length === 1) {
            // Single parameter - assume it's player ID
            playerId = playerIdOrName;
            playerName = 'player';
        } else {
            // Two parameters - determine which is which
            if (!isNaN(playerIdOrName) || /^\d+$/.test(playerIdOrName)) {
                // First parameter is player ID, second is player name
                playerId = playerIdOrName;
                playerName = playerKeyOrName;
            } else {
                // First parameter is player name, second is player key
                playerName = playerIdOrName;
                if (playerKeyOrName && playerKeyOrName.includes('.')) {
                    // Extract player ID from player key (format: 461.p.12345)
                    playerId = playerKeyOrName.split('.').pop();
                }
            }
        }

        if (playerId) {
            const url = `https://football.fantasysports.yahoo.com/f1/${leagueId}/addplayer?apid=${playerId}`;
            window.open(url, '_blank');
            showToast('Add Player', `Opening Yahoo Fantasy to add ${playerName}`, 'success');
        } else {
            // Open the general add players page
            const url = `https://football.fantasysports.yahoo.com/f1/${leagueId}/addplayer`;
            window.open(url, '_blank');
            showToast('Add Player', `Opening Yahoo Fantasy add player page`, 'info');
        }
    } else {
        showToast('Error', 'Please select a league first', 'error');
    }
}

function dropPlayer(playerIdOrName, playerKeyOrName) {
    console.log('Drop player:', playerIdOrName, playerKeyOrName);

    // Get the current league key from the dashboard
    const dashboard = window.fantasyDashboard;
    if (dashboard && dashboard.selectedLeague) {
        const leagueKey = dashboard.selectedLeague.league_key;
        // Extract league ID from league key (format: 461.l.861736)
        const leagueId = leagueKey.split('.')[2];

        let playerId;
        let playerName;

        // Determine if first parameter is player ID (number) or player name (string)
        if (!isNaN(playerIdOrName) || /^\d+$/.test(playerIdOrName)) {
            // First parameter is player ID, second is player name
            playerId = playerIdOrName;
            playerName = playerKeyOrName;
        } else {
            // First parameter is player name, second is player key
            playerName = playerIdOrName;
            if (playerKeyOrName && playerKeyOrName.includes('.')) {
                // Extract player ID from player key (format: 461.p.12345)
                playerId = playerKeyOrName.split('.').pop();
            }
        }

        if (playerId) {
            const url = `https://football.fantasysports.yahoo.com/f1/${leagueId}/dropplayer?dpid=${playerId}`;
            window.open(url, '_blank');
            showToast('Drop Player', `Opening Yahoo Fantasy to drop ${playerName}`, 'success');
        } else {
            // Open the general team management page
            const url = `https://football.fantasysports.yahoo.com/f1/${leagueId}/team`;
            window.open(url, '_blank');
            showToast('Drop Player', `Opening Yahoo Fantasy team management`, 'info');
        }
    } else {
        showToast('Error', 'Please select a league first', 'error');
    }
}

// Player insights modal
// Format player insights text for better readability
function formatInsightsText(text) {
    if (!text) return '<p class="text-muted">No insights available</p>';

    // Split by double asterisks for sections
    let formatted = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

    // Split into paragraphs on double newlines or section markers
    let paragraphs = formatted.split(/\n\n|(?=\*\*)/);

    // Wrap each paragraph
    let html = '';
    paragraphs.forEach(para => {
        para = para.trim();
        if (para) {
            // Check if it's a section header
            if (para.startsWith('<strong>')) {
                html += `<div class="mb-3">
                    <h6 class="text-primary">${para}</h6>
                </div>`;
            } else {
                html += `<p class="mb-2">${para}</p>`;
            }
        }
    });

    return html || '<p class="text-muted">No insights available</p>';
}

function displayPlayerInsightsModal(playerName, position, insights) {
    // Format the insights text with proper paragraphs and sections
    const formattedInsights = formatInsightsText(insights);

    const modalHtml = `
        <div class="modal fade" id="playerInsightsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-chart-line"></i>
                            ${playerName} Insights
                            <span class="badge bg-light text-dark ms-2">${formatPosition(position)}</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="player-insights-content" style="line-height: 1.6;">
                            ${formattedInsights}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('playerInsightsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('playerInsightsModal'));
    modal.show();

    // Clean up when modal is hidden
    document.getElementById('playerInsightsModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Initialize common functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Fantasy Football AI - Common JS loaded');

    // Add any global event listeners here

    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});