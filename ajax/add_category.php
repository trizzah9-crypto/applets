<?php
require '../db.php';

$name = trim($_POST['name'] ?? '');

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty category name']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->execute([$name]);
    echo json_encode([
        'status' => 'ok',
        'id' => $conn->lastInsertId()
    ]);
} catch (PDOException $e) {
    // You can check for duplicate entry error code 23000 if you want more precision
    echo json_encode(['status' => 'error', 'message' => 'Category already exists or error occurred']);
}
