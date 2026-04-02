<?php
ob_start();
session_start();
require_once 'db_connect.php';

// Configuration
define('TIMEZONE', 'UTC');
define('LOG_FILE', __DIR__ . '/logs/error.log');
define('UPLOAD_DIR', __DIR__ . '/images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('MAX_DIMENSIONS', ['width' => 1920, 'height' => 1080]);

// Restrict access to admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Session timeout (30 minutes)
$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
$_SESSION['last_activity'] = time();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Handle content management actions
$success_message = '';
$error_message = '';
$allowed_types = ['carousel', 'gallery', 'description'];

// Validate and process file uploads
function validateAndUploadFile($file, $user_id) {
    if ($file['error'] !== UPLOAD_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds 5MB limit.');
    }
    if (!in_array($file['type'], ALLOWED_TYPES)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
    }
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        throw new Exception('Invalid image file.');
    }
    if ($image_info[0] > MAX_DIMENSIONS['width'] || $image_info[1] > MAX_DIMENSIONS['height']) {
        throw new Exception('Image dimensions exceed ' . MAX_DIMENSIONS['width'] . 'x' . MAX_DIMENSIONS['height'] . '.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'content_' . $user_id . '_' . time() . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to move uploaded file.');
    }
    return 'images/' . $filename;
}

// Log audit entry
function logAudit($pdo, $user_id, $action, $details) {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (:user_id, :action, :details, NOW())");
        $stmt->execute([
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details
        ]);
    } catch (PDOException $e) {
        error_log('Audit log error: ' . $e->getMessage(), 3, LOG_FILE);
    }
}

// Add new content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_content'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Security error: Invalid CSRF token.';
    } else {
        try {
            $content_type = $_POST['content_type'] ?? '';
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $text_content = trim($_POST['text_content'] ?? '');
            $user_id = $_SESSION['user_id'];

            if (!in_array($content_type, $allowed_types)) {
                throw new Exception('Invalid content type.');
            }
            if (empty($title)) {
                throw new Exception('Title is required.');
            }
            if ($content_type === 'description' && empty($text_content)) {
                throw new Exception('Text content is required for description.');
            }
            if (in_array($content_type, ['carousel', 'gallery']) && empty($_FILES['image']['name'])) {
                throw new Exception('Image is required for carousel or gallery.');
            }

            $file_path = null;
            if (!empty($_FILES['image']['name'])) {
                $file_path = validateAndUploadFile($_FILES['image'], $user_id);
            }

            if ($content_type === 'description') {
                // Check if description already exists
                $stmt = $pdo->query("SELECT COUNT(*) FROM content WHERE content_type = 'description'");
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('A description already exists. Please edit the existing one.');
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO content (content_type, title, description, file_path, text_content, created_by, created_at, updated_at)
                VALUES (:content_type, :title, :description, :file_path, :text_content, :created_by, NOW(), NOW())
            ");
            $stmt->execute([
                'content_type' => $content_type,
                'title' => $title,
                'description' => $description,
                'file_path' => $file_path,
                'text_content' => $text_content,
                'created_by' => $user_id
            ]);

            logAudit($pdo, $user_id, 'Content Added', "Added $content_type content: $title");
            $success_message = 'Content added successfully.';
            header('Location: content_management.php');
            exit;
        } catch (Exception $e) {
            error_log('Add content error: ' . $e->getMessage(), 3, LOG_FILE);
            $error_message = $e->getMessage();
        }
    }
}

// Edit content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_content'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Security error: Invalid CSRF token.';
    } else {
        try {
            $content_id = (int)($_POST['content_id'] ?? 0);
            $content_type = $_POST['content_type'] ?? '';
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $text_content = trim($_POST['text_content'] ?? '');
            $user_id = $_SESSION['user_id'];

            if ($content_id <= 0) {
                throw new Exception('Invalid content ID.');
            }
            if (!in_array($content_type, $allowed_types)) {
                throw new Exception('Invalid content type.');
            }
            if (empty($title)) {
                throw new Exception('Title is required.');
            }
            if ($content_type === 'description' && empty($text_content)) {
                throw new Exception('Text content is required for description.');
            }

            $file_path = null;
            if (!empty($_FILES['image']['name'])) {
                $file_path = validateAndUploadFile($_FILES['image'], $user_id);
                // Delete old image if exists
                $stmt = $pdo->prepare("SELECT file_path FROM content WHERE content_id = :content_id");
                $stmt->execute(['content_id' => $content_id]);
                $old_file = $stmt->fetchColumn();
                if ($old_file && file_exists(__DIR__ . '/' . $old_file)) {
                    unlink(__DIR__ . '/' . $old_file);
                }
            }

            $stmt = $pdo->prepare("
                UPDATE content
                SET content_type = :content_type, title = :title, description = :description,
                    file_path = COALESCE(:file_path, file_path), text_content = :text_content,
                    updated_at = NOW()
                WHERE content_id = :content_id AND created_by = :created_by
            ");
            $stmt->execute([
                'content_type' => $content_type,
                'title' => $title,
                'description' => $description,
                'file_path' => $file_path,
                'text_content' => $text_content,
                'content_id' => $content_id,
                'created_by' => $user_id
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Content not found or you lack permission to edit.');
            }

            logAudit($pdo, $user_id, 'Content Edited', "Edited $content_type content ID: $content_id");
            $success_message = 'Content updated successfully.';
            header('Location: content_management.php');
            exit;
        } catch (Exception $e) {
            error_log('Edit content error: ' . $e->getMessage(), 3, LOG_FILE);
            $error_message = $e->getMessage();
        }
    }
}

// Delete content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_content'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Security error: Invalid CSRF token.';
    } else {
        try {
            $content_id = (int)($_POST['content_id'] ?? 0);
            $user_id = $_SESSION['user_id'];

            if ($content_id <= 0) {
                throw new Exception('Invalid content ID.');
            }

            // Fetch content to delete file
            $stmt = $pdo->prepare("SELECT file_path, content_type, title FROM content WHERE content_id = :content_id AND created_by = :created_by");
            $stmt->execute(['content_id' => $content_id, 'created_by' => $user_id]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$content) {
                throw new Exception('Content not found or you lack permission to delete.');
            }

            // Delete file if exists
            if ($content['file_path'] && file_exists(__DIR__ . '/' . $content['file_path'])) {
                unlink(__DIR__ . '/' . $content['file_path']);
            }

            // Delete content
            $stmt = $pdo->prepare("DELETE FROM content WHERE content_id = :content_id AND created_by = :created_by");
            $stmt->execute(['content_id' => $content_id, 'created_by' => $user_id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Failed to delete content.');
            }

            logAudit($pdo, $user_id, 'Content Deleted', "Deleted {$content['content_type']} content: {$content['title']}");
            $success_message = 'Content deleted successfully.';
            header('Location: content_management.php');
            exit;
        } catch (Exception $e) {
            error_log('Delete content error: ' . $e->getMessage(), 3, LOG_FILE);
            $error_message = $e->getMessage();
        }
    }
}

// Fetch all content
try {
    $stmt = $pdo->query("
        SELECT c.content_id, c.content_type, c.title, c.description, c.file_path, c.text_content, c.created_at, u.username
        FROM content c
        LEFT JOIN users u ON c.created_by = u.userID
        ORDER BY c.created_at DESC
    ");
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch content error: ' . $e->getMessage(), 3, LOG_FILE);
    $error_message = 'Failed to load content. Please try again.';
    $contents = [];
}

// Fetch username
try {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE userID = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $username = $stmt->fetch(PDO::FETCH_ASSOC)['username'] ?? 'Admin';
} catch (PDOException $e) {
    error_log('Fetch username error: ' . $e->getMessage(), 3, LOG_FILE);
    $error_message = 'Error fetching username.';
    $username = 'Admin';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Content Management - D'Anjung Bendang Homestay</title>
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
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
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
            position: sticky;
            top: 0;
            height: 100vh;
        }

        body.dark-mode .sidebar {
            background: #2c2c2c;
            border-right: 1px solid #444;
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

        body.dark-mode .sidebar .nav-link {
            color: #adb5bd;
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

        body.dark-mode .main-content {
            background: #222;
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

        body.dark-mode .header h2 {
            color: #e0e0e0;
        }

        .welcome-message {
            color: #26a69a;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
        }

        .section {
            margin-bottom: 40px;
        }

        .section h3 {
            color: #26a69a;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .content-table img {
            max-width: 100px;
            border-radius: 8px;
        }

        .content-table .text-content {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .modal-content {
            border-radius: 12px;
        }

        body.dark-mode .modal-content {
            background: #333;
            color: #e0e0e0;
        }

        .form-control, .form-select {
            border-radius: 8px;
        }

        body.dark-mode .form-control, body.dark-mode .form-select {
            background: #444;
            color: #e0e0e0;
            border: 1px solid #555;
        }

        .btn-primary {
            background: #26a69a;
            border: none;
            border-radius: 20px;
        }

        .btn-primary:hover {
            background: #2dd4bf;
        }

        .btn-danger {
            border-radius: 20px;
        }

        .btn-warning {
            border-radius: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }
            .wrapper {
                flex-direction: column;
            }
            .main-content {
                padding: 15px;
            }
            .content-table img {
                max-width: 80px;
            }
            .content-table .text-content {
                max-width: 150px;
            }
        }
    </style>
</head>
<body class="light-mode">
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" role="navigation" aria-label="Main navigation">
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
            <a href="content_management.php" class="nav-link active" aria-current="page">
                <i class="fas fa-image"></i> Content Management
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h2>Content Management</h2>
                    <p class="welcome-message">
                        <?php
                        $hour = date('H');
                        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
                        echo "$greeting, " . htmlspecialchars($username) . "!";
                        ?>
                    </p>
                </div>
                <div class="d-flex align-items-center">
                    <button id="theme-toggle" class="btn btn-outline-secondary me-2" aria-label="Toggle dark mode">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a href="logout.php" class="btn btn-logout" aria-label="Logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Messages -->
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

            <!-- Add Content Form -->
            <div class="section">
                <h3>Add New Content</h3>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="content_type" class="form-label">Content Type</label>
                            <select name="content_type" id="content_type" class="form-select" required>
                                <option value="carousel">Carousel Image</option>
                                <option value="gallery">Gallery Image</option>
                                <option value="description">Homestay Description</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" name="title" id="title" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-4">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <input type="text" name="description" id="description" class="form-control" maxlength="500">
                        </div>
                        <div class="col-12 image-field">
                            <label for="image" class="form-label">Image (Required for Carousel/Gallery)</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="col-12 text-field d-none">
                            <label for="text_content" class="form-label">Text Content (Required for Description)</label>
                            <textarea name="text_content" id="text_content" class="form-control" rows="5"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_content" class="btn btn-primary">Add Content</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Content List -->
            <div class="section">
                <h3>Existing Content</h3>
                <div class="table-responsive">
                    <table class="table table-hover content-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Image</th>
                                <th>Text Content</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($contents)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No content found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contents as $content): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(ucfirst($content['content_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($content['title']); ?></td>
                                        <td><?php echo htmlspecialchars($content['description'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($content['file_path']): ?>
                                                <img src="<?php echo htmlspecialchars($content['file_path']); ?>" alt="<?php echo htmlspecialchars($content['title']); ?>">
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-content"><?php echo htmlspecialchars($content['text_content'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($content['username'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($content['created_at']); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm edit-btn" 
                                                    data-id="<?php echo $content['content_id']; ?>"
                                                    data-type="<?php echo htmlspecialchars($content['content_type']); ?>"
                                                    data-title="<?php echo htmlspecialchars($content['title']); ?>"
                                                    data-description="<?php echo htmlspecialchars($content['description'] ?? ''); ?>"
                                                    data-text="<?php echo htmlspecialchars($content['text_content'] ?? ''); ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form action="" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="content_id" value="<?php echo $content['content_id']; ?>">
                                                <button type="submit" name="delete_content" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this content?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Content Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Content</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="content_id" id="edit_content_id">
                        <div class="mb-3">
                            <label for="edit_content_type" class="form-label">Content Type</label>
                            <select name="content_type" id="edit_content_type" class="form-select" required>
                                <option value="carousel">Carousel Image</option>
                                <option value="gallery">Gallery Image</option>
                                <option value="description">Homestay Description</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description (Optional)</label>
                            <input type="text" name="description" id="edit_description" class="form-control" maxlength="500">
                        </div>
                        <div class="mb-3 edit-image-field">
                            <label for="edit_image" class="form-label">Replace Image (Optional)</label>
                            <input type="file" name="image" id="edit_image" class="form-control" accept="image/jpeg,image/png,image/gif">
                        </div>
                        <div class="mb-3 edit-text-field d-none">
                            <label for="edit_text_content" class="form-label">Text Content (Required for Description)</label>
                            <textarea name="text_content" id="edit_text_content" class="form-control" rows="5"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="edit_content" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const icon = themeToggle.querySelector('i');
            icon.classList.toggle('fa-moon');
            icon.classList.toggle('fa-sun');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        });

        // Load Theme Preference