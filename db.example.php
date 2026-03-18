<?php

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // If accessed directly, redirect to a different page or show an error message
    header('HTTP/1.0 403 Forbidden');
    echo 'Direct access not allowed';
    exit;
}

$dbHost = "localhost";
$dbName = "radiusdb";
$dbUser = "root";
$dbPass = "password";

try {
	$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
	//set the PDO error mode to exception
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

?>