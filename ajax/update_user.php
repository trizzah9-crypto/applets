<?php
session_start();
require "../dbconnect.php"; // must create $conn as PDO

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not allowed"]);
    exit;
}

$userId     = (int)($_POST['user_id'] ?? 0);
$name       = trim($_POST['name'] ?? '');
$password   = trim($_POST['password'] ?? '');
$businessId = (int)($_SESSION['business_id'] ?? 0);

if ($userId <= 0 || empty($name)) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

/* Verify ownership */
$check = $conn->prepare("
    SELECT u.id
    FROM users u
    JOIN business_user bu ON bu.user_id = u.id
    WHERE u.id = :user_id AND bu.business_id = :business_id
");
$check->execute([
    ':user_id'     => $userId,
    ':business_id' => $businessId
]);



/* Update user */
if (!empty($password)) {
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("
        UPDATE users 
        SET name = :name, password = :password 
        WHERE id = :id
    ");
    $stmt->execute([
        ':name'     => $name,
        ':password' => $hash,
        ':id'       => $userId
    ]);
} else {
    $stmt = $conn->prepare("
        UPDATE users 
        SET name = :name 
        WHERE id = :id
    ");
    $stmt->execute([
        ':name' => $name,
        ':id'   => $userId
    ]);
}

echo json_encode(["status" => "ok"]);
