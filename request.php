<?php

// include config file
require_once 'includes/config.php';
require_once 'includes/email.php';
require_once 'includes/guest_accounts.php';

$seo_title = $site_name . ' | Request Guest Access';
$seo_description = 'Request a temporary eduroam Visitor Access account and receive secure guest Wi-Fi credentials by email for 24-hour use.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/request.php';
$seo_type = 'website';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["org_email"]) && isset($_POST["fullname"])) {
    // Retrieve and sanitize form data
    $email = trim(strtolower($_POST["org_email"]));
    $fullname = trim($_POST["fullname"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $output = "Please provide a valid email address.";
    } elseif ($fullname === '') {
        $output = "Please provide your full name.";
    } else {
        try {
            ensureGuestAccountInfrastructure($pdo);
            purgeExpiredGuestAccounts($pdo);

            $checkGuestStmt = $pdo->prepare("SELECT COUNT(*) FROM guest_accounts WHERE delivery_email = :email AND expires_at > NOW()");
            $checkGuestStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $checkGuestStmt->execute();
            $activeGuestCount = (int) $checkGuestStmt->fetchColumn();

            $checkUserStmt = $pdo->prepare("SELECT COUNT(*) FROM userinfo WHERE email = :email");
            $checkUserStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $checkUserStmt->execute();
            $existingUserCount = (int) $checkUserStmt->fetchColumn();

            if ($activeGuestCount > 0 || $existingUserCount > 0) {
                $output = "This email address already has an active account.";
            } else {
                $username = generateGuestUsername($pdo, $fullname);
                $password = generateRandomPassword(8);
                $createdAt = date("Y-m-d H:i:s");
                $expiresAtDb = date("Y-m-d H:i:s", strtotime('+24 hours'));
                $expiresAtTimestamp = strtotime($expiresAtDb);
                $expiresAtRadius = gmdate("D j M Y H:i:s", $expiresAtTimestamp) . " UTC";
                $expiresAtDisplay = date("D j M Y H:i:s T", $expiresAtTimestamp);

                $pdo->beginTransaction();

                $insertRadcheckStmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (:username, 'Cleartext-Password', ':=', :password)");
                $insertRadcheckStmt->bindParam(':username', $username, PDO::PARAM_STR);
                $insertRadcheckStmt->bindParam(':password', $password, PDO::PARAM_STR);
                $insertRadcheckStmt->execute();

                $insertExpiryStmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (:username, 'Expiration', ':=', :expiration)");
                $insertExpiryStmt->bindParam(':username', $username, PDO::PARAM_STR);
                $insertExpiryStmt->bindParam(':expiration', $expiresAtRadius, PDO::PARAM_STR);
                $insertExpiryStmt->execute();

                $insertUserinfoStmt = $pdo->prepare("INSERT INTO userinfo (username, fullname, email, updateby, updatedate) VALUES (:username, :fullname, :email, 'auto_request', :updatedate)");
                $insertUserinfoStmt->bindParam(':username', $username, PDO::PARAM_STR);
                $insertUserinfoStmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
                $insertUserinfoStmt->bindParam(':email', $email, PDO::PARAM_STR);
                $insertUserinfoStmt->bindParam(':updatedate', $createdAt, PDO::PARAM_STR);
                $insertUserinfoStmt->execute();

                $insertGuestStmt = $pdo->prepare("INSERT INTO guest_accounts (username, delivery_email, fullname, created_at, expires_at) VALUES (:username, :email, :fullname, :created_at, :expires_at)");
                $insertGuestStmt->bindParam(':username', $username, PDO::PARAM_STR);
                $insertGuestStmt->bindParam(':email', $email, PDO::PARAM_STR);
                $insertGuestStmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
                $insertGuestStmt->bindParam(':created_at', $createdAt, PDO::PARAM_STR);
                $insertGuestStmt->bindParam(':expires_at', $expiresAtDb, PDO::PARAM_STR);
                $insertGuestStmt->execute();

                $subject = 'Eduroam Access Information';
                $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background-color: #007BFF; color: #fff; padding: 20px; text-align: center;">
                            <h1>Eduroam Access Information</h1>
                        </div>
                        <div style="padding: 20px;">
                            <p>Dear ' . htmlspecialchars($fullname) . ',</p>
                            <p>Your 24-hour guest eduroam account has been created automatically.</p>
                            <p>Here are the details to connect to Eduroam:</p>
                            <ul>
                                <li><strong>Network Name (SSID):</strong> eduroam</li>
                                <li><strong>Username:</strong> ' . htmlspecialchars($username) . '</li>
                                <li><strong>Password:</strong> ' . htmlspecialchars($password) . '</li>
                                <li><strong>Valid Until:</strong> ' . htmlspecialchars($expiresAtDisplay) . '</li>
                            </ul>
                            <p>Select the "Eduroam" network on your device and sign in with the guest username and password above.</p>
                            <p>If you need to reset the password before the account expires, use this link:
                                <a href="' . htmlspecialchars($site_baseurl) . 'eduroam/forgotpass.php">Reset Password</a></p>
                            <p>Sincerely,</p>
                            <p>' . htmlspecialchars($site_name) . '</p>
                        </div>
                        </div>';

                $emailResult = sendEmail($email, $fullname, $subject, $message);

                if (!$emailResult['success']) {
                    $pdo->rollBack();
                    $output = "We could not send your credentials right now: " . $emailResult['error'];
                } else {
                    $pdo->commit();
                    $output = "Your guest eduroam account has been created and the credentials have been emailed to you.";
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Guest account auto-provisioning failed: " . $e->getMessage());
            $output = "Error, please try again later.";
        }
    }
} else {
    $output = "";
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/styles.css">
    <?php include 'template_parts/head.php'; ?>
</head>

<body class="app-shell public-shell">
<?php
    include_once('template_parts/nav.php');
?>
<main id="content" class="page-shell request-shell">
    <section id="requestform" class="request-layout">
        <div class="request-showcase">
            <div class="hero-brand-lockup">
                <div class="hero-logo-pair">
                    <img src="/eduroam/assets/images/nren-logo.jpg" alt="NREN logo" class="hero-logo">
                    <img src="/eduroam/assets/images/eduroam-logo.png" alt="eduroam logo" class="hero-logo hero-logo--wide">
                </div>
            </div>
            <span class="eyebrow">Guest Wi-Fi Access</span>
            <h1>Secure guest eduroam access with a professional, instant workflow.</h1>
            <p>eduroam Visitor Access enables higher education and research institute visitors to access the secure and trusted eduroam Wi-Fi network. The service can provide temporary access to the eduroam network in a simple and secure manner.</p>

            <div class="request-metrics">
                <div class="request-metric">
                    <strong>24 Hours</strong>
                    <span>Automatic access period</span>
                </div>
                <div class="request-metric">
                    <strong>Instant</strong>
                    <span>Credential delivery by email</span>
                </div>
                <div class="request-metric">
                    <strong>Managed</strong>
                    <span>Provisioning and expiry lifecycle</span>
                </div>
            </div>

            <div class="request-steps">
                <div class="request-step">
                    <span>1</span>
                    <div>
                        <h2>Submit your details</h2>
                        <p>Enter your full name and the personal email address where credentials should be sent.</p>
                    </div>
                </div>
                <div class="request-step">
                    <span>2</span>
                    <div>
                        <h2>Receive credentials</h2>
                        <p>Your guest username and password are issued automatically and emailed after creation.</p>
                    </div>
                </div>
            </div>

            <div class="request-info-card">
                <h2>Included with every guest account</h2>
                <p>If your higher education or research institute uses eduroam Visitor Access you can sign in with your personal (educational) credentials and use this service.</p>
                <ul class="feature-list">
                    <li>A generated eduroam username in the `@eva.nren.net.np` domain.</li>
                    <li>A temporary password sent to your personal email address.</li>
                    <li>Automatic account expiry after 24 hours for controlled visitor access.</li>
                </ul>
            </div>
        </div>

        <div class="request-panel">
            <div class="app-hero">
                <span class="auth-kicker">Guest Access</span>
                <h2 class="auth-title request-title">Request a 24-hour eduroam account</h2>
                <p class="auth-subtitle">Use the name you want reflected in the generated username and provide the email address where the access details should be delivered.</p>
            </div>

            <?php if ($output): ?>
                <div class="alert alert-warning" role="alert">
                    <?php echo $output; ?>
                </div>
            <?php endif; ?>

            <?php if ($output !== "Your guest eduroam account has been created and the credentials have been emailed to you."): ?>
                <form id="reqform" class="request-form" action="" method="POST">
                    <div class="mb-3">
                        <label for="fullname" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="fullname" placeholder="For example: Anamol Sapkota"
                            pattern="^\S(.*\S)?$" style="text-transform: capitalize;" name="fullname" required>
                    </div>
                    <div class="mb-3">
                        <label for="org_email" class="form-label">Personal Email Address</label>
                        <input type="email" class="form-control" id="org_email" placeholder="name@example.com" name="org_email"
                            required>
                    </div>
                    <div class="request-note">
                        The generated guest account is valid for 24 hours from creation. If needed, you can reset the password during the active access period.
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Request Access</button>
                </form>
                <p class="mt-3 mb-0"><a class="subtle-link" href="/eduroam/forgotpass.php">Forgot Password?</a></p>
            <?php else: ?>
                <div class="request-success">
                    <p class="mb-0">Check your inbox and spam folder for the credentials email, then join the `eduroam` SSID using the provided guest username and password.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>


<?php 
    include_once('template_parts/footer.php');
?>

</body>
</html>
