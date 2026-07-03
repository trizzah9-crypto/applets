<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

$business_id = (int)$_SESSION['business_id'];

try {
    $stmt = $conn->prepare("
        SELECT id, order_number, order_name, total_amount, discount, apply_vat, updated_at, created_at
        FROM draft_orders
        WHERE business_id = ? AND status = 'draft'
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$business_id]);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'orders' => $orders
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}