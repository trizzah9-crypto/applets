<?php
require("../db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$customer_id = (int)($_POST['id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$mode = trim($_POST['mode'] ?? 'payment');
$note = trim($_POST['note'] ?? '');
$business_id = (int)$_SESSION['business_id'];

if ($customer_id <= 0 || $amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid parameters']);
    exit;
}

try {
    $customerStmt = $conn->prepare("SELECT id, name, credit_limit, credit_balance FROM customers WHERE id = :id AND business_id = :business_id LIMIT 1");
    $customerStmt->execute([
        'id' => $customer_id,
        'business_id' => $business_id
    ]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
        exit;
    }

    $type = 'credit_payment';
    $signedAmount = -abs($amount);

    if ($mode === 'add_credit') {
        $type = 'credit_addition';
        $signedAmount = abs($amount);
        if ($note === '') $note = 'Credit added';
    } elseif ($mode === 'reduce_credit') {
        $type = 'credit_reduction';
        $signedAmount = -abs($amount);
        if ($note === '') $note = 'Credit reduced';
    } else {
        if ($note === '') $note = 'Partial credit payment';
    }

    $created_at = date('Y-m-d H:i:s');

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO customer_account_transactions (customer_id, sale_id, type, amount, note, created_at)
        VALUES (:customer_id, NULL, :type, :amount, :note, :created_at)
    ");
    $stmt->execute([
        'customer_id' => $customer_id,
        'type' => $type,
        'amount' => $signedAmount,
        'note' => $note,
        'created_at' => $created_at,
    ]);

    $balanceStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount),0) AS balance
        FROM customer_account_transactions
        WHERE customer_id = :customer_id
    ");
    $balanceStmt->execute(['customer_id' => $customer_id]);
    $newBalance = (float)($balanceStmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0);

    $updateCustomer = $conn->prepare("
        UPDATE customers
        SET credit_balance = :credit_balance,
            updated_at = :updated_at
        WHERE id = :id AND business_id = :business_id
    ");
    $updateCustomer->execute([
        'credit_balance' => $newBalance,
        'updated_at' => $created_at,
        'id' => $customer_id,
        'business_id' => $business_id
    ]);

    $accCheck = $conn->prepare("SELECT id FROM customer_accounts WHERE customer_id = :customer_id LIMIT 1");
    $accCheck->execute(['customer_id' => $customer_id]);
    if ($accCheck->fetchColumn()) {
        $accUpdate = $conn->prepare("
            UPDATE customer_accounts
            SET balance = :balance,
                updated_at = :updated_at
            WHERE customer_id = :customer_id
        ");
        $accUpdate->execute([
            'balance' => $newBalance,
            'updated_at' => $created_at,
            'customer_id' => $customer_id
        ]);
    } else {
        $accInsert = $conn->prepare("
            INSERT INTO customer_accounts (customer_id, balance, updated_at)
            VALUES (:customer_id, :balance, :updated_at)
        ");
        $accInsert->execute([
            'customer_id' => $customer_id,
            'balance' => $newBalance,
            'updated_at' => $created_at
        ]);
    }

    $conn->commit();

    $message = match ($mode) {
        'add_credit' => 'Credit added successfully. New balance: ' . number_format($newBalance, 2),
        'reduce_credit' => 'Credit reduced successfully. New balance: ' . number_format($newBalance, 2),
        default => 'Payment of ' . number_format($amount, 2) . ' recorded successfully. New balance: ' . number_format($newBalance, 2),
    };

    echo json_encode([
        'status' => 'ok',
        'message' => $message,
        'balance' => round($newBalance, 2)
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Failed to record transaction: ' . $e->getMessage()]);
}