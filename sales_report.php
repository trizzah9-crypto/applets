<?php
// sales_report.php
require("head.php");
require("db.php"); // or require("db.php") depending on your project

// Ensure business selected

if (!isset($_SESSION['business_id'])) {
    header("Location: business_select.php");
    exit;
}

$business_id = intval($_SESSION['business_id']);
$user_name = $_SESSION['user_name'] ?? ($_SESSION['admin'] ?? 'Cashier');

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Sales Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  /* Page styling */
  body { background:#f5f7fb; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
  .container { max-width:1200px; margin-top:28px; }
  .card { border-radius:12px; box-shadow:0 10px 30px rgba(2,86,89,0.06); }
  .filter-row .form-control, .filter-row .form-select { border-radius:10px; }
  .btn-action { border-radius:10px; padding:10px 14px; }
  .table thead th { background:#f0f2f5; }
  .muted { color:#6b7280; }
  .pulse { animation: pulse 1.6s infinite; }
  @keyframes pulse { 0% { transform:scale(1); } 50% { transform:scale(1.02);} 100% { transform:scale(1);} }
  .small-muted { font-size:0.9rem; color:#6b7280; }
  .totals { font-weight:700; font-size:1rem; }
  .action-btn { margin-right:6px; }
  .loading-overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.6); border-radius:12px; }
  /* Responsive table wrapper */
  .table-responsive { max-height:520px; overflow:auto; padding:8px; }
</style>
</head>
<body>
     <h2 class="mb-4 text-center py-3 rounded shadow-sm"
        style="background:rgba(0,123,255,0.1);color:#004549;border-left:5px solid #025659;">
        Sales Report
    </h2> 

<div class="container">
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <!-- <h4 class="mb-0">Sales Report</h4> -->
        <div class="small-muted">View and export sales — business ID: <?= $business_id ?></div>
      </div>
      <div class="text-end small-muted">Signed in as: <strong><?= htmlspecialchars($user_name) ?></strong></div>
    </div>

    <!-- Filters -->
    <div class="row filter-row g-2 mb-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label small-muted">From</label>
        <input type="date" id="fromDate" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label small-muted">To</label>
        <input type="date" id="toDate" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label small-muted">Payment Method</label>
        <select id="paymentMethod" class="form-select">
          <option value="">All</option>
          <option value="cash">Cash</option>
          <option value="mpesa">M-PESA</option>
          <option value="card">Card</option>
          <option value="credit">Credit</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small-muted">Search (sale # or customer)</label>
        <input type="text" id="search" class="form-control" placeholder="S123... or customer name">
      </div>

      <div class="col-md-3">
        <label class="form-label small-muted">Cashier</label>
        <input type="text" id="cashier" class="form-control" placeholder="Cashier name">
      </div>


      <div class="col-md-9 mt-2">
        <button id="applyFilters" class="btn btn-primary btn-action">Apply</button>
        <button id="resetFilters" class="btn btn-outline-secondary btn-action">Reset</button>
        <button id="downloadCsv" class="btn btn-success btn-action">Download CSV</button>
      </div>

      <div class="col-md-3 text-end mt-2">
        <div class="small-muted">Total: <span id="totalCount">0</span> sales • <span class="totals" id="totalAmount">KES 0.00</span></div>
      </div>
    </div>

    <!-- Table -->
    <div class="position-relative">
      <div id="loading" class="loading-overlay" style="display:none;">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-hover" id="salesTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Sale#</th>
              <th>Date</th>
              <th>Cashier</th>
              <th>Customer</th>
              <th>VAT</th>
              <th>Payment</th>
              <th class="text-end">Amount (KES)</th>
              <th>Actions</th>
              
            </tr>
          </thead>
          <tbody id="salesBody">
            <!-- AJAX rows -->
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Receipt template modal not used; we open as new window for print -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){
  const $loading = $("#loading");
  const $salesBody = $("#salesBody");
  const $totalCount = $("#totalCount");
  const $totalAmount = $("#totalAmount");

  function showLoading(show=true){
    $loading.toggle(show);
  }

  function getFilters(){
    return {
      from: $("#fromDate").val(),
      to: $("#toDate").val(),
      payment: $("#paymentMethod").val(),
      cashier: $("#cashier").val().trim(),
      q: $("#search").val().trim()
    };
  }

  function renderRow(i, row){
    // row: id, sale_number, created_at, customer_name, payment_type, total_amount
    return `
      <tr>
        <td>${i}</td>
        <td>${escapeHtml(row.sale_number)}</td>
        <td>${escapeHtml(row.created_at)}</td>
        <td>${escapeHtml(row.user_name)}</td>
        <td>${escapeHtml(row.customer_name || '')}</td>
        <td>${escapeHtml(row.vat_amount || '')}</td>
        <td>${escapeHtml(row.payment_type)}</td>
       <td class="text-end">${Number(row.total_amount).toFixed(2)}</td>
        <td>
          <button class="btn btn-sm btn-outline-primary viewReceipt" data-id="${row.id}">View / Print</button>
          <button class="btn btn-sm btn-outline-secondary downloadReceipt" data-id="${row.id}">Download</button>
        </td>
      </tr>
    `;
  }

  function escapeHtml(s){ return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function loadSales(){
    const filters = getFilters();
    showLoading(true);
    $.ajax({
      url: "ajax/get_sales.php",
      type: "GET",
      data: filters,
      dataType: "json"
    }).done(function(res){
      if(res.status !== 'ok'){
        $salesBody.html(`<tr><td colspan="7" class="text-center text-danger">${res.message || 'Failed to load'}</td></tr>`);
        $totalCount.text('0');
        $totalAmount.text('KES 0.00');
        return;
      }
      const rows = res.data;
      if(rows.length === 0){
        $salesBody.html(`<tr><td colspan="7" class="text-center">No sales found</td></tr>`);
      } else {
        let html = '';
        for(let i=0;i<rows.length;i++){
          html += renderRow(i+1, rows[i]);
        }
        $salesBody.html(html);
      }
      $totalCount.text(res.count || rows.length);
      $totalAmount.text('KES ' + (Number(res.total_sum || 0)).toFixed(2));
    }).fail(function(){
      $salesBody.html(`<tr><td colspan="7" class="text-center text-danger">Server error</td></tr>`);
    }).always(function(){ showLoading(false); });
  }

  // filters
  $("#applyFilters").click(loadSales);
  $("#resetFilters").click(function(){
    $("#fromDate").val(''); $("#toDate").val(''); $("#paymentMethod").val('');  $("#cashier").val(''); $("#search").val('');
    loadSales();
  });

  // initial load
  loadSales();

  // Download CSV uses same filters - opens new window to trigger download
  $("#downloadCsv").click(function(){
    const f = getFilters();
    const params = $.param(f);
    window.location = 'ajax/export_sales_csv.php?' + params;
  });

  // Delegate receipt view / download
  $(document).on('click', '.viewReceipt', function(){
    const id = $(this).data('id');
    // open receipt in new window and print
    $.get('ajax/get_receipt.php', { id: id }, function(res){
      if(res.status === 'ok'){
        const w = window.open('', '_blank', 'width=400,height=600');
        w.document.write(res.receipt_html);
        w.document.close();
        w.focus();
        w.print();
      } else {
        alert(res.message || 'Failed to load receipt');
      }
    }, 'json').fail(function(){ alert('Request failed'); });
  });

  // Download receipt (open and let user save as PDF)
  $(document).on('click', '.downloadReceipt', function(){
    const id = $(this).data('id');
    $.get('ajax/get_receipt.php', { id: id }, function(res){
      if(res.status === 'ok'){
        const w = window.open('', '_blank', 'width=400,height=600');
        w.document.write(res.receipt_html);
        w.document.close();
        w.focus();
        // no auto-print, user can Save As PDF
      } else {
        alert(res.message || 'Failed to load receipt');
      }
    }, 'json').fail(function(){ alert('Request failed'); });
  });

})();
</script>

<?php require("foot.php"); ?>
</body>
</html>
