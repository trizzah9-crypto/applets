<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No business selected']);
    exit;
}

$business_id = (int)$_SESSION['business_id'];
$draft_id = isset($_POST['draft_id']) ? (int)$_POST['draft_id'] : 0;

if ($draft_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid draft ID']);
    exit;
}

try {
    $stmt = $conn->prepare("
        DELETE FROM draft_orders
        WHERE id = ? AND business_id = ?
    ");
    $stmt->execute([$draft_id, $business_id]);

    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}