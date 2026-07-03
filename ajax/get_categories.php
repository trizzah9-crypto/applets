<?php
// ajax/get_categories.php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

function getCurrentBusinessId(PDO $conn): int {
    if (!empty($_SESSION['business_id'])) return (int) $_SESSION['business_id'];
    if (!empty($_SESSION['current_business_id'])) return (int) $_SESSION['current_business_id'];
    if (!empty($_SESSION['selected_business_id'])) return (int) $_SESSION['selected_business_id'];
    if (!empty($_SESSION['business']['id'])) return (int) $_SESSION['business']['id'];

    if (!empty($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT business_id FROM business_user WHERE user_id = :uid ORDER BY id ASC LIMIT 1");
        $stmt->execute([':uid' => (int) $_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['business_id'])) {
            return (int) $row['business_id'];
        }
    }

    return 0;
}

$businessId = getCurrentBusinessId($conn);

if ($businessId <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, name
    FROM categories
    WHERE business_id = :business_id
    ORDER BY name ASC
");
$stmt->execute([':business_id' => $businessId]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
 