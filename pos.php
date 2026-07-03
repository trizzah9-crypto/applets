<?php
require("head.php");
require 'db.php'; // assuming $conn is a PDO instance
?>
<!DOCTYPE html>
<html>
<head>
<title>POS - Hardware</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
.search-results {
    position: absolute;
    background: #fff;
    border: 1px solid #ccc;
    width: 100%;
    z-index: 10;
    max-height: 200px;
    overflow-y: auto;
}
.search-item {
    padding: 6px 10px;
    cursor: pointer;
}
.search-item:hover {
    background: rgba(5, 73, 96, 0.9);
    color: #fff;
}
.qty-btn {
    padding: 2px 6px;
    font-size: 16px;
    font-weight: bold;
    line-height: 1;
    user-select: none;
}

.category-tab {
    padding: 10px 18px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #ddd;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.15s ease;
}

.category-tab.active {
    background: #ff9acb;
    border-color: #ff9acb;
    color: #000;
}

.category-tab:hover {
    background: #ffe3ef;
}

.product-tile {
    background: #fff;
    border-radius: 14px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    position: relative;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: transform 0.1s ease, box-shadow 0.1s ease;
}

.product-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.15);
}

.product-name {
    font-weight: 600;
    font-size: 14px;
}

.product-price {
    font-weight: 700;
    margin-top: 6px;
}

.product-qty {
    position: absolute;
    top: 6px;
    right: 8px;
    background: #000;
    color: #fff;
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 6px;
}

.product-price {
    font-size: 16px;
    font-weight: 700;
}
.cart-item-name {
    font-size: 14px;
}

.cart-qty {
    font-size: 14px;
    font-weight: 600;
}

.total-amount {
    font-size: 22px;
    font-weight: 800;
}
.out-of-stock{
    opacity:0.5;
    filter:grayscale(100%);
    cursor:not-allowed;
}

@media (max-width: 768px) {

    .category-tab{
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 4px;
    }

}

@media (max-width:768px){

.mobile-nav{
    position:fixed;
    bottom:0;
    left:0;
    right:0;
    height:65px;
    display:flex;
    justify-content:space-around;
    align-items:center;
    background:#fff;
    box-shadow:0 -2px 12px rgba(0,0,0,.12);
    z-index:1000;
}

.nav-btn{
    flex:1;
    height:100%;
    border:none;
    background:transparent;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;      /* Centers icon & text */
    gap:2px;
    color:#777;
    transition:.25s;
}

.nav-btn i,
.nav-btn{
    font-size:20px;
}

.nav-btn small{
    font-size:11px;
    margin:0;
}

/* Active tab */
.nav-btn.active{
    color:#0d6efd;
    font-weight:700;
}

.nav-btn.active small{
    color:#0d6efd;
}

.nav-btn.active::before{
    content:"";
    width:28px;
    height:3px;
    background:#0d6efd;
    border-radius:20px;
    position:absolute;
    top:6px;
}

/* Pay button */
.nav-btn.pay{
    color:#fff;
    background:#0d6efd;
    width:58px;
    height:58px;
    border-radius:50%;
    margin-top:-22px;
    flex:0 0 58px;
    box-shadow:0 8px 20px rgba(13,110,253,.35);
}

}

@media (max-width:768px){

.table-responsive{
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
}

#cartTable{
    white-space:nowrap;
    font-size:12px;
}

#cartTable th,
#cartTable td{
    padding:6px 4px;
}

}




/* Desktop */
.payment-buttons{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    gap:10px;
}

.payment-btn{
    width:25%;
}

/* Phone */
@media (max-width:768px){

    .payment-buttons{
        flex-direction:row;
        gap:8px;
        align-items:center;
        margin-top:20px;
    }

    .payment-btn{
        flex:1;
        height:42px;
        margin:0;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:12px;
    }

}

@media (max-width:768px){

    .container{
        padding-left:4px !important;
        padding-right:4px !important;
    }

}

@media (max-width:768px){

    #pageTitle{
        display:block !important;
        width:100%;
        margin:8px 0 10px 0 !important;
        transform:none !important;
        text-align:center;
        font-size:16px;
        padding:10px !important;
        border-left:none;
        border-radius:8px;
        letter-spacing:.5px;
    }

}

</style>
</head>
<body class="bg-light">
            <h2 id="pageTitle"
    class="text-center mb-4 py-3 px-4 rounded shadow-sm"
    style="
        background: rgba(0,123,255,.1);
        color:#004549;
        font-weight:600;
        letter-spacing:1px;
        text-transform:uppercase;
        border-left:5px solid #025659;
        display:inline-block;
        margin-top:-40px;
        margin-left:50%;
        transform:translateX(-50%);
    ">
    POS-Sell Items
</h2>
    <div class="split" style="display: flex;">

<div id="cartView" class="container py-4 " style=" ">

    <div class="d-flex mb-2 gap-2 align-items-center">
    <select id="cartSelector" class="form-select w-auto"></select>
    <button id="newCartBtn" class="btn btn-sm btn-outline-primary">+ New Cart</button>
</div>

    <div class="position-relative">
        <input type="text" id="barcodeInput" class="form-control mb-3" placeholder="Scan barcode or type product name" autocomplete="off" autofocus>
        <div id="searchResults" class="search-results"></div>
    </div>
    <div class="table-responsive">
    <table class="table table-bordered" id="cartTable">
        <thead class="table-secondary">
            <tr><th>Name</th><th>Description</th><th style="width:160px;">Qty</th><th>Price</th><th>Subtotal</th><th>Action</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    </div>

    <div class="row mb-3 align-items-center">
  <div class="col-md-3 offset-md-9 text-end">
    <label for="discountInput" class="form-label fw-bold">Discount (KES)</label>
    <input type="number" min="0" step="0.01" id="discountInput" class="form-control" value="0">
  </div>
  <div class="col-md-3 offset-md-9 text-end mt-2">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" value="1" id="applyVat" checked>
      <label class="form-check-label" for="applyVat">
        Apply VAT (16%)
      </label>
    </div>
  </div>

</div>

          <h6 class="text-end text-muted">VAT (KES): <span id="vatAmountDisplay">0.00</span></h6>


    <h4 class="text-end mt-3">Total: <span class="fw-bold text-success">KES <span id="total">0.00</span></span></h4>

    <div class="payment-buttons">
        <button id="clearCartBtn" class="btn btn-outline-secondary payment-btn">
            CLEAR
        </button>

        <button id="openPaymentBtn" class="btn btn-primary payment-btn">
            PAY
        </button>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Customer name (optional)</label>
          <input id="customerName" class="form-control" placeholder="Customer full name">
        </div>

        <div class="mb-2">
          <label class="form-label">Payment method</label>
          <select id="paymentMethod" class="form-select">
            <option value="cash">Cash</option>
            <option value="mpesa">M-PESA</option>
            <option value="card">Card</option>
            <option value="credit">Credit (Customer Owes)</option>
          </select>
        </div>

        <!-- Credit Customer selector (shown only when paymentMethod === 'credit') -->
        <div id="creditCustomerBlock" class="mb-2 d-none">
            <label class="form-label">Select Customer</label>
            <select id="creditCustomerId" class="form-select">
                <option value="">-- Select Customer --</option>
                <?php
                    // Load customers for this business using PDO
                    $bid = $_SESSION['business_id'] ?? 0;
                    if ($bid) {
                        $stmt = $conn->prepare("SELECT id, name, phone FROM customers WHERE business_id = :bid ORDER BY name ASC");
                        $stmt->execute(['bid' => intval($bid)]);
                        while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $nameDisplay = htmlspecialchars($c['name']);
                            $phoneDisplay = htmlspecialchars($c['phone']);
                            echo "<option value='{$c['id']}'>{$nameDisplay} ({$phoneDisplay})</option>";
                        }
                    }
                ?>
            </select>
        </div>

        <div id="cashBlock" class="mb-2">
          <label class="form-label">Cash received (KES)</label>
          <input id="cashReceived" type="number" min="0" class="form-control" value="">
          <div class="mt-2">Change: KES <span id="cashChange">0.00</span></div>
        </div>

        <div id="nonCashNote" class="alert alert-light d-none small">For non-cash payments, you may add a reference later in notes.</div>
        <div id="paymentMsg" class="text-danger mt-1"></div>
      </div>

      <div class="modal-footer">
        <button id="confirmPayment" class="btn btn-success">Confirm & Complete Sale</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>



<div id="productsView" class="second p-3" style="background:#f5f6f8; min-width:57%; max-height:90vh; overflow-y:auto; margin-top: 28px;">

    <!-- Product search -->
    <div class="mb-3">
        <input type="text" id="tileSearch" class="form-control" placeholder="Search products...">
    </div>

    <!-- Category tabs -->
    <div class="d-flex gap-2 mb-3 flex-wrap" id="categoryTabs"></div>

    <!-- Product tiles -->
    <div class="row g-3" id="productTiles"></div>

</div>

    
</div>
</div>

<div class="mobile-nav d-md-none">
    <button data-view="productsView" class="nav-btn active">
        🏠
        <small>Home</small>
    </button>
    <button data-view="cartView" class="nav-btn">
        🛒
        <small>Cart</small>
    </button>
    <button id="mobilePayBtn" class="nav-btn pay">
        💳
        <small>Pay</small>
    </button>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function getApplyVat() {
    return getActiveCart().applyVat;
}


function updateTotalWithDiscount() {
  let total = calculateTotal();
  let discount = parseFloat($('#discountInput').val()) || 0;
  if (discount < 0) discount = 0;
  if (discount > total) discount = total;

  let applyVat = getApplyVat();
  let vatRate = 0.16;

  let totalAfterDiscount = total - discount;
  if (totalAfterDiscount < 0) totalAfterDiscount = 0;

  let vatAmount = applyVat ? totalAfterDiscount * vatRate : 0;

  let finalTotal = applyVat ? totalAfterDiscount + vatAmount : totalAfterDiscount;

  $('#total').text(finalTotal.toFixed(2));
  $('#vatAmountDisplay').text(vatAmount.toFixed(2));
}

$('#discountInput, #applyVat').on('input change', function () {
  updateTotalWithDiscount();
  updateCashChange();
});

$('#applyVat').on('change', function(){
    getActiveCart().applyVat = this.checked;
    updateTotalWithDiscount();
    updateCashChange();
      saveDraftOrder();
});

</script>

<script> 
let carts = {};
let activeCartId = 1;
let cartCounter = 1;

// First order/cart
carts[1] = {
    items: {},
    discount: 0,
    applyVat: true,
    draftId: 0,
    orderName: 'Order 1'
};

function getActiveCart() {
    return carts[activeCartId];
}

function getCartTotal(cart) {
    let total = 0;
    for (let id in cart.items) {
        total += (parseFloat(cart.items[id].qty) || 0) * (parseFloat(cart.items[id].selling_price) || 0);
    }
    return total;
}

function refreshCartSelector() {
    let html = '';
    for (let id in carts) {
        let cart = carts[id];
        let total = getCartTotal(cart);
        html += `<option value="${id}">
            ${cart.orderName || 'Order ' + id} - KES ${total.toFixed(2)}
        </option>`;
    }
    $('#cartSelector').html(html).val(activeCartId);
}

refreshCartSelector();

$('#cartSelector').on('change', function () {
    activeCartId = parseInt($(this).val());

    $('#discountInput').val(carts[activeCartId].discount);
    $('#applyVat').prop('checked', carts[activeCartId].applyVat);

    renderCart();
});

$('#newCartBtn').click(function () {
    cartCounter++;

    carts[cartCounter] = {
        items: {},
        discount: 0,
        applyVat: true,
        draftId: 0,
        orderName: 'Order ' + cartCounter
    };

    activeCartId = cartCounter;
    refreshCartSelector();
    renderCart();
});

const QTY_STEP = 1;  // Fractional step for quantity (quarter)

// --- SEARCH & BARCODE ---
$('#barcodeInput').on('input', function(){
    let query = $(this).val().trim();
    if(query.length < 2) { $('#searchResults').hide(); return; }

    $.get('ajax/search_products.php', {term: query}, function(data){
        $('#searchResults').html(data).show();
    });
});

$(document).on('click', '.search-item', function(){
    let product = {
        id: $(this).data('id'),
        name: $(this).data('name'),
        description: $(this).data('description'),
        selling_price: parseFloat($(this).data('price'))
    };

    addToCart(product);
    $('#searchResults').hide();
    $('#barcodeInput').val('').focus();
});

$('#barcodeInput').on('keypress', function(e){
    if(e.which == 13){
        e.preventDefault();
        let code = $(this).val().trim();
        $(this).val('');
        $.getJSON('ajax/get_product_by_barcode.php', {barcode: code}, function(product){
            if(!product || product.error){
                alert('Product not found');
                return;
            }
            addToCart(product);
        });
    }
});

// --- CART LOGIC ---
function addToCart(product){
    let cartItems = getActiveCart().items;

    if(!cartItems[product.id]){
        cartItems[product.id] = {
            id: product.id,
            name: product.name,
            description: product.description || "",
            selling_price: parseFloat(product.selling_price),
            qty: 1
        };
    } else {
        cartItems[product.id].qty += QTY_STEP;
    }
    renderCart();
    saveDraftOrder();
}

function renderCart(){
    let cartItems = getActiveCart().items;
    let tbody = '';
    let total = 0;

    for(let id in cartItems){
        let p = cartItems[id];
        let sub = p.qty * p.selling_price;
        total += sub;

        tbody += `
        <tr>
            <td>${p.name}</td>
            <td>${p.description}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-secondary decrease" data-id="${id}">–</button>
                <input type="number" class="form-control d-inline-block text-center" style="width:70px" data-id="${id}" value="${p.qty}">
                <button class="btn btn-sm btn-outline-secondary increase" data-id="${id}">+</button>
            </td>
            <td>${p.selling_price.toFixed(2)}</td>
            <td>${sub.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger delete-item" data-id="${id}">🗑</button></td>
        </tr>`;
    }

    $('#cartTable tbody').html(tbody);
    updateTotalWithDiscount();
    updateCashChange();
}

function calculateTotal(){
    let total = 0;
    let items = getActiveCart().items;

    for(let id in items){
        total += items[id].qty * items[id].selling_price;
    }
    return parseFloat(total.toFixed(2));
}

 function saveDraftOrder() {
    let cart = getActiveCart();

    if (Object.keys(cart.items).length === 0) {
        return;
    }

    $.post('ajax/save_draft_order.php', {
        draft_id: cart.draftId,
        order_name: cart.orderName,
        cart: JSON.stringify(cart.items),
        discount: cart.discount,
        apply_vat: cart.applyVat ? 1 : 0
    }, function (res) {
        if (res.status === 'ok') {
            cart.draftId = parseInt(res.draft_id);
            cart.orderName = res.order_name || cart.orderName;

            refreshCartSelector();
            console.log('Draft saved for cart', activeCartId, 'Draft ID:', cart.draftId);
        } else {
            console.log(res.message);
        }
    }, 'json');
}


function updateCashChange(){
    let subtotal = calculateTotal();
    let discount = parseFloat($('#discountInput').val()) || 0;

    if(discount < 0) discount = 0;
    if(discount > subtotal) discount = subtotal;

    let totalAfterDiscount = subtotal - discount;
    let vat = getApplyVat() ? totalAfterDiscount * 0.16 : 0;
    let finalTotal = totalAfterDiscount + vat;

    let cash = parseFloat($('#cashReceived').val()) || 0;
    let change = cash - finalTotal;

    $('#cashChange').text(change >= 0 ? change.toFixed(2) : '0.00');
}

// Update total and cash change when discount input changes
$('#discountInput').on('input', function(){
    getActiveCart().discount = parseFloat(this.value) || 0;
    updateTotalWithDiscount();
    updateCashChange();
    saveDraftOrder();
});

// --- Update Quantity by fractional step ---
$(document).on('click', '.increase', function(){
    let id = $(this).data('id');
    getActiveCart().items[id].qty += QTY_STEP;
    renderCart();
    saveDraftOrder();
});


$(document).on('click', '.decrease', function(){
    let id = $(this).data('id');
    let items = getActiveCart().items;
    let newQty = items[id].qty - QTY_STEP;

    if(newQty > 0){
        items[id].qty = newQty;
    } else {
        delete items[id];
    }
    renderCart();
    saveDraftOrder();
});

// --- Handle manual quantity input changes ---
// Update qty and totals live but do NOT rerender cart here to avoid input losing focus
$(document).on('input', 'input[type=number][data-id]', function(){
    let id = $(this).data('id');
    let raw = $(this).val();

    // Allow empty while typing, but mark qty as 0 temporarily
    if (raw === '') {
        getActiveCart().items[id].qty = 0;
        updateTotalWithDiscount();
        updateCashChange();
        return;
    }

    let val = parseFloat(raw);

    if (isNaN(val)) return;

    getActiveCart().items[id].qty = Math.round(val * 100) / 100;


    updateTotalWithDiscount();
    updateCashChange();
});


// On input blur, re-render cart fully (to clean up removed items etc)
$(document).on('blur', 'input[type=number][data-id]', function(){
    let id = $(this).data('id');

    let items = getActiveCart().items;
    if (!items[id]) return;

    if (items[id].qty <= 0) {
        delete items[id];
    }
    renderCart();
    saveDraftOrder();


});



// --- Delete Item ---
$(document).on('click', '.delete-item', function(){
    delete getActiveCart().items[$(this).data('id')];
    renderCart();
    saveDraftOrder();
});


// --- Clear Cart ---
$('#clearCartBtn').click(function(){
    if(confirm('Clear cart?')){
        getActiveCart().items = {};
        renderCart();
        saveDraftOrder();
    }
});

// --- Payment Modal logic ---
const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'), {keyboard: false});
$('#openPaymentBtn').click(function(){
    if(Object.keys(getActiveCart().items).length === 0){

        alert('Cart is empty');
        return;
    }
    // Reset modal fields
    $('#customerName').val('');
    $('#paymentMethod').val('cash');
    $('#creditCustomerId').val('');
    $('#cashReceived').val('');
    $('#cashChange').text('0.00');
    $('#paymentMsg').text('');
    $('#cashBlock').show();
    $('#nonCashNote').addClass('d-none');
    $('#creditCustomerBlock').addClass('d-none');
    paymentModal.show();
});

// show/hide cash block based on method + show customer selector for credit
$('#paymentMethod').on('change', function(){
    let m = $(this).val();
    if(m === 'cash'){
        $('#cashBlock').show();
        $('#nonCashNote').addClass('d-none');
        $('#creditCustomerBlock').addClass('d-none');
    } else if (m === 'credit') {
        $('#cashBlock').hide();
        $('#nonCashNote').addClass('d-none');
        $('#creditCustomerBlock').removeClass('d-none');
    } else {
        $('#cashBlock').hide();
        $('#nonCashNote').removeClass('d-none');
        $('#creditCustomerBlock').addClass('d-none');
    }
});

$('#cashReceived').on('input', updateCashChange);

// Confirm & complete sale
$('#confirmPayment').click(function(){
    $('#paymentMsg').text('');
    let method = $('#paymentMethod').val();
    let customer = $('#customerName').val().trim();
    let total = calculateTotal();
    let discount = parseFloat($('#discountInput').val()) || 0;
    if(discount < 0) discount = 0;
    if(discount > total) discount = total;
    let finalTotal = total - discount;
    let cash = parseFloat($('#cashReceived').val()) || 0;
    let creditCustomerId = $('#creditCustomerId').val();

    if(method === 'cash' && cash < finalTotal){
        $('#paymentMsg').text('Cash received is less than total after discount.');
        return;
    }

    if(method === 'credit' && (!creditCustomerId || creditCustomerId === "")){
        $('#paymentMsg').text('Select a customer for credit sale.');
        return;
    }

    // Prepare payload
    let payload = {
  draft_id: getActiveCart().draftId,
  cart: JSON.stringify(getActiveCart().items),
  payment_method: method,
  customer_name: customer,
  cash_received: cash,
  credit_customer_id: creditCustomerId,
  discount: discount.toFixed(2),
  apply_vat: getApplyVat() ? 1 : 0  // Send as 1 or 0
 };

    // POST to server
   $.post('ajax/make_sale.php', payload, function(res){
    if(res.status === 'ok'){
        if(res.receipt_html){
            let w = window.open('', '_blank', 'width=400,height=600');
            w.document.write(res.receipt_html);
            w.document.close();
            w.print();
        }
        alert('Sale completed');
       // Clear ONLY active cart
getActiveCart().items = {};
getActiveCart().discount = 0;
getActiveCart().applyVat = true;

$('#discountInput').val(0);
$('#applyVat').prop('checked', true);

renderCart();
carts[activeCartId].draftId = 0;
carts[activeCartId].items = {};
carts[activeCartId].discount = 0;
carts[activeCartId].applyVat = true;

$('#discountInput').val(0);
$('#applyVat').prop('checked', true);

renderCart();
window.history.replaceState({}, document.title, "pos.php");
        paymentModal.hide();
    } else {
        $('#paymentMsg').text(res.message || 'Failed to save sale');
    }
}).fail(function(xhr, status, error){
    $('#paymentMsg').text('Server request failed: ' + error);
});

});

// --- Hide Search When Clicking Outside ---
$(document).click(function(e){
    if(!$(e.target).closest('#searchResults, #barcodeInput').length){
        $('#searchResults').hide();
    }
});
</script>

<script>
let activeCategory = '';

function loadCategories() {
    $.getJSON('ajax/load_categories.php', function (cats) {
        let html = '';
        cats.forEach((c, i) => {
            html += `<div class="category-tab ${i === 0 ? 'active' : ''}" data-cat="${c}">${c}</div>`;
            if (i === 0) activeCategory = c;
        });
        $('#categoryTabs').html(html);
        loadTiles();
    });
}

function loadTiles() {
    let search = $('#tileSearch').val() || '';

    $.getJSON('ajax/load_products.php', {
        category: activeCategory,
        search: search
    }, function (products) {

        let cartItems = getActiveCart().items;
        let html = '';

        products.forEach(p => {
            let qty = cartItems[p.id]?.qty ?? 0;
            let outOfStock = parseFloat(p.stock_qty) <= 0;

            html += `
            <div class="col-6 col-md-4 col-lg-3">
                <div class="product-tile ${outOfStock ? 'out-of-stock' : ''}"
                    data-id="${p.id}"
                    data-name="${p.name}"
                    data-price="${p.selling_price}"
                    data-stock="${p.stock_qty}">

                    ${qty > 0 ? `<div class="product-qty">${qty}</div>` : ''}

                    ${outOfStock
                        ? `<div class="product-qty bg-danger">OUT</div>`
                        : `<div class="product-qty bg-success">${p.stock_qty}</div>`
                    }

                    <div class="product-name">${p.name}</div>
                    <div class="product-price">KES ${parseFloat(p.selling_price).toFixed(2)}</div>
                </div>
            </div>`;
        });

        $('#productTiles').html(html);
    });
}

// Category click
$(document).on('click', '.category-tab', function () {
    $('.category-tab').removeClass('active');
    $(this).addClass('active');
    activeCategory = $(this).data('cat');
    loadTiles();
});

// Tile click → ADD TO CART
$(document).on('click', '.product-tile', function () {
    let product = {
        id: $(this).data('id'),
        name: $(this).data('name'),
        selling_price: parseFloat($(this).data('price'))
    };

    addToCart(product);
    loadTiles(); // refresh quantity badge
});

// Search
$('#tileSearch').on('input', loadTiles);

// Hook into cart updates
const originalRenderCart = renderCart;
renderCart = function () {
    originalRenderCart();
    loadTiles();
};

// Init
loadCategories();
</script>

<script>
function showView(viewId) {
    $('#cartView, #productsView').hide();
    $('#' + viewId).show();

    $('.nav-btn').removeClass('active');
    $(`.nav-btn[data-view="${viewId}"]`).addClass('active');
}

// Bottom nav click
$(document).on('click', '.nav-btn[data-view]', function () {
    showView($(this).data('view'));
});

// Pay shortcut
$('#mobilePayBtn').click(function () {
    showView('cartView');
    $('#openPaymentBtn').trigger('click');
});

// Default mobile view
if (window.innerWidth <= 768) {
    showView('productsView');
}
</script>

<script>
    // Load draft order when coming from Pending Orders page
$(function () {
    const params = new URLSearchParams(window.location.search);
    const draftId = params.get('draft_id');

    if (draftId) {
        $.getJSON('ajax/get_draft_order.php', { draft_id: draftId }, function(res){
            if(res.status !== 'ok'){
                alert(res.message || 'Failed to load draft order');
                return;
            }

            let draft = res.draft;

            currentDraftId = parseInt(draft.id);

            // Use the current active cart
            getActiveCart().items = draft.cart_items || {};
            getActiveCart().discount = parseFloat(draft.discount) || 0;
            getActiveCart().applyVat = parseInt(draft.apply_vat) === 1;

            $('#discountInput').val(getActiveCart().discount);
            $('#applyVat').prop('checked', getActiveCart().applyVat);

            renderCart();
            updateTotalWithDiscount();
            updateCashChange();
        });
    }
});
</script>

</body>
</html>

<?php
require("foot.php");
?>
