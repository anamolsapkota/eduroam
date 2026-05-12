<?php

// Include config
require_once 'includes/config.php';

$errors = array();
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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    } else {
        $query = "SELECT * FROM radcheck WHERE username = :email";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $userExists = $stmt->rowCount() > 0;

        if ($userExists) {
            function generateRandomToken()
            {
                $length = 32;
                $bytes = openssl_random_pseudo_bytes($length);
                return bin2hex($bytes);
            }

            $token = generateRandomToken();

            $expirationTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $insertQuery = "INSERT INTO password_reset (email, token, expiration_time) VALUES (:email, :token, :expiration_time)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindParam(':email', $email);
            $insertStmt->bindParam(':token', $token);
            $insertStmt->bindParam(':expiration_time', $expirationTime);
            $insertStmt->execute();

            $resetLink = $site_baseurl . "eduroam/forgotpass.php?token=$token";
            $subject = "Password Reset | eduroam";
            $message = "Click the following link to reset your password: $resetLink";

            try {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];

    if ($password === $confirmPassword) {
        $currentTimestamp = date('Y-m-d H:i:s');
        $query = "SELECT * FROM password_reset WHERE token = :token AND expiration_time > :current_time";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':current_time', $currentTimestamp);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $email = $stmt->fetch(PDO::FETCH_ASSOC)['email'];

            $updateQuery = "UPDATE radcheck SET value = :password WHERE username = :email";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':password', $password);
            $updateStmt->bindParam(':email', $email);
            $updateStmt->execute();

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

$showResetForm = isset($_GET['token']);
$showSuccess = count($success_alerts) > 0 && !$showResetForm && !isset($_POST['send_reset_link']);
$passwordResetSuccess = count($success_alerts) > 0 && isset($_POST['reset_password']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sora', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f0f4f8;
            color: #1e293b;
        }

        .page-header {
            background: linear-gradient(135deg, #0b1929 0%, #152F4F 100%);
            color: #fff;
            padding: 2.5rem 0;
            text-align: center;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .page-header .brand {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64b5f6;
            margin-bottom: 0.75rem;
            display: block;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .reset-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            width: 100%;
            max-width: 480px;
            padding: 2rem;
        }

        .reset-card h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .reset-card .subtitle {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #334155;
            margin-bottom: 0.4rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            padding: 0.65rem 0.9rem;
            font-size: 0.9rem;
            font-family: 'Sora', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-submit {
            background: #0d3b6f;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            font-family: 'Sora', sans-serif;
            width: 100%;
            transition: background 0.2s;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: #0b2e57;
        }

        .links-row {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .links-row a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .links-row a:hover {
            text-decoration: underline;
        }

        .success-block {
            text-align: center;
            padding: 1.5rem 0;
        }

        .success-block i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .success-block i.fa-check-circle { color: #10b981; }
        .success-block i.fa-envelope { color: #3b82f6; }

        .success-block h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .success-block p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .info-tip {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-top: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.82rem;
            color: #475569;
        }

        .info-tip i {
            color: #3b82f6;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .page-footer {
            background: #152F4F;
            color: #94a3b8;
            padding: 1.25rem 0;
            text-align: center;
            font-size: 0.8rem;
        }

        .page-footer a {
            color: #fff;
            text-decoration: none;
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 3rem 1rem;
            }

            .page-header {
                padding: 3rem 0;
            }

            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="container">
            <span class="brand"><?php echo htmlspecialchars($site_name); ?></span>
            <?php if ($showResetForm): ?>
                <h1>Reset Your Password</h1>
                <p>Choose a new password for your eduroam account</p>
            <?php else: ?>
                <h1>Forgot Password</h1>
                <p>Recover access to your eduroam account</p>
            <?php endif; ?>
        </div>
    </header>

    <div class="main-content">
        <div class="reset-card">

            <?php if ($passwordResetSuccess): ?>
                <div class="success-block">
                    <i class="fas fa-check-circle"></i>
                    <h3>Password Reset Successfully</h3>
                    <p>Your password has been updated. You can now connect to eduroam with your new credentials.</p>
                </div>
                <div class="links-row">
                    <a href="/eduroam/request.php"><i class="fas fa-wifi me-1"></i>Request Page</a>
                </div>

            <?php elseif ($showResetForm): ?>
                <h2>Set New Password</h2>
                <p class="subtitle">Enter your new password below.</p>

                <?php if (count($errors) > 0): ?>
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;">
                        <?php foreach ($errors as $err): ?>
                            <div><i class="fas fa-exclamation-triangle me-1"></i><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                    </div>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    <button type="submit" name="reset_password" class="btn-submit">
                        <i class="fas fa-lock me-2"></i>Reset Password
                    </button>
                </form>

            <?php elseif (count($success_alerts) > 0): ?>
                <div class="success-block">
                    <i class="fas fa-envelope"></i>
                    <h3>Check Your Email</h3>
                    <p><?php echo htmlspecialchars($success_alerts[0]); ?></p>
                </div>
                <div class="info-tip">
                    <i class="fas fa-info-circle"></i>
                    <span>The reset link will expire in 1 hour. If you don't see the email, check your spam folder.</span>
                </div>
                <div class="links-row">
                    <a href="/eduroam/request.php"><i class="fas fa-wifi me-1"></i>Request Page</a>
                </div>

            <?php else: ?>
                <h2>Reset Your Password</h2>
                <p class="subtitle">Enter the email address associated with your eduroam account.</p>

                <?php if (count($errors) > 0): ?>
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;">
                        <?php foreach ($errors as $err): ?>
                            <div><i class="fas fa-exclamation-triangle me-1"></i><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="e.g. ram@nec.edu.np" required>
                    </div>
                    <button type="submit" name="send_reset_link" class="btn-submit">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>
                </form>

                <div class="links-row">
                    <a href="/eduroam/request.php"><i class="fas fa-wifi me-1"></i>Request eduroam</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <footer class="page-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All Rights Reserved.</p>
            <p style="margin-top: 0.25rem;">Designed and Developed by <a href="https://sapkotaanamol.com.np" target="_blank">Anamol Sapkota</a></p>
        </div>
    </footer>
</body>
</html>
