<?php

// Include config
require_once 'includes/config.php';
require_once 'includes/guest_accounts.php';

$seo_title = $site_name . ' | Reset Guest Password';
$seo_description = 'Reset the password for an active eduroam Visitor Access guest account using the delivery email address.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/forgotpass.php';
$seo_robots = 'noindex,follow';
$seo_type = 'website';

ensureGuestAccountInfrastructure($pdo);
purgeExpiredGuestAccounts($pdo);

$errors = array(); // Initialize an empty array to store errors
$success_alerts = array();

function sendEmail($to, $fullname, $subject, $message)
{
    global $mail_hostname, $mail_secure, $mail_port, $mail_username, $mail_password, $admin_email, $site_name;

    $Mail = new PHPMailer();
    $Mail->isSMTP();
    $Mail->SMTPAuth   = true;
    $Mail->Host       = $mail_hostname;
    $Mail->SMTPSecure = $mail_secure;
    $Mail->Port       = $mail_port;
    $Mail->Username   = $mail_username;
    $Mail->Password   = $mail_password;
    $Mail->From       = $admin_email;
    $Mail->FromName   = $site_name;
    $Mail->addReplyTo($Mail->From, $Mail->FromName);
    $Mail->isHTML(true);
    $Mail->XMailer = $site_name;
    $Mail->addAddress($to, $fullname);
    $Mail->Subject = $subject;
    $Mail->Body = $message;

    try {
        $Mail->send();
    } catch (Exception $e) {
        throw new Exception('Message could not be sent. Mailer Error: ' . $Mail->ErrorInfo);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reset_link'])) {

    $email = $_POST['email'];

    // check if the email is actually email and not sql injection
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    } else {
        // Check if the email exists in user metadata for a guest account.
        $query = "SELECT username, fullname FROM userinfo WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userExists = $user !== false;

        if ($userExists) {
            // Generate a unique token for the user (you can use a random string generator)
            function generateRandomToken()
            {
                $length = 32;
                $bytes = openssl_random_pseudo_bytes($length);
                return bin2hex($bytes);
            }

            $token = generateRandomToken();

            // Store the token and expiration time in the password_reset table
            $expirationTime = date('Y-m-d H:i:s', strtotime('+1 hour')); // Set the expiration time (e.g., 1 hour)
            $insertQuery = "INSERT INTO password_reset (email, token, expiration_time) VALUES (:email, :token, :expiration_time)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindParam(':email', $email);
            $insertStmt->bindParam(':token', $token);
            $insertStmt->bindParam(':expiration_time', $expirationTime);
            $insertStmt->execute();

            // Send an email with a reset link
            $resetLink = $site_baseurl . "eduroam/forgotpass.php?token=$token"; // Replace with your actual URL
            $subject = "Password Reset | eduroam";
            $message = "Click the following link to reset your password: $resetLink";

            try {
                // Send an email to the user
                sendEmail($email, $user['fullname'], $subject, $message);
                $success_alerts[] = "Password reset instructions sent to your email address.";
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            $errors[] = "Email address not found.";
        }
    }
}

// Check if the form was submitted for password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];

    if ($password === $confirmPassword) {
        // Verify that the token exists and has not expired
        $currentTimestamp = date('Y-m-d H:i:s');
        $query = "SELECT * FROM password_reset WHERE token = :token AND expiration_time > :current_time";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':current_time', $currentTimestamp);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Reset the user's password
            $email = $stmt->fetch(PDO::FETCH_ASSOC)['email'];
            $lookupQuery = "SELECT username FROM userinfo WHERE email = :email LIMIT 1";
            $lookupStmt = $pdo->prepare($lookupQuery);
            $lookupStmt->bindParam(':email', $email);
            $lookupStmt->execute();
            $username = $lookupStmt->fetchColumn();

            if ($username) {
                $updateQuery = "UPDATE radcheck SET value = :password WHERE username = :username AND attribute = 'Cleartext-Password'";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':password', $password);
                $updateStmt->bindParam(':username', $username);
                $updateStmt->execute();

                // Delete the used token from the password_reset table
                $deleteQuery = "DELETE FROM password_reset WHERE token = :token";
                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->bindParam(':token', $token);
                $deleteStmt->execute();

                $success_alerts[] = "Password reset successfully.";
            } else {
                $errors[] = "No guest account is associated with this email address.";
            }
        } else {
            $errors[] = "Invalid or expired token.";
        }
    } else {
        $errors[] = "Passwords do not match.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <?php include 'template_parts/head.php'; ?>
</head>

<body class="app-shell public-shell">
<?php include_once('template_parts/nav.php'); ?>
<main id="content" class="page-shell auth-shell">
        <section class="auth-card">
            <span class="auth-kicker">Password Recovery</span>
            <h1 class="auth-title"><?php echo $site_name ?></h1>
            <p class="auth-subtitle">Use the same email address you used to receive the guest account and we&apos;ll send a reset link.</p>

            <!-- Display Errors (if any) -->
            <?php if (count($errors) > 0) { ?>
                <div class="alert alert-danger text-center">
                    <?php foreach ($errors as $showerror) { ?>
                        <p><?php echo $showerror; ?></p>
                    <?php } ?>
                </div>
            <?php } ?>

            <!-- Display Success Alerts (if any) -->
            <?php if (count($success_alerts) > 0) { ?>
                <div class="alert alert-success text-center">
                    <?php foreach ($success_alerts as $showsuccess) { ?>
                        <p><?php echo $showsuccess; ?></p>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (!isset($_POST['reset_password']) && !isset($_GET['token'])) { ?>
                <form class="mt-4 mb-4" action="" method="post">
                    <div class="form-group mb-2">
                        <label for="email">Email Address Used For Account Delivery:</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" name="send_reset_link" class="btn btn-primary">Submit</button>
                </form>
            <?php } ?>

            <?php if (isset($_GET['token'])) { ?>
                <form class="mt-4 mb-4" action="" method="post">
                    <div class="form-group mt-2">
                        <label for="password">New Password:</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group mt-2">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <input type="hidden" name="token" value="<?php echo isset($_GET['token']) ? $_GET['token'] : ''; ?>">
                    <button type="submit" name="reset_password" class="btn btn-primary mt-2">Reset Password</button>
                </form>
            <?php } ?>

        </section>
</main>

<?php include_once('template_parts/footer.php'); ?>
</body>
</html>
