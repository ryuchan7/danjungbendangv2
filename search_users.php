<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Handle search
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = [];
try {
    $sql = "
        SELECT userID, username, email, full_name, phone, ic_no, role, created_at
        FROM users
    ";
    $params = [];
    if (!empty($search_term)) {
        $sql .= " WHERE username LIKE :search 
                  OR email LIKE :search 
                  OR full_name LIKE :search 
                  OR phone LIKE :search 
                  OR ic_no LIKE :search";
        $params[':search'] = '%' . $search_term . '%';
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['users' => $users, 'csrf_token' => $_SESSION['csrf_token']]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlspecialchars($e->getMessage())]);
}
?>