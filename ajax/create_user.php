<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status"=>"error","message"=>"Not allowed"]);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$biz_id = $_SESSION['business_id'] ?? 0;

if ($name === '' || $email === '' || $password === '' || !$biz_id) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$password = password_hash($password, PASSWORD_BCRYPT);

// NEW: permissions
$permissions = $_POST['permissions'] ?? [];
$permissionsStr = implode(',', $permissions);

// Current datetime for created_at
$now = date('Y-m-d H:i:s');

try {
    $check = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute([':email' => $email]);

    if ($check->rowCount() > 0) {
        echo json_encode(["status"=>"error", "message"=>"Email already exists"]);
        exit;
    }

    // Insert into users table with created_at
    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password, permissions, created_at)
        VALUES (:name, :email, :password, :permissions, :created_at)
    ");

    $result = $stmt->execute([
        ':name'        => $name,
        ':email'       => $email,
        ':password'    => $password,
        ':permissions' => $permissionsStr,
        ':created_at'  => $now
    ]);

    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        echo json_encode(["status"=>"error", "message"=>"User insert failed: " . $errorInfo[2]]);
        exit;
    }

    $newUserId = $conn->lastInsertId();

    // Insert into pivot table with created_at
    $pivot = $conn->prepare("
        INSERT INTO business_user (business_id, user_id, role, created_at)
        VALUES (:business_id, :user_id, 'staff', :created_at)
    ");

    $resultPivot = $pivot->execute([
        ':business_id' => $biz_id,
        ':user_id'     => $newUserId,
        ':created_at'  => $now
    ]);

    if (!$resultPivot) {
        $errorInfo = $pivot->errorInfo();
        echo json_encode(["status"=>"error", "message"=>"Pivot insert failed: " . $errorInfo[2]]);
        exit;
    }

    echo json_encode(["status" => "ok"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    exit;
}
