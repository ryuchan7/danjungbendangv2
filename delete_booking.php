<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Unauthorized access.';
    header('Location: index.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid CSRF token.';
    header('Location: bookings.php');
    exit;
}

// Validate booking ID
if (!isset($_POST['booking_id']) || !filter_var($_POST['booking_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = 'Invalid booking ID.';
    header('Location: bookings.php');
    exit;
}

$booking_id = $_POST['booking_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Get customer name for audit log
    $stmt = $pdo->prepare("
        SELECT COALESCE(u.full_name, b.full_name) AS customer_name
        FROM booking b
        LEFT JOIN users u ON b.userID = u.userID
        WHERE b.bookingID = :booking_id
    ");
    $stmt->execute(['booking_id' => $booking_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $result ? ($result['customer_name'] ?? 'Unknown Customer') : 'Unknown Customer';

    // Log the delete action to audit_logs
    $details = [
        'booking_id' => $booking_id,
        'customer_name' => $customer_name
    ];
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, created_at)
        VALUES (:user_id, :action, :details, NOW())
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'action' => 'Delete Booking',
        'details' => json_encode($details)
    ]);

    // Delete the booking
    $stmt = $pdo->prepare("DELETE FROM booking WHERE bookingID = :booking_id");
    $stmt->execute(['booking_id' => $booking_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = 'Booking deleted successfully.';
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log('Error deleting booking: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Failed to delete booking: ' . htmlspecialchars($e->getMessage());
}

// Redirect back to bookings.php
header('Location: bookings.php');
exit;
?>