<?php
/**
 * header.php - Fantasy Football AI page header.
 *
 * Provides, for every HTML page:
 *  1. Centralized AUTH check (redirect to login if not authenticated)
 *  2. Yahoo-account-link check (redirect to Yahoo OAuth if not linked,
 *     except on the login page, where the user goes to link it)
 *  3. The HTML <head> and the top navigation bar
 *
 * Include this at the top of every HTML page.
 */

require_once __DIR__ . '/common.php';

/**
 * True if the user has a linked Yahoo account with a stored access token.
 */
function checkYahooAccountLinked($userId) {
    $result = executeQuery(
        "SELECT id FROM users WHERE user_id = ? AND access_token IS NOT NULL",
        [$userId],
        's'
    );
    return ($result['success'] && !empty($result['data']));
}

// Require a centralized-AUTH session
if (!$GLOBALS['sessionUser']) {
    header('Location: /auth/login.php?program=FANTASY&redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId    = $GLOBALS['sessionUser']['user_id'];
$userEmail = $GLOBALS['sessionUser']['user_email'];
$userName  = $GLOBALS['sessionUser']['user_name'] ?? $userEmail;

$yahooLinked = checkYahooAccountLinked($userId);

// The login page is where users go to LINK Yahoo, so it is exempt from the
// "must already have Yahoo linked" redirect. Every other page requires it.
$isYahooLinkPage = str_contains($_SERVER['REQUEST_URI'], '/auth/loginHTML.php');

if (!$yahooLinked && !$isYahooLinkPage) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Link Yahoo Account</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-info">
                <h4>Yahoo Fantasy Account Required</h4>
                <p>To use Fantasy Football features, you need to link your Yahoo Fantasy Sports account.</p>
                <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($userName); ?> (<?php echo htmlspecialchars($userEmail); ?>)</p>
                <hr>
                <p>Redirecting to Yahoo authorization in <span id="countdown">3</span> seconds...</p>
            </div>
        </div>
        <script>
        var timeLeft = 3;
        var countdown = setInterval(function() {
            timeLeft--;
            document.getElementById('countdown').textContent = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                window.location.href = '/fantasy/auth/yahoo_auth.php';
            }
        }, 1000);
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Fantasy Football AI'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="shortcut icon" href="/fantasy/fantasy-ico.svg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sticky-top {
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .roster-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .player-card {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            background: white;
        }
        .analysis-progress {
            display: none;
        }
        .analysis-progress.show {
            display: block;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="/fantasy/football/dashboardHTML.php">
            <i class="fas fa-football-ball"></i> Fantasy Football AI
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/fantasy/football/dashboardHTML.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/fantasy/football/leaguesHTML.php">
                        <i class="fas fa-users"></i> My Leagues
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/fantasy/football/analysisHTML.php">
                        <i class="fas fa-robot"></i> AI Analysis
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/fantasy/football/rosterOptimizeHTML.php">
                        <i class="fas fa-sliders"></i> Roster Optimizer
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center">
                <button id="refreshDataBtn" class="btn btn-outline-light me-3">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Data
                </button>
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($userName); ?>
                </span>
                <?php if ($yahooLinked): ?>
                <a href="/fantasy/auth/logout.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-out-alt"></i> Yahoo Logout
                </a>
                <?php else: ?>
                <a href="/fantasy/auth/yahoo_auth.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-in-alt"></i> Yahoo Login
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Data Refresh Modal -->
<div id="dataRefreshModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Refreshing Fantasy Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="refreshStatus" class="mb-3"></div>
                <div id="refreshLog" class="small border p-2" style="max-height: 200px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var refreshBtn = document.getElementById('refreshDataBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                console.log('Refresh data functionality to be implemented');
            });
        }
    });
</script>
