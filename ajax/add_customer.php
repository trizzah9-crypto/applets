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

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$email = trim($_POST['email'] ?? '');
$customer_type = trim($_POST['customer_type'] ?? 'Walk-in');
$credit_limit = (float)($_POST['credit_limit'] ?? 0);
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');
$tax_pin = trim($_POST['tax_pin'] ?? '');
$status = trim($_POST['status'] ?? 'Active');
$notes = trim($_POST['notes'] ?? '');

$business_id = (int)$_SESSION['business_id'];

if (mb_strlen($name) < 2) {
    echo json_encode(['status' => 'error', 'message' => 'Name too short']);
    exit;
}

if ($phone !== '') {
    $phoneKey = normalizePhoneDigits($phone);
    if ($phoneKey === '') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
        exit;
    }
    if (!preg_match('/^(2547\d{8}|2541\d{8})$/', $phoneKey) && !preg_match('/^254\d{9}$/', $phoneKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format. Use 2547XXXXXXXX or 07XXXXXXXX']);
        exit;
    }

    $existing = $conn->prepare("SELECT id, phone FROM customers WHERE business_id = :business_id");
    $existing->execute(['business_id' => $business_id]);
    foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (normalizePhoneDigits($row['phone'] ?? '') === $phoneKey) {
            echo json_encode(['status' => 'error', 'message' => 'Phone number already exists for this business']);
            exit;
        }
    }

    $phone = $phoneKey;
}

$allowedTypes = ['Retail', 'Wholesale', 'VIP', 'Walk-in'];
if (!in_array($customer_type, $allowedTypes, true)) {
    $customer_type = 'Walk-in';
}

$allowedStatus = ['Active', 'Inactive', 'Blocked'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'Active';
}

$created_at = date('Y-m-d H:i:s');
$updated_at = $created_at;

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO customers (
            business_id, name, phone, address, email, customer_type, credit_limit,
            credit_balance, loyalty_points, date_of_birth, company_name, tax_pin,
            notes, profile_photo, status, created_at, updated_at
        ) VALUES (
            :business_id, :name, :phone, :address, :email, :customer_type, :credit_limit,
            0, 0, :date_of_birth, :company_name, :tax_pin,
            :notes, NULL, :status, :created_at, :updated_at
        )
    ");

    $stmt->execute([
        'business_id' => $business_id,
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'email' => $email,
        'customer_type' => $customer_type,
        'credit_limit' => $credit_limit,
        'date_of_birth' => $date_of_birth,
        'company_name' => $company_name,
        'tax_pin' => $tax_pin,
        'notes' => $notes,
        'status' => $status,
        'created_at' => $created_at,
        'updated_at' => $updated_at,
    ]);

    $customer_id = (int)$conn->lastInsertId();

    $accCheck = $conn->prepare("SELECT id FROM customer_accounts WHERE customer_id = :customer_id LIMIT 1");
    $accCheck->execute(['customer_id' => $customer_id]);
    if (!$accCheck->fetchColumn()) {
        $accInsert = $conn->prepare("
            INSERT INTO customer_accounts (customer_id, balance, updated_at)
            VALUES (:customer_id, 0, :updated_at)
        ");
        $accInsert->execute([
            'customer_id' => $customer_id,
            'updated_at' => $updated_at
        ]);
    }

    $conn->commit();

    echo json_encode(['status' => 'ok', 'customer_id' => $customer_id]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $e->getMessage()]);
}