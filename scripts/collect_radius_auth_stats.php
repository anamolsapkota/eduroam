<?php
// Cron script: collect radius auth stats snapshot
// Run every 10 minutes: */10 * * * * /usr/bin/php /path/to/eduroam/scripts/collect_radius_auth_stats.php >/dev/null 2>&1

require_once dirname(__DIR__) . '/includes/radius_monitoring.php';

$snapshot = radiusMonitorCollectSnapshot();
radiusMonitorAppendSnapshot($snapshot);
