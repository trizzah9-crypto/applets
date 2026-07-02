<?php
// ajax/get_customer_details.php
session_start();
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing customer ID']);
    exit;
}

$customer_id = intval($_GET['id']);
$business_id = $_SESSION['business_id'] ?? null;

// 1) Fetch customer row
try {
    if ($business_id !== null) {
        $sql = "SELECT * FROM customers WHERE id = :id AND business_id = :business_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $customer_id, 'business_id' => $business_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT * FROM customers WHERE id = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$customer) {
        echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
        exit;
    }

    // 2) Compute balance
    $balSql = "SELECT COALESCE(SUM(amount), 0) AS credit FROM customer_account_transactions WHERE customer_id = :customer_id";
    $stmt = $conn->prepare($balSql);
    $stmt->execute(['customer_id' => $customer_id]);
    $balance = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['credit'] ?? 0.0);

    // 3) Fetch transactions
    $txSql = "SELECT id, sale_id, type, amount, note, created_at FROM customer_account_transactions WHERE customer_id = :customer_id ORDER BY created_at DESC";
    $stmt = $conn->prepare($txSql);
    $stmt->execute(['customer_id' => $customer_id]);
    $transactionsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $transactions = [];
    foreach ($transactionsRaw as $r) {
        $r['amount'] = (float) $r['amount'];
        $r['date'] = date('Y-m-d H:i', strtotime($r['created_at']));  // formatted date
        $transactions[] = $r;
    }

    echo json_encode([
        'status' => 'ok',
        'customer' => $customer,
        'credit' => round($balance, 2),
        'transactions' => $transactions
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
