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
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/email.php';
require_once dirname(__DIR__) . '/includes/guest_accounts.php';

?>

<?php
    // Function to calculate uptime in human-readable format
    function calculateUptime($uptimeInSeconds)
    {
        $uptime = "";

        $days = floor($uptimeInSeconds / (3600 * 24));
        $uptimeInSeconds = (int) $uptimeInSeconds;
        $uptimeInSeconds %= (3600 * 24);

        $hours = floor($uptimeInSeconds / 3600);
        $uptimeInSeconds %= 3600;

        $minutes = floor($uptimeInSeconds / 60);

        if ($days > 0) {
            $uptime .= $days . " days ";
        }

        if ($hours > 0) {
            $uptime .= $hours . " hours ";
        }

        $uptime .= $minutes . " minutes";

        return $uptime;
    }
    
    // Function to execute a shell command and capture the output
    function executeCommand($command)
    {
        $output = shell_exec($command);
        return trim($output); // Remove leading/trailing white spaces
    }

    function buildDailySeries($rows, $days = 14)
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

    function buildForwardDailySeries($rows, $days = 14)
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

    function buildHourlySeries($rows)
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

    function formatDashboardDate($value)
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

    function formatAccountExpiry($expiresAt, $radiusExpiresAt = null)
    {
        if ($expiresAt) {
            return formatDashboardDate($expiresAt);
        }

        return $radiusExpiresAt ? formatDashboardDate($radiusExpiresAt) : '-';
    }

    function accountExpiryStatus($expiresAt, $radiusExpiresAt = null)
    {
        if (!$expiresAt && $radiusExpiresAt) {
            $expiresAt = $radiusExpiresAt;
        }

        if (!$expiresAt) {
            return '-';
        }

        $expiryTime = strtotime($expiresAt);

        if (!$expiryTime) {
            return '-';
        }

        $secondsRemaining = $expiryTime - time();

        if ($secondsRemaining <= 0) {
            return 'Expired';
        }

        if ($secondsRemaining < 3600) {
            $minutes = max(1, (int) ceil($secondsRemaining / 60));
            return 'Expires in ' . $minutes . ' min';
        }

        if ($secondsRemaining < 86400) {
            $hours = max(1, (int) ceil($secondsRemaining / 3600));
            return 'Expires in ' . $hours . ' hr';
        }

        $days = max(1, (int) ceil($secondsRemaining / 86400));
        return 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's');
    }

    include dirname(__DIR__) . '/db.php';
    ensureGuestAccountInfrastructure($pdo);
    purgeExpiredGuestAccounts($pdo);

    $manualCreateAlert = null;
    $manualCreateResult = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['manual_create_user'])) {
        $manualFullname = trim($_POST['manual_fullname'] ?? '');
        $manualEmail = trim($_POST['manual_email'] ?? '');
        $manualSendEmail = true;
        $updatedBy = $_SESSION['user']['username'] ?? 'admin_manual';

        try {
            $manualCreateResult = createGuestAccount($pdo, $manualFullname, $manualEmail, $updatedBy);

            if ($manualSendEmail) {
                $subject = 'Eduroam Access Information';
                $message = buildGuestCredentialEmail(
                    $manualCreateResult['fullname'],
                    $manualCreateResult['username'],
                    $manualCreateResult['password'],
                    $manualCreateResult['expires_at_display'],
                    $site_baseurl,
                    $site_name
                );

                $emailResult = sendEmail($manualEmail, $manualFullname, $subject, $message);

                if (!$emailResult['success']) {
                    $pdo->prepare("DELETE FROM radcheck WHERE username = :username")->execute([':username' => $manualCreateResult['username']]);
                    $pdo->prepare("DELETE FROM userinfo WHERE username = :username")->execute([':username' => $manualCreateResult['username']]);
                    $pdo->prepare("DELETE FROM guest_accounts WHERE username = :username")->execute([':username' => $manualCreateResult['username']]);
                    throw new RuntimeException('Account was not saved because the credential email failed: ' . $emailResult['error']);
                }
            }

            $manualCreateAlert = [
                'type' => 'success',
                'message' => 'Guest account created successfully and the credentials email was sent.'
            ];
        } catch (InvalidArgumentException | RuntimeException $e) {
            $manualCreateAlert = [
                'type' => 'danger',
                'message' => $e->getMessage(),
            ];
            $manualCreateResult = null;
        } catch (Throwable $e) {
            error_log('Manual guest account creation failed: ' . $e->getMessage());
            $manualCreateAlert = [
                'type' => 'danger',
                'message' => 'Manual account creation failed. Please try again.',
            ];
            $manualCreateResult = null;
        }
    }

    // Total Users (count from radcheck table)
    $queryTotalUsers = "SELECT COUNT(*) AS totalUsers FROM userinfo u INNER JOIN radcheck r ON u.username = r.username AND r.attribute = 'Cleartext-Password';";
    $stmtTotalUsers = $pdo->prepare($queryTotalUsers);
    $stmtTotalUsers->execute();
    $rowTotalUsers = $stmtTotalUsers->fetch(PDO::FETCH_ASSOC);
    $totalUsers = $rowTotalUsers['totalUsers'];

    // Active Users (assuming active users are not banned)
    $activeUsers = $totalUsers;
    $bannedUsers = 0;

    // Server Status
    $date = date("F j, Y (l)");

    // Get hostname and uptime information using shell commands
    $hostname = executeCommand("hostname");
    $uptimeInSeconds = executeCommand("cat /proc/uptime | awk '{print $1}'");
    $uptime = calculateUptime($uptimeInSeconds);

    // Get memory and storage information using shell commands
    $totalMemory = executeCommand("free -h | grep Mem | awk '{print $2}'");
    $usedMemory = executeCommand("free -h | grep Mem | awk '{print $3}'");
    $freeMemory = executeCommand("free -h | grep Mem | awk '{print $4}'");
    $totalMemoryBytes = (int) executeCommand("free -b | grep Mem | awk '{print $2}'");
    $usedMemoryBytes = (int) executeCommand("free -b | grep Mem | awk '{print $3}'");
    $freeMemoryBytes = (int) executeCommand("free -b | grep Mem | awk '{print $4}'");

    $totalDisk = executeCommand("df -h / | awk 'NR==2 {print $2}'");
    $freeDisk = executeCommand("df -h / | awk 'NR==2 {print $4}'");
    $usedDisk = executeCommand("df -h / | awk 'NR==2 {print $3}'");
    $totalDiskBytes = (int) executeCommand("df -B1 / | awk 'NR==2 {print $2}'");
    $freeDiskBytes = (int) executeCommand("df -B1 / | awk 'NR==2 {print $4}'");
    $usedDiskBytes = (int) executeCommand("df -B1 / | awk 'NR==2 {print $3}'");

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
        $nextGuestExpiry = formatDashboardDate($nextGuestExpiryValue);
        $guestStatusChart['values'] = [$guestActive, $guestExpired];

        $guestDailyStmt = $pdo->query("SELECT DATE(created_at) AS period, COUNT(*) AS count FROM guest_accounts WHERE created_at >= NOW() - INTERVAL 14 DAY GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
        $guestDailyChart = buildDailySeries($guestDailyStmt->fetchAll(PDO::FETCH_ASSOC), 14);

        $guestExpiryStmt = $pdo->query("SELECT DATE(expires_at) AS period, COUNT(*) AS count FROM guest_accounts WHERE expires_at >= CURDATE() AND expires_at < CURDATE() + INTERVAL 14 DAY GROUP BY DATE(expires_at) ORDER BY DATE(expires_at)");
        $guestExpiryChart = buildForwardDailySeries($guestExpiryStmt->fetchAll(PDO::FETCH_ASSOC), 14);
    }

    $authTotalRecords = (int) $pdo->query("SELECT COUNT(*) FROM radpostauth")->fetchColumn();
    $sessionTotalRecords = (int) $pdo->query("SELECT COUNT(*) FROM radacct")->fetchColumn();

    $authDailyStmt = $pdo->query("SELECT DATE(authdate) AS period, COUNT(*) AS count FROM radpostauth WHERE authdate >= NOW() - INTERVAL 14 DAY GROUP BY DATE(authdate) ORDER BY DATE(authdate)");
    $authDailyChart = buildDailySeries($authDailyStmt->fetchAll(PDO::FETCH_ASSOC), 14);

    $sessionHourlyStmt = $pdo->query("SELECT HOUR(acctstarttime) AS period, COUNT(*) AS count FROM radacct WHERE acctstarttime >= NOW() - INTERVAL 14 DAY GROUP BY HOUR(acctstarttime) ORDER BY HOUR(acctstarttime)");
    $sessionHourlyChart = buildHourlySeries($sessionHourlyStmt->fetchAll(PDO::FETCH_ASSOC));

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
    <title><?php echo $site_name; ?> Management</title>
    <link rel="stylesheet" href="/eduroam/assets/css/styles.css">
    <?php include dirname(__DIR__) . '/template_parts/head.php'; ?>
    <script>
        function deleteUser(username) {
            if (confirm('Are you sure you want to delete this user?')) {
                // Make an AJAX request to delete_user.php
                $.ajax({
                    type: 'POST',
                    url: '/eduroam/admin/delete_user.php',
                    data: {
                        username: username // Pass the username as a parameter
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            // User deleted successfully, you can update the UI or show a message
                            alert(response.message);
                            // Reload the page or update the user list
                            location.reload();
                        } else {
                            // Handle error case, show an error message
                            alert(response.message);
                        }
                    },
                    error: function () {
                        // Handle AJAX error
                        alert('An error occurred while deleting the user.');
                    }
                });
            }
        }

    </script>
</head>
<body class="app-shell">
    <?php include dirname(__DIR__) . '/template_parts/nav.php'; ?>
    <main id="content" class="page-shell">
        <?php 
        $dateTime = date("F j, Y, g:i a");
        ?>

<div class="hero-banner">
    <div>
        <span class="eyebrow">Dashboard</span>
        <h1><?php echo htmlspecialchars($site_name); ?> Management</h1>
        <p class="meta mb-0">Current server time: <?php echo htmlspecialchars($dateTime); ?></p>
    </div>
    <div class="hero-actions">
        <a href="/eduroam/admin/analytics/" class="btn btn-outline-light">
            <i class="fas fa-chart-line me-2"></i>Analytics
        </a>
        <a href="/eduroam/admin/logs/" class="btn btn-outline-light">
            <i class="fas fa-file-lines me-2"></i>Logs
        </a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="stat-card">
        <h2>Eduroam Users</h2>
        <p>Total Users: <strong><?php echo $totalUsers; ?></strong></p>
        <p>Active Users: <strong><?php echo $activeUsers; ?></strong></p>
        <p>Banned Users: <strong><?php echo $bannedUsers; ?></strong></p>
    </div>

    <div class="stat-card">
        <h2>Server</h2>
        <p>Date: <strong><?php echo $date; ?></strong></p>
        <p>Hostname: <strong><?php echo $hostname; ?></strong></p>
        <p>Uptime: <strong><?php echo $uptime; ?></strong></p>
    </div>

    <div class="stat-card">
        <h2>Memory</h2>
        <p>Total Memory: <strong><?php echo $totalMemory; ?></strong></p>
        <p>Free Memory: <strong><?php echo $freeMemory; ?></strong></p>
        <p>Used Memory: <strong><?php echo $usedMemory; ?></strong></p>
    </div>

    <div class="stat-card">
        <h2>Storage</h2>
        <p>Total Disk: <strong><?php echo $totalDisk; ?></strong></p>
        <p>Free Disk: <strong><?php echo $freeDisk; ?></strong></p>
        <p>Used Disk: <strong><?php echo $usedDisk; ?></strong></p>
    </div>
</div>

<div class="dashboard-grid dashboard-grid--secondary">
    <div class="stat-card">
        <h2>Guest Accounts</h2>
        <p>Total Issued: <strong><?php echo $guestTotal; ?></strong></p>
        <p>Currently Active: <strong><?php echo $guestActive; ?></strong></p>
        <p>Expired Records: <strong><?php echo $guestExpired; ?></strong></p>
        <p>Expiring Soon (6h): <strong><?php echo $guestPendingExpiry; ?></strong></p>
        <p>Next Expiry: <strong><?php echo htmlspecialchars($nextGuestExpiry); ?></strong></p>
    </div>

    <div class="stat-card">
        <h2>Radius Activity</h2>
        <p>Auth Attempts (14d): <strong><?php echo array_sum($authDailyChart['values']); ?></strong></p>
        <p>Recorded Sessions (14d): <strong><?php echo array_sum($sessionHourlyChart['values']); ?></strong></p>
        <p>Total Auth Records: <strong><?php echo $authTotalRecords; ?></strong></p>
        <p>Total Session Records: <strong><?php echo $sessionTotalRecords; ?></strong></p>
    </div>
</div>

        <div class="toolbar-card">
            <div class="toolbar-grid">
                <div>
                    <form action="import.php" method="post" enctype="multipart/form-data" class="upload-form">
                        <input type="file" name="upcsv" accept=".csv" required="">
                        <div class="toolbar-actions">
                            <input type="submit" value="Upload" class="btn btn-primary">
                            <a href="sample.csv" class="btn btn-outline-secondary" type="clear">Download Sample</a>
                        </div>
                    </form>
                </div>
                <div>
                    <form action="/eduroam/admin/users/" method="GET" class="search-form">
                        <div class="search-row">
                            <input type="text" name="search" required="" value="<?php if(isset($_GET['search'])){ echo htmlspecialchars($_GET['search']);} ?>" class="form-control" placeholder="Search users by name or username">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="/eduroam/admin/users/" class="btn btn-warning" type="clear">Users</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($manualCreateAlert): ?>
            <div class="alert alert-<?php echo htmlspecialchars($manualCreateAlert['type']); ?>" role="alert">
                <?php echo htmlspecialchars($manualCreateAlert['message']); ?>
            </div>
        <?php endif; ?>

        <section class="glass-panel manual-create-panel">
            <div class="chart-header">
                <div>
                    <span class="chart-eyebrow">Admin Action</span>
                    <h2>Create Guest Account Manually</h2>
                </div>
            </div>
            <p class="auth-subtitle">Issue a <?php echo htmlspecialchars(guestAccountDurationLabel()); ?> guest account directly from the dashboard. Credentials are emailed to the guest and are not displayed in the admin interface.</p>
            <form method="POST" action="" class="manual-create-form">
                <input type="hidden" name="manual_create_user" value="1">
                <div class="manual-create-grid">
                    <div class="mb-3">
                        <label for="manual_fullname" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="manual_fullname" name="manual_fullname" value="<?php echo htmlspecialchars($_POST['manual_fullname'] ?? ''); ?>" placeholder="Guest full name" required>
                    </div>
                    <div class="mb-3">
                        <label for="manual_email" class="form-label">Delivery Email</label>
                        <input type="email" class="form-control" id="manual_email" name="manual_email" value="<?php echo htmlspecialchars($_POST['manual_email'] ?? ''); ?>" placeholder="guest@example.com" required>
                    </div>
                </div>
                <div class="toolbar-actions">
                    <button type="submit" class="btn btn-primary">Create Guest Account</button>
                </div>
            </form>

            <?php if ($manualCreateResult): ?>
                <div class="manual-result-card">
                    <h3>Latest manual account</h3>
                    <div class="manual-result-grid">
                        <div><span>Username</span><strong><?php echo htmlspecialchars($manualCreateResult['username']); ?></strong></div>
                        <div><span>Email</span><strong><?php echo htmlspecialchars($manualCreateResult['delivery_email']); ?></strong></div>
                        <div><span>Expires At</span><strong><?php echo htmlspecialchars($manualCreateResult['expires_at_display']); ?></strong></div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="analytics-teaser">
            <div>
                <span class="chart-eyebrow">Analytics</span>
                <h2>Charts now live on the analytics page</h2>
                <p>Review guest requests, account expiry, FreeRADIUS activity, NAS usage, and system capacity in one focused view.</p>
            </div>
            <a href="/eduroam/admin/analytics/" class="btn btn-primary">
                <i class="fas fa-chart-line me-2"></i>Open Analytics
            </a>
        </section>

        <?php
        if ($_SERVER['PHP_AUTH_USER'] === $authUser && $_SERVER['PHP_AUTH_PW'] === $authPass) {
            // Make sure both database connections are available
            if (!isset($pdo) || !isset($conn)) {
                include 'db.php';
            }

            if (isset($_GET['search'])) {
                $filtervalues = $_GET['search'];
            
                // Pagination variables
                $records_per_page = 10;
                $page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
                $offset = ($page - 1) * $records_per_page;
            
                // Prepare the SQL query with pagination
                $query = "SELECT userinfo.username, userinfo.fullname, userinfo.email, userinfo.updateby, userinfo.updatedate, guest_accounts.created_at AS requested_at, guest_accounts.expires_at, expiry_check.value AS radius_expires_at FROM userinfo INNER JOIN radcheck password_check ON userinfo.username = password_check.username AND password_check.attribute = 'Cleartext-Password' LEFT JOIN guest_accounts ON userinfo.username = guest_accounts.username LEFT JOIN radcheck expiry_check ON userinfo.username = expiry_check.username AND expiry_check.attribute = 'Expiration' WHERE userinfo.username LIKE :filtervalues OR userinfo.fullname LIKE :filtervalues";
            
                // Add wildcard characters to the filtervalues
                $filtervalues = "%" . $filtervalues . "%";
            
                // Count total records
                $stmt_count = $pdo->prepare($query);
                $stmt_count->bindParam(':filtervalues', $filtervalues, PDO::PARAM_STR);
                $stmt_count->execute();
                $total_records = $stmt_count->rowCount();
            
                // Calculate total pages
                $total_pages = ceil($total_records / $records_per_page);
            
                // Modify query to include LIMIT and OFFSET
                $query .= " LIMIT :offset, :records_per_page";
            
                // Prepare and execute the query
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':filtervalues', $filtervalues, PDO::PARAM_STR);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
                $stmt->execute();
            
                // Fetch the results as an associative array
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
                echo '<section class="table-card table-responsive">';
                echo '<h2>Search Results</h2>';
            
                if (count($results) > 0) {
                    $countersearch = ($page - 1) * $records_per_page + 1;
                    echo '<table class="table table-bordered table-striped">';
                    echo '<thead class="thead-dark">';
                    echo '<tr><th>#</th><th>Full Name</th><th>Username</th><th>Email</th><th>Expires At</th><th>Status</th>';
                    echo '<th>Actions</th></tr>';
                    echo '</thead>';
                    foreach ($results as $row) {
                        echo "<tr>";
                        echo "<td>" . $countersearch . "</td>";
                        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['email'] ?? '-') . "</td>";
                        echo "<td>" . htmlspecialchars(formatAccountExpiry($row['expires_at'] ?? null, $row['radius_expires_at'] ?? null)) . "</td>";
                        echo "<td>" . htmlspecialchars(accountExpiryStatus($row['expires_at'] ?? null, $row['radius_expires_at'] ?? null)) . "</td>";
                        echo "<td><a href='javascript:void(0);' onclick=\"deleteUser('" . htmlspecialchars($row['username']) . "');\" class='btn btn-danger' data-hint='Delete User Account'>Delete</a></td>";
                        echo "</tr>";
                        $countersearch++;
                    }
                    echo '</table>';
                    echo '<p>Showing results for <b>' . htmlspecialchars($_GET['search']) . '</b> | Page <b></i>' . $page . '</i></b> of '. $total_pages .'</p>';
            
                    // Display pagination links
                    echo '<div class="pagination">';
                    // Determine the range of pages to display
                    $range = 2; // Adjust this value as needed

                    $startRange = max(1, $page - $range);
                    $endRange = min($total_pages, $page + $range);

                    // Previous page link
                    if ($page > 1) {
                        echo '<a href="?search=' . urlencode($_GET['search']) . '&page=' . ($page - 1) . '" class="prev">Previous</a>';
                    }

                    // Display numbered page links
                    for ($i = $startRange; $i <= $endRange; $i++) {
                        $activeClass = ($i == $page) ? 'active' : '';
                        echo '<a href="?search=' . urlencode($_GET['search']) . '&page=' . $i . '" class="' . $activeClass . '">' . $i . '</a>';
                    }

                    // Next page link
                    if ($page < $total_pages) {
                        echo '<a href="?search=' . urlencode($_GET['search']) . '&page=' . ($page + 1) . '" class="next">Next</a>';
                    }

                    echo '</div>';
                } else {
                    echo 'No Record Found';
                }
                echo '</section>';
            }

                echo '<div class="alert info-banner">Guest requests are auto-approved. Accounts are created immediately and expired accounts are purged automatically.</div>';

            // join userinfo and radcheck table by username and populate all data limit 10 latest
            $sql = "SELECT userinfo.fullname, userinfo.username, userinfo.email, userinfo.updateby, userinfo.updatedate, guest_accounts.created_at AS requested_at, guest_accounts.expires_at, expiry_check.value AS radius_expires_at FROM userinfo JOIN radcheck password_check ON userinfo.username = password_check.username AND password_check.attribute = 'Cleartext-Password' LEFT JOIN guest_accounts ON userinfo.username = guest_accounts.username LEFT JOIN radcheck expiry_check ON userinfo.username = expiry_check.username AND expiry_check.attribute = 'Expiration' ORDER BY userinfo.updatedate DESC LIMIT 10";

            $result = mysqli_query($conn, $sql);

            if(mysqli_num_rows($result) > 0 ) {
                echo '<section class="table-card table-responsive">';
                echo '<h2>Latest 10 Users</h2>';
                echo '<table class="table table-bordered table-striped">';
                echo '<thead class="thead-dark">';
                echo '<tr><th>Full Name</th><th>Username</th><th>Email</th><th>Requested At</th><th>Expires At</th><th>Status</th><th>Updated by</th><th>Updated At</th></tr>';
                echo '</thead>';
                echo '<tbody>';
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["fullname"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                    echo "<td>" . htmlspecialchars(formatDashboardDate($row["requested_at"] ?? null)) . "</td>";
                    echo "<td>" . htmlspecialchars(formatAccountExpiry($row["expires_at"] ?? null, $row["radius_expires_at"] ?? null)) . "</td>";
                    echo "<td>" . htmlspecialchars(accountExpiryStatus($row["expires_at"] ?? null, $row["radius_expires_at"] ?? null)) . "</td>";
                    echo "<td>" . htmlspecialchars($row["updateby"]) . "</td>";
                    echo "<td>" . htmlspecialchars(formatDashboardDate($row["updatedate"])) . "</td>";
                    echo "</tr>";
                }
                echo '</tbody>';
                echo '</table>';
                echo '</section>';
            } else {
                echo "0 results";
            }

        } else {
            header('WWW-Authenticate: Basic realm="Restricted Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Access Denied';
        }
        ?>
    </main>

    <?php include dirname(__DIR__) . '/template_parts/footer.php'; ?>
    <script>
        (function () {
            const charts = <?php echo json_encode($chartPayload, JSON_UNESCAPED_SLASHES); ?>;
            if (!window.Chart) return;
            const palette = {
                blue: '#0b5cab',
                navy: '#0d3b6f',
                teal: '#0e9f8a',
                amber: '#d97706',
                purple: '#7c3aed',
                red: '#dc2626',
                slate: '#64748b',
                sky: '#38bdf8'
            };

            Chart.defaults.color = '#314a63';
            Chart.defaults.font.family = 'Sora, sans-serif';
            Chart.defaults.plugins.legend.labels.usePointStyle = true;

            function formatNumber(value, unit) {
                const number = Number(value);
                const formatted = Number.isInteger(number) ? number.toString() : number.toLocaleString(undefined, {
                    maximumFractionDigits: 2
                });

                return unit ? formatted + ' ' + unit : formatted;
            }

            const visibleValueLabels = {
                id: 'visibleValueLabels',
                afterDatasetsDraw(chart, args, options) {
                    if (options === false) return;

                    const { ctx, chartArea } = chart;
                    const chartType = chart.config.type;
                    const hideZero = options.hideZero !== false;

                    ctx.save();
                    ctx.font = '700 11px Sora, sans-serif';
                    ctx.fillStyle = options.color || '#20364c';

                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        if (!meta.visible) return;

                        const total = dataset.data.reduce((sum, item) => {
                            const itemValue = Number(item);
                            return Number.isFinite(itemValue) ? sum + itemValue : sum;
                        }, 0);

                        meta.data.forEach((element, index) => {
                            const value = Number(dataset.data[index]);
                            if (!Number.isFinite(value) || (hideZero && value === 0)) return;

                            const position = element.tooltipPosition();

                            if (chartType === 'pie' || chartType === 'doughnut') {
                                if (total > 0 && value / total < 0.08) return;
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';
                                ctx.fillStyle = '#ffffff';
                                ctx.fillText(formatNumber(value), position.x, position.y);
                                ctx.fillStyle = options.color || '#20364c';
                                return;
                            }

                            if (chart.options.indexAxis === 'y') {
                                ctx.textAlign = 'left';
                                ctx.textBaseline = 'middle';
                                const x = Math.min(position.x + 8, chartArea.right - 26);
                                ctx.fillText(formatNumber(value, dataset.unit), x, position.y);
                                return;
                            }

                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(formatNumber(value, dataset.unit), position.x, Math.max(position.y - 7, chartArea.top + 12));
                        });
                    });

                    ctx.restore();
                }
            };

            const emptyState = {
                id: 'emptyState',
                afterDraw(chart, args, options) {
                    if (options === false) return;

                    const hasVisibleData = chart.data.datasets.some((dataset) => {
                        return dataset.data.some((item) => Number(item) > 0);
                    });

                    if (hasVisibleData) return;

                    const { ctx, chartArea } = chart;
                    ctx.save();
                    ctx.fillStyle = '#5a6f83';
                    ctx.font = '600 13px Sora, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(options.text || 'No data in this period', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
                    ctx.restore();
                }
            };

            Chart.register(visibleValueLabels, emptyState);

            function pluginDefaults(extra) {
                return Object.assign({
                    legend: {
                        labels: {
                            color: '#314a63',
                            padding: 14,
                            boxWidth: 8,
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 10,
                        callbacks: {
                            label(context) {
                                const parsed = context.parsed;
                                const value = typeof parsed === 'object' ? (parsed.y ?? parsed.x ?? 0) : parsed;
                                const label = context.label || context.dataset.label || '';
                                const unit = context.dataset.unit || '';
                                return (label ? label + ': ' : '') + formatNumber(value, unit);
                            }
                        }
                    },
                    visibleValueLabels: {
                        hideZero: true
                    },
                    emptyState: {
                        text: 'No data in this period'
                    }
                }, extra || {});
            }

            function axisStyle(maxTicksLimit) {
                return {
                    ticks: {
                        color: '#314a63',
                        precision: 0,
                        autoSkip: true,
                        maxTicksLimit: maxTicksLimit || 8,
                        font: {
                            size: 11,
                            weight: '600'
                        }
                    },
                    grid: {
                        color: 'rgba(49, 74, 99, 0.14)',
                        drawBorder: false
                    }
                };
            }

            function chartOptions(overrides) {
                const custom = overrides || {};
                const options = {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 24,
                            right: 16,
                            bottom: 0,
                            left: 6
                        }
                    },
                    plugins: pluginDefaults(),
                    scales: {
                        x: axisStyle(),
                        y: Object.assign(axisStyle(), {
                            beginAtZero: true
                        })
                    }
                };

                if (custom.scales === false) {
                    delete options.scales;
                } else if (custom.scales) {
                    options.scales = custom.scales;
                }

                if (custom.plugins) {
                    options.plugins = pluginDefaults(custom.plugins);
                }

                delete custom.scales;
                delete custom.plugins;

                return Object.assign(options, custom);
            }

            function renderSummary(id, data, options) {
                const container = document.getElementById(id);
                if (!container || !data) return;

                const settings = options || {};
                const labels = data.labels || [];
                const values = data.values || [];
                const hasData = values.some((item) => Number(item) > 0);

                container.textContent = '';

                if (!hasData) {
                    const empty = document.createElement('span');
                    empty.className = 'chart-summary-empty';
                    empty.textContent = settings.emptyText || 'No data recorded';
                    container.appendChild(empty);
                    return;
                }

                labels.forEach((label, index) => {
                    const item = document.createElement('span');
                    item.className = 'chart-summary-item';

                    const labelEl = document.createElement('span');
                    labelEl.textContent = label;

                    const valueEl = document.createElement('strong');
                    valueEl.textContent = formatNumber(values[index] || 0, settings.unit || '');

                    item.appendChild(labelEl);
                    item.appendChild(valueEl);
                    container.appendChild(item);
                });
            }

            function makeChart(id, config) {
                const el = document.getElementById(id);
                if (!el) return;
                new Chart(el, config);
            }

            makeChart('guestDailyChart', {
                type: 'line',
                data: {
                    labels: charts.guestDaily.labels,
                    datasets: [{
                        label: 'Guest requests',
                        data: charts.guestDaily.values,
                        borderColor: palette.teal,
                        backgroundColor: 'rgba(14, 159, 138, 0.16)',
                        pointBackgroundColor: palette.teal,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.32
                    }]
                },
                options: chartOptions({
                    plugins: {
                        emptyState: {
                            text: 'No guest requests in this period'
                        }
                    }
                })
            });

            makeChart('guestExpiryChart', {
                type: 'bar',
                data: {
                    labels: charts.guestExpiry.labels,
                    datasets: [{
                        label: 'Accounts expiring',
                        data: charts.guestExpiry.values,
                        backgroundColor: palette.amber,
                        borderRadius: 8
                    }]
                },
                options: chartOptions({
                    plugins: {
                        emptyState: {
                            text: 'No accounts expire in this period'
                        }
                    }
                })
            });

            makeChart('guestStatusChart', {
                type: 'doughnut',
                data: {
                    labels: charts.guestStatus.labels,
                    datasets: [{
                        data: charts.guestStatus.values,
                        backgroundColor: [palette.teal, palette.red],
                        borderWidth: 0
                    }]
                },
                options: chartOptions({
                    scales: false,
                    cutout: '62%',
                    plugins: {
                        visibleValueLabels: {
                            hideZero: true
                        }
                    }
                })
            });

            makeChart('authDailyChart', {
                type: 'bar',
                data: {
                    labels: charts.authDaily.labels,
                    datasets: [{
                        label: 'Auth attempts',
                        data: charts.authDaily.values,
                        backgroundColor: palette.blue,
                        borderRadius: 8
                    }]
                },
                options: chartOptions({
                    plugins: {
                        emptyState: {
                            text: 'No radpostauth records yet'
                        }
                    }
                })
            });

            makeChart('sessionHourlyChart', {
                type: 'line',
                data: {
                    labels: charts.sessionHourly.labels,
                    datasets: [{
                        label: 'Sessions',
                        data: charts.sessionHourly.values,
                        borderColor: palette.purple,
                        backgroundColor: 'rgba(124, 58, 237, 0.14)',
                        pointBackgroundColor: palette.purple,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: chartOptions({
                    scales: {
                        x: axisStyle(12),
                        y: Object.assign(axisStyle(), {
                            beginAtZero: true
                        })
                    },
                    plugins: {
                        emptyState: {
                            text: 'No radacct session records yet'
                        }
                    }
                })
            });

            makeChart('systemCapacityChart', {
                type: 'bar',
                data: {
                    labels: charts.systemCapacity.labels,
                    datasets: [{
                        label: 'GB',
                        unit: 'GB',
                        data: charts.systemCapacity.values,
                        backgroundColor: [palette.blue, palette.teal, palette.purple, palette.amber],
                        borderRadius: 8
                    }]
                },
                options: chartOptions({
                    indexAxis: 'y',
                    scales: {
                        x: Object.assign(axisStyle(), {
                            beginAtZero: true
                        }),
                        y: axisStyle(4)
                    }
                })
            });

            makeChart('authReplyChart', {
                type: 'pie',
                data: {
                    labels: charts.authReply.labels,
                    datasets: [{
                        data: charts.authReply.values,
                        backgroundColor: [palette.teal, palette.red, palette.amber, palette.purple, palette.slate, palette.sky],
                        borderWidth: 0
                    }]
                },
                options: chartOptions({
                    scales: false,
                    plugins: {
                        emptyState: {
                            text: 'No radpostauth outcomes yet'
                        }
                    }
                })
            });

            makeChart('topNasChart', {
                type: 'bar',
                data: {
                    labels: charts.topNas.labels,
                    datasets: [{
                        label: 'Sessions',
                        data: charts.topNas.values,
                        backgroundColor: palette.navy,
                        borderRadius: 8
                    }]
                },
                options: chartOptions({
                    indexAxis: 'y',
                    scales: {
                        x: Object.assign(axisStyle(), {
                            beginAtZero: true
                        }),
                        y: axisStyle(5)
                    },
                    plugins: {
                        emptyState: {
                            text: 'No radacct NAS records yet'
                        }
                    }
                })
            });

            renderSummary('guestDailySummary', charts.guestDaily, {
                emptyText: 'No guest requests in the last 14 days'
            });
            renderSummary('guestExpirySummary', charts.guestExpiry, {
                emptyText: 'No accounts expire in the next 14 days'
            });
            renderSummary('guestStatusSummary', charts.guestStatus, {
                emptyText: 'No guest account lifecycle data'
            });
            renderSummary('authDailySummary', charts.authDaily, {
                emptyText: 'No radpostauth records yet'
            });
            renderSummary('sessionHourlySummary', charts.sessionHourly, {
                emptyText: 'No radacct session records yet'
            });
            renderSummary('systemCapacitySummary', charts.systemCapacity, {
                unit: 'GB'
            });
            renderSummary('authReplySummary', charts.authReply, {
                emptyText: 'No radpostauth outcomes yet'
            });
            renderSummary('topNasSummary', charts.topNas, {
                emptyText: 'No radacct NAS records yet'
            });
        })();
    </script>

</body>
</html>
