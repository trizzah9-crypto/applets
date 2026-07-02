<?php
session_start();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require "../permissions.php";
require "../db.php";

try {
    // Check permission
    if (!can('view_financials')) {
        echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
        exit;
    }

    // Validate inputs
    $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $business_id = isset($_SESSION['business_id']) ? (int)$_SESSION['business_id'] : 0;
    $store_id    = isset($_SESSION['store_id']) ? (int)$_SESSION['store_id'] : 0;

    if ($id <= 0 || $business_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID or business session']);
        exit;
    }

    // Prepare delete SQL
    $sql = "DELETE FROM products WHERE id = :id AND business_id = :business_id";
    $params = [':id' => $id, ':business_id' => $business_id];

    if ($store_id > 0) {
        $sql .= " AND store_id = :store_id";
        $params[':store_id'] = $store_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No product found to delete']);
        exit;
    }

    echo json_encode(['status' => 'ok']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
