<?php
require 'db.php';

$stmt = $conn->query("PRAGMA table_info(products)");

$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($columns);
echo "</pre>";