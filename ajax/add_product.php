<?php
include '../db.php';
session_start();

// These MUST come from session (after user selects business and store)
$business_id = $_SESSION['business_id'] ?? null;
$store_id    = $_SESSION['store_id'] ?? null;

// Form inputs
$name        = $conn->real_escape_string($_POST['name']);
$barcode     = $conn->real_escape_string($_POST['barcode']);
$cost_price  = floatval($_POST['cost_price']);
$sell_price  = floatval($_POST['selling_price']);
$qty         = intval($_POST['stock_qty']);
$category    = $conn->real_escape_string($_POST['category']); // text category
$unit        = $conn->real_escape_string($_POST['unit']);
$description = $conn->real_escape_string($_POST['description']);

// Step 1: Check if product exists under SAME business + same barcode
$stmt = $conn->prepare("
    SELECT id, stock_qty 
    FROM products 
    WHERE barcode = ? AND business_id = ?
");
$stmt->bind_param('si', $barcode, $business_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {

    // Product exists → update
    $product = $result->fetch_assoc();
    $new_qty = $product['stock_qty'] + $qty;

    $update = $conn->prepare("
        UPDATE products 
        SET 
            name = ?, 
            cost_price = ?, 
            selling_price = ?, 
            stock_qty = ?, 
            category = ?, 
            unit = ?, 
            description = ? 
        WHERE id = ?
    ");

    $update->bind_param(
        'sddisssi',
        $name,
        $cost_price,
        $sell_price,
        $new_qty,
        $category,
        $unit,
        $description,
        $product['id']
    );

    $update->execute();
    $update->close();

    echo json_encode([
        'status' => 'updated',
        'message' => 'Stock updated for existing product.'
    ]);
} else {

    // Insert new product
    $insert = $conn->prepare("
        INSERT INTO products 
        (business_id, store_id, name, barcode, cost_price, selling_price, stock_qty, category, unit, description, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $insert->bind_param(
        'iissddisss',
        $business_id,
        $store_id,
        $name,
        $barcode,
        $cost_price,
        $sell_price,
        $qty,
        $category,
        $unit,
        $description
    );

    $insert->execute();
    $insert->close();

    echo json_encode([
        'status' => 'inserted',
        'message' => 'New product added.'
    ]);
}

$stmt->close();
?>
