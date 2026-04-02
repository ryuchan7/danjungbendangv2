<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: user_management.php');
    exit;
}

if (!isset($_POST['action'])) {
    $_SESSION['error_message'] = 'Invalid action.';
    header('Location: user_management.php');
    exit;
}

$action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);

try {
    $pdo->beginTransaction();

    if ($action === 'add') {
        // Validate required fields
        if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['role'])) {
            throw new Exception('All required fields must be filled.');
        }

        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = !empty($_POST['full_name']) ? filter_var($_POST['full_name'], FILTER_SANITIZE_STRING) : null;
        $phone = !empty($_POST['phone']) ? filter_var($_POST['phone'], FILTER_SANITIZE_STRING) : null;
        $ic_no = !empty($_POST['ic_no']) ? filter_var($_POST['ic_no'], FILTER_SANITIZE_STRING) : null;
        $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);

        // Validate role
        if (!in_array($role, ['admin', 'customer'])) {
            throw new Exception('Invalid role specified.');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
        $stmt->execute(['username' => $username, 'email' => $email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Username or email already exists.');
        }

        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, phone, ic_no, role, created_at)
            VALUES (:username, :email, :password, :full_name, :phone, :ic_no, :role, NOW())
        ");
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $full_name,
            'phone' => $phone,
            'ic_no' => $ic_no,
            'role' => $role
        ]);

        $user_id = $pdo->lastInsertId();
        $log_action = 'Add User';
        $log_details = ['user_id' => $user_id, 'username' => $username];
        $success_message = 'User added successfully.';

    } elseif ($action === 'edit') {
        if (!isset($_POST['user_id']) || !filter_var($_POST['user_id'], FILTER_VALIDATE_INT)) {
            throw new Exception('Invalid user ID.');
        }

        $user_id = (int)$_POST['user_id'];
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $full_name = !empty($_POST['full_name']) ? filter_var($_POST['full_name'], FILTER_SANITIZE_STRING) : null;
        $phone = !empty($_POST['phone']) ? filter_var($_POST['phone'], FILTER_SANITIZE_STRING) : null;
        $ic_no = !empty($_POST['ic_no']) ? filter_var($_POST['ic_no'], FILTER_SANITIZE_STRING) : null;
        $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);

        // Validate role
        if (!in_array($role, ['admin', 'customer'])) {
            throw new Exception('Invalid role specified.');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        // Check if username or email exists for other users
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM users 
            WHERE (username = :username OR email = :email) 
            AND userID != :user_id
        ");
        $stmt->execute(['username' => $username, 'email' => $email, 'user_id' => $user_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Username or email already exists.');
        }

        // Update user
        $params = [
            'user_id' => $user_id,
            'username' => $username,
            'email' => $email,
            'full_name' => $full_name,
            'phone' => $phone,
            'ic_no' => $ic_no,
            'role' => $role
        ];

        $sql = "
            UPDATE users 
            SET username = :username, 
                email = :email, 
                full_name = :full_name, 
                phone = :phone, 
                ic_no = :ic_no, 
                role = :role
        ";

        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql .= ", password = :password";
            $params['password'] = $password;
        }

        $sql .= " WHERE userID = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $log_action = 'Edit User';
        $log_details = ['user_id' => $user_id, 'username' => $username];
        $success_message = 'User updated successfully.';

    } elseif ($action === 'delete') {
        if (!isset($_POST['user_id']) || !filter_var($_POST['user_id'], FILTER_VALIDATE_INT)) {
            throw new Exception('Invalid user ID.');
        }

        $user_id = (int)$_POST['user_id'];

        // Prevent deleting the current admin
        if ($user_id == $_SESSION['user_id']) {
            throw new Exception('You cannot delete your own account.');
        }

        // Fetch username before deletion
        $stmt = $pdo->prepare("SELECT username FROM users WHERE userID = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found.');
        }

        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE userID = :user_id");
        $stmt->execute(['user_id' => $user_id]);

        $log_action = 'Delete User';
        $log_details = ['user_id' => $user_id, 'username' => $user['username']];
        $success_message = 'User deleted successfully.';
    } else {
        throw new Exception('Invalid action specified.');
    }

    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, created_at)
        VALUES (:user_id, :action, :details, NOW())
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'action' => $log_action,
        'details' => json_encode($log_details)
    ]);

    $pdo->commit();
    $_SESSION['success_message'] = $success_message;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = htmlspecialchars($e->getMessage());
}

header('Location: user_management.php');
exit;
?>