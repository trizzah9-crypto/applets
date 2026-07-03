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

$receipt_logo = null;

if (isset($_FILES['receipt_logo']) && $_FILES['receipt_logo']['error'] == 0) {

    $uploadDir = __DIR__ . '/../uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = strtolower(pathinfo($_FILES['receipt_logo']['name'], PATHINFO_EXTENSION));

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extension, $allowed)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid image format'
        ]);
        exit;
    }

    $filename = 'logo_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;

    $destination = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['receipt_logo']['tmp_name'], $destination)) {
        $receipt_logo = 'uploads/' . $filename;
    }
}

try {
$stmt = $conn->prepare(
    "INSERT INTO businesses
    (
        owner_user_id,
        name,
        description,
        receipt_logo,
        created_at
    )
    VALUES (?, ?, ?, ?, ?)"
);

$stmt->execute([
    $_SESSION['user_id'],
    $name,
    $description,
    $receipt_logo,
    $created_at
]);
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
