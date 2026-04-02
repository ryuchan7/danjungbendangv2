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
date_default_timezone_set('Asia/Kuala_Lumpur');
// Handle messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$today = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
$todayStr = $today->format('Y-m-d');

try {
    // Fetch bookings with proper sorting
    $stmt = $pdo->prepare("
        SELECT
            b.bookingID,
            b.userID,
            h.name AS homestay_name,
            b.book_date,
            b.checkIN,
            b.checkOUT,
            b.number_of_guests,
            b.payment_status,
            b.payment_method,
            b.amount_paid,
            b.booking_status,
            b.payment_proof,
            COALESCE(u.full_name, b.full_name) AS customer_name,
            COALESCE(u.email, b.email) AS customer_email,
            COALESCE(u.phone, b.phone) AS customer_phone,
            COALESCE(u.ic_no, b.ic_no) AS customer_ic_no,
            b.created_at
        FROM booking b
        INNER JOIN homestaylist h ON b.hslistID = h.hslistID
        LEFT JOIN users u ON b.userID = u.userID
        ORDER BY 
            CASE 
                WHEN b.checkOUT < :today THEN 1 
                ELSE 0 
            END,
            CASE 
                WHEN b.checkOUT >= :today THEN b.checkIN 
            END ASC,
            CASE 
                WHEN b.checkOUT < :today THEN b.checkOUT 
            END DESC
    ");
    $stmt->bindParam(':today', $todayStr);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch calendar bookings (only for specific homestay ID=1, unchanged)
    $stmt = $pdo->prepare("
        SELECT bookingID, checkIN, checkOUT, booking_status
        FROM booking
        WHERE hslistID = 1 AND booking_status NOT IN ('Cancelled', 'Rejected')
    ");
    $stmt->execute();
    $calendar_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch homestays for add booking form
    $stmt = $pdo->prepare("SELECT hslistID, name FROM homestaylist ORDER BY name");
    $stmt->execute();
    $homestays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate booked dates for calendar (unchanged)
    $booked_dates = [];
    foreach ($calendar_bookings as $booking) {
        $start = new DateTime($booking['checkIN'], new DateTimeZone('Asia/Kuala_Lumpur'));
        $end = new DateTime($booking['checkOUT'], new DateTimeZone('Asia/Kuala_Lumpur'));
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        foreach ($period as $date) {
            $booked_dates[] = $date->format('Y-m-d');
        }
    }
    $booked_dates = array_unique($booked_dates);
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
    <title>All Bookings - Cozy Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
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
            width: 250px;
            background: #fff;
            padding: 20px;
            border-right: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }
        .sidebar .logo {
            font-size: 1.8rem;
            font-weight: 600;
            color: #26a69a;
            margin-bottom: 30px;
            text-align: center;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
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
            margin-right: 12px;
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header h2 {
            color: #26a69a;
            font-weight: 600;
            margin: 0;
        }
        .btn-logout, .btn-add-booking {
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            color: #fff;
            font-weight: 500;
        }
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }
        .btn-add-booking {
            background: linear-gradient(135deg, #26a69a, #00695c);
        }
        .btn-add-booking:hover {
            background: linear-gradient(135deg, #00695c, #26a69a);
        }
        .section {
            margin-bottom: 40px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .section h3 {
            color: #26a69a;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-bottom: 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .table th {
            background: #26a69a;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #00695c;
        }
        .table td, .table th {
            padding: 12px;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .status-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
        }
        .status-pending-payment { background-color: #ffc107; color: #fff; }
        .status-pending-approval { background-color: #ffc107; color: #fff; }
        .status-confirmed { background-color: #28a745; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
        .status-rejected { background-color: #6c757d; color: #fff; }
        .status-completed { background-color: #17a2b8; color: #fff; }
        .btn-approve {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: #fff;
            border: none;
        }
        .btn-approve:hover {
            background: linear-gradient(135deg, #1e7e34, #28a745);
        }
        .btn-cancel {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: none;
        }
        .btn-cancel:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }
        .btn-delete {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: #fff;
            border: none;
        }
        .btn-delete:hover {
            background: linear-gradient(135deg, #495057, #6c757d);
        }
        .btn-details {
            background: linear-gradient(135deg, #17a2b8, #117a8b);
            color: #fff;
            border: none;
        }
        .btn-details:hover {
            background: linear-gradient(135deg, #117a8b, #17a2b8);
        }
        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #fff;
            border: none;
        }
        .btn-edit:hover {
            background: linear-gradient(135deg, #e0a800, #ffc107);
        }
        .btn-print {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            border: none;
        }
        .btn-print:hover {
            background: linear-gradient(135deg, #0056b3, #007bff);
        }
        .status-filter {
            max-width: 200px;
        }
        .fc-daygrid-day.booked {
            background: #dc3545 !important;
            color: #fff !important;
        }
        .fc-daygrid-day.past-booking {
            background: #d1ecf1 !important;
            color: #0c5460 !important;
        }
        .fc .fc-button {
            background: linear-gradient(135deg, #26a69a, #00695c);
            border: none;
            border-radius: 20px;
        }
        .calendar-container {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: #fff;
        }
        .calendar-legend {
            margin-top: 10px;
            display: flex;
            gap: 15px;
        }
        .calendar-legend span {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        .calendar-legend .booked, .calendar-legend .available, .calendar-legend .completed {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }
        .calendar-legend .booked { background: #dc3545; }
        .calendar-legend .available { background: #fff; border: 1px solid #e0e0e0; }
        .calendar-legend .completed { background: #17a2b8; }
        .modal-body .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .modal-body .detail-item:last-child {
            border-bottom: none;
        }
        .modal-body .detail-label {
            font-weight: 600;
            color: #26a69a;
            flex: 1;
        }
        .modal-body .detail-value {
            flex: 2;
            text-align: left;
        }
        .modal-body .detail-value.status-pending-payment,
        .modal-body .detail-value.status-pending-approval,
        .modal-body .detail-value.status-confirmed,
        .modal-body .detail-value.status-cancelled,
        .modal-body .detail-value.status-rejected,
        .modal-body .detail-value.status-completed {
            font-weight: 600;
        }
        .payment-proof-img {
            max-width: 200px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #e0e0e0;
            transition: transform 0.3s ease;
        }
        .payment-proof-img:hover {
            transform: scale(1.05);
        }
        .edit-form .form-label {
            color: #26a69a;
            font-weight: 600;
        }
        .edit-form .form-control, .edit-form .form-select {
            border-radius: 8px;
        }
        .past-booking {
            background-color: #d1ecf1 !important;
            border-left: 4px solid #17a2b8 !important;
            color: #0c5460 !important;
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
            <a href="bookings.php" class="nav-link active">
                <i class="fas fa-book"></i> All Bookings
            </a>
            <a href="reports.php" class="nav-link">
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
                <h2>All Bookings</h2>
                <div>
                    <button class="btn btn-add-booking" data-bs-toggle="modal" data-bs-target="#addBookingModal"><i class="fas fa-plus me-2"></i>Add New Booking</button>
                    <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
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

            <!-- Bookings Section -->
            <div class="section">
                <h3>Bookings</h3>
                <div class="search-container d-flex gap-3 mb-3 align-items-center">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search bookings...">
                    <select id="statusFilter" class="form-control status-filter">
                        <option value="">All Statuses</option>
                        <option value="Pending Payment">Pending Payment</option>
                        <option value="Pending Approval">Pending Approval</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Completed">Completed</option>
                    </select>
                    <span>Total Bookings: <span id="totalBookings"><?php echo count($bookings); ?></span></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="bookingsTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Booking ID</th>
                                <th>Homestay</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Guests</th>
                                <th>Amount Paid</th>
                                <th>Booking Status</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingsTableBody">
                            <?php
                            $today = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
                            foreach ($bookings as $index => $booking):
                                $checkOutDate = new DateTime($booking['checkOUT'], new DateTimeZone('Asia/Kuala_Lumpur'));
                                $effectiveStatus = ($checkOutDate < $today) ? 'Completed' : $booking['booking_status'];
                            ?>
                                <tr class="booking-row <?php echo ($checkOutDate < $today) ? 'past-booking' : ''; ?>" data-status="<?php echo htmlspecialchars($effectiveStatus); ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($booking['bookingID']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['homestay_name']); ?></td>
                                    <td><?php echo htmlspecialchars((new DateTime($booking['checkIN']))->format('d-m-Y')); ?></td>
                                    <td><?php echo htmlspecialchars((new DateTime($booking['checkOUT']))->format('d-m-Y')); ?></td>
                                    <td><?php echo htmlspecialchars($booking['number_of_guests']); ?></td>
                                    <td>RM<?php echo number_format($booking['amount_paid'], 2); ?></td>
                                    <td>
                                        <span class="status-circle status-<?php echo strtolower(str_replace(' ', '-', $effectiveStatus)); ?>">
                                            <?php echo htmlspecialchars($effectiveStatus); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></td>
                                    <td><?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (in_array($booking['booking_status'], ['Pending Payment', 'Pending Approval']) && !($checkOutDate < $today)): ?>
                                            <form action="approve_booking.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                                <button type="submit" class="btn btn-approve"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($booking['booking_status'], ['Pending Payment', 'Pending Approval', 'Confirmed']) && !($checkOutDate < $today)): ?>
                                            <form action="cancel_booking.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                                <button type="submit" class="btn btn-cancel"><i class="fas fa-times"></i> Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="delete_booking.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                            <button type="submit" class="btn btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                        <button class="btn btn-details" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $booking['bookingID']; ?>">
                                            <i class="fas fa-info-circle"></i> Details
                                        </button>
                                        <button class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $booking['bookingID']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container" id="paginationContainer"></div>
            </div>

            <!-- Calendar Section -->
            <div class="section">
                <h3>Booking Calendar</h3>
                <div class="calendar-container">
                    <div id="calendar"></div>
                    <div class="calendar-legend">
                        <span><span class="booked"></span> Booked</span>
                        <span><span class="completed"></span> Completed</span>
                        <span><span class="available"></span> Available</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Booking Modal -->
    <div class="modal fade" id="addBookingModal" tabindex="-1" aria-labelledby="addBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBookingModalLabel">Add New Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="add_booking.php" method="POST" enctype="multipart/form-data" class="edit-form">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="homestay" class="form-label"><i class="fas fa-home me-2"></i>Homestay</label>
                                    <select class="form-select" id="homestay" name="hslistID" required>
                                        <option value="">Select Homestay</option>
                                        <?php foreach ($homestays as $homestay): ?>
                                            <option value="<?php echo htmlspecialchars($homestay['hslistID']); ?>">
                                                <?php echo htmlspecialchars($homestay['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="book_date" class="form-label"><i class="fas fa-calendar-alt me-2"></i>Book Date</label>
                                    <input type="date" class="form-control" id="book_date" name="book_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="checkIN" class="form-label"><i class="fas fa-sign-in-alt me-2"></i>Check-In</label>
                                    <input type="date" class="form-control" id="checkIN" name="checkIN" required>
                                </div>
                                <div class="mb-3">
                                    <label for="checkOUT" class="form-label"><i class="fas fa-sign-out-alt me-2"></i>Check-Out</label>
                                    <input type="date" class="form-control" id="checkOUT" name="checkOUT" required>
                                </div>
                                <div class="mb-3">
                                    <label for="number_of_guests" class="form-label"><i class="fas fa-users me-2"></i>Number of Guests</label>
                                    <input type="number" class="form-control" id="number_of_guests" name="number_of_guests" min="1" required>
                                </div>
                                <div class="mb-3">
                                    <label for="amount_paid" class="form-label"><i class="fas fa-dollar-sign me-2"></i>Amount Paid (RM)</label>
                                    <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="booking_status" class="form-label"><i class="fas fa-info-circle me-2"></i>Booking Status</label>
                                    <select class="form-select" id="booking_status" name="booking_status" required>
                                        <option value="Pending Payment">Pending Payment</option>
                                        <option value="Pending Approval">Pending Approval</option>
                                        <option value="Confirmed" selected>Confirmed</option>
                                        <option value="Cancelled">Cancelled</option>
                                        <option value="Rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_status" class="form-label"><i class="fas fa-credit-card me-2"></i>Payment Status</label>
                                    <input type="text" class="form-control" id="payment_status" name="payment_status" value="Completed" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label"><i class="fas fa-wallet me-2"></i>Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="CASH">Cash</option>
                                        <option value="ONLINE BANKING">Online Banking</option>
                                        <option value="E_WALLET">E-Wallet</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="full_name" class="form-label"><i class="fas fa-user-tag me-2"></i>Customer Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label"><i class="fas fa-phone me-2"></i>Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                                <div class="mb-3">
                                    <label for="ic_no" class="form-label"><i class="fas fa-id-card me-2"></i>IC Number</label>
                                    <input type="text" class="form-control" id="ic_no" name="ic_no">
                                </div>
                                <div class="mb-3">
                                    <label for="payment_proof" class="form-label"><i class="fas fa-file-image me-2"></i>Payment Proof</label>
                                    <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Add Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking Details Modals -->
    <?php foreach ($bookings as $booking): ?>
        <?php
            $checkOutDate = new DateTime($booking['checkOUT'], new DateTimeZone('Asia/Kuala_Lumpur'));
            $effectiveStatus = ($checkOutDate < $today) ? 'Completed' : $booking['booking_status'];
        ?>
        <div class="modal fade" id="detailsModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $booking['bookingID']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="detailsModalLabel<?php echo $booking['bookingID']; ?>">Booking Details #<?php echo $booking['bookingID']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user me-2"></i>User ID:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['userID'] ?? 'Guest'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-calendar-alt me-2"></i>Book Date:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((new DateTime($booking['book_date']))->format('d-m-Y')); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-sign-in-alt me-2"></i>Check-In:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((new DateTime($booking['checkIN']))->format('d-m-Y')); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-sign-out-alt me-2"></i>Check-Out:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((new DateTime($booking['checkOUT']))->format('d-m-Y')); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-users me-2"></i>Guests:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['number_of_guests']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-dollar-sign me-2"></i>Amount Paid:</span>
                                    <span class="detail-value">RM<?php echo number_format($booking['amount_paid'], 2); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-info-circle me-2"></i>Booking Status:</span>
                                    <span class="detail-value status-<?php echo strtolower(str_replace(' ', '-', $effectiveStatus)); ?>">
                                        <?php echo htmlspecialchars($effectiveStatus); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-credit-card me-2"></i>Payment Status:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['payment_status']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-wallet me-2"></i>Payment Method:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['payment_method']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-clock me-2"></i>Created At:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((new DateTime($booking['created_at']))->format('d-m-Y H:i:s')); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user-tag me-2"></i>Customer Name:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-envelope me-2"></i>Email:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['customer_email'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-phone me-2"></i>Phone:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-id-card me-2"></i>IC Number:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['customer_ic_no'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-file-image me-2"></i>Payment Proof:</span>
                                    <span class="detail-value">
                                        <?php if ($booking['payment_proof']): ?>
                                            <?php $proofs = explode(',', $booking['payment_proof']); ?>
                                            <?php foreach ($proofs as $index => $proof): ?>
                                                <?php $image_path = str_replace('Uploads/', 'Uploads/', $proof); ?>
                                                <a href="/homestay/<?php echo htmlspecialchars($image_path); ?>" target="_blank">
                                                    <img src="/homestay/<?php echo htmlspecialchars($image_path); ?>" alt="Payment Proof <?php echo $index + 1; ?>" class="payment-proof-img">
                                                </a>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
                        <button class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $booking['bookingID']; ?>" data-bs-dismiss="modal">
                            <i class="fas fa-edit me-2"></i>Edit Booking
                        </button>
                        <a href="print_receipt.php?booking_id=<?php echo $booking['bookingID']; ?>" target="_blank" class="btn btn-print"><i class="fas fa-print me-2"></i>Print Receipt</a>
                        <?php if (in_array($booking['booking_status'], ['Pending Payment', 'Pending Approval', 'Confirmed']) && !($checkOutDate < $today)): ?>
                            <form action="cancel_booking.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                <button type="submit" class="btn btn-cancel"><i class="fas fa-times me-2"></i>Cancel Booking</button>
                            </form>
                        <?php endif; ?>
                        <form action="delete_booking.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                            <button type="submit" class="btn btn-delete"><i class="fas fa-trash me-2"></i>Delete Booking</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Booking Modal -->
        <div class="modal fade" id="editModal<?php echo $booking['bookingID']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $booking['bookingID']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?php echo $booking['bookingID']; ?>">Edit Booking #<?php echo $booking['bookingID']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="edit_booking.php" method="POST" enctype="multipart/form-data" class="edit-form">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="book_date_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-calendar-alt me-2"></i>Book Date</label>
                                        <input type="date" class="form-control" id="book_date_<?php echo $booking['bookingID']; ?>" name="book_date" value="<?php echo htmlspecialchars($booking['book_date']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="checkIN_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-sign-in-alt me-2"></i>Check-In</label>
                                        <input type="date" class="form-control" id="checkIN_<?php echo $booking['bookingID']; ?>" name="checkIN" value="<?php echo htmlspecialchars($booking['checkIN']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="checkOUT_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-sign-out-alt me-2"></i>Check-Out</label>
                                        <input type="date" class="form-control" id="checkOUT_<?php echo $booking['bookingID']; ?>" name="checkOUT" value="<?php echo htmlspecialchars($booking['checkOUT']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="number_of_guests_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-users me-2"></i>Number of Guests</label>
                                        <input type="number" class="form-control" id="number_of_guests_<?php echo $booking['bookingID']; ?>" name="number_of_guests" value="<?php echo htmlspecialchars($booking['number_of_guests']); ?>" min="1" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="amount_paid_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-dollar-sign me-2"></i>Amount Paid</label>
                                        <input type="number" step="0.01" class="form-control" id="amount_paid_<?php echo $booking['bookingID']; ?>" name="amount_paid" value="<?php echo htmlspecialchars($booking['amount_paid']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="booking_status_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-info-circle me-2"></i>Booking Status</label>
                                        <select class="form-select" id="booking_status_<?php echo $booking['bookingID']; ?>" name="booking_status" required>
                                            <option value="Pending Payment" <?php echo $booking['booking_status'] === 'Pending Payment' ? 'selected' : ''; ?>>Pending Payment</option>
                                            <option value="Pending Approval" <?php echo $booking['booking_status'] === 'Pending Approval' ? 'selected' : ''; ?>>Pending Approval</option>
                                            <option value="Confirmed" <?php echo $booking['booking_status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="Cancelled" <?php echo $booking['booking_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="Rejected" <?php echo $booking['booking_status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="Completed" <?php echo ($checkOutDate < $today) ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="payment_status_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-credit-card me-2"></i>Payment Status</label>
                                        <input type="text" class="form-control" id="payment_status_<?php echo $booking['bookingID']; ?>" name="payment_status" value="<?php echo htmlspecialchars($booking['payment_status']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="payment_method_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-wallet me-2"></i>Payment Method</label>
                                        <select class="form-select" id="payment_method_<?php echo $booking['bookingID']; ?>" name="payment_method" required>
                                            <option value="CASH" <?php echo $booking['payment_method'] === 'CASH' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="ONLINE BANKING" <?php echo $booking['payment_method'] === 'ONLINE BANKING' ? 'selected' : ''; ?>>Online Banking</option>
                                            <option value="E_WALLET" <?php echo $booking['payment_method'] === 'E_WALLET' ? 'selected' : ''; ?>>E-Wallet</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer_name_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-user-tag me-2"></i>Customer Name</label>
                                        <input type="text" class="form-control" id="customer_name_<?php echo $booking['bookingID']; ?>" name="customer_name" value="<?php echo htmlspecialchars($booking['customer_name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer_email_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                                        <input type="email" class="form-control" id="customer_email_<?php echo $booking['bookingID']; ?>" name="customer_email" value="<?php echo htmlspecialchars($booking['customer_email'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer_phone_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-phone me-2"></i>Phone</label>
                                        <input type="text" class="form-control" id="customer_phone_<?php echo $booking['bookingID']; ?>" name="customer_phone" value="<?php echo htmlspecialchars($booking['customer_phone'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="customer_ic_no_<?php echo $booking['bookingID']; ?>" class="form-label"><i class="fas fa-id-card me-2"></i>IC Number</label>
                                        <input type="text" class="form-control" id="customer_ic_no_<?php echo $booking['bookingID']; ?>" name="customer_ic_no" value="<?php echo htmlspecialchars($booking['customer_ic_no'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-file-image me-2"></i>Payment Proof 1</label>
                                        <input type="file" class="form-control" id="payment_proof_1_<?php echo $booking['bookingID']; ?>" name="payment_proof_1" accept="image/*">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-file-image me-2"></i>Payment Proof 2</label>
                                        <input type="file" class="form-control" id="payment_proof_2_<?php echo $booking['bookingID']; ?>" name="payment_proof_2" accept="image/*">
                                    </div>
                                    <?php if ($booking['payment_proof']): ?>
                                        <div class="mt-2">
                                            <p>Current Payment Proofs:</p>
                                            <?php $proofs = explode(',', $booking['payment_proof']); ?>
                                            <?php foreach ($proofs as $index => $proof): ?>
                                                <div class="mb-2">
                                                    <a href="/homestay/<?php echo htmlspecialchars($proof); ?>" target="_blank">
                                                        <img src="/homestay/<?php echo htmlspecialchars($proof); ?>" alt="Current Payment Proof <?php echo $index + 1; ?>" class="payment-proof-img">
                                                    </a>
                                                    <div>
                                                        <input type="checkbox" name="delete_proof[]" value="<?php echo htmlspecialchars($proof); ?>" id="delete_proof_<?php echo $booking['bookingID']; ?>_<?php echo $index; ?>">
                                                        <label for="delete_proof_<?php echo $booking['bookingID']; ?>_<?php echo $index; ?>">Delete this proof</label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search and Filter for Bookings
            const bookingsTableBody = document.getElementById('bookingsTableBody');
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const paginationContainer = document.getElementById('paginationContainer');
            const totalBookingsSpan = document.getElementById('totalBookings');
            const rows = Array.from(document.getElementsByClassName('booking-row'));
            const rowsPerPage = 20;
            let currentPage = 1;
            let filteredRows = rows;

            function updateRowNumbers() {
                const start = (currentPage - 1) * rowsPerPage;
                filteredRows.slice(start, start + rowsPerPage).forEach((row, index) => {
                    row.cells[0].textContent = start + index + 1;
                });
            }

            function filterRows() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const statusTerm = statusFilter.value.toLowerCase();

                filteredRows = rows.filter(row => {
                    const bookingId = row.cells[1].textContent.toLowerCase();
                    const homestay = row.cells[2].textContent.toLowerCase();
                    const name = row.cells[8].textContent.toLowerCase();
                    const phone = row.cells[9].textContent.toLowerCase();
                    const status = row.dataset.status.toLowerCase();

                    const matchesSearch = (
                        bookingId.includes(searchTerm) ||
                        homestay.includes(searchTerm) ||
                        name.includes(searchTerm) ||
                        phone.includes(searchTerm)
                    );

                    const matchesStatus = statusTerm ? status === statusTerm : true;

                    return matchesSearch && matchesStatus;
                });

                totalBookingsSpan.textContent = filteredRows.length;
                currentPage = 1;
                renderTable();
                renderPagination();
            }

            function renderTable() {
                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                const paginatedRows = filteredRows.slice(start, end);

                bookingsTableBody.innerHTML = '';
                paginatedRows.forEach(row => {
                    bookingsTableBody.appendChild(row);
                });
                updateRowNumbers();
            }

            function renderPagination() {
                const pageCount = Math.ceil(filteredRows.length / rowsPerPage);
                paginationContainer.innerHTML = '';

                if (pageCount <= 1) return;

                const pagination = document.createElement('nav');
                pagination.innerHTML = `
                    <ul class="pagination justify-content-center">
                        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">«</span>
                            </a>
                        </li>
                        ${Array.from({ length: pageCount }, (_, i) => i + 1).map(page => `
                            <li class="page-item ${currentPage === page ? 'active' : ''}">
                                <a class="page-link" href="#">${page}</a>
                            </li>
                        `).join('')}
                        <li class="page-item ${currentPage === pageCount ? 'disabled' : ''}">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">»</span>
                            </a>
                        </li>
                    </ul>
                `;
                paginationContainer.appendChild(pagination);

                pagination.querySelectorAll('.page-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const parentLi = this.parentElement;
                        if (parentLi.classList.contains('disabled')) return;

                        if (this.getAttribute('aria-label') === 'Previous') {
                            currentPage--;
                        } else if (this.getAttribute('aria-label') === 'Next') {
                            currentPage++;
                        } else {
                            currentPage = parseInt(this.textContent);
                        }
                        renderTable();
                        renderPagination();
                        window.scrollTo({ top: document.getElementById('bookingsTable').offsetTop - 100, behavior: 'smooth' });
                    });
                });
            }

            searchInput.addEventListener('input', filterRows);
            statusFilter.addEventListener('change', filterRows);
            renderTable();
            renderPagination();

            // Calendar initialization
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                timeZone: 'Asia/Kuala_Lumpur',
                showNonCurrentDates: false,
                events: [
                    <?php foreach ($calendar_bookings as $booking):
                        $checkOutDate = new DateTime($booking['checkOUT'], new DateTimeZone('Asia/Kuala_Lumpur'));
                        $effectiveStatus = ($checkOutDate < $today) ? 'Completed' : $booking['booking_status'];
                    ?>
                        {
                            title: '<?php echo htmlspecialchars($effectiveStatus); ?>',
                            start: '<?php echo htmlspecialchars($booking['checkIN']); ?>',
                            end: '<?php echo htmlspecialchars($booking['checkOUT']); ?>',
                            backgroundColor: '<?php echo ($checkOutDate < $today) ? '#17a2b8' : '#dc3545'; ?>',
                            borderColor: '<?php echo ($checkOutDate < $today) ? '#17a2b8' : '#dc3545'; ?>',
                            allDay: true,
                            extendedProps: {
                                bookingId: '<?php echo htmlspecialchars($booking['bookingID']); ?>',
                                checkIn: '<?php echo htmlspecialchars((new DateTime($booking['checkIN']))->format('d-m-Y')); ?>',
                                checkOut: '<?php echo htmlspecialchars((new DateTime($booking['checkOUT']))->format('d-m-Y')); ?>',
                                status: '<?php echo htmlspecialchars($effectiveStatus); ?>'
                            }
                        },
                    <?php endforeach; ?>
                ],
                eventDidMount: function(info) {
                    const start = moment.utc(info.event.start).startOf('day');
                    const end = moment.utc(info.event.end).startOf('day');
                    const isPast = info.event.extendedProps.status === 'Completed';
                    for (let d = moment(start); d.isBefore(end); d.add(1, 'days')) {
                        const dateStr = d.format('YYYY-MM-DD');
                        const cell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
                        if (cell) {
                            cell.classList.add(isPast ? 'past-booking' : 'booked');
                            cell.setAttribute('data-bs-toggle', 'tooltip');
                            cell.setAttribute('data-bs-placement', 'top');
                            cell.setAttribute('title', 
                                `Booking #${info.event.extendedProps.bookingId}\n` +
                                `Check-In: ${info.event.extendedProps.checkIn}\n` +
                                `Check-Out: ${info.event.extendedProps.checkOut}\n` +
                                `Status: ${info.event.extendedProps.status}`
                            );
                        }
                    }
                },
                datesSet: function(info) {
                    document.querySelectorAll('.fc-daygrid-day.booked, .fc-daygrid-day.past-booking').forEach(cell => {
                        cell.classList.remove('booked', 'past-booking');
                        cell.removeAttribute('data-bs-toggle');
                        cell.removeAttribute('data-bs-placement');
                        cell.removeAttribute('title');
                    });

                    calendar.getEvents().forEach(function(event) {
                        const start = moment.utc(event.start).startOf('day');
                        const end = moment.utc(event.end).startOf('day');
                        const isPast = event.extendedProps.status === 'Completed';
                        for (let d = moment(start); d.isBefore(end); d.add(1, 'days')) {
                            const dateStr = d.format('YYYY-MM-DD');
                            const cell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
                            if (cell) {
                                cell.classList.add(isPast ? 'past-booking' : 'booked');
                                cell.setAttribute('data-bs-toggle', 'tooltip');
                                cell.setAttribute('data-bs-placement', 'top');
                                cell.setAttribute('title', 
                                    `Booking #${event.extendedProps.bookingId}\n` +
                                    `Check-In: ${event.extendedProps.checkIn}\n` +
                                    `Check-Out: ${event.extendedProps.checkOut}\n` +
                                    `Status: ${event.extendedProps.status}`
                                );
                            }
                        }
                    });

                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }
            });
            calendar.render();

            // Calculate Amount Paid based on Check-In and Check-Out dates
            const checkInInput = document.getElementById('checkIN');
            const checkOutInput = document.getElementById('checkOUT');
            const amountPaidInput = document.getElementById('amount_paid');
            const ratePerDay = 250; // RM250 per day

            function calculateAmountPaid() {
                const checkIn = new Date(checkInInput.value);
                const checkOut = new Date(checkOutInput.value);
                if (checkIn && checkOut && checkOut > checkIn) {
                    const diffTime = checkOut - checkIn;
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    const totalAmount = diffDays * ratePerDay;
                    return totalAmount.toFixed(2);
                } else {
                    return '';
                }
            }

            function updateAmountPaid() {
                amountPaidInput.value = calculateAmountPaid();
            }

            checkInInput.addEventListener('change', updateAmountPaid);
            checkOutInput.addEventListener('change', updateAmountPaid);

            // Initialize amount paid on page load if dates are pre-filled
            updateAmountPaid();
        });
    </script>
</body>
</html>