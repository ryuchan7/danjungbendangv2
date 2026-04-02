<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid CSRF token.';
    header('Location: reports.php');
    exit;
}

// Require TCPDF library (adjust path as needed)
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';

// Validate report month
$report_month = isset($_POST['report_month']) ? filter_var($_POST['report_month'], FILTER_SANITIZE_STRING) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $report_month)) {
    $_SESSION['error_message'] = 'Invalid report month format.';
    header('Location: reports.php');
    exit;
}

// Fetch report data
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.bookingID, 
            h.name AS homestay_name, 
            b.checkIN, 
            b.checkOUT, 
            b.number_of_guests, 
            b.amount_paid, 
            b.booking_status, 
            COALESCE(u.full_name, b.full_name) AS customer_name,
            COALESCE(u.phone, b.phone) AS customer_phone,
            COALESCE(u.ic_no, b.ic_no) AS customer_ic_no
        FROM booking b
        INNER JOIN homestaylist h ON b.hslistID = h.hslistID
        LEFT JOIN users u ON b.userID = u.userID
        WHERE DATE_FORMAT(b.checkIN, '%Y-%m') = :report_month
        ORDER BY b.checkIN
    ");
    $stmt->execute(['report_month' => $report_month]);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error fetching report data: ' . htmlspecialchars($e->getMessage());
    header('Location: reports.php');
    exit;
}

// If no data, redirect with message
if (empty($report_data)) {
    $_SESSION['error_message'] = 'No bookings found for the selected month to generate a PDF.';
    header('Location: reports.php');
    exit;
}

// Create new TCPDF instance
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Cozy Homestay');
$pdf->SetTitle('Monthly Booking Report');
$pdf->SetSubject('Booking Report for ' . date('F Y', strtotime($report_month . '-01')));
$pdf->SetKeywords('Booking, Report, Cozy Homestay');

// Set default header data
$pdf->SetHeaderData('', 0, 'Cozy Homestay', 'Booking Report for ' . date('F Y', strtotime($report_month . '-01')));

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Define table headers
$header = [
    'Booking ID',
    'Homestay',
    'Check-In',
    'Check-Out',
    'Guests',
    'Amount Paid',
    'Status',
    'Customer Name',
    'Phone Number',
    'IC Number'
];

// Define column widths (adjust as needed to fit content)
$widths = [15, 25, 20, 20, 15, 20, 20, 25, 25, 25];

// Create table header
$html = '<table border="1" cellpadding="4" style="border-collapse: collapse;">';
$html .= '<thead><tr style="background-color: #26a69a; color: #ffffff;">';
foreach ($header as $i => $col) {
    $html .= '<th width="' . $widths[$i] . 'mm">' . htmlspecialchars($col) . '</th>';
}
$html .= '</tr></thead><tbody>';

// Add table rows
foreach ($report_data as $row) {
    // Determine status color
    $status_class = strtolower(str_replace(' ', '-', $row['booking_status']));
    $status_color = '';
    switch ($status_class) {
        case 'pending-payment':
        case 'pending-approval':
            $status_color = '#ffc107'; // Yellow
            break;
        case 'confirmed':
            $status_color = '#28a745'; // Green
            break;
        case 'cancelled':
            $status_color = '#dc3545'; // Red
            break;
        case 'rejected':
            $status_color = '#6c757d'; // Gray
            break;
    }

    $html .= '<tr>';
    $html .= '<td width="' . $widths[0] . 'mm">' . htmlspecialchars($row['bookingID']) . '</td>';
    $html .= '<td width="' . $widths[1] . 'mm">' . htmlspecialchars($row['homestay_name']) . '</td>';
    $html .= '<td width="' . $widths[2] . 'mm">' . htmlspecialchars($row['checkIN']) . '</td>';
    $html .= '<td width="' . $widths[3] . 'mm">' . htmlspecialchars($row['checkOUTлика: $html .= '<td width="' . $widths[4] . 'mm">' . htmlspecialchars($row['number_of_guests']) . '</td>';
    $html .= '<td width="' . $widths[5] . 'mm">$' . number_format($row['amount_paid'], 2) . '</td>';
    $html .= '<td width="' . $widths[6] . 'mm"><span style="color: ' . $status_color . ';">' . htmlspecialchars($row['booking_status']) . '</span></td>';
    $html .= '<td width="' . $widths[7] . 'mm">' . htmlspecialchars($row['customer_name'] ?? 'Guest') . '</td>';
    $html .= '<td width="' . $widths[8] . 'mm">' . htmlspecialchars($row['customer_phone'] ?? 'N/A') . '</td>';
    $html .= '<td width="' . $widths[9] . 'mm">' . htmlspecialchars($row['customer_ic_no'] ?? 'N/A') . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

// Write HTML to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Output the PDF
$pdf->Output('booking_report_' . $report_month . '.pdf', 'D');
?>