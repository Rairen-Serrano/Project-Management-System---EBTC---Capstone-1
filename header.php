<?php
// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="/index.php">
            <img src="/images/EBTC_logo.png" alt="EBTC Logo" style="width: 150px; height: auto;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php#home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/index.php#services">Services</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/index.php#about">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/index.php#contact">Contact</a>
                </li>
            </ul>
            <div class="d-flex">
                <?php if (isset($_SESSION['logged_in'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <?php
                            // Set dashboard link based on role
                            $dashboard_link = '';
                            switch($_SESSION['role']) {
                                case 'admin':
                                    $dashboard_link = '/admin/dashboard.php';
                                    break;
                                case 'engineer':
                                    $dashboard_link = '/engineer/dashboard.php';
                                    break;
                                case 'project_manager':
                                    $dashboard_link = '/manager/dashboard.php';
                                    break;
                                case 'worker':
                                    $dashboard_link = '/worker/dashboard.php';
                                    break;
                                case 'client':
                                    $dashboard_link = '/client/dashboard.php';
                                    break;
                            }
                            ?>
                            <li><a class="dropdown-item" href="<?php echo $dashboard_link; ?>"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                            <?php if ($_SESSION['role'] === 'client'): ?>
                                <li><a class="dropdown-item" href="/client/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="/client/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <?php if ($current_page === 'login.php' || $current_page === 'register.php'): ?>
                        <a href="/admin_login.php" class="btn btn-outline-light me-2">Admin Login</a>
                    <?php endif; ?>
                    <a href="/login.php" class="btn btn-outline-light me-2">Sign In</a>
                    <a href="/register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
