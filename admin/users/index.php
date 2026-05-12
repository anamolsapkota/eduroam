<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    header('Location: /eduroam/admin/login.php');
    exit;
}

$basic_auth = base64_decode($_SESSION['basic_auth']);
$authParts = explode(':', $basic_auth, 2);
$authUser = $authParts[0] ?? '';
$authPass = $authParts[1] ?? '';

$_SERVER['PHP_AUTH_USER'] = $authUser;
$_SERVER['PHP_AUTH_PW'] = $authPass;

if ($_SERVER['PHP_AUTH_USER'] !== $authUser || $_SERVER['PHP_AUTH_PW'] !== $authPass) {
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access Denied';
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/email.php';
require_once dirname(__DIR__, 2) . '/includes/guest_accounts.php';
include dirname(__DIR__, 2) . '/db.php';

ensureGuestAccountInfrastructure($pdo);
purgeExpiredGuestAccounts($pdo);

$seo_title = 'User Management | ' . $site_name;
$seo_description = 'Manage eduroam Visitor Access guest accounts.';
$seo_canonical = 'https://eva.nren.net.np/eduroam/admin/users/';
$seo_robots = 'noindex,follow';
$seo_type = 'website';

function formatUserManagementDate($value)
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return $value;
    }

    return date('M j, Y g:i A', $timestamp);
}

function formatUserManagementExpiry($expiresAt, $radiusExpiresAt = null)
{
    if ($expiresAt) {
        return formatUserManagementDate($expiresAt);
    }

    return $radiusExpiresAt ? formatUserManagementDate($radiusExpiresAt) : '-';
}

function userManagementExpiryStatus($expiresAt, $radiusExpiresAt = null)
{
    if (!$expiresAt && $radiusExpiresAt) {
        $expiresAt = $radiusExpiresAt;
    }

    if (!$expiresAt) {
        return 'No expiry';
    }

    $expiryTime = strtotime($expiresAt);

    if (!$expiryTime) {
        return 'No expiry';
    }

    $secondsRemaining = $expiryTime - time();

    if ($secondsRemaining <= 0) {
        return 'Expired';
    }

    if ($secondsRemaining <= 21600) {
        return 'Expiring soon';
    }

    return 'Active';
}

function userManagementStatusClass($status)
{
    $statusMap = [
        'Active' => 'status-pill--active',
        'Expired' => 'status-pill--expired',
        'Expiring soon' => 'status-pill--warning',
        'No expiry' => 'status-pill--neutral',
    ];

    return $statusMap[$status] ?? 'status-pill--neutral';
}

function paginationUrl($page, $search, $status)
{
    return '?' . http_build_query([
        'search' => $search,
        'status' => $status,
        'page' => $page,
    ]);
}

$manualCreateAlert = null;
$manualCreateResult = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['manual_create_user'])) {
    $manualFullname = trim($_POST['manual_fullname'] ?? '');
    $manualEmail = trim($_POST['manual_email'] ?? '');
    $manualSendEmail = true;
    $updatedBy = $_SESSION['user']['username'] ?? 'admin_manual';

    try {
        $manualCreateResult = createGuestAccount($pdo, $manualFullname, $manualEmail, $updatedBy);

        if ($manualSendEmail) {
            $subject = 'Eduroam Access Information';
            $message = buildGuestCredentialEmail(
                $manualCreateResult['fullname'],
                $manualCreateResult['username'],
                $manualCreateResult['password'],
                $manualCreateResult['expires_at_display'],
                $site_baseurl,
                $site_name
            );

            $emailResult = sendEmail($manualEmail, $manualFullname, $subject, $message);

            if (!$emailResult['success']) {
                $pdo->prepare("DELETE FROM radcheck WHERE username = :username")->execute([':username' => $manualCreateResult['username']]);
                $pdo->prepare("DELETE FROM userinfo WHERE username = :username")->execute([':username' => $manualCreateResult['username']]);
                $pdo->prepare("DELETE FROM guest_accounts WHERE username = :username")->execute([':username' => $manualCreateResult['username']]);
                throw new RuntimeException('Account was not saved because the credential email failed: ' . $emailResult['error']);
            }
        }

        $manualCreateAlert = [
            'type' => 'success',
            'message' => 'Guest account created successfully and the credentials email was sent.',
        ];
    } catch (InvalidArgumentException | RuntimeException $e) {
        $manualCreateAlert = [
            'type' => 'danger',
            'message' => $e->getMessage(),
        ];
        $manualCreateResult = null;
    } catch (Throwable $e) {
        error_log('Manual guest account creation failed: ' . $e->getMessage());
        $manualCreateAlert = [
            'type' => 'danger',
            'message' => 'Manual account creation failed. Please try again.',
        ];
        $manualCreateResult = null;
    }
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'active', 'expiring', 'expired', 'no_expiry'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

$recordsPerPage = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

$passwordSubquery = "(SELECT DISTINCT username FROM radcheck WHERE attribute = 'Cleartext-Password')";
$expirySubquery = "(SELECT username, MIN(value) AS radius_expires_at FROM radcheck WHERE attribute = 'Expiration' GROUP BY username)";
$expiryExpression = "COALESCE(guest_accounts.expires_at, STR_TO_DATE(expiry_check.radius_expires_at, '%d %b %Y %H:%i'))";

$fromClause = "FROM userinfo
    INNER JOIN $passwordSubquery password_check ON userinfo.username = password_check.username
    LEFT JOIN guest_accounts ON userinfo.username = guest_accounts.username
    LEFT JOIN $expirySubquery expiry_check ON userinfo.username = expiry_check.username";

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(userinfo.username LIKE :search OR userinfo.fullname LIKE :search OR userinfo.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status === 'active') {
    $where[] = "$expiryExpression > NOW()";
} elseif ($status === 'expiring') {
    $where[] = "$expiryExpression BETWEEN NOW() AND NOW() + INTERVAL 6 HOUR";
} elseif ($status === 'expired') {
    $where[] = "$expiryExpression <= NOW()";
} elseif ($status === 'no_expiry') {
    $where[] = "$expiryExpression IS NULL";
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$statsSql = "SELECT
        COUNT(*) AS total_users,
        COALESCE(SUM($expiryExpression > NOW()), 0) AS active_users,
        COALESCE(SUM($expiryExpression BETWEEN NOW() AND NOW() + INTERVAL 6 HOUR), 0) AS expiring_users,
        COALESCE(SUM($expiryExpression <= NOW()), 0) AS expired_users,
        COALESCE(SUM($expiryExpression IS NULL), 0) AS no_expiry_users
    $fromClause";
$stats = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) $fromClause $whereSql");
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $recordsPerPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $recordsPerPage;
}

$userSql = "SELECT
        userinfo.fullname,
        userinfo.username,
        userinfo.email,
        userinfo.updateby,
        userinfo.updatedate,
        guest_accounts.created_at AS requested_at,
        guest_accounts.expires_at,
        expiry_check.radius_expires_at,
        $expiryExpression AS effective_expires_at
    $fromClause
    $whereSql
    ORDER BY userinfo.updatedate DESC, userinfo.username ASC
    LIMIT :limit OFFSET :offset";
$userStmt = $pdo->prepare($userSql);
foreach ($params as $key => $value) {
    $userStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$userStmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$userStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$userStmt->execute();
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$showingFrom = $totalRecords > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $recordsPerPage, $totalRecords);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <link rel="stylesheet" href="/eduroam/assets/css/styles.css">
    <?php include dirname(__DIR__, 2) . '/template_parts/head.php'; ?>
</head>
<body class="app-shell">
    <?php include dirname(__DIR__, 2) . '/template_parts/nav.php'; ?>
    <main id="content" class="page-shell user-management-shell">
        <div class="hero-banner">
            <div>
                <span class="eyebrow">Admin</span>
                <h1>User Management</h1>
                <p class="meta mb-0">Showing <?php echo $showingFrom; ?>-<?php echo $showingTo; ?> of <?php echo $totalRecords; ?> users</p>
            </div>
            <div class="hero-actions">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="fas fa-user-plus me-2"></i>Create Guest
                </button>
                <a href="/eduroam/admin/" class="btn btn-outline-secondary">
                    <i class="fas fa-chart-line me-2"></i>Dashboard
                </a>
            </div>
        </div>

        <div id="userManagementAlert">
            <?php if ($manualCreateAlert): ?>
                <div class="alert alert-<?php echo htmlspecialchars($manualCreateAlert['type']); ?>" role="alert">
                    <?php echo htmlspecialchars($manualCreateAlert['message']); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($manualCreateResult): ?>
            <section class="manual-result-card user-management-result">
                <h3>Created account</h3>
                <div class="manual-result-grid">
                    <div><span>Username</span><strong><?php echo htmlspecialchars($manualCreateResult['username']); ?></strong></div>
                    <div><span>Email</span><strong><?php echo htmlspecialchars($manualCreateResult['delivery_email']); ?></strong></div>
                    <div><span>Expires At</span><strong><?php echo htmlspecialchars($manualCreateResult['expires_at_display']); ?></strong></div>
                </div>
            </section>
        <?php endif; ?>

        <div class="dashboard-grid user-management-stats">
            <div class="stat-card">
                <h2>Total Users</h2>
                <p>All Accounts: <strong><?php echo (int) ($stats['total_users'] ?? 0); ?></strong></p>
                <p>Filtered: <strong><?php echo $totalRecords; ?></strong></p>
            </div>
            <div class="stat-card">
                <h2>Active</h2>
                <p>Currently Active: <strong><?php echo (int) ($stats['active_users'] ?? 0); ?></strong></p>
                <p>Expiring Soon: <strong><?php echo (int) ($stats['expiring_users'] ?? 0); ?></strong></p>
            </div>
            <div class="stat-card">
                <h2>Expired</h2>
                <p>Expired Accounts: <strong><?php echo (int) ($stats['expired_users'] ?? 0); ?></strong></p>
                <p>No Expiry: <strong><?php echo (int) ($stats['no_expiry_users'] ?? 0); ?></strong></p>
            </div>
        </div>

        <section class="toolbar-card user-management-toolbar">
            <form method="GET" action="" class="user-filter-form">
                <div class="user-filter-grid">
                    <div>
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, username, or email">
                    </div>
                    <div>
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All users</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expiring" <?php echo $status === 'expiring' ? 'selected' : ''; ?>>Expiring soon</option>
                            <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="no_expiry" <?php echo $status === 'no_expiry' ? 'selected' : ''; ?>>No expiry</option>
                        </select>
                    </div>
                    <div class="user-filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <a href="/eduroam/admin/users/" class="btn btn-outline-secondary">
                            <i class="fas fa-rotate-left me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </section>

        <section class="table-card table-responsive user-management-table-card">
            <div class="table-card-heading">
                <h2>Users</h2>
                <span><?php echo $showingFrom; ?>-<?php echo $showingTo; ?> of <?php echo $totalRecords; ?></span>
            </div>
            <table class="table table-bordered table-striped align-middle user-management-table">
                <thead class="thead-dark">
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Expires At</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                                $effectiveExpiry = $user['effective_expires_at'] ?: $user['expires_at'];
                                $expiryDisplay = formatUserManagementExpiry($effectiveExpiry, $user['radius_expires_at']);
                                $requestedDisplay = formatUserManagementDate($user['requested_at']);
                                $updatedDisplay = formatUserManagementDate($user['updatedate']);
                                $accountStatus = userManagementExpiryStatus($effectiveExpiry, $user['radius_expires_at']);
                                $detailPayload = [
                                    'fullname' => $user['fullname'] ?: '-',
                                    'username' => $user['username'] ?: '-',
                                    'email' => $user['email'] ?: '-',
                                    'requested_at' => $requestedDisplay,
                                    'expires_at' => $expiryDisplay,
                                    'status' => $accountStatus,
                                    'updated_by' => $user['updateby'] ?: '-',
                                    'updated_at' => $updatedDisplay,
                                ];
                                $detailJson = htmlspecialchars(json_encode($detailPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($expiryDisplay); ?></td>
                                <td><span class="status-pill <?php echo userManagementStatusClass($accountStatus); ?>"><?php echo htmlspecialchars($accountStatus); ?></span></td>
                                <td><?php echo htmlspecialchars($updatedDisplay); ?></td>
                                <td>
                                    <div class="user-action-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userDetailsModal" data-user="<?php echo $detailJson; ?>" title="View details" aria-label="View details for <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal" data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" data-fullname="<?php echo htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); ?>" title="Delete user" aria-label="Delete <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo htmlspecialchars(paginationUrl($page - 1, $search, $status)); ?>">Previous</a>
                    <?php endif; ?>
                    <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                    ?>
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="<?php echo htmlspecialchars(paginationUrl($i, $search, $status)); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo htmlspecialchars(paginationUrl($page + 1, $search, $status)); ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="manual_create_user" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createUserModalLabel">Create Guest Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="manual_fullname" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="manual_fullname" name="manual_fullname" placeholder="Guest full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="manual_email" class="form-label">Delivery Email</label>
                                    <input type="email" class="form-control" id="manual_email" name="manual_email" placeholder="guest@example.com" required>
                                </div>
                            </div>
                        </div>
                        <p class="text-muted mb-0">Credentials will be emailed to the guest and will not be displayed in the admin interface.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userDetailsModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-detail-grid">
                        <div><span>Full Name</span><strong data-detail="fullname">-</strong></div>
                        <div><span>Username</span><strong data-detail="username">-</strong></div>
                        <div><span>Email</span><strong data-detail="email">-</strong></div>
                        <div><span>Status</span><strong data-detail="status">-</strong></div>
                        <div><span>Requested At</span><strong data-detail="requested_at">-</strong></div>
                        <div><span>Expires At</span><strong data-detail="expires_at">-</strong></div>
                        <div><span>Updated By</span><strong data-detail="updated_by">-</strong></div>
                        <div><span>Updated At</span><strong data-detail="updated_at">-</strong></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Delete <strong id="deleteUserName">this user</strong>?</p>
                    <p class="text-muted mb-0">This removes the user profile, credential rows, expiry row, and guest account record.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteUser">Delete User</button>
                </div>
            </div>
        </div>
    </div>

    <?php include dirname(__DIR__, 2) . '/template_parts/footer.php'; ?>

    <script>
        (function () {
            const alertContainer = document.getElementById('userManagementAlert');
            const detailsModal = document.getElementById('userDetailsModal');
            const deleteModal = document.getElementById('deleteUserModal');
            const confirmDeleteButton = document.getElementById('confirmDeleteUser');
            let deleteUsername = '';

            function showPageAlert(type, message) {
                alertContainer.innerHTML = '<div class="alert alert-' + type + '" role="alert">' + message + '</div>';
            }

            if (detailsModal) {
                detailsModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const user = JSON.parse(button.getAttribute('data-user') || '{}');

                    detailsModal.querySelectorAll('[data-detail]').forEach((field) => {
                        const key = field.getAttribute('data-detail');
                        field.textContent = user[key] || '-';
                    });
                });
            }

            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    deleteUsername = button.getAttribute('data-username') || '';
                    const fullname = button.getAttribute('data-fullname') || deleteUsername;
                    document.getElementById('deleteUserName').textContent = fullname + ' (' + deleteUsername + ')';
                });
            }

            if (confirmDeleteButton) {
                confirmDeleteButton.addEventListener('click', function () {
                    if (!deleteUsername) return;

                    confirmDeleteButton.disabled = true;
                    confirmDeleteButton.textContent = 'Deleting...';

                    fetch('/eduroam/admin/delete_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            username: deleteUsername
                        })
                    })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.status === 'success') {
                            bootstrap.Modal.getInstance(deleteModal).hide();
                            showPageAlert('success', data.message);
                            window.setTimeout(() => window.location.reload(), 700);
                        } else {
                            showPageAlert('danger', data.message || 'Failed to delete user.');
                        }
                    })
                    .catch(() => {
                        showPageAlert('danger', 'An error occurred while deleting the user.');
                    })
                    .finally(() => {
                        confirmDeleteButton.disabled = false;
                        confirmDeleteButton.textContent = 'Delete User';
                    });
                });
            }
        })();
    </script>
</body>
</html>
