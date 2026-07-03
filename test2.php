<?php
require 'db.php'; // PDO SQLite connection

 

try {

    $conn->exec("
        ALTER TABLE payments
        ADD COLUMN business_id INTEGER
    ");

    echo "business_id added successfully.";

} catch (PDOException $e) {

    echo $e->getMessage();

}