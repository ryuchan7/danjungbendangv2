<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set('Asia/Kuala_Lumpur'); // Adjusted to +08:00 timezone

// Handle messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Define available booking statuses
$booking_statuses = [
    'All' => 'All Statuses',
    'Confirmed' => 'Confirmed',
    'Pending Payment' => 'Pending Payment',
    'Pending Approval' => 'Pending Approval',
    'Cancelled' => 'Cancelled',
    'Rejected' => 'Rejected'
];

// Function to validate and sanitize report_month
function validateReportMonth($input) {
    // Check if input matches YYYY-MM format
    if (preg_match('/^\d{4}-\d{2}$/', $input)) {
        try {
            // Validate if it's a valid date
            $date = DateTime::createFromFormat('Y-m', $input);
            if ($date && $date->format('Y-m') === $input) {
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            }
        } catch (Exception $e) {
            // Invalid date, fallback to current month
            return date('Y-m');
        }
    }
    return date('Y-m'); // Fallback to current month if invalid
}

// Monthly report generation
$report_data = [];
$report_month = isset($_POST['report_month']) ? validateReportMonth($_POST['report_month']) : date('Y-m');
$selected_status = isset($_POST['booking_status']) && array_key_exists($_POST['booking_status'], $booking_statuses) 
    ? htmlspecialchars($_POST['booking_status'], ENT_QUOTES, 'UTF-8') 
    : 'All';

if (isset($_POST['generate_report']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        // Prepare SQL query with dynamic status filter
        $sql = "
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
        ";
        $params = ['report_month' => $report_month];

        // Add status filter if not 'All'
        if ($selected_status !== 'All') {
            $sql .= " AND b.booking_status = :booking_status";
            $params['booking_status'] = $selected_status;
        }

        $sql .= " ORDER BY b.checkIN";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dynamically update status to 'Completed' if checkOut is past
        $today = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
        foreach ($report_data as &$report) {
            $checkOutDate = new DateTime($report['checkOUT'], new DateTimeZone('Asia/Kuala_Lumpur'));
            if ($checkOutDate < $today) {
                $report['booking_status'] = 'Completed';
            }
        }
        unset($report); // Unset reference to avoid issues

    } catch (PDOException $e) {
        $error_message = 'Error generating report: ' . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Monthly Reports - Cozy Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 220px;
            background: #f8f9fa;
            padding: 20px;
            border-right: 1px solid #dee2e6;
        }
        .sidebar .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: #26a69a;
            margin-bottom: 30px;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: #6c757d;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #26a69a;
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            flex: 1;
            padding: 30px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header h2 {
            color: #333;
            font-weight: 600;
            margin: 0;
        }
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }
        .table-responsive {
            margin-bottom: 40px;
            max-height: 600px;
            overflow-y: auto;
        }
        .table th {
            background: #26a69a;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .status-pending-payment { background-color: #ffc107; color: #fff; }
        .status-pending-approval { background-color: #17a2b8; color: #fff; }
        .status-confirmed { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .status-rejected { background-color: #6c757d; color: #fff; }
        .status-completed { background-color: #17a2b8; color: #fff; } /* Added Completed status color */
        .status-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
        }
        .pdf-report-container {
            width: 100%;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">D'Anjung Bendang Homestay</div>
            <a href="admin_dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="user_management.php" class="nav-link">
                <i class="fas fa-users"></i> User Management
            </a>
            <a href="bookings.php" class="nav-link">
                <i class="fas fa-book"></i> All Bookings
            </a>
            <a href="reports.php" class="nav-link active">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <a href="audit_logs.php" class="nav-link">
                <i class="fas fa-history"></i> Audit Logs
            </a>
            <a href="feedback_management.php" class="nav-link">
                <i class="fas fa-comment-dots"></i> Feedback Customer
            </a>
               <a href="worker_salary.php" class="nav-link">
                <i class="fas fa-money-bill-wave"></i> Worker Salary
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Monthly Reports</h2>
                <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="row mb-3 align-items-end">
                    <div class="col-md-4">
                        <label for="report_month" class="form-label">Select Month</label>
                        <input type="month" class="form-control" id="report_month" name="report_month" value="<?php echo htmlspecialchars($report_month); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="booking_status" class="form-label">Booking Status</label>
                        <select class="form-control" id="booking_status" name="booking_status" required>
                            <?php foreach ($booking_statuses as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selected_status === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="generate_report" class="btn btn-primary w-100"><i class="fas fa-file-alt"></i> Generate Report</button>
                    </div>
                </div>
            </form>
            <?php if (!empty($report_data)): ?>
                <h4 id="report-title">Booking Report for <?php echo date('F Y', strtotime($report_month . '-01')); ?> (<?php echo htmlspecialchars($booking_statuses[$selected_status]); ?>)</h4>
                <div class="pdf-report-container">
                    <div class="table-responsive" id="report-table">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Homestay</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th>Guests</th>
                                    <th>Amount Paid</th>
                                    <th>Status</th>
                                    <th>Customer Name</th>
                                    <th>Phone</th>
                                    <th>IC Number</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['bookingID']); ?></td>
                                        <td><?php echo htmlspecialchars($report['homestay_name']); ?></td>
                                        <td><?php echo htmlspecialchars((new DateTime($report['checkIN']))->format('d-m-Y')); ?></td>
                                        <td><?php echo htmlspecialchars((new DateTime($report['checkOUT']))->format('d-m-Y')); ?></td>
                                        <td><?php echo htmlspecialchars($report['number_of_guests']); ?></td>
                                        <td>$<?php echo number_format($report['amount_paid'], 2); ?></td>
                                        <td>
                                            <span class="status-circle status-<?php echo strtolower(str_replace(' ', '-', $report['booking_status'])); ?>">
                                                <?php echo htmlspecialchars($report['booking_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($report['customer_name'] ?? 'Guest'); ?></td>
                                        <td><?php echo htmlspecialchars($report['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($report['customer_ic_no'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <button onclick="generatePDF()" class="btn btn-primary"><i class="fas fa-download"></i> Download PDF Report</button>
            <?php elseif (isset($_POST['generate_report'])): ?>
                <div class="alert alert-info">No bookings found for the selected month and status.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include jsPDF and html2canvas libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        const { jsPDF } = window.jspdf;

        async function generatePDF() {
            const doc = new jsPDF({
                orientation: 'landscape', // Use landscape to fit more columns
                unit: 'mm',
                format: 'a4'
            });

            const reportTitle = document.getElementById('report-title').innerText;
            const tableElement = document.getElementById('report-table');

            // Add title to the PDF
            doc.setFontSize(16);
            doc.text(reportTitle, 14, 20);

            // Use html2canvas to capture the table as an image
            const canvas = await html2canvas(tableElement, {
                scale: 2, // Increase scale for better quality
                useCORS: true,
                windowWidth: tableElement.scrollWidth // Ensure the full table width is captured
            });

            const imgData = canvas.toDataURL('image/png');
            const imgProps = doc.getImageProperties(imgData);
            const pdfWidth = doc.internal.pageSize.getWidth() - 28; // Leave margins
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

            // Add the table image to the PDF
            let heightLeft = pdfHeight;
            let position = 30; // Start below the title

            doc.addImage(imgData, 'PNG', 14, position, pdfWidth, pdfHeight);

            // Add additional pages if the table height exceeds the page height
            heightLeft -= (doc.internal.pageSize.getHeight() - 40); // Account for margins
            while (heightLeft > 0) {
                position = heightLeft - pdfHeight;
                doc.addPage();
                doc.addImage(imgData, 'PNG', 14, position, pdfWidth, pdfHeight);
                heightLeft -= (doc.internal.pageSize.getHeight() - 40);
            }

            // Save the PDF
            const statusPart = '<?php echo $selected_status === 'All' ? 'all' : strtolower(str_replace(' ', '_', $selected_status)); ?>';
            const filename = 'booking_report_' + '<?php echo htmlspecialchars($report_month); ?>_' + statusPart + '.pdf';
            doc.save(filename);
        }
    </script>
</body>
</html>