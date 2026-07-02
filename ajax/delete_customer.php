<?php
include '../db.php';
session_start();
header("Content-Type: application/json");

if(!isset($_SESSION['business_id'])){
    echo json_encode(['status'=>'error','message'=>'No business selected']); exit;
}

$id = intval($_POST['id'] ?? 0);
$business_id = $_SESSION['business_id'];

if(!$id){ echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }

$conn->query("DELETE FROM customers WHERE id=$id AND business_id=$business_id");

echo json_encode(['status'=>'ok']);
