<?php
// Ensure this file is included in a valid context
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'ceo') {
    header('Location: ../admin_login.php');
    exit;
}

// Get unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();
?>

<!-- Header -->
<header class="engineer-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center">
                <!-- Logo -->
                <a class="navbar-brand d-flex align-items-center m-0" href="#">
                    <img src="../images/EBTC_logo.png" alt="EBTC Logo" class="header-logo me-2">
                    <p class="text-white fw-500 m-0" style="font-family: 'Poppins', sans-serif;">Equalizer Builders Technologies Corporation</p>
                </a>
            </div>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#engineerNavbarContent">
                <i class="fas fa-bars text-white"></i>
            </button>

            <div class="collapse navbar-collapse" id="engineerNavbarContent">
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
<nav class="engineer-sidebar">
    <div class="sidebar-menu">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </a>
        <a href="project_overview.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'project_overview.php' ? 'active' : ''; ?>">
            <i class="fas fa-project-diagram"></i>
            Project Overview
        </a>
        <a href="employee_status.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'employee_status.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            Employee Status
        </a>
        <a href="clients.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            Clients
        </a>
        <a href="../logout.php">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
</nav> 