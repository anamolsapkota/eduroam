<?php
session_start();

// If $_SESSION['basic_auth'] is set, redirect to the management page
if (isset($_SESSION['basic_auth'])) {
    header('Location: /eduroam/management.php');
    exit;
}

require_once('includes/config.php');

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
        header('Location: /eduroam/management.php');
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
    <title><?php echo $site_name; ?> Management</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body id="logindiv">
    <div style="position: relative; width: 100vw; height: 100vh; margin: 0; padding: 0; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('https://source.unsplash.com/1600x900/?education'); background-size: cover; background-position: center;"></div>
        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6);"></div>
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"  id="login">
            <h5 class="mt-4 mb-4 text-center"><?php echo $site_name; ?> Management</h5>
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
    </div>
</body>
</html>
