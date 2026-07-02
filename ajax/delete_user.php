<?php
session_start();
require "../dbconnect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$id = intval($_POST['id']);
$biz_id = $_SESSION['business_id'];

// Delete pivot record first
$stmt = $conn->prepare("DELETE FROM business_user WHERE user_id = ? AND business_id = ?");
$stmt->bind_param("ii", $id, $biz_id);
$stmt->execute();

// Delete user (only if not linked elsewhere)
$stmt2 = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt2->bind_param("i", $id);

if ($stmt2->execute()) {
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "message" => "Could not delete"]);
}
?>
