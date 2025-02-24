<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Clean any existing output buffers and turn off output buffering
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
require_once '../dbconnect.php';
require '../vendor/autoload.php';

use \TCPDF as TCPDF;

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

try {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('EBTC PMS');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Statistics Report');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(15, 15, 15);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 11);

    // Get statistics data
    $stats = getStatistics($pdo);

    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'System Statistics Report', 0, true, 'C');
    $pdf->Ln(10);

    // Summary Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Summary', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    // Create summary table
    $summary_tbl = '<table border="1" cellpadding="5" style="width: 100%;">
        <tr style="background-color: #f5f5f5;">
            <th width="60%">Metric</th>
            <th width="40%">Total</th>
        </tr>
        <tr>
            <td>Total Clients</td>
            <td>' . $stats['total_clients'] . '</td>
        </tr>
        <tr>
            <td>Total Employees</td>
            <td>' . array_sum(array_column($stats['employees_by_role'], 'total')) . '</td>
        </tr>
        <tr>
            <td>Total Appointments</td>
            <td>' . array_sum(array_column($stats['appointments_by_status'], 'total')) . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($summary_tbl, true, false, false, false, '');
    $pdf->Ln(10);

    // Employees by Role Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Employees by Role', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $employees_tbl = '<table border="1" cellpadding="5" style="width: 100%;">
        <tr style="background-color: #f5f5f5;">
            <th width="60%">Role</th>
            <th width="40%">Total</th>
        </tr>';
    foreach ($stats['employees_by_role'] as $role) {
        $employees_tbl .= '<tr>
            <td>' . ucfirst($role['role']) . '</td>
            <td>' . $role['total'] . '</td>
        </tr>';
    }
    $employees_tbl .= '</table>';
    
    $pdf->writeHTML($employees_tbl, true, false, false, false, '');
    $pdf->Ln(10);

    // Appointments by Status Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Appointments by Status', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $appointments_tbl = '<table border="1" cellpadding="5" style="width: 100%;">
        <tr style="background-color: #f5f5f5;">
            <th width="60%">Status</th>
            <th width="40%">Total</th>
        </tr>';
    foreach ($stats['appointments_by_status'] as $status) {
        $appointments_tbl .= '<tr>
            <td>' . ucfirst($status['status']) . '</td>
            <td>' . $status['total'] . '</td>
        </tr>';
    }
    $appointments_tbl .= '</table>';
    
    $pdf->writeHTML($appointments_tbl, true, false, false, false, '');
    $pdf->Ln(10);

    // Monthly Appointments Section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Monthly Appointments (Last 6 Months)', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 11);
    
    $monthly_tbl = '<table border="1" cellpadding="5" style="width: 100%;">
        <tr style="background-color: #f5f5f5;">
            <th width="60%">Month</th>
            <th width="40%">Total Appointments</th>
        </tr>';
    foreach ($stats['appointments_by_month'] as $month) {
        $monthly_tbl .= '<tr>
            <td>' . date('F Y', strtotime($month['month'] . '-01')) . '</td>
            <td>' . $month['total'] . '</td>
        </tr>';
    }
    $monthly_tbl .= '</table>';
    
    $pdf->writeHTML($monthly_tbl, true, false, false, false, '');

    // Before outputting PDF, ensure no output has been sent
    if (headers_sent($filename, $linenum)) {
        throw new Exception("Headers already sent in $filename on line $linenum");
    }

    // Clear any remaining output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Output the PDF with error handling
    try {
        $pdf->Output('statistics_report_' . date('Y-m-d') . '.pdf', 'D');
    } catch (Exception $e) {
        throw new Exception('PDF Generation failed: ' . $e->getMessage());
    }
    exit;

} catch (Exception $e) {
    // Log error for debugging
    error_log('PDF Generation Error: ' . $e->getMessage());
    
    // Clear any output that might have been sent
    if (!headers_sent()) {
        header('Location: generate_report.php');
        $_SESSION['error_message'] = 'Error generating PDF report: ' . $e->getMessage();
    } else {
        echo '<script>
            alert("Error generating PDF report: ' . addslashes($e->getMessage()) . '");
            window.location.href = "generate_report.php";
        </script>';
    }
    exit;
}

// Function to get statistics
function getStatistics($pdo) {
    $stats = [];
    
    // Get total users (clients)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
    $stmt->execute();
    $stats['total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total employees by role
    $stmt = $pdo->prepare("
        SELECT role, COUNT(*) as total 
        FROM users 
        WHERE role NOT IN ('client', 'admin')
        GROUP BY role
    ");
    $stmt->execute();
    $stats['employees_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total appointments by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as total 
        FROM appointments 
        GROUP BY status
    ");
    $stmt->execute();
    $stats['appointments_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get appointments for the last 6 months
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as total
        FROM appointments
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $stats['appointments_by_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $stats;
} 