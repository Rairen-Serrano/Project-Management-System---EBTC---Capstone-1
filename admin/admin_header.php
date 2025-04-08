<?php
// Ensure this file is included in a valid context
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Get unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();
?>

<!-- Header -->
<header class="admin-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">

            <!-- Logo -->
            <a class="navbar-brand" href="#" style="display: flex; align-items: center; text-decoration: none;">
                <img src="../images/EBTC_logo.png" alt="EBTC Logo" class="header-logo" style="margin-right: 10px;">
                <p style="color: white; font-family: 'Poppins', sans-serif; font-weight: 500; margin: 0;">Equalizer Builders Technologies Corporation</p>
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarContent">
                <i class="fas fa-bars text-white"></i>
            </button>

            <div class="collapse navbar-collapse" id="adminNavbarContent">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Notifications -->
                    <li class="nav-item me-3 position-relative">
                        <a href="notifications.php" class="nav-link text-white">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                                    <span class="visually-hidden">Unread notifications</span>
                                </span>
                            <?php endif; ?>
                        </a>
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
<nav class="admin-sidebar">
    <div class="admin-sidebar-menu">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </a>
        <a href="appointments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            Appointments
        </a>
        <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            Users
        </a>
        <a href="employees.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            Employees
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