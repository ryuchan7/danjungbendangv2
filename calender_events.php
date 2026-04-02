<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Handle success/error messages from approval
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch all bookings with homestay and user/customer info
try {
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
            COALESCE(u.full_name, b.full_name) AS customer_name, 
            COALESCE(u.email, b.email) AS customer_email, 
            COALESCE(u.phone, b.phone) AS customer_phone, 
            COALESCE(u.ic_no, b.ic_no) AS customer_ic_no,
            b.created_at
        FROM booking b
        INNER JOIN homestaylist h ON b.hslistID = h.hslistID
        LEFT JOIN users u ON b.userID = u.userID
        ORDER BY b.created_at DESC
    ");
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching bookings: ' . $e->getMessage());
}

// Fetch all users
try {
    $stmt = $pdo->prepare("
        SELECT 
            userID, 
            username, 
            email, 
            full_name, 
            phone, 
            ic_no, 
            role, 
            created_at
        FROM users
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching users: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cozy Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f9f9;
        }
        .container {
            max-width: 1400px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        h2 {
            color: #26a69a;
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }
        .btn-approve {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .btn-approve:hover {
            background: linear-gradient(135deg, #1e7e34, #28a745);
        }
        .table-responsive {
            margin-bottom: 40px;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .table th {
            background: #26a69a;
            color: #fff;
        }
        .calendar-container {
            margin-bottom: 40px;
        }
        #calendar {
            max-width: 1000px;
            margin: 0 auto;
        }
        .users-section {
            margin-top: 40px;
        }
        .fc-daygrid-day.booked {
            background: #dc3545 !important;
            color: #fff !important;
        }
        .fc-daygrid-day.booked .fc-daygrid-day-number,
        .fc-daygrid-day.booked a {
            color: #fff !important;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 20px;
            }
            #calendar {
                font-size: 0.9rem;
            }
            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <p class="text-center">Admin Dashboard for Cozy Homestay</p>
        <div class="text-end mb-4">
            <a href="logout.php" class="btn btn-logout">Logout</a>
        </div>

        <!-- Display Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Bookings Table -->
        <h3>All Bookings</h3>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User ID</th>
                        <th>Homestay</th>
                        <th>Book Date</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Guests</th>
                        <th>Payment Status</th>
                        <th>Payment Method</th>
                        <th>Amount Paid</th>
                        <th>Booking Status</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>IC No</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="17" class="text-center">No bookings found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['bookingID']); ?></td>
                                <td><?php echo htmlspecialchars($booking['userID'] ?? 'Guest'); ?></td>
                                <td><?php echo htmlspecialchars($booking['homestay_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['book_date']); ?></td>
                                <td><?php echo htmlspecialchars($booking['checkIN']); ?></td>
                                <td><?php echo htmlspecialchars($booking['checkOUT']); ?></td>
                                <td><?php echo htmlspecialchars($booking['number_of_guests']); ?></td>
                                <td><?php echo htmlspecialchars($booking['payment_status']); ?></td>
                                <td><?php echo htmlspecialchars($booking['payment_method']); ?></td>
                                <td>$<?php echo number_format($booking['amount_paid'], 2); ?></td>
                                <td><?php echo htmlspecialchars($booking['booking_status']); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_ic_no'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($booking['created_at']); ?></td>
                                <td>
                                    <?php if ($booking['booking_status'] === 'Pending Payment'): ?>
                                        <form action="approve_booking.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['bookingID']; ?>">
                                            <button type="submit" class="btn btn-approve">Approve</button>
                                        </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Calendar -->
        <h3>Booking Calendar</h3>
        <div class="calendar-container">
            <div id="calendar"></div>
        </div>

        <!-- Users Section -->
        <h3>User Information</h3>
        <div class="table-responsive users-section">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>IC No</th>
                        <th>Role</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['userID']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['ic_no'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                events: {
                    url: 'calendar_events.php',
                    method: 'GET',
                    failure: function(error) {
                        console.error('Error fetching calendar events:', error);
                        alert('Failed to load calendar events. Please check the console for details.');
                    }
                },
                eventDidMount: function(info) {
                    // Highlight booked days
                    var start = new Date(info.event.start);
                    var end = new Date(info.event.end || info.event.start); // Handle single-day events
                    end.setDate(end.getDate());
                    for (var d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                        var dateStr = d.toISOString().split('T')[0];
                        var cell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
                        if (cell) {
                            cell.classList.add('booked');
                        }
                    }
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>