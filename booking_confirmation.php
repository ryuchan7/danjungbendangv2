<?php
require_once 'db_connect.php';

// Initialize variables
$booking = null;
$error = '';
$guests = isset($_GET['guests']) ? (int)$_GET['guests'] : 0;
$success = isset($_GET['success']) && $_GET['success'] == '1';

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    $error = 'Invalid booking ID.';
} else {
    $booking_id = (int)$_GET['booking_id'];
    if (!isset($pdo)) {
        $error = 'Database connection is not available.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT bookingID, checkIN, checkOUT, payment_method, amount_paid, book_date, booking_status
                FROM booking
                WHERE bookingID = :booking_id
            ");
            $stmt->execute([':booking_id' => $booking_id]);
            $booking = $stmt->fetch();
            if (!$booking) {
                $error = 'Booking not found.';
            }
        } catch (PDOException $e) {
            $error = 'Error retrieving booking: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt - Cozy Homestay</title>
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
        .receipt-section {
            max-width: 600px;
            margin: 100px auto 50px;
            background: #fff;
            border: 2px solid #b2dfdb;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #b2dfdb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .receipt-header h2 {
            color: #26a69a;
            font-weight: 600;
            font-size: 1.8rem;
            margin: 0;
        }
        .receipt-header .icon {
            color: #00695c;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .receipt-details {
            font-size: 0.95rem;
        }
        .receipt-details .row {
            margin-bottom: 10px;
        }
        .receipt-details .label {
            font-weight: 500;
            color: #333;
        }
        .receipt-details .value {
            text-align: right;
            color: #555;
            word-break: break-word;
        }
        .payment-instructions {
            margin: 20px 0;
            padding: 15px;
            background: #f4f9f9;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .payment-instructions h4 {
            color: #26a69a;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .payment-instructions .qr-code {
            text-align: center;
            margin: 15px 0;
        }
        .payment-instructions .qr-code img {
            max-width: 150px;
            border: 1px solid #b2dfdb;
            border-radius: 5px;
        }
        .payment-instructions .bank-details {
            font-size: 0.85rem;
            color: #333;
        }
        .payment-instructions .bank-details p {
            margin: 5px 0;
        }
        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #b2dfdb;
            padding-top: 15px;
            font-size: 0.85rem;
            color: #666;
        }
        .btn-home, .btn-submit-proof {
            padding: 10px 25px;
            background: linear-gradient(135deg, #26a69a, #00695c);
            color: #fff;
            border: none;
            border-radius: 20px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            display: block;
            margin: 20px auto 0;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-home:hover, .btn-submit-proof:hover {
            background: linear-gradient(135deg, #00695c, #26a69a);
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
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
        .alert {
            max-width: 600px;
            margin: 100px auto 20px;
            border-radius: 10px;
        }
        @media (max-width: 576px) {
            .receipt-section {
                margin: 80px 15px 30px;
                padding: 15px;
            }
            .receipt-header h2 {
                font-size: 1.5rem;
            }
            .receipt-details .row {
                font-size: 0.85rem;
            }
            .receipt-details .value {
                font-size: 0.8rem;
            }
            .payment-instructions {
                font-size: 0.8rem;
            }
            .payment-instructions .qr-code img {
                max-width: 120px;
            }
            .receipt-footer {
                font-size: 0.75rem;
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

    <!-- Receipt Section -->
    <section class="receipt-section">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif ($booking): ?>
            <div class="receipt-header">
                <i class="fas fa-home icon"></i>
                <h2>D'Anjung Bendang Homestay Receipt</h2>
            </div>
            <div class="receipt-details">
                <div class="row">
                    <div class="col-6 label">Booking ID</div>
                    <div class="col-6 value"><?php echo htmlspecialchars($booking['bookingID']); ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Booking Date</div>
                    <div class="col-6 value"><?php echo htmlspecialchars($booking['book_date']); ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Check-In</div>
                    <div class="col-6 value"><?php echo htmlspecialchars($booking['checkIN']) . ', 2:00 PM'; ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Check-Out</div>
                    <div class="col-6 value"><?php echo htmlspecialchars($booking['checkOUT']) . ', 12:00 PM'; ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Number of Guests</div>
                    <div class="col-6 value"><?php echo htmlspecialchars($guests); ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Payment Method</div>
                    <div class="col-6 value"><?php echo htmlspecialchars($booking['payment_method']); ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Total Amount</div>
                    <div class="col-6 value">$<?php echo number_format($booking['amount_paid'], 2); ?></div>
                </div>
            </div>
            <div class="payment-instructions">
                <h4>Payment Details</h4>
                <div class="qr-code">
                    <img src="images/qrcode.jpg" alt="QR Code for Payment">
                    <p>Scan this QR code to pay $100 deposit</p>
                </div>
                <div class="bank-details">
                    <p><strong>Bank Name:</strong> Maybank</p>
                    <p><strong>Account Holder:</strong> Jamil Bin Hanafiah</p>
                    <p><strong>Account Number:</strong> 15110039102</p>
                    <p><strong>Payment Methods:</strong> E-Wallet, FPX (Online Banking), Cash (bank deposit)</p>
                </div>
            </div>
            <div class="receipt-footer">
                Thank you for choosing Cozy Homestay!<br>
                Contact us at: support@cozyhomestay.com | +60 1163054550
            </div>
            <?php if ($booking['booking_status'] === 'Pending Payment'): ?>
                <a href="submit_payment_proof.php?booking_id=<?php echo urlencode($booking['bookingID']); ?>" class="btn-submit-proof">Submit Payment Proof</a>
            <?php elseif ($booking['booking_status'] === 'Pending Approval'): ?>
                <p class="text-center text-muted mt-3">Your payment proof is submitted and awaiting admin approval. Once confirmed, we will email or WhatsApp you. Thank you for booking with Cozy Homestay.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Modal for Success Message -->
    <?php if ($success): ?>
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #26a69a, #00695c); color: #fff;">
                    <h5 class="modal-title" id="successModalLabel">Booking Submitted</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Your booking is complete but needs to be approved by admin first.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #26a69a, #00695c); border: none;">OK</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer>
        <p>© 2025 Cozy Homestay. All rights reserved.</p>
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

        // Show modal if success=1
        <?php if ($success): ?>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = new bootstrap.Modal(document.getElementById('successModal'));
                modal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>