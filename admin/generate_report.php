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
    <title>Generate Report | Admin Dashboard</title>

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
<body>
    <div class="admin-dashboard-wrapper">
        <?php include 'admin_header.php'; ?>
        
        <div class="admin-main-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Generate Report</h3>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <div class="row justify-content-center">
                    <!-- Excel Report Options -->
                    <div class="col-md-5 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-excel me-2"></i>Excel Report
                                </h5>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <p class="text-muted mb-4">Generate detailed Excel reports for specific data.</p>
                                <form action="generate_excel.php" method="post" class="flex-grow-1 d-flex flex-column">
                                    <div class="mb-3 flex-grow-1">
                                        <label class="form-label">Select Report Type</label>
                                        <select name="report_type" class="form-select" required>
                                            <option value="">Choose a report type...</option>
                                            <option value="users">Total Users List</option>
                                            <option value="appointments">Total Appointments List</option>
                                            <option value="employees">Total Employees List</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success mt-auto">
                                        <i class="fas fa-download me-2"></i>Generate Excel
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- PDF Report Options -->
                    <div class="col-md-5 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-pdf me-2"></i>PDF Report
                                </h5>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <p class="text-muted mb-4">Generate comprehensive PDF statistics report.</p>
                                <form action="generate_pdf.php" method="post" class="flex-grow-1 d-flex flex-column">
                                    <button type="submit" class="btn btn-danger mt-auto">
                                        <i class="fas fa-download me-2"></i>Generate PDF
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const excelForm = document.querySelector('form[action="generate_excel.php"]');
        
        excelForm.addEventListener('submit', function(e) {
            const reportType = this.querySelector('select[name="report_type"]').value;
            console.log('Submitting Excel report form with type:', reportType);
            
            // Add a hidden field with timestamp to prevent caching
            const timestampField = document.createElement('input');
            timestampField.type = 'hidden';
            timestampField.name = 'timestamp';
            timestampField.value = new Date().getTime();
            this.appendChild(timestampField);
        });
    });
    </script>
</body>
</html> 