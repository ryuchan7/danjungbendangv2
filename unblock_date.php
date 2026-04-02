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
$hslistID = 1; // Adjust if you support multiple homestays

if (empty($blocked_date) || !DateTime::createFromFormat('Y-m-d', $blocked_date)) {
    $_SESSION['error_message'] = 'Invalid date format.';
    header('Location: bookings.php');
    exit;
}

try {
    // Delete blocked date
    $stmt = $pdo->prepare("DELETE FROM blocked_dates WHERE hslistID = ? AND blocked_date = ?");
    $stmt->execute([$hslistID, $blocked_date]);

    $_SESSION['success_message'] = 'Date successfully unblocked.';
    header('Location: bookings.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . htmlspecialchars($e->getMessage());
    header('Location: bookings.php');
    exit;
}
?>