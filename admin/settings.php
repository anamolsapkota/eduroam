<?php
// Form POST handlers must run before any HTML output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth']) || !isset($_SESSION['user'])) {
    header('Location: /eduroam/login.php');
    exit;
}

// Need config for form handling (config loads $pdo)
require_once dirname(__DIR__) . '/includes/config.php';

$alerts = [];
$smtpTestResult = null;
$test_email_recipient = '';

function upsertSetting(PDO $pdo, $key, $value)
{
    $stmt = $pdo->prepare(
        "INSERT INTO rmsettings (vkey, data) VALUES (:vkey, :data)
         ON DUPLICATE KEY UPDATE data = VALUES(data)"
    );
    $stmt->execute([
        ':vkey' => $key,
        ':data' => $value,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email_recipient = trim($_POST['test_email_recipient'] ?? '');
    $formData = [
        'site_name' => trim($_POST['site_name'] ?? ''),
        'admin_email' => trim($_POST['admin_email'] ?? ''),
        'mail_hostname' => trim($_POST['mail_hostname'] ?? ''),
        'mail_port' => trim($_POST['mail_port'] ?? ''),
        'mail_secure' => trim($_POST['mail_secure'] ?? 'tls'),
        'mail_username' => trim($_POST['mail_username'] ?? ''),
        'mail_password' => trim($_POST['mail_password'] ?? ''),
        'mail_send' => trim($_POST['mail_send'] ?? 'enable'),
    ];

    if ($formData['site_name'] === '') {
        $alerts[] = ['type' => 'danger', 'message' => 'Site name is required.'];
    }

    if ($formData['admin_email'] === '' || !filter_var($formData['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $alerts[] = ['type' => 'danger', 'message' => 'A valid admin email is required.'];
    }

    if ($formData['mail_hostname'] === '') {
        $alerts[] = ['type' => 'danger', 'message' => 'SMTP host is required.'];
    }

    if ($formData['mail_port'] === '' || !ctype_digit($formData['mail_port'])) {
        $alerts[] = ['type' => 'danger', 'message' => 'SMTP port must be numeric.'];
    }

    if (!in_array($formData['mail_secure'], ['tls', 'ssl', 'none'], true)) {
        $alerts[] = ['type' => 'danger', 'message' => 'SMTP security must be tls, ssl, or none.'];
    }

    if ($formData['mail_username'] === '') {
        $alerts[] = ['type' => 'danger', 'message' => 'SMTP username is required.'];
    }

    if ($formData['mail_password'] === '') {
        $alerts[] = ['type' => 'danger', 'message' => 'SMTP password is required.'];
    }

    if (!in_array($formData['mail_send'], ['enable', 'disable'], true)) {
        $alerts[] = ['type' => 'danger', 'message' => 'Mail send must be enable or disable.'];
    }

    if (empty($alerts)) {
        try {
            foreach ($formData as $key => $value) {
                upsertSetting($pdo, $key, $value);
            }

            $site_name = $formData['site_name'];
            $admin_email = $formData['admin_email'];
            $mail_hostname = $formData['mail_hostname'];
            $mail_port = $formData['mail_port'];
            $mail_secure = $formData['mail_secure'];
            $mail_username = $formData['mail_username'];
            $mail_password = $formData['mail_password'];
            $mail_send = $formData['mail_send'];

            $alerts[] = ['type' => 'success', 'message' => 'Settings saved successfully.'];
        } catch (PDOException $e) {
            $alerts[] = ['type' => 'danger', 'message' => 'Failed to save settings.'];
            error_log('Failed to save settings: ' . $e->getMessage());
        }
    }

    if (isset($_POST['test_email'])) {
        require_once dirname(__DIR__) . '/includes/email.php';

        if ($mail_send === 'disable') {
            $smtpTestResult = ['type' => 'warning', 'message' => 'Mail sending is currently disabled in settings.'];
        } elseif ($test_email_recipient === '' || !filter_var($test_email_recipient, FILTER_VALIDATE_EMAIL)) {
            $smtpTestResult = ['type' => 'danger', 'message' => 'Enter a valid recipient email address for the test email.'];
        } else {
            $smtpTest = sendEmail(
                $test_email_recipient,
                $_SESSION['user']['fullname'] ?? 'Admin',
                'Test Email | ' . $site_name,
                '<p>This is a test email from the eduroam dashboard settings page.</p>'
            );

            if ($smtpTest['success']) {
                $smtpTestResult = ['type' => 'success', 'message' => 'Test email sent successfully to ' . $test_email_recipient . '.'];
            } else {
                $smtpTestResult = ['type' => 'danger', 'message' => 'Test email failed: ' . $smtpTest['error']];
            }
        }
    }
}

// Now include the shell header (config.php already loaded above, shell will skip duplicate)
$admin_page_title = 'Settings';
$seo_title = 'Settings';
include __DIR__ . '/includes/admin-shell-header.php';

// Extra auth guard
if (!isset($_SESSION['user'])) {
    echo '<div class="alert alert-danger">Session expired. Please <a href="/eduroam/login.php">log in</a> again.</div>';
    include __DIR__ . '/includes/admin-shell-footer.php';
    exit;
}
?>

        <div class="admin-page-header">
            <div>
                <h1>Settings</h1>
                <p>Update site identity, sender details, and SMTP delivery from one place.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">

                <?php foreach ($alerts as $alert) { ?>
                    <div class="alert alert-<?php echo htmlspecialchars($alert['type']); ?>" role="alert">
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </div>
                <?php } ?>

                <?php if ($smtpTestResult) { ?>
                    <div class="alert alert-<?php echo htmlspecialchars($smtpTestResult['type']); ?>" role="alert">
                        <?php echo htmlspecialchars($smtpTestResult['message']); ?>
                    </div>
                <?php } ?>

                <div class="glass-panel">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="site_name" class="form-label">Site Name</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="admin_email" class="form-label">From Email Address</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($admin_email); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="mail_hostname" class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" id="mail_hostname" name="mail_hostname" value="<?php echo htmlspecialchars($mail_hostname); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="mail_port" class="form-label">SMTP Port</label>
                                    <input type="text" class="form-control" id="mail_port" name="mail_port" value="<?php echo htmlspecialchars((string) $mail_port); ?>" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="mail_secure" class="form-label">SMTP Security</label>
                                    <select class="form-select" id="mail_secure" name="mail_secure">
                                        <option value="tls" <?php echo $mail_secure === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $mail_secure === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo $mail_secure === 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="mail_send" class="form-label">Mail Sending</label>
                                    <select class="form-select" id="mail_send" name="mail_send">
                                        <option value="enable" <?php echo $mail_send === 'enable' ? 'selected' : ''; ?>>Enable</option>
                                        <option value="disable" <?php echo $mail_send === 'disable' ? 'selected' : ''; ?>>Disable</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="mail_username" class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" id="mail_username" name="mail_username" value="<?php echo htmlspecialchars($mail_username); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="mail_password" class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" id="mail_password" name="mail_password" value="<?php echo htmlspecialchars($mail_password); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="test_email_recipient" class="form-label">Test Email Recipient</label>
                                <input type="email" class="form-control" id="test_email_recipient" name="test_email_recipient" value="<?php echo htmlspecialchars($test_email_recipient); ?>" placeholder="Enter an email address for SMTP testing">
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                                <button type="submit" name="test_email" value="1" class="btn btn-outline-secondary">Save And Send Test Email</button>
                            </div>
                        </form>
                </div>
            </div>
        </div>

<?php include __DIR__ . '/includes/admin-shell-footer.php'; ?>
