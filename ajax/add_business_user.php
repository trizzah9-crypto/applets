<?php
// ajax/add_business_user.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['business_id'])) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized']);
    exit;
}

// only owner of the business can add users
$business_id = (int)$_SESSION['business_id'];
$uid = (int)$_SESSION['user_id'];

// Check owner
$q = $conn->prepare("SELECT owner_user_id FROM businesses WHERE id = ?");
$q->bind_param('i', $business_id);
$q->execute();
$owner = $q->get_result()->fetch_assoc();
if (!$owner || $owner['owner_user_id'] != $uid) {
    echo json_encode(['status'=>'error','message'=>'Only business owner can add users']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');
$role = $_POST['role'] ?? 'cashier';

if (!$email || !$name) {
    echo json_encode(['status'=>'error','message'=>'Name and email required']);
    exit;
}
if (!in_array($role, ['manager','cashier'])) { $role='cashier'; }

// If user exists, attach; else create user with random password and attach
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if ($user) {
    $new_user_id = $user['id'];
} else {
    $pw = bin2hex(random_bytes(4));
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $ins->bind_param('sss', $name, $email, $hash);
    $ins->execute();
    $new_user_id = $ins->insert_id;
    // TODO: send $pw to email — out of scope here
}

// attach pivot if not exists
$chk = $conn->prepare("SELECT id FROM business_user WHERE business_id=? AND user_id=?");
$chk->bind_param('ii', $business_id, $new_user_id);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    echo json_encode(['status'=>'error','message'=>'User already added']);
    exit;
}

$attach = $conn->prepare("INSERT INTO business_user (business_id, user_id, role) VALUES (?, ?, ?)");
$attach->bind_param('iis', $business_id, $new_user_id, $role);
$attach->execute();

echo json_encode(['status'=>'ok','user_id'=>$new_user_id,'role'=>$role]);
