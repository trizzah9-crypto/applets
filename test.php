<?php

require_once 'db.php'; // Provides $conn

try {

    $sql = "
    CREATE TABLE IF NOT EXISTS payments (

        id INTEGER PRIMARY KEY AUTOINCREMENT,

        order_tracking_id TEXT,

        merchant_reference TEXT UNIQUE NOT NULL,

        customer_name TEXT NOT NULL,

        customer_email TEXT,

        customer_phone TEXT,

        amount REAL NOT NULL,

        currency TEXT NOT NULL DEFAULT 'KES',

        status TEXT NOT NULL DEFAULT 'PENDING',

        payment_method TEXT,

        confirmation_code TEXT,

        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP

    );
    ";

    $conn->exec($sql);

    echo "✅ Payments table created successfully.";

} catch (PDOException $e) {

    die("Migration failed: " . $e->getMessage());

}