<?php
// ajax/export_sales_csv.php
session_start();
require_once("../db.php"); // must create $conn as PDO

if (!isset($_SESSION['business_id'])) {
    die("No business selected");
}

$business_id = (int)$_SESSION['business_id'];

$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
$payment = $_GET['payment'] ?? '';
$q       = trim($_GET['q'] ?? '');

/*
|--------------------------------------------------------------------------
| Build WHERE clause (same as get_sales.php)
|--------------------------------------------------------------------------
*/
$where  = " WHERE business_id = :business_id ";
$params = [
    ':business_id' => $business_id
];

if ($from !== '') {
    $where .= " AND DATE(created_at) >= :from ";
    $params[':from'] = $from;
}

if ($to !== '') {
    $where .= " AND DATE(created_at) <= :to ";
    $params[':to'] = $to;
}

if ($payment !== '') {
    $where .= " AND (payment_type = :payment OR payment_method = :payment) ";
    $params[':payment'] = $payment;
}

if ($q !== '') {
    $where .= " AND (sale_number LIKE :q OR customer_name LIKE :q) ";
    $params[':q'] = "%{$q}%";
}

/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT 
        id,
        sale_number,
        strftime('%Y-%m-%d %H:%M:%S', created_at) AS created_at,
        customer_name,
        payment_type,
        vat_amount,
        total_amount
    FROM sales
    {$where}
    ORDER BY created_at DESC
    LIMIT 10000
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

/*
|--------------------------------------------------------------------------
| CSV Output
|--------------------------------------------------------------------------
*/
$filename = "sales_report_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Sale Number', 'Date', 'Customer', 'VAT Amount', 'Payment', 'Amount']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        $row['id'],
        $row['sale_number'],
        $row['created_at'],
        $row['customer_name'],
        $row['vat_amount'],
        $row['payment_type'],
        $row['total_amount']
    ]);
}

fclose($out);
exit;
