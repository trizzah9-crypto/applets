<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../db.php'; // adjust path as needed

if(!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if(strlen($name) < 2){
    echo json_encode(['status' => 'error', 'message' => 'Name too short']);
    exit;
}

// Validate phone number format (Kenyan phone number example)
if(!empty($phone) && !preg_match('/^07\d{8}$/', $phone)){
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format. Use 07XXXXXXXX']);
    exit;
}

$business_id = $_SESSION['business_id'];

// Current datetime for created_at
$created_at = date('Y-m-d H:i:s');

// Check if phone already exists for this business (if phone provided)
if(!empty($phone)) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM customers WHERE business_id = :business_id AND phone = :phone");
    if(!$stmt){
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
        exit;
    }
    $stmt->execute(['business_id' => $business_id, 'phone' => $phone]);
    $count = $stmt->fetchColumn();

    if($count > 0){
        echo json_encode(['status' => 'error', 'message' => 'Phone number already exists for this business']);
        exit;
    }
}

// Insert customer with created_at
$stmt = $conn->prepare("INSERT INTO customers (business_id, name, phone, address, created_at) VALUES (:business_id, :name, :phone, :address, :created_at)");
if(!$stmt){
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed']);
    exit;
}

$success = $stmt->execute([
    'business_id' => $business_id,
    'name'        => $name,
    'phone'       => $phone,
    'address'     => $address,
    'created_at'  => $created_at,
]);

if($success){
    echo json_encode(['status' => 'ok']);
}else{
    $errorInfo = $stmt->errorInfo();
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $errorInfo[2]]);
}
