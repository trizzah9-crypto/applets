<?php
// ajax/clear_credit.php
session_start();
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing customer ID']);
    exit;
}

$customer_id = intval($_POST['id']);

// --- Get current balance for this customer (we do NOT require business_id in transactions table)
$sql = "SELECT COALESCE(SUM(amount), 0) AS balance FROM customer_account_transactions WHERE customer_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$res = $stmt->get_result();
$balance = (float) ($res->fetch_assoc()['balance'] ?? 0.0);
$stmt->close();

if ($balance <= 0.0) {
    echo json_encode(['status' => 'error', 'message' => 'This customer has no credit to clear']);
    exit;
}

// Insert clearing transaction (negative amount) and preserve history.
// We insert: customer_id, type, amount (negative), note. If your table has sale_id or business_id columns,
// they can be NULL / omitted — this query inserts only the columns shown in your dump.
$type = 'credit_clear';
$amount = -1.0 * $balance;
$note = 'Credit cleared';

$insertSql = "INSERT INTO customer_account_transactions (customer_id, type, amount, note) VALUES (?, ?, ?, ?)";
$stmt2 = $conn->prepare($insertSql);
if (!$stmt2) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt2->bind_param("isds", $customer_id, $type, $amount, $note);
$ok = $stmt2->execute();
if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $stmt2->error]);
    exit;
}
$stmt2->close();

echo json_encode(['status' => 'ok', 'cleared_amount' => round($balance, 2)]);
exit;
