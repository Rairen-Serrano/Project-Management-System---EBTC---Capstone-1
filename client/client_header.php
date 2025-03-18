<?php
// Ensure this file is included in a valid context
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}
?>

<!-- Header -->
<header class="client-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">
            <!-- Sidebar Toggle Button -->
            <button class="btn btn-link text-white me-3 sidebar-toggle" id="headerSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Logo -->
            <a class="navbar-brand" href="../index.php" style="display: flex; align-items: center; text-decoration: none;">
                <img src="../images/EBTC_logo.png" alt="EBTC Logo" class="header-logo" style="margin-right: 10px;">
                <p style="color: white; font-family: 'Poppins', sans-serif; font-weight: 500; margin: 0;">Equalizer Builders Technologies Corporation</p>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#clientNavbarContent">
                <i class="fas fa-bars text-white"></i>
            </button>

            <div class="collapse navbar-collapse" id="clientNavbarContent">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Notifications Dropdown -->
                    <li class="nav-item dropdown me-3">
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">New message from admin</a></li>
                            <li><a class="dropdown-item" href="#">Project status updated</a></li>
                            <li><a class="dropdown-item" href="#">Document approved</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                        </ul>
                    </li>

                    <!-- User Name Display -->
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($_SESSION['name']); ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header> 

<!-- Sidebar -->
<nav class="client-sidebar">
    <div class="client-sidebar-menu">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            My Appointments
        </a>
        <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>">
            <i class="fas fa-project-diagram"></i>
            My Projects
        </a>
        <a href="notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            Notifications
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            Profile
        </a>
        <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            Settings
        </a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
</nav>