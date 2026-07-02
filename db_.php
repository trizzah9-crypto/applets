<?php
try {
    $conn = new PDO('sqlite:' . __DIR__ . '/mamba.db');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

header('Content-Type: text/plain');

// Get all table names
$tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(PDO::FETCH_COLUMN);

if (empty($tables)) {
    echo "No tables found in this database.\n";
    exit;
}

echo "===== DATABASE SCHEMA =====\n\n";

foreach ($tables as $table) {
    echo "TABLE: $table\n";
    echo str_repeat('-', 40) . "\n";

    // Get column info for this table
    $columns = $conn->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $pk = $col['pk'] ? ' [PRIMARY KEY]' : '';
        $notnull = $col['notnull'] ? ' NOT NULL' : '';
        $default = $col['dflt_value'] !== null ? ' DEFAULT ' . $col['dflt_value'] : '';
        echo "  - {$col['name']} ({$col['type']}){$notnull}{$pk}{$default}\n";
    }

    // Get foreign keys (helps show relationships)
    $fks = $conn->query("PRAGMA foreign_key_list(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($fks)) {
        echo "  Foreign Keys:\n";
        foreach ($fks as $fk) {
            echo "    - {$fk['from']} -> {$fk['table']}({$fk['to']})\n";
        }
    }

    // Row count (helps me understand data volume for charts)
    $count = $conn->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo "  Row count: $count\n";

    // Sample row (helps me see real data shape/format)
    $sample = $conn->query("SELECT * FROM `$table` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($sample) {
        echo "  Sample row: " . json_encode($sample) . "\n";
    }

    echo "\n";
}

echo "===== END SCHEMA =====\n";