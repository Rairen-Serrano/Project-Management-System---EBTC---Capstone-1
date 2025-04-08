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

    <!-- Add this modal before the closing body tag -->
    <div class="modal fade" id="pinVerificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Security Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center mb-4">Please enter your 4-digit PIN to generate the report.</p>
                    <div class="pin-input-group">
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                        <input type="password" class="pin-input" maxlength="1" pattern="[0-9]" required>
                    </div>
                    <div id="pinError" class="text-danger text-center mt-2" style="display: none;">
                        Invalid PIN. Please try again.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="verifyPinBtn">Verify PIN</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update the JavaScript section -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const excelForm = document.querySelector('form[action="generate_excel.php"]');
        const pdfForm = document.querySelector('form[action="generate_pdf.php"]');
        const pinModal = new bootstrap.Modal(document.getElementById('pinVerificationModal'));
        const pinInputs = document.querySelectorAll('#pinVerificationModal .pin-input');
        const verifyPinBtn = document.getElementById('verifyPinBtn');
        
        let currentForm = null;

        // Prevent direct form submissions
        excelForm.addEventListener('submit', handleFormSubmit);
        pdfForm.addEventListener('submit', handleFormSubmit);

        function handleFormSubmit(e) {
            e.preventDefault();
            currentForm = e.target;
            // Clear previous PIN inputs
            pinInputs.forEach(input => {
                input.value = '';
            });
            pinInputs[0].focus();
            document.getElementById('pinError').style.display = 'none';
            pinModal.show();
        }

        // Handle PIN input navigation
        pinInputs.forEach((input, index) => {
            input.addEventListener('keyup', function(e) {
                if (e.key >= '0' && e.key <= '9') {
                    if (index < pinInputs.length - 1) {
                        pinInputs[index + 1].focus();
                    }
                } else if (e.key === 'Backspace') {
                    if (index > 0) {
                        pinInputs[index - 1].focus();
                    }
                }
            });
        });

        // Handle PIN verification
        verifyPinBtn.addEventListener('click', function() {
            const pin = Array.from(pinInputs).map(input => input.value).join('');
            
            if (pin.length !== 4) {
                document.getElementById('pinError').style.display = 'block';
                return;
            }

            fetch('../api/auth/verify_pin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ pin: pin })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    pinModal.hide();
                    
                    // Add verified PIN to the form
                    const pinField = document.createElement('input');
                    pinField.type = 'hidden';
                    pinField.name = 'verified_pin';
                    pinField.value = pin;
                    currentForm.appendChild(pinField);

                    // Submit the form
                    currentForm.submit();
                } else {
                    document.getElementById('pinError').textContent = data.message || 'Invalid PIN. Please try again.';
                    document.getElementById('pinError').style.display = 'block';
                    
                    // Clear PIN inputs
                    pinInputs.forEach(input => {
                        input.value = '';
                    });
                    pinInputs[0].focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('pinError').textContent = 'An error occurred. Please try again.';
                document.getElementById('pinError').style.display = 'block';
            });
        });
    });
    </script>
</body>
</html> 