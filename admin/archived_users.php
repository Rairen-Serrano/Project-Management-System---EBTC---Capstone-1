<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Get all archived users
$query = "SELECT 
    u.user_id,
    u.name,
    u.email,
    u.phone,
    u.date_created
FROM users u 
WHERE u.role = 'client' 
AND u.archived = 'Yes'
ORDER BY u.date_created DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Users | Admin Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../css/style.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
</head>
<body id="adminArchivedUsersPage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Archived Users</h4>
                </div>
                <div>
                    <a href="users.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="unarchiveUser(<?php echo $user['user_id']; ?>)">
                                                        <i class="fas fa-box-open"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No archived users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update the View User Modal section with the complete code -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Name:</div>
                        <div class="col-8" id="viewUserName"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Email:</div>
                        <div class="col-8" id="viewUserEmail"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Phone:</div>
                        <div class="col-8" id="viewUserPhone"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4 fw-bold">Joined Date:</div>
                        <div class="col-8" id="viewUserJoinedDate"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function viewUser(userId) {
        // Show loading state
        const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
        viewModal.show();
        
        // Clear previous data
        document.getElementById('viewUserName').textContent = 'Loading...';
        document.getElementById('viewUserEmail').textContent = 'Loading...';
        document.getElementById('viewUserPhone').textContent = 'Loading...';
        document.getElementById('viewUserJoinedDate').textContent = 'Loading...';
        
        // Fetch user details
        fetch(`get_user_details.php?id=${userId}`)
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                
                if (data.success && data.user) {
                    const user = data.user;
                    document.getElementById('viewUserName').textContent = user.name || 'N/A';
                    document.getElementById('viewUserEmail').textContent = user.email || 'N/A';
                    document.getElementById('viewUserPhone').textContent = user.phone || 'N/A';
                    document.getElementById('viewUserJoinedDate').textContent = user.date_created || 'N/A';
                } else {
                    console.error('Error in response:', data);
                    alert(data.message || 'Error loading user details');
                    bootstrap.Modal.getInstance(document.getElementById('viewUserModal')).hide();
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error loading user details: ' + error.message);
                bootstrap.Modal.getInstance(document.getElementById('viewUserModal')).hide();
            });
    }

    function unarchiveUser(userId) {
        if (confirm('Are you sure you want to unarchive this user?')) {
            fetch('unarchive_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User unarchived successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Error unarchiving user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error unarchiving user');
            });
        }
    }
    </script>
</body>
</html> 