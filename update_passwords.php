<?php
require_once 'db_connect.php';

try {
    // Fetch all users
    $stmt = $pdo->query("SELECT userID, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update each user's password to hashed version
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE userID = ?");
    foreach ($users as $user) {
        // Check if the password is already hashed (starts with $2y$ for bcrypt)
        if (!preg_match('/^\$2y\$/', $user['password'])) {
            $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
            $updateStmt->execute([$hashed_password, $user['userID']]);
            echo "Updated password for userID: {$user['userID']}<br>";
        }
    }
    echo "All passwords have been hashed.";
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?>