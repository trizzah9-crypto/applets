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

        :root{
            --primary:#054960;
            --primary-light:#0a5d78;
            --primary-bright:#0f7c9d;

            --accent:#ff7900;
            --accent-light:#ff9d3f;

            --success:#10b981;
            --danger:#ef4444;

            --radius-xl:28px;
            --radius-lg:22px;
            --radius-md:18px;

            --glass:rgba(255,255,255,.78);
            --glass-border:rgba(255,255,255,.45);

            --shadow-xl:
                0 30px 60px rgba(5,73,96,.15);

            --shadow-lg:
                0 20px 50px rgba(5,73,96,.12);

            --shadow-md:
                0 10px 25px rgba(5,73,96,.08);
        }

        body{
            background:
                radial-gradient(circle at top left,#eaf8ff,#f4f8fb);

            min-height:100vh;

            font-family:"Segoe UI",sans-serif;
        }

        /* Main container */

        .page-card{
            border:none !important;

            border-radius:var(--radius-xl);

            background:var(--glass);

            backdrop-filter:blur(20px);

            border:1px solid var(--glass-border);

            box-shadow:var(--shadow-xl);

            padding:35px !important;
        }

        /* Header */

        .page-header{
            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-light),
                    var(--primary-bright)
                );

            border-radius:var(--radius-xl);

            padding:30px;

            color:white;

            margin-bottom:30px;

            box-shadow:var(--shadow-lg);
        }

        .page-title{
            font-size:32px;
            font-weight:700;
            margin-bottom:6px;
        }

        .page-subtitle{
            opacity:.85;
            font-size:15px;
        }

        /* Order card */

        .order-row{
            background:
                rgba(255,255,255,.85);

            backdrop-filter:blur(15px);

            border:1px solid rgba(255,255,255,.5);

            border-radius:24px;

            padding:25px;

            transition:.3s ease;

            box-shadow:
                0 10px 25px rgba(0,0,0,.05);
        }

        .order-row:hover{
            transform:translateY(-4px);

            box-shadow:
                0 20px 40px rgba(5,73,96,.12);
        }

        .order-title{
            font-size:16px;
            font-weight:700;
            color:var(--primary);
        }

        .order-number{
            display:inline-block;

            background:
                rgba(5,73,96,.08);

            color:var(--primary);

            padding:8px 14px;

            border-radius:50px;

            font-size:13px;

            margin-top:8px;
        }

        .order-customer{
            margin-top:10px;
            color:#6b7280;
            font-size:14px;
        }

        .order-total{
            font-size:26px;
            font-weight:800;
            color:var(--success);
        }

        /* Buttons */

        .btn{
            border-radius:16px !important;
            font-weight:600;
            transition:.3s;
        }

        .btn:hover{
            transform:translateY(-2px);
        }

        .btn-primary{
            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-bright)
                ) !important;

            border:none !important;
        }

        .btn-success{
            background:
                linear-gradient(
                    135deg,
                    var(--success),
                    #34d399
                ) !important;

            border:none !important;
        }

        .btn-outline-danger{
            border:2px solid var(--danger) !important;
            color:var(--danger) !important;
        }

        .btn-outline-danger:hover{
            background:var(--danger) !important;
            color:white !important;
        }

        /* Alerts */

        .alert{
            border:none;
            border-radius:18px;
            backdrop-filter:blur(10px);
        }

        .alert-info{
            background:rgba(59,130,246,.12);
            color:#1e40af;
        }

        .alert-danger{
            background:rgba(239,68,68,.12);
            color:#991b1b;
        }

        /* Scrollbars */

        ::-webkit-scrollbar{
            width:10px;
        }

        ::-webkit-scrollbar-thumb{
            background:var(--primary-light);
            border-radius:50px;
        }

        /* Mobile */

        @media(max-width:768px){

            .page-card{
                padding:20px !important;
            }

            .page-title{
                font-size:24px;
            }

            .order-row{
                padding:20px;
            }

            .order-row{
                flex-direction:column !important;
                align-items:flex-start !important;
            }

            .order-row .text-end{
                width:100%;
                margin-top:20px;
                text-align:left !important;
            }

            .order-row .d-flex{
                width:100%;
            }

            .order-row .btn{
                flex:1;
            }
        }

</style>
</head>
<body>
<div class="container py-4">
    <div class="card page-card p-3">
       <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <div class="page-title">Pending Orders</div>
                <div class="page-subtitle">
                    Uncompleted carts saved from POS terminals
                </div>
            </div>

            <a href="pos.php" class="btn btn-light mt-3 mt-md-0">
                Back to POS
            </a>
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
                        <div class="order-number">${o.order_number}</div>
                        <div class="order-customer">
                            ${o.order_name || 'Walk In Customer'}
                        </div>
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