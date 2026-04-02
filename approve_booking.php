<?php
session_start();
require_once 'db_connect.php';

// Restrict to admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Unauthorized access.';
    header('Location: index.php');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid CSRF token.';
    header('Location: bookings.php');
    exit;
}

// Validate booking ID
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['booking_id']) || !filter_var($_POST['booking_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = 'Invalid request or booking ID.';
    header('Location: bookings.php');
    exit;
}

$booking_id = (int)$_POST['booking_id'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update booking status
    $stmt = $pdo->prepare("
        UPDATE booking 
        SET booking_status = 'Confirmed', 
            payment_status = 'Completed'
        WHERE bookingID = :booking_id
    ");
    $stmt->execute(['booking_id' => $booking_id]);

    // Fetch booking details for audit log
    $stmt = $pdo->prepare("
        SELECT COALESCE(u.full_name, b.full_name) AS customer_name
        FROM booking b
        LEFT JOIN users u ON b.userID = u.userID
        WHERE b.bookingID = :booking_id
    ");
    $stmt->execute(['booking_id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new PDOException('Booking not found.');
    }

    // Log approval in audit_logs
    $details = json_encode([
        'booking_id' => $booking_id,
        'customer_name' => $booking['customer_name'] ?? 'Guest'
    ]);
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, created_at)
        VALUES (:user_id, :action, :details, NOW())
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'action' => 'Approved Booking',
        'details' => $details
    ]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = 'Booking approved successfully!';
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error approving booking: ' . htmlspecialchars($e->getMessage());
}

header('Location: bookings.php'); // Redirect to bookings.php instead of admin_dashboard.php
exit;
?>