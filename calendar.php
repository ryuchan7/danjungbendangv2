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

date_default_timezone_set('UTC');

// Handle messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

try {
    // Fetch calendar bookings
    $stmt = $pdo->prepare("
        SELECT bookingID, checkIN, checkOUT, booking_status
        FROM booking
        WHERE hslistID = 1 AND booking_status NOT IN ('Cancelled', 'Rejected')
    ");
    $stmt->execute();
    $calendar_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $booked_dates = [];
    foreach ($calendar_bookings as $booking) {
        $start = new DateTime($booking['checkIN'], new DateTimeZone('UTC'));
        $end = new DateTime($booking['checkOUT'], new DateTimeZone('UTC'));
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
    <title>Booking Calendar - Cozy Homestay</title>
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
        .fc-daygrid-day.booked {
            background: #dc3545 !important;
            color: #fff !important;
        }
        .fc .fc-button {
            background: linear-gradient(135deg, #26a69a, #00695c);
            border: none;
            border-radius: 20px;
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
        }
        .calendar-legend .booked, .calendar-legend .available {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }
        .calendar-legend .booked {
            background: #dc3545;
        }
        .calendar-legend .available {
            background: #28a745;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">D'Anjung Bendang Homestay Homestay</div>
            <a href="admin_dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="analytics.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
            <a href="user_management.php" class="nav-link">
                <i class="fas fa-users"></i> User Management
            </a>
            <a href="bookings.php" class="nav-link">
                <i class="fas fa-book"></i> Bookings
            </a>
            <a href="calendar.php" class="nav-link active">
                <i class="fas fa-calendar-alt"></i> Calendar
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <a href="audit_logs.php" class="nav-link">
                <i class="fas fa-history"></i> Audit Logs
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Booking Calendar</h2>
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

            <div class="calendar-container">
                <div id="calendar"></div>
                <div class="calendar-legend">
                    <span><span class="booked"></span> Booked</span>
                    <span><span class="available"></span> Available</span>
                </div>
            </div>
        </div>
    </div>
</body>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Calendar initialization
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            timeZone: 'UTC',
            showNonCurrentDates: false,
            events: [
                <?php foreach ($calendar_bookings as $booking): ?>
                    {
                        title: 'Booked',
                        start: '<?php echo htmlspecialchars($booking['checkIN']); ?>',
                        end: '<?php echo htmlspecialchars($booking['checkOUT']); ?>',
                        backgroundColor: '#dc3545',
                        borderColor: '#dc3545',
                        allDay: true,
                        extendedProps: {
                            bookingId: '<?php echo htmlspecialchars($booking['bookingID']); ?>',
                            checkIn: '<?php echo htmlspecialchars($booking['checkIN']); ?>',
                            checkOut: '<?php echo htmlspecialchars($booking['checkOUT']); ?>',
                            status: '<?php echo htmlspecialchars($booking['booking_status']); ?>'
                        }
                    },
                <?php endforeach; ?>
            ],
            eventDidMount: function(info) {
                const start = moment.utc(info.event.start).startOf('day');
                const end = moment.utc(info.event.end).startOf('day');
                for (let d = moment(start); d.isBefore(end); d.add(1, 'days')) {
                    const dateStr = d.format('YYYY-MM-DD');
                    const cell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
                    if (cell) {
                        cell.classList.add('booked');
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
                document.querySelectorAll('.fc-daygrid-day.booked').forEach(cell => {
                    cell.classList.remove('booked');
                    cell.removeAttribute('data-bs-toggle');
                    cell.removeAttribute('data-bs-placement');
                    cell.removeAttribute('title');
                });

                calendar.getEvents().forEach(function(event) {
                    const start = moment.utc(event.start).startOf('day');
                    const end = moment.utc(event.end).startOf('day');
                    for (let d = moment(start); d.isBefore(end); d.add(1, 'days')) {
                        const dateStr = d.format('YYYY-MM-DD');
                        const cell = document.querySelector(`.fc-daygrid-day[data-date="${dateStr}"]`);
                        if (cell) {
                            cell.classList.add('booked');
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
    });
</script>
</html>