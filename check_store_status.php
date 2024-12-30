<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

$response = [
    'is_closed' => false,
    'notification' => []
];

$now = new DateTime();
$todayDate = $now->format('Y-m-d');
$todayName = $now->format('l');
$currentTime = $now->format('H:i');

// Fetch the first active store; adjust if you have multiple
$stmt = $pdo->query("SELECT work_schedule FROM stores WHERE is_active=1 ORDER BY id ASC LIMIT 1");
$store = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$store) {
    $response['is_closed'] = true;
    $response['notification'] = [
        'title' => 'Store Closed',
        'message' => 'No active store found.',
        'end_datetime' => $now->modify('+1 day')->format('c')
    ];
    echo json_encode($response);
    exit;
}

$decoded = @json_decode($store['work_schedule'] ?? '', true);
if (!is_array($decoded)) {
    $response['is_closed'] = true;
    $response['notification'] = [
        'title' => 'Store Closed',
        'message' => 'No valid schedule data available.',
        'end_datetime' => $now->modify('+1 day')->format('c')
    ];
    echo json_encode($response);
    exit;
}

$holidays = $decoded['holidays'] ?? [];
foreach ($holidays as $h) {
    if (!empty($h['date']) && $h['date'] === $todayDate) {
        $response['is_closed'] = true;
        $desc = $h['desc'] ?? 'Store is closed for a holiday.';
        $response['notification'] = [
            'title' => 'Store Closed',
            'message' => $desc,
            'end_datetime' => (new DateTime($h['date'] . ' 23:59:59'))->format('c')
        ];
        echo json_encode($response);
        exit;
    }
}

$days = $decoded['days'] ?? [];
if (!isset($days[$todayName])) {
    $response['is_closed'] = true;
    $response['notification'] = [
        'title' => 'Store Closed',
        'message' => "No schedule for $todayName.",
        'end_datetime' => $now->modify('+1 day')->format('c')
    ];
    echo json_encode($response);
    exit;
}

$start = $days[$todayName]['start'] ?? '';
$end = $days[$todayName]['end'] ?? '';
if (empty($start) || empty($end)) {
    $response['is_closed'] = true;
    $response['notification'] = [
        'title' => 'Store Closed',
        'message' => "Closed today ($todayName).",
        'end_datetime' => $now->modify('+1 day')->format('c')
    ];
    echo json_encode($response);
    exit;
}

if ($currentTime < $start || $currentTime > $end) {
    $response['is_closed'] = true;
    $response['notification'] = [
        'title' => 'Store Closed',
        'message' => "Operating hours today: $start - $end.",
        'end_datetime' => (new DateTime($todayDate . ' ' . $end))->format('c')
    ];
    echo json_encode($response);
    exit;
}

echo json_encode($response);
exit;
