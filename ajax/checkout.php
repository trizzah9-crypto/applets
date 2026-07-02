<?php
include '../db.php';
$cart = json_decode($_POST['cart'], true);
$cash = floatval($_POST['cash']);
if (!$cart) exit("Invalid cart");

$total = 0;
$receipt = "<h3>🧾 Hardware Store Receipt</h3><table border='1' cellspacing='0' cellpadding='5'><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>";

foreach ($cart as $item) {
  $rowTotal = $item['qty'] * $item['price'];
  $total += $rowTotal;

  // Update stock
  $conn->query("UPDATE products SET stock_qty = stock_qty - {$item['qty']} WHERE id = {$item['id']}");

  $receipt .= "<tr>
    <td>{$item['name']}</td>
    <td>{$item['qty']}</td>
    <td>{$item['price']}</td>
    <td>$rowTotal</td>
  </tr>";
}

$change = $cash - $total;
$conn->query("INSERT INTO sales (total_amount, date, payment_method) VALUES ($total, NOW(), 'cash')");

$receipt .= "</table>
<p><b>Total:</b> Ksh $total</p>
<p><b>Cash:</b> Ksh $cash</p>
<p><b>Change:</b> Ksh $change</p>
<p><i>Thank you for shopping with us!</i></p>";

echo "OK|" . $receipt;
?>
