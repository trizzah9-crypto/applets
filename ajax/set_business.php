<?php
// ajax/set_business.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$business_id = intval($_POST['business_id'] ?? 0);
$role = $_POST['role'] ?? '';

if ($business_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid business']);
    exit;
}

$uid = (int) $_SESSION['user_id'];

// Check if user is owner of the business
$q = $conn->prepare("SELECT id FROM businesses WHERE id = :business_id AND owner_user_id = :uid");
$q->execute([':business_id' => $business_id, ':uid' => $uid]);
$owner = $q->fetch(PDO::FETCH_ASSOC);

if ($owner) {
    $_SESSION['business_id'] = $business_id;
    $_SESSION['role'] = 'owner';
    echo json_encode(['status' => 'ok']);
    exit;
}

// Check if user belongs to business via pivot table
$q2 = $conn->prepare("SELECT role FROM business_user WHERE business_id = :business_id AND user_id = :uid LIMIT 1");
$q2->execute([':business_id' => $business_id, ':uid' => $uid]);
$r2 = $q2->fetch(PDO::FETCH_ASSOC);

if ($r2) {
    $_SESSION['business_id'] = $business_id;
    $_SESSION['role'] = $r2['role'];
    echo json_encode(['status' => 'ok']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'You do not belong to that business']);
