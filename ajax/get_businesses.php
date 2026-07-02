<?php
// ajax/get_businesses.php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$uid = (int) $_SESSION['user_id'];
$list = [];

// OWNED BUSINESSES
$q = $conn->prepare("SELECT id, name, 'owner' AS role FROM businesses WHERE owner_user_id = :uid");
$q->execute([':uid' => $uid]);
$list = $q->fetchAll(PDO::FETCH_ASSOC);

// BUSINESSES VIA PIVOT TABLE
$q2 = $conn->prepare("SELECT b.id, b.name, bu.role FROM businesses b JOIN business_user bu ON bu.business_id = b.id WHERE bu.user_id = :uid");
$q2->execute([':uid' => $uid]);
$list2 = $q2->fetchAll(PDO::FETCH_ASSOC);

// Merge and dedupe by 'id'
$seen = [];
$out = [];

foreach (array_merge($list, $list2) as $b) {
    if (isset($seen[$b['id']])) continue;
    $seen[$b['id']] = true;
    $out[] = $b;
}

echo json_encode(['status' => 'ok', 'businesses' => $out]);
