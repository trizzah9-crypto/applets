<?php
require 'db.php';

try {

    $conn->beginTransaction();

    // Create new table without UNIQUE
    $conn->exec("
        CREATE TABLE categories_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            business_id INTEGER
        )
    ");

    // Copy existing data
    $conn->exec("
        INSERT INTO categories_new (id, name, business_id)
        SELECT id, name, business_id
        FROM categories
    ");

    // Remove old table
    $conn->exec("DROP TABLE categories");

    // Rename new table
    $conn->exec("
        ALTER TABLE categories_new
        RENAME TO categories
    ");

    $conn->commit();

    echo "
    <div style='padding:15px;
                background:#d4edda;
                color:#155724;
                border:1px solid #c3e6cb;
                border-radius:8px'>
        Categories table rebuilt successfully.<br>
        UNIQUE constraint removed from name column.
    </div>
    ";

} catch(PDOException $e) {

    $conn->rollBack();

    echo "
    <div style='padding:15px;
                background:#f8d7da;
                color:#721c24;
                border:1px solid #f5c6cb;
                border-radius:8px'>
        Error:<br>
        {$e->getMessage()}
    </div>
    ";
}