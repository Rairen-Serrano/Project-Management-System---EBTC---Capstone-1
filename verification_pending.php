<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email | EBTC PMS</title>
    
    <!--CSS link here-->
    <link rel="stylesheet" href="css/style.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!--Font Awesome Link-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'header.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="fas fa-envelope-open-text text-primary" style="font-size: 4rem;"></i>
                        </div>
                        
                        <h2 class="mb-4" style="color: #235347;">Verify Your Email Address</h2>
                        
                        <div class="alert alert-info">
                            <p class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                A verification link has been sent to your email address.
                            </p>
                        </div>

                        <p class="mb-4">
                            Please check your email and click on the verification link to activate your account. 
                            If you don't see the email, please check your spam folder.
                        </p>

                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-primary py-2">
                                <i class="fas fa-home me-2"></i>Return to Homepage
                            </a>
                            <button type="button" class="btn btn-outline-primary py-2" id="resendBtn" onclick="resendVerification()">
                                <i class="fas fa-paper-plane me-2"></i>Resend Verification Email
                            </button>
                        </div>

                        <div class="mt-4 text-muted small">
                            <p class="mb-0">
                                Need help? <a href="contact.php" class="text-decoration-none">Contact Support</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    function resendVerification() {
        const button = document.getElementById('resendBtn');
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

        // Add AJAX call to resend verification email
        fetch('resend_verification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: '<?php echo isset($_SESSION['temp_email']) ? $_SESSION['temp_email'] : ''; ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Verification email has been resent. Please check your inbox.');
            } else {
                alert('Error resending verification email. Please try again later.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error resending verification email. Please try again later.');
        })
        .finally(() => {
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Resend Verification Email';
            }, 30000); // Enable button after 30 seconds
        });
    }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 