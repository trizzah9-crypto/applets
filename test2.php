<?php
require 'db.php'; // PDO SQLite connection

try {

    // Get existing columns
    $stmt = $conn->query("PRAGMA table_info(customers)");
    $existingColumns = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['name'];
    }

    $columns = [
        'email' => "TEXT",
        'customer_type' => "TEXT DEFAULT 'Retail'",
        'credit_limit' => "REAL DEFAULT 0",
        'credit_balance' => "REAL DEFAULT 0",
        'loyalty_points' => "INTEGER DEFAULT 0",
        'date_of_birth' => "TEXT",
        'company_name' => "TEXT",
        'tax_pin' => "TEXT",
        'notes' => "TEXT",
        'profile_photo' => "TEXT",
        'status' => "TEXT DEFAULT 'Active'",
        'updated_at' => "TEXT"
    ];

    foreach ($columns as $name => $definition) {

        if (!in_array($name, $existingColumns)) {

            $sql = "ALTER TABLE customers ADD COLUMN $name $definition";
            $conn->exec($sql);

            echo "Added column: $name<br>";

        } else {

            echo "Column already exists: $name<br>";

        }
    }

    echo "<hr>";
    echo "Customer migration completed successfully.";

} catch (PDOException $e) {

    die("Migration failed: " . $e->getMessage());

}