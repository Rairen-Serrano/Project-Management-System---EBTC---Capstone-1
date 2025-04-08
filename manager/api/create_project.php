<?php
// Add these at the very top
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../dbconnect.php';

// Set JSON header before any output
header('Content-Type: application/json');

// Add at the top of create_project.php after the headers
error_log('Received POST data: ' . print_r($_POST, true));
error_log('Received FILES data: ' . print_r($_FILES, true));

// Check for session and role
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'project_manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Add this function at the top of create_project.php after the headers
function isPDF($file) {
    if (!isset($file['type'])) {
        return false;
    }
    
    // Check MIME type
    if ($file['type'] !== 'application/pdf') {
        return false;
    }
    
    // Additional check for file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    return $extension === 'pdf';
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, get the client_id and service from the appointment
    $stmt = $pdo->prepare("SELECT client_id, service FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$_POST['appointment_id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Format dates to ensure proper MySQL date format
    $start_date = date('Y-m-d', strtotime($_POST['start_date']));
    $end_date = date('Y-m-d', strtotime($_POST['end_date']));

    // Validate dates
    if (!$start_date || $start_date === '1970-01-01' || $start_date === '-0001-11-30') {
        throw new Exception('Invalid start date format');
    }
    if (!$end_date || $end_date === '1970-01-01' || $end_date === '-0001-11-30') {
        throw new Exception('Invalid end date format');
    }

    // Create project with client_id and service
    $stmt = $pdo->prepare("
        INSERT INTO projects (
            appointment_id,
            client_id, 
            service,
            start_date, 
            end_date, 
            notes, 
            quotation_file,
            contract_file,
            budget_file,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ongoing', NOW())
    ");

    $stmt->execute([
        $_POST['appointment_id'],
        $appointment['client_id'],
        $appointment['service'],
        $start_date,
        $end_date,
        $_POST['notes'],
        isset($_FILES['quotation_file']) ? $_FILES['quotation_file']['name'] : null,
        isset($_FILES['contract_file']) ? $_FILES['contract_file']['name'] : null,
        isset($_FILES['budget_file']) ? $_FILES['budget_file']['name'] : null
    ]);

    $project_id = $pdo->lastInsertId();

    // Handle file upload if exists
    if (isset($_FILES['quotation_file']) || isset($_FILES['contract_file']) || isset($_FILES['budget_file'])) {
        $upload_dir = '../../uploads/';
        
        // Create directories if they don't exist
        $dirs = ['quotations', 'contracts', 'budgets'];
        foreach ($dirs as $dir) {
            if (!file_exists($upload_dir . $dir)) {
                mkdir($upload_dir . $dir, 0777, true);
            }
        }

        // Handle quotation file
        if (isset($_FILES['quotation_file']) && $_FILES['quotation_file']['error'] == 0) {
            if (!isPDF($_FILES['quotation_file'])) {
                throw new Exception('Quotation file must be a PDF document');
            }
            move_uploaded_file(
                $_FILES['quotation_file']['tmp_name'], 
                $upload_dir . 'quotations/' . $_FILES['quotation_file']['name']
            );
        }

        // Handle contract file
        if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] == 0) {
            if (!isPDF($_FILES['contract_file'])) {
                throw new Exception('Contract file must be a PDF document');
            }
            move_uploaded_file(
                $_FILES['contract_file']['tmp_name'], 
                $upload_dir . 'contracts/' . $_FILES['contract_file']['name']
            );
        }

        // Handle budget file
        if (isset($_FILES['budget_file']) && $_FILES['budget_file']['error'] == 0) {
            if (!isPDF($_FILES['budget_file'])) {
                throw new Exception('Budget file must be a PDF document');
            }
            move_uploaded_file(
                $_FILES['budget_file']['tmp_name'], 
                $upload_dir . 'budgets/' . $_FILES['budget_file']['name']
            );
        }
    }

    // Assign personnel to project
    if (isset($_POST['personnel'])) {
        $personnel = json_decode($_POST['personnel'], true);
        if ($personnel === null) {
            throw new Exception('Invalid personnel data format');
        }

        $assign_stmt = $pdo->prepare("
            INSERT INTO project_assignees (project_id, user_id, assigned_date)
            VALUES (?, ?, NOW())
        ");

        foreach ($personnel as $person) {
            if (!isset($person['id'])) {
                continue;
            }
            $assign_stmt->execute([$project_id, $person['id']]);
            
            // Update active projects count
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET active_projects = active_projects + 1 
                WHERE user_id = ?
            ");
            $update_stmt->execute([$person['id']]);
        }
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Project created successfully',
        'project_id' => $project_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in create_project.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating project: ' . $e->getMessage()
    ]);
}
?> 