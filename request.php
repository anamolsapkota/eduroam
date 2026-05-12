<?php

// include config file
require_once 'includes/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["org_email"]) && isset($_POST["fullname"])) {
    // Retrieve and sanitize form data
    $email = trim(strtolower($_POST["org_email"]));
    $fullname = $_POST["fullname"];

    // Check if email ends with an allowed domain
    $email_parts = explode("@", $email);
    $domain = end($email_parts);
    if (!in_array($domain, $allowed_domains)) {
        $output = "Only institutional email addresses are allowed.";
        $output_type = "warning";
    } else {
        // Escape variables to prevent SQL injection
        $email = mysqli_real_escape_string($conn, $email);
        $fullname = mysqli_real_escape_string($conn, $fullname);

        // Check if email already exists in eduroam_request table
        $check_sql = "SELECT COUNT(*) AS count FROM eduroam_request WHERE org_email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        $check_row = mysqli_fetch_assoc($check_result);
        $count = $check_row['count'];

        if ($count > 0) {
            $output = "This email address has already requested for eduroam.";
            $output_type = "warning";
        } else {
            // Check if email already exists in radcheck table
            $radcheck_sql = "SELECT COUNT(*) AS count FROM radcheck WHERE username = '$email'";
            $radcheck_result = mysqli_query($conn, $radcheck_sql);
            $radcheck_row = mysqli_fetch_assoc($radcheck_result);
            $radcheck_count = $radcheck_row['count'];

            if ($radcheck_count > 0) {
                $output = "This email address already has an existing account.";
                $output_type = "info";
            } else {
                // Insert into eduroam_request table
                $sql = "INSERT INTO eduroam_request (fullname, org_email, created_at) VALUES ('$fullname', '$email', NOW())";

                if (mysqli_query($conn, $sql)) {
                    $output = "Your request for eduroam has been received. You will be notified once your account is approved.";
                    $output_type = "success";
                } else {
                    $output = "Error, please try again later.";
                    $output_type = "danger";
                }
            }
        }
    }
} else {
    $output = "";
    $output_type = "";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Request eduroam Access | <?php echo $site_name; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

        .request-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            width: 100%;
            max-width: 520px;
            padding: 2rem;
        }

        .request-card h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .request-card .subtitle {
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

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.6rem 0;
            font-size: 0.85rem;
            color: #475569;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-list li:last-child {
            border-bottom: none;
        }

        .info-list li i {
            color: #3b82f6;
            font-size: 0.85rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            width: 100%;
            max-width: 520px;
            margin-top: 1.5rem;
        }

        .info-panel h3 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
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
            color: #10b981;
            margin-bottom: 1rem;
        }

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

            .two-col {
                display: flex;
                gap: 2rem;
                align-items: flex-start;
                max-width: 960px;
                width: 100%;
            }

            .two-col .request-card {
                flex: 1;
            }

            .two-col .info-panel {
                margin-top: 0;
                flex: 0 0 340px;
            }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="container">
            <span class="brand"><?php echo htmlspecialchars($site_name); ?></span>
            <h1>Request eduroam Access</h1>
            <p>Get connected to the global eduroam Wi-Fi network</p>
        </div>
    </header>

    <div class="main-content">
        <div class="two-col">
            <div class="request-card">
                <?php if ($output_type === 'success'): ?>
                    <div class="success-block">
                        <i class="fas fa-check-circle"></i>
                        <h3>Request Submitted</h3>
                        <p><?php echo htmlspecialchars($output); ?></p>
                    </div>
                <?php else: ?>
                    <h2>Submit Your Request</h2>
                    <p class="subtitle">Fill in your details below to request an eduroam account.</p>

                    <?php if ($output): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($output_type); ?> py-2" role="alert" style="font-size: 0.85rem;">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo htmlspecialchars($output); ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="fullname" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" placeholder="e.g. Ram Sharma" pattern="^\S(.*\S)?$" style="text-transform: capitalize;" required>
                        </div>
                        <div class="mb-3">
                            <label for="org_email" class="form-label">Institutional Email Address</label>
                            <input type="email" class="form-control" id="org_email" name="org_email" placeholder="e.g. ram@nec.edu.np" required>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                        </button>
                    </form>

                    <div class="links-row">
                        <a href="/eduroam/forgotpass.php"><i class="fas fa-key me-1"></i>Forgot Password?</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-panel">
                <h3><i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>How It Works</h3>
                <ul class="info-list">
                    <li>
                        <i class="fas fa-file-lines"></i>
                        <span>Submit your request using your institutional email address.</span>
                    </li>
                    <li>
                        <i class="fas fa-user-check"></i>
                        <span>An administrator will review and approve your request.</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>You will receive your login credentials via email once approved.</span>
                    </li>
                    <li>
                        <i class="fas fa-wifi"></i>
                        <span>Connect to the <strong>eduroam</strong> Wi-Fi network using your credentials — works at participating institutions worldwide.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <footer class="page-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>
