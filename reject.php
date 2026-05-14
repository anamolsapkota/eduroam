<?php

// Include config
require_once 'includes/config.php';

// Define the allowed URLs
$allowed_urls = array(
    $site_baseurl . 'eduroam/management.php',
    $site_baseurl . 'eduroam/admin/',
);

// Check if the request is an AJAX request and the referring URL matches an allowed URL
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$referer_valid = false;
foreach ($allowed_urls as $url) {
    if (strpos($referer, $url) === 0) {
        $referer_valid = true;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $referer_valid) {
    // Include your database connection code here
    include 'db.php';

    // Check if the ID parameter is set
    if (isset($_POST['id'])) {
        // Sanitize and validate the ID
        $id = intval($_POST['id']);

        try {
            // Delete user data from the eduroam_request table
            $deleteQuery = "DELETE FROM eduroam_request WHERE id = :id";
            $stmt = $pdo->prepare($deleteQuery);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Check if any row was affected (deleted)
            if ($stmt->rowCount() > 0) {
                // Rejection was successful
                $response = array('status' => 'success', 'message' => 'User request rejected successfully');
            } else {
                // No record found for the provided ID
                $response = array('status' => 'error', 'message' => 'No record found for the provided ID');
            }
        } catch (PDOException $e) {
            // Log the error
            error_log("Database error in reject.php: " . $e->getMessage());

            // Rejection failed
            $response = array('status' => 'error', 'message' => 'Failed to reject user request: Database error');
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