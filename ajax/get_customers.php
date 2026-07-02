<?php
require("../db.php");
session_start();

$business_id = $_SESSION['business_id'] ?? 0;

if (!$business_id) {
    echo '<div class="alert alert-danger">No business selected.</div>';
    exit;
}

// Prepare statement to get customers for this business
$stmt = $conn->prepare("SELECT * FROM customers WHERE business_id = ? ORDER BY id DESC");
$stmt->execute([$business_id]);

// Fetch all customers
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$customers) {
    echo '<div class="alert alert-info">No customers found.</div>';
    exit;
}

echo '<table class="table table-bordered table-hover">
       <thead class="table-dark" style="color:black;">
            <tr>
                <th style="color:black;">ID</th>
                <th style="color:black;">Name</th>
                <th style="color:black;">Phone</th>
                <th style="color:black;">Address</th>
                <th style="color:black;">Credit</th>
                <th style="color:black;">Actions</th>
            </tr>
        </thead>
        <tbody>';

foreach ($customers as $c) {
    $id = $c['id'];

    // Prepare statement to get credit balance for this customer
    $balStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount),0) AS balance 
        FROM customer_account_transactions 
        WHERE customer_id = ?
    ");
    $balStmt->execute([$id]);
    $bal = $balStmt->fetch(PDO::FETCH_ASSOC)['balance'];

    $balFmt = number_format($bal, 2);

    // Color formatting
    $color = ($bal < 0) ? "text-danger fw-bold" : "text-success fw-bold";

    echo "<tr class='customer-row' data-customer-id='{$c['id']}'>
            <td>{$c['id']}</td>
            <td>{$c['name']}</td>
            <td>{$c['phone']}</td>
            <td>{$c['address']}</td>
            <td class='{$color}'>KES {$balFmt}</td>
            <td>
                <button class='btn btn-danger btn-sm deleteCustomer' data-id='{$c['id']}'>
                    Delete
                </button>
            </td>
        </tr>";
}

echo "</tbody></table>";
?>
