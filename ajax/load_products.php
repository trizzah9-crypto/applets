<?php
require '../db.php';
session_start();

$bid = $_SESSION['business_id'] ?? 0;
$cat = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT id, name, selling_price 
        FROM products 
        WHERE business_id = :bid";
$params = ['bid' => $bid];

if ($cat !== '') {
    $sql .= " AND category = :cat";
    $params['cat'] = $cat;
}

if ($search !== '') {
    $sql .= " AND name LIKE :s";
    $params['s'] = "%$search%";
}

$sql .= " ORDER BY name";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
