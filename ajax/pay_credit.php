<?php
require("../db.php");

if (!isset($_POST['id']) || !isset($_POST['amount'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$customer_id = intval($_POST['id']);
$amount = floatval($_POST['amount']);

if ($amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid amount']);
    exit;
}

// Get current credit balance (sum of all amounts: positive for purchases, negative for payments)
$q = $conn->prepare("SELECT IFNULL(SUM(amount), 0) AS balance FROM customer_account_transactions WHERE customer_id = :customer_id");
$q->execute(['customer_id' => $customer_id]);
$balance = (float) $q->fetch(PDO::FETCH_ASSOC)['balance'];

// No longer restrict payments > balance, allow overpayment (customer keeps positive balance)

$negativeAmount = -1 * $amount;
$created_at = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO customer_account_transactions (customer_id, type, amount, note, created_at) VALUES (:customer_id, 'credit_payment', :amount, 'Partial credit payment', :created_at)");

$success = $stmt->execute([
    'customer_id' => $customer_id,
    'amount' => $negativeAmount,
    'created_at' => $created_at,
]);

if ($success) {
    echo json_encode([
        'status' => 'ok',
        'message' => "Payment of " . number_format($amount, 2) . " recorded successfully."
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => "Failed to record payment."
    ]);
}
