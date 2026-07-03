<?php
header('Content-Type: application/json');
session_start();

require_once("../db.php"); // your PDO SQLite connection in $conn

// SECRET KEY
$SECRET_KEY = "0700000000";  // change to your own secret key

$plans = [
    'yearly'  => ['label' => 'Yearly Plan', 'price' => 3000, 'days' => 365, 'interval' => 'year'],
    'monthly' => ['label' => 'Monthly Plan', 'price' => 300,  'days' => 30,  'interval' => 'month'],
    'weekly'  => ['label' => 'Weekly Plan', 'price' => 80,   'days' => 7,   'interval' => 'week'],
];

$planKey = $_POST['plan'] ?? '';
$phone   = trim($_POST['phone'] ?? '');
$userId  = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (!isset($plans[$planKey])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid plan']);
    exit;
}

if (!$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Phone required']);
    exit;
}

if (!preg_match('/^\d{9,12}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
    exit;
}

// ---------------------------------------
// STEP 1: SECRET KEY CHECK
// ---------------------------------------
if ($phone === $SECRET_KEY) {

    $daysToAdd = $plans[$planKey]['days'];

    // Fetch current expiry
    $stmt = $conn->prepare("SELECT subscription_expires_at FROM businesses WHERE owner_user_id = :userId");
    $stmt->execute(['userId' => $userId]);
    $currentExpiry = $stmt->fetchColumn();

    $now = new DateTime();

    if ($currentExpiry && $currentExpiry !== "0000-00-00 00:00:00") {
        $expiryDate = new DateTime($currentExpiry);

        if ($expiryDate > $now) {
            // Extend from current expiry
            $expiryDate->modify("+{$daysToAdd} days");
        } else {
            // Expired → start from now
            $expiryDate = (clone $now)->modify("+{$daysToAdd} days");
        }
    } else {
        // No subscription → start now
        $expiryDate = (clone $now)->modify("+{$daysToAdd} days");
    }

    $newExpiry = $expiryDate->format("Y-m-d H:i:s");

    // UPDATE
    $updateStmt = $conn->prepare("UPDATE businesses SET subscription_plan = :planKey, subscription_expires_at = :newExpiry WHERE owner_user_id = :userId");
    $updateStmt->execute([
        'planKey' => $planKey,
        'newExpiry' => $newExpiry,
        'userId' => $userId
    ]);

    echo json_encode([
        'status' => 'success',
        'plan_label' => $plans[$planKey]['label'],
        'price' => $plans[$planKey]['price'],
        'interval' => $plans[$planKey]['interval'],
        'expiry' => $expiryDate->format("F j, Y")
    ]);
    exit;
}

// ---------------------------------------
// SECRET KEY DID NOT MATCH → GO TO PAYMENT
// ---------------------------------------

echo json_encode([
    'status' => 'pay',
    'message' => 'Proceed to payment',
    'plan' => $planKey,
    'amount' => $plans[$planKey]['price'],
    'phone' => $phone
]);

exit;
