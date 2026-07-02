<?php
require 'db.php';  // your existing PDO connection file

try {
    // Try to add the deleted_at column
    $conn->exec("ALTER TABLE products ADD COLUMN deleted_at TEXT DEFAULT NULL;");
    echo "✅ Column 'deleted_at' added successfully.";
} catch (PDOException $e) {
    // If column exists or error occurs, show message
    echo "⚠️ Could not add 'deleted_at' column: " . $e->getMessage();
}
