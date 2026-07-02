<?php
session_start();
include '../db.php';  // Adjust path as necessary

header('Content-Type: application/json');

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

$business_id = $_SESSION['business_id'];
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid customer ID']);
    exit;
}

// Verify customer belongs to this business
$stmt = $conn->prepare("SELECT id, name, phone, address FROM customers WHERE id = ? AND business_id = ?");
$stmt->bind_param("ii", $id, $business_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
    exit;
}
$customer = $result->fetch_assoc();
$stmt->close();

// Get current balance from customer_accounts table
$stmt = $conn->prepare("SELECT balance FROM customer_accounts WHERE customer_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($balance);
if (!$stmt->fetch()) {
    $balance = 0;  // Default if no account record
}
$stmt->close();

// Get transactions for this customer (latest first)
$stmt = $conn->prepare("SELECT created_at, type, amount, note FROM customer_account_transactions WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = [
        'date' => $row['created_at'],
        'type' => $row['type'],
        'amount' => floatval($row['amount']),
        'note' => $row['note']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'status' => 'ok',
    'customer' => $customer,
    'credit' => floatval($balance),
    'transactions' => $transactions
]);
exit;
