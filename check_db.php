<?php

require("db.php");

// Assuming $conn is your PDO SQLite connection
$sql = "CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    location TEXT,
    balance REAL DEFAULT 0,
    payment_method TEXT DEFAULT 'cash',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);";

$conn->exec($sql);
echo "Suppliers table created or already exists.";
