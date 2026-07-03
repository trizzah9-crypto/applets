<?php
// sales_report.php
require("head.php");
require("db.php");

// Ensure business selected
if (!isset($_SESSION['business_id'])) {
    header("Location: business_select.php");
    exit;
}

$business_id = intval($_SESSION['business_id']);
$user_name = $_SESSION['user_name'] ?? ($_SESSION['admin'] ?? 'Cashier');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Report</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
        :root {
            --brand-950: #033542;
            --brand-900: #054960;
            --brand-800: #0a5d78;
            --brand-700: #0f7c9d;
            --accent-500: #ff7900;
            --accent-400: #ff9d3f;

            --bg-50: #f4f8fb;
            --bg-100: #edf4f8;
            --text-900: #0f172a;
            --text-700: #334155;
            --text-500: #64748b;
            --white: #ffffff;

            --radius-xl: 18px;
            --radius-2xl: 24px;
            --radius-3xl: 28px;

            --shadow-soft: 0 10px 30px rgba(5, 73, 96, 0.08);
            --shadow-medium: 0 18px 50px rgba(5, 73, 96, 0.14);
            --shadow-strong: 0 24px 70px rgba(5, 73, 96, 0.18);

            --border-soft: 1px solid rgba(255, 255, 255, 0.45);
            --glass-bg: rgba(255, 255, 255, 0.72);
            --glass-blur: blur(18px);
            --transition-fast: 180ms ease;
            --transition-med: 260ms ease;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(15, 124, 157, 0.08), transparent 28%),
                radial-gradient(circle at top right, rgba(255, 157, 63, 0.08), transparent 25%),
                linear-gradient(180deg, #f8fbfd 0%, #f3f8fb 100%);
            color: var(--text-900);
        }

        body::-webkit-scrollbar,
        .table-responsive::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        body::-webkit-scrollbar-thumb,
        .table-responsive::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--brand-700), var(--brand-900));
            border-radius: 999px;
        }

        body::-webkit-scrollbar-track,
        .table-responsive::-webkit-scrollbar-track {
            background: rgba(5, 73, 96, 0.08);
            border-radius: 999px;
        }

        .sales-page-shell {
            max-width: 1480px;
            margin: 0 auto;
            padding: 22px 18px 36px;
        }

        .sales-hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-3xl);
            padding: 28px 28px 24px;
            background: linear-gradient(135deg, var(--brand-950) 0%, var(--brand-800) 45%, var(--brand-700) 100%);
            color: #fff;
            box-shadow: var(--shadow-strong);
            border: 1px solid rgba(255, 255, 255, 0.12);
            margin-bottom: 22px;
        }

        .sales-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 15% 20%, rgba(255, 157, 63, 0.22), transparent 24%),
                radial-gradient(circle at 85% 20%, rgba(255, 255, 255, 0.12), transparent 18%),
                radial-gradient(circle at 50% 100%, rgba(255, 121, 0, 0.12), transparent 30%);
            pointer-events: none;
        }

        .sales-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .sales-hero h2 {
            margin: 0;
            font-size: clamp(1.5rem, 2.2vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .sales-hero p {
            margin: 6px 0 0;
            color: rgba(255, 255, 255, 0.84);
        }

        .sales-page-shell .card {
            border: var(--border-soft);
            border-radius: var(--radius-3xl);
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            transition: transform var(--transition-med), box-shadow var(--transition-med);
        }

        .sales-page-shell .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .stat-card {
            border-radius: 22px;
            padding: 18px;
            background: linear-gradient(135deg, rgba(5, 73, 96, 0.96), rgba(15, 124, 157, 0.92));
            color: #fff;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(255, 255, 255, 0.12);
            min-height: 110px;
        }

        .stat-card.accent {
            background: linear-gradient(135deg, rgba(255, 121, 0, 0.96), rgba(255, 157, 63, 0.92));
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.85;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .stat-value {
            font-size: clamp(1.3rem, 2vw, 2rem);
            font-weight: 800;
            line-height: 1.1;
        }

        .stat-subtext {
            margin-top: 8px;
            font-size: 0.9rem;
            opacity: 0.84;
        }

        .filter-panel {
            padding: 18px;
            border-radius: var(--radius-2xl);
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(5, 73, 96, 0.08);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .filter-panel .form-label {
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--text-700);
            margin-bottom: 6px;
        }

        .filter-panel .form-control,
        .filter-panel .form-select {
            min-height: 46px;
            border-radius: 14px;
            border: 1px solid rgba(5, 73, 96, 0.14);
            background: rgba(255, 255, 255, 0.92);
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
        }

        .filter-panel .form-control:focus,
        .filter-panel .form-select:focus {
            border-color: rgba(15, 124, 157, 0.55);
            box-shadow: 0 0 0 0.22rem rgba(15, 124, 157, 0.16);
        }

        .btn {
            border-radius: 14px;
            font-weight: 700;
            transition: transform var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast), border-color var(--transition-fast);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand-800), var(--brand-700));
            border: 0;
            box-shadow: 0 10px 24px rgba(15, 124, 157, 0.22);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: linear-gradient(135deg, var(--brand-900), var(--brand-800));
        }

        .btn-success {
            background: linear-gradient(135deg, #15803d, #22c55e);
            border: 0;
        }

        .btn-outline-secondary,
        .btn-outline-primary {
            border-width: 1px;
        }

        .btn-action {
            padding: 10px 16px;
        }

        .table-wrap {
            border-radius: var(--radius-2xl);
            overflow: hidden;
            border: 1px solid rgba(5, 73, 96, 0.08);
            background: rgba(255, 255, 255, 0.88);
        }

        .table-responsive {
            max-height: 560px;
            overflow: auto;
        }

        .table {
            margin: 0;
            color: var(--text-900);
        }

        .table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: linear-gradient(180deg, rgba(5, 73, 96, 0.98), rgba(10, 93, 120, 0.98));
            color: #fff;
            border-bottom: 0;
            font-size: 0.85rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 16px 14px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px;
            border-color: rgba(5, 73, 96, 0.08);
            vertical-align: middle;
            white-space: nowrap;
        }

        .table-hover tbody tr {
            transition: background-color var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
        }

        .table-hover tbody tr:hover {
            background: rgba(15, 124, 157, 0.06);
        }

        .table tbody tr:nth-child(even) {
            background: rgba(5, 73, 96, 0.02);
        }

        .table .text-end {
            font-variant-numeric: tabular-nums;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(15, 124, 157, 0.1);
            color: var(--brand-900);
            font-size: 0.85rem;
            font-weight: 700;
        }

        .pill.warning {
            background: rgba(255, 157, 63, 0.16);
            color: #b45309;
        }

        .small-muted {
            font-size: 0.92rem;
            color: var(--text-500);
        }

        .totals {
            font-weight: 800;
            color: var(--brand-900);
            font-size: 1.02rem;
        }

        .loading-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.62);
            backdrop-filter: blur(6px);
            border-radius: var(--radius-2xl);
            z-index: 5;
        }

        .floating-actions {
            position: fixed;
            right: 20px;
            bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 50;
        }

        .fab {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            box-shadow: var(--shadow-medium);
            border: 0;
        }

        .payment-pill,
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            text-transform: capitalize;
        }

        .payment-pill.primary {
            background: rgba(15, 124, 157, .12);
            color: #0f7c9d;
        }

        .payment-pill.success {
            background: rgba(16, 185, 129, .12);
            color: #10b981;
        }

        .payment-pill.warning {
            background: rgba(255, 121, 0, .12);
            color: #ff7900;
        }

        .payment-pill.info {
            background: rgba(59, 130, 246, .12);
            color: #2563eb;
        }

        .payment-pill.secondary {
            background: rgba(100, 116, 139, .12);
            color: #64748b;
        }

        .status-pill.success {
            background: rgba(16, 185, 129, .12);
            color: #10b981;
        }

        .status-pill.warning {
            background: rgba(255, 121, 0, .12);
            color: #ff7900;
        }

        .status-pill.danger {
            background: rgba(239, 68, 68, .12);
            color: #ef4444;
        }

        .status-pill.secondary {
            background: rgba(100, 116, 139, .12);
            color: #64748b;
        }

        .btn-view {
            border: none;
            color: white;
            background: linear-gradient(135deg, #0a5d78, #0f7c9d);
            border-radius: 12px;
        }

        .btn-download {
            border: none;
            color: white;
            background: linear-gradient(135deg, #ff7900, #ff9d3f);
            border-radius: 12px;
        }

        .print-only {
            display: none;
        }

        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sales-page-shell {
                padding: 14px 12px 28px;
            }

            .sales-hero {
                padding: 20px 18px;
                border-radius: 22px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .filter-panel {
                padding: 14px;
            }

            .btn-action {
                width: 100%;
                margin-bottom: 8px;
            }

            .floating-actions {
                right: 12px;
                bottom: 12px;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 10px;
            }
        }

        @media print {
            body {
                background: #fff !important;
                color: #000 !important;
            }

            .sales-hero,
            .filter-panel,
            .btn,
            .floating-actions,
            #loading,
            .no-print,
            .modal,
            .modal-backdrop {
                display: none !important;
            }

            .sales-page-shell {
                max-width: 100%;
                padding: 0;
            }

            .card,
            .table-wrap {
                box-shadow: none !important;
                border: 0 !important;
                background: #fff !important;
            }

            .print-only {
                display: block !important;
                margin-bottom: 18px;
            }

            .table thead th {
                background: #0a5d78 !important;
                color: #fff !important;
            }

            .table-responsive {
                max-height: none !important;
                overflow: visible !important;
            }
        }
</style>
</head>
<body>
<div class="sales-page-shell">

    <div class="sales-hero">
        <div class="sales-hero-content">
            <div>
                <h2>Sales Report</h2>
                <p>Premium enterprise sales reporting with filters, receipt actions, and live totals.</p>
            </div>
            <div class="text-end">
                
                <div style="margin-top:8px; opacity:.9;">Signed in as <strong><?= htmlspecialchars($user_name) ?></strong></div>
            </div>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Total Sales</div>
            <div class="stat-value" id="totalCount">0</div>
            <div class="stat-subtext">Sales records loaded</div>
        </div>

        <div class="stat-card accent">
            <div class="stat-label">Revenue</div>
            <div class="stat-value" id="totalAmount">KES 0.00</div>
            <div class="stat-subtext">Filtered sales value</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">VAT</div>
            <div class="stat-value" id="vatCollected">KES 0.00</div>
            <div class="stat-subtext">Collected tax amount</div>
        </div>

        <div class="stat-card accent">
            <div class="stat-label">Credit Balance</div>
            <div class="stat-value" id="creditAmount">KES 0.00</div>
            <div class="stat-subtext">Outstanding balances</div>
        </div>
    </div>

    <div class="card p-3 p-md-4">
        <div class="filter-panel mb-3">
            <div class="row g-2 align-items-end">
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
                    <label class="form-label small-muted">Status</label>
                    <select id="status" class="form-select">
                        <option value="">All</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="refunded">Refunded</option>
                        <option value="void">Void</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small-muted">Search (invoice / sale / customer)</label>
                    <input type="text" id="search" class="form-control" placeholder="INV-0001 or customer name">
                </div>

                <div class="col-md-3">
                    <label class="form-label small-muted">Cashier</label>
                    <input type="text" id="cashier" class="form-control" placeholder="Cashier name">
                </div>

                <div class="col-md-6 mt-2">
                    <button id="applyFilters" class="btn btn-primary btn-action me-2">Apply</button>
                    <button id="resetFilters" class="btn btn-outline-secondary btn-action me-2">Reset</button>
                    <button id="downloadCsv" class="btn btn-success btn-action">Download CSV</button>
                </div>

                <div class="col-md-6 text-md-end mt-2">
                    <div class="small-muted">
                        Total: <span id="totalCountInline">0</span> sales •
                        <span class="totals" id="totalAmountInline">KES 0.00</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-wrap position-relative">
            <div id="loading" class="loading-overlay" style="display:none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle" id="salesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Cashier</th>
                            <th>Customer</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">VAT</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Profit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salesBody">
                        <tr>
                            <td colspan="15" class="text-center text-muted py-4">Loading sales...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="floating-actions no-print">
    <button class="btn btn-primary fab" id="scrollTopBtn" type="button" title="Back to top">↑</button>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function () {
    const $loading = $("#loading");
    const $salesBody = $("#salesBody");

    const $totalCount = $("#totalCount");
    const $totalCountInline = $("#totalCountInline");
    const $totalAmount = $("#totalAmount");
    const $totalAmountInline = $("#totalAmountInline");
    const $vatCollected = $("#vatCollected");
    const $creditAmount = $("#creditAmount");

    function showLoading(show = true) {
        $loading.toggle(show);
    }

    function escapeHtml(s) {
        return String(s || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function money(v) {
        const n = Number(v || 0);
        return "KES " + n.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function paymentBadge(payment) {
        const value = String(payment || "").toLowerCase();
        const map = {
            cash: "success",
            mpesa: "primary",
            card: "info",
            credit: "warning"
        };
        const klass = map[value] || "secondary";
        return `<span class="payment-pill ${klass}">${escapeHtml(value || "n/a")}</span>`;
    }

    function statusBadge(status) {
        const value = String(status || "completed").toLowerCase();
        const map = {
            completed: "success",
            pending: "warning",
            refunded: "danger",
            void: "secondary"
        };
        const klass = map[value] || "secondary";
        return `<span class="status-pill ${klass}">${escapeHtml(value)}</span>`;
    }

    function getFilters() {
        return {
            from: $("#fromDate").val(),
            to: $("#toDate").val(),
            payment: $("#paymentMethod").val(),
            status: $("#status").val(),
            cashier: $("#cashier").val().trim(),
            q: $("#search").val().trim()
        };
    }

    function renderRow(i, row) {
        const invoice = row.invoice_number || row.sale_number || "";
        const total = row.total_including_vat ?? row.total_amount ?? 0;
        const payment = row.payment_type || row.payment_method || "";
        const status = row.status || "completed";

        return `
            <tr>
                <td>${i}</td>

                <td>
                    <div class="fw-bold">${escapeHtml(invoice)}</div>
                    <small class="text-muted">${escapeHtml(row.sale_number || "")}</small>
                </td>

                <td>${escapeHtml(row.created_at || "")}</td>

                <td>${escapeHtml(row.user_name || "")}</td>

                <td>${escapeHtml(row.customer_name || "Walk-in Customer")}</td>

                <td>${paymentBadge(payment)}</td>

                <td>${statusBadge(status)}</td>

                <td class="text-end">${money(row.total_before_vat)}</td>

                <td class="text-end text-warning fw-semibold">${money(row.discount)}</td>

                <td class="text-end">${money(row.vat_amount)}</td>

                <td class="text-end fw-bold text-primary">${money(total)}</td>

                <td class="text-end text-success fw-bold">${money(row.paid_amount)}</td>

                <td class="text-end ${Number(row.balance_due || 0) > 0 ? 'text-danger fw-bold' : ''}">
                    ${money(row.balance_due)}
                </td>

                <td class="text-end text-success fw-bold">${money(row.profit)}</td>

                <td class="text-nowrap">
                    <button class="btn btn-sm btn-view viewReceipt" data-id="${row.id}">
                        Receipt
                    </button>
                    <button class="btn btn-sm btn-download downloadReceipt" data-id="${row.id}">
                        PDF
                    </button>
                </td>
            </tr>
        `;
    }

    function setEmpty(message) {
        $salesBody.html(`
            <tr>
                <td colspan="15" class="text-center text-muted py-4">${escapeHtml(message)}</td>
            </tr>
        `);
    }

    function applyTotals(res, rows) {
        let count = 0;
        let amount = 0;
        let vat = 0;
        let credit = 0;

        if (res && res.summary) {
            count = Number(res.summary.count || 0);
            amount = Number(res.summary.total_amount || 0);
            vat = Number(res.summary.vat_amount || 0);
            credit = Number(res.summary.balance_due || 0);
        } else {
            count = rows.length;
            rows.forEach(function (r) {
                amount += Number(r.total_including_vat || r.total_amount || 0);
                vat += Number(r.vat_amount || 0);
                credit += Number(r.balance_due || 0);
            });
        }

        $totalCount.text(count);
        $totalCountInline.text(count);

        $totalAmount.text(money(amount));
        $totalAmountInline.text(money(amount));

        $vatCollected.text(money(vat));
        $creditAmount.text(money(credit));
    }

    function loadSales() {
        const filters = getFilters();
        showLoading(true);

        $.ajax({
            url: "ajax/get_sales.php",
            type: "GET",
            data: filters,
            dataType: "json"
        }).done(function (res) {
            if (!res || res.status !== "ok") {
                setEmpty((res && res.message) ? res.message : "Failed to load sales");
                $totalCount.text("0");
                $totalCountInline.text("0");
                $totalAmount.text("KES 0.00");
                $totalAmountInline.text("KES 0.00");
                $vatCollected.text("KES 0.00");
                $creditAmount.text("KES 0.00");
                return;
            }

            const rows = Array.isArray(res.data) ? res.data : [];

            if (rows.length === 0) {
                setEmpty("No sales found");
            } else {
                let html = "";
                for (let i = 0; i < rows.length; i++) {
                    html += renderRow(i + 1, rows[i]);
                }
                $salesBody.html(html);
            }

            applyTotals(res, rows);
        }).fail(function () {
            setEmpty("Server error");
            $totalCount.text("0");
            $totalCountInline.text("0");
            $totalAmount.text("KES 0.00");
            $totalAmountInline.text("KES 0.00");
            $vatCollected.text("KES 0.00");
            $creditAmount.text("KES 0.00");
        }).always(function () {
            showLoading(false);
        });
    }

    $("#applyFilters").on("click", loadSales);

    $("#resetFilters").on("click", function () {
        $("#fromDate").val("");
        $("#toDate").val("");
        $("#paymentMethod").val("");
        $("#status").val("");
        $("#cashier").val("");
        $("#search").val("");
        loadSales();
    });

    $("#downloadCsv").on("click", function () {
        const f = getFilters();
        const params = $.param(f);
        window.location = "ajax/export_sales_csv.php?" + params;
    });

    $(document).on("click", ".viewReceipt", function () {
        const id = $(this).data("id");

        $.get("ajax/get_receipt.php", { id: id }, function (res) {
            if (res && res.status === "ok") {
                const w = window.open("", "_blank", "width=420,height=650");
                w.document.write(res.receipt_html);
                w.document.close();
                w.focus();
                w.print();
            } else {
                alert((res && res.message) ? res.message : "Failed to load receipt");
            }
        }, "json").fail(function () {
            alert("Request failed");
        });
    });

    $(document).on("click", ".downloadReceipt", function () {
        const id = $(this).data("id");

        $.get("ajax/get_receipt.php", { id: id }, function (res) {
            if (res && res.status === "ok") {
                const w = window.open("", "_blank", "width=420,height=650");
                w.document.write(res.receipt_html);
                w.document.close();
                w.focus();
            } else {
                alert((res && res.message) ? res.message : "Failed to load receipt");
            }
        }, "json").fail(function () {
            alert("Request failed");
        });
    });

    $("#scrollTopBtn").on("click", function () {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });

    $("#search").on("keypress", function (e) {
        if (e.which === 13) {
            loadSales();
        }
    });

    loadSales();
})();
</script>

<?php require("foot.php"); ?>
</body>
</html>