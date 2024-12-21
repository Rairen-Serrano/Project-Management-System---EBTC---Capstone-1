<?php
// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<header class="login-header">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between py-3">
            <div class="d-flex align-items-center">
                <a href="/index.php" class="text-decoration-none d-flex align-items-center">
                    <img src="/images/EBTC_logo.png" alt="EBTC Logo" class="header-logo me-3">
                    <h1 class="header-title mb-0">Equalizer Builders Technologies Corporation PMS</h1>
                </a>
            </div>
            <?php if ($current_page !== 'admin_login.php'): ?>
            <div>
                <a href="admin_login.php" class="btn btn-outline-secondary btn-sm">Admin Login</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>
