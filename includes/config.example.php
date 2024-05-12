<?php

define(BASE_DIR, '/var/www/idp-tu.nren.net.np/eduroam/');

require(BASE_DIR . 'db.php');
require(BASE_DIR . 'includes/class.phpmailer.php');
require(BASE_DIR . 'includes/class.smtp.php');

$site_baseurl = "https://idp-tu.nren.net.np/";

// Define allowed email domains
$allowed_domains = [
    'tu.edu.np',
    'cdp.tu.edu.np',
    'cded.tu.edu.np',
];

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

} catch (PDOException $e) {
    echo 'Database connection failed: ' . $e->getMessage() . '<br>';
}

?>
