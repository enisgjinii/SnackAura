<?php
// check_store_status.php
session_start();
header('Content-Type: application/json');
require 'db.php'; // Ensure this file contains your PDO connection as $pdo

$current_datetime = new DateTime();
$current_date = $current_datetime->format('Y-m-d');
$current_day = $current_datetime->format('l'); // Full day name, e.g., 'Monday'
$current_time = $current_datetime->format('H:i:s');

// Initialize response
$response = [
    'is_closed' => false,
    'notification' => []
];

// Check if today is a holiday
$stmt = $pdo->prepare("SELECT * FROM operational_hours WHERE type = 'holiday' AND date = ?");
if ($stmt->execute([$current_date])) {
    $holiday = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($holiday && $holiday['is_closed']) {
        $response['is_closed'] = true;
        $response['notification'] = [
            'title' => $holiday['title'] ?? 'Store Closed',
            'message' => $holiday['description'] ?? 'The store is currently closed for a holiday.',
            'end_datetime' => (new DateTime($holiday['date'] . ' ' . $holiday['close_time']))->format('c')
        ];
        echo json_encode($response);
        exit;
    }
}

// Check regular operational hours for today
$stmt = $pdo->prepare("SELECT * FROM operational_hours WHERE type = 'regular' AND day_of_week = ?");
if ($stmt->execute([$current_day])) {
    $regular_hours = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($regular_hours) {
        if ($regular_hours['is_closed']) {
            $response['is_closed'] = true;
            $response['notification'] = [
                'title' => 'Store Closed',
                'message' => 'The store is currently closed.',
                'end_datetime' => (new DateTime())->modify('+1 day')->format('c') // Next day as reopening time
            ];
        } else {
            // Check if current time is within operational hours
            if ($current_time < $regular_hours['open_time'] || $current_time > $regular_hours['close_time']) {
                $response['is_closed'] = true;
                $response['notification'] = [
                    'title' => 'Store Closed',
                    'message' => 'The store is currently closed. Our working hours are from ' . date('H:i', strtotime($regular_hours['open_time'])) . ' to ' . date('H:i', strtotime($regular_hours['close_time'])) . '.',
                    'end_datetime' => (new DateTime($current_date . ' ' . $regular_hours['close_time']))->format('c')
                ];
            }
        }
    } else {
        // No regular hours found for today
        $response['is_closed'] = true;
        $response['notification'] = [
            'title' => 'Store Closed',
            'message' => 'No operational hours found for today.',
            'end_datetime' => (new DateTime())->modify('+1 day')->format('c')
        ];
    }
} else {
    // Query failed
    $errorInfo = $stmt->errorInfo();
    // Log the error but respond with a closed status to be safe
    file_put_contents('errors.md', "### [" . date('Y-m-d H:i:s') . "] Error\n\n**Message:** Failed to execute operational_hours query: " . implode(", ", $errorInfo) . "\n\n---\n\n", FILE_APPEND | LOCK_EX);
    $response['is_closed'] = true;
    $response['notification'] = [
        'title' => 'Store Closed',
        'message' => 'Unable to determine operational status at this time.',
        'end_datetime' => (new DateTime())->modify('+1 day')->format('c')
    ];
}

// Return JSON response
echo json_encode($response);
exit;
?>
