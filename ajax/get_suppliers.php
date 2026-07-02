<?php
// get_suppliers.php
session_start();
header('Content-Type: application/json');
require_once '../db.php'; // Your PDO SQLite connection setup file

if (!isset($_SESSION['business_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$business_id = $_SESSION['business_id'];

try {
    $stmt = $conn->prepare("SELECT supplier_id, name, phone, email, location, payment_method FROM suppliers WHERE business_id = :business_id ORDER BY name ASC");
    $stmt->execute(['business_id' => $business_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($suppliers);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
