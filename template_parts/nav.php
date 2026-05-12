<nav class="navbar navbar-expand-lg app-navbar" id="navbar">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo isset($_SESSION['user']) ? '/eduroam/admin/' : '/eduroam/'; ?>">
            <span class="brand-mark brand-mark--dual">
                <img src="/eduroam/assets/images/nren-logo.jpg" alt="NREN logo" class="brand-logo">
                <img src="/eduroam/assets/images/eduroam-logo.png" alt="eduroam logo" class="brand-logo">
            </span>
            <span class="brand-copy">
                <span class="brand-title"><?php echo $site_name; ?></span>
                <span class="brand-accent"><?php echo isset($_SESSION['user']) ? 'Admin Portal' : 'Guest Access'; ?></span>
            </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if(isset($_SESSION['user'])) : ?>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/users/">Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/analytics/">Analytics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/graphs/">Monitoring</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/nas">NAS</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/logs">Logs</a>
                </li>
                <?php else : ?>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/request.php">Request Access</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/faq.php">FAQ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/forgotpass.php">Reset Password</a>
                </li>
                <?php endif; ?>
            </ul>

            <?php if(isset($_SESSION['user'])) : ?>
                <?php
                    $userDetails = $_SESSION['user'];
                    if(isset($userDetails['username'])) {
                        $username = $userDetails['username'];
                        $email = $userDetails['email'];
                        $fullname = $userDetails['fullname'];
                    }
                ?>
                
                <div class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" 
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-2"></i>
                            <span class="d-none d-md-inline"><?php echo $fullname; ?></span>
                            <span class="d-md-none">Profile</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><h6 class="dropdown-header">Logged in as:</h6></li>
                            <li><span class="dropdown-item-text small text-muted"><?php echo $fullname; ?></span></li>
                            <li><span class="dropdown-item-text small text-muted"><?php echo $email; ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/eduroam/admin/settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="/eduroam/admin/logout.php" method="post" class="d-inline">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
