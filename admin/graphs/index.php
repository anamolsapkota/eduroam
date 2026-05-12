<?php
$admin_page_title = 'Monitoring';
$seo_title = 'Monitoring';
include dirname(__DIR__) . '/includes/admin-shell-header.php';
require_once dirname(__DIR__, 2) . '/includes/radius_monitoring.php';

$statsRows = radiusMonitorReadStats();
$currentSnapshot = radiusMonitorCollectSnapshot();

if (empty($statsRows)) {
    $statsRows[] = $currentSnapshot;
} else {
    $lastStatsRow = end($statsRows);
    $lastStatsTime = strtotime($lastStatsRow['timestamp'] ?? '');
    $currentSnapshotTime = strtotime($currentSnapshot['timestamp']);

    if ($currentSnapshotTime > $lastStatsTime) {
        $statsRows[] = $currentSnapshot;
    }
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

function formatMonitorBytes($bytes)
{
    $bytes = max(0, (float) $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unitIndex = 0;

    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }

    return number_format($bytes, $unitIndex === 0 ? 0 : 2) . ' ' . $units[$unitIndex];
}

function formatMonitorDuration($seconds)
{
    $seconds = max(0, (int) $seconds);
    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);

    if ($days > 0) {
        return $days . 'd ' . $hours . 'h';
    }

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    return $minutes . 'm';
}

$activeSessionCondition = "(acctstoptime IS NULL OR acctstoptime = '0000-00-00 00:00:00')";

$accountingStats = $pdo->query(
    "SELECT
        COUNT(*) AS total_sessions,
        COALESCE(SUM(CASE WHEN $activeSessionCondition THEN 1 ELSE 0 END), 0) AS active_sessions,
        COUNT(DISTINCT NULLIF(username, '')) AS unique_users,
        COUNT(DISTINCT NULLIF(nasipaddress, '')) AS nas_count,
        COALESCE(SUM(acctinputoctets), 0) AS input_octets,
        COALESCE(SUM(acctoutputoctets), 0) AS output_octets,
        COALESCE(SUM(acctsessiontime), 0) AS session_seconds,
        COALESCE(AVG(NULLIF(acctsessiontime, 0)), 0) AS average_session_seconds
     FROM radacct"
)->fetch(PDO::FETCH_ASSOC);

$inputOctets = (int) ($accountingStats['input_octets'] ?? 0);
$outputOctets = (int) ($accountingStats['output_octets'] ?? 0);
$totalOctets = $inputOctets + $outputOctets;

// --- Time-range bandwidth queries ---
$timeRanges = [
    '10min' => ['interval' => '100 MINUTE', 'group' => "DATE_FORMAT(acctstarttime, '%Y-%m-%d %H:%i')", 'format' => '%H:%i', 'label' => 'Last 100 Minutes'],
    'hourly' => ['interval' => '24 HOUR', 'group' => "DATE_FORMAT(acctstarttime, '%Y-%m-%d %H:00')", 'format' => '%H:00', 'label' => 'Last 24 Hours'],
    'daily' => ['interval' => '14 DAY', 'group' => "DATE(acctstarttime)", 'format' => '%b %e', 'label' => 'Last 14 Days'],
    'monthly' => ['interval' => '12 MONTH', 'group' => "DATE_FORMAT(acctstarttime, '%Y-%m')", 'format' => '%b %Y', 'label' => 'Last 12 Months'],
    'yearly' => ['interval' => '5 YEAR', 'group' => "YEAR(acctstarttime)", 'format' => '%Y', 'label' => 'Last 5 Years'],
];

$bandwidthByRange = [];
foreach ($timeRanges as $key => $range) {
    $rows = $pdo->query(
        "SELECT {$range['group']} AS period,
            COALESCE(SUM(acctinputoctets), 0) AS input_octets,
            COALESCE(SUM(acctoutputoctets), 0) AS output_octets,
            COUNT(*) AS session_count,
            COUNT(DISTINCT NULLIF(username, '')) AS unique_users
         FROM radacct
         WHERE acctstarttime >= NOW() - INTERVAL {$range['interval']}
         GROUP BY {$range['group']}
         ORDER BY {$range['group']}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $inputData = [];
    $outputData = [];
    $sessionData = [];
    $userData = [];

    foreach ($rows as $row) {
        $ts = strtotime($row['period']);
        if ($ts) {
            $labels[] = date($range['format'] === '%H:%i' ? 'H:i' : ($range['format'] === '%H:00' ? 'H:00' : ($range['format'] === '%b %e' ? 'M j' : ($range['format'] === '%b %Y' ? 'M Y' : 'Y'))), $ts);
        } else {
            $labels[] = $row['period'];
        }
        $inputData[] = round(((int) $row['input_octets']) / 1048576, 2);
        $outputData[] = round(((int) $row['output_octets']) / 1048576, 2);
        $sessionData[] = (int) $row['session_count'];
        $userData[] = (int) $row['unique_users'];
    }

    $bandwidthByRange[$key] = [
        'labels' => $labels,
        'input' => $inputData,
        'output' => $outputData,
        'sessions' => $sessionData,
        'users' => $userData,
        'title' => $range['label'],
    ];
}

// --- Time-range auth queries (radpostauth) ---
$authByRange = [];
foreach ($timeRanges as $key => $range) {
    $groupCol = str_replace('acctstarttime', 'authdate', $range['group']);
    $rows = $pdo->query(
        "SELECT {$groupCol} AS period,
            COALESCE(SUM(CASE WHEN reply = 'Access-Accept' THEN 1 ELSE 0 END), 0) AS accepts,
            COALESCE(SUM(CASE WHEN reply = 'Access-Reject' THEN 1 ELSE 0 END), 0) AS rejects,
            COALESCE(SUM(CASE WHEN reply NOT IN ('Access-Accept','Access-Reject') THEN 1 ELSE 0 END), 0) AS other,
            COUNT(*) AS total
         FROM radpostauth
         WHERE authdate >= NOW() - INTERVAL {$range['interval']}
         GROUP BY {$groupCol}
         ORDER BY {$groupCol}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $acceptData = [];
    $rejectData = [];
    $otherData = [];
    $totalData = [];

    foreach ($rows as $row) {
        $ts = strtotime($row['period']);
        if ($ts) {
            $fmt = $range['format'];
            $phpFmt = $fmt === '%H:%i' ? 'H:i' : ($fmt === '%H:00' ? 'H:00' : ($fmt === '%b %e' ? 'M j' : ($fmt === '%b %Y' ? 'M Y' : 'Y')));
            $labels[] = date($phpFmt, $ts);
        } else {
            $labels[] = $row['period'];
        }
        $acceptData[] = (int) $row['accepts'];
        $rejectData[] = (int) $row['rejects'];
        $otherData[] = (int) $row['other'];
        $totalData[] = (int) $row['total'];
    }

    $authByRange[$key] = [
        'labels' => $labels,
        'accepts' => $acceptData,
        'rejects' => $rejectData,
        'other' => $otherData,
        'total' => $totalData,
        'title' => $range['label'],
    ];
}

// --- Session duration distribution ---
$sessionDistribution = $pdo->query(
    "SELECT
        SUM(CASE WHEN acctsessiontime < 300 THEN 1 ELSE 0 END) AS under_5m,
        SUM(CASE WHEN acctsessiontime >= 300 AND acctsessiontime < 1800 THEN 1 ELSE 0 END) AS m5_30m,
        SUM(CASE WHEN acctsessiontime >= 1800 AND acctsessiontime < 3600 THEN 1 ELSE 0 END) AS m30_1h,
        SUM(CASE WHEN acctsessiontime >= 3600 AND acctsessiontime < 14400 THEN 1 ELSE 0 END) AS h1_4h,
        SUM(CASE WHEN acctsessiontime >= 14400 THEN 1 ELSE 0 END) AS over_4h
     FROM radacct
     WHERE acctsessiontime IS NOT NULL AND acctsessiontime > 0"
)->fetch(PDO::FETCH_ASSOC);

$topUsers = $pdo->query(
    "SELECT COALESCE(NULLIF(username, ''), 'unknown') AS username,
        COUNT(*) AS sessions,
        COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) AS total_octets
     FROM radacct
     GROUP BY username
     ORDER BY total_octets DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$topNas = $pdo->query(
    "SELECT COALESCE(NULLIF(nasipaddress, ''), 'unknown') AS nasipaddress,
        COUNT(*) AS sessions,
        COALESCE(SUM(acctinputoctets + acctoutputoctets), 0) AS total_octets
     FROM radacct
     GROUP BY nasipaddress
     ORDER BY total_octets DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$fullPayload = [
    'authMonitor' => $chartPayload,
    'bandwidthByRange' => $bandwidthByRange,
    'authByRange' => $authByRange,
    'sessionDistribution' => [
        'labels' => ['< 5 min', '5-30 min', '30m-1h', '1-4 hours', '> 4 hours'],
        'values' => [
            (int) ($sessionDistribution['under_5m'] ?? 0),
            (int) ($sessionDistribution['m5_30m'] ?? 0),
            (int) ($sessionDistribution['m30_1h'] ?? 0),
            (int) ($sessionDistribution['h1_4h'] ?? 0),
            (int) ($sessionDistribution['over_4h'] ?? 0),
        ],
    ],
];
?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <style>
        .range-tabs {
            display: flex;
            gap: 0;
            border: 1px solid rgba(49, 74, 99, 0.18);
            border-radius: 8px;
            overflow: hidden;
            background: #f4f7fb;
        }
        .range-tab {
            padding: 0.5rem 0.9rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            color: #314a63;
            background: transparent;
            border: none;
            border-right: 1px solid rgba(49, 74, 99, 0.12);
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .range-tab:last-child { border-right: none; }
        .range-tab:hover { background: rgba(11, 92, 171, 0.08); }
        .range-tab.active {
            background: #0d3b6f;
            color: #fff;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .chart-header .range-tabs {
            flex-shrink: 0;
        }
        .monitoring-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .monitoring-grid .chart-card canvas {
            max-height: 350px;
        }
        @media (max-width: 767.98px) {
            .range-tabs {
                flex-wrap: wrap;
            }
            .range-tab {
                flex: 1;
                text-align: center;
                min-width: 0;
            }
        }
    </style>

        <div class="admin-page-header">
            <div>
                <h1>Authentication &amp; Traffic Graphs</h1>
                <p>Multi-resolution views: 10-minute, hourly, daily, monthly, yearly.</p>
            </div>
            <div class="admin-page-actions">
                <a href="/eduroam/admin/logs/" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-lines me-2"></i>Logs
                </a>
                <a href="/eduroam/admin/analytics/" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </a>
            </div>
        </div>

        <section class="table-card radius-monitor-cron-card">
            <div class="table-card-heading">
                <div>
                    <span class="chart-eyebrow">Collector Cron</span>
                    <h2>Collect graph samples every 10 minutes</h2>
                </div>
                <button type="button" class="btn btn-outline-secondary" id="copyCronCommand">
                    <i class="fas fa-copy me-2"></i>Copy
                </button>
            </div>
            <pre class="cron-snippet" id="collectorCronCommand"><?php echo htmlspecialchars($cronCommand); ?></pre>
        </section>

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

        <div class="dashboard-grid radius-monitor-stats">
            <div class="stat-card">
                <h2>Bandwidth</h2>
                <p>Total Used: <strong><?php echo htmlspecialchars(formatMonitorBytes($totalOctets)); ?></strong></p>
                <p>Downloaded: <strong><?php echo htmlspecialchars(formatMonitorBytes($outputOctets)); ?></strong></p>
                <p>Uploaded: <strong><?php echo htmlspecialchars(formatMonitorBytes($inputOctets)); ?></strong></p>
            </div>
            <div class="stat-card">
                <h2>Sessions</h2>
                <p>Total Sessions: <strong><?php echo (int) ($accountingStats['total_sessions'] ?? 0); ?></strong></p>
                <p>Active Sessions: <strong><?php echo (int) ($accountingStats['active_sessions'] ?? 0); ?></strong></p>
                <p>Average Duration: <strong><?php echo htmlspecialchars(formatMonitorDuration($accountingStats['average_session_seconds'] ?? 0)); ?></strong></p>
            </div>
            <div class="stat-card">
                <h2>Accounting Scope</h2>
                <p>Unique Users: <strong><?php echo (int) ($accountingStats['unique_users'] ?? 0); ?></strong></p>
                <p>NAS Seen: <strong><?php echo (int) ($accountingStats['nas_count'] ?? 0); ?></strong></p>
                <p>Total Time: <strong><?php echo htmlspecialchars(formatMonitorDuration($accountingStats['session_seconds'] ?? 0)); ?></strong></p>
            </div>
        </div>

        <?php if (!is_readable($logPath)): ?>
            <div class="alert alert-warning">
                FreeRADIUS log file is not readable at <code><?php echo htmlspecialchars($logPath); ?></code>.
            </div>
        <?php endif; ?>

        <?php if (!is_readable($statsPath)): ?>
            <div class="alert info-banner">
                CSV history is not available yet. Add the cron entry above to collect graph samples every 10 minutes.
            </div>
        <?php endif; ?>

        <!-- Auth Monitor from radius.log (cumulative deltas) -->
        <section class="chart-card chart-card--wide radius-monitor-chart-card">
            <div class="chart-header">
                <div>
                    <span class="chart-eyebrow">Radius.log Monitor</span>
                    <h2>Accepts, Rejects, Invalid Users Per Sample</h2>
                </div>
            </div>
            <canvas id="radiusAuthGraph"></canvas>
            <div class="chart-summary radius-monitor-summary">
                <span class="chart-summary-item"><span>Source</span><strong><?php echo htmlspecialchars($logPath); ?></strong></span>
                <span class="chart-summary-item"><span>CSV</span><strong><?php echo htmlspecialchars($statsPath); ?></strong></span>
            </div>
        </section>

        <!-- Auth Activity from radpostauth with time range selector -->
        <div class="monitoring-grid">
            <section class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">Authentication Activity</span>
                        <h2>Accept / Reject / Other</h2>
                    </div>
                    <div class="range-tabs" data-chart="authRange">
                        <button class="range-tab active" data-range="10min">10 Min</button>
                        <button class="range-tab" data-range="hourly">Hourly</button>
                        <button class="range-tab" data-range="daily">Daily</button>
                        <button class="range-tab" data-range="monthly">Monthly</button>
                        <button class="range-tab" data-range="yearly">Yearly</button>
                    </div>
                </div>
                <canvas id="authRangeChart"></canvas>
                <div class="chart-summary" id="authRangeSummary"></div>
            </section>
        </div>

        <!-- Bandwidth with time range selector -->
        <div class="monitoring-grid">
            <section class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">Network Traffic</span>
                        <h2>Upload / Download Bandwidth</h2>
                    </div>
                    <div class="range-tabs" data-chart="bwRange">
                        <button class="range-tab active" data-range="10min">10 Min</button>
                        <button class="range-tab" data-range="hourly">Hourly</button>
                        <button class="range-tab" data-range="daily">Daily</button>
                        <button class="range-tab" data-range="monthly">Monthly</button>
                        <button class="range-tab" data-range="yearly">Yearly</button>
                    </div>
                </div>
                <canvas id="bwRangeChart"></canvas>
                <div class="chart-summary" id="bwRangeSummary"></div>
            </section>
        </div>

        <!-- Session Distribution -->
        <div class="monitoring-grid">
            <section class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">Session Analysis</span>
                        <h2>Session Duration Distribution</h2>
                    </div>
                </div>
                <canvas id="sessionDistChart"></canvas>
            </section>
        </div>

        <section class="dashboard-grid radius-monitor-accounting-grid">
            <article class="table-card">
                <div class="table-card-heading">
                    <h2>Top Users By Bandwidth</h2>
                </div>
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Sessions</th>
                            <th>Bandwidth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topUsers)): ?>
                            <tr><td colspan="3" class="text-muted text-center">No accounting data yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($topUsers as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo (int) $row['sessions']; ?></td>
                                    <td><?php echo htmlspecialchars(formatMonitorBytes($row['total_octets'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </article>
            <article class="table-card">
                <div class="table-card-heading">
                    <h2>Top NAS By Bandwidth</h2>
                </div>
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>NAS</th>
                            <th>Sessions</th>
                            <th>Bandwidth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topNas)): ?>
                            <tr><td colspan="3" class="text-muted text-center">No accounting data yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($topNas as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nasipaddress']); ?></td>
                                    <td><?php echo (int) $row['sessions']; ?></td>
                                    <td><?php echo htmlspecialchars(formatMonitorBytes($row['total_octets'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </article>
        </section>

        <section class="table-card radius-monitor-events-card">
            <div class="table-card-heading">
                <h2>Recent Events</h2>
                <a href="/eduroam/admin/logs/" class="btn btn-outline-secondary">
                    <i class="fas fa-file-lines me-2"></i>Open Logs
                </a>
            </div>
            <pre class="log-viewer radius-monitor-recent"><?php echo htmlspecialchars(implode(PHP_EOL, $recentLines)); ?></pre>
        </section>

    <script>
    (function () {
        var payload = <?php echo json_encode($fullPayload, JSON_UNESCAPED_SLASHES); ?>;
        var copyButton = document.getElementById('copyCronCommand');
        var cronCommand = document.getElementById('collectorCronCommand');

        if (copyButton && cronCommand && navigator.clipboard) {
            copyButton.addEventListener('click', function () {
                navigator.clipboard.writeText(cronCommand.textContent.trim()).then(function () {
                    copyButton.innerHTML = '<i class="fas fa-check me-2"></i>Copied';
                    setTimeout(function () {
                        copyButton.innerHTML = '<i class="fas fa-copy me-2"></i>Copy';
                    }, 1600);
                });
            });
        }

        if (!window.Chart) return;

        // --- Professional defaults ---
        Chart.defaults.color = '#314a63';
        Chart.defaults.font.family = 'Sora, sans-serif';
        Chart.defaults.font.weight = '500';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 16;
        Chart.defaults.animation = { duration: 400 };
        Chart.defaults.datasets.line.pointRadius = 2;
        Chart.defaults.datasets.line.pointHoverRadius = 4;

        var palette = {
            green: '#10b981',
            greenBg: 'rgba(16, 185, 129, 0.12)',
            red: '#ef4444',
            redBg: 'rgba(239, 68, 68, 0.10)',
            blue: '#3b82f6',
            blueBg: 'rgba(59, 130, 246, 0.10)',
            teal: '#0e9f8a',
            tealBg: 'rgba(14, 159, 138, 0.12)',
            navy: '#0d3b6f',
            navyBg: 'rgba(13, 59, 111, 0.10)',
            amber: '#f59e0b',
            amberBg: 'rgba(245, 158, 11, 0.10)',
            purple: '#8b5cf6',
            purpleBg: 'rgba(139, 92, 246, 0.10)',
            slate: '#64748b',
            slateBg: 'rgba(100, 116, 139, 0.10)',
        };

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function tooltipTitle(items) {
            return items[0].label || '';
        }

        function professionalTooltip(extra) {
            return {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#e2e8f0',
                bodyColor: '#cbd5e1',
                borderColor: 'rgba(148, 163, 184, 0.3)',
                borderWidth: 1,
                padding: { top: 12, right: 16, bottom: 12, left: 16 },
                cornerRadius: 8,
                titleFont: { size: 13, weight: '700', family: 'Sora, sans-serif' },
                bodyFont: { size: 12, weight: '500', family: 'Sora, sans-serif' },
                bodySpacing: 6,
                boxPadding: 6,
                usePointStyle: true,
                callbacks: Object.assign({ title: tooltipTitle }, extra || {}),
            };
        }

        function gridStyle() {
            return {
                color: 'rgba(49, 74, 99, 0.08)',
                drawBorder: false,
                tickLength: 0,
            };
        }

        function tickStyle(limit) {
            return {
                color: '#64748b',
                font: { size: 11, weight: '600' },
                padding: 8,
                maxTicksLimit: limit || 10,
                autoSkip: true,
            };
        }

        // ---- Auth Monitor (radius.log cumulative) ----
        var authMonitorCanvas = document.getElementById('radiusAuthGraph');
        if (authMonitorCanvas && payload.authMonitor) {
            var am = payload.authMonitor;
            new Chart(authMonitorCanvas, {
                type: 'line',
                data: {
                    labels: am.labels,
                    datasets: [
                        {
                            label: 'Accepts',
                            data: am.accepts,
                            borderColor: palette.green,
                            backgroundColor: palette.greenBg,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            pointBackgroundColor: palette.green,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Rejects',
                            data: am.rejects,
                            borderColor: palette.red,
                            backgroundColor: palette.redBg,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            pointBackgroundColor: palette.red,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Invalid Users',
                            data: am.invalids,
                            borderColor: palette.blue,
                            backgroundColor: palette.blueBg,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            pointBackgroundColor: palette.blue,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.3,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 16 } },
                        tooltip: professionalTooltip({
                            afterTitle: function (items) {
                                var idx = items[0].dataIndex;
                                var total = (am.accepts[idx] || 0) + (am.rejects[idx] || 0) + (am.invalids[idx] || 0);
                                return 'Total events: ' + total;
                            },
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString();
                            },
                        }),
                    },
                    scales: {
                        x: { grid: gridStyle(), ticks: tickStyle(8) },
                        y: { beginAtZero: true, grid: gridStyle(), ticks: Object.assign(tickStyle(), { precision: 0 }) },
                    },
                },
            });
        }

        // ---- Auth Range Chart (radpostauth with time tabs) ----
        var authRangeCanvas = document.getElementById('authRangeChart');
        var authRangeChart = null;
        var authRangeData = payload.authByRange;

        function buildAuthRange(range) {
            var d = authRangeData[range] || authRangeData['daily'];
            if (authRangeChart) authRangeChart.destroy();

            authRangeChart = new Chart(authRangeCanvas, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        {
                            label: 'Accept',
                            data: d.accepts,
                            backgroundColor: palette.green,
                            borderRadius: 4,
                            barPercentage: 0.7,
                            categoryPercentage: 0.8,
                        },
                        {
                            label: 'Reject',
                            data: d.rejects,
                            backgroundColor: palette.red,
                            borderRadius: 4,
                            barPercentage: 0.7,
                            categoryPercentage: 0.8,
                        },
                        {
                            label: 'Other',
                            data: d.other,
                            backgroundColor: palette.amber,
                            borderRadius: 4,
                            barPercentage: 0.7,
                            categoryPercentage: 0.8,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 16 } },
                        tooltip: professionalTooltip({
                            afterTitle: function (items) {
                                var idx = items[0].dataIndex;
                                return 'Total: ' + (d.total[idx] || 0).toLocaleString() + ' attempts';
                            },
                            label: function (ctx) {
                                var total = d.total[ctx.dataIndex] || 1;
                                var pct = total > 0 ? ((ctx.parsed.y / total) * 100).toFixed(1) : '0.0';
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString() + ' (' + pct + '%)';
                            },
                        }),
                    },
                    scales: {
                        x: { stacked: true, grid: gridStyle(), ticks: tickStyle(12) },
                        y: { stacked: true, beginAtZero: true, grid: gridStyle(), ticks: Object.assign(tickStyle(), { precision: 0 }) },
                    },
                },
            });

            // Update summary
            var summaryEl = document.getElementById('authRangeSummary');
            if (summaryEl) {
                var totalAccepts = d.accepts.reduce(function (a, b) { return a + b; }, 0);
                var totalRejects = d.rejects.reduce(function (a, b) { return a + b; }, 0);
                var totalOther = d.other.reduce(function (a, b) { return a + b; }, 0);
                var grandTotal = totalAccepts + totalRejects + totalOther;
                var successRate = grandTotal > 0 ? ((totalAccepts / grandTotal) * 100).toFixed(1) : '0.0';
                summaryEl.innerHTML =
                    '<span class="chart-summary-item"><span>Period</span><strong>' + d.title + '</strong></span>' +
                    '<span class="chart-summary-item"><span>Accepts</span><strong>' + totalAccepts.toLocaleString() + '</strong></span>' +
                    '<span class="chart-summary-item"><span>Rejects</span><strong>' + totalRejects.toLocaleString() + '</strong></span>' +
                    '<span class="chart-summary-item"><span>Success Rate</span><strong>' + successRate + '%</strong></span>' +
                    '<span class="chart-summary-item"><span>Total</span><strong>' + grandTotal.toLocaleString() + '</strong></span>';
            }
        }

        if (authRangeCanvas && authRangeData) {
            buildAuthRange('10min');
            document.querySelector('.range-tabs[data-chart="authRange"]').addEventListener('click', function (e) {
                var tab = e.target.closest('.range-tab');
                if (!tab) return;
                this.querySelectorAll('.range-tab').forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                buildAuthRange(tab.dataset.range);
            });
        }

        // ---- Bandwidth Range Chart ----
        var bwRangeCanvas = document.getElementById('bwRangeChart');
        var bwRangeChart = null;
        var bwRangeData = payload.bandwidthByRange;

        function buildBwRange(range) {
            var d = bwRangeData[range] || bwRangeData['daily'];
            if (bwRangeChart) bwRangeChart.destroy();

            bwRangeChart = new Chart(bwRangeCanvas, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [
                        {
                            label: 'Upload (MB)',
                            data: d.input,
                            borderColor: palette.teal,
                            backgroundColor: palette.tealBg,
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: palette.teal,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.35,
                            fill: true,
                        },
                        {
                            label: 'Download (MB)',
                            data: d.output,
                            borderColor: palette.navy,
                            backgroundColor: palette.navyBg,
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: palette.navy,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            tension: 0.35,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { labels: { usePointStyle: true, padding: 16 } },
                        tooltip: professionalTooltip({
                            afterTitle: function (items) {
                                var idx = items[0].dataIndex;
                                var totalMb = (d.input[idx] || 0) + (d.output[idx] || 0);
                                return 'Total: ' + formatBytes(totalMb * 1048576) + ' | Sessions: ' + (d.sessions[idx] || 0) + ' | Users: ' + (d.users[idx] || 0);
                            },
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ' + formatBytes(ctx.parsed.y * 1048576);
                            },
                        }),
                    },
                    scales: {
                        x: { grid: gridStyle(), ticks: tickStyle(12) },
                        y: {
                            beginAtZero: true,
                            grid: gridStyle(),
                            ticks: Object.assign(tickStyle(), {
                                callback: function (val) {
                                    if (val >= 1024) return (val / 1024).toFixed(1) + ' GB';
                                    return val + ' MB';
                                },
                            }),
                            title: { display: true, text: 'Traffic', color: '#64748b', font: { size: 11, weight: '700' } },
                        },
                    },
                },
            });

            // Update summary
            var summaryEl = document.getElementById('bwRangeSummary');
            if (summaryEl) {
                var totalUp = d.input.reduce(function (a, b) { return a + b; }, 0);
                var totalDown = d.output.reduce(function (a, b) { return a + b; }, 0);
                var totalSessions = d.sessions.reduce(function (a, b) { return a + b; }, 0);
                summaryEl.innerHTML =
                    '<span class="chart-summary-item"><span>Period</span><strong>' + d.title + '</strong></span>' +
                    '<span class="chart-summary-item"><span>Upload</span><strong>' + formatBytes(totalUp * 1048576) + '</strong></span>' +
                    '<span class="chart-summary-item"><span>Download</span><strong>' + formatBytes(totalDown * 1048576) + '</strong></span>' +
                    '<span class="chart-summary-item"><span>Total</span><strong>' + formatBytes((totalUp + totalDown) * 1048576) + '</strong></span>' +
                    '<span class="chart-summary-item"><span>Sessions</span><strong>' + totalSessions.toLocaleString() + '</strong></span>';
            }
        }

        if (bwRangeCanvas && bwRangeData) {
            buildBwRange('10min');
            document.querySelector('.range-tabs[data-chart="bwRange"]').addEventListener('click', function (e) {
                var tab = e.target.closest('.range-tab');
                if (!tab) return;
                this.querySelectorAll('.range-tab').forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                buildBwRange(tab.dataset.range);
            });
        }

        // ---- Session Distribution Chart ----
        var sessionDistCanvas = document.getElementById('sessionDistChart');
        if (sessionDistCanvas && payload.sessionDistribution) {
            var sd = payload.sessionDistribution;
            var sdTotal = sd.values.reduce(function (a, b) { return a + b; }, 0);
            new Chart(sessionDistCanvas, {
                type: 'doughnut',
                data: {
                    labels: sd.labels,
                    datasets: [{
                        data: sd.values,
                        backgroundColor: [palette.green, palette.blue, palette.amber, palette.purple, palette.red],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverBorderWidth: 3,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { usePointStyle: true, padding: 16, font: { size: 12, weight: '600' } },
                        },
                        tooltip: professionalTooltip({
                            label: function (ctx) {
                                var pct = sdTotal > 0 ? ((ctx.parsed / sdTotal) * 100).toFixed(1) : '0.0';
                                return ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString() + ' sessions (' + pct + '%)';
                            },
                        }),
                    },
                },
            });
        }
    })();
    </script>

<?php include dirname(__DIR__) . '/includes/admin-shell-footer.php'; ?>
