<?php
header('Content-Type: application/json');
session_start();
require_once("../db.php");

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Prepare variables
$name    = $_POST['business_name'] ?? '';
$email   = $_POST['business_email'] ?? '';
$phone   = $_POST['business_phone'] ?? '';
$address = $_POST['business_address'] ?? '';

// Handle logo upload
$logoPath = null;

if (!empty($_FILES['receipt_logo']['name'])) {
    $ext = pathinfo($_FILES['receipt_logo']['name'], PATHINFO_EXTENSION);
    $newName = "logo_" . $userId . "_" . time() . "." . $ext;
    $uploadDir = __DIR__ . "/../uploads";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadPath = $uploadDir . "/" . $newName;

    if (move_uploaded_file($_FILES['receipt_logo']['tmp_name'], $uploadPath)) {
        // Relative path for storage in DB (adjust if needed)
        $logoPath = "uploads/$newName";
    }
}

try {
    if ($logoPath) {
        $sql = "UPDATE businesses SET business_name = ?, business_email = ?, business_phone = ?, business_address = ?, receipt_logo = ? WHERE owner_user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $email, $phone, $address, $logoPath, $userId]);
    } else {
        $sql = "UPDATE businesses SET business_name = ?, business_email = ?, business_phone = ?, business_address = ? WHERE owner_user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $email, $phone, $address, $userId]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Settings updated successfully!']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update settings: ' . $e->getMessage()]);
}
