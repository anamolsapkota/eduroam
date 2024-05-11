<?php

require_once('db.php'); // Database connection

// Define allowed email domains
$allowed_domains = [
    'tu.edu.np',
    'cdp.tu.edu.np',
    'cded.tu.edu.np',
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["org_email"]) && isset($_POST["fullname"])) {
    // Retrieve and sanitize form data
    $email = trim(strtolower($_POST["org_email"]));
    $fullname = $_POST["fullname"];

    // Check if email ends with an allowed domain
    $email_parts = explode("@", $email);
    $domain = end($email_parts);
    if (!in_array($domain, $allowed_domains)) {
        $output = "Only institutional email addresses are allowed.";
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
        } else {
            // Check if email already exists in radcheck table
            $radcheck_sql = "SELECT COUNT(*) AS count FROM radcheck WHERE username = '$email'";
            $radcheck_result = mysqli_query($conn, $radcheck_sql);
            $radcheck_row = mysqli_fetch_assoc($radcheck_result);
            $radcheck_count = $radcheck_row['count'];

            if ($radcheck_count > 0) {
                $output = "This email address already has an existing account.";
            } else {
                // Insert into eduroam_request table
                $sql = "INSERT INTO eduroam_request (fullname, org_email, created_at) VALUES ('$fullname', '$email', NOW())";
                
                if (mysqli_query($conn, $sql)) {
                    $output = "Your request for eduroam has been received.";
                } else {
                    $output = "Error, please try again later.";
                }
            }
        }
    }  
} else {
    $output = "";
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>Eduroam Request Form | IDP-TU </title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
<?php
    include_once('template_parts/nav.php');
?>
<div id="content">

<div style="height: 100px;"></div>
    <!-- <img src="../ku-drone.jpg" style="width: 100%; height: 337px; object-fit: cover;"> -->
    <div id="requestform">
        <div class="row">
            <h3 class="container mt-4">eduroam Request</h3>
            <p class="ml-4 text-secondary">Request for your eduroam account</p>

            <?php if ($output): ?>
                <div class="alert alert-warning" role="alert">
                    <?php echo $output; ?>
                </div>
            <?php endif; ?>

            <?php if ($output !== "Your request for eduroam has been received.") { ?>
                <form id="reqform" action="" method="POST">
                    <div class="mb-3 mt-3">
                        <label for="fullname" class="form-label">Full Name:</label>
                        <input type="text" class="form-control" id="fullname" placeholder="Enter Your Full Name"
                            pattern="^\S(.*\S)?$" style="text-transform: capitalize;" name="fullname" required>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="org_email" class="form-label">Institutional Email Address:</label>
                        <input type="email" class="form-control" id="org_email" placeholder="Enter email address" name="org_email"
                            required>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
                <!-- Link to reset password -->
                <p class="mt-3"><a href="https://idp-tu.nren.net.np/eduroam/forgotpass.php">Forgot Password?</a></p>
            <?php } ?>
            
        </div>
    </div>
    <div style="height: 100px;"></div>
</div>


<?php 
    include_once('template_parts/footer.php');
?>

</body>
</html>