<?php


define('BASE_DIR', '/var/www/idp-tu.nren.net.np/eduroam/');

require(BASE_DIR . 'db.php');
require(BASE_DIR . 'includes/class.phpmailer.php');
require(BASE_DIR . 'includes/class.smtp.php');
// require(BASE_DIR . 'includes/mailSettings.php');

$site_baseurl = "https://idp-tu.nren.net.np/";
$site_name = "TU Eduroam";

try {
    // get rmsettings
    $stmt = $pdo->prepare("SELECT vkey,data FROM rmsettings");
    $stmt->execute();
    $rmsettings = array();

    // Fetch all rows and build the $rmsettings array
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rmsettings[$row['vkey']] = $row['data'];
    }

    $mail_send = $rmsettings['mail_send'];
    $mail_hostname = $rmsettings['mail_hostname'];
    $mail_username = $rmsettings['mail_username'];
    $mail_password = $rmsettings['mail_password'];
    $mail_port = $rmsettings['mail_port'];
    $mail_secure = $rmsettings['mail_secure'];
    $site_name = $rmsettings['site_name'];
    $admin_email = $rmsettings['admin_email'];
    $site_baseurl = "https://idp-tu.nren.net.np/";

} catch (PDOException $e) {
    echo 'Database connection failed: ' . $e->getMessage() . '<br>';
}

// Function to generate a random password
function generateRandomPassword($length = 8)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

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

    $Mail->send();
}

// Define the allowed URL
$allowed_url = 'https://idp-tu.nren.net.np/eduroam/management.php';

// Check if the request is an AJAX request and the referring URL matches the allowed URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $allowed_url) === 0) {
    // Include your database connection code here
    include 'db.php';

    // Check if the ID parameter is set
    if (isset($_POST['id'])) {
        // Sanitize and validate the ID
        $id = intval($_POST['id']);

        // Start a transaction
        $pdo->beginTransaction();

        try {
            // Retrieve user data from the eduroam_request table
            $selectQuery = "SELECT fullname, org_email FROM eduroam_request WHERE id = :id";
            $stmt = $pdo->prepare($selectQuery);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Extract user data
                $fullname = $row['fullname'];
                $org_email = $row['org_email'];

                // Insert user data into the radcheck table
                $insertQuery1 = "INSERT INTO radcheck (username, attribute, op, value) VALUES (:org_email, 'Cleartext-Password', ':=', :password)";
                $stmt1 = $pdo->prepare($insertQuery1);
                $password = generateRandomPassword(8); // Generate a random password
                $stmt1->bindParam(':org_email', $org_email, PDO::PARAM_STR);
                $stmt1->bindParam(':password', $password, PDO::PARAM_STR);
                $stmt1->execute();

                // Insert user data into the userinfo table
                $insertQuery2 = "INSERT INTO userinfo (username, fullname, email, updateby, updatedate) VALUES (:org_email, :fullname, :org_email, 'approve_script', :updatedate)";
                $stmt2 = $pdo->prepare($insertQuery2);
                $updatedate = date("Y-m-d H:i:s");
                $stmt2->bindParam(':org_email', $org_email, PDO::PARAM_STR);
                $stmt2->bindParam(':fullname', $fullname, PDO::PARAM_STR);
                $stmt2->bindParam(':updatedate', $updatedate, PDO::PARAM_STR);
                $stmt2->execute();

                // delete user data from the eduroam_request table
                $deleteQuery = "DELETE FROM eduroam_request WHERE id = :id";
                $stmt3 = $pdo->prepare($deleteQuery);
                $stmt3->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt3->execute();

                // Commit the transaction
                $pdo->commit();

                $email = $org_email;

                // Send an email to the user
                $subject = 'Eduroam Access Information';
                $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background-color: #007BFF; color: #fff; padding: 20px; text-align: center;">
                            <h1>Eduroam Access Information</h1>
                        </div>
                        <div style="padding: 20px;">
                            <p>Dear ' . $fullname . ',</p>
                            <p>We are pleased to provide you with access to Eduroam, a secure and convenient Wi-Fi network service
                                available at educational institutions worldwide. Eduroam allows you to connect to the internet seamlessly
                                while visiting participating institutions, including our own.</p>
                            <p>Here are the details to connect to Eduroam:</p>
                            <ul>
                                <li><strong>Network Name (SSID):</strong> eduroam</li>
                                <li><strong>Username: </strong>' . $email . '</li>
                                <li><strong>Password:</strong> ' . $password . '</li>
                            </ul>
                            <p>Simply select the "Eduroam" network on your device, enter your email address and password, and you&apos;ll
                                have secure internet access.</p>
                            <p>If you ever forget your password and need to reset it, you can do so by clicking the following link:
                                <a href="'. $site_baseurl .'eduroam/forgotpass.php">Reset Password</a></p>
                            <p>We hope you enjoy the benefits of secure and
                                hassle-free internet access.</p>
                            <p>Sincerely,</p>
                            <p>' . $site_name . '</p>
                        </div>
                        </div>';

                sendEmail($email,$fullname,$subject,$message);

                // Approval was successful
                $response = array('status' => 'success', 'message' => 'User approved successfully', 'password' => $password);
            } else {
                // No record found for the provided ID
                $response = array('status' => 'error', 'message' => 'No record found for the provided ID');
            }
        } catch (PDOException $e) {
            // Something went wrong, rollback the transaction
            $pdo->rollback();

            // Approval failed
            $response = array('status' => 'error', 'message' => 'Failed to approve user');
        }

        // Send a JSON response back to the client
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Handle invalid requests or direct access to this script
http_response_code(404);
header('Location: https://idp-tu.nren.net.np');
exit;

?>
