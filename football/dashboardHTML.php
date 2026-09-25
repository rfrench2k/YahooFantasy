<?php
$pageTitle = "Dashboard - Fantasy Football AI";
include_once '../common/common.php';
include_once '../common/aPRIV_API.php';

// Get authenticated user from centralized AUTH system
$authUserId = getCurrentUserId();
if (!$authUserId) {
    header('Location: /auth/login.php?program=FANTASY&redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get Yahoo user ID from database for this authenticated user
$sql = "SELECT yahoo_user_id FROM users WHERE user_id = ?";
$result = executeQuery($sql, [$authUserId], 's');

if (!$result['success'] || empty($result['data']) || empty($result['data'][0]['yahoo_user_id'])) {
    // User is authenticated but hasn't linked Yahoo account yet
    logMessage("User $authUserId has not linked Yahoo account", 'WARNING');
    $yahooUserId = null;
} else {
    $yahooUserId = $result['data'][0]['yahoo_user_id'];
}

logMessage("Dashboard accessed by auth user: $authUserId, Yahoo user: " . ($yahooUserId ?? 'NOT_LINKED'), 'INFO');

initializeDirectories();
?>

<?php include '../common/header.php'; ?>

<div class="container-fluid mt-4">

    <!-- Teams Section (Merged Leagues + Stats) -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-users"></i> Teams</h5>
                    <div class="d-flex align-items-center">
                        <select id="teamSelector" class="form-select form-select-sm me-2" style="width: 250px; display: none;">
                            <option value="">Select Team...</option>
                        </select>
                        <button id="refreshTeamsBtn" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="teamsContainer">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading your teams...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading your fantasy teams...</p>
                        </div>
                    </div>
                    <!-- Team Stats integrated here -->
                    <div id="teamStatsContainer" style="display: none;">
                        <!-- Team stats will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabbed Content Section -->
    <div id="tabbedContentSection" class="row mb-4" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="mainTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="roster-tab" data-bs-toggle="tab" data-bs-target="#roster-pane" type="button" role="tab">
                                <i class="fas fa-clipboard-list"></i> Current Roster
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="analysis-tab" data-bs-toggle="tab" data-bs-target="#analysis-pane" type="button" role="tab">
                                <i class="fas fa-robot"></i> AI Analysis
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="players-tab" data-bs-toggle="tab" data-bs-target="#players-pane" type="button" role="tab">
                                <i class="fas fa-user-plus"></i> Available Players
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="mainTabsContent">
                        <!-- Roster Tab -->
                        <div class="tab-pane fade show active" id="roster-pane" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Your Team Roster</h6>
                                <div>
                                    <button id="optimizeRosterBtn" class="btn btn-warning btn-sm">
                                        <i class="fas fa-magic"></i> Optimize
                                    </button>
                                    <button id="refreshRosterBtn" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            <div id="rosterContainer">
                                <!-- Roster will be loaded here -->
                            </div>
                        </div>

                        <!-- AI Analysis Tab -->
                        <div class="tab-pane fade" id="analysis-pane" role="tabpanel">
                            <div class="mb-3">
                                <h6 class="mb-3">AI Analysis & Recommendations</h6>

                                <!-- Custom Analysis Request -->
                                <div class="mb-3">
                                    <label for="analysisFocus" class="form-label">Custom Analysis Request (Optional):</label>
                                    <textarea id="analysisFocus" class="form-control" rows="2"
                                              placeholder="e.g., 'Focus on RB depth' or 'Looking for WR upgrade'"></textarea>
                                    <div class="form-text">Specify what you want the AI to focus on, or leave blank for general analysis</div>
                                </div>

                                <!-- Position Selection -->
                                <div class="mb-3 d-flex align-items-center">
                                    <label class="form-label mb-0 me-3">Analyze Positions:</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input position-filter" type="checkbox" value="QB" id="pos-QB">
                                            <label class="form-check-label" for="pos-QB">QB</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input position-filter" type="checkbox" value="RB" id="pos-RB">
                                            <label class="form-check-label" for="pos-RB">RB</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input position-filter" type="checkbox" value="WR" id="pos-WR">
                                            <label class="form-check-label" for="pos-WR">WR</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input position-filter" type="checkbox" value="TE" id="pos-TE">
                                            <label class="form-check-label" for="pos-TE">TE</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input position-filter" type="checkbox" value="K" id="pos-K">
                                            <label class="form-check-label" for="pos-K">K</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input position-filter" type="checkbox" value="DEF" id="pos-DEF">
                                            <label class="form-check-label" for="pos-DEF">DEF</label>
                                        </div>
                                    </div>
                                </div>

                                <button id="startAnalysisBtn" class="btn btn-success">
                                    <i class="fas fa-brain"></i> Run Full Analysis
                                </button>
                            </div>

                            <hr>

                            <div id="analysisContainer">
                                <div class="text-center text-muted">
                                    <i class="fas fa-robot fa-3x mb-3"></i>
                                    <p>Configure your analysis above and click "Run Full Analysis"</p>
                                </div>
                            </div>
                        </div>

                        <!-- Available Players Tab -->
                        <div class="tab-pane fade" id="players-pane" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Top Available Players</h6>
                                <div>
                                    <select id="positionFilter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                        <option value="">All Positions</option>
                                        <option value="QB">QB</option>
                                        <option value="RB">RB</option>
                                        <option value="WR">WR</option>
                                        <option value="TE">TE</option>
                                        <option value="K">K</option>
                                        <option value="DEF">DEF</option>
                                    </select>
                                    <button id="refreshPlayersBtn" class="btn btn-outline-secondary btn-sm ms-2">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            <div id="availablePlayersContainer">
                                <!-- Available players will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="modal fade" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 id="loadingMessage">Processing...</h5>
                <p id="loadingDetail" class="text-muted mb-0">Please wait while we fetch your data.</p>
            </div>
        </div>
    </div>
</div>

<script src="dashboard.js?v=<?php echo $version; ?>"></script>

<?php include '../common/footer.html'; ?>