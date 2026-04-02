<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['query']) || !isset($input['response'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, created_at)
        VALUES (:user_id, :action, :details, NOW())
    ");
    $stmt->execute([
        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        'action' => 'Chatbot Interaction',
        'details' => json_encode([
            'query' => substr($input['query'], 0, 255),
            'response' => substr($input['response'], 0, 255)
        ])
    ]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>