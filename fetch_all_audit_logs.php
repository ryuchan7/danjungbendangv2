<?php
session_start();
require_once 'db_connect.php';

// Verify CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit(json_encode(['error' => 'Invalid CSRF token']));
}

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized access']));
}

try {
    $sql = "
        SELECT al.log_id, al.action, al.details, al.created_at, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.userID
        ORDER BY al.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process details for JSON output
    foreach ($audit_logs as &$log) {
        $details = json_decode($log['details'], true);
        if (is_array($details)) {
            if (in_array($log['action'], ['Approved Booking', 'Cancel Booking', 'Delete Booking'])) {
                $log['details'] = json_encode([
                    'booking_id' => $details['booking_id'] ?? 'Unknown',
                    'customer_name' => $details['customer_name'] ?? getCustomerNameByBookingId($pdo, $details['booking_id'] ?? 0)
                ]);
            } elseif (in_array($log['action'], ['Add User', 'Edit User', 'Delete User'])) {
                $log['details'] = json_encode([
                    'user_id' => $details['user_id'] ?? 'Unknown',
                    'username' => $details['username'] ?? 'Unknown Username'
                ]);
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($audit_logs);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlspecialchars($e->getMessage())]);
}

function getCustomerNameByBookingId($pdo, $booking_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(u.full_name, b.full_name) AS customer_name
            FROM booking b
            LEFT JOIN users u ON b.userID = u.userID
            WHERE b.bookingID = :booking_id
        ");
        $stmt->execute(['booking_id' => $booking_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? ($result['customer_name'] ?? 'Unknown Customer') : 'Unknown Customer';
    } catch (PDOException $e) {
        error_log('Error fetching customer name: ' . $e->getMessage());
        return 'Unknown Customer';
    }
}
?>