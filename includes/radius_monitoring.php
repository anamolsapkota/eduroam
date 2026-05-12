<?php

if (!defined('RADIUS_MONITOR_LOG_PATH')) {
    define('RADIUS_MONITOR_LOG_PATH', '/var/log/freeradius/radius.log');
}

if (!defined('RADIUS_MONITOR_STATS_PATH')) {
    define('RADIUS_MONITOR_STATS_PATH', '/var/log/freeradius/auth_stats.csv');
}

function radiusMonitorRunCommand($command)
{
    $output = shell_exec($command);
    return trim((string) $output);
}

function radiusMonitorCountPattern($logPath, $pattern)
{
    if (!is_readable($logPath)) {
        return 0;
    }

    $count = 0;
    $handle = fopen($logPath, 'r');

    if (!$handle) {
        return 0;
    }

    while (($line = fgets($handle)) !== false) {
        if (stripos($line, $pattern) !== false) {
            $count++;
        }
    }

    fclose($handle);
    return $count;
}

function radiusMonitorCollectSnapshot($logPath = RADIUS_MONITOR_LOG_PATH)
{
    return [
        'timestamp' => date('Y-m-d H:i:s'),
        'accepts' => radiusMonitorCountPattern($logPath, 'Login OK'),
        'rejects' => radiusMonitorCountPattern($logPath, 'Login incorrect'),
        'invalids' => radiusMonitorCountPattern($logPath, 'Invalid user'),
    ];
}

function radiusMonitorAppendSnapshot($statsPath = RADIUS_MONITOR_STATS_PATH, $logPath = RADIUS_MONITOR_LOG_PATH)
{
    $snapshot = radiusMonitorCollectSnapshot($logPath);
    $directory = dirname($statsPath);

    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Stats directory is not writable: ' . $directory);
    }

    $isNewFile = !file_exists($statsPath) || filesize($statsPath) === 0;
    $handle = fopen($statsPath, 'a');

    if (!$handle) {
        throw new RuntimeException('Unable to write stats file: ' . $statsPath);
    }

    if ($isNewFile) {
        fputcsv($handle, ['timestamp', 'accepts', 'rejects', 'invalids']);
    }

    fputcsv($handle, [
        $snapshot['timestamp'],
        $snapshot['accepts'],
        $snapshot['rejects'],
        $snapshot['invalids'],
    ]);
    fclose($handle);

    return $snapshot;
}

function radiusMonitorReadStats($statsPath = RADIUS_MONITOR_STATS_PATH, $limit = 288)
{
    if (!is_readable($statsPath)) {
        return [];
    }

    $rows = [];
    $handle = fopen($statsPath, 'r');

    if (!$handle) {
        return [];
    }

    fgetcsv($handle);

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 4 || strtolower($row[0]) === 'timestamp') {
            continue;
        }

        $rows[] = [
            'timestamp' => $row[0],
            'accepts' => (int) $row[1],
            'rejects' => (int) $row[2],
            'invalids' => (int) $row[3],
        ];
    }

    fclose($handle);

    if ($limit > 0 && count($rows) > $limit) {
        return array_slice($rows, -$limit);
    }

    return $rows;
}

function radiusMonitorBuildChartPayload($rows)
{
    $labels = [];
    $accepts = [];
    $rejects = [];
    $invalids = [];
    $previous = null;

    foreach ($rows as $row) {
        $timestamp = strtotime($row['timestamp']);
        $labels[] = $timestamp ? date('M j H:i', $timestamp) : $row['timestamp'];

        if ($previous === null) {
            $accepts[] = (int) $row['accepts'];
            $rejects[] = (int) $row['rejects'];
            $invalids[] = (int) $row['invalids'];
        } else {
            $accepts[] = max(0, (int) $row['accepts'] - (int) $previous['accepts']);
            $rejects[] = max(0, (int) $row['rejects'] - (int) $previous['rejects']);
            $invalids[] = max(0, (int) $row['invalids'] - (int) $previous['invalids']);
        }

        $previous = $row;
    }

    return [
        'labels' => $labels,
        'accepts' => $accepts,
        'rejects' => $rejects,
        'invalids' => $invalids,
    ];
}

function radiusMonitorRecentLogLines($logPath = RADIUS_MONITOR_LOG_PATH, $lines = 5)
{
    if (!is_readable($logPath)) {
        return ['Log file not found or inaccessible'];
    }

    $output = radiusMonitorRunCommand('tail -n ' . (int) $lines . ' ' . escapeshellarg($logPath) . ' 2>&1');

    if ($output === '') {
        return ['No log content available'];
    }

    return preg_split('/\r\n|\r|\n/', $output);
}

function radiusMonitorServiceSnapshot()
{
    $status = radiusMonitorRunCommand('systemctl is-active freeradius 2>&1');
    $startedAt = radiusMonitorRunCommand('systemctl show freeradius -p ActiveEnterTimestamp --value 2>&1');
    $usage = radiusMonitorRunCommand("ps -C freeradius -o %cpu,%mem --no-headers | awk '{cpu+=$1; mem+=$2} END {printf \"%.1f,%.1f\", cpu, mem}'");
    $usageParts = array_map('trim', explode(',', $usage));

    return [
        'status' => $status ?: 'unknown',
        'started_at' => $startedAt ?: '-',
        'cpu' => $usageParts[0] ?? '0.0',
        'memory' => $usageParts[1] ?? '0.0',
    ];
}
