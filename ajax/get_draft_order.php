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
$draft_id = isset($_GET['draft_id']) ? (int)$_GET['draft_id'] : 0;

if ($draft_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid draft ID']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, order_number, order_name, cart_data, total_amount, discount, apply_vat, updated_at
        FROM draft_orders
        WHERE id = ? AND business_id = ? AND status = 'draft'
        LIMIT 1
    ");
    $stmt->execute([$draft_id, $business_id]);

    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        echo json_encode(['status' => 'error', 'message' => 'Draft order not found']);
        exit;
    }

    $cartItems = json_decode($draft['cart_data'], true);
    if (!is_array($cartItems)) {
        $cartItems = [];
    }

    echo json_encode([
        'status' => 'ok',
        'draft' => [
            'id' => (int)$draft['id'],
            'order_number' => $draft['order_number'],
            'order_name' => $draft['order_name'],
            'cart_items' => $cartItems,
            'total_amount' => $draft['total_amount'],
            'discount' => $draft['discount'],
            'apply_vat' => (int)$draft['apply_vat'],
            'updated_at' => $draft['updated_at']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}