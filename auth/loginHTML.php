<?php
$pageTitle = "Login - Fantasy Football AI";
include_once '../common/common.php';
include_once '../common/aPRIV_API.php';

logMessage("Login page accessed", 'INFO');
?>

<?php include '../common/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-success text-white text-center">
                    <h4><i class="fas fa-football-ball"></i> Fantasy Football AI</h4>
                    <p class="mb-0 small">Intelligent Roster Recommendations</p>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <p class="lead">Welcome!</p>
                        <p class="text-muted">Get AI-powered fantasy football analysis using Yahoo Fantasy Sports data and Claude AI.</p>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="yahoo_auth.php" class="btn btn-primary btn-lg">
                            <i class="fab fa-yahoo"></i> Login with Yahoo
                        </a>
                    </div>

                    <hr class="my-4">

                    <div class="text-center">
                        <h6>Features:</h6>
                        <ul class="list-unstyled small text-muted">
                            <li><i class="fas fa-check text-success"></i> AI-powered add/drop recommendations</li>
                            <li><i class="fas fa-check text-success"></i> Roster optimization analysis</li>
                            <li><i class="fas fa-check text-success"></i> Injury and matchup insights</li>
                            <li><i class="fas fa-check text-success"></i> Direct Yahoo Fantasy integration</li>
                        </ul>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <small class="text-muted">
                        Don't have a Yahoo Fantasy account?
                        <a href="https://football.fantasysports.yahoo.com/" target="_blank">Sign up here</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../common/footer.html'; ?>