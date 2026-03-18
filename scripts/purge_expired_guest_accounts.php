<?php

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/guest_accounts.php';

try {
    purgeExpiredGuestAccounts($pdo);
} catch (Throwable $e) {
    error_log('Expired guest cleanup failed: ' . $e->getMessage());
    exit(1);
}

exit(0);

?>
