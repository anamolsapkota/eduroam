<?php

// include config file
require_once 'includes/config.php';
require_once 'includes/email.php';
require_once 'includes/guest_accounts.php';

$seo_title = $site_name . ' | Request Guest Access';
$seo_description = 'Request a temporary eduroam Visitor Access account and receive secure guest Wi-Fi credentials by email for ' . guestAccountDurationLabel() . ' use.';
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
            $guestAccount = createGuestAccount($pdo, $fullname, $email, 'auto_request');

            $subject = 'Eduroam Access Information';
            $message = buildGuestCredentialEmail(
                $guestAccount['fullname'],
                $guestAccount['username'],
                $guestAccount['password'],
                $guestAccount['expires_at_display'],
                $site_baseurl,
                $site_name
            );

            $emailResult = sendEmail($email, $fullname, $subject, $message);

            if (!$emailResult['success']) {
                $pdo->prepare("DELETE FROM radcheck WHERE username = :username")->execute([':username' => $guestAccount['username']]);
                $pdo->prepare("DELETE FROM userinfo WHERE username = :username")->execute([':username' => $guestAccount['username']]);
                $pdo->prepare("DELETE FROM guest_accounts WHERE username = :username")->execute([':username' => $guestAccount['username']]);
                $output = "We could not send your credentials right now: " . $emailResult['error'];
            } else {
                $output = "Your guest eduroam account has been created and the credentials have been emailed to you.";
            }
        } catch (InvalidArgumentException | RuntimeException $e) {
            $output = $e->getMessage();
        } catch (Throwable $e) {
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
                    <strong><?php echo htmlspecialchars(guestAccountDurationLabel()); ?></strong>
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
                    <li>Automatic account expiry after <?php echo htmlspecialchars(guestAccountDurationLabel()); ?> for controlled visitor access.</li>
                </ul>
            </div>
        </div>

        <div class="request-panel">
            <div class="app-hero">
                <span class="auth-kicker">Guest Access</span>
                <h2 class="auth-title request-title">Request a <?php echo htmlspecialchars(guestAccountDurationLabel()); ?> eduroam account</h2>
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
                        The generated guest account is valid for <?php echo htmlspecialchars(guestAccountDurationLabel()); ?> from creation. If needed, you can reset the password during the active access period.
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
