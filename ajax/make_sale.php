<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../db.php'; // assumes $conn is your PDO connection
session_start();

header('Content-Type: application/json');

// Ensure business is selected
if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

$business_id = $_SESSION['business_id'];
$applyVat = isset($_POST['apply_vat']) && $_POST['apply_vat'] == 1;
$vatRate = $applyVat ? 0.16 : 0;


// Receive POST data
$cart_json = $_POST['cart'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'cash';
$customer_name = $_POST['customer_name'] ?? '';
$cash_received = floatval($_POST['cash_received'] ?? 0);
$credit_customer_id = intval($_POST['credit_customer_id'] ?? 0);

// New: Discount from frontend
$discount = floatval($_POST['discount'] ?? 0);
if ($discount < 0) $discount = 0;

if (!$cart_json) {
    echo json_encode(['status' => 'error', 'message' => 'Empty cart']);
    exit;
}

$cart = json_decode($cart_json, true);
if (!$cart || count($cart) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid cart']);
    exit;
}

try {
    $conn->beginTransaction();

    // Prepare statement to check full product details for this business
    $stockCheckStmt = $conn->prepare("SELECT name, barcode, description, stock_qty FROM products WHERE id = ? AND business_id = ?");

    // Check stock availability
    foreach ($cart as $item) {
        $pid = intval($item['id']);
        $qty = floatval($item['qty']);  // decimal qty

        $stockCheckStmt->execute([$pid, $business_id]);
        $row = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $item_name = $row['name'] ?? '';
            $item_barcode = $row['barcode'] ?? '';
            $item_description = $row['description'] ?? '';
            $available_stock = floatval($row['stock_qty']); // decimal stock

            if ($available_stock < $qty) {
                throw new Exception(
                    "Insufficient stock for product ID {$pid}. " .
                    "Name: {$item_name}. " .
                    "Barcode: {$item_barcode}. " .
                    "Description: {$item_description}. " .
                    "Available: {$available_stock}, Requested: {$qty}"
                );
            }
        } else {
            throw new Exception("Product ID {$pid} not found for this business");
        }
    }

    // Calculate total amount before discount
    $total = 0;
    foreach ($cart as $item) {
        $qty = floatval($item['qty']);
        $price = floatval($item['selling_price'] ?? $item['price'] ?? 0);
        $total += $qty * $price;
    }
    $total = round($total, 2);

    // Calculate VAT amount on total BEFORE discount
    $vat_amount = round($total * $vatRate, 2);

    // Apply discount
    $total_after_discount = $total - $discount;
    if ($total_after_discount < 0) {
        $total_after_discount = 0;
    }

    // Calculate total including VAT after discount
    // VAT is on original total before discount; discount applies after VAT calculation
    $total_including_vat = round($total_after_discount + $vat_amount, 2);

    // If payment method is credit, ensure a customer is selected
    if ($payment_method === 'credit') {
        if ($credit_customer_id <= 0) {
            throw new Exception("You must select a customer for a credit sale.");
        }
        // Verify customer belongs to this business
        $custCheck = $conn->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ?");
        $custCheck->execute([$credit_customer_id, $business_id]);
        if (!$custCheck->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("Selected customer not found for this business.");
        }
    }

    // Insert sale record with business_id and discount info + VAT info
    $stmt = $conn->prepare("
        INSERT INTO sales 
        (sale_number, total_amount, payment_type, customer_name, business_id, discount, user_name, created_at, vat_rate, vat_amount, total_including_vat)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $sale_number = uniqid('S');
    $created_at = date('Y-m-d H:i:s');  // PHP datetime for created_at

    $stmt->execute([
        $sale_number,
        $total_after_discount,
        $payment_method,
        $customer_name,
        $business_id,
        $discount,
        $_SESSION['user_name'] ?? 'Unknown',
        $created_at,
        $vatRate * 100,    // Store as percentage 16
        $vat_amount,
        $total_including_vat
    ]);

    $sale_id = $conn->lastInsertId();

    // Prepare statements for sale items and stock update
    $stmtItem = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal, cost_price) VALUES (?, ?, ?, ?, ?, ?)");
    $updateStock = $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND business_id = ?");
    $costStmt = $conn->prepare("SELECT cost_price FROM products WHERE id = ? AND business_id = ?");

    foreach ($cart as $item) {
        $pid = intval($item['id']);
        $qty = floatval($item['qty']);
        $price = floatval($item['selling_price'] ?? $item['price'] ?? 0);
        $subtotal = round($price * $qty, 2);

        $costStmt->execute([$pid, $business_id]);
        $costRow = $costStmt->fetch(PDO::FETCH_ASSOC);
        $cost_price = floatval($costRow['cost_price'] ?? 0);

        $stmtItem->execute([$sale_id, $pid, $qty, $price, $subtotal, $cost_price]);
        $updateStock->execute([$qty, $pid, $business_id]);
    }

    // Handle credit payment method updates
    if ($payment_method === 'credit') {
        $now = date('Y-m-d H:i:s');

        $upd = $conn->prepare("UPDATE customer_accounts SET balance = balance + ?, updated_at = ? WHERE customer_id = ?");
        $upd->execute([$total_after_discount, $now, $credit_customer_id]);

        if ($upd->rowCount() === 0) {
            $ins = $conn->prepare("INSERT INTO customer_accounts (customer_id, balance, updated_at) VALUES (?, ?, ?)");
            $ins->execute([$credit_customer_id, $total_after_discount, $now]);
        }

        $tstmt = $conn->prepare("
            INSERT INTO customer_account_transactions (customer_id, sale_id, type, amount, note, created_at)
            VALUES (?, ?, 'credit_purchase', ?, 'Purchased on credit', ?)
        ");
        $tstmt->execute([$credit_customer_id, $sale_id, $total_after_discount, $created_at]);
    }

    // Fetch business info dynamically for receipt
    $businessInfoStmt = $conn->prepare("
        SELECT business_name, business_address, business_email, business_phone 
        FROM businesses WHERE id = ?
    ");
    $businessInfoStmt->execute([$business_id]);
    $businessInfo = $businessInfoStmt->fetch(PDO::FETCH_ASSOC);

    $businessName = $businessInfo['business_name'] ?? 'Your Business Name';
    $businessAddress = $businessInfo['business_address'] ?? 'Business Location';
    $businessEmail = $businessInfo['business_email'] ?? '';
    $businessPhone = $businessInfo['business_phone'] ?? '';

    // Get cashier's name from session
    $cashier_name = $_SESSION['user_name'] ?? 'Cashier';

    // Generate receipt HTML
    $receipt = "<div style='font-family:monospace; padding:10px; width:350px; text-align:center;'>";
    $receipt .= "<h2 style='margin:0; margin-bottom:5px;'>RECEIPT</h2>";
    $receipt .= "<h3 style='margin:0;'>" . htmlspecialchars($businessName) . "</h3>";
    $receipt .= "<small style='display:block; margin-bottom:3px;'>Location: " . nl2br(htmlspecialchars($businessAddress)) . "</small>";
    if ($businessEmail || $businessPhone) {
        $receipt .= "<small style='display:block; margin-bottom:8px;'>";
        if ($businessEmail) $receipt .= "Email: " . htmlspecialchars($businessEmail) . " ";
        if ($businessPhone) $receipt .= "Phone: " . htmlspecialchars($businessPhone);
        $receipt .= "</small>";
    }
    $receipt .= "<div style='text-align:left; font-size:12px; margin-bottom:6px;'>Date: {$created_at}</div>";
    $receipt .= "<hr style='border:1px dashed #000; margin:6px 0;'>";

    $receipt .= "<div style='text-align:left;'>Sale#: {$sale_number}</div>";
    $receipt .= "<table style='width:100%; border-collapse:collapse; margin-top:8px; font-size:13px;'>";
    $receipt .= "<tr><th style='text-align:left'>Item</th><th style='text-align:left'>Description</th><th>Q</th><th style='text-align:right'>Total</th></tr>";

    foreach ($cart as $item) {
        $name = htmlspecialchars($item['name']);
        $description = htmlspecialchars($item['description'] ?? '');
        $qty = floatval($item['qty']);
        $price = floatval($item['selling_price'] ?? $item['price'] ?? 0);
        $rowTotal = number_format($price * $qty, 2);
        $formattedQty = rtrim(rtrim(number_format($qty, 2), '0'), '.');

        $receipt .= "<tr>";
        $receipt .= "<td style='text-align:left; vertical-align:top;'>{$name}</td>";
        $receipt .= "<td style='text-align:left; vertical-align:top; font-size:11px; color:#555;'>{$description}</td>";
        $receipt .= "<td style='text-align:center; vertical-align:top;'>{$formattedQty}</td>";
        $receipt .= "<td style='text-align:right; vertical-align:top;'>{$rowTotal}</td>";
        $receipt .= "</tr>";
    }
    $receipt .= "</table>";

    $receipt .= "<hr>";
    if ($discount > 0) {
        $receipt .= "<div style='display:flex; justify-content:space-between'><div>Discount</div><div>- KES " . number_format($discount, 2) . "</div></div>";
    }
    $receipt .= "<div style='display:flex; justify-content:space-between'><div>VAT (16%)</div><div>KES " . number_format($vat_amount, 2) . "</div></div>";
    $receipt .= "<div style='display:flex; justify-content:space-between'><div><strong>Total (Excl. VAT & Discount)</strong></div><div><strong>KES " . number_format($total, 2) . "</strong></div></div>";
    $receipt .= "<div style='display:flex; justify-content:space-between'><div><strong>Total (Incl. VAT & Discount)</strong></div><div><strong>KES " . number_format($total_including_vat, 2) . "</strong></div></div>";
    $receipt .= "<div style='display:flex; justify-content:space-between'><div>Payment</div><div>" . htmlspecialchars($payment_method) . "</div></div>";

    if ($payment_method === 'cash') {
        $receipt .= "<div style='display:flex; justify-content:space-between'><div>Cash</div><div>KES " . number_format($cash_received, 2) . "</div></div>";
        $change = max(0, $cash_received - $total_including_vat);
        $receipt .= "<div style='display:flex; justify-content:space-between'><div>Change</div><div>KES " . number_format($change, 2) . "</div></div>";
    } elseif ($payment_method === 'credit') {
        $custName = '';
        $cq = $conn->prepare("SELECT name, phone FROM customers WHERE id = ? LIMIT 1");
        $cq->execute([$credit_customer_id]);
        if ($crow = $cq->fetch(PDO::FETCH_ASSOC)) {
            $custName = htmlspecialchars($crow['name']) . (!empty($crow['phone']) ? ' (' . htmlspecialchars($crow['phone']) . ')' : '');
        }
        $receipt .= "<div style='margin-top:6px; text-align:left;'>Customer on credit: " . ($custName ?: 'N/A') . "</div>";
    }

    if (!empty($customer_name)) {
        $receipt .= "<div style='margin-top:6px; text-align:left;'>Served to: " . htmlspecialchars($customer_name) . "</div>";
    }

    // Cashier name
    $receipt .= "<div style='margin-top:6px; text-align:left;'>Cashier: " . htmlspecialchars($cashier_name) . "</div>";

    $receipt .= "<p style='text-align:center; margin-top:8px;'>Thank you for shopping with us!</p>";
    $receipt .= "</div>";

    $conn->commit();

    echo json_encode(['status' => 'ok', 'receipt_html' => $receipt]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
}
