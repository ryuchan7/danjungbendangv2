<?php
session_start();
require_once 'db_connect.php';

$errors = [];
$success = isset($_GET['registered']) ? 'Registration successful! Please log in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $errors[] = 'Username and password are required.';
    } else {
        try {
            // Allow login with either username or email
            $stmt = $pdo->prepare("SELECT userID, username, password, role FROM users WHERE username = :username OR email = :email");
            $stmt->execute([':username' => $username, ':email' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $errors[] = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - D'Anjung Bendang Homestay Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f9f9;
            color: #333;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Ensures footer stays at bottom */
        }
        .navbar {
            background: #fff;
            padding: 15px 0;
            transition: padding 0.3s ease;
            width: 100%;
        }
        .navbar.shrink {
            padding: 5px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 600;
            color: #26a69a !important;
            font-size: 1.8rem;
        }
        .navbar-nav .nav-link {
            color: #333 !important;
            font-weight: 500;
            margin-left: 20px;
            transition: color 0.3s;
        }
        .navbar-nav .nav-link:hover {
            color: #26a69a !important;
        }
        .navbar-nav .nav-link.active {
            color: #26a69a !important;
            font-weight: 600;
        }
        .booking-btn {
            background: #26a69a;
            color: #fff !important;
            padding: 8px 20px;
            border-radius: 20px;
            border: none;
        }
        .booking-btn:hover {
            background: #00695c;
            color: #fff !important;
        }
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding-top: 70px; /* Adjust for fixed navbar height */
            padding-bottom: 30px; /* Adjust for footer height */
        }
        .login-container {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .login-container h2 {
            color: #26a69a;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .alert-danger {
            background-color: #ffe6e6;
            color: #d32f2f;
            border: none;
            border-radius: 5px;
            padding: 10px;
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
        .btn-login {
            background: #26a69a;
            color: #fff;
            border: none;
            width: 100%;
            padding: 10px;
            border-radius: 25px;
            font-weight: 500;
        }
        .btn-login:hover {
            background: #00695c;
        }
        footer {
            background: #263238;
            color: #fff;
            padding: 20px 0;
            text-align: center;
            width: 100%;
        }
        @media (max-width: 576px) {
            .login-container {
                margin: 15px;
                padding: 20px;
            }
            .navbar-nav .nav-link {
                margin-left: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">Cozy Homestay</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                        <a class="nav-link btn booking-btn" href="booking_form.php">Book Now</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="login.php">Admin Login</a>
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
        <p>© 2025 D'Anjung Bendang Homestay Homestay. All rights reserved.</p>
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