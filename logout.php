<?php

// logout
session_start();
session_unset();

// clear all session values
$_SESSION = array();

// destroy the session
session_destroy();

// redirect to the login page
header('Location: /eduroam/login.php');

?>