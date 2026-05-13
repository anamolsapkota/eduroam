<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    header('Location: /eduroam/login.php');
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

$adminShellBaseDir = dirname(__DIR__, 2);
require_once $adminShellBaseDir . '/includes/config.php';

$adminCurrentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$adminCurrentPath = rtrim($adminCurrentPath, '/');

function adminNavActive($path) {
    global $adminCurrentPath;
    $check = rtrim($path, '/');
    if ($check === '/eduroam/admin' && ($adminCurrentPath === '/eduroam/admin' || $adminCurrentPath === '/eduroam/admin/index.php')) {
        return true;
    }
    if ($check !== '/eduroam/admin' && strpos($adminCurrentPath, $check) === 0) {
        return true;
    }
    return false;
}

$adminUserFullname = $_SESSION['user']['fullname'] ?? 'Admin';
$adminUserEmail = $_SESSION['user']['email'] ?? '';
$adminUserInitials = '';
$nameParts = preg_split('/\s+/', trim($adminUserFullname));
if (count($nameParts) >= 2) {
    $adminUserInitials = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1));
} else {
    $adminUserInitials = strtoupper(mb_substr($adminUserFullname, 0, 2));
}

$seo_title = $seo_title ?? ($site_name . ' Admin');
$admin_page_title = $admin_page_title ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <link rel="stylesheet" href="/eduroam/assets/css/styles.css">
    <link rel="stylesheet" href="/eduroam/assets/css/admin-shell.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
<div class="admin-shell" id="adminShell">
    <div class="admin-sidebar-backdrop" id="sidebarBackdrop"></div>

    <aside class="admin-sidebar" id="adminSidebar">
        <a href="/eduroam/admin/" class="sidebar-logo">
            <span class="sidebar-logo-icon">
                <img src="/eduroam/assets/images/logo.jpg" alt="eduroam">
            </span>
            <span class="sidebar-logo-text">
                <span class="sidebar-logo-title"><?php echo htmlspecialchars($site_name); ?></span>
                <span class="sidebar-logo-subtitle">Admin Portal</span>
            </span>
        </a>

        <nav class="sidebar-nav">
            <div class="sidebar-nav-group">
                <div class="sidebar-nav-label">Main</div>
                <a href="/eduroam/admin/" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin') ? ' active' : ''; ?>">
                    <i class="fas fa-gauge-high"></i>
                    <span class="sidebar-nav-item-text">Dashboard</span>
                </a>
                <a href="/eduroam/admin/users/" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin/users') ? ' active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span class="sidebar-nav-item-text">Users</span>
                </a>
                <a href="/eduroam/admin/analytics/" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin/analytics') ? ' active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span class="sidebar-nav-item-text">Analytics</span>
                </a>
                <a href="/eduroam/admin/graphs/" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin/graphs') ? ' active' : ''; ?>">
                    <i class="fas fa-chart-area"></i>
                    <span class="sidebar-nav-item-text">Monitoring</span>
                </a>
            </div>
            <div class="sidebar-nav-group">
                <div class="sidebar-nav-label">System</div>
                <a href="/eduroam/admin/nas/" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin/nas') ? ' active' : ''; ?>">
                    <i class="fas fa-server"></i>
                    <span class="sidebar-nav-item-text">NAS</span>
                </a>
                <a href="/eduroam/admin/logs/" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin/logs') ? ' active' : ''; ?>">
                    <i class="fas fa-file-lines"></i>
                    <span class="sidebar-nav-item-text">Logs</span>
                </a>
                <a href="/eduroam/admin/admins/" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin/admins') ? ' active' : ''; ?>">
                    <i class="fas fa-user-shield"></i>
                    <span class="sidebar-nav-item-text">Admins</span>
                </a>
                <a href="/eduroam/admin/settings.php" class="sidebar-nav-item<?php echo adminNavActive('/eduroam/admin/settings') ? ' active' : ''; ?>">
                    <i class="fas fa-gear"></i>
                    <span class="sidebar-nav-item-text">Settings</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-user">
            <span class="sidebar-avatar"><?php echo htmlspecialchars($adminUserInitials); ?></span>
            <span class="sidebar-user-info">
                <span class="sidebar-user-name"><?php echo htmlspecialchars($adminUserFullname); ?></span>
                <span class="sidebar-user-role">Administrator</span>
            </span>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-header">
            <div class="admin-header-left">
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <span></span><span></span><span></span>
                </button>
                <a href="/eduroam/admin/" class="admin-header-brand">
                    <span class="admin-header-brand-icon">
                        <img src="/eduroam/assets/images/logo.jpg" alt="eduroam">
                    </span>
                    <span class="admin-header-brand-name"><?php echo htmlspecialchars($site_name); ?> Admin</span>
                </a>
                <span class="admin-breadcrumb">
                    <span class="admin-breadcrumb-parent">Admin</span>
                    <span class="admin-breadcrumb-sep">/</span>
                    <span class="admin-breadcrumb-current"><?php echo htmlspecialchars($admin_page_title); ?></span>
                </span>
            </div>
            <div class="admin-header-right">
                <div class="admin-clock">
                    <span class="admin-clock-dot"></span>
                    <span class="admin-clock-time" id="adminClock">--:--:-- NPT</span>
                </div>
                <span class="admin-header-divider"></span>
                <span class="admin-header-avatar sidebar-avatar"><?php echo htmlspecialchars($adminUserInitials); ?></span>
                <a href="/eduroam/logout.php" class="admin-logout" onclick="return confirm('Are you sure you want to logout?');">
                    <span>Logout</span>
                    <i class="fas fa-right-from-bracket"></i>
                </a>
            </div>
        </header>

        <main class="admin-content">
