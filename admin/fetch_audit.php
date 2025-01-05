<?php
session_start();
require_once 'includes/db_connect.php';

function s($d)
{
    return htmlspecialchars(trim((string)$d), ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['product_id'])) {
    $product_id = (int)$_GET['product_id'];
    if ($product_id > 0) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM product_audit WHERE product_id=? ORDER BY changed_at DESC');
            $stmt->execute([$product_id]);
            $audit = $stmt->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($audit);
            exit();
        } catch (PDOException $e) {
            echo json_encode([]);
            exit();
        }
    }
}
echo json_encode([]);
exit();
?>
