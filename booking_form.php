<?php
require_once 'db_connect.php';

// Initialize variables
$errors = [];
$name = $email = $phone = $ic_no = $check_in = $check_out = $guests = $payment_method = '';
$price_per_night = 250.00;
$hslistID = 1;
$userID = null;
$booked_dates = [];

// Fetch booked dates from the database
try {
    $stmt = $pdo->prepare("
        SELECT checkIN, checkOUT 
        FROM booking 
        WHERE hslistID = :hslistID 
        AND booking_status NOT IN ('Cancelled', 'Rejected')
    ");
    $stmt->execute([':hslistID' => $hslistID]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate array of booked dates, excluding check-out date
    foreach ($bookings as $booking) {
        $start = new DateTime($booking['checkIN']);
        $end = new DateTime($booking['checkOUT']);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);
        foreach ($period as $date) {
            $booked_dates[] = $date->format('Y-m-d');
        }
    }
    $booked_dates = array_unique($booked_dates);
} catch (PDOException $e) {
    $errors[] = 'Error fetching booked dates: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $ic_no = filter_input(INPUT_POST, 'ic_no', FILTER_SANITIZE_STRING);
    $check_in = filter_input(INPUT_POST, 'check_in', FILTER_SANITIZE_STRING);
    $check_out = filter_input(INPUT_POST, 'check_out', FILTER_SANITIZE_STRING);
    $guests = filter_input(INPUT_POST, 'guests', FILTER_SANITIZE_NUMBER_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);

    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required if provided.';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    }
    if (empty($ic_no)) {
        $errors[] = 'IC Number is required.';
    }
    if (empty($check_in) || !DateTime::createFromFormat('Y-m-d', $check_in)) {
        $errors[] = 'Valid check-in date is required.';
    }
    if (empty($check_out) || !DateTime::createFromFormat('Y-m-d', $check_out)) {
        $errors[] = 'Valid check-out date is required.';
    }
    if ($check_in && $check_out) {
        $checkInDate = new DateTime($check_in);
        $checkOutDate = new DateTime($check_out);
        if ($checkInDate >= $checkOutDate) {
            $errors[] = 'Check-out date must be after check-in date.';
        }
        if ($checkInDate < new DateTime()) {
            $errors[] = 'Check-in date cannot be in the past.';
        }
    }
    if (empty($guests) || $guests < 1) {
        $errors[] = 'Number of guests must be at least 1.';
    } elseif ($guests > 15) {
        $errors[] = 'Cannot accommodate more than 15 guests.';
    }
    if (!in_array($payment_method, ['CASH', 'ONLINE BANKING', 'E_WALLET'])) {
        $errors[] = 'Please select a valid payment method.';
    }

    // Check for overlapping bookings
    if (empty($errors) && $check_in && $check_out) {
        try {
            $stmt = $pdo->prepare("
                SELECT checkIN, checkOUT 
                FROM booking 
                WHERE hslistID = :hslistID 
                AND booking_status NOT IN ('Cancelled', 'Rejected')
                AND (
                    (:check_in <= checkOUT AND :check_out >= checkIN)
                )
            ");
            $stmt->execute([
                ':hslistID' => $hslistID,
                ':check_in' => $check_in,
                ':check_out' => $check_out
            ]);
            $overlapping = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($overlapping) {
                foreach ($overlapping as $booking) {
                    if ($check_in < $booking['checkOUT'] && $check_out > $booking['checkIN']) {
                        if ($check_out !== $booking['checkIN']) {
                            $errors[] = 'Selected dates overlap with an existing booking from ' . $booking['checkIN'] . ' to ' . $booking['checkOUT'] . '. Please choose different dates.';
                            break;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Error checking availability: ' . $e->getMessage();
        }
    }

    // Validate hslistID
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM homestaylist WHERE hslistID = :hslistID");
            $stmt->execute([':hslistID' => $hslistID]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Selected homestay is not available.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validating homestay: ' . $e->getMessage();
        }
    }

    // Calculate amount
    if (empty($errors) && $checkInDate && $checkOutDate) {
        $interval = $checkInDate->diff($checkOutDate);
        $nights = $interval->days;
        $amount_paid = $nights * $price_per_night;
    }

    // Insert into database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO booking (
                    userID, hslistID, book_date, checkIN, checkOUT, 
                    payment_status, payment_method, amount_paid, booking_status, 
                    number_of_guests, full_name, email, phone, ic_no
                ) VALUES (
                    :userID, :hslistID, CURDATE(), :checkIN, :checkOUT, 
                    :payment_status, :payment_method, :amount_paid, 'Pending Payment', 
                    :number_of_guests, :full_name, :email, :phone, :ic_no
                )
            ");
            $stmt->execute([
                ':userID' => $userID ?: null,
                ':hslistID' => $hslistID,
                ':checkIN' => $check_in,
                ':checkOUT' => $check_out,
                ':payment_status' => 'Pending',
                ':payment_method' => $payment_method,
                ':amount_paid' => $amount_paid,
                ':number_of_guests' => $guests,
                ':full_name' => $name,
                ':email' => $email ?: null,
                ':phone' => $phone,
                ':ic_no' => $ic_no
            ]);
            $booking_id = $pdo->lastInsertId();
            header("Location: booking_confirmation.php?booking_id=" . urlencode($booking_id) . 
                   "&guests=" . urlencode($guests) . 
                   "&full_name=" . urlencode($name) . 
                   "&email=" . urlencode($email) . 
                   "&phone=" . urlencode($phone) . 
                   "&ic_no=" . urlencode($ic_no));
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Error saving booking: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Stay - D'Anjung Bendang Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <style>
        :root {
            --primary: #1a3c5e;
            --secondary: #4a90e2;
            --accent: #ffd700;
            --text: #2d3748;
            --bg-light: #f7fafc;
            --bg-dark: #1a202c;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --success: #28a745;
            --gradient: linear-gradient(135deg, #4a90e2, #1a3c5e);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            color: var(--text);
            line-height: 1.7;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            z-index: 1000;
            top: 0;
        }

        .navbar.shrink {
            padding: 0.5rem 0;
            background: rgba(255, 255, 255, 0.95);
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--primary) !important;
            font-size: 1.9rem;
            letter-spacing: 0.5px;
        }

        .navbar-nav .nav-link {
            color: var(--text) !important;
            font-weight: 500;
            margin-left: 1.5rem;
            padding: 0.5rem 1rem;
            position: relative;
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: width 0.3s ease;
        }

        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
            width: 100%;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--secondary) !important;
        }

        .booking-btn {
            background: var(--secondary);
            color: #fff !important;
            padding: 0.6rem 1.8rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .booking-btn:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        /* Booking Form (Unchanged) */
        .booking-section {
            max-width: 900px;
            margin: 100px auto 50px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            padding: 40px;
            border: 1px solid #e0e0e0;
        }

        .booking-section h2 {
            color: #1a5f7a;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #1a5f7a;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #b2dfdb;
            padding: 10px;
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #1a5f7a;
            box-shadow: 0 0 5px rgba(26, 95, 122, 0.5);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
        }

        .submit-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #1a5f7a, #2a8eae);
            color: #fff;
            border: none;
            border-radius: 25px;
            font-weight: 500;
            width: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #2a8eae, #1a5f7a);
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .date-note {
            font-size: 0.85rem;
            color: #555;
            margin-top: 5px;
        }

        /* Modal Styles (Unchanged) */
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, #1a5f7a, #2a8eae);
            color: #fff;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            border-bottom: none;
            padding: 20px;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.5rem;
        }

        .modal-body {
            padding: 20px;
            font-size: 1.1rem;
            color: #2b2b2b;
            text-align: center;
        }

        .modal-footer {
            border-top: none;
            padding: 20px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 30px;
            background: linear-gradient(135deg, #1a5f7a, #2a8eae);
            color: #fff;
            border: none;
            border-radius: 25px;
            font-weight: 500;
            transition: transform 0.3s, background 0.3s;
        }

        .modal-btn:hover {
            background: linear-gradient(135deg, #2a8eae, #1a5f7a);
            transform: scale(1.05);
        }

        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out;
            transform: translateY(-50px);
        }

        .modal.show .modal-dialog {
            transform: translateY(0);
        }

        /* Footer */
        footer {
            background: var(--bg-dark);
            color: #fff;
            padding: 2.5rem 0;
            text-align: center;
            font-weight: 300;
            font-size: 0.95rem;
        }

        /* Scroll to Top */
        .scroll-to-top {
            position: fixed;
            bottom: 2.5rem;
            right: 2.5rem;
            background: var(--secondary);
            color: #fff;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .scroll-to-top.show {
            opacity: 1;
        }

        .scroll-to-top:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        /* FAB Booking */
        .fab-booking {
            position: fixed;
            bottom: 7rem;
            right: 2.5rem;
            background: var(--secondary);
            color: #fff;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            z-index: 999;
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .fab-booking:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .booking-section {
                margin: 80px 15px 30px;
                padding: 20px;
            }
            .booking-section h2 {
                font-size: 1.8rem;
            }
            .form-control, .form-select {
                font-size: 0.9rem;
            }
            .modal-title {
                font-size: 1.2rem;
            }
            .modal-body {
                font-size: 1rem;
            }
            .date-note {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">D'Anjung Bendang Homestay</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="index.php#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#details">Details</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#attractions">Attractions</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#activities">Activities</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#contact">Contact</a></li>
                    <li class="nav-item">
                        <a class="nav-link btn booking-btn active" href="booking_form.php">Book Now</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Booking Form Section -->
    <section class="booking-section">
        <h2>Book Your Stay at D'Anjung Bendang Homestay</h2>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="booking_form.php" id="bookingForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="ic_no" class="form-label">IC Number</label>
                    <input type="text" class="form-control" id="ic_no" name="ic_no" value="<?php echo htmlspecialchars($ic_no); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email Address (Optional)</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="check_in" class="form-label">Check-In Date</label>
                    <input type="date" class="form-control" id="check_in" name="check_in" value="<?php echo htmlspecialchars($check_in); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                    <div class="date-note">Check-in available from 2:00 PM.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="check_out" class="form-label">Check-Out Date</label>
                    <input type="date" class="form-control" id="check_out" name="check_out" value="<?php echo htmlspecialchars($check_out); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    <div class="date-note">Check-out by 12:00 PM (available for next check-in at 2:00 PM).</div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="guests" class="form-label">Number of Guests (Max 15)</label>
                    <input type="number" class="form-control" id="guests" name="guests" min="1" max="15" value="<?php echo htmlspecialchars($guests); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-select" id="payment_method" name="payment_method" required>
                        <option value="" disabled <?php echo empty($payment_method) ? 'selected' : ''; ?>>Select Payment Method</option>
                        <option value="CASH" <?php echo $payment_method === 'CASH' ? 'selected' : ''; ?>>Cash</option>
                        <option value="ONLINE BANKING" <?php echo $payment_method === 'ONLINE BANKING' ? 'selected' : ''; ?>>Online Banking</option>
                        <option value="E_WALLET" <?php echo $payment_method === 'E_WALLET' ? 'selected' : ''; ?>>E-Wallet</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="submit-btn">Submit Booking</button>
        </form>
    </section>

    <!-- Modal for Booked Dates -->
    <div class="modal fade" id="bookedDatesModal" tabindex="-1" aria-labelledby="bookedDatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookedDatesModalLabel">Dates Unavailable</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalMessage">The selected date is already booked. Please choose a different date.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>© 2025 D'Anjung Bendang Homestay. All rights reserved.</p>
    </footer>

    <!-- Scroll to Top -->
    <div class="scroll-to-top" onclick="scrollToTop()"><i class="fas fa-chevron-up"></i></div>

    <!-- Floating Booking Button -->
    <a href="booking_form.php" class="fab-booking d-md-none"><i class="fas fa-calendar-check"></i></a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Navbar Animation
        gsap.from('.navbar', { y: -100, duration: 0.8, ease: 'power2.out' });

        // Navbar Shrink and Scroll to Top
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            navbar.classList.toggle('shrink', window.scrollY > 50);
            const scrollBtn = document.querySelector('.scroll-to-top');
            scrollBtn.classList.toggle('show', window.scrollY > 300);
        });

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Smooth Scroll for Nav Links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function (e) {
                if (this.hash && document.querySelector(this.hash)) {
                    e.preventDefault();
                    document.querySelector(this.hash).scrollIntoView({ behavior: 'smooth' });
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Initialize Bootstrap Modal
        const bookedDatesModal = new bootstrap.Modal(document.getElementById('bookedDatesModal'), {
            backdrop: 'static',
            keyboard: true
        });

        // Pass booked dates from PHP to JavaScript
        const bookedDates = <?php echo json_encode($booked_dates); ?>;

        // Get date inputs and modal message element
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const form = document.getElementById('bookingForm');
        const modalMessage = document.getElementById('modalMessage');

        // Set minimum date for check-in to today
        const today = new Date().toISOString().split('T')[0];
        checkInInput.setAttribute('min', today);

        // Update check-out minimum date based on check-in
        checkInInput.addEventListener('change', function () {
            const checkInDate = new Date(this.value);
            if (checkInDate) {
                const minCheckOut = new Date(checkInDate);
                minCheckOut.setDate(checkInDate.getDate() + 1);
                checkOutInput.setAttribute('min', minCheckOut.toISOString().split('T')[0]);
            }
            validateDate(this, 'Check-in');
        });

        // Validate check-out date
        checkOutInput.addEventListener('change', function () {
            if (this.value) {
                this.classList.remove('is-invalid');
            }
        });

        // Function to validate date selection
        function validateDate(input, type) {
            const selectedDate = input.value;
            if (type === 'Check-in' && bookedDates.includes(selectedDate)) {
                modalMessage.textContent = `Check-in date ${selectedDate} is already booked. Please choose a different date.`;
                bookedDatesModal.show();
                input.value = '';
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        }

        // Validate entire date range on form submission
        form.addEventListener('submit', function (e) {
            const checkInDate = checkInInput.value;
            const checkOutDate = checkOutInput.value;
            if (checkInDate && checkOutDate) {
                const start = new Date(checkInDate);
                const end = new Date(checkOutDate);
                let current = new Date(start);
                let bookedInRange = [];
                while (current < end) {
                    const dateStr = current.toISOString().split('T')[0];
                    if (bookedDates.includes(dateStr)) {
                        bookedInRange.push(dateStr);
                    }
                    current.setDate(current.getDate() + 1);
                }
                if (bookedInRange.length > 0) {
                    e.preventDefault();
                    modalMessage.textContent = `The selected date range includes booked dates: ${bookedInRange.join(', ')}. Please choose a different range.`;
                    bookedDatesModal.show();
                    checkInInput.classList.add('is-invalid');
                    checkOutInput.classList.add('is-invalid');
                    return;
                }
            }
        });

        // Clear invalid class when modal is closed
        document.getElementById('bookedDatesModal').addEventListener('hidden.bs.modal', function () {
            checkInInput.classList.remove('is-invalid');
            checkOutInput.classList.remove('is-invalid');
        });
    </script>
</body>
</html>