<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}

// Get user's information
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        // Verify PIN code
        if (!isset($_POST['pin_code']) || strlen($_POST['pin_code']) !== 4) {
            throw new Exception('Please enter a valid 4-digit PIN code');
        }

        if ($_POST['pin_code'] !== $user['pin_code']) {
            throw new Exception('Invalid PIN code');
        }

        // Handle profile photo upload
        $profile_photo = $user['profile_photo']; // Keep existing photo by default
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_photo']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Invalid file type. Please upload a JPEG, PNG, or GIF image.');
            }

            $max_size = 5 * 1024 * 1024; // 5MB
            if ($_FILES['profile_photo']['size'] > $max_size) {
                throw new Exception('File size too large. Maximum size is 5MB.');
            }

            $upload_dir = '../uploads/profile_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $file_name = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_path)) {
                // Delete old profile photo if exists
                if ($user['profile_photo'] && file_exists('../' . $user['profile_photo'])) {
                    unlink('../' . $user['profile_photo']);
                }
                $profile_photo = 'uploads/profile_photos/' . $file_name;
            }
        }

        // Update user information
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, 
                phone = ?,
                job_title = ?,
                department = ?,
                about_me = ?,
                profile_photo = ?
            WHERE user_id = ?
        ");
        
        $stmt->execute([
            $_POST['name'],
            $_POST['phone'],
            $_POST['job_title'],
            $_POST['department'],
            $_POST['about_me'],
            $profile_photo,
            $_SESSION['user_id']
        ]);

        $_SESSION['success_message'] = 'Profile updated successfully!';
        header('Location: profile.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | EBTC PMS</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .profile-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #235347;
        }
        .profile-photo-upload {
            position: relative;
            display: inline-block;
        }
        .profile-photo-upload .upload-icon {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #235347;
            color: white;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
        }
        .readonly-field {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        .pin-input-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }

        .pin-input {
            width: 45px;
            height: 45px;
            text-align: center;
            font-size: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: #f8f9fa;
            transition: border-color 0.3s;
        }

        .pin-input:focus {
            border-color: #0d6efd;
            outline: none;
            box-shadow: none;
        }
    </style>
</head>
<body id="profilePage">
    <?php include 'client_header.php'; ?>
    
    <div class="client-dashboard-wrapper">
        <!-- Main Content -->
        <div class="client-main-content">
            <!-- Mobile Toggle Button -->
            <button class="btn btn-primary d-md-none mb-3" id="clientSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">My Profile</h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" enctype="multipart/form-data" id="profileForm">
                                <!-- Profile Photo -->
                                <div class="text-center mb-4">
                                    <div class="profile-photo-upload">
                                        <img src="<?php echo $user['profile_photo'] ? '../' . htmlspecialchars($user['profile_photo']) : '../images/default-avatar.png'; ?>" 
                                             alt="Profile Photo" 
                                             class="profile-photo mb-3" 
                                             id="profilePhotoPreview">
                                        <label for="profile_photo" class="upload-icon">
                                            <i class="fas fa-camera"></i>
                                        </label>
                                        <input type="file" 
                                               id="profile_photo" 
                                               name="profile_photo" 
                                               accept="image/*" 
                                               style="display: none;"
                                               onchange="previewImage(this)">
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Personal Information -->
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="name" 
                                               name="name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" 
                                               required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" 
                                               class="form-control readonly-field" 
                                               id="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               readonly>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="phone" 
                                               name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                               required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <input type="text" 
                                               class="form-control readonly-field" 
                                               id="role" 
                                               value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" 
                                               readonly>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="job_title" class="form-label">Job Title</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="job_title" 
                                               name="job_title" 
                                               value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Department/Team</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="department" 
                                               name="department" 
                                               value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label for="about_me" class="form-label">About Me</label>
                                        <textarea class="form-control" 
                                                  id="about_me" 
                                                  name="about_me" 
                                                  rows="4"><?php echo htmlspecialchars($user['about_me'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <input type="hidden" name="update_profile" value="1">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePhotoPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.substr(0, 11);
            }
            e.target.value = value;
        });

        // Handle PIN input fields
        const pinInputs = document.querySelectorAll('.pin-input');
        pinInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value) {
                    e.target.value = value[0];
                    if (index < pinInputs.length - 1) {
                        pinInputs[index + 1].focus();
                    }
                }
                updatePinCode();
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    pinInputs[index - 1].focus();
                }
            });
        });

        function updatePinCode() {
            const pin = Array.from(pinInputs).map(input => input.value).join('');
            document.getElementById('pin_code').value = pin;
        }

        // Handle form submission with PIN verification
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
            pinInputs.forEach(input => input.value = '');
            document.getElementById('pin_code').value = '';
            modal.show();
        });

        // Handle PIN confirmation
        document.getElementById('confirmPin').addEventListener('click', function() {
            const pinCode = document.getElementById('pin_code').value;
            
            if (pinCode.length !== 4 || !/^\d{4}$/.test(pinCode)) {
                alert('Please enter a valid 4-digit PIN');
                return;
            }

            // Add the PIN to the form and submit
            const form = document.getElementById('profileForm');
            const pinInput = document.createElement('input');
            pinInput.type = 'hidden';
            pinInput.name = 'pin_code';
            pinInput.value = pinCode;
            form.appendChild(pinInput);
            form.submit();
        });
    </script>

    <!-- PIN Verification Modal -->
    <div class="modal fade" id="pinVerificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verify Changes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <p class="mb-3">Enter your 4-digit PIN to confirm changes</p>
                        <div class="pin-input-group">
                            <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                            <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                            <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                            <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        </div>
                        <input type="hidden" id="pin_code" name="pin_code">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmPin">Confirm Changes</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 