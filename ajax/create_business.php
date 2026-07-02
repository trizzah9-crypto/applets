<?php
// ajax/create_business.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

// Get current timestamp in SQLite format
$created_at = date('Y-m-d H:i:s');

if (!$name) {
    echo json_encode(['status'=>'error','message'=>'Name required']);
    exit;
}

try {
    $stmt = $conn->prepare(
        "INSERT INTO businesses (owner_user_id, name, description, created_at)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$_SESSION['user_id'], $name, $description, $created_at]);

    $business_id = $conn->lastInsertId();

    // add pivot row marking owner role
    $stmt2 = $conn->prepare(
        "INSERT INTO business_user (business_id, user_id, role, created_at)
         VALUES (?, ?, 'owner', ?)"
    );
    $stmt2->execute([$business_id, $_SESSION['user_id'], $created_at]);

    echo json_encode(['status'=>'ok','business_id'=>$business_id]);

} catch (PDOException $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
