<?php
require '../db.php';
session_start();

$bid = $_SESSION['business_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT DISTINCT category 
    FROM products 
    WHERE business_id = :bid
      AND category IS NOT NULL
      AND category != ''
    ORDER BY category
");
$stmt->execute(['bid' => $bid]);

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
