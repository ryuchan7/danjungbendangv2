<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: admin_dashboard.php');
    exit;
}

if (!isset($_POST['booking_id']) || !filter_var($_POST['booking_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = 'Invalid booking ID.';
    header('Location: admin_dashboard.php');
    exit;
}

$booking_id = (int)$_POST['booking_id'];

try {
    $pdo->beginTransaction();

    // Update booking status
    $stmt = $pdo->prepare("UPDATE booking SET booking_status = 'Cancelled' WHERE bookingID = :booking_id");
    $stmt->execute(['booking_id' => $booking_id]);

    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, created_at)
        VALUES (:user_id, 'Cancel Booking', :details, NOW())
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'details' => json_encode(['booking_id' => $booking_id])
    ]);

    $pdo->commit();
    $_SESSION['success_message'] = 'Booking cancelled successfully.';
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error cancelling booking: ' . htmlspecialchars($e->getMessage());
}

header('Location: admin_dashboard.php');
exit;
?>