<?php
include '../db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['error' => 'No business selected']);
    exit;
}

$business_id = $_SESSION['business_id'];

$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
if (!$barcode) {
    echo json_encode(['error' => 'Barcode missing']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, business_id, store_id, name, description, barcode, cost_price, selling_price, stock_qty, pack_size, unit, category, category_id, created_at, updated_at, pack_size
    FROM products 
    WHERE barcode = ? 
    AND business_id = ?
    LIMIT 1
");

$stmt->execute([$barcode, $business_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    echo json_encode($product);
} else {
    echo json_encode(['error' => 'Product not found']);
}
?>
