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

try {
    // Owned businesses
    $q = $conn->prepare("
        SELECT id, name, receipt_logo, business_phone, 'owner' AS role
        FROM businesses
        WHERE owner_user_id = :uid
    ");
    $q->execute([':uid' => $uid]);
    $list = $q->fetchAll(PDO::FETCH_ASSOC);

    // Businesses via pivot table
    $q2 = $conn->prepare("
        SELECT b.id, b.name, b.receipt_logo, b.business_phone, bu.role
        FROM businesses b
        JOIN business_user bu ON bu.business_id = b.id
        WHERE bu.user_id = :uid
    ");
    $q2->execute([':uid' => $uid]);
    $list2 = $q2->fetchAll(PDO::FETCH_ASSOC);

    // Merge and dedupe
    $seen = [];
    $out = [];

    foreach (array_merge($list, $list2) as $b) {
        $id = (int) $b['id'];
        if (isset($seen[$id])) continue;

        $seen[$id] = true;
        $out[] = [
            'id' => $id,
            'name' => $b['name'] ?? '',
            'receipt_logo' => $b['receipt_logo'] ?? '',
            'business_phone' => $b['business_phone'] ?? '',
            'role' => $b['role'] ?? 'member'
        ];
    }

    echo json_encode(['status' => 'ok', 'businesses' => $out]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}