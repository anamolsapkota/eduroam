<?php
// Start the session if not already started
session_start();

// Check if 'basic_auth' session variable is set
if(!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    // Redirect to the login page
    header('Location: login.php');
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
require_once 'includes/config.php';

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

    include 'db.php';
    // Total Users (count from radcheck table)
    $queryTotalUsers = "SELECT COUNT(*) AS totalUsers FROM userinfo u INNER JOIN radcheck r ON u.username = r.username;";
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
    <link rel="stylesheet" href="assets/css/styles.css">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet"> -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        function deleteUser(username) {
            if (confirm('Are you sure you want to delete this user?')) {
                // Make an AJAX request to delete_user.php
                $.ajax({
                    type: 'POST',
                    url: 'delete_user.php',
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

        // function to approve request
        function approveRequest(id) {
            if (confirm('Are you sure you want to approve this request?')) {
                // Make an AJAX request to approve.php
                $.ajax({
                    type: 'POST',
                    url: 'approve.php',
                    data: {
                        id: id // Pass the ID as a parameter
                    },
                    dataType: 'json', // Expect JSON response
                    success: function (response) {
                        console.log(response); // Debugging: Log the response
                        if (response.status === 'success') {
                            // Request approved successfully, you can update the UI or show a message
                            alert(response.message);
                            // Reload the page or update the request list
                            location.reload();
                        } else {
                            // Handle error case, show an error message
                            alert(response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', error); // Debugging: Log the error
                        console.error('Response Text:', xhr.responseText); // Debugging: Log the response
                        
                        // Try to parse the response as JSON
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            alert('Error: ' + errorResponse.message);
                        } catch (e) {
                            // If it's not JSON, show the raw response
                            alert('An error occurred while approving the request. Server response: ' + xhr.responseText.substring(0, 200));
                        }
                    }
                });
            }
        }

        // function to reject request
        function rejectRequest(id) {
            if (confirm('Are you sure you want to reject this request?')) {
                // Make an AJAX request to reject.php
                $.ajax({
                    type: 'POST',
                    url: 'reject.php',
                    data: {
                        id: id // Pass the ID as a parameter
                    },
                    dataType: 'json', // Expect JSON response
                    success: function (response) {
                        if (response.status === 'success') {
                            // Request rejected successfully, you can update the UI or show a message
                            alert(response.message);
                            // Reload the page or update the request list
                            location.reload();
                        } else {
                            // Handle error case, show an error message
                            alert(response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', error); // Debugging: Log the error
                        console.error('Response Text:', xhr.responseText); // Debugging: Log the response
                        // Try to parse the response as JSON
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            alert('Error: ' + errorResponse.message);
                        } catch (e) {
                            // If it's not JSON, show the raw response
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
    <div id="content" class="container mt-4 mb-4">
        <?php 
        // get current date and time
        $dateTime = date("F j, Y, g:i a");
        echo "Current Server Time: ".$dateTime;
        ?>

<div class="row mt-2">
    <!-- User Column -->
    <div class="col-md-3">
        <div class="card mt-1 alert alert-primary">
            <div class="card-body">
                <h2 class="card-title">Eduroam Users</h2>
                <p>Total Users:
                    <?php echo $totalUsers; ?>
                </p>
                <p>Active Users:
                    <?php echo $activeUsers; ?>
                </p>
                <p>Banned Users:
                    <?php echo $bannedUsers; echo '<br /><br />'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Server Column -->
    <div class="col-md-3">
        <div class="card mt-1 alert alert-primary">
            <div class="card-body">
                <h2 class="card-title">Server</h2>
                <p>Date:
                    <?php echo $date; ?>
                </p>
                <p>Hostname:
                    <?php echo $hostname; ?>
                </p>
                <p>Uptime:
                    <?php echo $uptime; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Memory Column -->
    <div class="col-md-3">
        <div class="card mt-1 alert alert-primary">
            <div class="card-body">
                <h2 class="card-title">Memory</h2>
                <p>Total Memory:
                    <?php echo $totalMemory; ?>
                </p>
                <p>Free Memory:
                    <?php echo $freeMemory; ?>
                </p>
                <p>Used Memory:
                    <?php echo $usedMemory; echo '<br /><br />'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Storage Column -->
    <div class="col-md-3">
        <div class="card mt-1 alert alert-primary">
            <div class="card-body">
                <h2 class="card-title">Storage</h2>
                <p>Total Disk:
                    <?php echo $totalDisk; ?>
                </p>
                <p>Free Disk:
                    <?php echo $freeDisk; ?>
                </p>
                <p>Used Disk:
                    <?php echo $usedDisk; echo '<br /><br />'; ?>
                </p>
            </div>
        </div>
    </div>
</div>

        <div class="m-2 row alert-info align-middle border">
            <!-- <h4 class="mt-4 text-center">Bulk Import</h4> -->
            <div class="col-md-8">
                <div class="container mt-2">
                    <div class="row p-3 mb-2">
                        <form action="import.php" method="post" enctype="multipart/form-data">
                            <input type="file" name="upcsv" accept=".csv" required="">
                            <input type="submit" value="Upload">
                            <a href="sample.csv" class="border text-dark p-1" type="clear">Download
                                Sample</a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="container mt-4">
                    <form action="" method="GET">
                        <div class="input-group mb-3 mt-4">
                            <input type="text" name="search" required="" value="<?php if(isset($_GET['search'])){ echo $_GET['search'];} ?>" class="form-control" placeholder="Search data">
                            <button type="submit" class="btn btn-primary">Search</button>&nbsp;&nbsp;
                            <a href="/eduroam/management.php" class="btn btn-warning" type="clear">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                $query = "SELECT userinfo.username, userinfo.fullname, userinfo.email, radcheck.value, userinfo.updateby, userinfo.updatedate FROM userinfo INNER JOIN radcheck ON userinfo.username = radcheck.username WHERE userinfo.username LIKE :filtervalues OR userinfo.fullname LIKE :filtervalues";
            
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
            
                echo '<div class="container mt-4 table-responsive">';
                echo '<h2>Search Results</h2>';
            
                if (count($results) > 0) {
                    $countersearch = ($page - 1) * $records_per_page + 1;
                    echo '<table class="table table-bordered table-striped">';
                    echo '<thead class="thead-dark">';
                    echo '<tr><th>#</th><th>Full Name</th><th>Username</th>';
                    if (isset($_GET['password']) && $_GET['password'] == 'show') {
                        echo '<th>Password</th>';
                    }
                    echo '<th>Actions</th></tr>';
                    echo '</thead>';
                    foreach ($results as $row) {
                        echo "<tr>";
                        echo "<td>" . $countersearch . "</td>";
                        echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                        if (isset($_GET['password']) && $_GET['password'] == 'show') {
                            echo "<td>" . htmlspecialchars($row['value']) . "</td>";
                        }
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
                echo '</div>';
            }

            // Select all from eduroam_request table
            $sql = "SELECT * FROM eduroam_request";
            $result = mysqli_query($conn, $sql);

            echo '<div class="container mt-4 table-responsive">';
            echo '<h2>Eduroam Requests</h2>';

            if (mysqli_num_rows($result) > 0) {
                echo '<table class="table table-bordered table-striped">';
                echo '<thead class="thead-dark">';
                echo '<tr><th>Full Name</th><th>Email</th><th>Created At</th><th>Actions</th></tr>';
                echo '</thead>';
                echo '<tbody>';
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["fullname"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["org_email"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["created_at"]) . "</td>";
                    echo "<td><a href='javascript:void(0);' onclick=\"approveRequest('" . htmlspecialchars($row['id']) . "');\" class='btn btn-primary' data-hint='Approve User Account'>Approve</a>&nbsp;<a href='javascript:void(0);' onclick=\"rejectRequest('" . htmlspecialchars($row['id']) . "');\" class='btn btn-danger' data-hint='Reject User Account'>Reject</a></td>";
                    echo "</tr>";
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo "0 results";
            }
            echo '</div>';

            // join userinfo and radcheck table by username and populate all data limit 10 latest
            $sql = "SELECT userinfo.fullname, userinfo.username, userinfo.email, radcheck.value, userinfo.updateby, userinfo.updatedate FROM userinfo JOIN radcheck ON userinfo.username = radcheck.username ORDER BY userinfo.updatedate DESC LIMIT 10";

            $result = mysqli_query($conn, $sql);

            if(mysqli_num_rows($result) > 0 ) {
                echo '<div class="container mt-4 table-responsive">';
                echo '<h2>Latest 10 Users</h2>';
                echo '<table class="table table-bordered table-striped">';
                echo '<thead class="thead-dark">';
                echo '<tr><th>Full Name</th><th>Username</th><th>Email</th><th>Updated by</th><th>Updated At</th></tr>';
                echo '</thead>';
                echo '<tbody>';
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["fullname"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["updateby"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["updatedate"]) . "</td>";
                    echo "</tr>";
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            } else {
                echo "0 results";
            }

        } else {
            header('WWW-Authenticate: Basic realm="Restricted Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Access Denied';
        }
        ?>
    </div>

    <?php include 'template_parts/footer.php'; ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var attribution = document.createElement('div');
            attribution.innerHTML = '<span class="text-white-50">Designed and Developed by <a href="https://sapkotaanamol.com.np" target="_blank" class="text-white-50" style="text-decoration:none;">Anamol Sapkota</a></span>';
            var designedDeveloped = document.getElementById('designed-developed');
            if (designedDeveloped) {
                designedDeveloped.appendChild(attribution);
            }
        });
    </script>

</body>
</html>
