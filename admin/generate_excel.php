<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../dbconnect.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../admin_login.php');
    exit;
}

// Check if report type is specified
if (!isset($_POST['report_type'])) {
    $_SESSION['error_message'] = 'No report type specified';
    header('Location: generate_report.php');
    exit;
}

try {
    $report_type = $_POST['report_type'];
    
    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set default styles
    $sheet->getDefaultRowDimension()->setRowHeight(20);
    $sheet->getDefaultColumnDimension()->setWidth(15);
    
    // Header style array
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '007BFF'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];

    // Data style array
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    switch ($report_type) {
        case 'users':
            // Set title
            $sheet->setCellValue('A1', 'Users Report - Generated on ' . date('F d, Y'));
            $sheet->mergeCells('A1:E1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Set headers
            $headers = ['Name', 'Email', 'Phone', 'Registration Date', 'Status'];
            $sheet->fromArray($headers, NULL, 'A3');
            $sheet->getStyle('A3:E3')->applyFromArray($headerStyle);
            
            // Fetch data
            $query = "
                SELECT 
                    name, 
                    email, 
                    phone, 
                    date_created,
                    status
                FROM users 
                WHERE role = 'client'
                ORDER BY name ASC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add data
            $row = 4;
            foreach ($data as $user) {
                $sheet->setCellValue('A' . $row, $user['name']);
                $sheet->setCellValue('B' . $row, $user['email']);
                $sheet->setCellValue('C' . $row, $user['phone']);
                $sheet->setCellValue('D' . $row, date('M d, Y', strtotime($user['date_created'])));
                $sheet->setCellValue('E' . $row, ucfirst($user['status']));
                
                // Add conditional formatting for status
                if (strtolower($user['status']) === 'active') {
                    $sheet->getStyle('E' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('008000'));
                } else {
                    $sheet->getStyle('E' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0000'));
                }
                
                $row++;
            }
            
            // Apply styles to data
            $sheet->getStyle('A4:E' . ($row-1))->applyFromArray($dataStyle);
            
            $filename = 'users_report_' . date('Y-m-d') . '.xlsx';
            break;

        case 'appointments':
            // Set title
            $sheet->setCellValue('A1', 'Appointments Report - Generated on ' . date('F d, Y'));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Set headers
            $headers = ['Client Name', 'Email', 'Service', 'Date', 'Time', 'Status'];
            $sheet->fromArray($headers, NULL, 'A3');
            $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);
            
            // Fetch data
            $stmt = $pdo->prepare("
                SELECT 
                    a.service,
                    a.date,
                    a.time,
                    a.status,
                    u.name as client_name,
                    u.email as client_email
                FROM appointments a
                JOIN users u ON a.client_id = u.user_id
                ORDER BY a.date DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add data
            $row = 4;
            foreach ($data as $appointment) {
                $sheet->setCellValue('A' . $row, $appointment['client_name']);
                $sheet->setCellValue('B' . $row, $appointment['client_email']);
                $sheet->setCellValue('C' . $row, $appointment['service']);
                $sheet->setCellValue('D' . $row, date('M d, Y', strtotime($appointment['date'])));
                $sheet->setCellValue('E' . $row, date('h:i A', strtotime($appointment['time'])));
                $sheet->setCellValue('F' . $row, ucfirst($appointment['status']));
                $row++;
            }
            
            // Apply styles to data
            $sheet->getStyle('A4:F' . ($row-1))->applyFromArray($dataStyle);
            
            $filename = 'appointments_report_' . date('Y-m-d') . '.xlsx';
            break;

        case 'employees':
            // Set title
            $sheet->setCellValue('A1', 'Employees Report - Generated on ' . date('F d, Y'));
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Set headers
            $headers = ['Name', 'Email', 'Phone', 'Role', 'Join Date', 'Status'];
            $sheet->fromArray($headers, NULL, 'A3');
            $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);
            
            // Fetch data
            $query = "
                SELECT 
                    name, 
                    email, 
                    phone, 
                    role,
                    date_created,
                    status
                FROM users 
                WHERE role NOT IN ('client', 'admin')
                ORDER BY role, name ASC
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add data
            $row = 4;
            foreach ($data as $employee) {
                $sheet->setCellValue('A' . $row, $employee['name']);
                $sheet->setCellValue('B' . $row, $employee['email']);
                $sheet->setCellValue('C' . $row, $employee['phone']);
                $sheet->setCellValue('D' . $row, ucfirst($employee['role']));
                $sheet->setCellValue('E' . $row, date('M d, Y', strtotime($employee['date_created'])));
                $sheet->setCellValue('F' . $row, ucfirst($employee['status']));
                
                // Add conditional formatting for status
                if (strtolower($employee['status']) === 'active') {
                    $sheet->getStyle('F' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('008000'));
                } else {
                    $sheet->getStyle('F' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0000'));
                }
                
                $row++;
            }
            
            // Apply styles to data
            $sheet->getStyle('A4:F' . ($row-1))->applyFromArray($dataStyle);
            
            $filename = 'employees_report_' . date('Y-m-d') . '.xlsx';
            break;

        default:
            throw new Exception('Invalid report type');
    }

    // Auto-size columns
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Create temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'excel_');

    try {
        // Save to temporary file first
        $writer = new Xlsx($spreadsheet);
        $writer->save($temp_file);

        // Clear all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (file_exists($temp_file)) {
            // Set headers for download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($temp_file));
            header('Cache-Control: max-age=0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Read and output file
            readfile($temp_file);
            
            // Delete temporary file
            unlink($temp_file);
        } else {
            throw new Exception("Temporary file not found");
        }
    } catch (Exception $e) {
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        throw $e;
    }

    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error generating report: ' . $e->getMessage();
    header('Location: generate_report.php');
    exit;
} 