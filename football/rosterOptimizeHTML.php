<?php
$pageTitle = "Roster Optimizer - Fantasy Football AI";
include '../common/header.php';
// Note: header.php already includes common.php and handles the auth check

$userId = getCurrentUserId();
logMessage("Roster optimizer accessed by user_id: $userId", 'INFO');
?>

<!-- header.php already outputs HTML header and navigation -->

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h4><i class="fas fa-magic"></i> Roster Optimizer</h4>
                    <p class="mb-0">Optimize your lineup for maximum points using AI analysis</p>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Coming Soon!</strong> The roster optimizer will help you set optimal lineups based on matchups, projections, and AI analysis.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6>Features in Development:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Optimal lineup suggestions</li>
                                <li><i class="fas fa-check text-success"></i> Matchup-based recommendations</li>
                                <li><i class="fas fa-check text-success"></i> Injury risk assessment</li>
                                <li><i class="fas fa-check text-success"></i> Weather impact analysis</li>
                                <li><i class="fas fa-check text-success"></i> Start/sit recommendations</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Available Now:</h6>
                            <div class="d-grid gap-2">
                                <a href="dashboardHTML.php" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt"></i> Return to Dashboard
                                </a>
                                <a href="analysisHTML.php" class="btn btn-success">
                                    <i class="fas fa-robot"></i> Run AI Analysis
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../common/footer.html'; ?>