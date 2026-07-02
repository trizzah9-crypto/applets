<?php
// ajax/register.php
header('Content-Type: application/json');
session_start();

// DO NOT CHANGE THIS
require_once __DIR__ . '/../db.php';

$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password']   ?? '';

// Basic validation
if ($name === '' || $email === '' || $pass === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
    exit;
}

try {
    /* =========================
       CHECK DUPLICATE EMAIL
    ========================= */
    $chk = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $chk->execute([':email' => $email]);

    if ($chk->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered.']);
        exit;
    }

    /* =========================
       INSERT USER
    ========================= */
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $ins = $conn->prepare("
        INSERT INTO users (name, email, password)
        VALUES (:name, :email, :password)
    ");
    $ins->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':password' => $hash
    ]);

    $user_id = $conn->lastInsertId();

    /* =========================
       SET SESSION
    ========================= */
    $_SESSION['user_id']   = (int) $user_id;
    $_SESSION['user_name'] = $name;

    echo json_encode([
        'status'  => 'ok',
        'user_id' => (int)$user_id,
        'name'    => $name
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error.'
    ]);
}
