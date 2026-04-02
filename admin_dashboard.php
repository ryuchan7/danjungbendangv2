<?php
ob_start(); // Start output buffering at the very top
session_start();
require_once 'db_connect.php';

// Configuration
define('TIMEZONE', 'UTC');
define('LOG_FILE', __DIR__ . '/logs/error.log');

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

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Handle notifications endpoint
if (isset($_GET['check_notifications']) && $_GET['check_notifications'] == 1) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM booking WHERE booking_status IN ('Pending Payment', 'Pending Approval')");
        $count = $stmt->fetchColumn();
        header('Content-Type: application/json');
        echo json_encode(['count' => $count]);
    } catch (PDOException $e) {
        error_log('Notification check error: ' . $e->getMessage(), 3, LOG_FILE);
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// Handle bookings data endpoint
if (isset($_GET['get_bookings']) && $_GET['get_bookings'] == 1) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    if ($year < 2024 || $year > 2030) {
        $year = date('Y');
    }
    $monthly_bookings = [];
    $labels = [];
    try {
        for ($month = 1; $month <= 12; $month++) {
            $month_str = sprintf('%04d-%02d', $year, $month);
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as booking_count
                FROM booking
                WHERE DATE_FORMAT(checkIN, '%Y-%m') = :month
                AND YEAR(checkIN) BETWEEN 2024 AND 2030
            ");
            $stmt->execute(['month' => $month_str]);
            $booking_count = $stmt->fetchColumn();
            $monthly_bookings[] = $booking_count;
            $labels[] = date('M', mktime(0, 0, 0, $month, 1, $year));
        }
        header('Content-Type: application/json');
        echo json_encode(['labels' => $labels, 'bookings' => $monthly_bookings]);
    } catch (PDOException $e) {
        error_log('Chart data error: ' . $e->getMessage(), 3, LOG_FILE);
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// Handle monthly sales data endpoint
if (isset($_GET['get_sales']) && $_GET['get_sales'] == 1) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    if ($year < 2024 || $year > 2030) {
        $year = date('Y');
    }
    $monthly_sales = [];
    $labels = [];
    try {
        for ($month = 1; $month <= 12; $month++) {
            $month_str = sprintf('%04d-%02d', $year, $month);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount_paid), 0) as sales_amount
                FROM booking
                WHERE payment_status = 'Completed'
                AND DATE_FORMAT(checkIN, '%Y-%m') = :month
                AND YEAR(checkIN) BETWEEN 2024 AND 2030
            ");
            $stmt->execute(['month' => $month_str]);
            $sales_amount = $stmt->fetchColumn();
            $monthly_sales[] = $sales_amount;
            $labels[] = date('M', mktime(0, 0, 0, $month, 1, $year));
        }
        header('Content-Type: application/json');
        echo json_encode(['labels' => $labels, 'sales' => $monthly_sales]);
    } catch (PDOException $e) {
        error_log('Sales chart data error: ' . $e->getMessage(), 3, LOG_FILE);
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// Handle yearly sales data endpoint
if (isset($_GET['get_yearly_sales']) && $_GET['get_yearly_sales'] == 1) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $yearly_sales = [];
    $labels = [];
    try {
        for ($year = 2024; $year <= 2030; $year++) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount_paid), 0) as sales_amount
                FROM booking
                WHERE payment_status = 'Completed'
                AND YEAR(checkIN) = :year
            ");
            $stmt->execute(['year' => $year]);
            $sales_amount = $stmt->fetchColumn();
            $yearly_sales[] = $sales_amount;
            $labels[] = $year;
        }
        header('Content-Type: application/json');
        echo json_encode(['labels' => $labels, 'sales' => $yearly_sales]);
    } catch (PDOException $e) {
        error_log('Yearly sales chart data error: ' . $e->getMessage(), 3, LOG_FILE);
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// Handle dashboard refresh endpoint
if (isset($_GET['refresh_dashboard']) && $_GET['refresh_dashboard'] == 1) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    try {
        // Total Sales
        $stmt = $pdo->query("SELECT SUM(amount_paid) as total_sales FROM booking WHERE payment_status = 'Completed'");
        $total_sales = $stmt->fetchColumn() ?: 0;

        // Total Bookings
        $stmt = $pdo->query("SELECT COUNT(*) as total_bookings FROM booking");
        $total_bookings = $stmt->fetchColumn();

        // Total Users
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $total_users = $stmt->fetchColumn();

        // Pending Actions
        $stmt = $pdo->query("SELECT COUNT(*) as pending_actions FROM booking WHERE booking_status IN ('Pending Payment', 'Pending Approval')");
        $pending_actions = $stmt->fetchColumn();

        // Total Cancellations
        $stmt = $pdo->query("SELECT COUNT(*) as total_cancellations FROM booking WHERE booking_status = 'Cancelled'");
        $total_cancellations = $stmt->fetchColumn();

        // Occupancy Rate
        $current_month = date('Y-m');
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT DATE(checkIN)) as booked_days
            FROM booking
            WHERE booking_status NOT IN ('Cancelled', 'Rejected')
            AND DATE_FORMAT(checkIN, '%Y-%m') = :current_month
        ");
        $stmt->execute(['current_month' => $current_month]);
        $booked_days = $stmt->fetchColumn();
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'));
        $occupancy_rate = $booked_days ? round(($booked_days / $days_in_month) * 100, 2) : 0;

        // Monthly Bookings Data
        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        if ($year < 2024 || $year > 2030) {
            $year = date('Y');
        }
        $monthly_bookings = [];
        $labels = [];
        for ($month = 1; $month <= 12; $month++) {
            $month_str = sprintf('%04d-%02d', $year, $month);
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as booking_count
                FROM booking
                WHERE DATE_FORMAT(checkIN, '%Y-%m') = :month
                AND YEAR(checkIN) BETWEEN 2024 AND 2030
            ");
            $stmt->execute(['month' => $month_str]);
            $booking_count = $stmt->fetchColumn();
            $monthly_bookings[] = $booking_count;
            $labels[] = date('M', mktime(0, 0, 0, $month, 1, $year));
        }

        // Monthly Sales Data
        $monthly_sales = [];
        for ($month = 1; $month <= 12; $month++) {
            $month_str = sprintf('%04d-%02d', $year, $month);
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount_paid), 0) as sales_amount
                FROM booking
                WHERE payment_status = 'Completed'
                AND DATE_FORMAT(checkIN, '%Y-%m') = :month
                AND YEAR(checkIN) BETWEEN 2024 AND 2030
            ");
            $stmt->execute(['month' => $month_str]);
            $sales_amount = $stmt->fetchColumn();
            $monthly_sales[] = $sales_amount;
        }

        // Yearly Sales Data
        $yearly_sales = [];
        $yearly_labels = [];
        for ($year = 2024; $year <= 2030; $year++) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount_paid), 0) as sales_amount
                FROM booking
                WHERE payment_status = 'Completed'
                AND YEAR(checkIN) = :year
            ");
            $stmt->execute(['year' => $year]);
            $sales_amount = $stmt->fetchColumn();
            $yearly_sales[] = $sales_amount;
            $yearly_labels[] = $year;
        }

        // Recent Activities
        $stmt = $pdo->prepare("
            SELECT al.log_id, al.user_id, al.action, al.details, al.created_at, u.username
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.userID
            ORDER BY al.created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'total_sales' => number_format($total_sales, 2),
            'total_bookings' => $total_bookings,
            'total_users' => $total_users,
            'pending_actions' => $pending_actions,
            'total_cancellations' => $total_cancellations,
            'occupancy_rate' => $occupancy_rate,
            'monthly_bookings' => $monthly_bookings,
            'monthly_sales' => $monthly_sales,
            'monthly_labels' => $labels,
            'yearly_sales' => $yearly_sales,
            'yearly_labels' => $yearly_labels,
            'recent_activities' => $recent_activities
        ]);
    } catch (PDOException $e) {
        error_log('Dashboard refresh error: ' . $e->getMessage(), 3, LOG_FILE);
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// Handle messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch username
try {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE userID = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $user['username'] ?? 'Admin';
} catch (PDOException $e) {
    error_log('Error fetching username: ' . $e->getMessage(), 3, LOG_FILE);
    $error_message = 'Error fetching username. Please try again.';
    $username = 'Admin';
}

// Fetch dashboard metrics
try {
    // Total Sales
    $stmt = $pdo->query("SELECT SUM(amount_paid) as total_sales FROM booking WHERE payment_status = 'Completed'");
    $total_sales = $stmt->fetchColumn() ?: 0;

    // Total Bookings
    $stmt = $pdo->query("SELECT COUNT(*) as total_bookings FROM booking");
    $total_bookings = $stmt->fetchColumn();

    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $total_users = $stmt->fetchColumn();

    // Total Cancellations
    $stmt = $pdo->query("SELECT COUNT(*) as total_cancellations FROM booking WHERE booking_status = 'Cancelled'");
    $total_cancellations = $stmt->fetchColumn();

    // Pending Actions
    $stmt = $pdo->query("SELECT COUNT(*) as pending_actions FROM booking WHERE booking_status IN ('Pending Payment', 'Pending Approval')");
    $pending_actions = $stmt->fetchColumn();

    // Occupancy Rate
    $current_month = date('Y-m');
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(checkIN)) as booked_days
        FROM booking
        WHERE booking_status NOT IN ('Cancelled', 'Rejected')
        AND DATE_FORMAT(checkIN, '%Y-%m') = :current_month
    ");
    $stmt->execute(['current_month' => $current_month]);
    $booked_days = $stmt->fetchColumn();
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'));
    $occupancy_rate = $booked_days ? round(($booked_days / $days_in_month) * 100, 2) : 0;

    // Monthly Bookings Data
    $year = date('Y');
    $monthly_bookings = [];
    $labels = [];
    for ($month = 1; $month <= 12; $month++) {
        $month_str = sprintf('%04d-%02d', $year, $month);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as booking_count
            FROM booking
            WHERE DATE_FORMAT(checkIN, '%Y-%m') = :month
            AND YEAR(checkIN) BETWEEN 2024 AND 2030
        ");
        $stmt->execute(['month' => $month_str]);
        $booking_count = $stmt->fetchColumn();
        $monthly_bookings[] = $booking_count;
        $labels[] = date('M', mktime(0, 0, 0, $month, 1, $year));
    }

    // Monthly Sales Data
    $monthly_sales = [];
    for ($month = 1; $month <= 12; $month++) {
        $month_str = sprintf('%04d-%02d', $year, $month);
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount_paid), 0) as sales_amount
            FROM booking
            WHERE payment_status = 'Completed'
            AND DATE_FORMAT(checkIN, '%Y-%m') = :month
            AND YEAR(checkIN) BETWEEN 2024 AND 2030
        ");
        $stmt->execute(['month' => $month_str]);
        $sales_amount = $stmt->fetchColumn();
        $monthly_sales[] = $sales_amount;
    }

    // Yearly Sales Data
    $yearly_sales = [];
    $yearly_labels = [];
    for ($year = 2024; $year <= 2030; $year++) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount_paid), 0) as sales_amount
            FROM booking
            WHERE payment_status = 'Completed'
            AND YEAR(checkIN) = :year
        ");
        $stmt->execute(['year' => $year]);
        $sales_amount = $stmt->fetchColumn();
        $yearly_sales[] = $sales_amount;
        $yearly_labels[] = $year;
    }

    // Calendar Bookings
    $stmt = $pdo->prepare("
        SELECT bookingID, checkIN, checkOUT, booking_status
        FROM booking
        WHERE hslistID = 1 AND booking_status NOT IN ('Cancelled', 'Rejected')
    ");
    $stmt->execute();
    $calendar_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $booked_dates = [];
    $today = new DateTime('now', new DateTimeZone('UTC'));
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

    // Recent Activities
    $stmt = $pdo->prepare("
        SELECT al.log_id, al.user_id, al.action, al.details, al.created_at, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.userID
        ORDER BY al.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage() . ' at line ' . __LINE__, 3, LOG_FILE);
    $error_message = 'Database error. Please try again later.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Admin Dashboard - D'Anjung Bendang Homestay</title>
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
            transition: background-color 0.3s, color 0.3s;
        }
        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
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
            position: sticky;
            top: 0;
            height: 100vh;
        }
        body.dark-mode .sidebar {
            background: #2c2c2c;
            border-right: 1px solid #444;
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
        body.dark-mode .sidebar .nav-link {
            color: #adb5bd;
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
        body.dark-mode .main-content {
            background: #222;
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
        body.dark-mode .header h2 {
            color: #e0e0e0;
        }
        .welcome-message {
            color: #26a69a;
            font-size: 1.2rem;
            font-weight: 500;
        }
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }
        .overview-card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        body.dark-mode .overview-card {
            background: linear-gradient(135deg, #333, #444);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .overview-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #26a69a, #2dd4bf);
            opacity: 0.8;
        }
        .overview-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        body.dark-mode .overview-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.5);
        }
        .overview-card h5 {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        body.dark-mode .overview-card h5 {
            color: #adb5bd;
        }
        .overview-card p {
            font-size: 1.8rem;
            font-weight: 700;
            color: #26a69a;
            margin: 0;
            line-height: 1.2;
        }
        body.dark-mode .overview-card p {
            color: #2dd4bf;
        }
        .overview-card i {
            color: #26a69a;
            font-size: 2rem;
            margin-bottom: 10px;
            transition: transform 0.3s ease;
        }
        .overview-card:hover i {
            transform: scale(1.2);
        }
        .section {
            margin-bottom: 30px;
        }
        .section h3 {
            color: #26a69a;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .quick-links .btn {
            margin-right: 10px;
            margin-bottom: 10px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        .quick-links .btn i {
            margin-right: 5px;
        }
        .chart-container {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            height: 300px;
            position: relative;
            transition: all 0.3s ease;
        }
        body.dark-mode .chart-container {
            background: #333;
            border: 1px solid #555;
        }
        .chart-container.fullscreen {
            width: 100vw;
            height: 100vh;
            padding: 20px;
            z-index: 9999;
            background: inherit;
        }
        .chart-container canvas {
            max-height: 200px !important;
            width: 100% !important;
        }
        .chart-container.fullscreen canvas {
            max-height: 80vh !important;
            width: 100% !important;
        }
        .btn-fullscreen {
            padding: 5px 10px;
            font-size: 0.9rem;
        }
        .btn-fullscreen i {
            transition: all 0.3s ease;
        }
        .btn-fullscreen.active i {
            transform: rotate(45deg);
        }
        body.dark-mode .btn-fullscreen {
            border-color: #555;
            color: #e0e0e0;
        }
        body.dark-mode .btn-fullscreen:hover {
            background: #444;
        }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        body.dark-mode .activity-item {
            border-bottom: 1px solid #555;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item .action {
            font-weight: 500;
            color: #26a69a;
        }
        .activity-item .details {
            color: #6c757d;
            font-size: 0.9rem;
        }
        body.dark-mode .activity-item .details {
            color: #adb5bd;
        }
        .activity-item .timestamp {
            font-size: 0.85rem;
            color: #adb5bd;
        }
        body.dark-mode .activity-item .timestamp {
            color: #999;
        }
        .loading-spinner {
            min-height: 100px;
        }
        .calendar-container {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        body.dark-mode .calendar-container {
            background: #333;
            border: 1px solid #555;
        }
        .fc-daygrid-day.booked {
            background: #dc3545 !important;
            color: #fff;
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
        .calendar-legend .booked, .calendar-legend .available, .calendar-legend .completed {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }
        .calendar-legend .booked {
            background: #dc3545;
        }
        .calendar-legend .available {
            background: #ffffff;
            border: 1px solid #dee2e6;
        }
        .calendar-legend .completed {
            background: #17a2b8;
        }
        @media (max-width: 992px) {
            .overview-card {
                padding: 15px;
            }
            .overview-card p {
                font-size: 1.5rem;
            }
            .overview-card i {
                font-size: 1.8rem;
            }
            .chart-container {
                height: 250px;
            }
            .chart-container canvas {
                max-height: 180px !important;
            }
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }
            .wrapper {
                flex-direction: column;
            }
            .main-content {
                padding: 15px;
            }
            .overview-card {
                margin-bottom: 15px;
            }
            .overview-card p {
                font-size: 1.3rem;
            }
            .overview-card i {
                font-size: 1.5rem;
            }
            .chart-container {
                height: 200px;
            }
            .chart-container canvas {
                max-height: 150px !important;
            }
        }
    </style>
</head>
<body class="light-mode">
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" role="navigation" aria-label="Main navigation">
            <div class="logo">D'Anjung Bendang Homestay</div>
            <a href="admin_dashboard.php" class="nav-link active" aria-current="page">
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
                <div>
                    <h2>Admin Dashboard</h2>
                    <p class="welcome-message">
                        <?php
                        $hour = date('H');
                        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
                        echo "$greeting, " . htmlspecialchars($username) . "!";
                        ?>
                    </p>
                </div>
                <div class="d-flex align-items-center">
                    <button id="theme-toggle" class="btn btn-outline-secondary me-2" aria-label="Toggle dark mode">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a href="logout.php" class="btn btn-logout" aria-label="Logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            <!-- Messages -->
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
            <!-- Quick Links -->
            <div class="section quick-links">
                <h3>Quick Actions</h3>
                <a href="user_management.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add New User</a>
                <a href="bookings.php" class="btn btn-secondary"><i class="fas fa-book"></i> View All Bookings</a>
                <a href="reports.php" class="btn btn-info"><i class="fas fa-file-alt"></i> Generate Reports</a>
                <form action="" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" name="export_metrics" class="btn btn-success"><i class="fas fa-download"></i> Export Metrics</button>
                </form>
            </div>
            <!-- Export Metrics Handler -->
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_metrics'])) {
                if (headers_sent($file, $line)) {
                    error_log("Headers already sent in $file on line $line", 3, LOG_FILE);
                    $_SESSION['error_message'] = 'Cannot export metrics due to output conflict.';
                    header('Location: admin_dashboard.php');
                    exit;
                }
                if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                    error_log('Invalid CSRF token for export metrics', 3, LOG_FILE);
                    $_SESSION['error_message'] = 'Security error: Invalid CSRF token.';
                    header('Location: admin_dashboard.php');
                    exit;
                }
                try {
                    $metrics = [];
                    $stmt = $pdo->query("SELECT SUM(amount_paid) as total_sales FROM booking WHERE payment_status = 'Completed'");
                    $metrics['total_sales'] = $stmt->fetchColumn() ?: 0;
                    $stmt = $pdo->query("SELECT COUNT(*) as total_bookings FROM booking");
                    $metrics['total_bookings'] = $stmt->fetchColumn();
                    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
                    $metrics['total_users'] = $stmt->fetchColumn();
                    $stmt = $pdo->query("SELECT COUNT(*) as pending_actions FROM booking WHERE booking_status IN ('Pending Payment', 'Pending Approval')");
                    $metrics['pending_actions'] = $stmt->fetchColumn();
                    $stmt = $pdo->query("SELECT COUNT(*) as total_cancellations FROM booking WHERE booking_status = 'Cancelled'");
                    $metrics['total_cancellations'] = $stmt->fetchColumn();
                    $current_month = date('Y-m');
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT DATE(checkIN)) as booked_days
                        FROM booking
                        WHERE booking_status NOT IN ('Cancelled', 'Rejected')
                        AND DATE_FORMAT(checkIN, '%Y-%m') = :current_month
                    ");
                    $stmt->execute(['current_month' => $current_month]);
                    $booked_days = $stmt->fetchColumn();
                    $days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'));
                    $metrics['occupancy_rate'] = $booked_days ? round(($booked_days / $days_in_month) * 100, 2) : 0;

                    // Yearly Sales for Export
                    $yearly_sales = [];
                    for ($year = 2024; $year <= 2030; $year++) {
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(amount_paid), 0) as sales_amount
                            FROM booking
                            WHERE payment_status = 'Completed'
                            AND YEAR(checkIN) = :year
                        ");
                        $stmt->execute(['year' => $year]);
                        $yearly_sales[$year] = $stmt->fetchColumn();
                    }

                    $csv_data = [
                        ['Metric', 'Value'],
                        ['Total Sales', '$' . number_format($metrics['total_sales'], 2)],
                        ['Total Bookings', $metrics['total_bookings']],
                        ['Total Users', $metrics['total_users']],
                        ['Pending Actions', $metrics['pending_actions']],
                        ['Total Cancellations', $metrics['total_cancellations']],
                        ['Occupancy Rate', $metrics['occupancy_rate'] . '%'],
                        [''],
                        ['Yearly Sales'],
                        ['Year', 'Sales ($)']
                    ];
                    foreach ($yearly_sales as $year => $sales) {
                        $csv_data[] = [$year, '$' . number_format($sales, 2)];
                    }

                    $csv_content = '';
                    foreach ($csv_data as $row) {
                        $csv_content .= implode(',', array_map('strval', $row)) . "\n";
                    }
                    $content_length = strlen($csv_content);
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="metrics_export_' . date('Ymd') . '.csv"');
                    header('Content-Length: ' . $content_length);
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    ob_get_clean();
                    $output = fopen('php://output', 'w');
                    if ($output === false) {
                        throw new Exception('Failed to open output stream');
                    }
                    foreach ($csv_data as $row) {
                        fputcsv($output, $row);
                    }
                    fclose($output);
                    exit;
                } catch (PDOException $e) {
                    error_log('Export metrics database error: ' . $e->getMessage() . ' at line ' . __LINE__, 3, LOG_FILE);
                    $_SESSION['error_message'] = 'Database error during export. Please try again.';
                    header('Location: admin_dashboard.php');
                    exit;
                } catch (Exception $e) {
                    error_log('Export metrics error: ' . $e->getMessage() . ' at line ' . __LINE__, 3, LOG_FILE);
                    $_SESSION['error_message'] = 'Failed to export metrics: ' . $e->getMessage();
                    header('Location: admin_dashboard.php');
                    exit;
                }
            }
            ?>
            <!-- Overview Section -->
            <div id="overviewSection" class="section">
                <h3>Overview</h3>
                <div class="loading-spinner text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div class="row mb-4 g-4 d-none" id="overviewContent">
                    <div class="col-lg-4 col-md-6">
                        <div class="overview-card">
                            <i class="fas fa-dollar-sign"></i>
                            <h5>Total Sales</h5>
                            <p id="totalSales">$<?php echo number_format($total_sales, 2); ?></p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="overview-card">
                            <i class="fas fa-book"></i>
                            <h5>Total Bookings</h5>
                            <p id="totalBookings"><?php echo $total_bookings; ?></p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="overview-card">
                            <i class="fas fa-users"></i>
                            <h5>Total Users</h5>
                            <p id="totalUsers"><?php echo $total_users; ?></p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="overview-card">
                            <i class="fas fa-clock"></i>
                            <h5>Pending Actions</h5>
                            <p id="pendingActions"><?php echo $pending_actions; ?></p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="overview-card">
                            <i class="fas fa-times-circle"></i>
                            <h5>Total Cancellations</h5>
                            <p id="totalCancellations"><?php echo $total_cancellations; ?></p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="overview-card">
                            <i class="fas fa-percentage"></i>
                            <h5>Occupancy Rate</h5>
                            <p id="occupancyRate"><?php echo $occupancy_rate; ?>%</p>
                        </div>
                    </div>
                </div>
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
            <!-- Charts Section -->
            <div class="section">
                <h3>Analytics</h3>
                <div class="row g-4">
                    <div class="col-lg-6 col-md-12">
                        <div class="chart-container" id="monthlyBookingsChartContainer">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Monthly Bookings Trend (2024-2030)</h4>
                                <button class="btn btn-outline-secondary btn-fullscreen" data-chart="monthlyBookingsChartContainer" aria-label="Toggle full screen for bookings chart">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <div class="mb-3">
                                <label for="yearFilter" class="form-label">Select Year</label>
                                <select id="yearFilter" class="form-select w-auto d-inline-block">
                                    <?php for ($i = 2030; $i >= 2024; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == date('Y') ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <canvas id="monthlyBookingsChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12">
                        <div class="chart-container" id="monthlySalesChartContainer">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Monthly Sales Trend (2024-2030)</h4>
                                <button class="btn btn-outline-secondary btn-fullscreen" data-chart="monthlySalesChartContainer" aria-label="Toggle full screen for sales chart">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <div class="mb-3">
                                <label for="salesYearFilter" class="form-label">Select Year</label>
                                <select id="salesYearFilter" class="form-select w-auto d-inline-block">
                                    <?php for ($i = 2030; $i >= 2024; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == date('Y') ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <canvas id="monthlySalesChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12">
                        <div class="chart-container" id="yearlySalesChartContainer">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Yearly Sales Trend (2024-2030)</h4>
                                <button class="btn btn-outline-secondary btn-fullscreen" data-chart="yearlySalesChartContainer" aria-label="Toggle full screen for yearly sales chart">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <canvas id="yearlySalesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Recent Activity -->
            <div class="section">
                <h3>Recent Activity <span id="notificationBadge" class="badge bg-danger d-none">0</span></h3>
                <div class="chart-container" id="recentActivity">
                    <?php if (empty($recent_activities)): ?>
                        <p>No recent activity found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="action"><?php echo htmlspecialchars($activity['action']); ?></span>
                                        <div class="details">
                                            By <?php echo htmlspecialchars($activity['username'] ?? 'Unknown User'); ?>
                                            (Details: <?php echo htmlspecialchars($activity['details']); ?>)
                                        </div>
                                    </div>
                                    <div class="timestamp">
                                        <?php echo htmlspecialchars($activity['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const icon = themeToggle.querySelector('i');
            icon.classList.toggle('fa-moon');
            icon.classList.toggle('fa-sun');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        });
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.querySelector('i').classList.replace('fa-moon', 'fa-sun');
        }
        // Loading Indicator
        document.addEventListener('DOMContentLoaded', () => {
            const overviewSection = document.getElementById('overviewSection');
            const spinner = overviewSection.querySelector('.loading-spinner');
            const content = overviewSection.querySelector('#overviewContent');
            setTimeout(() => {
                spinner.classList.add('d-none');
                content.classList.remove('d-none');
            }, 500);

            // Calendar initialization
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                timeZone: 'UTC',
                showNonCurrentDates: false,
                events: [
                    <?php foreach ($calendar_bookings as $booking):
                        $checkOutDate = new DateTime($booking['checkOUT'], new DateTimeZone('UTC'));
                        $isPastBooking = $checkOutDate < new DateTime('now', new DateTimeZone('UTC'));
                    ?>
                        {
                            title: '<?php echo $isPastBooking ? 'Completed' : 'Booked'; ?>',
                            start: '<?php echo htmlspecialchars($booking['checkIN']); ?>',
                            end: '<?php echo htmlspecialchars($booking['checkOUT']); ?>',
                            backgroundColor: '<?php echo $isPastBooking ? '#17a2b8' : '#dc3545'; ?>',
                            borderColor: '<?php echo $isPastBooking ? '#17a2b8' : '#dc3545'; ?>',
                            allDay: true,
                            extendedProps: {
                                bookingId: '<?php echo htmlspecialchars($booking['bookingID']); ?>',
                                checkIn: '<?php echo htmlspecialchars((new DateTime($booking['checkIN']))->format('d-m-Y')); ?>',
                                checkOut: '<?php echo htmlspecialchars((new DateTime($booking['checkOUT']))->format('d-m-Y')); ?>',
                                status: '<?php echo htmlspecialchars($isPastBooking ? 'Completed' : $booking['booking_status']); ?>'
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
                    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }
            });
            calendar.render();
        });
        // Monthly Bookings Chart
        const monthlyCtx = document.getElementById('monthlyBookingsChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Number of Bookings',
                    data: <?php echo json_encode($monthly_bookings); ?>,
                    backgroundColor: 'rgba(38, 166, 154, 0.6)',
                    borderColor: 'rgba(38, 166, 154, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Bookings'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
        // Monthly Sales Chart
        const salesCtx = document.getElementById('monthlySalesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Total Sales ($)',
                    data: <?php echo json_encode($monthly_sales); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sales ($)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                return `Sales: $${value.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });
        // Yearly Sales Chart
        const yearlySalesCtx = document.getElementById('yearlySalesChart').getContext('2d');
        const yearlySalesChart = new Chart(yearlySalesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($yearly_labels); ?>,
                datasets: [{
                    label: 'Total Sales ($)',
                    data: <?php echo json_encode($yearly_sales); ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.6)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sales ($)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Year'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                return `Sales: $${value.toLocaleString()}`;
                            }
                        }
                    }
                }
            }
        });
        // Year Filter for Monthly Bookings Chart
        document.getElementById('yearFilter').addEventListener('change', async (e) => {
            const year = e.target.value;
            try {
                const response = await fetch(`?get_bookings=1&year=${year}`);
                const data = await response.json();
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                monthlyChart.data.labels = data.labels;
                monthlyChart.data.datasets[0].data = data.bookings;
                monthlyChart.update();
            } catch (error) {
                console.error('Error fetching chart data:', error);
            }
        });
        // Year Filter for Monthly Sales Chart
        document.getElementById('salesYearFilter').addEventListener('change', async (e) => {
            const year = e.target.value;
            try {
                const response = await fetch(`?get_sales=1&year=${year}`);
                const data = await response.json();
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                salesChart.data.labels = data.labels;
                salesChart.data.datasets[0].data = data.sales;
                salesChart.update();
            } catch (error) {
                console.error('Error fetching sales chart data:', error);
            }
        });
        // Notification Polling
        async function checkNotifications() {
            try {
                const response = await fetch('?check_notifications=1');
                const data = await response.json();
                const badge = document.getElementById('notificationBadge');
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            } catch (error) {
                console.error('Error checking notifications:', error);
            }
        }
        setInterval(checkNotifications, 60000);
        checkNotifications();
        // Auto-Refresh Dashboard
        async function refreshDashboard() {
            try {
                const year = document.getElementById('yearFilter').value;
                const response = await fetch(`?refresh_dashboard=1&year=${year}`);
                const data = await response.json();
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                document.getElementById('totalSales').textContent = `$${data.total_sales}`;
                document.getElementById('totalBookings').textContent = data.total_bookings;
                document.getElementById('totalUsers').textContent = data.total_users;
                document.getElementById('pendingActions').textContent = data.pending_actions;
                document.getElementById('totalCancellations').textContent = data.total_cancellations;
                document.getElementById('occupancyRate').textContent = `${data.occupancy_rate}%`;
                monthlyChart.data.labels = data.monthly_labels;
                monthlyChart.data.datasets[0].data = data.monthly_bookings;
                monthlyChart.update();
                salesChart.data.labels = data.monthly_labels;
                salesChart.data.datasets[0].data = data.monthly_sales;
                salesChart.update();
                yearlySalesChart.data.labels = data.yearly_labels;
                yearlySalesChart.data.datasets[0].data = data.yearly_sales;
                yearlySalesChart.update();
                const recentActivity = document.getElementById('recentActivity');
                recentActivity.innerHTML = '';
                if (data.recent_activities.length === 0) {
                    recentActivity.innerHTML = '<p>No recent activity found.</p>';
                } else {
                    data.recent_activities.forEach(activity => {
                        const div = document.createElement('div');
                        div.className = 'activity-item';
                        div.innerHTML = `
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="action">${activity.action}</span>
                                    <div class="details">
                                        By ${activity.username || 'Unknown User'}
                                        (Details: ${activity.details})
                                    </div>
                                </div>
                                <div class="timestamp">
                                    ${activity.created_at}
                                </div>
                            </div>
                        `;
                        recentActivity.appendChild(div);
                    });
                }
            } catch (error) {
                console.error('Error refreshing dashboard:', error);
            }
        }
        setInterval(refreshDashboard, 60000);
        refreshDashboard();
        // Full-Screen Chart Functionality
        document.querySelectorAll('.btn-fullscreen').forEach(button => {
            button.addEventListener('click', () => {
                const chartContainerId = button.getAttribute('data-chart');
                const chartContainer = document.getElementById(chartContainerId);
                
                if (!document.fullscreenElement) {
                    chartContainer.requestFullscreen().then(() => {
                        chartContainer.classList.add('fullscreen');
                        button.classList.add('active');
                        setTimeout(() => {
                            if (chartContainerId === 'monthlyBookingsChartContainer') {
                                monthlyChart.resize();
                            } else if (chartContainerId === 'monthlySalesChartContainer') {
                                salesChart.resize();
                            } else if (chartContainerId === 'yearlySalesChartContainer') {
                                yearlySalesChart.resize();
                            }
                        }, 100);
                    }).catch(err => {
                        console.error('Error entering fullscreen:', err);
                    });
                } else {
                    document.exitFullscreen().then(() => {
                        chartContainer.classList.remove('fullscreen');
                        button.classList.remove('active');
                        setTimeout(() => {
                            if (chartContainerId === 'monthlyBookingsChartContainer') {
                                monthlyChart.resize();
                            } else if (chartContainerId === 'monthlySalesChartContainer') {
                                salesChart.resize();
                            } else if (chartContainerId === 'yearlySalesChartContainer') {
                                yearlySalesChart.resize();
                            }
                        }, 100);
                    }).catch(err => {
                        console.error('Error exiting fullscreen:', err);
                    });
                }
            });
        });
        document.addEventListener('fullscreenchange', () => {
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach(container => {
                if (!document.fullscreenElement) {
                    container.classList.remove('fullscreen');
                    const button = container.querySelector('.btn-fullscreen');
                    if (button) button.classList.remove('active');
                    setTimeout(() => {
                        monthlyChart.resize();
                        salesChart.resize();
                        yearlySalesChart.resize();
                    }, 100);
                }
            });
        });
    </script>
</body>
</html>