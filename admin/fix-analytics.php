<?php
/**
 * Diagnostic & fix tool for analytics and monitoring issues.
 *
 * Checks performed (generic — works on any eduroam server):
 *   1. FreeRADIUS post-auth section missing 'sql' → radpostauth stays empty
 *   2. radacct table missing 'acctinterval' column → SQL errors in radius log
 *   3. NAS table entries with leading whitespace in nasname
 *   4. Duplicate NAS table entries (same IP)
 *
 * Each check runs dynamically. Fixes are only offered when an issue is detected.
 *
 * Usage: visit /eduroam/admin/fix-analytics.php in the browser.
 * Auth:  requires admin session (same as other admin pages).
 */

$admin_page_title = 'Fix Analytics';
include __DIR__ . '/includes/admin-shell-header.php';
include dirname(__DIR__) . '/db.php';

// ── helpers ──────────────────────────────────────────────────────────────────

function fr_site_path(): string
{
    return '/etc/freeradius/3.0/sites-available/eduroam';
}

function check_freeradius_postauth(): array
{
    $path = fr_site_path();
    if (!is_readable($path)) {
        return ['ok' => false, 'error' => "Cannot read $path — PHP may not have permission."];
    }
    $content = file_get_contents($path);

    // Check if sql is already in post-auth (but not inside Post-Auth-Type REJECT)
    // We look for 'sql' as a standalone word directly inside post-auth { ... }
    if (preg_match('/post-auth\s*\{[^}]*?\bsql\b/s', $content)) {
        return ['ok' => true, 'message' => 'post-auth section already contains <code>sql</code>.'];
    }
    return ['ok' => false, 'error' => 'post-auth section is missing <code>sql</code> — radpostauth will not receive data.'];
}

function fix_freeradius_postauth(): array
{
    $path = fr_site_path();
    if (!is_writable($path)) {
        return ['ok' => false, 'error' => "Cannot write to $path — run with appropriate permissions."];
    }
    $content = file_get_contents($path);
    $original = $content;

    // 1. Add sql right after "post-auth {"
    $content = preg_replace(
        '/(post-auth\s*\{)/',
        "$1\n        sql",
        $content,
        1,
        $count1
    );

    // 2. Add sql inside Post-Auth-Type REJECT { ... }
    $content = preg_replace(
        '/(Post-Auth-Type\s+REJECT\s*\{)/',
        "$1\n            sql",
        $content,
        1,
        $count2
    );

    if ($count1 === 0 && $count2 === 0) {
        return ['ok' => false, 'error' => 'Could not locate post-auth block in config.'];
    }

    // Backup original
    $backupPath = $path . '.bak.' . date('Ymd_His');
    copy($path, $backupPath);

    file_put_contents($path, $content);
    return ['ok' => true, 'message' => "Updated $path (backup: $backupPath). <strong>You must restart FreeRADIUS</strong> for changes to take effect."];
}

function check_acctinterval(PDO $pdo): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM radacct LIKE 'acctinterval'");
    if ($stmt->rowCount() > 0) {
        return ['ok' => true, 'message' => '<code>acctinterval</code> column already exists in <code>radacct</code>.'];
    }
    return ['ok' => false, 'error' => '<code>acctinterval</code> column missing from <code>radacct</code> — causes SQL errors in FreeRADIUS.'];
}

function fix_acctinterval(PDO $pdo): array
{
    try {
        $pdo->exec("ALTER TABLE radacct ADD COLUMN acctinterval int(12) DEFAULT NULL AFTER acctsessiontime");
        return ['ok' => true, 'message' => 'Added <code>acctinterval</code> column to <code>radacct</code>.'];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Failed to add column: ' . htmlspecialchars($e->getMessage())];
    }
}

function check_nas_space(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, nasname FROM nas WHERE nasname LIKE ' %'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return ['ok' => true, 'message' => 'No NAS entries with leading spaces.'];
    }
    $details = [];
    foreach ($rows as $r) {
        $details[] = "id={$r['id']} nasname=\"" . htmlspecialchars($r['nasname']) . '"';
    }
    return ['ok' => false, 'error' => 'NAS entries with leading space: ' . implode(', ', $details)];
}

function fix_nas_space(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("UPDATE nas SET nasname = TRIM(nasname) WHERE nasname LIKE ' %'");
        $stmt->execute();
        $affected = $stmt->rowCount();
        return ['ok' => true, 'message' => "Trimmed leading spaces from $affected NAS entry/entries."];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Failed: ' . htmlspecialchars($e->getMessage())];
    }
}

function check_nas_duplicates(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT TRIM(nasname) AS ip, GROUP_CONCAT(id) AS ids, COUNT(*) AS cnt FROM nas GROUP BY TRIM(nasname) HAVING cnt > 1");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return ['ok' => true, 'message' => 'No duplicate NAS entries.'];
    }
    $details = [];
    foreach ($rows as $r) {
        $details[] = htmlspecialchars($r['ip']) . " (ids: {$r['ids']})";
    }
    return ['ok' => false, 'error' => 'Duplicate NAS IPs: ' . implode(', ', $details)];
}

function fix_nas_duplicates(PDO $pdo): array
{
    try {
        // Keep the entry with the lowest id, delete the rest
        $dupes = $pdo->query("SELECT TRIM(nasname) AS ip, MIN(id) AS keep_id, GROUP_CONCAT(id) AS all_ids FROM nas GROUP BY TRIM(nasname) HAVING COUNT(*) > 1")->fetchAll(PDO::FETCH_ASSOC);
        $deleted = 0;
        foreach ($dupes as $d) {
            $stmt = $pdo->prepare("DELETE FROM nas WHERE TRIM(nasname) = ? AND id != ?");
            $stmt->execute([$d['ip'], $d['keep_id']]);
            $deleted += $stmt->rowCount();
        }
        return ['ok' => true, 'message' => "Removed $deleted duplicate NAS entry/entries (kept lowest id for each IP)."];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Failed: ' . htmlspecialchars($e->getMessage())];
    }
}

// ── process ──────────────────────────────────────────────────────────────────

$checks = [
    'postauth'  => ['label' => 'FreeRADIUS post-auth sql logging',       'check' => 'check_freeradius_postauth', 'fix' => 'fix_freeradius_postauth',  'args' => 'none'],
    'acctint'   => ['label' => 'radacct.acctinterval column',            'check' => 'check_acctinterval',        'fix' => 'fix_acctinterval',         'args' => 'pdo'],
    'nas_space' => ['label' => 'NAS entries with leading spaces',        'check' => 'check_nas_space',           'fix' => 'fix_nas_space',            'args' => 'pdo'],
    'nas_dupe'  => ['label' => 'Duplicate NAS entries',                  'check' => 'check_nas_duplicates',      'fix' => 'fix_nas_duplicates',       'args' => 'pdo'],
];

$results = [];
$fixApplied = false;

// If a fix was requested via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    $fixKey = $_POST['fix'];
    if (isset($checks[$fixKey])) {
        $fn = $checks[$fixKey]['fix'];
        $results[$fixKey] = ($checks[$fixKey]['args'] === 'pdo') ? $fn($pdo) : $fn();
        $fixApplied = true;
    }
}

// If "fix all" was requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_all'])) {
    foreach ($checks as $key => $c) {
        $checkResult = ($c['args'] === 'pdo') ? ($c['check'])($pdo) : ($c['check'])();
        if (!$checkResult['ok']) {
            $results[$key] = ($c['args'] === 'pdo') ? ($c['fix'])($pdo) : ($c['fix'])();
        }
    }
    $fixApplied = true;
}

// Run checks (after any fixes, so status is current)
$statuses = [];
foreach ($checks as $key => $c) {
    $statuses[$key] = ($c['args'] === 'pdo') ? ($c['check'])($pdo) : ($c['check'])();
}

$allOk = true;
foreach ($statuses as $s) {
    if (!$s['ok']) { $allOk = false; break; }
}

// Check if FreeRADIUS needs restart
$needsRestart = false;
if ($fixApplied && isset($results['postauth']) && $results['postauth']['ok']) {
    $needsRestart = true;
}
?>

<div class="admin-page-header">
    <div>
        <h1><i class="fas fa-wrench"></i> Analytics &amp; Monitoring Fix</h1>
        <p>Diagnose and fix issues preventing analytics data from being collected.</p>
    </div>
    <div class="admin-page-actions">
        <a href="/eduroam/admin/analytics/" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-chart-bar me-1"></i>Analytics
        </a>
        <a href="/eduroam/admin/graphs/" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-chart-area me-1"></i>Monitoring
        </a>
    </div>
</div>

<?php if ($fixApplied): ?>
    <?php foreach ($results as $key => $r): ?>
        <div class="alert alert-<?php echo $r['ok'] ? 'success' : 'danger'; ?> d-flex align-items-start" role="alert">
            <i class="fas fa-<?php echo $r['ok'] ? 'check-circle' : 'times-circle'; ?> me-2 mt-1"></i>
            <div>
                <strong><?php echo htmlspecialchars($checks[$key]['label']); ?>:</strong>
                <?php echo $r['message'] ?? $r['error']; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($needsRestart): ?>
    <div class="alert alert-warning d-flex align-items-start" role="alert">
        <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
        <div>
            <strong>FreeRADIUS restart required.</strong> Run the following command on the server:
            <pre class="mt-2 mb-0" style="background:#f8f9fa;padding:10px;border-radius:4px;">sudo systemctl restart freeradius</pre>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Diagnostic Checks</h5>
        <?php if (!$allOk): ?>
            <form method="post" class="d-inline">
                <button type="submit" name="fix_all" value="1" class="btn btn-primary btn-sm"
                        onclick="return confirm('Apply all fixes?')">
                    <i class="fas fa-magic me-1"></i>Fix All Issues
                </button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:40px"></th>
                    <th>Check</th>
                    <th>Status</th>
                    <th style="width:120px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $key => $c): ?>
                    <?php $s = $statuses[$key]; ?>
                    <tr>
                        <td class="text-center">
                            <?php if ($s['ok']): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-times-circle text-danger"></i>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($c['label']); ?></strong></td>
                        <td>
                            <?php if ($s['ok']): ?>
                                <span class="text-success"><?php echo $s['message']; ?></span>
                            <?php else: ?>
                                <span class="text-danger"><?php echo $s['error']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$s['ok']): ?>
                                <form method="post" class="d-inline">
                                    <button type="submit" name="fix" value="<?php echo $key; ?>" class="btn btn-warning btn-sm"
                                            onclick="return confirm('Apply this fix?')">
                                        <i class="fas fa-wrench me-1"></i>Fix
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($allOk): ?>
    <div class="alert alert-success d-flex align-items-start" role="alert">
        <i class="fas fa-check-circle me-2 mt-1"></i>
        <div>
            <strong>All checks passed.</strong> Analytics and monitoring should be receiving data.
            <?php if (!$needsRestart): ?>
                If you just applied the FreeRADIUS config fix, make sure to restart the service:
                <pre class="mt-2 mb-0" style="background:#f8f9fa;padding:10px;border-radius:4px;">sudo systemctl restart freeradius</pre>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">What these fixes do</h5>
    </div>
    <div class="card-body">
        <dl>
            <dt>1. FreeRADIUS post-auth sql logging</dt>
            <dd>Adds <code>sql</code> to the <code>post-auth</code> and <code>Post-Auth-Type REJECT</code> sections in
                <code>/etc/freeradius/3.0/sites-available/eduroam</code> so that authentication results
                (Accept/Reject) are logged to the <code>radpostauth</code> table. Without this, the
                analytics auth charts remain empty.</dd>

            <dt>2. radacct.acctinterval column</dt>
            <dd>Adds the missing <code>acctinterval</code> column to the <code>radacct</code> table. FreeRADIUS's
                default SQL queries reference this column for interim accounting updates, and its absence
                causes repeated SQL errors in the radius log.</dd>

            <dt>3. NAS leading spaces</dt>
            <dd>Trims leading whitespace from NAS IP addresses. A leading space prevents DNS resolution and
                causes "Failed resolving" errors when FreeRADIUS loads NAS clients from the database.</dd>

            <dt>4. Duplicate NAS entries</dt>
            <dd>Removes duplicate NAS rows (same IP). Duplicates cause "Failed to add duplicate client" errors
                on FreeRADIUS startup. The entry with the lowest id is kept.</dd>
        </dl>
    </div>
</div>

<?php include __DIR__ . '/includes/admin-shell-footer.php'; ?>
