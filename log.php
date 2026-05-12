<?php

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Direct access not allowed';
    exit;
}

// Local log file path
$log_file_path = '/var/log/freeradius/radius.log';

// Function to fetch last 50 lines of log content from a local file
function fetchLogContent($log_file_path) {
    if (file_exists($log_file_path) && is_readable($log_file_path)) {
        $output = shell_exec('tail -n 50 ' . escapeshellarg($log_file_path) . ' 2>&1');
        return $output ?: 'No log content available';
    } else {
        return 'Log file not found or inaccessible';
    }
}

// Fetch log content from the local file
$log_content = fetchLogContent($log_file_path);

// Display the output
echo "<pre id='logContent' class='overflow-hidden'>" . htmlspecialchars($log_content) . "</pre>";
?>
