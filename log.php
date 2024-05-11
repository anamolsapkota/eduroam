<?php

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // If accessed directly, redirect to a different page or show an error message
    header('HTTP/1.0 403 Forbidden');
    echo 'Direct access not allowed';
    exit;
}

// Local log file path
$log_file_path = '/var/log/freeradius/radius.log';

// Function to fetch last 50 lines of log content from a local file
function fetchLogContent($log_file_path) {
    // Check if the log file exists and is readable
    if (file_exists($log_file_path) && is_readable($log_file_path)) {
        // Read the last 50 lines of the log file
        $log_lines = file($log_file_path);
        $log_lines = array_slice($log_lines, -10);
        // Join the lines into a single string
        $log_content = implode('', $log_lines);
        // Return the log content
        return $log_content;
    } else {
        // Log file does not exist or is not readable
        return 'Log file not found or inaccessible';
    }
}

// Fetch log content from the local file
$log_content = fetchLogContent($log_file_path);

// Display the output
echo "<pre id='logContent' class='overflow-hidden'>$log_content</pre>";
?>
