<?php

require_once dirname(__DIR__) . '/includes/radius_monitoring.php';

try {
    $dryRun = in_array('--dry-run', $argv ?? [], true);
    $snapshot = $dryRun ? radiusMonitorCollectSnapshot() : radiusMonitorAppendSnapshot();
    fwrite(
        STDOUT,
        sprintf(
            "[%s] accepts=%d rejects=%d invalids=%d%s\n",
            $snapshot['timestamp'],
            $snapshot['accepts'],
            $snapshot['rejects'],
            $snapshot['invalids'],
            $dryRun ? ' dry-run' : ''
        )
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'FreeRADIUS auth stats collection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
