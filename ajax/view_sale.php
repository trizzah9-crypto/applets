<?php
require("../dbconnect.php");

$id = $_POST['id'];
$sale = $conn->query("SELECT * FROM sales WHERE id=$id")->fetch_assoc();
$items = $conn->query("SELECT * FROM sale_items WHERE sale_id=$id");

echo "<h4>Receipt</h4>
<b>Date:</b> {$sale['date']} <br>
<b>Sale No:</b> {$sale['sale_number']} <br>
<b>Customer:</b> {$sale['customer_name']} <br>
<b>Payment:</b> {$sale['payment_method']} <br>
<hr>";

echo "<table class='table table-bordered'>
<thead><tr>
<th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th>
</tr></thead><tbody>";

while($i = $items->fetch_assoc()) {
    echo "<tr>
            <td>{$i['product_id']}</td>
            <td>{$i['quantity']}</td>
            <td>{$i['price']}</td>
            <td>{$i['subtotal']}</td>
          </tr>";
}

echo "</tbody></table>";

echo "<h4 class='text-end'>TOTAL: KES " . number_format($sale['total_amount'], 2) . "</h4>";
