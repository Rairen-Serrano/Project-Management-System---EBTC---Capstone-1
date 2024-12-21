<?php
session_start();
require_once 'dbconnect.php';
include 'header.php';

$verification_status = '';
$status_class = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $pdo->beginTransaction();
        
        // Get verification record
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM email_verifications 
            WHERE token = ? AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $verification = $stmt->fetch();
        
        if ($verification) {
            // Activate user
            $stmt = $pdo->prepare("
                UPDATE users 
                SET status = 'active' 
                WHERE user_id = ?
            ");
            $stmt->execute([$verification['user_id']]);
            
            // Delete verification record
            $stmt = $pdo->prepare("
                DELETE FROM email_verifications 
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            $pdo->commit();
            
            $verification_status = 'Your email has been verified successfully! You can now login to your account.';
            $status_class = 'success';
        } else {
            $verification_status = 'Invalid or expired verification link. Please request a new verification email.';
            $status_class = 'danger';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $verification_status = 'Verification error. Please try again.';
        $status_class = 'danger';
        error_log("Email verification error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | EBTC PMS</title>
    
    <!--CSS link here-->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4 text-center">
                        <?php if ($status_class === 'success'): ?>
                            <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-danger" style="font-size: 48px;"></i>
                        <?php endif; ?>
                        
                        <h3 class="mt-3 mb-4">Email Verification</h3>
                        
                        <div class="alert alert-<?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($verification_status); ?>
                        </div>
                        
                        <a href="index.php" class="btn btn-primary">Go to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
?> 