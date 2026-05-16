<?php
$pageTitle = "AI Analysis - Fantasy Football AI";
include '../common/header.php';
// Note: header.php already includes common.php and handles the auth check

$userId = getCurrentUserId();
logMessage("Analysis page accessed by user_id: $userId", 'INFO');
?>

<!-- header.php already outputs HTML header and navigation -->

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-robot"></i> AI Analysis Center</h4>
                    <p class="mb-0">Comprehensive fantasy football analysis powered by Claude AI</p>
                </div>
                <div class="card-body">
                    <!-- League Selection -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="leagueSelect" class="form-label">Select League:</label>
                            <select id="leagueSelect" class="form-select">
                                <option value="">Loading leagues...</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button id="runAnalysisBtn" class="btn btn-success" disabled>
                                <i class="fas fa-brain"></i> Run Full Analysis
                            </button>
                        </div>
                    </div>

                    <!-- Analysis Results -->
                    <div id="analysisResults" class="row">
                        <div class="col-12">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-robot fa-4x mb-3"></i>
                                <h5>Ready for AI Analysis</h5>
                                <p>Select a league and click "Run Full Analysis" to get intelligent roster recommendations.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analysis History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Analysis</h5>
                </div>
                <div class="card-body">
                    <div id="analysisHistory">
                        <div class="text-center text-muted">
                            <p>No previous analysis found. Run your first analysis above!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="analysis.js?v=<?php echo $version; ?>"></script>

<?php include '../common/footer.html'; ?>