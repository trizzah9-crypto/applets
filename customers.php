<?php
require("head.php");
include 'db.php';

if(!isset($_SESSION['business_id'])) { die("No business selected"); }
$business_id = $_SESSION['business_id'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Customers</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
.table thead th { background:#f1f1f1; }
</style>
</head>
<body class="bg-light">

<div class="container py-4">

    <h2 class="mb-4 text-center py-3 rounded shadow-sm"
        style="background:rgba(0,123,255,0.1);color:#004549;border-left:5px solid #025659;">
        Customer Management
    </h2>

    <!-- Add Customer -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">Add Customer</div>
        <div class="card-body">
            <div id="msg"></div>

            <div class="row">
                <div class="col-md-4 mb-2">
                    <label>Full Name</label>
                    <input id="name" class="form-control" placeholder="Customer Name">
                </div>
                <div class="col-md-4 mb-2">
                    <label>Phone Number</label>
                    <input id="phone" class="form-control" placeholder="07XXXXXXXX">
                </div>
                <div class="col-md-4 mb-2">
                    <label>Address (optional)</label>
                    <input id="address" class="form-control" placeholder="Location">
                </div>
            </div>

            <button id="saveCustomer" class="btn btn-primary mt-2" style="background:#054960;">Save Customer</button>
        </div>
    </div>

    <!-- Customer List -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold">All Customers</div>
        <div class="card-body">
            <div id="customerTable"></div>
        </div>
    </div>

</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="customerDetailsLabel">Customer Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="customerDetailsContent">
        Loading...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {

    // Load all customers into table
    function loadCustomers() {
        $.get("ajax/get_customers.php", function(data) {
            $("#customerTable").html(data);
        });
    }

    loadCustomers();

    // Add customer
    $("#saveCustomer").click(function() {
        let name = $("#name").val().trim();
        let phone = $("#phone").val().trim();
        let address = $("#address").val().trim();

        if (name.length < 2) {
            $("#msg").html(`
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Enter customer name.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
            return;
        }

        $.post("ajax/add_customer.php", { name, phone, address }, function(res) {
            if (res.status === 'ok') {
                $("#msg").html(`
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Customer added successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);

                $("#name, #phone, #address").val('');

                setTimeout(() => { loadCustomers(); }, 300);

            } else {
                $("#msg").html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ${res.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);
            }
        }, "json").fail(function(jqXHR, textStatus, errorThrown) {
            $("#msg").html(`
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    AJAX error: ${textStatus} - ${errorThrown}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
        });
    });

    // Delete customer
    $(document).on("click", ".deleteCustomer", function() {
        if (!confirm("Delete this customer?")) return;

        let id = $(this).data("id");

        $.post("ajax/delete_customer.php", { id }, function(res) {
            if (res.status === 'ok') {
                loadCustomers();
            } else {
                alert(res.message);
            }
        }, "json");
    });

    // Load customer details modal content (reusable)
    function loadCustomerDetails(customerId) {
        $('#customerDetailsContent').html('Loading...');
        $.get('ajax/get_customer_details.php', { id: customerId }, function(res) {
            if(res.status === 'ok') {
                let html = `
                    <h5>Customer: ${res.customer.name}</h5>
                    <p>Phone: ${res.customer.phone || 'N/A'}</p>
                    <p>Address: ${res.customer.address || 'N/A'}</p>
                    <hr>
                    <h6>Credit Balance: <strong>${res.credit.toFixed(2)}</strong></h6>
                    <hr>

                    <h6>Clear / Reduce Credit</h6>
                    <div class="input-group mb-2">
                        <input type="number" min="0.01" step="0.01" id="creditPayAmount" class="form-control" placeholder="Enter amount to pay">
                        <button class="btn btn-success" id="payCreditBtn">Pay</button>
                    </div>
                    <div id="creditPayMsg"></div>

                    <hr>
                    <h6>Transactions:</h6>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Note</th></tr></thead>
                        <tbody>`;

                if(res.transactions.length === 0) {
                    html += `<tr><td colspan="4" class="text-center">No transactions found</td></tr>`;
                } else {
                    res.transactions.forEach(t => {
                        html += `
                            <tr>
                                <td>${t.date}</td>
                                <td>${t.type}</td>
                                <td>${t.amount.toFixed(2)}</td>
                                <td>${t.note || ''}</td>
                            </tr>
                        `;
                    });
                }

                html += '</tbody></table>';

                $('#customerDetailsContent').html(html);
            } else {
                $('#customerDetailsContent').html(`<div class="alert alert-danger">${res.message}</div>`);
            }
        }, 'json').fail(() => {
            $('#customerDetailsContent').html('<div class="alert alert-danger">Failed to load data.</div>');
        });
    }

    // Show customer details modal on row click
    $(document).on('click', '.customer-row', function() {
        let customerId = $(this).data('customer-id');
        if(!customerId) return;

        // Attach customer id to modal for later use
        $('#customerDetailsModal').data('customer-id', customerId);

        // Show modal
        let modal = new bootstrap.Modal(document.getElementById('customerDetailsModal'));
        modal.show();

        loadCustomerDetails(customerId);
    });

    // Handle partial credit payment
    $(document).on("click", "#payCreditBtn", function() {
        let amount = parseFloat($("#creditPayAmount").val());
        let customerId = $("#customerDetailsModal").data("customer-id");

        if (!amount || amount <= 0) {
            $("#creditPayMsg").html(`<div class="alert alert-danger">Enter a valid amount greater than zero.</div>`);
            return;
        }

        $.post("ajax/pay_credit.php", { id: customerId, amount }, function(res) {
            if (res.status === "ok") {

                $("#creditPayMsg").html(`<div class="alert alert-success">${res.message}</div>`);

                // Reload customer details after a short delay
                setTimeout(() => {
                    loadCustomerDetails(customerId);
                    $("#creditPayMsg").html("");
                }, 700);

            } else {
                $("#creditPayMsg").html(`<div class="alert alert-danger">${res.message}</div>`);
            }
        }, "json").fail(() => {
            $("#creditPayMsg").html(`<div class="alert alert-danger">Failed to process payment. Try again.</div>`);
        });
    });

});
</script>

</body>
</html>

<?php require("foot.php"); ?>
