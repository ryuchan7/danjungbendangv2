<?php
require_once 'db_connect.php';

// Initialize variables
$error = '';
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    $error = 'Invalid booking ID.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT bookingID, booking_status FROM booking WHERE bookingID = :booking_id");
        $stmt->execute([':booking_id' => $booking_id]);
        $booking = $stmt->fetch();
        if (!$booking) {
            $error = 'Booking not found.';
        } elseif ($booking['booking_status'] !== 'Pending Payment') {
            $error = 'Payment proof already submitted or booking not in pending payment status.';
        }
    } catch (PDOException $e) {
        $error = 'Error retrieving booking: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['payment_proof'];
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Only JPG and PNG files are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size must not exceed 5MB.';
        } else {
            // Define absolute upload directory
            $upload_dir = 'C:/xampp/htdocs/homestay/uploads/';
            // Create uploads folder if it doesn't exist
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error = 'Failed to create uploads directory.';
                }
            }

            // Proceed if no directory error
            if (!$error) {
                $file_name = 'proof_' . $booking_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $file_path = $upload_dir . $file_name;

                // Attempt to move the uploaded file
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    try {
                        $relative_path = 'uploads/' . $file_name; // Store relative path in DB
                        $stmt = $pdo->prepare("
                            UPDATE booking
                            SET payment_proof = :payment_proof, booking_status = 'Pending Approval'
                            WHERE bookingID = :booking_id
                        ");
                        $stmt->execute([
                            ':payment_proof' => $relative_path,
                            ':booking_id' => $booking_id
                        ]);
                        // Redirect with success flag
                        header("Location: booking_confirmation.php?booking_id=" . urlencode($booking_id) . "&guests=" . urlencode($_GET['guests'] ?? 0) . "&success=1");
                        exit;
                    } catch (PDOException $e) {
                        $error = 'Error saving payment proof: ' . $e->getMessage();
                        unlink($file_path); // Remove file if DB update fails
                    }
                } else {
                    $error = 'Error moving uploaded file. Check directory permissions or path.';
                    // Log detailed error for debugging
                    error_log("Failed to move uploaded file from {$file['tmp_name']} to $file_path. Error: " . print_r(error_get_last(), true));
                }
            }
        }
    } else {
        $error = 'Please upload a payment proof file.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Payment Proof - Cozy Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f9f9;
            color: #333;
        }
        .navbar {
            background: #fff;
            padding: 15px 0;
            transition: padding 0.3s ease;
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
        .booking-btn {
            background: linear-gradient(135deg, #26a69a, #00695c);
            color: #fff !important;
            padding: 8px 20px;
            border-radius: 20px;
        }
        .booking-btn:hover {
            background: linear-gradient(135deg, #00695c, #26a69a);
        }
        .upload-section {
            max-width: 600px;
            margin: 100px auto 50px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .upload-section h2 {
            color: #26a69a;
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 500;
            color: #333;
        }
        .form-control {
            border-radius: 10px;
            border: 1px solid #b2dfdb;
            padding: 10px;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #26a69a;
            box-shadow: 0 0 5px rgba(38, 166, 154, 0.5);
            outline: none;
        }
        .submit-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #26a69a, #00695c);
            color: #fff;
            border: none;
            border-radius: 25px;
            font-weight: 500;
            width: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .submit-btn:hover {
            background: linear-gradient(135deg, #00695c, #26a69a);
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        footer {
            background: #263238;
            color: #fff;
            padding: 20px 0;
            text-align: center;
        }
        .scroll-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #26a69a;
            color: #fff;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
        }
        .scroll-to-top.show {
            opacity: 1;
            transform: translateY(0);
        }
        .scroll-to-top:hover {
            transform: translateY(-5px);
            background: #00695c;
        }
        @media (max-width: 576px) {
            .upload-section {
                margin: 80px 15px 30px;
                padding: 20px;
            }
            .upload-section h2 {
                font-size: 1.8rem;
            }
            .form-control {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">Cozy Homestay</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#details">Details</a></li>
                    <li class="nav-item">
                        <a class="nav-link btn booking-btn" href="booking_form.php">Book Now</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Upload Section -->
    <section class="upload-section">
        <h2>Submit Payment Proof</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="payment_proof" class="form-label">Upload Payment Proof (JPG/PNG, max 5MB)</label>
                    <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept="image/jpeg,image/png" required>
                </div>
                <button type="submit" class="submit-btn">Submit Proof</button>
            </form>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer>
        <p>© 2025 D'Anjung Bendang Homestay. All rights reserved.</p>
    </footer>

    <!-- Scroll to Top -->
    <div class="scroll-to-top" onclick="scrollToTop()"><i class="fas fa-chevron-up"></i></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            navbar.classList.toggle('shrink', window.scrollY > 50);
            const scrollBtn = document.querySelector('.scroll-to-top');
            scrollBtn.classList.toggle('show', window.scrollY > 300);
        });

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>