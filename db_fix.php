<?php
require_once __DIR__ . '/db.php'; // your PDO connection

echo "<pre>";

try {
    // add created_at column with default
    $sql = "
        ALTER TABLE businesses
        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ";

    $conn->exec($sql);

    echo "✅ created_at column added successfully\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
