<?php

require_once('../db.php');

// Check if eduroam_request table exists
$eduroam_request_exists_query = "SHOW TABLES LIKE 'eduroam_request'";
$result_eduroam = $pdo->query($eduroam_request_exists_query);
$eduroam_table_exists = $result_eduroam->rowCount() > 0;

// Check if password_reset table exists
$password_reset_exists_query = "SHOW TABLES LIKE 'password_reset'";
$result_password = $pdo->query($password_reset_exists_query);
$password_reset_table_exists = $result_password->rowCount() > 0;

// If eduroam_request table does not exist, create it
if (!$eduroam_table_exists) {
    $eduroam_request = "CREATE TABLE eduroam_request (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(255) NOT NULL,
        org_email VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($eduroam_request);
}

// If password_reset table does not exist, create it
if (!$password_reset_table_exists) {
    $password_reset = "CREATE TABLE password_reset (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expiration_time DATETIME NOT NULL
    )";
    $pdo->exec($password_reset);
}

http_response_code(404);
echo 'Already initialized';
exit;

?>
