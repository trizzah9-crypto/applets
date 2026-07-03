<?php
header('Content-Type: application/json');

session_start();

require_once("../db.php");

if (!isset($_SESSION['business_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No business selected'
    ]);
    exit;
}

$business_id = (int)$_SESSION['business_id'];

$from     = trim($_GET['from'] ?? '');
$to       = trim($_GET['to'] ?? '');
$payment  = trim($_GET['payment'] ?? '');
$status   = trim($_GET['status'] ?? '');
$q        = trim($_GET['q'] ?? '');
$cashier  = trim($_GET['cashier'] ?? '');

$where = [];
$params = [];

$where[] = "s.business_id = :business_id";
$params['business_id'] = $business_id;

if ($from !== '') {
    $where[] = "DATE(s.created_at) >= :from_date";
    $params['from_date'] = $from;
}

if ($to !== '') {
    $where[] = "DATE(s.created_at) <= :to_date";
    $params['to_date'] = $to;
}

if ($payment !== '') {
    $where[] = "(s.payment_type = :payment OR s.payment_method = :payment)";
    $params['payment'] = $payment;
}

if ($status !== '') {
    $where[] = "s.status = :status";
    $params['status'] = $status;
}

if ($cashier !== '') {
    $where[] = "s.user_name LIKE :cashier";
    $params['cashier'] = "%{$cashier}%";
}

if ($q !== '') {
    $where[] = "(
                    s.sale_number LIKE :search
                    OR s.invoice_number LIKE :search
                    OR s.customer_name LIKE :search
                )";

    $params['search'] = "%{$q}%";
}

$whereSql = implode(' AND ', $where);

$sql = "
SELECT
    s.id,
    s.sale_number,
    s.invoice_number,
    s.created_at,
    s.user_name,
    s.customer_name,

    s.payment_type,
    s.payment_method,

    s.status,

    s.discount,

    s.vat_rate,
    s.vat_amount,

    s.total_before_vat,
    s.total_amount,
    s.total_including_vat,

    s.paid_amount,
    s.balance_due,
    s.change_amount,

    s.refunded_amount,
    s.refunded_at,

    COALESCE(SUM(si.profit),0) AS profit,
    COALESCE(SUM(si.line_cost),0) AS line_cost,
    COALESCE(SUM(si.line_total),0) AS line_total

FROM sales s

LEFT JOIN sale_items si
ON s.id = si.sale_id

WHERE {$whereSql}

GROUP BY s.id

ORDER BY s.created_at DESC

LIMIT 1000
";

try {

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'count' => 0,
        'total_amount' => 0,
        'vat_amount' => 0,
        'discount' => 0,
        'profit' => 0,
        'balance_due' => 0,
        'refunded_amount' => 0
    ];

    foreach ($rows as &$row) {

        $grandTotal = $row['total_including_vat'];

        if ($grandTotal <= 0) {
            $grandTotal = $row['total_amount'];
        }

        $row['grand_total'] = $grandTotal;

        $summary['count']++;
        $summary['total_amount'] += (float)$grandTotal;
        $summary['vat_amount'] += (float)$row['vat_amount'];
        $summary['discount'] += (float)$row['discount'];
        $summary['profit'] += (float)$row['profit'];
        $summary['balance_due'] += (float)$row['balance_due'];
        $summary['refunded_amount'] += (float)$row['refunded_amount'];
    }

    echo json_encode([
        'status' => 'ok',
        'data' => $rows,
        'summary' => [
            'count' => $summary['count'],
            'total_amount' => round($summary['total_amount'], 2),
            'vat_amount' => round($summary['vat_amount'], 2),
            'discount' => round($summary['discount'], 2),
            'profit' => round($summary['profit'], 2),
            'balance_due' => round($summary['balance_due'], 2),
            'refunded_amount' => round($summary['refunded_amount'], 2)
        ]
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}