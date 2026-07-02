<?php
session_start();
header('Content-Type: application/json');
require_once '../db.php'; // Your PDO SQLite connection setup file

if (!isset($_SESSION['business_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$business_id = $_SESSION['business_id'];

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$location = trim($_POST['location'] ?? '');
$payment_method = $_POST['payment_method'] ?? 'cash';

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Supplier name is required']);
    exit;
}

if (!in_array($payment_method, ['cash', 'credit'])) {
    $payment_method = 'cash';
}

try {
    $stmt = $conn->prepare("INSERT INTO suppliers (business_id, name, phone, email, location, payment_method, balance, created_at) VALUES (:business_id, :name, :phone, :email, :location, :payment_method, 0, datetime('now'))");
    $stmt->execute([
        'business_id' => $business_id,
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'location' => $location,
        'payment_method' => $payment_method,
    ]);

    echo json_encode(['status' => 'ok', 'supplier_id' => $conn->lastInsertId()]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
