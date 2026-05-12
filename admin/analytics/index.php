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
require_once dirname(__DIR__, 2) . '/includes/guest_accounts.php';
include dirname(__DIR__, 2) . '/db.php';

ensureGuestAccountInfrastructure($pdo);
purgeExpiredGuestAccounts($pdo);

$seo_title = 'Analytics | ' . $site_name;
$seo_description = 'Review eduroam Visitor Access usage, account lifecycle, FreeRADIUS activity, and system capacity.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/admin/analytics/';
$seo_robots = 'noindex,follow';
$seo_type = 'website';

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

function analyticsForwardDailySeries($rows, $days = 14)
{
    $labels = [];
    $values = [];
    $rowMap = [];

    foreach ($rows as $row) {
        $rowMap[$row['period']] = (int) $row['count'];
    }

    for ($i = 0; $i < $days; $i++) {
        $day = date('Y-m-d', strtotime("+$i days"));
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

function analyticsDate($value)
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return $value;
    }

    return date('M j, Y g:i A', $timestamp);
}

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

$guestAccountsExists = $pdo->query("SHOW TABLES LIKE 'guest_accounts'")->rowCount() > 0;
$guestTotal = 0;
$guestActive = 0;
$guestExpired = 0;
$guestPendingExpiry = 0;
$nextGuestExpiry = '-';
$guestDailyChart = ['labels' => [], 'values' => []];
$guestExpiryChart = ['labels' => [], 'values' => []];
$guestStatusChart = ['labels' => ['Active', 'Expired'], 'values' => [0, 0]];

if ($guestAccountsExists) {
    $guestTotal = (int) $pdo->query("SELECT COUNT(*) FROM guest_accounts")->fetchColumn();
    $guestActive = (int) $pdo->query("SELECT COUNT(*) FROM guest_accounts WHERE expires_at > NOW()")->fetchColumn();
    $guestExpired = (int) $pdo->query("SELECT COUNT(*) FROM guest_accounts WHERE expires_at <= NOW()")->fetchColumn();
    $guestPendingExpiry = (int) $pdo->query("SELECT COUNT(*) FROM guest_accounts WHERE expires_at BETWEEN NOW() AND NOW() + INTERVAL 6 HOUR")->fetchColumn();
    $nextGuestExpiryValue = $pdo->query("SELECT MIN(expires_at) FROM guest_accounts WHERE expires_at > NOW()")->fetchColumn();
    $nextGuestExpiry = analyticsDate($nextGuestExpiryValue);
    $guestStatusChart['values'] = [$guestActive, $guestExpired];

    $guestDailyStmt = $pdo->query("SELECT DATE(created_at) AS period, COUNT(*) AS count FROM guest_accounts WHERE created_at >= NOW() - INTERVAL 14 DAY GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
    $guestDailyChart = analyticsDailySeries($guestDailyStmt->fetchAll(PDO::FETCH_ASSOC), 14);

    $guestExpiryStmt = $pdo->query("SELECT DATE(expires_at) AS period, COUNT(*) AS count FROM guest_accounts WHERE expires_at >= CURDATE() AND expires_at < CURDATE() + INTERVAL 14 DAY GROUP BY DATE(expires_at) ORDER BY DATE(expires_at)");
    $guestExpiryChart = analyticsForwardDailySeries($guestExpiryStmt->fetchAll(PDO::FETCH_ASSOC), 14);
}

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
    'guestDaily' => $guestDailyChart,
    'guestExpiry' => $guestExpiryChart,
    'guestStatus' => $guestStatusChart,
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
    <main id="content" class="page-shell analytics-shell">
        <div class="hero-banner">
            <div>
                <span class="eyebrow">Analytics</span>
                <h1>Usage and System Analytics</h1>
                <p class="meta mb-0">Guest lifecycle, FreeRADIUS activity, NAS usage, and system capacity.</p>
            </div>
            <div class="hero-actions">
                <a href="/eduroam/admin/graphs/" class="btn btn-outline-light">
                    <i class="fas fa-chart-area me-2"></i>Monitoring
                </a>
                <a href="/eduroam/admin/" class="btn btn-outline-light">
                    <i class="fas fa-gauge-high me-2"></i>Dashboard
                </a>
                <a href="/eduroam/admin/logs/" class="btn btn-outline-light">
                    <i class="fas fa-file-lines me-2"></i>Logs
                </a>
            </div>
        </div>

        <div class="dashboard-grid analytics-summary-grid">
            <div class="stat-card">
                <h2>Guest Accounts</h2>
                <p>Total Issued: <strong><?php echo $guestTotal; ?></strong></p>
                <p>Active: <strong><?php echo $guestActive; ?></strong></p>
                <p>Expiring Soon: <strong><?php echo $guestPendingExpiry; ?></strong></p>
                <p>Next Expiry: <strong><?php echo htmlspecialchars($nextGuestExpiry); ?></strong></p>
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
            <div class="alert info-banner radius-data-banner">
                No FreeRADIUS SQL authentication or accounting records have been received yet. Authentication and session charts will populate after radpostauth or radacct receives data.
            </div>
        <?php endif; ?>

        <section class="analytics-grid">
            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">Guest Accounts</span>
                        <h2>Requests Over The Last 14 Days</h2>
                    </div>
                </div>
                <canvas id="guestDailyChart"></canvas>
                <div class="chart-summary" id="guestDailySummary"></div>
            </article>

            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">Guest Accounts</span>
                        <h2>Accounts Expiring Next 14 Days</h2>
                    </div>
                </div>
                <canvas id="guestExpiryChart"></canvas>
                <div class="chart-summary" id="guestExpirySummary"></div>
            </article>

            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">Lifecycle</span>
                        <h2>Active vs Expired Accounts</h2>
                    </div>
                </div>
                <canvas id="guestStatusChart"></canvas>
                <div class="chart-summary" id="guestStatusSummary"></div>
            </article>

            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">FreeRADIUS</span>
                        <h2>Authentication Attempts</h2>
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
                <div class="chart-summary" id="systemCapacitySummary"></div>
            </article>

            <article class="chart-card">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">FreeRADIUS</span>
                        <h2>Authentication Outcomes</h2>
                    </div>
                </div>
                <canvas id="authReplyChart"></canvas>
                <div class="chart-summary" id="authReplySummary"></div>
            </article>

            <article class="chart-card chart-card--wide">
                <div class="chart-header">
                    <div>
                        <span class="chart-eyebrow">Network Access Servers</span>
                        <h2>Top NAS By Session Count</h2>
                    </div>
                </div>
                <canvas id="topNasChart"></canvas>
                <div class="chart-summary" id="topNasSummary"></div>
            </article>
        </section>
    </main>

    <?php include dirname(__DIR__, 2) . '/template_parts/footer.php'; ?>
    <script>
        window.analyticsCharts = <?php echo json_encode($chartPayload, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="/eduroam/assets/js/admin-analytics.js"></script>
</body>
</html>
