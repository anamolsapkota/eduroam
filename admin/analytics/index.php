<?php
$admin_page_title = 'Analytics';
include dirname(__DIR__) . '/includes/admin-shell-header.php';
include dirname(__DIR__, 2) . '/db.php';

function analyticsCommand($command)
{
    $output = shell_exec($command);
    return trim((string) $output);
}

function analyticsDailySeries($rows, $days = 14)
{
    $labels = [];
    $values = [];
    $rowMap = [];
    foreach ($rows as $row) {
        $rowMap[$row['period']] = (int) $row['count'];
    }
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M j', strtotime($day));
        $values[] = $rowMap[$day] ?? 0;
    }
    return ['labels' => $labels, 'values' => $values];
}

function analyticsHourlySeries($rows)
{
    $labels = [];
    $values = [];
    $rowMap = [];
    foreach ($rows as $row) {
        $rowMap[(int) $row['period']] = (int) $row['count'];
    }
    for ($hour = 0; $hour < 24; $hour++) {
        $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00';
        $values[] = $rowMap[$hour] ?? 0;
    }
    return ['labels' => $labels, 'values' => $values];
}

// System stats
$totalMemory = analyticsCommand("free -h | grep Mem | awk '{print $2}'");
$usedMemory = analyticsCommand("free -h | grep Mem | awk '{print $3}'");
$freeMemory = analyticsCommand("free -h | grep Mem | awk '{print $4}'");
$totalMemoryBytes = (int) analyticsCommand("free -b | grep Mem | awk '{print $2}'");
$usedMemoryBytes = (int) analyticsCommand("free -b | grep Mem | awk '{print $3}'");
$freeMemoryBytes = (int) analyticsCommand("free -b | grep Mem | awk '{print $4}'");

$totalDisk = analyticsCommand("df -h / | awk 'NR==2 {print $2}'");
$freeDisk = analyticsCommand("df -h / | awk 'NR==2 {print $4}'");
$usedDisk = analyticsCommand("df -h / | awk 'NR==2 {print $3}'");
$freeDiskBytes = (int) analyticsCommand("df -B1 / | awk 'NR==2 {print $4}'");
$usedDiskBytes = (int) analyticsCommand("df -B1 / | awk 'NR==2 {print $3}'");

// User stats (regular accounts, no guest/expiry)
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM userinfo u INNER JOIN radcheck r ON u.username = r.username")->fetchColumn();

$userDailyStmt = $pdo->query("SELECT DATE(updatedate) AS period, COUNT(*) AS count FROM userinfo WHERE updatedate >= NOW() - INTERVAL 14 DAY GROUP BY DATE(updatedate) ORDER BY DATE(updatedate)");
$userDailyChart = analyticsDailySeries($userDailyStmt->fetchAll(PDO::FETCH_ASSOC), 14);

// FreeRADIUS auth stats
$authTotalRecords = (int) $pdo->query("SELECT COUNT(*) FROM radpostauth")->fetchColumn();
$sessionTotalRecords = (int) $pdo->query("SELECT COUNT(*) FROM radacct")->fetchColumn();

$authDailyStmt = $pdo->query("SELECT DATE(authdate) AS period, COUNT(*) AS count FROM radpostauth WHERE authdate >= NOW() - INTERVAL 14 DAY GROUP BY DATE(authdate) ORDER BY DATE(authdate)");
$authDailyChart = analyticsDailySeries($authDailyStmt->fetchAll(PDO::FETCH_ASSOC), 14);

$sessionHourlyStmt = $pdo->query("SELECT HOUR(acctstarttime) AS period, COUNT(*) AS count FROM radacct WHERE acctstarttime >= NOW() - INTERVAL 14 DAY GROUP BY HOUR(acctstarttime) ORDER BY HOUR(acctstarttime)");
$sessionHourlyChart = analyticsHourlySeries($sessionHourlyStmt->fetchAll(PDO::FETCH_ASSOC));

$authReplyChart = ['labels' => [], 'values' => []];
$authReplyStmt = $pdo->query("SELECT COALESCE(reply, 'unknown') AS reply, COUNT(*) AS count FROM radpostauth WHERE authdate >= NOW() - INTERVAL 14 DAY GROUP BY reply ORDER BY count DESC");
foreach ($authReplyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $authReplyChart['labels'][] = ucfirst(strtolower($row['reply']));
    $authReplyChart['values'][] = (int) $row['count'];
}
if (empty($authReplyChart['labels'])) {
    $authReplyChart = ['labels' => ['No data'], 'values' => [0]];
}

$topNasChart = ['labels' => [], 'values' => []];
$topNasStmt = $pdo->query("SELECT COALESCE(nasipaddress, 'unknown') AS nasipaddress, COUNT(*) AS count FROM radacct GROUP BY nasipaddress ORDER BY count DESC LIMIT 5");
foreach ($topNasStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $topNasChart['labels'][] = $row['nasipaddress'];
    $topNasChart['values'][] = (int) $row['count'];
}
if (empty($topNasChart['labels'])) {
    $topNasChart = ['labels' => ['No session data'], 'values' => [0]];
}

$chartPayload = [
    'userDaily' => $userDailyChart,
    'authDaily' => $authDailyChart,
    'sessionHourly' => $sessionHourlyChart,
    'authReply' => $authReplyChart,
    'topNas' => $topNasChart,
    'systemCapacity' => [
        'labels' => ['Memory Used', 'Memory Free', 'Disk Used', 'Disk Free'],
        'values' => [
            round($usedMemoryBytes / 1073741824, 2),
            round($freeMemoryBytes / 1073741824, 2),
            round($usedDiskBytes / 1073741824, 2),
            round($freeDiskBytes / 1073741824, 2),
        ],
    ],
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<div class="admin-page-header">
    <div>
        <h1>Usage and System Analytics</h1>
        <p>Authentication activity, session patterns, NAS usage, and system capacity.</p>
    </div>
    <div class="admin-page-actions">
        <a href="/eduroam/admin/graphs/" class="btn btn-primary btn-sm">
            <i class="fas fa-chart-area me-2"></i>Monitoring
        </a>
        <a href="/eduroam/admin/" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-gauge-high me-2"></i>Dashboard
        </a>
    </div>
</div>

<div class="dashboard-grid analytics-summary-grid">
    <div class="stat-card">
        <h2>Users</h2>
        <p>Total Registered: <strong><?php echo $totalUsers; ?></strong></p>
        <p>New (14d): <strong><?php echo array_sum($userDailyChart['values']); ?></strong></p>
    </div>
    <div class="stat-card">
        <h2>FreeRADIUS</h2>
        <p>Auth Attempts (14d): <strong><?php echo array_sum($authDailyChart['values']); ?></strong></p>
        <p>Sessions (14d): <strong><?php echo array_sum($sessionHourlyChart['values']); ?></strong></p>
        <p>Total Auth Records: <strong><?php echo $authTotalRecords; ?></strong></p>
        <p>Total Session Records: <strong><?php echo $sessionTotalRecords; ?></strong></p>
    </div>
    <div class="stat-card">
        <h2>System</h2>
        <p>Memory: <strong><?php echo htmlspecialchars($usedMemory); ?> / <?php echo htmlspecialchars($totalMemory); ?></strong></p>
        <p>Disk: <strong><?php echo htmlspecialchars($usedDisk); ?> / <?php echo htmlspecialchars($totalDisk); ?></strong></p>
        <p>Free Memory: <strong><?php echo htmlspecialchars($freeMemory); ?></strong></p>
        <p>Free Disk: <strong><?php echo htmlspecialchars($freeDisk); ?></strong></p>
    </div>
</div>

<?php if ($authTotalRecords === 0 && $sessionTotalRecords === 0): ?>
    <div class="info-banner">
        No FreeRADIUS SQL authentication or accounting records have been received yet. Charts will populate after radpostauth or radacct receives data.
    </div>
<?php endif; ?>

<section class="analytics-grid">
    <article class="chart-card">
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">Users</span>
                <h2>Registrations Over The Last 14 Days</h2>
            </div>
        </div>
        <canvas id="userDailyChart"></canvas>
        <div class="chart-summary" id="userDailySummary"></div>
    </article>

    <article class="chart-card">
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">FreeRADIUS</span>
                <h2>Authentication Attempts (14 Days)</h2>
            </div>
        </div>
        <canvas id="authDailyChart"></canvas>
        <div class="chart-summary" id="authDailySummary"></div>
    </article>

    <article class="chart-card">
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">FreeRADIUS</span>
                <h2>Sessions By Hour</h2>
            </div>
        </div>
        <canvas id="sessionHourlyChart"></canvas>
        <div class="chart-summary" id="sessionHourlySummary"></div>
    </article>

    <article class="chart-card">
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">System Capacity</span>
                <h2>Memory and Disk Utilization</h2>
            </div>
        </div>
        <canvas id="systemCapacityChart"></canvas>
    </article>

    <article class="chart-card">
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">FreeRADIUS</span>
                <h2>Authentication Outcomes</h2>
            </div>
        </div>
        <canvas id="authReplyChart"></canvas>
    </article>

    <article class="chart-card chart-card--wide">
        <div class="chart-header">
            <div>
                <span class="chart-eyebrow">Network Access Servers</span>
                <h2>Top NAS By Session Count</h2>
            </div>
        </div>
        <canvas id="topNasChart"></canvas>
    </article>
</section>

<script>
    window.analyticsCharts = <?php echo json_encode($chartPayload, JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="/eduroam/assets/js/admin-analytics.js"></script>

<?php include dirname(__DIR__) . '/includes/admin-shell-footer.php'; ?>
