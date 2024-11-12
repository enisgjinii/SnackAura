<?php
// submit_reservation.php

session_start();
header('Content-Type: application/json');

// Kontrollo nëse është POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Kërkesa e pavlefshme.']);
    exit;
}

// Përfshini lidhjen me bazën e të dhënave
require 'db.php'; // Sigurohuni që kjo skedë përmban lidhjen PDO si $pdo

// Merrni dhe sanitizoni të dhënat
$client_name = trim($_POST['client_name'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$reservation_date = trim($_POST['reservation_date'] ?? '');
$reservation_time = trim($_POST['reservation_time'] ?? '');
$number_of_people = intval($_POST['number_of_people'] ?? 0);
$reservation_message = trim($_POST['reservation_message'] ?? '');

// Validimet
$errors = [];

if (empty($client_name)) {
    $errors[] = 'Emri është i nevojshëm.';
}

if (empty($client_email)) {
    $errors[] = 'Emaili është i nevojshëm.';
} elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Emaili nuk është valid.';
}

if (empty($reservation_date)) {
    $errors[] = 'Data e rezervimit është e nevojshme.';
} elseif (!DateTime::createFromFormat('Y-m-d', $reservation_date)) {
    $errors[] = 'Data e rezervimit nuk është në formatin e duhur.';
}

if (empty($reservation_time)) {
    $errors[] = 'Ora e rezervimit është e nevojshme.';
} elseif (!DateTime::createFromFormat('H:i', $reservation_time)) {
    $errors[] = 'Ora e rezervimit nuk është në formatin e duhur.';
}

if ($number_of_people <= 0) {
    $errors[] = 'Numri i njerëzve duhet të jetë më i madh se 0.';
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
    exit;
}

// Kontrollo nëse store është e hapur në kohën dhe datën e rezervimit
try {
    // Merrni operational hours për datën e rezervimit
    $stmt = $pdo->prepare("SELECT * FROM operational_hours WHERE type = 'holiday' AND date = ?");
    $stmt->execute([$reservation_date]);
    $holiday = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_closed = false;
    $notification = [];

    if ($holiday && $holiday['is_closed']) {
        $is_closed = true;
        $notification = [
            'title' => $holiday['title'] ?? 'Store Closed',
            'message' => $holiday['description'] ?? 'The store is closed for a holiday.',
            'end_datetime' => (new DateTime($holiday['date'] . ' ' . $holiday['close_time']))->format('c')
        ];
    } else {
        // Merr operational hours regulare për ditën e rezervimit
        $day_of_week = (new DateTime($reservation_date))->format('l'); // e.g., 'Monday'
        $stmt = $pdo->prepare("SELECT * FROM operational_hours WHERE type = 'regular' AND day_of_week = ?");
        $stmt->execute([$day_of_week]);
        $regular_hours = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($regular_hours) {
            if ($regular_hours['is_closed']) {
                $is_closed = true;
                $notification = [
                    'title' => 'Store Closed',
                    'message' => 'The store is closed on this day.',
                    'end_datetime' => (new DateTime($reservation_date . ' ' . $regular_hours['close_time']))->format('c')
                ];
            } else {
                // Kontrollo nëse ora e rezervimit është brenda orareve të hapura
                $reservation_datetime = new DateTime("$reservation_date $reservation_time");
                $open_datetime = new DateTime("$reservation_date " . $regular_hours['open_time']);
                $close_datetime = new DateTime("$reservation_date " . $regular_hours['close_time']);

                if ($reservation_datetime < $open_datetime || $reservation_datetime > $close_datetime) {
                    $is_closed = true;
                    $notification = [
                        'title' => 'Store Closed',
                        'message' => 'The store is not open at the selected time. Our working hours are from ' . $open_datetime->format('H:i') . ' to ' . $close_datetime->format('H:i') . '.',
                        'end_datetime' => $close_datetime->format('c')
                    ];
                }
            }
        } else {
            // Nuk ka operational hours për ditën e rezervimit
            $is_closed = true;
            $notification = [
                'title' => 'Store Closed',
                'message' => 'No operational hours found for the selected day.',
                'end_datetime' => (new DateTime())->modify('+1 day')->format('c')
            ];
        }
    }

    if ($is_closed) {
        echo json_encode(['status' => 'error', 'message' => $notification['message']]);
        exit;
    }

    // Insert rezervimin në bazën e të dhënave
    $stmt = $pdo->prepare("INSERT INTO reservations (client_name, client_email, reservation_date, reservation_time, number_of_people, message) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$client_name, $client_email, $reservation_date, $reservation_time, $number_of_people, $reservation_message]);

    echo json_encode(['status' => 'success', 'message' => 'Rezervimi juaj u bë me sukses! Ne do t’ju kontaktojmë së shpejti për konfirmim.']);
    exit;
} catch (PDOException $e) {
    // Log error
    $timestamp = date('Y-m-d H:i:s');
    $error_message = "[$timestamp] Failed to process reservation: " . $e->getMessage();
    file_put_contents('errors.md', "### [$timestamp] Error\n\n**Message:** $error_message\n\n---\n\n", FILE_APPEND | LOCK_EX);
    echo json_encode(['status' => 'error', 'message' => 'Gabim gjatë procesimit të rezervimit. Ju lutem provoni më vonë.']);
    exit;
}
