<?php
session_start();

// If $_SESSION['basic_auth'] is set, redirect to the management page
if (isset($_SESSION['basic_auth'])) {
    header('Location: /eduroam/admin/');
    exit;
}

require_once dirname(__DIR__) . '/includes/config.php';

$seo_title = 'Admin Login | eduroam Visitor Access';
$seo_description = 'Administrator sign-in for the eduroam Visitor Access management dashboard.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/admin/login.php';
$seo_robots = 'noindex,follow';
$seo_type = 'website';

// If request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Sanitize username and password
    $username = htmlspecialchars($username);
    $password = htmlspecialchars($password);

    // Hash the password
    $password = sha1($password);

    // Check if username and password are correct
    $stmt = $pdo->prepare("SELECT * FROM rmadmin WHERE username = :username AND password = :password");
    $stmt->execute(['username' => $username, 'password' => $password]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user;

        $authUser = "idpAdmin";
        $authPass = "idpAdminP4ssw0rd";

        // Pass basic_auth username and password to the management page
        $_SESSION['basic_auth'] = base64_encode($authUser . ':' . $authPass);
        header('Location: /eduroam/admin/');
        exit;
    } else {
        $_SESSION['alert'] = 'Invalid username or password';
        // Redirect to the same page to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <link rel="stylesheet" href="/eduroam/assets/css/styles.css">
    <?php include dirname(__DIR__) . '/template_parts/head.php'; ?>
</head>
<body id="logindiv" class="app-shell public-shell">
    <?php include_once dirname(__DIR__) . '/template_parts/nav.php'; ?>
    <main id="content" class="page-shell auth-shell">
        <div id="login" class="auth-card">
            <div class="auth-logo-wrap">
                <div class="auth-logo-pair">
                    <img src="/eduroam/assets/images/nren-logo.jpg" alt="NREN logo" class="auth-logo">
                    <img src="/eduroam/assets/images/eduroam-logo.png" alt="eduroam logo" class="auth-logo auth-logo--wide">
                </div>
            </div>
            <span class="auth-kicker">Administrator Access</span>
            <h1 class="auth-title"><?php echo $site_name; ?> Management</h1>
            <p class="auth-subtitle">Sign in to manage guest credentials, review recent accounts, and update the delivery settings.</p>
            <!-- If alert in session, show Bootstrap warning alert -->
            <?php if (isset($_SESSION['alert']) && $_SESSION['alert']) : ?>
                <div class="alert alert-warning" role="alert">
                    <?php echo $_SESSION['alert']; ?>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>
            <!-- Username and password form -->
            <form action="" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        </div>
    </main>
    <?php include_once dirname(__DIR__) . '/template_parts/footer.php'; ?>
</body>
</html>
