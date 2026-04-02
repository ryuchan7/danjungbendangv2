<?php
session_start();
require_once 'db_connect.php';

// Prevent infinite redirect loop
if (isset($_SESSION['redirect_count'])) {
    $_SESSION['redirect_count']++;
    if ($_SESSION['redirect_count'] > 5) {
        die('Error: Too many redirects. Please check your session or cookie settings.');
    }
} else {
    $_SESSION['redirect_count'] = 1;
}

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    unset($_SESSION['redirect_count']); // Reset redirect count
    header('Location: index.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set('Asia/Kuala_Lumpur');

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$feedback_data = [];
$customer_names = [];

// Clear session messages after displaying
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch all customer names from booking table
try {
    $stmt = $pdo->query("
        SELECT COALESCE(u.full_name, b.full_name) AS customer_name
        FROM booking b
        LEFT JOIN users u ON b.userID = u.userID
        WHERE COALESCE(u.full_name, b.full_name) IS NOT NULL
        ORDER BY customer_name
    ");
    $customer_names = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Create feedback entries for all customer names if not already in feedback table
    foreach ($customer_names as $name) {
        $stmt = $pdo->prepare("SELECT id FROM feedback WHERE customer_name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() === false) {
            // Fix: Include a default empty string for the review column
            $stmt = $pdo->prepare("INSERT INTO feedback (customer_name, review, created_at) VALUES (?, '', NOW())");
            $stmt->execute([$name]);
        }
    }

    // Fetch all feedback data
    $stmt = $pdo->query("SELECT * FROM feedback ORDER BY created_at ASC");
    $feedback_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Error fetching or creating feedback: ' . htmlspecialchars($e->getMessage());
    error_log($e->getMessage()); // Log error for debugging
}

// Handle add feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $customer_name = filter_var($_POST['customer_name'], FILTER_SANITIZE_STRING);
        $customer_name = trim($customer_name);
        $review = filter_var($_POST['review'], FILTER_SANITIZE_STRING);
        $proof_file = $_FILES['proof_file'];

        if (empty($customer_name) || empty($review)) {
            $error_message = 'Customer name and review are required.';
        } elseif ($proof_file['error'] === UPLOAD_ERR_NO_FILE || $proof_file['error'] === UPLOAD_ERR_OK) {
            $proof_path = '';
            if ($proof_file['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'feedback/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $proof_path = $upload_dir . uniqid() . '_' . basename($proof_file['name']);
                if (!move_uploaded_file($proof_file['tmp_name'], $proof_path)) {
                    $error_message = 'Error uploading proof file.';
                }
            }

            if (empty($error_message)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO feedback (customer_name, review, proof_path, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$customer_name, $review, $proof_path]);
                    $_SESSION['success_message'] = 'Feedback added successfully.';
                    unset($_SESSION['redirect_count']);
                    header('Location: feedback_management.php');
                    exit;
                } catch (PDOException $e) {
                    $error_message = 'Error adding feedback: ' . htmlspecialchars($e->getMessage());
                    error_log($e->getMessage());
                }
            }
        } else {
            $error_message = 'Error uploading proof file.';
        }
    }
}

// Handle edit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_feedback']) && isset($_POST['feedback_id'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $feedback_id = filter_var($_POST['feedback_id'], FILTER_SANITIZE_NUMBER_INT);
        $customer_name = filter_var($_POST['customer_name'], FILTER_SANITIZE_STRING);
        $customer_name = trim($customer_name);
        $review = filter_var($_POST['review'], FILTER_SANITIZE_STRING);
        $proof_file = $_FILES['proof_file'];
        $old_proof_path = $_POST['old_proof_path'] ?? '';

        $proof_path = $old_proof_path;
        if ($proof_file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'feedback/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $proof_path = $upload_dir . uniqid() . '_' . basename($proof_file['name']);
            if (move_uploaded_file($proof_file['tmp_name'], $proof_path) && !empty($old_proof_path) && file_exists($old_proof_path)) {
                unlink($old_proof_path);
            }
        }

        try {
            $stmt = $pdo->prepare("UPDATE feedback SET customer_name = ?, review = ?, proof_path = ? WHERE id = ?");
            $stmt->execute([$customer_name, $review, $proof_path ?: null, $feedback_id]);
            $_SESSION['success_message'] = 'Feedback updated successfully.';
            unset($_SESSION['redirect_count']);
            header('Location: feedback_management.php');
            exit;
        } catch (PDOException $e) {
            $error_message = 'Error updating feedback: ' . htmlspecialchars($e->getMessage());
            error_log($e->getMessage());
        }
    }
}

// Handle delete feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback']) && isset($_POST['feedback_id'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $feedback_id = filter_var($_POST['feedback_id'], FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $pdo->prepare("SELECT proof_path FROM feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($feedback && !empty($feedback['proof_path']) && file_exists($feedback['proof_path'])) {
                unlink($feedback['proof_path']);
            }
            $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            $_SESSION['success_message'] = 'Feedback deleted successfully.';
            unset($_SESSION['redirect_count']);
            header('Location: feedback_management.php');
            exit;
        } catch (PDOException $e) {
            $error_message = 'Error deleting feedback: ' . htmlspecialchars($e->getMessage());
            error_log($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Customer Feedback - Cozy Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 220px;
            background: #f8f9fa;
            padding: 20px;
            border-right: 1px solid #dee2e6;
        }
        .sidebar .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: #26a69a;
            margin-bottom: 30px;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: #6c757d;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #26a69a;
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            flex: 1;
            padding: 30px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header h2 {
            color: #333;
            font-weight: 600;
            margin: 0;
        }
        .btn-logout, .btn-add-feedback {
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            color: #fff;
        }
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }
        .btn-add-feedback {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .btn-add-feedback:hover {
            background: linear-gradient(135deg, #0056b3, #007bff);
        }
        .section {
            margin-bottom: 40px;
        }
        .section h3 {
            color: #26a69a;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-bottom: 40px;
            max-height: 600px;
            overflow-y: auto;
        }
        .table th {
            background: #26a69a;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .proof-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }
        .proof-image:hover {
            transform: scale(1.05);
        }
        .search-container {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-container input {
            max-width: 300px;
        }
        .btn-view {
            background: linear-gradient(135deg, #17a2b8, #117a8b);
            color: #fff;
            border: none;
        }
        .btn-view:hover {
            background: linear-gradient(135deg, #117a8b, #17a2b8);
        }
        .btn-edit {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #fff;
            border: none;
        }
        .btn-edit:hover {
            background: linear-gradient(135deg, #e0a800, #ffc107);
        }
        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: none;
        }
        .btn-delete:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }
        .modal-body .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .modal-body .detail-item:last-child {
            border-bottom: none;
        }
        .modal-body .detail-label {
            font-weight: 600;
            color: #26a69a;
            flex: 1;
        }
        .modal-body .detail-value {
            flex: 2;
            text-align: left;
        }
        .form-label {
            color: #26a69a;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 8px;
        }
        .editable-cell {
            position: relative;
        }
        .editable-cell input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            display: none;
        }
        .editable-cell.editing input {
            display: block;
        }
        .editable-cell.editing span {
            display: none;
        }
        .save-btn, .cancel-btn {
            display: none;
            margin-left: 5px;
            padding: 2px 8px;
            font-size: 0.8rem;
        }
        .editable-cell.editing .save-btn, .editable-cell.editing .cancel-btn {
            display: inline-block;
        }
        .pagination-container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">D'Anjung Bendang Homestay</div>
            <a href="admin_dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="user_management.php" class="nav-link">
                <i class="fas fa-users"></i> User Management
            </a>
            <a href="bookings.php" class="nav-link">
                <i class="fas fa-book"></i> All Bookings
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <a href="audit_logs.php" class="nav-link">
                <i class="fas fa-history"></i> Audit Logs
            </a>
            <a href="feedback_management.php" class="nav-link active">
                <i class="fas fa-comment-dots"></i> Customer Feedback
            </a>
               <a href="worker_salary.php" class="nav-link">
                <i class="fas fa-money-bill-wave"></i> Worker Salary
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Customer Feedback</h2>
                <div>
                    <button class="btn btn-add-feedback" data-bs-toggle="modal" data-bs-target="#addFeedbackModal"><i class="fas fa-plus me-2"></i>Add Feedback</button>
                    <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Add Feedback Modal -->
            <div class="modal fade" id="addFeedbackModal" tabindex="-1" aria-labelledby="addFeedbackModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addFeedbackModalLabel">Add New Feedback</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="" enctype="multipart/form-data" class="edit-form">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="customer_name" class="form-label"><i class="fas fa-user-tag me-2"></i>Customer Name</label>
                                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                            <div class="mt-2">
                                                <label for="customer_select" class="form-label text-muted">Or select from list:</label>
                                                <select class="form-select" id="customer_select" name="customer_select" onchange="document.getElementById('customer_name').value = this.value">
                                                    <option value="">Select a Customer</option>
                                                    <?php foreach ($customer_names as $name): ?>
                                                        <option value="<?php echo htmlspecialchars($name); ?>">
                                                            <?php echo htmlspecialchars($name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="review" class="form-label"><i class="fas fa-comment-dots me-2"></i>Review</label>
                                            <textarea class="form-control" id="review" name="review" rows="4" required></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="proof_file" class="form-label"><i class="fas fa-file-image me-2"></i>Upload Picture Proof (Optional)</label>
                                            <input type="file" class="form-control" id="proof_file" name="proof_file" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
                                <button type="submit" name="submit_feedback" class="btn btn-primary"><i class="fas fa-save me-2"></i>Add Feedback</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Feedback Table -->
            <?php if (!empty($feedback_data)): ?>
                <div class="section">
                    <h3>Feedback List</h3>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by customer name...">
                        <span>Total Feedback: <span id="totalFeedback"><?php echo count($feedback_data); ?></span></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="feedbackTable">
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Review</th>
                                    <th>Picture Proof</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="feedbackTableBody">
                                <?php foreach ($feedback_data as $feedback): ?>
                                    <tr class="feedback-row" data-id="<?php echo $feedback['id']; ?>">
                                        <td class="editable-cell" data-original-name="<?php echo htmlspecialchars($feedback['customer_name']); ?>">
                                            <span><?php echo htmlspecialchars($feedback['customer_name']); ?></span>
                                            <input type="text" value="<?php echo htmlspecialchars($feedback['customer_name']); ?>">
                                            <button class="save-btn btn btn-success btn-sm">Save</button>
                                            <button class="cancel-btn btn btn-secondary btn-sm">Cancel</button>
                                        </td>
                                        <td><?php echo htmlspecialchars($feedback['review'] ?? ''); ?></td>
                                        <td>
                                            <?php if (!empty($feedback['proof_path']) && preg_match('/\.(jpg|jpeg|png)$/', $feedback['proof_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($feedback['proof_path']); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($feedback['proof_path']); ?>" alt="Proof" class="proof-image">
                                                </a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars((new DateTime($feedback['created_at']))->format('d-m-Y H:i')); ?></td>
                                        <td>
                                            <button class="btn btn-view action-btn" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $feedback['id']; ?>"><i class="fas fa-eye"></i> View</button>
                                            <button class="btn btn-edit action-btn" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $feedback['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="btn btn-delete action-btn" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $feedback['id']; ?>"><i class="fas fa-trash"></i> Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination-container" id="paginationContainer"></div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No feedback available.</div>
            <?php endif; ?>

            <!-- View, Edit, and Delete Modals -->
            <?php foreach ($feedback_data as $feedback): ?>
                <!-- View Modal -->
                <div class="modal fade" id="viewModal<?php echo $feedback['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $feedback['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="viewModalLabel<?php echo $feedback['id']; ?>">Feedback Details #<?php echo $feedback['id']; ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user-tag me-2"></i>Customer Name:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($feedback['customer_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-comment-dots me-2"></i>Review:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($feedback['review'] ?? ''); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-file-image me-2"></i>Picture Proof:</span>
                                    <span class="detail-value">
                                        <?php if (!empty($feedback['proof_path']) && preg_match('/\.(jpg|jpeg|png)$/', $feedback['proof_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($feedback['proof_path']); ?>" target="_blank">
                                                <img src="<?php echo htmlspecialchars($feedback['proof_path']); ?>" alt="Proof" class="proof-image">
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-clock me-2"></i>Date Added:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars((new DateTime($feedback['created_at']))->format('d-m-Y H:i')); ?></span>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
                                <button class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $feedback['id']; ?>" data-bs-dismiss="modal"><i class="fas fa-edit me-2"></i>Edit Feedback</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?php echo $feedback['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $feedback['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editModalLabel<?php echo $feedback['id']; ?>">Edit Feedback #<?php echo $feedback['id']; ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="" enctype="multipart/form-data" class="edit-form">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <input type="hidden" name="old_proof_path" value="<?php echo htmlspecialchars($feedback['proof_path'] ?? ''); ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="customer_name_<?php echo $feedback['id']; ?>" class="form-label"><i class="fas fa-user-tag me-2"></i>Customer Name</label>
                                                <input type="text" class="form-control" id="customer_name_<?php echo $feedback['id']; ?>" name="customer_name" value="<?php echo htmlspecialchars($feedback['customer_name']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="review_<?php echo $feedback['id']; ?>" class="form-label"><i class="fas fa-comment-dots me-2"></i>Review</label>
                                                <textarea class="form-control" id="review_<?php echo $feedback['id']; ?>" name="review" rows="4"><?php echo htmlspecialchars($feedback['review'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="proof_file_<?php echo $feedback['id']; ?>" class="form-label"><i class="fas fa-file-image me-2"></i>Upload Picture Proof (Optional)</label>
                                                <input type="file" class="form-control" id="proof_file_<?php echo $feedback['id']; ?>" name="proof_file" accept="image/*">
                                                <?php if (!empty($feedback['proof_path']) && preg_match('/\.(jpg|jpeg|png)$/', $feedback['proof_path'])): ?>
                                                    <div class="mt-2">
                                                        <p>Current Proof:</p>
                                                        <a href="<?php echo htmlspecialchars($feedback['proof_path']); ?>" target="_blank">
                                                            <img src="<?php echo htmlspecialchars($feedback['proof_path']); ?>" alt="Current Proof" class="proof-image">
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
                                    <button type="submit" name="edit_feedback" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal<?php echo $feedback['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $feedback['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteModalLabel<?php echo $feedback['id']; ?>">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete feedback from <?php echo htmlspecialchars($feedback['customer_name']); ?>? This action cannot be undone.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <button type="submit" name="delete_feedback" class="btn btn-delete"><i class="fas fa-trash me-2"></i>Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search and Pagination for Feedback
            const feedbackTableBody = document.getElementById('feedbackTableBody');
            const searchInput = document.getElementById('searchInput');
            const paginationContainer = document.getElementById('paginationContainer');
            const totalFeedbackSpan = document.getElementById('totalFeedback');
            const rows = Array.from(document.getElementsByClassName('feedback-row'));
            const rowsPerPage = 10;
            let currentPage = 1;
            let filteredRows = rows;

            function filterRows() {
                const searchTerm = searchInput.value.toLowerCase().trim();

                filteredRows = rows.filter(row => {
                    const customerName = row.cells[0].textContent.toLowerCase();
                    return customerName.includes(searchTerm);
                });

                totalFeedbackSpan.textContent = filteredRows.length;
                currentPage = 1;
                renderTable();
                renderPagination();
            }

            function renderTable() {
                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                const paginatedRows = filteredRows.slice(start, end);

                feedbackTableBody.innerHTML = '';
                paginatedRows.forEach(row => {
                    feedbackTableBody.appendChild(row);
                });
            }

            function renderPagination() {
                const pageCount = Math.ceil(filteredRows.length / rowsPerPage);
                paginationContainer.innerHTML = '';

                if (pageCount <= 1) return;

                const pagination = document.createElement('nav');
                pagination.innerHTML = `
                    <ul class="pagination justify-content-center">
                        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">«</span>
                            </a>
                        </li>
                        ${Array.from({ length: pageCount }, (_, i) => i + 1).map(page => `
                            <li class="page-item ${currentPage === page ? 'active' : ''}">
                                <a class="page-link" href="#">${page}</a>
                            </li>
                        `).join('')}
                        <li class="page-item ${currentPage === pageCount ? 'disabled' : ''}">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">»</span>
                            </a>
                        </li>
                    </ul>
                `;
                paginationContainer.appendChild(pagination);

                pagination.querySelectorAll('.page-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const parentLi = this.parentElement;
                        if (parentLi.classList.contains('disabled')) return;

                        if (this.getAttribute('aria-label') === 'Previous') {
                            currentPage--;
                        } else if (this.getAttribute('aria-label') === 'Next') {
                            currentPage++;
                        } else {
                            currentPage = parseInt(this.textContent);
                        }
                        renderTable();
                        renderPagination();
                        window.scrollTo({ top: document.getElementById('feedbackTable').offsetTop - 100, behavior: 'smooth' });
                    });
                });
            }

            // Inline editing for customer name
            document.querySelectorAll('.editable-cell').forEach(cell => {
                const span = cell.querySelector('span');
                const input = cell.querySelector('input');
                const saveBtn = cell.querySelector('.save-btn');
                const cancelBtn = cell.querySelector('.cancel-btn');
                const originalName = cell.getAttribute('data-original-name');

                span.addEventListener('click', function() {
                    cell.classList.add('editing');
                    input.value = originalName;
                    input.focus();
                });

                cancelBtn.addEventListener('click', function() {
                    cell.classList.remove('editing');
                    input.value = originalName;
                });

                saveBtn.addEventListener('click', function() {
                    const newName = input.value.trim();
                    if (newName && newName !== originalName) {
                        const feedbackId = cell.closest('tr').getAttribute('data-id');
                        const formData = new FormData();
                        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                        formData.append('feedback_id', feedbackId);
                        formData.append('customer_name', newName);
                        formData.append('edit_feedback', '1');

                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(() => {
                            span.textContent = newName;
                            cell.setAttribute('data-original-name', newName);
                            cell.classList.remove('editing');
                        })
                        .catch(error => console.error('Error:', error));
                    } else {
                        cell.classList.remove('editing');
                    }
                });

                input.addEventListener('blur', function() {
                    if (cell.classList.contains('editing')) {
                        cancelBtn.click();
                    }
                });

                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        saveBtn.click();
                    }
                });
            });

            searchInput.addEventListener('input', filterRows);
            renderTable();
            renderPagination();
        });
    </script>
</body>
</html>