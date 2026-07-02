<?php
// ajax/login.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php'; // Make sure $conn is your PDO instance

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password required']);
    exit;
}

// Prepare and execute PDO statement
$stmt = $conn->prepare("
    SELECT id, name, password, permissions 
    FROM users 
    WHERE email = :email 
    LIMIT 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    exit;
}

// success: set basic session
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];

// Load permissions into session
$_SESSION['permissions'] = [];

if (!empty($user['permissions'])) {
    $_SESSION['permissions'] = explode(',', $user['permissions']);
}

// fetch businesses for this user (owner and pivot)
$businesses = [];

// Businesses where user is owner
$q1 = $conn->prepare("SELECT id, name, 'owner' as role FROM businesses WHERE owner_user_id = :user_id");
$q1->execute([':user_id' => $_SESSION['user_id']]);
while ($b = $q1->fetch(PDO::FETCH_ASSOC)) {
    $businesses[] = $b;
}

// Businesses via pivot (manager/cashier etc.)
$q2 = $conn->prepare("SELECT b.id, b.name, bu.role FROM businesses b JOIN business_user bu ON bu.business_id = b.id WHERE bu.user_id = :user_id");
$q2->execute([':user_id' => $_SESSION['user_id']]);
while ($b = $q2->fetch(PDO::FETCH_ASSOC)) {
    $businesses[] = $b;
}

// remove duplicates by id (owner could also be in pivot)
$seen = [];
$filtered = [];
foreach ($businesses as $b) {
    if (isset($seen[$b['id']])) continue;
    $seen[$b['id']] = true;
    $filtered[] = $b;
}

// optionally save business list in session for selector
$_SESSION['business_list'] = $filtered;

echo json_encode([
    'status' => 'ok',
    'user' => ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name']],
    'businesses' => $filtered,
]);
