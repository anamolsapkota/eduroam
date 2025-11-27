<?php
// Start the session if not already started
session_start();

// Check if 'basic_auth' session variable is set
if(!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    header('Location: login.php');
    exit;
}

// Get basic auth from session
$basic_auth = base64_decode($_SESSION['basic_auth']);
$authUser = explode(':', $basic_auth)[0];
$authPass = explode(':', $basic_auth)[1];

$_SERVER['PHP_AUTH_USER'] = $authUser;
$_SERVER['PHP_AUTH_PW'] = $authPass;

// exit if not authenticated
if ($_SERVER['PHP_AUTH_USER'] !== $authUser || $_SERVER['PHP_AUTH_PW'] !== $authPass) {
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access Denied';
    exit;
}

// Include the config.php file
require_once 'includes/config.php';

// Include database connection
include 'db.php';

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
    return trim($output);
}

// Get server statistics
$queryTotalUsers = "SELECT COUNT(*) AS totalUsers FROM userinfo u INNER JOIN radcheck r ON u.username = r.username;";
$stmtTotalUsers = $pdo->prepare($queryTotalUsers);
$stmtTotalUsers->execute();
$rowTotalUsers = $stmtTotalUsers->fetch(PDO::FETCH_ASSOC);
$totalUsers = $rowTotalUsers['totalUsers'];

$activeUsers = $totalUsers;
$bannedUsers = 0;

$date = date("F j, Y (l)");
$hostname = executeCommand("hostname");
$uptimeInSeconds = executeCommand("cat /proc/uptime | awk '{print $1}'");
$uptime = calculateUptime($uptimeInSeconds);

$totalMemory = executeCommand("free -h | grep Mem | awk '{print $2}'");
$usedMemory = executeCommand("free -h | grep Mem | awk '{print $3}'");
$freeMemory = executeCommand("free -h | grep Mem | awk '{print $4}'");

$totalDisk = executeCommand("df -h / | awk 'NR==2 {print $2}'");
$freeDisk = executeCommand("df -h / | awk 'NR==2 {print $4}'");
$usedDisk = executeCommand("df -h / | awk 'NR==2 {print $3}'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }

        body {
            background: #667eea;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            padding: 30px 0;
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .page-header h2 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h2 i {
            color: var(--primary-color);
            font-size: 2rem;
        }

        .time-display {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .stat-card.primary .icon {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }

        .stat-card.success .icon {
            background: rgba(25, 135, 84, 0.1);
            color: var(--success-color);
        }

        .stat-card.info .icon {
            background: rgba(13, 202, 240, 0.1);
            color: var(--info-color);
        }

        .stat-card.warning .icon {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }

        .stat-card p {
            margin: 5px 0 0 0;
            color: var(--secondary-color);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-card .stat-details {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .card-professional {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: none;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header-professional {
            background: #667eea;
            color: white;
            padding: 20px 30px;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header-professional h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .card-body-professional {
            padding: 30px;
        }

        .search-bar {
            background: var(--light-color);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-bar input {
            flex: 1;
            min-width: 200px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            transition: border-color 0.3s ease;
        }

        .search-bar input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-professional {
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-professional:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-gradient {
            background: #667eea;
            color: white;
        }

        .table-professional {
            margin: 0;
        }

        .table-professional thead {
            background: var(--light-color);
        }

        .table-professional thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            color: var(--dark-color);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table-professional tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-professional tbody tr {
            transition: background-color 0.2s ease;
        }

        .table-professional tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            transform: scale(1.1);
        }

        .pagination-professional {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }

        .pagination-professional a {
            padding: 8px 15px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            color: var(--dark-color);
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .pagination-professional a.active {
            background: #667eea;
            border-color: transparent;
            color: white;
        }

        .pagination-professional a:hover:not(.active) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--secondary-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .upload-section {
            background: rgba(13, 202, 240, 0.1);
            border: 2px dashed var(--info-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .upload-section form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Extra styles for User Management section (from second file) */

        .modal-professional .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-professional .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0;
            padding: 20px 30px;
        }

        .modal-professional .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }

        .modal-professional .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-professional .modal-body {
            padding: 30px;
        }

        .modal-professional .modal-footer {
            border: none;
            padding: 20px 30px;
            background: var(--light-color);
        }

        .form-label-professional {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control-professional {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control-professional:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .input-group-professional {
            position: relative;
        }

        .input-group-professional .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--secondary-color);
            z-index: 10;
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .alert-professional {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-professional i {
            font-size: 1.3rem;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }

        .password-strength.weak {
            background: var(--danger-color);
            width: 33%;
        }

        .password-strength.medium {
            background: var(--warning-color);
            width: 66%;
        }

        .password-strength.strong {
            background: var(--success-color);
            width: 100%;
        }
    </style>
    <?php include 'template_parts/head.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        // existing delete / approve / reject for search + requests
        function deleteUser(username) {
            if (confirm('Are you sure you want to delete this user?')) {
                $.ajax({
                    type: 'POST',
                    url: 'delete_user.php',
                    data: { username: username },
                    success: function (response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function () {
                        alert('An error occurred while deleting the user.');
                    }
                });
            }
        }

        function approveRequest(id) {
            if (confirm('Are you sure you want to approve this request?')) {
                $.ajax({
                    type: 'POST',
                    url: 'approve.php',
                    data: { id: id },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            alert('Error: ' + errorResponse.message);
                        } catch (e) {
                            alert('An error occurred while approving the request. Server response: ' + xhr.responseText.substring(0, 200));
                        }
                    }
                });
            }
        }

        function rejectRequest(id) {
            if (confirm('Are you sure you want to reject this request?')) {
                $.ajax({
                    type: 'POST',
                    url: 'reject.php',
                    data: { id: id },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            alert('Error: ' + errorResponse.message);
                        } catch (e) {
                            alert('An error occurred while rejecting the request. Server response: ' + xhr.responseText.substring(0, 200));
                        }
                    }
                });
            }
        }
    </script>
</head>
<body>
    <?php include 'template_parts/nav.php'; ?>
    
    <div class="container main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h2>
                <i class="fas fa-tachometer-alt"></i>
                <?php echo $site_name; ?> Management Dashboard
            </h2>
            <div class="time-display">
                <i class="far fa-clock"></i>
                <?php echo date("F j, Y, g:i a"); ?>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <!-- Users Stats -->
            <div class="stat-card primary">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $totalUsers; ?></h3>
                <p>Total Eduroam Users</p>
                <div class="stat-details">Active: <?php echo $activeUsers; ?> | Banned: <?php echo $bannedUsers; ?></div>
            </div>

            <!-- Server Stats -->
            <div class="stat-card info">
                <div class="icon">
                    <i class="fas fa-server"></i>
                </div>
                <h3><?php echo $hostname; ?></h3>
                <p>Server Hostname</p>
                <div class="stat-details">Uptime: <?php echo $uptime; ?></div>
            </div>

            <!-- Memory Stats -->
            <div class="stat-card success">
                <div class="icon">
                    <i class="fas fa-memory"></i>
                </div>
                <h3><?php echo $usedMemory; ?> / <?php echo $totalMemory; ?></h3>
                <p>Memory Usage</p>
                <div class="stat-details">Free: <?php echo $freeMemory; ?></div>
            </div>

            <!-- Storage Stats -->
            <div class="stat-card warning">
                <div class="icon">
                    <i class="fas fa-hdd"></i>
                </div>
                <h3><?php echo $usedDisk; ?> / <?php echo $totalDisk; ?></h3>
                <p>Disk Usage</p>
                <div class="stat-details">Free: <?php echo $freeDisk; ?></div>
            </div>
        </div>

        <!-- Bulk Import Section -->
        <div class="card-professional">
            <div class="card-header-professional">
                <h5><i class="fas fa-file-upload"></i> Bulk Import & Search</h5>
            </div>
            <div class="card-body-professional">
                <div class="row">
                    <div class="col-md-8">
                        <div class="upload-section">
                            <form action="import.php" method="post" enctype="multipart/form-data">
                                <input type="file" name="upcsv" accept=".csv" required class="form-control">
                                <button type="submit" class="btn btn-professional btn-gradient">
                                    <i class="fas fa-upload"></i> Upload CSV
                                </button>
                                <a href="sample.csv" class="btn btn-professional btn-secondary">
                                    <i class="fas fa-download"></i> Download Sample
                                </a>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <form action="" method="GET">
                            <div class="input-group">
                                <input type="text" name="search" required value="<?php if(isset($_GET['search'])){ echo htmlspecialchars($_GET['search']);} ?>" class="form-control" placeholder="Search users...">
                                <button type="submit" class="btn btn-professional btn-gradient">
                                    <i class="fas fa-search"></i>
                                </button>
                                <a href="/eduroam/management.php" class="btn btn-professional btn-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
        if ($_SERVER['PHP_AUTH_USER'] === $authUser && $_SERVER['PHP_AUTH_PW'] === $authPass) {
            // Search Results
            if (isset($_GET['search'])) {
                $filtervalues = $_GET['search'];
                $records_per_page = 10;
                $page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
                $offset = ($page - 1) * $records_per_page;
                
                $query = "SELECT userinfo.username, userinfo.fullname, userinfo.email, radcheck.value, userinfo.updateby, userinfo.updatedate FROM userinfo INNER JOIN radcheck ON userinfo.username = radcheck.username WHERE userinfo.username LIKE :filtervalues OR userinfo.fullname LIKE :filtervalues";
                $filtervalues = "%" . $filtervalues . "%";
                
                $stmt_count = $pdo->prepare($query);
                $stmt_count->bindParam(':filtervalues', $filtervalues, PDO::PARAM_STR);
                $stmt_count->execute();
                $total_records = $stmt_count->rowCount();
                $total_pages = ceil($total_records / $records_per_page);
                
                $query .= " LIMIT :offset, :records_per_page";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':filtervalues', $filtervalues, PDO::PARAM_STR);
                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindParam(':records_per_page', $records_per_page, PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<div class="card-professional">';
                echo '<div class="card-header-professional">';
                echo '<h5><i class="fas fa-search"></i> Search Results for "' . htmlspecialchars($_GET['search']) . '"</h5>';
                echo '</div>';
                echo '<div class="card-body-professional">';
                
                if (count($results) > 0) {
                    $countersearch = ($page - 1) * $records_per_page + 1;
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-professional">';
                    echo '<thead><tr><th>#</th><th>Full Name</th><th>Username</th>';
                    if (isset($_GET['password']) && $_GET['password'] == 'show') {
                        echo '<th>Password</th>';
                    }
                    echo '<th>Actions</th></tr></thead><tbody>';
                    
                    foreach ($results as $row) {
                        echo "<tr>";
                        echo "<td>" . $countersearch . "</td>";
                        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                        if (isset($_GET['password']) && $_GET['password'] == 'show') {
                            echo "<td>" . htmlspecialchars($row['value']) . "</td>";
                        }
                        echo "<td><button onclick=\"deleteUser('" . htmlspecialchars($row['username']) . "');\" class='btn btn-danger btn-icon'><i class='fas fa-trash'></i></button></td>";
                        echo "</tr>";
                        $countersearch++;
                    }
                    echo '</tbody></table></div>';
                    
                    echo '<p class="text-center mt-3">Page <strong>' . $page . '</strong> of ' . $total_pages . '</p>';
                    
                    // Pagination
                    echo '<div class="pagination-professional">';
                    $range = 2;
                    $startRange = max(1, $page - $range);
                    $endRange = min($total_pages, $page + $range);
                    
                    if ($page > 1) {
                        echo '<a href="?search=' . urlencode($_GET['search']) . '&page=' . ($page - 1) . '"><i class="fas fa-chevron-left"></i></a>';
                    }
                    
                    for ($i = $startRange; $i <= $endRange; $i++) {
                        $activeClass = ($i == $page) ? 'active' : '';
                        echo '<a href="?search=' . urlencode($_GET['search']) . '&page=' . $i . '" class="' . $activeClass . '">' . $i . '</a>';
                    }
                    
                    if ($page < $total_pages) {
                        echo '<a href="?search=' . urlencode($_GET['search']) . '&page=' . ($page + 1) . '"><i class="fas fa-chevron-right"></i></a>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="empty-state">';
                    echo '<i class="fas fa-search-minus"></i>';
                    echo '<h4>No Results Found</h4>';
                    echo '<p>Try adjusting your search criteria</p>';
                    echo '</div>';
                }
                echo '</div></div>';
            }

            // Eduroam Requests
            $sql = "SELECT * FROM eduroam_request";
            $result = mysqli_query($conn, $sql);

            echo '<div class="card-professional">';
            echo '<div class="card-header-professional">';
            echo '<h5><i class="fas fa-user-clock"></i> Pending Eduroam Requests</h5>';
            echo '</div>';
            echo '<div class="card-body-professional">';

            if ($result && mysqli_num_rows($result) > 0) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-professional">';
                echo '<thead><tr><th>Full Name</th><th>Email</th><th>Created At</th><th>Actions</th></tr></thead>';
                echo '<tbody>';
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["fullname"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["org_email"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["created_at"]) . "</td>";
                    echo "<td>";
                    echo "<button onclick=\"approveRequest('" . htmlspecialchars($row['id']) . "');\" class='btn btn-success btn-icon me-2'><i class='fas fa-check'></i></button>";
                    echo "<button onclick=\"rejectRequest('" . htmlspecialchars($row['id']) . "');\" class='btn btn-danger btn-icon'><i class='fas fa-times'></i></button>";
                    echo "</td>";
                    echo "</tr>";
                }
                echo '</tbody></table></div>';
            } else {
                echo '<div class="empty-state">';
                echo '<i class="fas fa-inbox"></i>';
                echo '<h4>No Pending Requests</h4>';
                echo '<p>All requests have been processed</p>';
                echo '</div>';
            }
            echo '</div></div>';

            // // Latest 10 Users
            // $sql = "SELECT userinfo.fullname, userinfo.username, userinfo.email, radcheck.value, userinfo.updateby, userinfo.updatedate FROM userinfo JOIN radcheck ON userinfo.username = radcheck.username ORDER BY userinfo.updatedate DESC LIMIT 10";
            // $result = mysqli_query($conn, $sql);

            // if($result && mysqli_num_rows($result) > 0) {
            //     echo '<div class="card-professional">';
            //     echo '<div class="card-header-professional">';
            //     echo '<h5><i class="fas fa-user-plus"></i> Latest 10 Users</h5>';
            //     echo '</div>';
            //     echo '<div class="card-body-professional">';
            //     echo '<div class="table-responsive">';
            //     echo '<table class="table table-professional">';
            //     echo '<thead><tr><th>Full Name</th><th>Username</th><th>Email</th><th>Updated by</th><th>Updated At</th></tr></thead>';
            //     echo '<tbody>';
            //     while ($row = mysqli_fetch_assoc($result)) {
            //         echo "<tr>";
            //         echo "<td>" . htmlspecialchars($row["fullname"]) . "</td>";
            //         echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
            //         echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
            //         echo "<td>" . htmlspecialchars($row["updateby"]) . "</td>";
            //         echo "<td>" . htmlspecialchars($row["updatedate"]) . "</td>";
            //         echo "</tr>";
            //     }
            //     echo '</tbody></table></div>';
            //     echo '</div></div>';
            // }

        } else {
            header('WWW-Authenticate: Basic realm="Restricted Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Access Denied';
        }
        ?>

        <!-- =============== NEW ADVANCED USER MANAGEMENT SECTION =============== -->
        <div class="card-professional">
            <div class="card-header-professional">
                <h5><i class="fas fa-users-cog"></i> Advanced User Management</h5>
                <button class="btn btn-professional btn-gradient" onclick="um_openCreateModal()">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>
            <div class="card-body-professional">
                <div class="search-bar">
                    <input type="text" id="um_searchInput" class="form-control" placeholder="Search by username, name, or email...">
                    <select id="um_limitSelect" class="form-select" style="max-width: 150px;">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                    <button class="btn btn-professional btn-secondary" onclick="um_refreshTable()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-professional">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user"></i> Username</th>
                                <th><i class="fas fa-id-card"></i> Full Name</th>
                                <th><i class="fas fa-envelope"></i> Email</th>
                                <th><i class="fas fa-calendar"></i> Updated Date</th>
                                <th><i class="fas fa-user-edit"></i> Updated By</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody id="um_userTableBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="User table pagination">
                    <ul class="pagination pagination-professional" id="um_paginationContainer"></ul>
                </nav>
            </div>
        </div>

    </div>

    <!-- Create/Edit User Modal -->
    <div class="modal fade modal-professional" id="um_userModal" tabindex="-1" aria-labelledby="um_userModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="um_userModalLabel">
                        <i class="fas fa-user-plus"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="um_userForm">
                        <input type="hidden" id="um_originalUsername" name="original_username">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="um_username" class="form-label form-label-professional">
                                        Username <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control form-control-professional" id="um_username" name="username" required>
                                    </div>
                                    <small class="text-muted" id="um_usernameHelp">Cannot be changed after creation</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="um_fullname" class="form-label form-label-professional">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" class="form-control form-control-professional" id="um_fullname" name="fullname" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="um_email" class="form-label form-label-professional">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control form-control-professional" id="um_email" name="email" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="um_password" class="form-label form-label-professional">
                                Password
                            </label>
                            <div class="input-group-professional">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control form-control-professional" id="um_password" name="password">
                                </div>
                                <i class="fas fa-eye toggle-password" onclick="um_togglePassword()"></i>
                            </div>
                            <small class="text-muted" id="um_passwordHelp">Leave blank to auto-generate a secure password</small>
                            <div class="password-strength" id="um_passwordStrength" style="display: none;"></div>
                        </div>

                        <div class="mb-3" id="um_sendEmailContainer">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="um_send_email" name="send_email" checked>
                                <label class="form-check-label" for="um_send_email">
                                    <i class="fas fa-paper-plane"></i> Send welcome email with credentials
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-professional alert-info" id="um_passwordDisplay" style="display: none;">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Generated Password:</strong> 
                                <code id="um_generatedPassword" style="font-size: 1.1rem;"></code>
                                <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="um_copyPassword()">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary btn-professional" onclick="um_saveUser()">
                        <i class="fas fa-save"></i> <span id="um_saveButtonText">Save User</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade modal-professional" id="um_deleteModal" tabindex="-1" aria-labelledby="um_deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title" id="um_deleteModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-professional alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Warning!</strong> This action cannot be undone.
                        </div>
                    </div>
                    <p>Are you sure you want to delete user <strong id="um_deleteUsername"></strong>?</p>
                    <p class="text-muted">This will permanently remove all user data and access credentials.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger btn-professional" onclick="um_confirmDelete()">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Details Modal -->
    <div class="modal fade modal-professional" id="um_viewModal" tabindex="-1" aria-labelledby="um_viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="um_viewModalLabel">
                        <i class="fas fa-user-circle"></i> User Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-user"></i> Username:</strong>
                            <p class="ms-4" id="um_viewUsername"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-id-card"></i> Full Name:</strong>
                            <p class="ms-4" id="um_viewFullname"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-envelope"></i> Email:</strong>
                            <p class="ms-4" id="um_viewEmail"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-lock"></i> Password:</strong>
                            <p class="ms-4">
                                <code id="um_viewPassword">********</code>
                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="um_toggleViewPassword()">
                                    <i class="fas fa-eye" id="um_viewPasswordIcon"></i>
                                </button>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-calendar"></i> Updated Date:</strong>
                            <p class="ms-4" id="um_viewUpdatedate"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-user-edit"></i> Updated By:</strong>
                            <p class="ms-4" id="um_viewUpdateby"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'template_parts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ========== Advanced User Management JS (uses user_management_api.php) ==========

        let um_isEdit = false;
        let um_currentPage = 1;
        let um_currentLimit = 10;
        let um_currentSearch = '';
        let um_deleteTargetUsername = '';
        let um_viewPasswordShown = false;
        let um_actualPassword = '';

        document.addEventListener('DOMContentLoaded', function () {
            um_loadUsers();

            // Search with debounce
            let searchTimeout;
            document.getElementById('um_searchInput').addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () {
                    um_currentSearch = document.getElementById('um_searchInput').value;
                    um_currentPage = 1;
                    um_loadUsers();
                }, 500);
            });

            // Limit change
            document.getElementById('um_limitSelect').addEventListener('change', function () {
                um_currentLimit = parseInt(this.value);
                um_currentPage = 1;
                um_loadUsers();
            });

            // Password strength checker
            document.getElementById('um_password').addEventListener('input', function () {
                um_checkPasswordStrength(this.value);
            });
        });

        function um_loadUsers() {
            const tbody = document.getElementById('um_userTableBody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

            const formData = new FormData();
            formData.append('action', 'list');
            formData.append('page', um_currentPage);
            formData.append('limit', um_currentLimit);
            formData.append('search', um_currentSearch);

            fetch('user_management_api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        um_displayUsers(data.data);
                        um_updatePagination(data.totalPages, data.page);
                    } else {
                        um_showError('Failed to load users: ' + (data.message || 'Unknown error'));
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading users</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    um_showError('An error occurred while loading users: ' + error.message);
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error: ' + error.message + '</td></tr>';
                });
        }

        function um_displayUsers(users) {
            const tbody = document.getElementById('um_userTableBody');

            if (!users || users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-users-slash"></i>
                                <h4>No Users Found</h4>
                                <p>Try adjusting your search criteria or add a new user</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = users.map(user => `
                <tr>
                    <td><strong>${um_escapeHtml(user.username)}</strong></td>
                    <td>${um_escapeHtml(user.fullname)}</td>
                    <td>
                        <a href="mailto:${um_escapeHtml(user.email)}" class="text-decoration-none">
                            ${um_escapeHtml(user.email)}
                        </a>
                    </td>
                    <td><small>${um_formatDate(user.updatedate)}</small></td>
                    <td><span class="badge badge-status bg-secondary">${um_escapeHtml(user.updateby)}</span></td>
                    <td>
                        <button class="btn btn-info btn-icon" onclick="um_viewUser('${um_escapeHtml(user.username)}')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-warning btn-icon" onclick="um_editUser('${um_escapeHtml(user.username)}')" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-icon" onclick="um_deleteUser('${um_escapeHtml(user.username)}')" title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function um_updatePagination(totalPages, currentPageNum) {
            const container = document.getElementById('um_paginationContainer');

            if (!totalPages || totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';

            // Previous
            html += `
                <li class="page-item ${currentPageNum === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="um_changePage(${currentPageNum - 1}); return false;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPageNum - 2 && i <= currentPageNum + 2)) {
                    html += `
                        <li class="page-item ${i === currentPageNum ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="um_changePage(${i}); return false;">${i}</a>
                        </li>
                    `;
                } else if (i === currentPageNum - 3 || i === currentPageNum + 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            // Next
            html += `
                <li class="page-item ${currentPageNum === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="um_changePage(${currentPageNum + 1}); return false;">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;

            container.innerHTML = html;
        }

        function um_changePage(page) {
            if (page < 1) return;
            um_currentPage = page;
            um_loadUsers();
        }

        function um_openCreateModal() {
            um_isEdit = false;
            document.getElementById('um_userModalLabel').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
            document.getElementById('um_userForm').reset();
            document.getElementById('um_originalUsername').value = '';
            document.getElementById('um_username').readOnly = false;
            document.getElementById('um_sendEmailContainer').style.display = 'block';
            document.getElementById('um_passwordHelp').textContent = 'Leave blank to auto-generate a secure password';
            document.getElementById('um_passwordDisplay').style.display = 'none';
            document.getElementById('um_usernameHelp').style.display = 'block';
            document.getElementById('um_saveButtonText').textContent = 'Save User';

            const modal = new bootstrap.Modal(document.getElementById('um_userModal'));
            modal.show();
        }

        function um_editUser(username) {
            um_isEdit = true;
            document.getElementById('um_userModalLabel').innerHTML = '<i class="fas fa-user-edit"></i> Edit User';
            document.getElementById('um_saveButtonText').textContent = 'Update User';

            const formData = new FormData();
            formData.append('action', 'get');
            formData.append('username', username);

            fetch('user_management_api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const user = data.data;
                        document.getElementById('um_originalUsername').value = user.username;
                        document.getElementById('um_username').value = user.username;
                        document.getElementById('um_username').readOnly = true;
                        document.getElementById('um_fullname').value = user.fullname;
                        document.getElementById('um_email').value = user.email;
                        document.getElementById('um_password').value = '';
                        document.getElementById('um_sendEmailContainer').style.display = 'none';
                        document.getElementById('um_passwordHelp').textContent = 'Leave blank to keep current password';
                        document.getElementById('um_passwordDisplay').style.display = 'none';
                        document.getElementById('um_usernameHelp').style.display = 'none';

                        const modal = new bootstrap.Modal(document.getElementById('um_userModal'));
                        modal.show();
                    } else {
                        um_showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    um_showError('An error occurred while fetching user data');
                });
        }

        function um_viewUser(username) {
            const formData = new FormData();
            formData.append('action', 'get');
            formData.append('username', username);

            fetch('user_management_api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const user = data.data;
                        document.getElementById('um_viewUsername').textContent = user.username;
                        document.getElementById('um_viewFullname').textContent = user.fullname;
                        document.getElementById('um_viewEmail').textContent = user.email;
                        document.getElementById('um_viewPassword').textContent = '••••••••';
                        um_actualPassword = user.password || '(not set)';
                        um_viewPasswordShown = false;
                        document.getElementById('um_viewPasswordIcon').className = 'fas fa-eye';
                        document.getElementById('um_viewUpdatedate').textContent = um_formatDate(user.updatedate);
                        document.getElementById('um_viewUpdateby').textContent = user.updateby;

                        const modal = new bootstrap.Modal(document.getElementById('um_viewModal'));
                        modal.show();
                    } else {
                        um_showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    um_showError('An error occurred while fetching user data');
                });
        }

        function um_saveUser() {
            const form = document.getElementById('um_userForm');
            const formData = new FormData(form);

            if (um_isEdit) {
                formData.append('action', 'update');
                formData.append('username', document.getElementById('um_originalUsername').value);
            } else {
                formData.append('action', 'create');
                formData.append('send_email', document.getElementById('um_send_email').checked ? 'true' : 'false');
            }

            const saveBtn = document.querySelector('#um_userModal .btn-primary');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="loading-spinner"></span> Saving...';
            saveBtn.disabled = true;

            fetch('user_management_api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;

                    if (data.status === 'success') {
                        if (!um_isEdit && data.password) {
                            document.getElementById('um_generatedPassword').textContent = data.password;
                            document.getElementById('um_passwordDisplay').style.display = 'block';
                            um_showSuccess(data.message + ' (Password: ' + data.password + ')');
                        } else {
                            um_showSuccess(data.message);
                            bootstrap.Modal.getInstance(document.getElementById('um_userModal')).hide();
                        }
                        um_loadUsers();
                    } else {
                        um_showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    um_showError('An error occurred while saving user');
                });
        }

        function um_deleteUser(username) {
            um_deleteTargetUsername = username;
            document.getElementById('um_deleteUsername').textContent = username;
            const modal = new bootstrap.Modal(document.getElementById('um_deleteModal'));
            modal.show();
        }

        function um_confirmDelete() {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('username', um_deleteTargetUsername);

            fetch('user_management_api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        um_showSuccess(data.message);
                        bootstrap.Modal.getInstance(document.getElementById('um_deleteModal')).hide();
                        um_loadUsers();
                    } else {
                        um_showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    um_showError('An error occurred while deleting user');
                });
        }

        function um_refreshTable() {
            um_currentPage = 1;
            um_currentSearch = '';
            document.getElementById('um_searchInput').value = '';
            um_loadUsers();
            um_showSuccess('Table refreshed');
        }

        function um_togglePassword() {
            const input = document.getElementById('um_password');
            const icon = document.querySelector('.toggle-password');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function um_toggleViewPassword() {
            const passwordEl = document.getElementById('um_viewPassword');
            const icon = document.getElementById('um_viewPasswordIcon');
            
            if (um_viewPasswordShown) {
                passwordEl.textContent = '••••••••';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                um_viewPasswordShown = false;
            } else {
                passwordEl.textContent = um_actualPassword;
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                um_viewPasswordShown = true;
            }
        }

        function um_checkPasswordStrength(password) {
            const strengthBar = document.getElementById('um_passwordStrength');
            
            if (!password) {
                strengthBar.style.display = 'none';
                return;
            }
            
            strengthBar.style.display = 'block';
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength';
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }

        function um_copyPassword() {
            const password = document.getElementById('um_generatedPassword').textContent;
            navigator.clipboard.writeText(password).then(() => {
                um_showSuccess('Password copied to clipboard');
            });
        }

        function um_showSuccess(message) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function um_showError(message) {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header bg-danger text-white">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong class="me-auto">Error</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function um_formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString.replace(' ', 'T'));
            if (isNaN(date.getTime())) return dateString;
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }

        function um_escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
        }

    </script>
</body>
</html>
