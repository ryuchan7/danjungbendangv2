<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Session timeout (30 minutes)
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Generate and validate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid CSRF token. Please try again.';
    header('Location: audit_logs.php');
    exit;
}

date_default_timezone_set('Asia/Kuala_Lumpur'); // Adjusted to +08:00 timezone

// Handle messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Function to get customer name by booking ID
function getCustomerNameByBookingId($pdo, $booking_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(u.full_name, b.full_name) AS customer_name
            FROM booking b
            LEFT JOIN users u ON b.userID = u.userID
            WHERE b.bookingID = :booking_id
        ");
        $stmt->execute(['booking_id' => $booking_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? ($result['customer_name'] ?? 'Unknown Customer') : 'Unknown Customer';
    } catch (PDOException $e) {
        error_log('Error fetching customer name: ' . $e->getMessage());
        return 'Error retrieving customer name';
    }
}

// Define available action filters based on database
$action_filters = [
    'All' => 'All Actions',
    'Approved Booking' => 'Approved Booking',
    'Cancel Booking' => 'Cancel Booking',
    'Delete Booking' => 'Delete Booking',
    'Add User' => 'Add User',
    'Edit User' => 'Edit User',
    'Delete User' => 'Delete User'
];

// Handle filter form
$selected_action = isset($_POST['action_filter']) && array_key_exists($_POST['action_filter'], $action_filters) 
    ? htmlspecialchars($_POST['action_filter'], ENT_QUOTES, 'UTF-8') 
    : 'All';

try {
    // Fetch audit logs with dynamic filter
    $sql = "
        SELECT al.log_id, al.action, al.details, al.created_at, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.userID
    ";
    $params = [];
    
    if ($selected_action !== 'All' && array_key_exists($selected_action, $action_filters)) {
        $sql .= " WHERE al.action = :action";
        $params['action'] = $selected_action;
    }

    $sql .= " ORDER BY al.created_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Audit Logs - D'Anjung Bendang Homestay</title>
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
            padding: 20px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
        .section {
            margin-bottom: 30px;
        }
        .section h3 {
            color: #26a69a;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .table-responsive {
            margin-bottom: 30px;
            max-height: 500px;
            overflow-y: auto;
            position: relative;
        }
        .table th {
            background: #26a69a;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 11px;
            padding: 6px;
        }
        .table td {
            font-size: 11px;
            padding: 6px;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
                font-size: 9px;
                line-height: 1.1;
            }
            .sidebar, .header, .alert, form, button {
                display: none;
            }
            .main-content {
                padding: 10px;
                margin: 0;
            }
            .section {
                margin: 0;
            }
            .table-responsive {
                max-height: none;
                overflow: visible;
            }
            .table th, .table td {
                font-size: 9px;
                padding: 3px;
                border: 0.5px solid #dee2e6;
            }
            @page {
                margin: 0.5cm;
                size: A4;
            }
        }
        /* Temporary styles for PDF capture */
        .pdf-capture {
            max-height: none !important;
            overflow: visible !important;
            position: static !important;
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
            <a href="reports.php" class="nav-link">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <a href="audit_logs.php" class="nav-link active">
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
                <h2>Audit Logs</h2>
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

            <!-- Filter Form -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="row mb-3 align-items-end">
                    <div class="col-md-4">
                        <label for="action_filter" class="form-label">Filter by Action</label>
                        <select class="form-control" id="action_filter" name="action_filter" required>
                            <?php foreach ($action_filters as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selected_action === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Apply Filter</button>
                    </div>
                </div>
            </form>

            <!-- Audit Logs Section -->
            <div class="section">
                <h3>Recent Admin Actions (<?php echo htmlspecialchars($action_filters[$selected_action]); ?>)</h3>
                <div class="table-responsive" id="audit-table">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($audit_logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No audit logs found for the selected action.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td>
                                            <?php
                                            $details = json_decode($log['details'], true);
                                            if (is_array($details)) {
                                                if (in_array($log['action'], ['Approved Booking', 'Cancel Booking', 'Delete Booking'])) {
                                                    $booking_id = isset($details['booking_id']) ? $details['booking_id'] : 'Unknown';
                                                    $customer_name = isset($details['customer_name']) ? $details['customer_name'] : getCustomerNameByBookingId($pdo, $booking_id);
                                                    echo htmlspecialchars("Booking ID: {$booking_id}, Customer: {$customer_name}");
                                                } elseif (in_array($log['action'], ['Add User', 'Edit User', 'Delete User'])) {
                                                    $user_id = isset($details['user_id']) ? $details['user_id'] : 'Unknown';
                                                    $username = isset($details['username']) ? $details['username'] : 'Unknown Username';
                                                    echo htmlspecialchars("User ID: {$user_id}, Username: {$username}");
                                                } else {
                                                    echo htmlspecialchars($log['details']);
                                                }
                                            } else {
                                                echo htmlspecialchars($log['details']);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars((new DateTime($log['created_at']))->format('d-m-Y H:i:s')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($audit_logs)): ?>
                    <button onclick="generatePDF()" class="btn btn-primary"><i class="fas fa-download"></i> Download PDF</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include jsPDF and html2canvas libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        const { jsPDF } = window.jspdf;

        async function generatePDF() {
            // Temporarily adjust table-responsive for full capture
            const tableResponsive = document.querySelector('.table-responsive');
            const originalStyles = {
                maxHeight: tableResponsive.style.maxHeight,
                overflowY: tableResponsive.style.overflowY,
                position: tableResponsive.style.position
            };
            tableResponsive.classList.add('pdf-capture');
            tableResponsive.style.maxHeight = 'none';
            tableResponsive.style.overflowY = 'visible';
            tableResponsive.style.position = 'static';

            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            const reportTitle = 'Audit Logs - <?php echo htmlspecialchars($action_filters[$selected_action]); ?>';
            const tableElement = document.getElementById('audit-table');

            // Add title to the PDF
            doc.setFontSize(14);
            doc.text(reportTitle, 10, 10);

            // Use html2canvas to capture the full table
            const canvas = await html2canvas(tableElement, {
                scale: 2, // Higher scale for better quality
                useCORS: true,
                windowWidth: document.documentElement.scrollWidth,
                scrollX: 0,
                scrollY: -window.scrollY // Adjust for current scroll position
            });

            const imgData = canvas.toDataURL('image/png');
            const imgProps = doc.getImageProperties(imgData);
            const pdfWidth = doc.internal.pageSize.getWidth() - 10;
            let pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            let position = 15;

            // Add image to PDF, split across pages if necessary
            while (pdfHeight > 0) {
                doc.addImage(imgData, 'PNG', 5, position, pdfWidth, pdfHeight > 280 ? 280 : pdfHeight); // 280mm is approx. A4 height minus margins
                if (pdfHeight > 280) {
                    doc.addPage();
                    position = 10;
                    pdfHeight -= 280;
                } else {
                    pdfHeight = 0;
                }
            }

            // Restore original styles
            tableResponsive.classList.remove('pdf-capture');
            Object.assign(tableResponsive.style, originalStyles);

            // Save PDF
            const filename = 'audit_logs_' + '<?php echo htmlspecialchars($selected_action === 'All' ? 'all' : strtolower(str_replace(' ', '_', $selected_action))); ?>' + '_' + new Date().toISOString().slice(0, 10) + '.pdf';
            doc.save(filename);
        }
    </script>
</body>
</html>