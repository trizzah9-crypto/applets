<?php

require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch subscription
$stmt = $conn->prepare("SELECT subscription_expires_at FROM businesses WHERE id = ?");
$stmt->execute([$_SESSION['business_id']]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

$expiry = $data['subscription_expires_at'] ?? null;

if (!$expiry || $expiry == "0000-00-00 00:00:00") {
    header("Location: subscription_expired.php");
    exit;
}

$now = new DateTime();
$expiryDate = new DateTime($expiry);

if ($expiryDate < $now) {
    // EXPIRED - redirect
    header("Location: subscription_expired.php");
    exit;
}
