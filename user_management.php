<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set('UTC');

// Handle messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle search
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_query = '';
$params = [];

try {
    // Base SQL query
    $sql = "
        SELECT userID, username, email, full_name, phone, ic_no, role, created_at
        FROM users
    ";

    // Add search conditions if search term is provided
    if (!empty($search_term)) {
        $search_query = " WHERE username LIKE :search 
                         OR email LIKE :search 
                         OR full_name LIKE :search 
                         OR phone LIKE :search 
                         OR ic_no LIKE :search";
        $sql .= $search_query;
        $params[':search'] = '%' . $search_term . '%';
    }

    $sql .= " ORDER BY created_at DESC";

    // Prepare and execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>User Management - Cozy Homestay</title>
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
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #a71d2a, #dc3545);
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
        .modal-body .form-group {
            margin-bottom: 15px;
        }
        .search-container {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-container input {
            flex: 2;
        }
        .search-container select {
            flex: 1;
            max-width: 150px;
        }
        .search-container span {
            flex: 1;
            text-align: right;
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
            <a href="user_management.php" class="nav-link active">
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
              <a href="feedback_management.php" class="nav-link">
                <i class="fas fa-comment-dots"></i> Feedback Customer
            </a>
               <a href="worker_salary.php" class="nav-link">
                <i class="fas fa-money-bill-wave"></i> Worker Salary
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>User Management</h2>
                <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

            <!-- Search Container -->
            <div class="search-container">
                <input type="text" id="searchInput" class="form-control" placeholder="Search users..." value="<?php echo htmlspecialchars($search_term); ?>">
                <select id="statusFilter" class="form-control">
                    <option value="">All Statuses</option>
                </select>
                <span>Total Users: <span id="totalUsers"><?php echo count($users); ?></span></span>
            </div>

            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
            <div class="table-responsive users-section">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Full Name</th>
                            <th>Phone</th>
                            <th>IC Number</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No users found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['userID']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['ic_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['userID']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['userID'] != $_SESSION['user_id']): ?>
                                            <form action="add_edit_user.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="add_edit_user.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label for="username">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" class="form-control" name="full_name">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="form-group">
                            <label for="ic_no">IC Number</label>
                            <input type="text" class="form-control" name="ic_no">
                        </div>
                        <div class="form-group">
                            <label for="role">Role <span class="text-danger">*</span></label>
                            <select class="form-control" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modals -->
    <?php foreach ($users as $user): ?>
        <div class="modal fade" id="editUserModal<?php echo $user['userID']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?php echo $user['userID']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel<?php echo $user['userID']; ?>">Edit User #<?php echo $user['userID']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="add_edit_user.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="user_id" value="<?php echo $user['userID']; ?>">
                            <div class="form-group">
                                <label for="username">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="ic_no">IC Number</label>
                                <input type="text" class="form-control" name="ic_no" value="<?php echo htmlspecialchars($user['ic_no'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="role">Role <span class="text-danger">*</span></label>
                                <select class="form-control" name="role" required>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</body>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const usersTableBody = document.getElementById('usersTableBody');
        const totalUsersSpan = document.getElementById('totalUsers');
        const rows = Array.from(usersTableBody.getElementsByTagName('tr'));

        function filterRows() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let filteredRows = rows;

            if (searchTerm) {
                filteredRows = rows.filter(row => {
                    const username = row.cells[1].textContent.toLowerCase();
                    const email = row.cells[2].textContent.toLowerCase();
                    const fullName = row.cells[3].textContent.toLowerCase();
                    const phone = row.cells[4].textContent.toLowerCase();
                    const icNo = row.cells[5].textContent.toLowerCase();

                    return (
                        username.includes(searchTerm) ||
                        email.includes(searchTerm) ||
                        fullName.includes(searchTerm) ||
                        phone.includes(searchTerm) ||
                        icNo.includes(searchTerm)
                    );
                });
            }

            usersTableBody.innerHTML = '';
            filteredRows.forEach(row => usersTableBody.appendChild(row));
            totalUsersSpan.textContent = filteredRows.length;
        }

        searchInput.addEventListener('input', function() {
            filterRows();
        });

        // Initial load
        filterRows();
    });
</script>
</html>