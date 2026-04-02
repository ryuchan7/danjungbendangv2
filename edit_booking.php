<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Unauthorized access.';
    header('Location: index.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Invalid CSRF token.';
    header('Location: bookings.php');
    exit;
}

// Validate booking ID
if (!isset($_POST['booking_id']) || !filter_var($_POST['booking_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = 'Invalid booking ID.';
    header('Location: bookings.php');
    exit;
}

$booking_id = (int)$_POST['booking_id'];

try {
    // Fetch existing booking to get current payment proof
    $stmt = $pdo->prepare("SELECT payment_proof FROM booking WHERE bookingID = :booking_id");
    $stmt->execute(['booking_id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $_SESSION['error_message'] = 'Booking not found.';
        header('Location: bookings.php');
        exit;
    }

    // Collect form data
    $book_date = filter_input(INPUT_POST, 'book_date', FILTER_SANITIZE_STRING) ?? '';
    $checkIN = filter_input(INPUT_POST, 'checkIN', FILTER_SANITIZE_STRING) ?? '';
    $checkOUT = filter_input(INPUT_POST, 'checkOUT', FILTER_SANITIZE_STRING) ?? '';
    $number_of_guests = filter_input(INPUT_POST, 'number_of_guests', FILTER_VALIDATE_INT) ?: 1;
    $amount_paid = filter_input(INPUT_POST, 'amount_paid', FILTER_VALIDATE_FLOAT) ?: 0;
    $booking_status = filter_input(INPUT_POST, 'booking_status', FILTER_SANITIZE_STRING) ?? '';
    $payment_status = filter_input(INPUT_POST, 'payment_status', FILTER_SANITIZE_STRING) ?? '';
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING) ?? '';
    $customer_name = filter_input(INPUT_POST, 'customer_name', FILTER_SANITIZE_STRING) ?? '';
    $customer_email = filter_input(INPUT_POST, 'customer_email', FILTER_SANITIZE_EMAIL) ?? '';
    $customer_phone = filter_input(INPUT_POST, 'customer_phone', FILTER_SANITIZE_STRING) ?? '';
    $customer_ic_no = filter_input(INPUT_POST, 'customer_ic_no', FILTER_SANITIZE_STRING) ?? '';
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING) ?? '';

    // Validate required fields
    if (empty($book_date) || empty($checkIN) || empty($checkOUT) || $number_of_guests < 1 || $amount_paid < 0 ||
        empty($booking_status) || empty($payment_status) || empty($payment_method)) {
        $_SESSION['error_message'] = 'Please fill in all required fields.';
        header('Location: bookings.php');
        exit;
    }

    // Validate dates
    $book_date_obj = DateTime::createFromFormat('Y-m-d', $book_date);
    $checkIN_obj = DateTime::createFromFormat('Y-m-d', $checkIN);
    $checkOUT_obj = DateTime::createFromFormat('Y-m-d', $checkOUT);

    if (!$book_date_obj || !$checkIN_obj || !$checkOUT_obj || $checkIN_obj >= $checkOUT_obj) {
        $_SESSION['error_message'] = 'Invalid date selection. Check-in must be before check-out.';
        header('Location: bookings.php');
        exit;
    }

    // Validate booking status
    $valid_statuses = ['Pending Payment', 'Pending Approval', 'Confirmed', 'Cancelled', 'Rejected', 'Completed'];
    if (!in_array($booking_status, $valid_statuses)) {
        $_SESSION['error_message'] = 'Invalid booking status: ' . htmlspecialchars($booking_status) . '.';
        header('Location: bookings.php');
        exit;
    }

    // Validate payment status
    $valid_payment_statuses = ['Pending', 'Completed', 'Failed'];
    if (!in_array($payment_status, $valid_payment_statuses)) {
        $_SESSION['error_message'] = 'Invalid payment status: ' . htmlspecialchars($payment_status) . '.';
        header('Location: bookings.php');
        exit;
    }

    // Validate payment method
    $valid_payment_methods = ['CASH', 'ONLINE BANKING', 'E_WALLET'];
    if (!in_array($payment_method, $valid_payment_methods)) {
        $_SESSION['error_message'] = 'Invalid payment method: ' . htmlspecialchars($payment_method) . '.';
        header('Location: bookings.php');
        exit;
    }

    // Handle payment proof uploads and deletions
    $existing_proofs = !empty($booking['payment_proof']) ? explode(',', $booking['payment_proof']) : [];
    $upload_dir = 'Uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Handle images marked for deletion
    $delete_proofs = isset($_POST['delete_proof']) ? (array)$_POST['delete_proof'] : [];
    foreach ($delete_proofs as $proof) {
        $index = array_search($proof, $existing_proofs);
        if ($index !== false) {
            unset($existing_proofs[$index]);
            if (file_exists($proof)) {
                unlink($proof); // Delete the file from the server
            }
        }
    }
    $existing_proofs = array_values($existing_proofs); // Reindex array

    // Handle new payment proof uploads (up to 2 images)
    $new_proofs = [];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $files = [
        'payment_proof_1' => $_FILES['payment_proof_1'] ?? null,
        'payment_proof_2' => $_FILES['payment_proof_2'] ?? null
    ];

    foreach ($files as $field => $file) {
        if ($file && !empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
            if (count($existing_proofs) + count($new_proofs) >= 2) {
                break; // Limit to 2 images total
            }
            $file_tmp = $file['tmp_name'];
            $file_name = $file['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $file_size = $file['size'];

            // Validate file type and size
            if (in_array($file_ext, $allowed_exts) && $file_size <= $max_size) {
                $new_file_name = 'payment_proof_' . $booking_id . '_' . time() . '_' . $field . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $destination)) {
                    $new_proofs[] = $destination;
                } else {
                    $_SESSION['error_message'] = 'Failed to upload one or more payment proof images.';
                    header('Location: bookings.php');
                    exit;
                }
            } else {
                $_SESSION['error_message'] = 'Invalid file type or size for ' . $field . '. Only JPG, JPEG, PNG, GIF up to 5MB are allowed.';
                header('Location: bookings.php');
                exit;
            }
        }
    }

    // Combine existing and new proofs (limit to 2)
    $final_proofs = array_merge($existing_proofs, $new_proofs);
    $final_proofs = array_slice($final_proofs, 0, 2); // Ensure max 2 proofs
    $payment_proof_string = !empty($final_proofs) ? implode(',', $final_proofs) : '';

    // Update booking in database
    $stmt = $pdo->prepare("
        UPDATE booking
        SET 
            book_date = :book_date,
            checkIN = :checkIN,
            checkOUT = :checkOUT,
            number_of_guests = :number_of_guests,
            amount_paid = :amount_paid,
            booking_status = :booking_status,
            payment_status = :payment_status,
            payment_method = :payment_method,
            full_name = :customer_name,
            email = :customer_email,
            phone = :customer_phone,
            ic_no = :customer_ic_no,
            payment_proof = :payment_proof,
            notes = :notes
        WHERE bookingID = :booking_id
    ");
    $stmt->execute([
        'book_date' => $book_date,
        'checkIN' => $checkIN,
        'checkOUT' => $checkOUT,
        'number_of_guests' => $number_of_guests,
        'amount_paid' => $amount_paid,
        'booking_status' => $booking_status,
        'payment_status' => $payment_status,
        'payment_method' => $payment_method,
        'customer_name' => $customer_name ?: null,
        'customer_email' => $customer_email ?: null,
        'customer_phone' => $customer_phone ?: null,
        'customer_ic_no' => $customer_ic_no ?: null,
        'payment_proof' => $payment_proof_string,
        'notes' => $notes ?: null,
        'booking_id' => $booking_id
    ]);

    // Log the edit action in audit_logs
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, created_at)
        VALUES (:user_id, :action, :details, NOW())
    ");
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'action' => 'Edited booking #' . $booking_id,
        'details' => json_encode(['booking_id' => $booking_id, 'customer_name' => $customer_name])
    ]);

    $_SESSION['success_message'] = 'Booking updated successfully.';
    header('Location: bookings.php');
    exit;

} catch (PDOException $e) {
    // Log the error for debugging (in a production environment, log to a file instead of displaying)
    error_log('Database error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error: ' . htmlspecialchars($e->getMessage());
    header('Location: bookings.php');
    exit;
}
?>