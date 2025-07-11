<nav class="navbar navbar-expand-lg navbar-light pt-3 pb-3" id="navbar">
    <div class="container">
        <!-- Brand/Logo -->
        <a class="navbar-brand fw-bold" href="/eduroam/management.php">
            <?php echo $site_name; ?> Management
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible Content -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Navigation Menu Items (Left Side) -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if(isset($_SESSION['user'])) : ?>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="/eduroam/admin/users">Users</a>
                    </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/management.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/nas">NAS</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/eduroam/admin/logs">Logs</a>
                </li>
                <?php endif; ?>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="/eduroam/reports.php">Reports</a>
                </li> -->
            </ul>

            <!-- User Info and Logout (Right Side) -->
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
                    <!-- User Dropdown -->
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
                            <!-- <li><a class="dropdown-item" href="/eduroam/profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="/eduroam/settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li> -->
                            <li>
                                <form action="logout.php" method="post" class="d-inline">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </div>
            <?php else : ?>
                <!-- Login Button for Non-Authenticated Users -->
                <div class="navbar-nav ms-auto">
                    <a class="nav-link btn btn-outline-primary px-3" href="/eduroam/login.php">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Additional CSS for better styling -->
<style>
/* Custom navbar styles */
.navbar {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    background-color: #fff;
}

.navbar-brand {
    font-size: 1.5rem;
    color: #fff !important;
}

.nav-link {
    font-weight: 500;
    color: #fff !important;
    transition: color 0.3s ease;
}

.nav-link:hover {
    color: #007bff !important;
}

.dropdown-menu {
    border: 1px solid #e9ecef;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.dropdown-item-text {
    font-size: 0.875rem;
}

/* Mobile responsive adjustments */
@media (max-width: 991.98px) {
    .navbar-nav {
        text-align: center;
    }
    
    .navbar-nav .nav-link {
        padding: 0.75rem 1rem;
    }
    
    .dropdown-menu {
        position: static !important;
        float: none;
        width: 100%;
        margin-top: 0;
        background-color: transparent;
        border: 0;
        box-shadow: none;
    }
    
    .dropdown-item {
        text-align: center;
    }
}

/* Additional mobile styles */
@media (max-width: 575.98px) {
    .navbar-brand {
        font-size: 1.25rem;
    }
    
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
}
</style>