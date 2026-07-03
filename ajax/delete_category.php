<?php
// ajax/delete_category.php
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

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid category ID.']);
    exit;
}

$businessId = getCurrentBusinessId($conn);

if ($businessId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Business ID not found.']);
    exit;
}

try {

    // Check if category is used by any products
    $check = $conn->prepare("
        SELECT COUNT(*) as total
        FROM products
        WHERE category_id = :id
          AND business_id = :business_id
    ");

    $check->execute([
        ':id' => $id,
        ':business_id' => $businessId
    ]);

    $count = $check->fetch(PDO::FETCH_ASSOC);

    if ($count['total'] > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Category is being used by products and cannot be deleted.'
        ]);
        exit;
    }

    // Safe to delete
    $stmt = $conn->prepare("
        DELETE FROM categories
        WHERE id = :id
          AND business_id = :business_id
    ");

    $stmt->execute([
        ':id' => $id,
        ':business_id' => $businessId
    ]);

    echo json_encode([
        'status' => 'ok'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} 