<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: bookings.php');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid CSRF token.';
    header('Location: bookings.php');
    exit;
}

// Sanitize and validate inputs
$hslistID = filter_input(INPUT_POST, 'hslistID', FILTER_VALIDATE_INT);
$book_date = filter_input(INPUT_POST, 'book_date', FILTER_SANITIZE_STRING);
$checkIN = filter_input(INPUT_POST, 'checkIN', FILTER_SANITIZE_STRING);
$checkOUT = filter_input(INPUT_POST, 'checkOUT', FILTER_SANITIZE_STRING);
$number_of_guests = filter_input(INPUT_POST, 'number_of_guests', FILTER_VALIDATE_INT);
$amount_paid = filter_input(INPUT_POST, 'amount_paid', FILTER_VALIDATE_FLOAT);
$booking_status = filter_input(INPUT_POST, 'booking_status', FILTER_SANITIZE_STRING);
$payment_status = filter_input(INPUT_POST, 'payment_status', FILTER_SANITIZE_STRING);
$payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
$full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
$ic_no = filter_input(INPUT_POST, 'ic_no', FILTER_SANITIZE_STRING);

$required_fields = ['hslistID', 'book_date', 'checkIN', 'checkOUT', 'number_of_guests', 'amount_paid', 'booking_status', 'payment_status', 'payment_method', 'full_name'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $_SESSION['error_message'] = 'All required fields must be filled.';
        header('Location: bookings.php');
        exit;
    }
}

// Validate number of guests
if ($number_of_guests < 1 || $number_of_guests > 15) {
    $_SESSION['error_message'] = 'Number of guests must be between 1 and 15.';
    header('Location: bookings.php');
    exit;
}

// Validate payment method
$valid_payment_methods = ['CASH', 'ONLINE BANKING', 'E_WALLET'];
if (!in_array($payment_method, $valid_payment_methods)) {
    $_SESSION['error_message'] = 'Invalid payment method selected.';
    header('Location: bookings.php');
    exit;
}

// Validate dates
try {
    $book_date = new DateTime($book_date);
    $checkIN = new DateTime($checkIN);
    $checkOUT = new DateTime($checkOUT);

    if ($checkIN >= $checkOUT) {
        $_SESSION['error_message'] = 'Check-out date must be after check-in date.';
        header('Location: bookings.php');
        exit;
    }

    // Check for existing bookings with time consideration (check-out 12 PM, check-in 3 PM)
    $stmt = $pdo->prepare("
        SELECT bookingID
        FROM booking
        WHERE hslistID = :hslistID
        AND booking_status NOT IN ('Cancelled', 'Rejected')
        AND (
            (:checkIN <= DATE_SUB(checkOUT, INTERVAL 1 DAY) AND :checkOUT >= checkIN)
        )
    ");
    $stmt->execute([
        ':hslistID' => $hslistID,
        ':checkIN' => $checkIN->format('Y-m-d'),
        ':checkOUT' => $checkOUT->format('Y-m-d')
    ]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['error_message'] = 'Selected dates are already booked.';
        header('Location: bookings.php');
        exit;
    }

    // Validate amount paid
    $rate_per_day = 250; // RM250 per day
    $interval = $checkIN->diff($checkOUT);
    $days = $interval->days;
    $expected_amount = $days * $rate_per_day;
    if (abs($amount_paid - $expected_amount) > 0.01) {
        $_SESSION['error_message'] = 'Amount paid does not match the calculated amount (RM250 per day).';
        header('Location: bookings.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Invalid date format.';
    header('Location: bookings.php');
    exit;
}

// Handle file upload
$payment_proof = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $file_type = mime_content_type($_FILES['payment_proof']['tmp_name']);
    $file_size = $_FILES['payment_proof']['size'];

    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error_message'] = 'Only JPEG, PNG, and GIF files are allowed.';
        header('Location: bookings.php');
        exit;
    }

    if ($file_size > $max_size) {
        $_SESSION['error_message'] = 'File size exceeds 5MB limit.';
        header('Location: bookings.php');
        exit;
    }

    $upload_dir = 'Uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
    $file_name = 'payment_proof_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;

    if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
        $_SESSION['error_message'] = 'Failed to upload payment proof.';
        header('Location: bookings.php');
        exit;
    }

    $payment_proof = $file_path;
}

// Insert into database
try {
    $stmt = $pdo->prepare("
        INSERT INTO booking (
            hslistID, userID, book_date, checkIN, checkOUT, number_of_guests, 
            payment_status, payment_method, amount_paid, booking_status, 
            payment_proof, full_name, email, phone, ic_no, created_at
        ) VALUES (
            :hslistID, NULL, :book_date, :checkIN, :checkOUT, :number_of_guests, 
            :payment_status, :payment_method, :amount_paid, :booking_status, 
            :payment_proof, :full_name, :email, :phone, :ic_no, NOW()
        )
    ");

    $stmt->execute([
        ':hslistID' => $hslistID,
        ':book_date' => $book_date->format('Y-m-d'),
        ':checkIN' => $checkIN->format('Y-m-d'),
        ':checkOUT' => $checkOUT->format('Y-m-d'),
        ':number_of_guests' => $number_of_guests,
        ':payment_status' => $payment_status,
        ':payment_method' => $payment_method,
        ':amount_paid' => $amount_paid,
        ':booking_status' => $booking_status,
        ':payment_proof' => $payment_proof,
        ':full_name' => $full_name,
        ':email' => $email ?: null,
        ':phone' => $phone ?: null,
        ':ic_no' => $ic_no ?: null
    ]);

    $_SESSION['success_message'] = 'Booking added successfully.';
    header('Location: bookings.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . htmlspecialchars($e->getMessage());
    header('Location: bookings.php');
    exit;
}
?>