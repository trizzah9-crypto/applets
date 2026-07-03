<?php
require 'db.php';

try {

    $check = $conn->query("
        PRAGMA table_info(categories)
    ");

    $columns = $check->fetchAll(PDO::FETCH_ASSOC);

    $exists = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'business_id') {
            $exists = true;
            break;
        }
    }

    if (!$exists) {

        $conn->exec("
            ALTER TABLE categories
            ADD COLUMN business_id INTEGER
        ");

        echo "
        <div style='padding:15px;background:#d4edda;color:#155724;
        border:1px solid #c3e6cb;border-radius:8px'>
            Categories table altered successfully.<br>
            Column <b>business_id</b> added.
        </div>
        ";

    } else {

        echo "
        <div style='padding:15px;background:#fff3cd;color:#856404;
        border:1px solid #ffeeba;border-radius:8px'>
            Table already altered.<br>
            Column <b>business_id</b> already exists.
        </div>
        ";
    }

} catch(PDOException $e) {

    echo "
    <div style='padding:15px;background:#f8d7da;color:#721c24;
    border:1px solid #f5c6cb;border-radius:8px'>
        Error:<br>
        {$e->getMessage()}
    </div>
    ";
}