<?php
// get_category_options.php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if ($category_id > 0) {
    $options = fetchCategoryOptions($pdo, $category_id);
    echo json_encode(['success' => true, 'options' => $options]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid category ID.']);
}
