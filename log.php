<?php

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // If accessed directly, redirect to a different page or show an error message
    header('HTTP/1.0 403 Forbidden');
    echo 'Direct access not allowed';
    exit;
}

// Local log file path
$log_file_path = '/var/log/freeradius/radius.log';

// Function to fetch last lines of log content from a local file efficiently
function fetchLogContent($log_file_path, $lines = 120) {
    if (!file_exists($log_file_path) || !is_readable($log_file_path)) {
        return 'Log file not found or inaccessible';
    }

    $command = 'tail -n ' . (int) $lines . ' ' . escapeshellarg($log_file_path) . ' 2>&1';
    $output = shell_exec($command);

    if ($output === null || trim($output) === '') {
        return 'No log content available';
    }

    return $output;
}

// Fetch log content from the local file
$log_content = fetchLogContent($log_file_path);

// Display the output
echo "<div class='log-viewer-meta'>Showing the latest FreeRADIUS log entries from <code>" . htmlspecialchars($log_file_path) . "</code></div>";
echo "<pre id='logContent' class='log-viewer'>" . htmlspecialchars($log_content) . "</pre>";
?>
