<?php
require("../db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$business_id = (int)$_SESSION['business_id'];

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid customer ID']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = :id AND business_id = :business_id");
    $stmt->execute([
        'id' => $id,
        'business_id' => $business_id
    ]);

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
}