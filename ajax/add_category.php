<?php
// ajax/add_category.php
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

$name = trim($_POST['name'] ?? '');

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Category name is required.']);
    exit;
}

$businessId = getCurrentBusinessId($conn);

if ($businessId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Business ID not found.']);
    exit;
}

try {
    $check = $conn->prepare("
        SELECT id
        FROM categories
        WHERE business_id = :business_id
          AND LOWER(name) = LOWER(:name)
        LIMIT 1
    ");
    $check->execute([
        ':business_id' => $businessId,
        ':name' => $name
    ]);

    if ($check->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['status' => 'error', 'message' => 'Category already exists for this business.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO categories (name, business_id)
        VALUES (:name, :business_id)
    ");
    $stmt->execute([
        ':name' => $name,
        ':business_id' => $businessId
    ]);

    echo json_encode([
        'status' => 'ok',
        'id' => $conn->lastInsertId(),
        'name' => $name
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
 