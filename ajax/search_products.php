<?php
include '../db.php';
session_start();

if (!isset($_SESSION['business_id'])) {
    exit; // Autocomplete stays blank
}

$business_id = $_SESSION['business_id'];
$term = $_GET['term'] ?? '';

if ($term === '') exit;

$sql = "
    SELECT id, name, barcode, selling_price, description, pack_size
    FROM products
    WHERE business_id = :business_id
    AND (
        name LIKE :term
        OR barcode LIKE :term
        OR description LIKE :term
    )
    LIMIT 10
";

$stmt = $conn->prepare($sql);
$likeTerm = "%{$term}%";
$stmt->bindParam(':business_id', $business_id, PDO::PARAM_INT);
$stmt->bindParam(':term', $likeTerm, PDO::PARAM_STR);

$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $desc = htmlspecialchars($row['description'], ENT_QUOTES);
    $name = htmlspecialchars($row['name'], ENT_QUOTES);
    $stored = intval($row['pack_size']); // make sure this is int 0 or 1
    echo "<div class='search-item'
            data-id='{$row['id']}'
            data-name='{$name}'
            data-price='{$row['selling_price']}'
            data-barcode='{$row['barcode']}'
            data-description='{$desc}'
            data-stored-in-packs='{$stored}'>
            <strong>{$name}</strong> ({$row['barcode']}),
            <small>{$desc}</small>,
            KES {$row['selling_price']}
          </div>";
}
?>
