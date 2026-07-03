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

$business_id = (int) $_SESSION['business_id'];
$cashier_id  = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

$draft_id      = isset($_POST['draft_id']) ? (int) $_POST['draft_id'] : 0;
$order_name    = trim($_POST['order_name'] ?? 'Walk In');
$cart_json     = $_POST['cart'] ?? '';
$discount      = floatval($_POST['discount'] ?? 0);
$apply_vat     = isset($_POST['apply_vat']) ? (int) $_POST['apply_vat'] : 1;

if ($discount < 0) {
    $discount = 0;
}

if ($cart_json === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty cart data']);
    exit;
}

$cart = json_decode($cart_json, true);

if (!is_array($cart)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid cart data']);
    exit;
}

try {
    // Recalculate total on server side
    $total = 0;
    foreach ($cart as $item) {
        $qty = floatval($item['qty'] ?? 0);
        $price = floatval($item['selling_price'] ?? $item['price'] ?? 0);
        $total += $qty * $price;
    }
    $total = round($total, 2);

    if ($discount > $total) {
        $discount = $total;
    }

    $now = date('Y-m-d H:i:s');

    // Generate order number only when creating a new draft
    if ($draft_id > 0) {
        $stmt = $conn->prepare("
            SELECT id, order_number
            FROM draft_orders
            WHERE id = ? AND business_id = ?
            LIMIT 1
        ");
        $stmt->execute([$draft_id, $business_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            echo json_encode(['status' => 'error', 'message' => 'Draft order not found']);
            exit;
        }

        $order_number = $existing['order_number'];

        $update = $conn->prepare("
            UPDATE draft_orders
            SET
                order_name = ?,
                cart_data = ?,
                total_amount = ?,
                discount = ?,
                apply_vat = ?,
                cashier_id = ?,
                status = 'draft',
                updated_at = ?
            WHERE id = ? AND business_id = ?
        ");

        $update->execute([
            $order_name,
            $cart_json,
            $total,
            $discount,
            $apply_vat,
            $cashier_id,
            $now,
            $draft_id,
            $business_id
        ]);
    } else {
        $order_number = 'DRAFT-' . strtoupper(uniqid());

        $insert = $conn->prepare("
            INSERT INTO draft_orders
                (business_id, order_number, order_name, cart_data, total_amount, discount, apply_vat, cashier_id, status, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)
        ");

        $insert->execute([
            $business_id,
            $order_number,
            $order_name,
            $cart_json,
            $total,
            $discount,
            $apply_vat,
            $cashier_id,
            $now,
            $now
        ]);

        $draft_id = (int) $conn->lastInsertId();
    }

    echo json_encode([
        'status' => 'ok',
        'draft_id' => $draft_id,
        'order_number' => $order_number,
        'order_name' => $order_name,
        'total_amount' => number_format($total, 2, '.', ''),
        'discount' => number_format($discount, 2, '.', ''),
        'apply_vat' => $apply_vat,
        'updated_at' => $now
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}