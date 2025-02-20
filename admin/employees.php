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
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$query = "SELECT * FROM users WHERE role != 'client' AND archived = 'No'";
$params = [];

// Add role filter
if ($role_filter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

// Add status filter
if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
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
$query .= " ORDER BY date_created DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique roles for filter dropdown
$role_stmt = $pdo->query("SELECT DISTINCT role FROM users WHERE role != 'client' ORDER BY role");
$roles = $role_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management | Admin Dashboard</title>

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
<body id="adminEmployeesPage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">Employee Management</h4>
                </div>
                <div class="d-flex gap-2">
                    <a href="archived_employees.php" class="btn btn-secondary">
                        <i class="fas fa-archive"></i> Archived Employees
                    </a>
                    <a href="add_employee_form.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add New Employee
                    </a>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Role Filter -->
                        <div class="col-md-3">
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

                        <!-- Status Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, email, or phone" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($_GET)): ?>
                                    <a href="employees.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Employees Table -->
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
                                    <th>Status</th>
                                    <th>Joined Date</th>
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
                                                <span class="badge bg-primary">
                                                    <?php echo ucfirst($employee['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($employee['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($employee['date_created'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick='editEmployee(<?php echo json_encode($employee); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($employee['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="archiveEmployee(<?php echo $employee['user_id']; ?>)">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="activateEmployee(<?php echo $employee['user_id']; ?>)">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <?php if (!empty($search) || $role_filter !== 'all' || $status_filter !== 'all'): ?>
                                                <div class="text-muted">
                                                    <i class="fas fa-search me-2"></i>No employees found matching your criteria
                                                </div>
                                                <a href="employees.php" class="btn btn-sm btn-outline-primary mt-2">
                                                    Clear Filters
                                                </a>
                                            <?php else: ?>
                                                <div class="text-muted">
                                                    <i class="fas fa-users me-2"></i>No employees found
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

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editEmployeeForm">
                        <input type="hidden" id="editEmployeeId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" id="editEmployeeName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmployeeEmail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="editEmployeePhone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="editEmployeeRole" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role); ?>">
                                        <?php echo ucfirst($role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateEmployee()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 