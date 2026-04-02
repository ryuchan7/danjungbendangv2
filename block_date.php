<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid CSRF token.';
    header('Location: bookings.php');
    exit;
}

// Validate input
$blocked_date = $_POST['blocked_date'] ?? '';
$reason = $_POST['reason'] ?? '';
$hslistID = 1; // Adjust if you support multiple homestays

if (empty($blocked_date) || !DateTime::createFromFormat('Y-m-d', $blocked_date)) {
    $_SESSION['error_message'] = 'Invalid date format.';
    header('Location: bookings.php');
    exit;
}

try {
    // Check if date is already blocked
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocked_dates WHERE hslistID = ? AND blocked_date = ?");
    $stmt->execute([$hslistID, $blocked_date]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error_message'] = 'This date is already blocked.';
        header('Location: bookings.php');
        exit;
    }

    // Check if date has an active booking
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM booking 
        WHERE hslistID = ? 
        AND booking_status NOT IN ('Cancelled', 'Rejected')
        AND ? BETWEEN checkIN AND DATE_SUB(checkOUT, INTERVAL 1 DAY)
    ");
    $stmt->execute([$hslistID, $blocked_date]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error_message'] = 'Cannot block this date as it has an active booking.';
        header('Location: bookings.php');
        exit;
    }

    // Insert blocked date
    $stmt = $pdo->prepare("INSERT INTO blocked_dates (hslistID, blocked_date, reason) VALUES (?, ?, ?)");
    $stmt->execute([$hslistID, $blocked_date, $reason]);

    $_SESSION['success_message'] = 'Date successfully blocked.';
    header('Location: bookings.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . htmlspecialchars($e->getMessage());
    header('Location: bookings.php');
    exit;
}
?>