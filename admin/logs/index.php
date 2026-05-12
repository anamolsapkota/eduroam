<?php
$admin_page_title = 'Logs';
include dirname(__DIR__) . '/includes/admin-shell-header.php';
?>

<div class="admin-page-header">
    <div>
        <h1>System Logs</h1>
        <p>FreeRADIUS authentication and accounting logs.</p>
    </div>
</div>

<div class="table-card">
    <div class="table-card-heading">
        <h2>Recent Logs</h2>
        <span style="color: #64748b; font-size: 0.85rem;">
            <?php echo date("F j, Y, g:i a"); ?>
        </span>
    </div>
    <div style="padding: 0;">
        <?php include dirname(__DIR__, 2) . '/log.php'; ?>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/admin-shell-footer.php'; ?>
