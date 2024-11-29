<?php
require_once 'includes/db_connect.php';
session_start();

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $response['message'] = 'Invalid CSRF token.';
        echo json_encode($response);
        exit();
    }

    // Validate and decode the order data
    if (isset($_POST['order'])) {
        $order = json_decode($_POST['order'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($order)) {
            $response['message'] = 'Invalid order data.';
            echo json_encode($response);
            exit();
        }

        try {
            // Begin Transaction
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE categories SET position = :position WHERE id = :id');

            foreach ($order as $index => $item) {
                $position = $index + 1;
                $id = (int) $item['id'];
                $stmt->execute(['position' => $position, 'id' => $id]);
            }

            // Commit Transaction
            $pdo->commit();

            $response['status'] = 'success';
            $response['message'] = 'Category positions updated successfully.';
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Position Update Error: ' . $e->getMessage());
            $response['message'] = 'Failed to update positions.';
        }
    } else {
        $response['message'] = 'No order data received.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
