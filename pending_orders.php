<?php
require("head.php");
 

if (!isset($_SESSION['business_id'])) {
    die("No business selected");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pending Orders</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body{
            background:#f4f6f9;
        }
        .page-card{
            border:none;
            border-radius:16px;
            box-shadow:0 4px 15px rgba(0,0,0,.08);
        }
        .order-row{
            border-radius:12px;
            padding:14px;
            background:#fff;
            border:1px solid #e9ecef;
            transition:.2s;
        }
        .order-row:hover{
            transform:translateY(-1px);
            box-shadow:0 4px 12px rgba(0,0,0,.08);
        }
        .order-title{
            font-weight:700;
            color:#0d6efd;
        }
        .order-total{
            font-weight:800;
            color:#198754;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card page-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">Pending Orders</h4>
                <small class="text-muted">Uncompleted carts saved from POS</small>
            </div>
            <a href="pos.php" class="btn btn-primary">Back to POS</a>
        </div>

        <div id="ordersList">
            <div class="text-muted">Loading orders...</div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadDraftOrders(){
    $.getJSON('ajax/get_draft_orders.php', function(res){
        if(res.status !== 'ok'){
            $('#ordersList').html('<div class="alert alert-danger">Failed to load draft orders</div>');
            return;
        }

        if(!res.orders.length){
            $('#ordersList').html('<div class="alert alert-info">No pending orders found</div>');
            return;
        }

        let html = '';
        res.orders.forEach(o => {
            html += `
                <div class="order-row mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="order-title">Updated: ${o.updated_at}</div>
                        <div class="text-muted small">${o.order_number}</div>
                        <div class="text-muted small">${o.order_name || 'Walk In'}</div>
                    </div>
                    <div class="text-end">
                        <div class="order-total">KES ${parseFloat(o.total_amount).toFixed(2)}</div>
                        <div class="mt-2 d-flex gap-2 justify-content-end">
                            <a href="pos.php?draft_id=${o.id}" class="btn btn-sm btn-success">Continue</a>
                            <button class="btn btn-sm btn-outline-danger delete-order" data-id="${o.id}">Delete</button>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#ordersList').html(html);
    });
}

$(document).on('click', '.delete-order', function(){
    let id = $(this).data('id');
    if(!confirm('Delete this draft order?')) return;

    $.post('ajax/delete_draft_order.php', {draft_id: id}, function(res){
        if(res.status === 'ok'){
            loadDraftOrders();
        } else {
            alert(res.message || 'Failed to delete draft');
        }
    }, 'json');
});

loadDraftOrders();
</script>
</body>
</html>

<?php require("foot.php"); ?>