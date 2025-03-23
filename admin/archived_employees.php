<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Get filter and search parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query for archived employees
$query = "SELECT * FROM users WHERE role != 'client' AND archived = 'Yes'";
$params = [];

// Add role filter
if ($role_filter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

// Add search condition
if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
$query .= " ORDER BY archived_date DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique roles for filter dropdown
$role_stmt = $pdo->query("SELECT DISTINCT role FROM users WHERE role != 'client' AND archived = 'Yes' ORDER BY role");
$roles = $role_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Employees | Admin Dashboard</title>

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
<body id="adminArchivedEmployeesPage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Archived Employees</h4>
                </div>
                <div>
                    <a href="employees.php" class="btn btn-primary">
                        <i class="fas fa-users"></i> Active Employees
                    </a>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Role Filter -->
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role); ?>" 
                                            <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="col-md-8">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, email, or phone" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($_GET)): ?>
                                    <a href="archived_employees.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Archived Employees Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Archived Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($employees) > 0): ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                            <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($employee['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($employee['archived_date'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="restoreEmployee(<?php echo $employee['user_id']; ?>)">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="deleteEmployee(<?php echo $employee['user_id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <?php if (!empty($search) || $role_filter !== 'all'): ?>
                                                <div class="text-muted">
                                                    <i class="fas fa-search me-2"></i>No archived employees found matching your criteria
                                                </div>
                                                <a href="archived_employees.php" class="btn btn-sm btn-outline-primary mt-2">
                                                    Clear Filters
                                                </a>
                                            <?php else: ?>
                                                <div class="text-muted">
                                                    <i class="fas fa-archive me-2"></i>No archived employees found
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this JavaScript function for restore functionality -->
    <script>
    function restoreEmployee(userId) {
        if (confirm('Are you sure you want to restore this employee?')) {
            // Create form data
            const formData = new FormData();
            formData.append('user_id', userId);

            // Send restore request
            fetch('restore_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Employee restored successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Error restoring employee');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error restoring employee');
            });
        }
    }
    </script>
</body>
</html> 