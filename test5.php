<?php
require_once("db.php"); // Adjust path if necessary

try {

    $conn->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Mark non-credit sales as fully paid
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE sales
        SET paid_amount = total_amount
        WHERE paid_amount = 0
        AND (
            LOWER(COALESCE(payment_type,'')) != 'credit'
            AND LOWER(COALESCE(payment_method,'')) != 'credit'
        )
    ");

    $stmt->execute();
    $paidUpdated = $stmt->rowCount();


    /*
    |--------------------------------------------------------------------------
    | 2. Calculate outstanding balances
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE sales
        SET balance_due = total_amount - paid_amount
        WHERE balance_due = 0
    ");

    $stmt->execute();
    $balanceUpdated = $stmt->rowCount();


    /*
    |--------------------------------------------------------------------------
    | 3. Populate line totals from subtotal
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE sale_items
        SET line_total = subtotal
        WHERE line_total = 0
    ");

    $stmt->execute();
    $lineTotalUpdated = $stmt->rowCount();


    /*
    |--------------------------------------------------------------------------
    | 4. Calculate cost value of each line
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE sale_items
        SET line_cost = quantity * cost_price
        WHERE line_cost = 0
    ");

    $stmt->execute();
    $lineCostUpdated = $stmt->rowCount();


    /*
    |--------------------------------------------------------------------------
    | 5. Calculate line profit
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE sale_items
        SET profit = line_total - line_cost
        WHERE profit = 0
    ");

    $stmt->execute();
    $profitUpdated = $stmt->rowCount();


    /*
    |--------------------------------------------------------------------------
    | 6. Update refunded sales balances
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE sales
        SET balance_due = 0
        WHERE status = 'refunded'
    ");

    $stmt->execute();
    $refundedUpdated = $stmt->rowCount();


    $conn->commit();

    echo "<h2>Sales Metrics Migration Completed Successfully</h2>";

    echo "<table border='1' cellpadding='10' cellspacing='0'>";
    echo "<tr><th>Migration Step</th><th>Rows Updated</th></tr>";
    echo "<tr><td>Paid Amount Updated</td><td>{$paidUpdated}</td></tr>";
    echo "<tr><td>Balance Due Updated</td><td>{$balanceUpdated}</td></tr>";
    echo "<tr><td>Line Totals Calculated</td><td>{$lineTotalUpdated}</td></tr>";
    echo "<tr><td>Line Costs Calculated</td><td>{$lineCostUpdated}</td></tr>";
    echo "<tr><td>Profits Calculated</td><td>{$profitUpdated}</td></tr>";
    echo "<tr><td>Refund Balances Fixed</td><td>{$refundedUpdated}</td></tr>";
    echo "</table>";

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    die("Migration failed: " . $e->getMessage());
}