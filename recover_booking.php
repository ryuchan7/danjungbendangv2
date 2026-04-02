<?php
session_start();
require_once 'db_connect.php';

// Check if user is admin
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

// Check if booking_id is provided
if (!isset($_POST['booking_id']) || !is_numeric($_POST['booking_id'])) {
    $_SESSION['error_message'] = 'Invalid booking ID.';
    header('Location: bookings.php');
    exit;
}

try {
    $booking_id = $_POST['booking_id'];
    $stmt = $pdo->prepare("UPDATE booking SET deleted_at = NULL WHERE bookingID = ?");
    $stmt->execute([$booking_id]);

    $_SESSION['success_message'] = 'Booking recovered successfully.';
    header('Location: bookings.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error recovering booking: ' . $e->getMessage();
    header('Location: bookings.php');
    exit;
}
?>