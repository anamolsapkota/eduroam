<?php

// ini_set('display_startup_errors', 1);
// ini_set('display_errors', 1);
// error_reporting(-1);

// if method is get, redirect to the index page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: /');
    exit;
}

require_once('includes/config.php');

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
    
    if ($Mail->send()) {
        echo 'Email sent successfully to ' . $to . '<br>';
    } else {
        echo 'Email could not be sent to ' . $to . ': ' . $Mail->ErrorInfo . '<br>';
    }
}

try {

    if (isset($_FILES["upcsv"])) {
        $file = $_FILES["upcsv"];
        if ($file["error"] === UPLOAD_ERR_OK) {
            $csvFile = $file["tmp_name"];

            // Read and process the CSV file
            if (($handle = fopen($csvFile, "r")) !== FALSE) {
                // Skip the header row (first row)
                fgetcsv($handle, 1000, ",");
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $email = $data[2];
                    $username = $data[1];
                    $fullname = $data[0];
                    // $email = $data[1];
                    $updateby = "bulk_import";
                    $updatedate = date("Y-m-d H:i:s");

                    // Check if the username exists in the radcheck table
                    $checkQuery = "SELECT COUNT(*) FROM radcheck WHERE username = ?";
                    $stmt = $pdo->prepare($checkQuery);
                    $stmt->execute([$username]);
                    $userExists = $stmt->fetchColumn();

                    if ($userExists == 0) {
                        // The username does not exist in the radcheck table, so you can proceed with insertion
                        // Generate a random password
                        $password = generateRandomPassword(8);

                        // Insert data into the radcheck table
                        $stmt = $pdo->prepare("INSERT INTO `radcheck` (`username`, `attribute`, `op`, `value`) VALUES (?, 'Cleartext-Password', ':=', ?)");
                        $stmt->execute([$username, $password]);

                        // Insert data into the userinfo table
                        $stmt = $pdo->prepare("INSERT INTO `userinfo` (`username`, `fullname`, `email`, `updateby`, `updatedate`) VALUES (?,?,?,?,?)");
                        $stmt->execute([$username, $fullname, $email, $updateby, $updatedate]);

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
                                        <li><strong>Username: </strong>' . $username . '</li>
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

                        echo 'User data inserted successfully<br />';
                        echo '-----------------------------------<br />';
                    } else {
                        echo 'User with email ' . $username . ' already exists in the radcheck table. Skipping insertion.<br>';
                    }
                }
                fclose($handle);
            } else {
                echo 'Error opening CSV file<br>';
            }

        } else {
            echo 'Error uploading file: ' . $file["error"] . '<br>';
        }
    } else {
        echo 'Please select a file to upload.<br>';
    }

} catch (PDOException $e) {
    echo 'Database connection failed: ' . $e->getMessage() . '<br>';
}

$pdo = null;
echo "DONE.";
?>
