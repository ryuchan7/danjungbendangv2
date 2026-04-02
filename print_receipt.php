<?php
session_start();
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || !filter_var($_GET['booking_id'], FILTER_VALIDATE_INT)) {
    die('Invalid booking ID.');
}

$booking_id = (int)$_GET['booking_id'];

try {
    // Fetch booking details
    $stmt = $pdo->prepare("
        SELECT 
            b.bookingID, 
            h.name AS homestay_name, 
            b.book_date,
            b.checkIN, 
            b.checkOUT, 
            b.number_of_guests, 
            b.payment_status, 
            b.payment_method, 
            b.amount_paid, 
            b.booking_status, 
            COALESCE(u.full_name, b.full_name) AS customer_name, 
            COALESCE(u.email, b.email) AS customer_email,
            COALESCE(u.phone, b.phone) AS customer_phone,
            b.created_at
        FROM booking b
        INNER JOIN homestaylist h ON b.hslistID = h.hslistID
        LEFT JOIN users u ON b.userID = u.userID
        WHERE b.bookingID = :booking_id
    ");
    $stmt->execute(['booking_id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die('Booking not found.');
    }

    // Calculate number of nights
    $check_in_date = new DateTime($booking['checkIN']);
    $check_out_date = new DateTime($booking['checkOUT']);
    $number_of_nights = $check_in_date->diff($check_out_date)->days;

    // Format dates to Malaysian format (dd/mm/yyyy)
    $book_date = (new DateTime())->format('d/m/Y'); // Updated to current date
    $check_in = $check_in_date->format('d/m/Y');
    $check_out = $check_out_date->format('d/m/Y');
    $created_at = (new DateTime($booking['created_at']))->format('d/m/Y H:i:s');

    // Determine status highlight color
    $status_color = '#6c757d'; // Default (gray) for other statuses
    if ($booking['booking_status'] === 'Confirmed') {
        $status_color = '#28a745'; // Green
    } elseif (in_array($booking['booking_status'], ['Pending Payment', 'Pending Approval'])) {
        $status_color = '#ffc107'; // Yellow
    } elseif ($booking['booking_status'] === 'Cancelled') {
        $status_color = '#dc3545'; // Red
    }

    // Prepare a message for WhatsApp
    $share_message = "Dear {$booking['customer_name']},\n\n";
    $share_message .= "Thank you for booking with D'Anjung Bendang Homestay! Your booking receipt (Booking ID: {$booking['bookingID']}) is ready.\n";
    $share_message .= "Please reply to this message to receive the PDF receipt, or contact us at d.anjungbendang@gmail.com or 01173206136.\n\n";
    $share_message .= "Best regards,\nD'Anjung Bendang Homestay Team";

    // Format phone number for WhatsApp
    $phone = $booking['customer_phone'] ?? '';
    $formatted_phone = '';
    if ($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) >= 10 && substr($phone, 0, 2) === '01') {
            $formatted_phone = '+6' . $phone;
        }
    }

    // Prepare WhatsApp link
    $whatsapp_message = urlencode($share_message);
    $whatsapp_link = $formatted_phone ? "https://wa.me/{$formatted_phone}?text={$whatsapp_message}" : '';

    // Sanitize customer name for filename
    $sanitized_customer_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $booking['customer_name']);
    $pdf_filename = "DAnjungBendang_Receipt_{$sanitized_customer_name}_{$booking['bookingID']}.pdf";

} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt - D'Anjung Bendang Homestay</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            color: #2b2b2b;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .receipt-container {
            background: #fff;
            width: 100%;
            max-width: 850px;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            margin-top: 0;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .receipt-header img.logo {
            max-height: 90px;
            margin-bottom: 12px;
        }
        .receipt-header h1 {
            color: #1a5f7a;
            font-weight: 700;
            margin: 0 0 6px;
            font-size: 24px;
        }
        .receipt-header p {
            margin: 0;
            color: #666;
            font-size: 13px;
            font-weight: 300;
            line-height: 1.4;
        }
        .receipt-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .seller-info, .customer-info, .receipt-details {
            padding: 12px;
            background: #fafafa;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        .seller-info h3, .customer-info h3, .receipt-details h3 {
            color: #1a5f7a;
            font-size: 16px;
            margin: 0 0 10px;
            font-weight: 700;
        }
        .seller-info p, .customer-info p, .receipt-details p {
            margin: 5px 0;
            font-size: 13px;
            line-height: 1.4;
            color: #333;
            font-weight: 400;
        }
        .receipt-details p strong {
            color: #1a5f7a;
            font-weight: 700;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        .receipt-table th {
            background-color: #ffeb3b;
            color: #2b2b2b;
            padding: 12px;
            text-align: left;
            font-weight: 700;
            font-size: 14px;
            border-bottom: 2px solid #ddd;
        }
        .receipt-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
            font-weight: 400;
        }
        .receipt-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .receipt-table tr:last-child td {
            border-bottom: none;
        }
        .total-row {
            background-color: #ffeb3b;
            font-weight: 700;
            font-size: 14px;
        }
        .total-row .label {
            font-weight: 700;
        }
        .empty-row {
            border-bottom: 1px solid #ddd;
            background-color: #fff;
        }
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 3px solid #1a5f7a;
        }
        .footer p {
            margin: 8px 0;
            font-size: 14px;
            color: #555;
            font-weight: 300;
        }
        .print-button {
            display: block;
            margin: 25px auto;
            padding: 14px 28px;
            background: linear-gradient(135deg, #1a5f7a, #2a8eae);
            color: #fff;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .print-button:hover {
            background: linear-gradient(135deg, #2a8eae, #1a5f7a);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                padding: 15px;
                width: 100%;
                max-width: 100%;
                margin: 0;
            }
            .print-button {
                display: none;
            }
            @page {
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <img src="images/logo.jpg" alt="D'Anjung Bendang Homestay Logo" class="logo">
            <h1>D'Anjung Bendang Homestay Invoice</h1>
            <p>Official Invoice | Lot 6896, Simpang 3, Kampung Batu Lapan, 34350 Kuala Kurau, Perak</p>
        </div>

        <!-- Receipt Information -->
        <div class="receipt-info">
            <div class="seller-info">
                <h3>Seller</h3>
                <p>D'Anjung Bendang Homestay</p>
                <p>011-7320-6136</p>
                <p>d.anjungbendang@gmail.com</p>
            </div>
            <div class="customer-info">
                <h3>Customer</h3>
                <p><?php echo htmlspecialchars($booking['customer_name']); ?></p>
                <?php if (!empty($booking['customer_email'])): ?>
                    <p><?php echo htmlspecialchars($booking['customer_email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($booking['customer_phone'])): ?>
                    <p><?php echo htmlspecialchars($booking['customer_phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="receipt-details">
                <h3>Invoice Details</h3>
                <p><strong>Invoice Number:</strong> <?php echo $booking['bookingID']; ?></p>
                <p><strong>Invoice Date:</strong> <?php echo $book_date; ?></p>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($booking['payment_method']); ?></p>
            </div>
        </div>

        <!-- Receipt Table -->
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th>CHECK-IN</th>
                    <th>CHECK-OUT</th>
                    <th>QUANTITY</th>
                    <th>UNIT PRICE (RM)</th>
                    <th>SUBTOTAL (RM)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Homestay</td>
                    <td><?php echo $check_in; ?></td>
                    <td><?php echo $check_out; ?></td>
                    <td><?php echo $number_of_nights . ' Nights'; ?></td>
                    <td><?php echo number_format($booking['amount_paid'] / $number_of_nights, 2); ?></td>
                    <td><?php echo number_format($booking['amount_paid'], 2); ?></td>
                </tr>
                <?php
                // Add 5 empty rows with horizontal lines to fill the page
                for ($i = 0; $i < 5; $i++) {
                    echo '<tr class="empty-row"><td colspan="6"></td></tr>';
                }
                ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4"></td>
                    <td class="label">TOTAL (RM)</td>
                    <td><?php echo number_format($booking['amount_paid'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>Contact us at d.anjungbendang@gmail.com | 011-7320-6136</p>
        </div>
    </div>

    <button class="print-button" onclick="printAndShare()"><i class="fas fa-print"></i> Print and Share Receipt</button>

    <script>
        function printAndShare() {
            document.title = "<?php echo addslashes($pdf_filename); ?>";
            window.print();
            setTimeout(() => {
                document.title = "Booking Receipt - D'Anjung Bendang Homestay";
            }, 500);
            setTimeout(() => {
                const whatsapp_link = "<?php echo $whatsapp_link; ?>";
                if (whatsapp_link) {
                    window.open(whatsapp_link, '_blank');
                } else {
                    alert('WhatsApp number not available.');
                }
            }, 1000);
        }
    </script>
</body>
</html>