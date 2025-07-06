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
require_once '../../includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> Management</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet"> -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
    <?php include '../../template_parts/nav.php'; ?>
    <div id="content" class="container mt-4 mb-4">
        <?php 
        // get current date and time
        $dateTime = date("F j, Y, g:i a");
        echo "Current Server Time: ".$dateTime;
        ?>
<?php
            echo "<div class='container mt-4 overflow-hide'>";
            echo "<h2>Recent Logs</h2>";
            include '../../log.php';
            echo "</div>";
        ?>
        </div>

    <?php include '../../template_parts/footer.php'; ?>
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
