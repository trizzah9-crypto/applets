<?php
require "../permissions.php";
include '../db.php';

$business_id = $_SESSION['business_id'] ?? 0;
$store_id    = $_SESSION['store_id'] ?? 0;

if ($business_id == 0) {
    echo "<tr><td colspan='9'>No business selected</td></tr>";
    exit;
}
if ($store_id > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM products
        WHERE business_id = ?
          AND store_id = ?
          AND deleted_at IS NULL
        ORDER BY id DESC
    ");
    $stmt->execute([$business_id, $store_id]);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM products
        WHERE business_id = ?
          AND deleted_at IS NULL
        ORDER BY id DESC
    ");
    $stmt->execute([$business_id]);
}

$output = '';

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    // 🔒 SOURCE OF TRUTH
    $stockQty = (int)$row['stock_qty'];
    $packSize = (int)$row['pack_size'];
    $unit     = strtolower($row['unit']);

    if ($unit === 'pack' && $packSize > 0) {
        $packs = intdiv($stockQty, $packSize);
        $leftoverPieces = $stockQty % $packSize;

        $stockDisplay = $leftoverPieces > 0
            ? "$packs packs + $leftoverPieces pcs"
            : "$packs packs";

        $unitDisplay = "pack of $packSize";
    } else {
        $stockDisplay = $unit === 'pcs'
            ? "$stockQty pcs"
            : "$stockQty " . htmlspecialchars($row['unit']);

        $unitDisplay = htmlspecialchars($row['unit']);
    }

   $output .= "<tr class='product-row' data-barcode='".htmlspecialchars($row['barcode'], ENT_QUOTES)."'>";

$output .= "<td>".htmlspecialchars($row['id'])."</td>";
$output .= "<td>".htmlspecialchars($row['name'])."</td>";
$category = isset($row['category']) ? htmlspecialchars($row['category']) : '';
$output .= "<td>$category</td>";
$output .= "<td>".htmlspecialchars($row['description'])."</td>";
$output .= "<td>".htmlspecialchars($row['barcode'])."</td>";

// Cost price
if (can('view_cost_price')) {
    $output .= "<td>".number_format($row['cost_price'], 2)."</td>";
}

$output .= "<td>".number_format($row['selling_price'], 2)."</td>";
$output .= "<td>$stockDisplay</td>";
$output .= "<td>$unitDisplay</td>";

// 🗑 ACTIONS COLUMN (INSIDE TR)
if (can('delete_products')) {
    $output .= "
        <td>
            <button 
                type='button'
                class='btn btn-sm btn-danger delete-product'
                data-id='{$row['id']}'
                data-name='".htmlspecialchars($row['name'], ENT_QUOTES)."'>
                Delete
            </button>
        </td>
    ";
} else {
    $output .= "<td>-</td>";
}

$output .= "</tr>";

}

echo $output;
?>
