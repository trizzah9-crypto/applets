<?php
session_start();
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function normalizePhoneDigits($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '254' . substr($digits, 1);
    }
    if (str_starts_with($digits, '7') && strlen($digits) === 9) {
        return '254' . $digits;
    }
    if (str_starts_with($digits, '254') && strlen($digits) === 12) {
        return $digits;
    }
    return $digits;
}

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing customer ID']);
    exit;
}

$customer_id = (int)$_GET['id'];
$business_id = isset($_SESSION['business_id']) ? (int)$_SESSION['business_id'] : null;

try {
    if ($business_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = :id AND business_id = :business_id LIMIT 1");
        $stmt->execute(['id' => $customer_id, 'business_id' => $business_id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $customer_id]);
    }

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
        exit;
    }

    $balanceStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount),0) AS balance
        FROM customer_account_transactions
        WHERE customer_id = :customer_id
    ");
    $balanceStmt->execute(['customer_id' => $customer_id]);
    $current_balance = (float)($balanceStmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0);

    $txStmt = $conn->prepare("
        SELECT id, sale_id, type, amount, note, created_at
        FROM customer_account_transactions
        WHERE customer_id = :customer_id
        ORDER BY datetime(created_at) DESC, id DESC
        LIMIT 100
    ");
    $txStmt->execute(['customer_id' => $customer_id]);
    $transactionsRaw = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    $transactions = [];
    $payments = [];
    foreach ($transactionsRaw as $r) {
        $amount = (float)($r['amount'] ?? 0);
        $date = !empty($r['created_at']) ? date('d M Y, H:i', strtotime($r['created_at'])) : '';
        $item = [
            'id' => (int)$r['id'],
            'sale_id' => $r['sale_id'],
            'type' => $r['type'],
            'amount' => round($amount, 2),
            'note' => $r['note'],
            'date' => $date,
            'created_at' => $r['created_at'],
        ];
        $transactions[] = $item;

        if ($amount < 0 || stripos((string)$r['type'], 'payment') !== false) {
            $payments[] = $item;
        }
    }

    $purchasesStmt = $conn->prepare("
        SELECT COALESCE(SUM(COALESCE(NULLIF(s.total_including_vat,0), s.total_amount, 0)),0) AS total_purchases,
               COUNT(*) AS sale_count
        FROM sales s
        WHERE s.business_id = :business_id
          AND TRIM(LOWER(COALESCE(s.customer_name,''))) = TRIM(LOWER(:customer_name))
    ");
    $purchasesStmt->execute([
        'business_id' => $business_id,
        'customer_name' => $customer['name']
    ]);
    $purchaseRow = $purchasesStmt->fetch(PDO::FETCH_ASSOC);

    $paymentStmt = $conn->prepare("
        SELECT COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END),0) AS total_payments
        FROM customer_account_transactions
        WHERE customer_id = :customer_id
    ");
    $paymentStmt->execute(['customer_id' => $customer_id]);
    $totalPayments = (float)($paymentStmt->fetch(PDO::FETCH_ASSOC)['total_payments'] ?? 0);

    $notesStmt = $conn->prepare("
        SELECT type, note, created_at
        FROM customer_account_transactions
        WHERE customer_id = :customer_id
          AND COALESCE(note,'') <> ''
        ORDER BY datetime(created_at) DESC, id DESC
        LIMIT 20
    ");
    $notesStmt->execute(['customer_id' => $customer_id]);
    $notesHistoryRaw = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

    $notesHistory = [];
    foreach ($notesHistoryRaw as $n) {
        $notesHistory[] = [
            'type' => $n['type'],
            'note' => $n['note'],
            'date' => !empty($n['created_at']) ? date('d M Y, H:i', strtotime($n['created_at'])) : ''
        ];
    }

    $creditLimit = (float)($customer['credit_limit'] ?? 0);
    $availableCredit = max($creditLimit - $current_balance, 0);

    $customer['customer_since'] = !empty($customer['created_at']) ? date('d M Y', strtotime($customer['created_at'])) : '';
    $customer['phone_tel'] = normalizePhoneDigits($customer['phone'] ?? '');
    $customer['phone_wa'] = normalizePhoneDigits($customer['phone'] ?? '');
    $customer['phone_sms'] = normalizePhoneDigits($customer['phone'] ?? '');

    echo json_encode([
        'status' => 'ok',
        'customer' => $customer,
        'summary' => [
            'current_balance' => round($current_balance, 2),
            'lifetime_purchases' => round((float)($purchaseRow['total_purchases'] ?? 0), 2),
            'loyalty_points' => (int)($customer['loyalty_points'] ?? 0),
            'transaction_count' => count($transactions),
            'credit_limit' => round($creditLimit, 2),
            'available_credit' => round($availableCredit, 2),
            'total_payments' => round($totalPayments, 2),
            'sale_count' => (int)($purchaseRow['sale_count'] ?? 0),
        ],
        'transactions' => $transactions,
        'payments' => $payments,
        'notes_history' => $notesHistory
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}