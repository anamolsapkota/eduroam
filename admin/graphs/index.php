<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    header('Location: /eduroam/admin/login.php');
    exit;
}

$basic_auth = base64_decode($_SESSION['basic_auth']);
$authParts = explode(':', $basic_auth, 2);
$authUser = $authParts[0] ?? '';
$authPass = $authParts[1] ?? '';

$_SERVER['PHP_AUTH_USER'] = $authUser;
$_SERVER['PHP_AUTH_PW'] = $authPass;

if ($_SERVER['PHP_AUTH_USER'] !== $authUser || $_SERVER['PHP_AUTH_PW'] !== $authPass) {
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access Denied';
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/radius_monitoring.php';

$seo_title = 'FreeRADIUS Monitoring | ' . $site_name;
$seo_description = 'FreeRADIUS authentication monitoring graphs generated from radius.log.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/admin/graphs/';
$seo_robots = 'noindex,follow';
$seo_type = 'website';

$statsRows = radiusMonitorReadStats();
$currentSnapshot = radiusMonitorCollectSnapshot();

if (empty($statsRows)) {
    $statsRows[] = $currentSnapshot;
}

$chartPayload = radiusMonitorBuildChartPayload($statsRows);
$service = radiusMonitorServiceSnapshot();
$recentLines = radiusMonitorRecentLogLines();
$lastSample = end($statsRows) ?: $currentSnapshot;
$sampleCount = count($statsRows);
$statsPath = RADIUS_MONITOR_STATS_PATH;
$logPath = RADIUS_MONITOR_LOG_PATH;
$cronCommand = '*/10 * * * * /usr/bin/php ' . dirname(__DIR__, 2) . '/scripts/collect_radius_auth_stats.php >/dev/null 2>&1';
$isActive = strtolower($service['status']) === 'active';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <link rel="stylesheet" href="/eduroam/assets/css/styles.css">
    <?php include dirname(__DIR__, 2) . '/template_parts/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body class="app-shell">
    <?php include dirname(__DIR__, 2) . '/template_parts/nav.php'; ?>
    <main id="content" class="page-shell radius-monitor-shell">
        <div class="hero-banner">
            <div>
                <span class="eyebrow">FreeRADIUS Monitoring</span>
                <h1>Authentication Graphs</h1>
                <p class="meta mb-0">Accepts, rejects, invalid users, service status, and recent log events.</p>
            </div>
            <div class="hero-actions">
                <a href="/eduroam/admin/logs/" class="btn btn-outline-light">
                    <i class="fas fa-file-lines me-2"></i>Logs
                </a>
                <a href="/eduroam/admin/analytics/" class="btn btn-outline-light">
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </a>
            </div>
        </div>

        <div class="dashboard-grid radius-monitor-stats">
            <div class="stat-card">
                <h2>FreeRADIUS</h2>
                <p>Status: <strong class="<?php echo $isActive ? 'text-success' : 'text-danger'; ?>"><?php echo htmlspecialchars(strtoupper($service['status'])); ?></strong></p>
                <p>Started: <strong><?php echo htmlspecialchars($service['started_at']); ?></strong></p>
                <p>CPU / Memory: <strong><?php echo htmlspecialchars($service['cpu']); ?>% / <?php echo htmlspecialchars($service['memory']); ?>%</strong></p>
            </div>
            <div class="stat-card">
                <h2>Current Counters</h2>
                <p>Accepts: <strong><?php echo (int) $currentSnapshot['accepts']; ?></strong></p>
                <p>Rejects: <strong><?php echo (int) $currentSnapshot['rejects']; ?></strong></p>
                <p>Invalid Users: <strong><?php echo (int) $currentSnapshot['invalids']; ?></strong></p>
            </div>
            <div class="stat-card">
                <h2>Collection</h2>
                <p>Samples: <strong><?php echo $sampleCount; ?></strong></p>
                <p>Latest Sample: <strong><?php echo htmlspecialchars($lastSample['timestamp']); ?></strong></p>
                <p>Interval: <strong>10 minutes</strong></p>
            </div>
        </div>

        <?php if (!is_readable($logPath)): ?>
            <div class="alert alert-warning">
                FreeRADIUS log file is not readable at <code><?php echo htmlspecialchars($logPath); ?></code>.
            </div>
        <?php endif; ?>

        <?php if (!is_readable($statsPath)): ?>
            <div class="alert info-banner">
                CSV history is not available yet. Add the cron entry below to collect graph samples every 10 minutes.
            </div>
        <?php endif; ?>

        <section class="chart-card chart-card--wide radius-monitor-chart-card">
            <div class="chart-header">
                <div>
                    <span class="chart-eyebrow">Authentication Activity</span>
                    <h2>Accepts, Rejects, Invalid Users</h2>
                </div>
            </div>
            <canvas id="radiusAuthGraph"></canvas>
            <div class="chart-summary radius-monitor-summary">
                <span class="chart-summary-item"><span>Source</span><strong><?php echo htmlspecialchars($logPath); ?></strong></span>
                <span class="chart-summary-item"><span>CSV</span><strong><?php echo htmlspecialchars($statsPath); ?></strong></span>
            </div>
        </section>

        <section class="dashboard-grid radius-monitor-detail-grid">
            <article class="table-card">
                <div class="table-card-heading">
                    <h2>Collector Cron</h2>
                </div>
                <pre class="cron-snippet"><?php echo htmlspecialchars($cronCommand); ?></pre>
            </article>
            <article class="table-card">
                <div class="table-card-heading">
                    <h2>Recent Events</h2>
                </div>
                <pre class="log-viewer radius-monitor-recent"><?php echo htmlspecialchars(implode(PHP_EOL, $recentLines)); ?></pre>
            </article>
        </section>
    </main>

    <?php include dirname(__DIR__, 2) . '/template_parts/footer.php'; ?>
    <script>
        (function () {
            const payload = <?php echo json_encode($chartPayload, JSON_UNESCAPED_SLASHES); ?>;
            const canvas = document.getElementById('radiusAuthGraph');

            if (!canvas || !window.Chart) return;

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: payload.labels,
                    datasets: [
                        {
                            label: 'Accepts',
                            data: payload.accepts,
                            borderColor: '#0e9f8a',
                            backgroundColor: 'rgba(14, 159, 138, 0.12)',
                            tension: 0.28,
                            fill: true
                        },
                        {
                            label: 'Rejects',
                            data: payload.rejects,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.1)',
                            tension: 0.28,
                            fill: true
                        },
                        {
                            label: 'Invalid users',
                            data: payload.invalids,
                            borderColor: '#0b5cab',
                            backgroundColor: 'rgba(11, 92, 171, 0.1)',
                            tension: 0.28,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxTicksLimit: 8
                            },
                            grid: {
                                color: 'rgba(49, 74, 99, 0.12)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(49, 74, 99, 0.12)'
                            }
                        }
                    }
                }
            });
        })();
    </script>
</body>
</html>
