<?php
$admin_page_title = 'Dashboard';
include __DIR__ . '/includes/admin-shell-header.php';
include dirname(__DIR__) . '/db.php';

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

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1><?php echo htmlspecialchars($site_name); ?> Management Dashboard</h1>
        <div style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;">
            <i class="far fa-clock"></i> <?php echo date("F j, Y, g:i a"); ?>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="dashboard-grid dashboard-grid--4col">
    <!-- Users Stats -->
    <div class="stat-card">
        <h2>Total Users</h2>
        <h3 style="font-size: 1.8rem; font-weight: 700; margin: 0;"><?php echo $totalUsers; ?></h3>
        <p>Active: <?php echo $activeUsers; ?> | Banned: <?php echo $bannedUsers; ?></p>
    </div>

    <!-- Server Stats -->
    <div class="stat-card">
        <h2>Hostname</h2>
        <h3 style="font-size: 1.8rem; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($hostname); ?></h3>
        <p>Uptime: <?php echo $uptime; ?></p>
    </div>

    <!-- Memory Stats -->
    <div class="stat-card">
        <h2>Memory Usage</h2>
        <h3 style="font-size: 1.8rem; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($usedMemory); ?> / <?php echo htmlspecialchars($totalMemory); ?></h3>
        <p>Free: <?php echo htmlspecialchars($freeMemory); ?></p>
    </div>

    <!-- Storage Stats -->
    <div class="stat-card">
        <h2>Disk Usage</h2>
        <h3 style="font-size: 1.8rem; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($usedDisk); ?> / <?php echo htmlspecialchars($totalDisk); ?></h3>
        <p>Free: <?php echo htmlspecialchars($freeDisk); ?></p>
    </div>
</div>

<!-- Bulk Import & Search -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-file-upload"></i> Bulk Import &amp; Search</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <div style="background: rgba(13, 202, 240, 0.1); border: 2px dashed #0dcaf0; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                    <form action="/eduroam/import.php" method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="file" name="upcsv" accept=".csv" required class="form-control" style="max-width: 300px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload CSV
                        </button>
                        <a href="/eduroam/sample.csv" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Download Sample
                        </a>
                    </form>
                </div>
            </div>
            <div class="col-md-4">
                <form action="" method="GET">
                    <div class="input-group">
                        <input type="text" name="search" required value="<?php if (isset($_GET['search'])) { echo htmlspecialchars($_GET['search']); } ?>" class="form-control" placeholder="Search users...">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="/eduroam/admin/" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Search Results
if (isset($_GET['search'])) {
    $filtervalues = $_GET['search'];
    $records_per_page = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
    $offset = ($page - 1) * $records_per_page;

    $query = "SELECT userinfo.username, userinfo.fullname, userinfo.email, userinfo.updateby, userinfo.updatedate FROM userinfo WHERE userinfo.username LIKE :filtervalues OR userinfo.fullname LIKE :filtervalues";
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

    echo '<div class="card mb-4">';
    echo '<div class="card-header bg-primary text-white">';
    echo '<h5 class="mb-0"><i class="fas fa-search"></i> Search Results for "' . htmlspecialchars($_GET['search']) . '"</h5>';
    echo '</div>';
    echo '<div class="card-body">';

    if (count($results) > 0) {
        $countersearch = ($page - 1) * $records_per_page + 1;
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover">';
        echo '<thead><tr><th>#</th><th>Full Name</th><th>Username</th><th>Actions</th></tr></thead><tbody>';

        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . $countersearch . "</td>";
            echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td><button onclick=\"deleteUser('" . htmlspecialchars($row['username']) . "');\" class='btn btn-danger btn-sm'><i class='fas fa-trash'></i></button></td>";
            echo "</tr>";
            $countersearch++;
        }
        echo '</tbody></table></div>';

        echo '<p class="text-center mt-3">Page <strong>' . $page . '</strong> of ' . $total_pages . '</p>';

        // Pagination
        echo '<nav><ul class="pagination justify-content-center">';
        $range = 2;
        $startRange = max(1, $page - $range);
        $endRange = min($total_pages, $page + $range);

        if ($page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($_GET['search']) . '&page=' . ($page - 1) . '"><i class="fas fa-chevron-left"></i></a></li>';
        }

        for ($i = $startRange; $i <= $endRange; $i++) {
            $activeClass = ($i == $page) ? ' active' : '';
            echo '<li class="page-item' . $activeClass . '"><a class="page-link" href="?search=' . urlencode($_GET['search']) . '&page=' . $i . '">' . $i . '</a></li>';
        }

        if ($page < $total_pages) {
            echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($_GET['search']) . '&page=' . ($page + 1) . '"><i class="fas fa-chevron-right"></i></a></li>';
        }
        echo '</ul></nav>';
    } else {
        echo '<div class="text-center py-5">';
        echo '<i class="fas fa-search-minus" style="font-size: 3rem; color: #dee2e6; margin-bottom: 1rem; display: block;"></i>';
        echo '<h4>No Results Found</h4>';
        echo '<p class="text-muted">Try adjusting your search criteria</p>';
        echo '</div>';
    }
    echo '</div></div>';
}

// Pending Eduroam Requests
$sql = "SELECT * FROM eduroam_request";
$result = mysqli_query($conn, $sql);

echo '<div class="card mb-4">';
echo '<div class="card-header bg-primary text-white">';
echo '<h5 class="mb-0"><i class="fas fa-user-clock"></i> Pending Eduroam Requests</h5>';
echo '</div>';
echo '<div class="card-body">';

if ($result && mysqli_num_rows($result) > 0) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover">';
    echo '<thead><tr><th>Full Name</th><th>Email</th><th>Created At</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row["fullname"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["org_email"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["created_at"]) . "</td>";
        echo "<td>";
        echo "<button onclick=\"approveRequest('" . htmlspecialchars($row['id']) . "');\" class='btn btn-success btn-sm me-2'><i class='fas fa-check'></i></button>";
        echo "<button onclick=\"rejectRequest('" . htmlspecialchars($row['id']) . "');\" class='btn btn-danger btn-sm'><i class='fas fa-times'></i></button>";
        echo "</td>";
        echo "</tr>";
    }
    echo '</tbody></table></div>';
} else {
    echo '<div class="text-center py-5">';
    echo '<i class="fas fa-inbox" style="font-size: 3rem; color: #dee2e6; margin-bottom: 1rem; display: block;"></i>';
    echo '<h4>No Pending Requests</h4>';
    echo '<p class="text-muted">All requests have been processed</p>';
    echo '</div>';
}
echo '</div></div>';
?>

<script>
function deleteUser(username) {
    if (confirm('Are you sure you want to delete this user?')) {
        $.ajax({
            type: 'POST',
            url: '/eduroam/delete_user.php',
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
            url: '/eduroam/approve.php',
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
            url: '/eduroam/reject.php',
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

<?php include __DIR__ . '/includes/admin-shell-footer.php'; ?>
