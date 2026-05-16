<?php
$pageTitle = "My Leagues - Fantasy Football AI";
include '../common/header.php';
// Note: header.php already includes common.php and handles the auth check

$userId = getCurrentUserId();
logMessage("Leagues page accessed by user_id: $userId", 'INFO');
?>

<!-- header.php already outputs HTML header and navigation -->

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-users"></i> My Fantasy Leagues</h4>
                        <p class="mb-0">Manage and analyze all your fantasy football leagues</p>
                    </div>
                    <button id="refreshLeaguesBtn" class="btn btn-light">
                        <i class="fas fa-sync-alt"></i> Refresh from Yahoo
                    </button>
                </div>
                <div class="card-body">
                    <div id="leaguesContainer">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading leagues...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading your fantasy leagues...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- League Details Section -->
    <div id="leagueDetailsSection" class="row" style="display: none;">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 id="selectedLeagueName"><i class="fas fa-trophy"></i> League Details</h5>
                </div>
                <div class="card-body">
                    <div id="leagueDetailsContent">
                        <!-- League details will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tools"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button id="viewDashboardBtn" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt"></i> View Dashboard
                        </button>
                        <button id="runAnalysisBtn" class="btn btn-success">
                            <i class="fas fa-robot"></i> Run AI Analysis
                        </button>
                        <button id="viewRosterBtn" class="btn btn-info">
                            <i class="fas fa-clipboard-list"></i> View Roster
                        </button>
                        <a href="#" id="yahooLeagueLink" target="_blank" class="btn btn-outline-secondary">
                            <i class="fab fa-yahoo"></i> Open in Yahoo
                        </a>
                    </div>
                </div>
            </div>

            <!-- League Settings -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-cog"></i> League Settings</h6>
                </div>
                <div class="card-body">
                    <div id="leagueSettings">
                        <!-- Settings will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="leagues.js?v=<?php echo $version; ?>"></script>

<?php include '../common/footer.html'; ?>