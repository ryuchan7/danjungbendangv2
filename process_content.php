<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $content_type = $_POST['content_type'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $file_path = null;

    if (isset($_FILES['file_path']) && $_FILES['file_path']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['file_path']['name']);
        $file_path = $upload_dir . $file_name;
        if (!move_uploaded_file($_FILES['file_path']['tmp_name'], $file_path)) {
            $_SESSION['error'] = "Failed to upload image.";
            header("Location: content_management.php");
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO content (content_type, title, description, file_path, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$content_type, $title, $description, $file_path, $_SESSION['user_id']]);
        $_SESSION['success'] = "Content added successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: content_management.php");
    exit;
}

if ($action === 'edit') {
    $content_id = $_POST['content_id'];
    $content_type = $_POST['content_type'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $file_path = null;

    // Check if a new file is uploaded
    if (isset($_FILES['file_path']) && $_FILES['file_path']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['file_path']['name']);
        $file_path = $upload_dir . $file_name;
        if (!move_uploaded_file($_FILES['file_path']['tmp_name'], $file_path)) {
            $_SESSION['error'] = "Failed to upload image.";
            header("Location: content_management.php");
            exit;
        }
        // Delete old image if it exists
        $stmt = $pdo->prepare("SELECT file_path FROM content WHERE content_id = ?");
        $stmt->execute([$content_id]);
        $old_file = $stmt->fetchColumn();
        if ($old_file && file_exists($old_file)) {
            unlink($old_file);
        }
    }

    try {
        if ($file_path) {
            $stmt = $pdo->prepare("UPDATE content SET content_type = ?, title = ?, description = ?, file_path = ?, updated_at = NOW() WHERE content_id = ? AND created_by = ?");
            $stmt->execute([$content_type, $title, $description, $file_path, $content_id, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE content SET content_type = ?, title = ?, description = ?, updated_at = NOW() WHERE content_id = ? AND created_by = ?");
            $stmt->execute([$content_type, $title, $description, $content_id, $_SESSION['user_id']]);
        }
        $_SESSION['success'] = "Content updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: content_management.php");
    exit;
}

if ($action === 'delete') {
    $content_id = $_GET['id'];
    try {
        // Delete old image if it exists
        $stmt = $pdo->prepare("SELECT file_path FROM content WHERE content_id = ?");
        $stmt->execute([$content_id]);
        $old_file = $stmt->fetchColumn();
        if ($old_file && file_exists($old_file)) {
            unlink($old_file);
        }
        $stmt = $pdo->prepare("DELETE FROM content WHERE content_id = ? AND created_by = ?");
        $stmt->execute([$content_id, $_SESSION['user_id']]);
        $_SESSION['success'] = "Content deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: content_management.php");
    exit;
}

header("Location: content_management.php");
exit;
?>