<?php 
require "permissions.php";

if (!can('view_products')) {
    header("Location: no_access.php");
    exit;
}
require("head.php");
include 'db.php'; 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products - Hardware POS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* Style to visually disable select while keeping it enabled */
        select.readonly {
            pointer-events: none;
            background-color: #e9ecef;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <h2 class="text-center mb-4 py-3 px-4 rounded shadow-sm" 
        style="
            background: rgba(0, 123, 255, 0.1); 
            color: #004549ff; 
            font-weight: 600; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
            border-left: 5px solid #025659ff;
            display: inline-block;
            margin-top: -40px;
            margin-left: 50%;
            transform: translateX(-50%);
        ">
        Inventory.
    </h2>

    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addSupplierModalLabel">Add Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <div id="supplierMsgBox" class="alert d-none" role="alert"></div>

        <form id="addSupplierForm">
          <div class="mb-3">
            <label for="supplierName" class="form-label">Name *</label>
            <input type="text" class="form-control" id="supplierName" name="name" required>
          </div>
          <div class="mb-3">
            <label for="supplierPhone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="supplierPhone" name="phone">
          </div>
          <div class="mb-3">
            <label for="supplierEmail" class="form-label">Email</label>
            <input type="email" class="form-control" id="supplierEmail" name="email">
          </div>
          <div class="mb-3">
            <label for="supplierLocation" class="form-label">Location</label>
            <input type="text" class="form-control" id="supplierLocation" name="location">
          </div>
          <div class="mb-3">
            <label for="supplierPaymentMethod" class="form-label">Payment Method</label>
            <select id="supplierPaymentMethod" name="payment_method" class="form-select">
              <option value="cash" selected>Cash</option>
              <option value="credit">Credit</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Add Supplier</button>
        </form>
        <table class="table table-bordered mt-4" id="suppliersTable">
    <thead>
        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Location</th>
            <th>Payment Method</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

      </div>
    </div>
  </div>
</div>


    <div id="msgBox" class="alert d-none" role="alert"></div>
        <?php if(can("")){ ?>
    <form id="productForm" class="row g-3 p-4 border rounded shadow-sm bg-light bg-opacity-75">

    <input type="hidden"  id="net_selling_price" name="net_selling_price">


     <!-- Barcode -->
<div class="col-md-3">
    <label for="barcode" class="form-label fw-semibold">Barcode</label>
    <input type="text" name="barcode" id="barcode" class="form-control form-control-sm" placeholder="Enter Barcode" required autofocus>
</div>

<!-- Product Name -->
<div class="col-md-3">
    <label for="name" class="form-label fw-semibold">Product Name</label>
    <input type="text" name="name" id="name" class="form-control form-control-sm" placeholder="Product Name" required>
</div>

<!-- Category -->
<div class="col-md-3">
    <label class="form-label fw-semibold">Category</label>
    <div class="d-flex gap-2">
        <select name="category" id="categorySelect" class="form-select form-select-sm" required>
            <option value="">Select category</option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">+</button>
    </div>
</div>

<!-- Supplier Select -->
<div class="col-md-3">
    <label class="form-label fw-semibold">Supplier</label>
    <div class="d-flex gap-2">
        <select name="supplier_id" id="supplierSelect" class="form-select form-select-sm" required>
            <option value="">Select supplier</option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">+</button>
    </div>
</div>

<!-- Add Supplier Modal -->

<!-- Description -->
<div class="col-md-2">
    <label for="description" class="form-label fw-semibold">Description</label>
    <input type="text" name="description" id="description" class="form-control form-control-sm" placeholder="Optional description">
</div>

<!-- Unit -->
<div class="col-md-2">
    <label for="unit" class="form-label fw-semibold">Unit</label>
    <select name="unit" id="unit" class="form-select form-select-sm" required>
        <option value="" selected disabled>Select Unit</option>
        <option value="pcs">Pieces (pcs)</option>
        <option value="kg">Kilograms (kg)</option>
        <option value="liters">Liters</option>
        <option value="m">Meters (m)</option>
        <option value="pack">Pack</option>
    </select>
    <!-- Hidden input to ensure unit is always submitted -->
    <input type="hidden" name="unit" id="unit_hidden" />
</div>

<!-- Cost Price -->
<div class="col-md-2">
    <label for="cost_price" class="form-label fw-semibold">Cost Price (per unit)</label>
    <input type="number" step="0.01" name="cost_price" id="cost_price" class="form-control form-control-sm" placeholder="e.g. 50.00" min="0" required>
</div>

<!-- Cost Per Pack (calculator only) -->
<div class="col-md-2 d-none" id="costPerPackDiv">
    <label class="form-label fw-semibold">Cost per Pack</label>
    <input
        type="number"
        step="0.01"
        id="cost_per_pack"
        class="form-control form-control-sm"
        placeholder="Enter cost per pack"
        min="0"
    >
</div>

<!-- Selling Price -->
<div class="col-md-2">
    <label for="selling_price" class="form-label fw-semibold">Selling Price (per unit)</label>
    <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control form-control-sm" placeholder="e.g. 75.00" min="0" required>
</div>

<!-- Profit Display -->
<div class="col-md-2">
    <label class="form-label fw-semibold">Profit / Unit</label>
    <input type="text" id="profit_display" class="form-control form-control-sm" readonly>
</div>

<div class="col-md-2">
    <label class="form-label fw-semibold">Profit Margin (%)</label>
    <input type="number" step="0.01" id="profit_margin" class="form-control form-control-sm" placeholder="e.g. 30">
</div>

<div class="col-md-2 d-flex align-items-end">
    <button type="button" id="vatBtn" class="btn btn-sm btn-outline-success w-100">
        + VAT (16%)
    </button>
</div>

<!-- Current Stock (Shown only if unit is NOT 'pack') -->
<div class="col-md-3" id="currentStockDiv">
    <label for="stock_qty" class="form-label fw-semibold">Current Stock Quantity</label>
    <input 
        type="number" 
        name="stock_qty" 
        id="stock_qty" 
        class="form-control form-control-sm" 
        placeholder="Enter stock quantity" 
        min="0" 
        step="any" 
        value="0"
    >
</div>

<!-- Add Stock (Shown only if unit is NOT 'pack') -->
<div class="col-md-3" id="addStockDiv">
    <label for="add_stock" class="form-label fw-semibold">Add Stock</label>
    <input type="number" name="add_stock" id="add_stock" class="form-control form-control-sm" placeholder="Add stock quantity"  min="0" step="any" value="0">
</div>

<!-- Pack Size (Pieces per Pack) -->
<div class="col-md-3 d-none" id="packSizeDiv">
    <label for="pack_size" class="form-label fw-semibold">Pieces per Pack</label>
    <input type="number" name="pack_size" id="pack_size" class="form-control form-control-sm" placeholder="e.g. 12" min="1" value="1">
</div>

<!-- Packs Bought -->
<div class="col-md-3 d-none" id="packQtyDiv">
    <label for="pack_qty" class="form-label fw-semibold">Packs Bought</label>
    <input type="number" name="pack_qty" id="pack_qty" class="form-control form-control-sm" placeholder="Number of packs" min="0" value="0">
</div>

<!-- Available Packs (readonly) -->
<div class="col-md-3 d-none" id="availablePacksDiv">
    <label for="available_packs" class="form-label fw-semibold">Available Packs</label>
    <input type="number" id="available_packs" class="form-control form-control-sm" placeholder="Current available packs" min="0" value="0">
</div>

<!-- Add Packs -->
<div class="col-md-3 d-none" id="addPacksDiv">
    <label for="add_packs" class="form-label fw-semibold">Add Packs</label>
    <input type="number" name="add_packs" id="add_packs" class="form-control form-control-sm" placeholder="Add packs" min="0" value="0">
</div>

<!-- Buttons -->
<div class="col-md-2 d-flex align-items-end gap-2">
    <button type="submit" class="btn btn-sm w-50 text-white" style="background:#fd7b08ff;">Save</button>
    <button type="button" id="clearBtn" class="btn btn-sm btn-secondary w-50">Clear</button>
</div>

    </form>
        <?php }?>
<br><br>
        <div class="row mb-3">
    <div class="col-md-4">
        <input type="text" id="productSearch" class="form-control form-control-sm"
               placeholder="Search by barcode or product name">
    </div>
    <div class="col-md-2">
        <button id="searchBtn" class="btn btn-sm btn-primary w-100">
            Search
        </button>
    </div>
    <div class="col-md-2">
        <button id="clearSearchBtn" class="btn btn-sm btn-secondary w-100">
            Clear
        </button>
    </div>

    <div class="col-md-3">
    <select id="categoryFilter" class="form-select form-select-sm">
        <option value="">All Categories</option>
    </select>
    </div>

</div>


    <table class="table table-striped mt-4" id="productTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Description</th>
                <th>Barcode</th>

                <?php if(can("")){ ?>
                    <th>Cost Price</th>
                    <?php } ?>
                
                <th>Selling Price</th>
                <th>Stock</th>
                <th>Unit</th>
                <th>Actions</th>

            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="newCategoryName" class="form-control" placeholder="Category name">
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary btn-sm" onclick="addCategory()">Save</button>
      </div>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<script>
    function loadCategories(selectedName = null) {
        $.getJSON('ajax/get_categories.php', function (data) {
            const select = $('#categorySelect');
            select.html('<option value="">Select category</option>');

            data.forEach(cat => {
                const opt = $('<option>', {
                    value: cat.name,
                    text: cat.name
                });
                if (selectedName && selectedName === cat.name) {
                    opt.prop('selected', true);
                }
                select.append(opt);
            });
        });
    }
    loadCategories();

    function addCategory() {
        const name = $('#newCategoryName').val().trim();
        if (!name) {
            alert('Enter category name');
            return;
        }

        $.post('ajax/add_category.php', { name }, function (res) {
            if (res.status === 'ok') {
                loadCategories(res.id); // auto select new category
                $('#newCategoryName').val('');
                bootstrap.Modal.getInstance(
                    document.getElementById('addCategoryModal')
                ).hide();
            } else {
                alert(res.message || 'Failed to add category');
            }
        }, 'json');
    }

    function loadCategoryFilter() {
            $.getJSON('ajax/get_categories.php', function (data) {
                const filter = $('#categoryFilter');
                filter.html('<option value="">All Categories</option>');

                data.forEach(cat => {
                    filter.append(
                        $('<option>', {
                            value: cat.name,
                            text: cat.name
                        })
                    );
                });
            });
        }

        loadCategoryFilter();


</script>

<script>


$(document).ready(function() {
    // Cache selectors
    const unitSelect = $('#unit');
    const unitHidden = $('#unit_hidden');
    let isEditing = false;
    let vatApplied = false;

    // Load categories into select dropdown
    function loadCategories(selectedName = null) {
        $.getJSON('ajax/get_categories.php', function(data) {
            const select = $('#categorySelect');
            select.html('<option value="">Select category</option>');
            data.forEach(cat => {
                const opt = $('<option>', { value: cat.name, text: cat.name });
                if (selectedName && selectedName === cat.name) opt.prop('selected', true);
                select.append(opt);
            });
        });
    }

    loadCategories();

    // Add new category via modal
    window.addCategory = function() {
        const name = $('#newCategoryName').val().trim();
        if (!name) {
            alert('Enter category name');
            return;
        }
        $.post('ajax/add_category.php', { name }, function(res) {
            if (res.status === 'ok') {
                loadCategories(name);
                $('#newCategoryName').val('');
                bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
            } else {
                alert(res.message || 'Failed to add category');
            }
        }, 'json');
    };

    // Toggle inputs based on selected unit
    function toggleStockInputs() {
        const unit = unitSelect.val();
        unitHidden.val(unit);

        if (unit === 'pack') {
            $('#packSizeDiv, #costPerPackDiv').removeClass('d-none');
            $('#cost_price').prop('readonly', true);

            if (isEditing) {
                $('#availablePacksDiv, #addPacksDiv').removeClass('d-none');
                $('#packQtyDiv, #currentStockDiv, #addStockDiv').addClass('d-none');
                $('#pack_qty').val('');
            } else {
                $('#packQtyDiv').removeClass('d-none');
                $('#availablePacksDiv, #addPacksDiv, #currentStockDiv, #addStockDiv').addClass('d-none');
                $('#available_packs, #add_packs').val('');
            }
        } else {
            $('#currentStockDiv, #addStockDiv').removeClass('d-none');
            $('#packSizeDiv, #packQtyDiv, #availablePacksDiv, #addPacksDiv, #costPerPackDiv').addClass('d-none');
            $('#cost_price').prop('readonly', false);
            $('#pack_size, #pack_qty, #available_packs, #add_packs, #cost_per_pack').val('');
        }
    }

    unitSelect.on('change', function() {
        toggleStockInputs();
    });

    toggleStockInputs();

    // Load products into table
    function loadProducts() {
        $.get('ajax/get_products.php', function(data) {
            $('#productTable tbody').html(data);
        });
    }

    loadProducts();

    // Fetch product by barcode for editing
    function fetchProductForEdit(barcode, showAlertOnNotFound = true) {
        $.getJSON('ajax/get_product_by_barcode.php', { barcode }, function(product) {
            if (product && !product.error) {
                populateEditForm(product);
            } else {
                if (showAlertOnNotFound) alert('Product not found, you can add a new one.');
                resetFormExceptBarcode();
            }
        });
    }

    // Populate form fields when editing
    function populateEditForm(p) {
        isEditing = true;

        $('#barcode').val(p.barcode);
        $('#name').val(p.name);
        loadCategories(p.category);
        $('#description').val(p.description || '');
        $('#cost_price').val(p.cost_price);
        $('#selling_price').val(p.selling_price);
        $('#unit').val(p.unit);
        unitHidden.val(p.unit);

        // Lock unit select to prevent changes while editing
        unitSelect.addClass('readonly').off('change');

        if (p.unit === 'pack') {
            const packSize = parseInt(p.pack_size) || 1;
            $('#pack_size').val(packSize);
            toggleStockInputs();

            const stockQty = parseInt(p.stock_qty) || 0;
            const availablePacks = Math.floor(stockQty / packSize);
            $('#available_packs').val(availablePacks);
            $('#add_packs').val(0);
            $('#stock_qty, #add_stock').val('');

            if (p.cost_price && packSize > 0) {
                const costPerPack = parseFloat(p.cost_price) * packSize;
                $('#cost_per_pack').val(costPerPack.toFixed(2));
            }
        } else {
            toggleStockInputs();
            $('#stock_qty').val(p.stock_qty || 0);
            $('#add_stock').val(0);
            $('#pack_size, #pack_qty, #available_packs, #add_packs, #cost_per_pack').val('');
        }

        // Reset VAT button state on form load
        vatApplied = false;
        $('#vatBtn').removeClass('btn-success').addClass('btn-outline-success').text('+ VAT (16%)');

        // Store the net selling price for calculation (assume selling_price is net)
        $('#net_selling_price').val(parseFloat(p.selling_price).toFixed(2));

        calculateProfit();
    }

    // Reset form but keep barcode (for quick new entry)
    function resetFormExceptBarcode() {
        isEditing = false;

        const barcodeVal = $('#barcode').val();
        $('#productForm')[0].reset();
        $('#barcode').val(barcodeVal);

        unitSelect.removeClass('readonly').off('change').on('change', function() {
            toggleStockInputs();
            unitHidden.val($(this).val());
        });

        toggleStockInputs();
        unitHidden.val(unitSelect.val());

        // Reset VAT button and net price on clear
        vatApplied = false;
        $('#vatBtn').removeClass('btn-success').addClass('btn-outline-success').text('+ VAT (16%)');
        $('#net_selling_price').val('0.00');
        calculateProfit();
    }

    // Clear button resets form fully
    $('#clearBtn').on('click', function() {
        $('#productForm')[0].reset();
        $('#barcode').focus();
        resetFormExceptBarcode();
    });

    // Handle form submission
    $('#productForm').on('submit', function(e) {
        e.preventDefault();

        unitHidden.val(unitSelect.val());

        $.post('ajax/save_product.php', $(this).serialize())
            .done(function(res) {
                if (res.status === 'ok') {
                    showMessage('Product saved successfully!', 'success');
                    $.getJSON('ajax/get_product_by_barcode.php', { barcode: $('#barcode').val().trim() }, function(product) {
                        if (product && !product.error) {
                            populateEditForm(product);
                            loadProducts();
                        }
                    });
                } else {
                    showMessage('Error: ' + (res.message || 'Failed to save product'), 'danger');
                }
            })
            .fail(function(xhr, status, error) {
                showMessage('AJAX error: ' + error, 'danger');
            });
    });

    // Show messages alert
    function showMessage(text, type = 'success') {
        const box = $('#msgBox');
        box.removeClass('d-none alert-success alert-danger');
        box.addClass(type === 'success' ? 'alert-success' : 'alert-danger');
        box.text(text).fadeIn();

        setTimeout(() => {
            box.fadeOut(() => box.addClass('d-none'));
        }, 3000);
    }

    // Search filter
        function filterProducts() {
            const query = $('#productSearch').val().toLowerCase().trim();
            const selectedCategory = $('#categoryFilter').val().toLowerCase();

            $('#productTable tbody tr').each(function () {
                const name = $(this).find('td:eq(1)').text().toLowerCase();
                const category = $(this).find('td:eq(2)').text().toLowerCase();
                const barcode = $(this).find('td:eq(4)').text().toLowerCase();

                const matchesSearch =
                    name.includes(query) || barcode.includes(query);

                const matchesCategory =
                    selectedCategory === '' || category === selectedCategory;

                if (matchesSearch && matchesCategory) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        $('#searchBtn').on('click', filterProducts);
        $('#productSearch').on('keyup', filterProducts);
        $('#categoryFilter').on('change', filterProducts);

        $('#clearSearchBtn').on('click', function () {
            $('#productSearch').val('');
            $('#categoryFilter').val('');
            $('#productTable tbody tr').show();
        });

            // Delete product
    $(document).on('click', '.delete-product', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        if (!id) {
            showMessage('Invalid product ID', 'danger');
            return;
        }

        if (!confirm(`Are you sure you want to permanently delete "${name}"?`)) return;

        $.post('ajax/delete_product.php', { id }, function(res) {
            if (res.status === 'ok') {
                showMessage('Product deleted successfully.', 'success');
                loadProducts();
            } else {
                showMessage(res.message || 'Failed to delete product.', 'danger');
            }
        }, 'json').fail(function() {
            showMessage('Failed to delete product.', 'danger');
        });
    });

    // Click product row to edit
    $(document).on('click', '.product-row', function() {
        const barcode = $(this).data('barcode');
        if (barcode) {
            fetchProductForEdit(barcode, false);
            $('html, body').animate({ scrollTop: 0 }, 'fast');
        }
    });

    // Update cost per unit from pack inputs
    $('#cost_per_pack, #pack_size').on('input', function() {
        const costPerPack = parseFloat($('#cost_per_pack').val());
        const packSize = parseInt($('#pack_size').val());

        if (costPerPack > 0 && packSize > 0) {
            const costPerUnit = costPerPack / packSize;
            $('#cost_price').val(costPerUnit.toFixed(2));
            calculateProfit();
        }
    });

    // Calculate profit and margin
    function calculateProfit() {
        const cost = parseFloat($('#cost_price').val()) || 0;
        const net = parseFloat($('#net_selling_price').val()) || 0;

        const profit = net - cost;
        const margin = cost > 0 ? (profit / cost) * 100 : 0;

        $('#profit_display').val(profit.toFixed(2));
        $('#profit_margin').val(margin.toFixed(2));
    }

    $('#selling_price, #cost_price').on('input', calculateProfit);

    // VAT button toggling
    $('#vatBtn').on('click', function() {
        let net = parseFloat($('#net_selling_price').val()) || parseFloat($('#selling_price').val()) || 0;

        if (!vatApplied) {
            // Apply VAT
            const gross = net * 1.16;
            $('#selling_price').val(gross.toFixed(2));

            vatApplied = true;
            $(this).removeClass('btn-outline-success').addClass('btn-success').text('VAT Applied ✔');
        } else {
            // Remove VAT
            $('#selling_price').val(net.toFixed(2));

            vatApplied = false;
            $(this).removeClass('btn-success').addClass('btn-outline-success').text('+ VAT (16%)');
        }

        calculateProfit();
    });

    // Profit margin input changes selling price accordingly
    $('#profit_margin').on('input', function() {
        const cost = parseFloat($('#cost_price').val()) || 0;
        const margin = parseFloat($(this).val()) || 0;

        if (cost > 0) {
            const net = cost + (cost * margin / 100);
            $('#net_selling_price').val(net.toFixed(2));

            if (vatApplied) {
                $('#selling_price').val((net * 1.16).toFixed(2));
            } else {
                $('#selling_price').val(net.toFixed(2));
            }
            calculateProfit();
        }
    });

    // Adjust net selling price on selling price input (consider VAT)
    $('#selling_price').on('input', function() {
        const entered = parseFloat($(this).val()) || 0;

        if (vatApplied) {
            // Selling price includes VAT → calculate net price
            const net = entered / 1.16;
            $('#net_selling_price').val(net.toFixed(2));
        } else {
            // Selling price is net price
            $('#net_selling_price').val(entered.toFixed(2));
        }

        calculateProfit();
    });
});

$(document).ready(function() {
    // Load suppliers for dropdown
    function loadSuppliers(selectedId = null) {
        $.getJSON('ajax/get_suppliers.php', function(data) {
            const select = $('#supplierSelect');
            select.html('<option value="">Select supplier</option>');
            data.forEach(supplier => {
                const opt = $('<option>', {
                    value: supplier.supplier_id,
                    text: supplier.name + (supplier.phone ? ' (' + supplier.phone + ')' : '')
                });
                if (selectedId && selectedId == supplier.supplier_id) {
                    opt.prop('selected', true);
                }
                select.append(opt);
            });
        });
    }

    loadSuppliers();

    // Handle Add Supplier form submit
$('#addSupplierForm').on('submit', function(e) {
    e.preventDefault();

    const name = $('#supplierName').val().trim();
    if (!name) {
        alert('Supplier name is required!');
        $('#supplierName').focus();
        return;
    }

    const email = $('#supplierEmail').val().trim();
    if (email && !validateEmail(email)) {
        alert('Please enter a valid email address.');
        $('#supplierEmail').focus();
        return;
    }

$.post('ajax/add_supplier.php', $(this).serialize(), function(res) {
   if (res.status === 'ok') {
    loadSuppliers(res.supplier_id);
    $('#addSupplierForm')[0].reset();

    showSupplierMessage('Supplier added successfully!', 'success'); // Show message first

    // Then hide modal after short delay to let user see the message
    setTimeout(() => {
        bootstrap.Modal.getInstance(
            document.getElementById('addSupplierModal')
        ).hide();
    }, 1500);
}
 else {
        showSupplierMessage(res.message || 'Failed to add supplier', 'danger'); // <-- here
    }
}, 'json').fail(function() {
    showSupplierMessage('AJAX error while adding supplier.', 'danger'); // <-- here
});


});


});

// Email validation function
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showSupplierMessage(text, type = 'success') {
    const box = $('#supplierMsgBox');
    box.stop(true, true);
    box.removeClass('alert-success alert-danger').addClass(type === 'success' ? 'alert-success' : 'alert-danger');
    box.text(text);
    box.removeClass('d-none').fadeIn(200);
    setTimeout(() => {
        box.fadeOut(400, function() {
            box.addClass('d-none');
        });
    }, 3000);
}

function loadSuppliersTable() {
    $.getJSON('ajax/get_suppliers.php', function(data) {
        const tbody = $('#suppliersTable tbody');
        tbody.empty();

        if (data.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center">No suppliers found.</td></tr>');
            return;
        }

        data.forEach(supplier => {
            const row = $('<tr>');
            row.append($('<td>').text(supplier.name));
            row.append($('<td>').text(supplier.phone || '-'));
            row.append($('<td>').text(supplier.email || '-'));
            row.append($('<td>').text(supplier.location || '-'));
            row.append($('<td>').text(supplier.payment_method.charAt(0).toUpperCase() + supplier.payment_method.slice(1)));
            tbody.append(row);
        });
    });
}



</script>
</body>
</html>

<?php require("foot.php"); ?>
