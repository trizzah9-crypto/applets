<?php
// ajax/get_sale_details.php
header('Content-Type: application/json');
session_start();
require_once("../dbconnect.php");

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status'=>'error','message'=>'No business selected']);
    exit;
}
$business_id = intval($_SESSION['business_id']);
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status'=>'error','message'=>'Invalid sale id']);
    exit;
}

// fetch sale
$stmt = $conn->prepare("SELECT * FROM sales WHERE id = ? AND business_id = ? LIMIT 1");
$stmt->bind_param('ii', $id, $business_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) {
    echo json_encode(['status'=>'error','message'=>'Sale not found']);
    exit;
}

// fetch items with product info if available
$itStmt = $conn->prepare("
    SELECT si.*, p.name AS product_name, p.description AS product_description
    FROM sale_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = ?
");
$itStmt->bind_param('i', $id);
$itStmt->execute();
$itemsRes = $itStmt->get_result();
$items = $itemsRes->fetch_all(MYSQLI_ASSOC);
$itStmt->close();

// fetch business info
$bStmt = $conn->prepare("SELECT business_name, business_address, business_email, business_phone FROM businesses WHERE id = ? LIMIT 1");
$bStmt->bind_param('i', $business_id);
$bStmt->execute();
$biz = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

$businessName = htmlspecialchars($biz['business_name'] ?? 'Business');
$businessAddress = nl2br(htmlspecialchars($biz['business_address'] ?? ''));
$businessEmail = htmlspecialchars($biz['business_email'] ?? '');
$businessPhone = htmlspecialchars($biz['business_phone'] ?? '');

$cashier = htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['admin'] ?? 'Cashier');

// Build modal HTML (safe escaping)
$modalHtml = "";
$modalHtml .= "<div class='mb-2'><strong>Sale#: </strong>".htmlspecialchars($sale['sale_number'])."</div>";
$modalHtml .= "<div class='mb-2'><strong>Date: </strong>".htmlspecialchars($sale['created_at'])."</div>";
$modalHtml .= "<div class='mb-2'><strong>Customer: </strong>".htmlspecialchars($sale['customer_name'] ?: 'Walk-in')."</div>";
$modalHtml .= "<div class='mb-2'><strong>Payment: </strong>".htmlspecialchars($sale['payment_type'])."</div>";
$modalHtml .= "<hr>";
$modalHtml .= "<div><strong>Items</strong></div>";
$modalHtml .= "<table class='table table-sm table-bordered mt-2'><thead><tr><th>Item</th><th>Description</th><th style='width:80px'>Q</th><th style='width:120px' class='text-end'>Price</th><th style='width:120px' class='text-end'>Subtotal</th></tr></thead><tbody>";

foreach($items as $it){
    $iname = htmlspecialchars($it['product_name'] ?? 'Item');
    $idesc = htmlspecialchars($it['product_description'] ?? ($it['description'] ?? ''));
    $iq = intval($it['quantity']);
    $ip = number_format($it['price'],2);
    $isub = number_format($it['subtotal'],2);
    $modalHtml .= "<tr>
        <td>$iname</td>
        <td style='font-size:12px;color:#444;'>$idesc</td>
        <td class='text-center'>$iq</td>
        <td class='text-end'>$ip</td>
        <td class='text-end'>$isub</td>
    </tr>";
}
$modalHtml .= "</tbody></table>";

$modalHtml .= "<div class='d-flex justify-content-between mt-3'><div><strong>Cashier: </strong>$cashier</div><div class='fw-bold'>Total: KES ".number_format($sale['total_amount'],2)."</div></div>";
$modalHtml .= "<div class='mt-2 small text-muted'>Served by: $cashier</div>";

// Build printable HTML (clean, minimal)
$printHtml = "<!doctype html><html><head><meta charset='utf-8'><title>Receipt {$sale['sale_number']}</title>";
$printHtml .= "<style>body{font-family:monospace;padding:12px} .table{width:100%;border-collapse:collapse}.table th,.table td{padding:6px;border-bottom:1px dashed #ccc;}</style></head><body>";
$printHtml .= "<h2 style='margin:0'>RECEIPT</h2><div style='margin-bottom:6px'><strong>".htmlspecialchars($businessName)."</strong></div>";
$printHtml .= "<div style='font-size:13px;margin-bottom:6px'>Location: $businessAddress</div>";
if($businessEmail || $businessPhone) $printHtml .= "<div style='font-size:12px;margin-bottom:8px'>$businessEmail $businessPhone</div>";
$printHtml .= "<div style='font-size:13px;margin-bottom:6px'>Date: ".htmlspecialchars($sale['created_at'])."</div>";
$printHtml .= "<div>Sale#: ".htmlspecialchars($sale['sale_number'])."</div>";
$printHtml .= "<table class='table' style='margin-top:8px'><thead><tr><th style='text-align:left'>Item</th><th style='text-align:left'>Desc</th><th>Q</th><th style='text-align:right'>Total</th></tr></thead><tbody>";
foreach($items as $it){
    $iname = htmlspecialchars($it['product_name'] ?? 'Item');
    $idesc = htmlspecialchars($it['product_description'] ?? ($it['description'] ?? ''));
    $iq = intval($it['quantity']);
    $isub = number_format($it['subtotal'],2);
    $printHtml .= "<tr><td>$iname</td><td style='font-size:12px;color:#444;'>$idesc</td><td style='text-align:center'>$iq</td><td style='text-align:right'>$isub</td></tr>";
}
$printHtml .= "</tbody></table>";
$printHtml .= "<div style='display:flex;justify-content:space-between;margin-top:10px'><div>Payment: ".htmlspecialchars($sale['payment_type'])."</div><div><strong>KES ".number_format($sale['total_amount'],2)."</strong></div></div>";
$printHtml .= "<div style='margin-top:10px'>Cashier: $cashier</div>";
$printHtml .= "<p style='text-align:center;margin-top:12px'>Thank you for shopping with us!</p>";
$printHtml .= "</body></html>";

// Return JSON
echo json_encode(['status'=>'ok','html'=>$modalHtml,'print_html'=>$printHtml]);
exit;
