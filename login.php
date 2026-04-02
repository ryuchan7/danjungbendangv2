<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Regenerate session ID on login attempt for security
if (isset($_POST['username'])) {
    session_regenerate_id(true);
}

// Include database connection
require_once 'db_connect.php';

// Initialize variables
$errors = [];
$success = isset($_GET['registered']) ? 'Registration successful! Please log in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($username) || empty($password)) {
        $errors[] = 'Username and password are required.';
    } else {
        try {
            // Prepare query to allow login with username or email
            $stmt = $pdo->prepare("SELECT userID, username, password, role FROM users WHERE username = :username OR email = :email");
            $stmt->execute([':username' => $username, ':email' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time(); // For session timeout

                // Debug: Log session data (remove in production)
                error_log("Session after login: " . print_r($_SESSION, true));

                // Redirect based on role using absolute URLs
                if ($user['role'] === 'admin') {
                    header('Location: http://localhost/homestay/admin_dashboard.php');
                } else {
                    header('Location: http://localhost/homestay/index.php');
                }
                exit(); // Stop script execution after redirect
            } else {
                $errors[] = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            // Log error instead of displaying (safer for production)
            error_log('Database error: ' . $e->getMessage());
            $errors[] = 'An error occurred. Please try again later.';
        }
    }
}

// Flush output buffer
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - D'Anjung Bendang Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            color: #2b2b2b;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .navbar {
            background: #fff;
            padding: 15px 0;
            transition: padding 0.3s ease;
            border-bottom: 1px solid #e0e0e0;
            width: 100%;
        }
        .navbar.shrink {
            padding: 5px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: #1a5f7a !important;
            font-size: 1.8rem;
        }
        .navbar-nav .nav-link {
            color: #2b2b2b !important;
            font-weight: 500;
            margin-left: 20px;
            transition: color 0.3s;
        }
        .navbar-nav .nav-link:hover {
            color: #1a5f7a !important;
        }
        .navbar-nav .nav-link.active {
            color: #1a5f7a !important;
            font-weight: 600;
        }
        .booking-btn {
            background: linear-gradient(135deg, #1a5f7a, #2a8eae);
            color: #fff !important;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
        }
        .booking-btn:hover {
            background: linear-gradient(135deg, #2a8eae, #1a5f7a);
        }
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding-top: 80px;
            padding-bottom: 40px;
        }
        .login-container {
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            max-width: 400px;
            width: 100%;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        .login-container h2 {
            color: #1a5f7a;
            font-weight: 700;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 600;
            color: #1a5f7a;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #b2dfdb;
            padding: 10px;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #1a5f7a;
            box-shadow: 0 0 5px rgba(26, 95, 122, 0.5);
            outline: none;
        }
        .alert-danger {
            background-color: #ffe6e6;
            color: #d32f2f;
            border: none;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .alert-danger ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .alert-danger li::before {
            content: "• ";
            color: #d32f2f;
        }
        .alert-success {
            background-color: #e0f7fa;
            color: #1a5f7a;
            border: none;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .btn-login {
            background: linear-gradient(135deg, #1a5f7a, #2a8eae);
            color: #fff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 25px;
            font-weight: 500;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #2a8eae, #1a5f7a);
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        footer {
            background: #1a5f7a;
            color: #fff;
            padding: 20px 0;
            text-align: center;
            width: 100%;
            font-weight: 300;
        }
        @media (max-width: 576px) {
            .login-container {
                margin: 15px;
                padding: 20px;
            }
            .navbar-nav .nav-link {
                margin-left: 10px;
            }
            .main-content {
                padding-top: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">D'Anjung Bendang Homestay</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#details">Details</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#gallery">Gallery</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#attraction">Attraction</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#activities">Activities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#map">Map</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-info text-white" href="login.php" style="border-radius: 50px; padding: 6px 20px;">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>


    <!-- Main Content with Centered Login Form -->
    <div class="main-content">
        <div class="login-container">
            <h2>Admin Login</h2>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-login">Login</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>© 2025 D'Anjung Bendang Homestay. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Shrinking Navbar
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            navbar.classList.toggle('shrink', window.scrollY > 50);
        });

        // Smooth scroll for nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function (e) {
                if (this.hash && document.querySelector(this.hash)) {
                    e.preventDefault();
                    document.querySelector(this.hash).scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>