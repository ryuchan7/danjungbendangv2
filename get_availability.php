<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

$currentYear = date('Y'); // 2025
$currentMonth = date('m'); // 04
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$availableDates = [];

try {
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $day);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM booking 
            WHERE booking_status IN ('confirmed', 'pending-approval', 'pending-payment')
            AND (
                (checkIN <= :endDate AND checkOUT >= :startDate)
            )
        ");
        $stmt->execute([
            'startDate' => $date,
            'endDate' => $date
        ]);
        $count = $stmt->fetchColumn();
        if ($count == 0) {
            $availableDates[] = $date;
        }
    }
    echo json_encode(['dates' => $availableDates]);
} catch (PDOException $e) {
    echo json_encode(['dates' => []]);
}
?>