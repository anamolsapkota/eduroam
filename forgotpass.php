<?php

// Include config
require_once 'includes/config.php';

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
        // Check if the email exists in your user database (e.g., radcheck table)
        $query = "SELECT * FROM radcheck WHERE username = :email";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $userExists = $stmt->rowCount() > 0;

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
                sendEmail($email, "User", $subject, $message);
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
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Update the user's password in your user database (e.g., radcheck table)
            $updateQuery = "UPDATE radcheck SET value = :password WHERE username = :email";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':password', $password);
            $updateStmt->bindParam(':email', $email);
            $updateStmt->execute();

            // Delete the used token from the password_reset table
            $deleteQuery = "DELETE FROM password_reset WHERE token = :token";
            $deleteStmt = $pdo->prepare($deleteQuery);
            $deleteStmt->bindParam(':token', $token);
            $deleteStmt->execute();

            $success_alerts[] = "Password reset successfully.";
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
    <title>Forgot Password | <?php echo $site_name ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
<?php include_once('template_parts/nav.php'); ?>
<div id="content">
    <div class="row">
        <div class="col-md-4 offset-md-4 form login-form">
            <div class="mr-auto ml-auto text-center mt-4 mb-4"></div>
            <h2 class="text-center"><?php echo $site_name ?></h2>
            <p class="text-center">Reset your password.</p>

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
                <!-- HTML Form for Sending Reset Link -->
                <form class="mt-4 mb-4" action="" method="post">
                    <div class="form-group mb-2">
                        <label for="email">Email Address:</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" name="send_reset_link" class="btn btn-primary">Submit</button>
                </form>
            <?php } ?>

            <?php if (isset($_GET['token'])) { ?>
                <!-- HTML Form for Password Reset -->
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

        </div>
    </div>
    <div style="height: 100px;"></div>
</div>

<?php include_once('template_parts/footer.php'); ?>
</body>
</html>
