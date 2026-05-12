<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if 'basic_auth' session variable is set
if(!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    // Redirect to the login page
    header('Location: /eduroam/admin/login.php');
    exit; // Terminate script execution after redirection
}

// Get basic auth from session
$basic_auth = base64_decode($_SESSION['basic_auth']);
$authUser = explode(':', $basic_auth)[0];
$authPass = explode(':', $basic_auth)[1];

$_SERVER['PHP_AUTH_USER'] = $authUser;
$_SERVER['PHP_AUTH_PW'] = $authPass;

// exit if not ($_SERVER['PHP_AUTH_USER'] === $authUser && $_SERVER['PHP_AUTH_PW'] === $authPass)
if ($_SERVER['PHP_AUTH_USER'] !== $authUser || $_SERVER['PHP_AUTH_PW'] !== $authPass) {
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access Denied';
    exit;
}

// Include the config.php file
require_once dirname(__DIR__, 2) . '/includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> Management</title>
    <link rel="stylesheet" href="/eduroam/assets/css/styles.css">
    <?php include dirname(__DIR__, 2) . '/template_parts/head.php'; ?>
</head>
<body class="app-shell">
    <?php include dirname(__DIR__, 2) . '/template_parts/nav.php'; ?>
    <main id="content" class="page-shell">
        <?php $dateTime = date("F j, Y, g:i a"); ?>
        <div class="hero-banner">
            <div>
                <span class="eyebrow">Admin Logs</span>
                <h1>Recent Logs</h1>
                <p class="meta mb-0">Current server time: <?php echo htmlspecialchars($dateTime); ?></p>
            </div>
            <div class="hero-actions">
                <a href="/eduroam/admin/graphs/" class="btn btn-outline-light">
                    <i class="fas fa-chart-area me-2"></i>Monitoring
                </a>
                <a href="/eduroam/admin/analytics/" class="btn btn-outline-light">
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </a>
            </div>
        </div>

        <section class="table-card logs-card">
            <div class="logs-toolbar">
                <div>
                    <span class="chart-eyebrow">FreeRADIUS</span>
                    <h2>Latest Log Entries</h2>
                </div>
                <div class="logs-actions">
                    <label class="log-follow-toggle" for="logFollowLatest">
                        <input type="checkbox" id="logFollowLatest" checked>
                        <span>Follow latest</span>
                    </label>
                    <button type="button" class="btn btn-outline-secondary" id="logJumpBottom">
                        <i class="fas fa-arrow-down me-2"></i>Latest
                    </button>
                    <button type="button" class="btn btn-primary" id="logRefresh">
                        <i class="fas fa-rotate me-2"></i>Refresh
                    </button>
                </div>
            </div>
            <?php include dirname(__DIR__, 2) . '/log.php'; ?>
        </section>
        </main>

    <?php include dirname(__DIR__, 2) . '/template_parts/footer.php'; ?>
    <script>
        (function () {
            const viewer = document.getElementById('logContent');
            const followToggle = document.getElementById('logFollowLatest');
            const jumpButton = document.getElementById('logJumpBottom');
            const refreshButton = document.getElementById('logRefresh');
            const scrollKey = 'eduroamAdminLogScrollTop';
            const followKey = 'eduroamAdminLogFollowLatest';

            if (!viewer || !followToggle) return;

            const savedFollow = sessionStorage.getItem(followKey);
            followToggle.checked = savedFollow === null ? true : savedFollow === '1';

            function isNearBottom() {
                return viewer.scrollHeight - viewer.scrollTop - viewer.clientHeight < 32;
            }

            function savePosition() {
                sessionStorage.setItem(scrollKey, String(viewer.scrollTop));
                sessionStorage.setItem(followKey, followToggle.checked ? '1' : '0');
            }

            function scrollToBottom() {
                viewer.scrollTop = viewer.scrollHeight;
                savePosition();
            }

            window.requestAnimationFrame(function () {
                if (followToggle.checked) {
                    scrollToBottom();
                    return;
                }

                const savedScroll = Number(sessionStorage.getItem(scrollKey));
                if (Number.isFinite(savedScroll)) {
                    viewer.scrollTop = savedScroll;
                }
            });

            viewer.addEventListener('scroll', function () {
                if (!isNearBottom()) {
                    followToggle.checked = false;
                }

                savePosition();
            });

            followToggle.addEventListener('change', function () {
                if (followToggle.checked) {
                    scrollToBottom();
                    return;
                }

                savePosition();
            });

            if (jumpButton) {
                jumpButton.addEventListener('click', function () {
                    followToggle.checked = true;
                    scrollToBottom();
                });
            }

            if (refreshButton) {
                refreshButton.addEventListener('click', function () {
                    savePosition();
                    window.location.reload();
                });
            }

            window.addEventListener('beforeunload', savePosition);
        })();
    </script>

</body>
</html>
