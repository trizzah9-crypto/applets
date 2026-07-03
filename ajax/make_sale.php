
<?php
session_start();

ini_set('display_errors', 0);   // Never print PHP warnings into JSON output
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['business_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'No business selected']);
        exit;
    }

    $business_id = (int)$_SESSION['business_id'];

    $applyVat = isset($_POST['apply_vat']) && (int)$_POST['apply_vat'] === 1;
    $vatRate = $applyVat ? 0.16 : 0;

    $cart_json = $_POST['cart'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $cash_received = (float)($_POST['cash_received'] ?? 0);
    $credit_customer_id = (int)($_POST['credit_customer_id'] ?? 0);
    $draft_id = (int)($_POST['draft_id'] ?? 0);
    $discount = max(0, (float)($_POST['discount'] ?? 0));

    if (!$cart_json) {
        echo json_encode(['status' => 'error', 'message' => 'Empty cart']);
        exit;
    }

    $cart = json_decode($cart_json, true);
    if (!is_array($cart) || count($cart) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid cart']);
        exit;
    }

    $conn->beginTransaction();

    // Check stock
    $stockCheckStmt = $conn->prepare("SELECT name, barcode, description, stock_qty FROM products WHERE id = ? AND business_id = ?");
    foreach ($cart as $item) {
        $pid = (int)($item['id'] ?? 0);
        $qty = (float)($item['qty'] ?? 0);

        if ($pid <= 0 || $qty <= 0) {
            throw new Exception("Invalid cart item found.");
        }

        $stockCheckStmt->execute([$pid, $business_id]);
        $row = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("Product ID {$pid} not found for this business");
        }

        $available_stock = (float)$row['stock_qty'];
        if ($available_stock < $qty) {
            throw new Exception(
                "Insufficient stock for product ID {$pid}. " .
                "Name: " . ($row['name'] ?? '') . ". " .
                "Barcode: " . ($row['barcode'] ?? '') . ". " .
                "Description: " . ($row['description'] ?? '') . ". " .
                "Available: {$available_stock}, Requested: {$qty}"
            );
        }
    }

    // Calculate total before discount
    $total = 0;
    foreach ($cart as $item) {
        $qty = (float)($item['qty'] ?? 0);
        $price = (float)($item['selling_price'] ?? $item['price'] ?? 0);
        $total += $qty * $price;
    }
    $total = round($total, 2);

    // Match frontend logic:
    // totalAfterDiscount -> VAT applied on discounted total
    $total_after_discount = max(0, $total - $discount);
    $vat_amount = round($total_after_discount * $vatRate, 2);
    $total_including_vat = round($total_after_discount + $vat_amount, 2);

    if ($payment_method === 'credit') {
        if ($credit_customer_id <= 0) {
            throw new Exception("You must select a customer for a credit sale.");
        }

        $custCheck = $conn->prepare("SELECT id FROM customers WHERE id = ? AND business_id = ?");
        $custCheck->execute([$credit_customer_id, $business_id]);

        if (!$custCheck->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("Selected customer not found for this business.");
        }
    }

    // Insert sale
    $stmt = $conn->prepare("
        INSERT INTO sales
        (sale_number, total_amount, payment_type, customer_name, business_id, discount, user_name, created_at, vat_rate, vat_amount, total_including_vat)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $sale_number = uniqid('S');
    $created_at = date('Y-m-d H:i:s');

    $stmt->execute([
        $sale_number,
        $total_after_discount,
        $payment_method,
        $customer_name,
        $business_id,
        $discount,
        $_SESSION['user_name'] ?? 'Unknown',
        $created_at,
        $vatRate * 100,
        $vat_amount,
        $total_including_vat
    ]);

    $sale_id = $conn->lastInsertId();

    // Insert items + update stock
    $stmtItem = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal, cost_price) VALUES (?, ?, ?, ?, ?, ?)");
    $updateStock = $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND business_id = ?");
    $costStmt = $conn->prepare("SELECT cost_price FROM products WHERE id = ? AND business_id = ?");

    foreach ($cart as $item) {
        $pid = (int)($item['id'] ?? 0);
        $qty = (float)($item['qty'] ?? 0);
        $price = (float)($item['selling_price'] ?? $item['price'] ?? 0);
        $subtotal = round($price * $qty, 2);

        $costStmt->execute([$pid, $business_id]);
        $costRow = $costStmt->fetch(PDO::FETCH_ASSOC);
        $cost_price = (float)($costRow['cost_price'] ?? 0);

        $stmtItem->execute([$sale_id, $pid, $qty, $price, $subtotal, $cost_price]);
        $updateStock->execute([$qty, $pid, $business_id]);
    }

    // Credit customer balance
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

    // Business info for receipt
    $businessInfoStmt = $conn->prepare("
        SELECT business_name, business_address, business_email, business_phone
        FROM businesses WHERE id = ?
    ");
    $businessInfoStmt->execute([$business_id]);
    $businessInfo = $businessInfoStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $businessName = $businessInfo['business_name'] ?? 'Your Business Name';
    $businessAddress = $businessInfo['business_address'] ?? 'Business Location';
    $businessEmail = $businessInfo['business_email'] ?? '';
    $businessPhone = $businessInfo['business_phone'] ?? '';
    $cashier_name = $_SESSION['user_name'] ?? 'Cashier';

    // Receipt HTML
    $receipt = "<div style='font-family:monospace; padding:10px; width:350px; text-align:center;'>";
    $receipt .= "<h2 style='margin:0 0 5px 0;'>RECEIPT</h2>";
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
        $name = htmlspecialchars($item['name'] ?? '');
        $description = htmlspecialchars($item['description'] ?? '');
        $qty = (float)($item['qty'] ?? 0);
        $price = (float)($item['selling_price'] ?? $item['price'] ?? 0);
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
    $receipt .= "<div style='display:flex; justify-content:space-between'><div><strong>Total After Discount</strong></div><div><strong>KES " . number_format($total_after_discount, 2) . "</strong></div></div>";
    $receipt .= "<div style='display:flex; justify-content:space-between'><div><strong>Total (Incl. VAT)</strong></div><div><strong>KES " . number_format($total_including_vat, 2) . "</strong></div></div>";
    $receipt .= "<div style='display:flex; justify-content:space-between'><div>Payment</div><div>" . htmlspecialchars($payment_method) . "</div></div>";

    if ($payment_method === 'cash') {
        $receipt .= "<div style='display:flex; justify-content:space-between'><div>Cash</div><div>KES " . number_format($cash_received, 2) . "</div></div>";
        $change = max(0, $cash_received - $total_including_vat);
        $receipt .= "<div style='display:flex; justify-content:space-between'><div>Change</div><div>KES " . number_format($change, 2) . "</div></div>";
    } elseif ($payment_method === 'credit') {
        $cq = $conn->prepare("SELECT name, phone FROM customers WHERE id = ? LIMIT 1");
        $cq->execute([$credit_customer_id]);
        $crow = $cq->fetch(PDO::FETCH_ASSOC);

        $custName = 'N/A';
        if ($crow) {
            $custName = htmlspecialchars($crow['name'] ?? '');
            if (!empty($crow['phone'])) {
                $custName .= ' (' . htmlspecialchars($crow['phone']) . ')';
            }
        }

        $receipt .= "<div style='margin-top:6px; text-align:left;'>Customer on credit: {$custName}</div>";
    }

    if ($customer_name !== '') {
        $receipt .= "<div style='margin-top:6px; text-align:left;'>Served to: " . htmlspecialchars($customer_name) . "</div>";
    }

    $receipt .= "<div style='margin-top:6px; text-align:left;'>Cashier: " . htmlspecialchars($cashier_name) . "</div>";
    $receipt .= "<p style='text-align:center; margin-top:8px;'>Thank you for shopping with us!</p>";
    $receipt .= "</div>";

    // Delete draft if sale completed
    if ($draft_id > 0) {
        $deleteDraft = $conn->prepare("DELETE FROM draft_orders WHERE id = ? AND business_id = ?");
        $deleteDraft->execute([$draft_id, $business_id]);
    }

    $conn->commit();

    echo json_encode([
        'status' => 'ok',
        'receipt_html' => $receipt
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("make_sale.php error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Server error while completing sale'
    ]);
    exit;
}

