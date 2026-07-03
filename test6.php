<?php
/**
 * Migration: Create draft_orders table
 * Database: PDO + SQLite
 */
require ("db.php");

try {

    $conn->exec("
        CREATE TABLE IF NOT EXISTS draft_orders (

            id INTEGER PRIMARY KEY AUTOINCREMENT,

            business_id INTEGER NOT NULL,

            order_number TEXT NOT NULL UNIQUE,

            order_name TEXT DEFAULT 'Walk In',

            cart_data TEXT NOT NULL,

            total_amount REAL NOT NULL DEFAULT 0,

            discount REAL NOT NULL DEFAULT 0,

            apply_vat INTEGER NOT NULL DEFAULT 1,

            cashier_id INTEGER,

            status TEXT NOT NULL DEFAULT 'draft',

            created_at TEXT NOT NULL,

            updated_at TEXT NOT NULL,

            FOREIGN KEY (business_id) REFERENCES businesses(id),
            FOREIGN KEY (cashier_id) REFERENCES users(id)

        );
    ");

    // Helpful indexes
    $conn->exec("
        CREATE INDEX IF NOT EXISTS idx_draft_orders_business
        ON draft_orders(business_id);
    ");

    $conn->exec("
        CREATE INDEX IF NOT EXISTS idx_draft_orders_status
        ON draft_orders(status);
    ");

    $conn->exec("
        CREATE INDEX IF NOT EXISTS idx_draft_orders_updated
        ON draft_orders(updated_at);
    ");

    echo "draft_orders table created successfully.";

} catch (PDOException $e) {

    die("Migration failed: " . $e->getMessage());

}