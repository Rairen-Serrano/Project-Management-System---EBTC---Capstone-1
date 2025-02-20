<?php
session_start();
require_once '../dbconnect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee | Admin Dashboard</title>

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
<body id="adminAddEmployeePage">
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Add New Employee</h4>
                <a href="employees.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Employees
                </a>
            </div>

            <!-- Add Employee Form -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <form id="addEmployeeForm" action="add_employee.php" method="POST">
                        <!-- Personal Information Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-user me-2"></i>Personal Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" id="addEmployeeName" required 
                                           pattern="[A-Za-z\s]+" title="Please enter a valid name (letters and spaces only)">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" id="addEmployeePhone" required 
                                           pattern="[0-9]+" title="Please enter a valid phone number (numbers only)">
                                </div>
                            </div>
                        </div>

                        <!-- Account Information Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-id-card me-2"></i>Account Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" id="addEmployeeEmail" required>
                                    <div class="form-text">This will be used for login.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" name="role" id="addEmployeeRole" required>
                                        <option value="">Select Role</option>
                                        <option value="project_manager">Project Manager</option>
                                        <option value="engineer">Engineer</option>
                                        <option value="technician">Technician</option>
                                        <option value="worker">Worker</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Password Section -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>Generated Password
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Auto-generated Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="password" id="addEmployeePassword" readonly>
                                        <button type="button" class="btn btn-outline-secondary" onclick="copyPassword()">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="generateNewPassword()">
                                            <i class="fas fa-sync-alt"></i> Generate New
                                        </button>
                                    </div>
                                    <div class="form-text">This password will be required for the employee's first login. They can change it later.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="text-end">
                            <a href="employees.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="button" class="btn btn-primary" onclick="addEmployee()">
                                <i class="fas fa-plus me-2"></i>Add Employee
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Function to generate a secure password
    function generatePassword() {
        const length = 12;
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
        let password = "";
        for (let i = 0; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        return password;
    }

    // Function to copy password to clipboard
    function copyPassword() {
        const passwordField = document.getElementById('addEmployeePassword');
        passwordField.select();
        document.execCommand('copy');
    }

    // Function to generate new password
    function generateNewPassword() {
        const passwordField = document.getElementById('addEmployeePassword');
        passwordField.value = generatePassword();
    }

    // Generate initial password when page loads
    document.addEventListener('DOMContentLoaded', function() {
        generateNewPassword();
    });

    function addEmployee() {
        const form = document.getElementById('addEmployeeForm');
        const formData = new FormData(form);

        // Send AJAX request
        fetch('add_employee.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Employee added successfully!');
                window.location.href = 'employees.php';
            } else {
                alert(data.message || 'Error adding employee');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding employee');
        });
    }
    </script>
</body>
</html> 