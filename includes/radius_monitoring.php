<?php
// Radius monitoring constants
define('RADIUS_MONITOR_LOG_PATH', '/var/log/freeradius/radius.log');
define('RADIUS_MONITOR_STATS_PATH', dirname(__DIR__) . '/stats/radius_auth_stats.csv');

function radiusMonitorReadStats() {
    $path = RADIUS_MONITOR_STATS_PATH;
    if (!is_readable($path)) return [];
    $rows = [];
    $handle = fopen($path, 'r');
    if (!$handle) return [];
    while (($line = fgetcsv($handle)) !== false) {
        if (count($line) >= 4) {
            $rows[] = [
                'timestamp' => $line[0],
                'accepts' => (int)$line[1],
                'rejects' => (int)$line[2],
                'invalids' => (int)$line[3],
            ];
        }
    }
    fclose($handle);
    return $rows;
}

function radiusMonitorCollectSnapshot() {
    $logPath = RADIUS_MONITOR_LOG_PATH;
    $accepts = 0;
    $rejects = 0;
    $invalids = 0;

    if (is_readable($logPath)) {
        $output = shell_exec('tail -n 5000 ' . escapeshellarg($logPath) . ' 2>/dev/null');
        if ($output) {
            $accepts = preg_match_all('/Access-Accept/', $output);
            $rejects = preg_match_all('/Access-Reject/', $output);
            $invalids = preg_match_all('/Invalid user/', $output);
        }
    }

    return [
        'timestamp' => date('Y-m-d H:i:s'),
        'accepts' => $accepts,
        'rejects' => $rejects,
        'invalids' => $invalids,
    ];
}

function radiusMonitorAppendSnapshot($snapshot) {
    $path = RADIUS_MONITOR_STATS_PATH;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $handle = fopen($path, 'a');
    if ($handle) {
        fputcsv($handle, [$snapshot['timestamp'], $snapshot['accepts'], $snapshot['rejects'], $snapshot['invalids']]);
        fclose($handle);
    }
}

function radiusMonitorBuildChartPayload($rows) {
    $labels = [];
    $accepts = [];
    $rejects = [];
    $invalids = [];

    foreach ($rows as $row) {
        $ts = strtotime($row['timestamp']);
        $labels[] = $ts ? date('M j H:i', $ts) : $row['timestamp'];
        $accepts[] = (int)$row['accepts'];
        $rejects[] = (int)$row['rejects'];
        $invalids[] = (int)$row['invalids'];
    }

    return [
        'labels' => $labels,
        'accepts' => $accepts,
        'rejects' => $rejects,
        'invalids' => $invalids,
    ];
}

function radiusMonitorServiceSnapshot() {
    $status = 'unknown';
    $startedAt = '-';
    $cpu = '0';
    $memory = '0';

    $statusOutput = shell_exec('systemctl is-active freeradius 2>/dev/null');
    if ($statusOutput) {
        $status = trim($statusOutput);
    }

    $showOutput = shell_exec('systemctl show freeradius --property=ActiveEnterTimestamp 2>/dev/null');
    if ($showOutput && preg_match('/ActiveEnterTimestamp=(.+)/', $showOutput, $m)) {
        $startedAt = trim($m[1]);
    }

    $psOutput = shell_exec("ps aux | grep '[f]reeradius' | head -1 | awk '{print $3, $4}' 2>/dev/null");
    if ($psOutput) {
        $parts = preg_split('/\s+/', trim($psOutput));
        $cpu = $parts[0] ?? '0';
        $memory = $parts[1] ?? '0';
    }

    return [
        'status' => $status,
        'started_at' => $startedAt,
        'cpu' => $cpu,
        'memory' => $memory,
    ];
}

function radiusMonitorRecentLogLines($count = 20) {
    $logPath = RADIUS_MONITOR_LOG_PATH;
    if (!is_readable($logPath)) return ['Log file not readable: ' . $logPath];
    $output = shell_exec('tail -n ' . intval($count) . ' ' . escapeshellarg($logPath) . ' 2>&1');
    return $output ? explode("\n", rtrim($output)) : ['No log content available'];
}
