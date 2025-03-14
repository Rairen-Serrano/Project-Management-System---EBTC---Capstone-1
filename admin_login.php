<?php
session_start();
require_once 'dbconnect.php';

// Add this at the beginning of the file, after session_start()
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Clear any existing redirect loop
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 5) {
    session_unset();
    session_destroy();
    session_start();
}

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['role'])) {
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'project_manager':
            header('Location: manager/dashboard.php');
            break;
        case 'engineer':
        case 'technician':
        case 'worker':
            header('Location: engineer/dashboard.php');
            break;
        default:
            session_unset();
            session_destroy();
            session_start();
    }
    exit;
}

// Debug POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST request received');
    error_log('POST data: ' . print_r($_POST, true));
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("Attempting login for email: $email");
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' AND role != 'client'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("User query executed. Found user: " . ($user ? 'Yes' : 'No'));
        
        if ($user) {
            error_log("User role: " . $user['role']);
            error_log("Password verification: " . (password_verify($password, $user['password']) ? 'Success' : 'Failed'));
        }
        
        if ($user && password_verify($password, $user['password'])) {
            error_log("Login successful for user: {$user['name']} with role: {$user['role']}");
            
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            error_log("Session data set: " . print_r($_SESSION, true));
            
            switch($user['role']) {
                case 'admin':
                    error_log("Redirecting to admin dashboard");
                    header('Location: admin/dashboard.php');
                    break;
                case 'project_manager':
                    error_log("Redirecting to manager dashboard");
                    header('Location: manager/dashboard.php');
                    break;
                case 'engineer':
                    header('Location: engineer/dashboard.php');
                    break;
                case 'technician':
                    header('Location: technician/dashboard.php');
                    break;
                case 'worker':
                    header('Location: worker/dashboard.php');
                    break;
                default:
                    error_log("Redirecting to index");
                    header('Location: index.php');
            }
            exit();
        } else {
            error_log("Login failed - Invalid credentials");
            $error_message = 'Invalid email or password';
        }
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = 'Login error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | EBTC PMS</title>
    
    <!--CSS link here-->
    <link rel="stylesheet" href="css/style.css">

    <!--Javascript link here-->
    <script defer src="js/script.js"></script>
    
    <!-- jQuery (use CDN only) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!--Font Awesome Link-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

</head>
<body id="adminLoginPage">
    <?php include 'header.php'; ?>
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Staff Login</h2>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="admin_login.php" id="loginForm">
                            <div class="form-floating mb-4">
                                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                                <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                            </div>

                            <div class="form-floating mb-4">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3">Login</button>

                            <p style="margin-top: 10px;"></p>
                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-2"></i>Back to User Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        console.log('Form submitted');
    });
    </script>
</body>
</html> 