<?php
// Start the session if not already started
session_start();

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
require_once '../../includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> Management</title>
    <link rel="stylesheet" href="/eduroam/assets/css/styles.css">
    <?php include '../../template_parts/head.php'; ?>
</head>
<body class="app-shell">
    <?php include '../../template_parts/nav.php'; ?>
    <main id="content" class="page-shell">
        <?php 
        // get current date and time
        $dateTime = date("F j, Y, g:i a");
        echo "<div class='hero-banner'><div><span class='eyebrow'>Admin Logs</span><h1>Recent Logs</h1><p class='meta mb-0'>Current server time: " . htmlspecialchars($dateTime) . "</p></div></div>";
        ?>
<?php
            echo "<section class='table-card overflow-hide'>";
            include '../../log.php';
            echo "</section>";
        ?>
        </main>

    <?php include '../../template_parts/footer.php'; ?>

</body>
</html>
