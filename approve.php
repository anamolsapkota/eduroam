<?php

// Include config
require_once 'includes/config.php';
require_once 'includes/email.php';

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

// Define the allowed URL
$allowed_url = $site_baseurl . 'eduroam/management.php';

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

                // Send an email to the user
                $subject = 'Eduroam Access Information';
                $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background-color: #007BFF; color: #fff; padding: 20px; text-align: center;">
                            <h1>Eduroam Access Information</h1>
                        </div>
                        <div style="padding: 20px;">
                            <p>Dear ' . htmlspecialchars($fullname) . ',</p>
                            <p>We are pleased to provide you with access to Eduroam, a secure and convenient Wi-Fi network service
                                available at educational institutions worldwide. Eduroam allows you to connect to the internet seamlessly
                                while visiting participating institutions, including our own.</p>
                            <p>Here are the details to connect to Eduroam:</p>
                            <ul>
                                <li><strong>Network Name (SSID):</strong> eduroam</li>
                                <li><strong>Username: </strong>' . htmlspecialchars($org_email) . '</li>
                                <li><strong>Password:</strong> ' . htmlspecialchars($password) . '</li>
                            </ul>
                            <p>Simply select the "Eduroam" network on your device, enter your email address and password, and you&apos;ll
                                have secure internet access.</p>
                            <p>If you ever forget your password and need to reset it, you can do so by clicking the following link:
                                <a href="' . htmlspecialchars($site_baseurl) . 'eduroam/forgotpass.php">Reset Password</a></p>
                            <p>We hope you enjoy the benefits of secure and
                                hassle-free internet access.</p>
                            <p>Sincerely,</p>
                            <p>' . htmlspecialchars($site_name) . '</p>
                        </div>
                        </div>';

                // Send the email
                $result = sendEmail($org_email, $fullname, $subject, $message);
                
                if ($result['success']) {
                    // Approval was successful
                    $response = array('status' => 'success', 'message' => 'User approved successfully and email sent', 'password' => $password);
                } else {
                    // Email failed but user was still approved
                    $response = array('status' => 'success', 'message' => 'User approved successfully but email failed: ' . $result['error'], 'password' => $password);
                }
            } else {
                // No record found for the provided ID
                $response = array('status' => 'error', 'message' => 'No record found for the provided ID');
            }
        } catch (PDOException $e) {
            // Something went wrong, rollback the transaction
            $pdo->rollback();

            // Log the error
            error_log("Database error in approve.php: " . $e->getMessage());

            // Approval failed
            $response = array('status' => 'error', 'message' => 'Failed to approve user: Database error');
        }

        // Send a JSON response back to the client
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // ID parameter not provided
        $response = array('status' => 'error', 'message' => 'ID parameter is required');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
} else {
    // Handle invalid requests or direct access to this script
    http_response_code(403);
    header('Location: https://idp-pri.nren.net.np');
    exit;
}

?>
