<?php

// Include the config.php file
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/guest_accounts.php';

// Define the allowed URL
$allowed_url = $site_baseurl . 'eduroam/admin';

// Check if the request is an AJAX request and the referring URL matches the allowed URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $allowed_url) === 0) {
    // Include your database connection code here
    include dirname(__DIR__) . '/db.php';
    ensureGuestAccountInfrastructure($pdo);

    // Check if the username (email) parameter is set
    if (isset($_POST['username'])) {
        // Sanitize and validate the username (you should perform more validation)
        $username = $_POST['username'];

        // Start a transaction
        $pdo->beginTransaction();

        try {
            // Delete records from the first table
            $deleteQuery1 = "DELETE FROM radcheck WHERE username = :username";
            $stmt1 = $pdo->prepare($deleteQuery1);
            $stmt1->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt1->execute();

            // Delete records from the second table
            $deleteQuery2 = "DELETE FROM userinfo WHERE username = :username";
            $stmt2 = $pdo->prepare($deleteQuery2);
            $stmt2->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt2->execute();

            $deleteQuery3 = "DELETE FROM guest_accounts WHERE username = :username";
            $stmt3 = $pdo->prepare($deleteQuery3);
            $stmt3->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt3->execute();

            // Commit the transaction
            $pdo->commit();

            // Deletion was successful
            $response = array('status' => 'success', 'message' => 'User deleted successfully');
        } catch (PDOException $e) {
            // Something went wrong, rollback the transaction
            $pdo->rollback();

            // Deletion failed
            $response = array('status' => 'error', 'message' => 'Failed to delete user');
        }

        // Send a JSON response back to the client
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Handle invalid requests or direct access to this script
http_response_code(404);
header('Location: ' . $site_baseurl);
exit;
?>
