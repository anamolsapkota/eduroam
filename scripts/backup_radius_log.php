<?php
// Cron script: backup today's radius log to Google Drive via rclone
// Run daily at 23:55: 55 23 * * * /usr/bin/php /path/to/eduroam/scripts/backup_radius_log.php >/dev/null 2>&1

require_once dirname(__DIR__) . '/includes/config.php';

$remote = $rmsettings['rclone_remote'] ?? '';
$drivePath = $rmsettings['rclone_drive_path'] ?? 'eduroam-logs';
$serverName = $rmsettings['rclone_server_name'] ?? php_uname('n');
$enabled = $rmsettings['rclone_backup_enabled'] ?? 'disable';
$logPath = '/var/log/freeradius/radius.log';

if ($enabled !== 'enable') {
    echo "Backup disabled in settings.\n";
    exit(0);
}

if ($remote === '') {
    echo "Error: rclone remote name not configured.\n";
    exit(1);
}

if (!is_readable($logPath)) {
    echo "Error: Radius log not readable at $logPath\n";
    exit(1);
}

// Check rclone is available
$rcloneCheck = shell_exec('which rclone 2>/dev/null');
if (empty(trim((string) $rcloneCheck))) {
    echo "Error: rclone not found in PATH.\n";
    exit(1);
}

$today = date('Y-m-d');
$outputFilename = "radius-log-$today.log";
$statsDir = dirname(__DIR__) . '/stats';
if (!is_dir($statsDir)) {
    mkdir($statsDir, 0755, true);
}
$tempFilePath = "$statsDir/$outputFilename";

// Extract today's log lines
// FreeRADIUS log format typically starts with a date like "Mon May 12 14:30:00 2026"
// We match lines containing today's date components
$monthDay = date('M') . ' ' . ltrim(date('d'), '0');
$monthDayPadded = date('M') . '  ' . ltrim(date('d'), '0');
$year = date('Y');

// Use grep to efficiently extract matching lines
$grepPattern = escapeshellarg("$monthDay .* $year\|$monthDayPadded .* $year");
$cmd = "grep -E " . escapeshellarg(date('M') . ' +' . ltrim(date('d'), '0') . ' .* ' . $year) . " " . escapeshellarg($logPath) . " > " . escapeshellarg($tempFilePath) . " 2>/dev/null";
shell_exec($cmd);

// If grep found nothing, try copying the whole log as fallback (the log may already be rotated daily)
if (!file_exists($tempFilePath) || filesize($tempFilePath) === 0) {
    copy($logPath, $tempFilePath);
}

if (!file_exists($tempFilePath) || filesize($tempFilePath) === 0) {
    echo "No log entries found for $today.\n";
    exit(0);
}

// Upload via rclone
$remotePath = escapeshellarg("$remote:$drivePath/$serverName/");
$localFile = escapeshellarg($tempFilePath);
$rcloneCmd = "rclone copy $localFile $remotePath --log-level ERROR 2>&1";
$output = shell_exec($rcloneCmd);

if (!empty(trim((string) $output))) {
    echo "rclone error: $output\n";
    // Keep the temp file for debugging
    exit(1);
}

echo "Successfully uploaded $outputFilename to $remote:$drivePath/$serverName/\n";

// Clean up temp file after successful upload
unlink($tempFilePath);
