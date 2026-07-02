<?php
// ajax/get_receipt.php
header('Content-Type: application/json');
session_start();
require_once("../db.php"); // provides $conn (PDO)

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status'=>'error','message'=>'No business selected']);
    exit;
}

$business_id = (int)$_SESSION['business_id'];
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status'=>'error','message'=>'Invalid sale id']);
    exit;
}

/* =======================
   Fetch sale
======================= */
$stmt = $conn->prepare("
    SELECT *
    FROM sales
    WHERE id = :id AND business_id = :business_id
    LIMIT 1
");
$stmt->execute([
    ':id' => $id,
    ':business_id' => $business_id
]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    echo json_encode(['status'=>'error','message'=>'Sale not found']);
    exit;
}

/* =======================
   Fetch sale items
======================= */
$itStmt = $conn->prepare("
    SELECT si.*, p.name AS product_name, p.description
    FROM sale_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = :sale_id
");
$itStmt->execute([':sale_id' => $id]);
$items = $itStmt->fetchAll(PDO::FETCH_ASSOC);

/* =======================
   Fetch business info
======================= */
$bStmt = $conn->prepare("
    SELECT business_name, business_address, business_email, business_phone
    FROM businesses
    WHERE id = :id
    LIMIT 1
");
$bStmt->execute([':id' => $business_id]);
$biz = $bStmt->fetch(PDO::FETCH_ASSOC);

/* =======================
   Prepare display data
======================= */
$businessName    = htmlspecialchars($biz['business_name'] ?? 'Business');
$businessAddress = nl2br(htmlspecialchars($biz['business_address'] ?? ''));
$businessEmail   = htmlspecialchars($biz['business_email'] ?? '');
$businessPhone   = htmlspecialchars($biz['business_phone'] ?? '');

$cashier = $_SESSION['user_name'] ?? ($_SESSION['admin'] ?? 'Cashier');

$dateNow = htmlspecialchars($sale['created_at']);

/* ✅ USE STORED TOTALS — DO NOT RECOMPUTE */
$total_excl_vat        = (float)$sale['total_amount'];
$vat_amount            = (float)$sale['vat_amount'];
$total_including_vat   = (float)$sale['total_including_vat'];

$total_excl_fmt = number_format($total_excl_vat, 2);
$vat_amount_fmt = number_format($vat_amount, 2);
$total_incl_fmt = number_format($total_including_vat, 2);

/* =======================
   Build receipt HTML
======================= */
$receipt = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt {$sale['sale_number']}</title>
<style>
body { font-family: monospace; padding:10px; }
.header { text-align:center; }
.table { width:100%; border-collapse: collapse; font-size:13px; }
.table th, .table td { padding:6px; border-bottom:1px dashed #ccc; }
.totals { font-weight:700; margin-top:8px; display:flex; justify-content:space-between; }
.small { font-size:12px; color:#444; }
</style>
</head>
<body>

<div class="header">
  <h2>RECEIPT</h2>
  <div><strong>{$businessName}</strong></div>
  <div class="small">{$businessAddress}</div>
HTML;

if ($businessEmail || $businessPhone) {
    $receipt .= "<div class='small'>{$businessEmail} {$businessPhone}</div>";
}

$receipt .= <<<HTML
  <div class="small">Date: {$dateNow}</div>
</div>

<hr>

<div>Sale#: {$sale['sale_number']}</div>

<table class="table">
<thead>
<tr>
  <th align="left">Item</th>
  <th align="left">Desc</th>
  <th>Q</th>
  <th align="right">Total</th>
</tr>
</thead>
<tbody>
HTML;

foreach ($items as $it) {
    $name = htmlspecialchars($it['product_name'] ?? '');
    $desc = htmlspecialchars($it['description'] ?? '');
    $qty  = rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.');
    $rowTotal = number_format((float)$it['subtotal'], 2);

    // Optional VAT label per item (uses stored vat_amount)
    $vatLabel = ((float)($it['vat_amount'] ?? 0) > 0)
        ? ''
        : ' <small>(VAT Exempt)</small>';

    $receipt .= "
    <tr>
      <td>{$name}{$vatLabel}</td>
      <td>{$desc}</td>
      <td align='center'>{$qty}</td>
      <td align='right'>{$rowTotal}</td>
    </tr>";
}

$receipt .= <<<HTML
</tbody>
</table>

<div class="totals">
  <div>VAT</div>
  <div>KES {$vat_amount_fmt}</div>
</div>

<div class="totals">
  <div>Total (Excl. VAT)</div>
  <div>KES {$total_excl_fmt}</div>
</div>

<div class="totals">
  <div>Total (Incl. VAT)</div>
  <div>KES {$total_incl_fmt}</div>
</div>

<div>Payment: {$sale['payment_type']}</div>
HTML;

if (!empty($sale['customer_name'])) {
    $receipt .= "<div>Customer: " . htmlspecialchars($sale['customer_name']) . "</div>";
}

$receipt .= <<<HTML
<div>Cashier: {$cashier}</div>

<p style="text-align:center;margin-top:12px;">
  Thank you for shopping with us!
</p>

</body>
</html>
HTML;

echo json_encode([
    'status' => 'ok',
    'receipt_html' => $receipt
]);
exit;
