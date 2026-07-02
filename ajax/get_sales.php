<?php
// ajax/get_sales.php
header('Content-Type: application/json');
session_start();
require_once("../db.php"); // $conn is PDO connection

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

$business_id = intval($_SESSION['business_id']);

// Safe inputs
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$payment = $_GET['payment'] ?? '';
$q = trim($_GET['q'] ?? '');

// Build WHERE clause and parameters
$where = " WHERE business_id = :business_id ";
$params = ['business_id' => $business_id];

if ($from !== '') {
    $where .= " AND DATE(created_at) >= :from_date ";
    $params['from_date'] = $from;
}

if ($to !== '') {
    $where .= " AND DATE(created_at) <= :to_date ";
    $params['to_date'] = $to;
}

if ($payment !== '') {
    $where .= " AND (payment_type = :payment OR payment_method = :payment) ";
    $params['payment'] = $payment;
}

if ($q !== '') {
    $where .= " AND (sale_number LIKE :search_q OR customer_name LIKE :search_q) ";
    $params['search_q'] = "%$q%";
}

if (!empty($_GET['cashier'])) {
    $where .= " AND user_name LIKE :cashier ";
    $params['cashier'] = '%' . $_GET['cashier'] . '%';
}

// Prepare SQL query
$sql = "SELECT id, sale_number, total_amount, payment_type, customer_name, vat_amount, user_name, 
        strftime('%Y-%m-%d %H:%M:%S', created_at) as created_at
        FROM sales
        {$where}
        ORDER BY created_at DESC
        LIMIT 1000";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_sum = 0;
    foreach ($data as $row) {
        $total_sum += floatval($row['total_amount']);
    }

    echo json_encode([
        'status' => 'ok',
        'data' => $data,
        'count' => count($data),
        'total_sum' => round($total_sum, 2)
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

exit;
