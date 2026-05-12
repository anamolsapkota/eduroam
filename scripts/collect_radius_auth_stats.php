<?php

require_once dirname(__DIR__) . '/includes/radius_monitoring.php';

try {
    $snapshot = radiusMonitorAppendSnapshot();
    fwrite(
        STDOUT,
        sprintf(
            "[%s] accepts=%d rejects=%d invalids=%d\n",
            $snapshot['timestamp'],
            $snapshot['accepts'],
            $snapshot['rejects'],
            $snapshot['invalids']
        )
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'FreeRADIUS auth stats collection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
