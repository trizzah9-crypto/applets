<?php
require 'db.php'; // your PDO connection

try {

    function columnExists(PDO $conn, string $table, string $column): bool
    {
        $stmt = $conn->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }

        return false;
    }

    $salesColumns = [

        'invoice_number' => "TEXT",
        'status' => "TEXT DEFAULT 'completed'",
        'cashier_id' => "INTEGER",
        'customer_id' => "INTEGER",

        'paid_amount' => "REAL DEFAULT 0",
        'balance_due' => "REAL DEFAULT 0",
        'change_amount' => "REAL DEFAULT 0",

        'notes' => "TEXT",

        'refunded_amount' => "REAL DEFAULT 0",
        'refunded_at' => "TEXT",

        'updated_at' => "TEXT"
    ];

    foreach ($salesColumns as $column => $definition) {

        if (!columnExists($conn, 'sales', $column)) {

            $sql = "ALTER TABLE sales ADD COLUMN $column $definition";
            $conn->exec($sql);

            echo "Added sales.$column <br>";
        }
    }

    $saleItemsColumns = [

        'discount_amount' => "REAL DEFAULT 0",
        'tax_amount' => "REAL DEFAULT 0",

        'line_total' => "REAL DEFAULT 0",
        'line_cost' => "REAL DEFAULT 0",

        'profit' => "REAL DEFAULT 0"
    ];

    foreach ($saleItemsColumns as $column => $definition) {

        if (!columnExists($conn, 'sale_items', $column)) {

            $sql = "ALTER TABLE sale_items ADD COLUMN $column $definition";
            $conn->exec($sql);

            echo "Added sale_items.$column <br>";
        }
    }

    echo "<br><strong>Migration completed successfully.</strong>";

} catch (PDOException $e) {

    die("Migration failed: " . $e->getMessage());
}