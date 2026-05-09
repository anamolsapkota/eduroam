<?php

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/guest_accounts.php';

try {
    $repaired = repairExistingGuestAccounts($pdo);
    fwrite(STDOUT, "Repaired guest accounts: " . $repaired . PHP_EOL);
} catch (Throwable $e) {
    error_log('Existing guest account repair failed: ' . $e->getMessage());
    fwrite(STDERR, "Repair failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);

?>
