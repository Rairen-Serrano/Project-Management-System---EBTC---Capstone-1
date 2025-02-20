<?php
// Ensure this file is included in a valid context
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'worker') {
    header('Location: ../admin_login.php');
    exit;
}
?>

<!-- Header -->
<header class="worker-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center">
                <!-- Sidebar Toggle Button -->
                <button class="btn btn-link text-white sidebar-toggle" id="headerSidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo -->
                <a class="navbar-brand d-flex align-items-center m-0" href="#">
                    <img src="../images/EBTC_logo.png" alt="EBTC Logo" class="header-logo me-2">
                    <p class="text-white fw-500 m-0" style="font-family: 'Poppins', sans-serif;">Equalizer Builders Technologies Corporation</p>
                </a>
            </div>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#workerNavbarContent">
                <i class="fas fa-bars text-white"></i>
            </button>

            <div class="collapse navbar-collapse" id="workerNavbarContent">
                <ul class="navbar-nav ms-auto align-items-center">
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
<nav class="worker-sidebar">
    <div class="worker-sidebar-menu">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </a>
        <a href="tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks"></i>
            My Tasks
        </a>
        <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>">
            <i class="fas fa-project-diagram"></i>
            Projects
        </a>
        <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            Reports
        </a>
        <a href="materials.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'materials.php' ? 'active' : ''; ?>">
            <i class="fas fa-tools"></i>
            Materials
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('headerSidebarToggle');
    const sidebar = document.querySelector('.worker-sidebar');
    const mainContent = document.querySelector('.worker-main-content');
    
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    });

    // Mobile responsive handling
    function checkWidth() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        }
    }

    window.addEventListener('resize', checkWidth);
    checkWidth(); // Initial check
});
</script> 