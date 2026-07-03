<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';  // Make sure $conn is PDO

header('Content-Type: application/json');

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

$business_id = (int)$_SESSION['business_id'];
$store_id = isset($_SESSION['store_id']) ? (int)$_SESSION['store_id'] : 0;

$barcode = trim($_POST['barcode'] ?? '');
$name = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$category_id = !empty($_POST['category_id'])
    ? (int)$_POST['category_id']
    : null;

$description = trim($_POST['description'] ?? '');
$cost_price = floatval($_POST['cost_price'] ?? 0);
$selling_price = floatval($_POST['net_selling_price'] ?? 0);
$unit = trim($_POST['unit'] ?? '');

$stock_qty = isset($_POST['stock_qty']) ? floatval($_POST['stock_qty']) : null;
$add_stock = isset($_POST['add_stock']) ? floatval($_POST['add_stock']) : 0;

$pack_size = isset($_POST['pack_size']) ? intval($_POST['pack_size']) : null;
$pack_qty = isset($_POST['pack_qty']) ? floatval($_POST['pack_qty']) : 0;
$add_packs = isset($_POST['add_packs']) ? floatval($_POST['add_packs']) : 0;

 

// Validation
if ($selling_price < $cost_price) {
    echo json_encode(['status' => 'error', 'message' => "Selling price cannot be below cost price $selling_price"]);
    exit;
}
if ($barcode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Barcode input missing']);
    exit;
}
if ($name === '' || $cost_price <= 0 || $selling_price <= 0 || $unit === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}
if ($unit === 'pack') {
    if ($pack_size === null || $pack_size <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pack size is required and must be > 0']);
        exit;
    }
    if ($stock_qty === null) {
        $stock_qty = 0;
    }
} else {
    if ($stock_qty === null || $stock_qty < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Stock quantity is required for unit type']);
        exit;
    }
    // Reset pack fields for non-pack units
    $pack_size = null;
    $pack_qty = 0;
    $add_packs = 0;
}

// Current timestamp string for SQLite
$now = date('Y-m-d H:i:s');

// Check if product exists
try {
    $check = $conn->prepare("SELECT id, stock_qty FROM products WHERE barcode = :barcode AND business_id = :business_id LIMIT 1");
    $check->execute([
        ':barcode' => $barcode,
        ':business_id' => $business_id
    ]);
    $res = $check->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if ($res) {
    // UPDATE
    $product_id = (int)$res['id'];
    $currentStock = (float)$res['stock_qty'];

// ------------------------------
// STOCK LOGIC (EDIT MODE)
// ------------------------------

$newStock = $currentStock;

/* 🔹 ADD STOCK → increase only */
if ($unit === 'pack') {
    $piecesToAdd = max(0, $add_packs) * (int)$pack_size;
    $newStock += $piecesToAdd;
} else {
    $newStock += max(0, $add_stock);
}

/* 🔹 stock_qty → reduction only */
if ($stock_qty !== null && $stock_qty < $currentStock) {
    $reduction = $currentStock - $stock_qty;
    $newStock -= $reduction;
}

/* 🔒 Never allow negative stock */
$newStock = max(0, $newStock);


    try {
       $upd = $conn->prepare("
        UPDATE products SET
            name = :name,
            description = :description,
            cost_price = :cost_price,
            selling_price = :selling_price,
            stock_qty = :stock_qty,
            unit = :unit,
            pack_size = :pack_size,
            category = :category,
            category_id = :category_id,
            updated_at = :updated_at
        WHERE id = :id
    ");


       $upd->execute([
            ':name' => $name,
            ':description' => $description,
            ':cost_price' => $cost_price,
            ':selling_price' => $selling_price,
            ':stock_qty' => $newStock,
            ':unit' => $unit,
            ':pack_size' => $pack_size,
            ':category' => $category,
            ':category_id' => $category_id,
            ':updated_at' => $now,
            ':id' => $product_id
        ]);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
        exit;
    }

    if ($upd->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Update did not affect any rows']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'updated' => true]);
    exit;
} else {
    // INSERT
$final_stock_qty = $unit === 'pack'
    ? (float)$pack_qty * (float)$pack_size
    : (float)$stock_qty;

    try {
       $ins = $conn->prepare("
                INSERT INTO products
                    (business_id, store_id, name, description, barcode,
                     cost_price, selling_price, stock_qty, unit, pack_size, category, category_id,
                     created_at, updated_at)
                VALUES
                    (:business_id, :store_id, :name, :description, :barcode,
                     :cost_price, :selling_price, :stock_qty, :unit, :pack_size, :category, :category_id,
                     :created_at, :updated_at)
            ");


       $ins->execute([
            ':business_id' => $business_id,
            ':store_id' => $store_id,
            ':name' => $name,
            ':description' => $description,
            ':barcode' => $barcode,
            ':cost_price' => $cost_price,
            ':selling_price' => $selling_price,
            ':stock_qty' => $final_stock_qty,
            ':unit' => $unit,
            ':pack_size' => $pack_size,
            ':category' => $category,
            ':category_id' => $category_id,
            ':created_at' => $now,
            ':updated_at' => $now
        ]);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $e->getMessage()]);
        exit;
    }

    if ($ins->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Insert did not affect any rows']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'inserted' => true]);
    exit;
}
