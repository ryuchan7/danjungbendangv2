<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

// Initialize response array
$response = ['available' => false, 'message' => ''];

// Get and validate input
$startDate = isset($_POST['startDate']) ? filter_var($_POST['startDate'], FILTER_SANITIZE_STRING) : '';
$endDate = isset($_POST['endDate']) ? filter_var($_POST['endDate'], FILTER_SANITIZE_STRING) : '';
$hslistID = isset($_POST['hslistID']) ? filter_var($_POST['hslistID'], FILTER_VALIDATE_INT) : 1; // Default to homestay ID 1 if not provided

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $response['message'] = 'Invalid date format. Please use YYYY-MM-DD.';
    echo json_encode($response);
    exit;
}

// Convert dates to DateTime for validation
try {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $today = new DateTime(); // Current date (2025-04-30)

    // Ensure start date is not in the past
    if ($start < $today) {
        $response['message'] = 'Start date cannot be in the past.';
        echo json_encode($response);
        exit;
    }

    // Ensure start date is before end date
    if ($start > $end) {
        $response['message'] = 'Start date must be before end date.';
        echo json_encode($response);
        exit;
    }
} catch (Exception $e) {
    $response['message'] = 'Invalid date values provided.';
    echo json_encode($response);
    exit;
}

// Validate homestay ID exists
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM homestaylist WHERE hslistID = :hslistID");
    $stmt->execute(['hslistID' => $hslistID]);
    if ($stmt->fetchColumn() == 0) {
        $response['message'] = 'Invalid homestay ID.';
        echo json_encode($response);
        exit;
    }
} catch (PDOException $e) {
    $response['message'] = 'Error validating homestay: ' . htmlspecialchars($e->getMessage());
    echo json_encode($response);
    exit;
}

// Check availability
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM booking 
        WHERE hslistID = :hslistID
        AND booking_status IN ('confirmed', 'pending-approval', 'pending-payment')
        AND (
            (checkIN <= :endDate AND checkOUT >= :startDate)
        )
    ");
    $stmt->execute([
        'hslistID' => $hslistID,
        'startDate' => $startDate,
        'endDate' => $endDate
    ]);
    $count = $stmt->fetchColumn();
    $available = $count == 0;

    $response['available'] = $available;
    $response['message'] = $available ? 'The selected dates are available!' : 'Sorry, the selected dates are not available.';
} catch (PDOException $e) {
    $response['message'] = 'Error checking availability: ' . htmlspecialchars($e->getMessage());
}

echo json_encode($response);
?>