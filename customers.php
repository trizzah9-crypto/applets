<?php
require("head.php");
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['business_id'])) {
    die("No business selected");
}

$business_id = (int)$_SESSION['business_id'];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function scalar(PDO $conn, string $sql, array $params = []) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function badgeCustomerType($type) {
    $type = strtolower(trim((string)$type));
    return match ($type) {
        'retail' => 'type-retail',
        'wholesale' => 'type-wholesale',
        'vip' => 'type-vip',
        'walk-in', 'walkin', 'walk in' => 'type-walkin',
        default => 'type-walkin',
    };
}

function badgeStatus($status) {
    $status = strtolower(trim((string)$status));
    return match ($status) {
        'active' => 'status-active',
        'inactive' => 'status-inactive',
        'blocked' => 'status-blocked',
        default => 'status-inactive',
    };
}

function normalizePhoneDigits($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '254' . substr($digits, 1);
    }
    if (str_starts_with($digits, '7') && strlen($digits) === 9) {
        return '254' . $digits;
    }
    if (str_starts_with($digits, '254') && strlen($digits) === 12) {
        return $digits;
    }
    return $digits;
}

function phoneDisplay($phone) {
    $digits = normalizePhoneDigits($phone);
    return $digits ?: trim((string)$phone);
}

function initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $parts = array_values(array_filter($parts));
    $first = $parts[0][0] ?? 'C';
    $second = $parts[1][0] ?? ($parts[0][1] ?? '');
    return strtoupper($first . $second);
}

$businessParam = [':business_id' => $business_id];

$totalCustomers = (int)scalar($conn, "SELECT COUNT(*) FROM customers WHERE business_id = :business_id", $businessParam);
$activeCustomers = (int)scalar($conn, "SELECT COUNT(*) FROM customers WHERE business_id = :business_id AND LOWER(COALESCE(status,'Active')) = 'active'", $businessParam);
$creditCustomers = (int)scalar($conn, "
    SELECT COUNT(*)
    FROM customers c
    WHERE c.business_id = :business_id
      AND COALESCE((SELECT SUM(amount) FROM customer_account_transactions t WHERE t.customer_id = c.id),0) > 0
", $businessParam);
$vipCustomers = (int)scalar($conn, "SELECT COUNT(*) FROM customers WHERE business_id = :business_id AND LOWER(COALESCE(customer_type,'')) = 'vip'", $businessParam);
$newCustomersThisMonth = (int)scalar($conn, "
    SELECT COUNT(*)
    FROM customers
    WHERE business_id = :business_id
      AND date(created_at) >= date('now','start of month')
", $businessParam);
$totalLoyaltyPoints = (int)scalar($conn, "SELECT COALESCE(SUM(COALESCE(loyalty_points,0)),0) FROM customers WHERE business_id = :business_id", $businessParam);
$outstandingCreditAmount = (float)scalar($conn, "
    SELECT COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END),0)
    FROM (
        SELECT c.id, COALESCE((SELECT SUM(amount) FROM customer_account_transactions t WHERE t.customer_id = c.id),0) AS balance
        FROM customers c
        WHERE c.business_id = :business_id
    )
", $businessParam);
$largestOutstandingBalance = (float)scalar($conn, "
    SELECT COALESCE(MAX(balance),0)
    FROM (
        SELECT c.id, COALESCE((SELECT SUM(amount) FROM customer_account_transactions t WHERE t.customer_id = c.id),0) AS balance
        FROM customers c
        WHERE c.business_id = :business_id
    )
", $businessParam);
$customersWithBalances = (int)scalar($conn, "
    SELECT COUNT(*)
    FROM (
        SELECT c.id, COALESCE((SELECT SUM(amount) FROM customer_account_transactions t WHERE t.customer_id = c.id),0) AS balance
        FROM customers c
        WHERE c.business_id = :business_id
    )
    WHERE balance > 0
", $businessParam);
$averageCustomerValue = (float)scalar($conn, "
    SELECT COALESCE(AVG(customer_value),0)
    FROM (
        SELECT c.id,
               COALESCE((
                    SELECT SUM(COALESCE(NULLIF(s.total_including_vat,0), s.total_amount, 0))
                    FROM sales s
                    WHERE s.business_id = c.business_id
                      AND TRIM(LOWER(COALESCE(s.customer_name,''))) = TRIM(LOWER(c.name))
               ),0) AS customer_value
        FROM customers c
        WHERE c.business_id = :business_id
    )
", $businessParam);

$topSpendingStmt = $conn->prepare("
    SELECT c.id, c.name, c.customer_type,
           COALESCE((
                SELECT SUM(COALESCE(NULLIF(s.total_including_vat,0), s.total_amount, 0))
                FROM sales s
                WHERE s.business_id = c.business_id
                  AND TRIM(LOWER(COALESCE(s.customer_name,''))) = TRIM(LOWER(c.name))
           ),0) AS total_spent
    FROM customers c
    WHERE c.business_id = :business_id
    ORDER BY total_spent DESC, c.id DESC
    LIMIT 5
");
$topSpendingStmt->execute($businessParam);
$topSpending = $topSpendingStmt->fetchAll(PDO::FETCH_ASSOC);

$topCreditStmt = $conn->prepare("
    SELECT c.id, c.name, c.customer_type,
           COALESCE((SELECT SUM(amount) FROM customer_account_transactions t WHERE t.customer_id = c.id),0) AS balance
    FROM customers c
    WHERE c.business_id = :business_id
    ORDER BY balance DESC, c.id DESC
    LIMIT 5
");
$topCreditStmt->execute($businessParam);
$topCredit = $topCreditStmt->fetchAll(PDO::FETCH_ASSOC);

$loyalStmt = $conn->prepare("
    SELECT id, name, customer_type, loyalty_points
    FROM customers
    WHERE business_id = :business_id
    ORDER BY COALESCE(loyalty_points,0) DESC, id DESC
    LIMIT 5
");
$loyalStmt->execute($businessParam);
$mostLoyal = $loyalStmt->fetchAll(PDO::FETCH_ASSOC);

$recentStmt = $conn->prepare("
    SELECT id, name, phone, customer_type, status, created_at
    FROM customers
    WHERE business_id = :business_id
    ORDER BY datetime(created_at) DESC, id DESC
    LIMIT 5
");
$recentStmt->execute($businessParam);
$recentRegs = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root{
            --brand:#054960;
            --brand2:#0a5d78;
            --accent:#0f7c9d;
            --orange:#ff7900;
            --orange2:#ff9d3f;
            --green:#0e9b59;
            --purple:#6f42c1;
            --blue:#0d6efd;
            --cyan:#14a6a6;
            --card-border:rgba(255,255,255,.18);
        }
        body{
            background:
                radial-gradient(circle at top left, rgba(15,124,157,.13), transparent 28%),
                radial-gradient(circle at top right, rgba(255,121,0,.11), transparent 26%),
                linear-gradient(180deg, #eef6f8 0%, #f6f8fb 100%);
        }
        .crm-shell{ padding: 18px 0 40px; }
        .crm-hero{
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 28px;
            background: linear-gradient(135deg, #054960 0%, #0a5d78 48%, #0f7c9d 100%);
            color: #fff;
            box-shadow: 0 24px 70px rgba(5,73,96,.22);
            border: 1px solid rgba(255,255,255,.12);
        }
        .crm-hero::after{
            content:"";
            position:absolute;
            right:-80px; top:-80px;
            width:240px; height:240px;
            background: radial-gradient(circle, rgba(255,255,255,.22), transparent 60%);
            filter: blur(4px);
        }
        .crm-badge{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.22);
            backdrop-filter: blur(16px);
            font-weight: 700;
            letter-spacing: .02em;
            font-size: .82rem;
        }
        .crm-title{
            font-size: clamp(1.65rem, 4vw, 3rem);
            line-height: 1.06;
            margin: 14px 0 12px;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .crm-subtitle{
            max-width: 840px;
            font-size: 1.02rem;
            opacity: .92;
            margin-bottom: 18px;
        }
        .hero-pills{ display:flex; flex-wrap:wrap; gap:12px; }
        .hero-pill{
            display:flex; align-items:center; gap:10px;
            padding: 12px 16px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.22);
            backdrop-filter: blur(14px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.16);
            font-weight: 600;
        }
        .hero-pill span{
            display:inline-flex;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            align-items:center;
            justify-content:center;
            border-radius: 999px;
            background: rgba(255,255,255,.2);
            font-weight: 800;
        }
        .growth-widget{
            display:inline-flex;
            flex-direction:column;
            align-items:flex-end;
            justify-content:center;
            gap:8px;
            min-height: 150px;
            padding: 22px;
            border-radius: 26px;
            background: rgba(255,255,255,.13);
            border: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(18px);
            box-shadow: 0 20px 50px rgba(0,0,0,.12);
            font-size: 2.2rem;
            font-weight: 800;
        }
        .growth-widget small{
            font-size: .9rem;
            opacity: .9;
            font-weight: 600;
        }
        .glass-card{
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(255,255,255,.6);
            backdrop-filter: blur(18px);
            border-radius: 26px;
            box-shadow: 0 18px 50px rgba(21,44,56,.08);
        }
        .section-card{
            border-radius: 26px;
            border: 1px solid rgba(255,255,255,.55);
            background: rgba(255,255,255,.84);
            backdrop-filter: blur(18px);
            box-shadow: 0 18px 50px rgba(21,44,56,.08);
        }
        .section-title{
            font-weight: 800;
            letter-spacing: -.02em;
            color: #103240;
        }
        .crm-input,.form-control,.form-select{
            border-radius: 18px !important;
            border: 1px solid rgba(5,73,96,.12) !important;
            padding: .8rem 1rem !important;
            box-shadow: none !important;
        }
        .crm-btn{
            border-radius: 18px;
            background: linear-gradient(135deg, var(--brand), var(--brand2));
            color:#fff;
            border: none;
            font-weight: 700;
            box-shadow: 0 14px 30px rgba(5,73,96,.18);
        }
        .crm-btn:hover{ color:#fff; filter: brightness(1.03); }
        .crm-btn-outline{
            border-radius: 18px;
            border: 1px solid rgba(5,73,96,.16);
            background: rgba(255,255,255,.76);
            font-weight: 700;
        }
        .crm-stat-card{
            position:relative;
            overflow:hidden;
            border-radius: 26px;
            padding: 22px;
            min-height: 150px;
            color:#fff;
            box-shadow: 0 18px 44px rgba(5,73,96,.12);
            border: 1px solid rgba(255,255,255,.12);
        }
        .crm-stat-card::after{
            content:"";
            position:absolute;
            right:-22px;
            top:-12px;
            width:120px;
            height:120px;
            border-radius:50%;
            background: rgba(255,255,255,.12);
            filter: blur(2px);
        }
        .stat-icon{
            width: 56px;
            height: 56px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius: 18px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.2);
            font-size: 1.45rem;
            margin-bottom: 18px;
        }
        .crm-stat-card h2{
            font-size: clamp(1.35rem, 2.6vw, 2.15rem);
            margin: 0;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .crm-stat-card p{
            margin: 6px 0 0;
            opacity: .92;
            font-weight: 600;
        }
        .stat-teal{ background: linear-gradient(135deg, #054960, #0a5d78 60%, #0f7c9d); }
        .stat-orange{ background: linear-gradient(135deg, #ff7900, #ff9d3f); }
        .stat-purple{ background: linear-gradient(135deg, #5b3aa6, #6f42c1); }
        .stat-blue{ background: linear-gradient(135deg, #0d6efd, #14a6a6); }
        .crm-table-wrap{
            overflow-x:auto;
            border-radius: 24px;
        }
        .crm-table{
            min-width: 1320px;
            margin: 0;
        }
        .crm-table thead th{
            background: linear-gradient(135deg, #054960, #0a5d78);
            color:#fff;
            border: none;
            padding: 16px 14px;
            white-space: nowrap;
            font-size: .88rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .crm-table tbody td{
            vertical-align: middle;
            padding: 16px 14px;
            border-color: rgba(5,73,96,.08);
            white-space: nowrap;
        }
        .crm-table tbody tr{
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
            cursor: pointer;
        }
        .crm-table tbody tr:hover{
            background: rgba(15,124,157,.04);
            transform: translateY(-1px);
        }
        .customer-avatar{
            width: 52px;
            height: 52px;
            border-radius: 18px;
            background: linear-gradient(135deg, #054960, #0f7c9d);
            color: #fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(5,73,96,.22);
            overflow:hidden;
            flex: none;
        }
        .customer-avatar img{
            width:100%;
            height:100%;
            object-fit: cover;
        }
        .customer-name{
            font-weight: 800;
            color:#103240;
            letter-spacing:-.02em;
        }
        .muted-small{
            color:#6e7d86;
            font-size:.82rem;
        }
        .type-retail{ background:#0d6efd !important; }
        .type-wholesale{ background:#6f42c1 !important; }
        .type-vip{ background:#ff7900 !important; }
        .type-walkin{ background:#0f7c9d !important; }
        .status-active{ background:#0e9b59 !important; }
        .status-inactive{ background:#8b95a1 !important; }
        .status-blocked{ background:#dc3545 !important; }
        .soft-pill{
            display:inline-flex;
            align-items:center;
            gap:8px;
            border-radius:999px;
            padding: .45rem .8rem;
            font-weight: 700;
            font-size: .82rem;
        }
        .btn-action-mini{
            border-radius: 12px;
            padding: .42rem .6rem;
        }
        .dropdown-menu{
            border: none;
            border-radius: 18px;
            box-shadow: 0 20px 45px rgba(21,44,56,.16);
            padding: .55rem;
        }
        .dropdown-item{
            border-radius: 12px;
            padding: .7rem .8rem;
            font-weight: 600;
        }
        .dropdown-item i{ margin-right: .55rem; }
        .modal-content.crm-modal{
            border: none;
            border-radius: 28px;
            overflow:hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,.22);
        }
        .modal-header.crm-modal-header{
            background: linear-gradient(135deg, #054960, #0a5d78 60%, #0f7c9d);
            color:#fff;
            border: none;
        }
        .modal-body.crm-modal-body{
            background: linear-gradient(180deg, #fbfdfe 0%, #f4f8fb 100%);
        }
        .summary-card{
            position:relative;
            overflow:hidden;
            border-radius: 22px;
            padding: 18px;
            background: rgba(255,255,255,.9);
            border: 1px solid rgba(5,73,96,.08);
            box-shadow: 0 14px 30px rgba(5,73,96,.06);
            min-height: 120px;
        }
        .summary-card .icon-bubble{
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
            margin-bottom: 16px;
            font-size: 1.2rem;
        }
        .sum-teal{ background: linear-gradient(135deg, #054960, #0f7c9d); }
        .sum-orange{ background: linear-gradient(135deg, #ff7900, #ff9d3f); }
        .sum-purple{ background: linear-gradient(135deg, #6f42c1, #8f5cf7); }
        .sum-green{ background: linear-gradient(135deg, #0e9b59, #28b56a); }
        .summary-card h5{
            margin:0;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }
        .summary-card small{
            color:#6e7d86;
            font-weight: 600;
        }
        .detail-section{
            border-radius: 24px;
            background:#fff;
            border: 1px solid rgba(5,73,96,.08);
            box-shadow: 0 14px 30px rgba(5,73,96,.05);
        }
        .detail-section .card-header{
            background: transparent;
            border-bottom: 1px solid rgba(5,73,96,.08);
            font-weight: 800;
            color:#103240;
        }
        .fab{
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 1040;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display:flex;
            align-items:center;
            justify-content:center;
            background: linear-gradient(135deg, #054960, #0f7c9d);
            color:#fff;
            box-shadow: 0 18px 40px rgba(5,73,96,.28);
            border: none;
        }
        .fab i{ font-size: 1.3rem; }
        .mobile-compact{ display: block; }
        @media (max-width: 991.98px){
            .crm-hero{ padding: 22px; }
            .growth-widget{
                min-height: 96px;
                align-items:flex-start;
                text-align:left;
                margin-top: 18px;
            }
            .crm-table{ min-width: 1140px; }
        }
        @media (max-width: 767.98px){
            .hero-pills{ display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); }
            .growth-widget{ width:100%; }
            .crm-stat-card{ min-height: 130px; padding: 18px; }
            .summary-card{ min-height: 108px; }
            .mobile-compact .btn{ padding: .5rem .75rem; }
        }
    </style>
</head>
<body>
<div class="container-fluid crm-shell px-3 px-lg-4">

    <div class="crm-hero mb-4">
        <div class="row align-items-center g-3 position-relative" style="z-index:1;">
            <div class="col-lg-8">
                <div class="crm-badge mb-3">
                    <i class="bi bi-people-fill"></i>
                    CRM Intelligence Center
                </div>
                <h1 class="crm-title">Customer Relationship Management</h1>
                <p class="crm-subtitle">
                    Manage customers, monitor credit exposure, track loyalty and build stronger business relationships.
                </p>
                <div class="hero-pills">
                    <div class="hero-pill"><span><?= number_format($totalCustomers) ?></span> Total Customers</div>
                    <div class="hero-pill"><span><?= number_format($activeCustomers) ?></span> Active Customers</div>
                    <div class="hero-pill"><span><?= number_format($creditCustomers) ?></span> Customers on Credit</div>
                    <div class="hero-pill"><span><?= number_format($vipCustomers) ?></span> VIP Customers</div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="growth-widget">
                    +18.4%
                    <small>Customer Growth</small>
                    <div style="font-size:1rem;font-weight:600;opacity:.9;">This month</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-teal">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <h2><?= number_format($totalCustomers) ?></h2>
                <p>Total Customers</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-orange">
                <div class="stat-icon"><i class="bi bi-credit-card-2-front-fill"></i></div>
                <h2>KES <?= number_format($outstandingCreditAmount, 2) ?></h2>
                <p>Outstanding Credit Amount</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-purple">
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                <h2><?= number_format($customersWithBalances) ?></h2>
                <p>Customers With Balances</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-blue">
                <div class="stat-icon"><i class="bi bi-calendar2-plus-fill"></i></div>
                <h2><?= number_format($newCustomersThisMonth) ?></h2>
                <p>New Customers This Month</p>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-teal">
                <div class="stat-icon"><i class="bi bi-stars"></i></div>
                <h2><?= number_format($totalLoyaltyPoints) ?></h2>
                <p>Total Loyalty Points Issued</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-orange">
                <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <h2>KES <?= number_format($averageCustomerValue, 2) ?></h2>
                <p>Average Customer Value</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-purple">
                <div class="stat-icon"><i class="bi bi-star-fill"></i></div>
                <h2><?= number_format($vipCustomers) ?></h2>
                <p>VIP Customers</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="crm-stat-card stat-blue">
                <div class="stat-icon"><i class="bi bi-bag-heart-fill"></i></div>
                <h2>KES <?= number_format($largestOutstandingBalance, 2) ?></h2>
                <p>Largest Outstanding Balance</p>
            </div>
        </div>
    </div>

    <div class="glass-card p-3 p-lg-4 mb-4">
        <div class="row align-items-center g-3">
            <div class="col-12 col-lg-3">
                <label class="form-label fw-semibold mb-2">Search customers</label>
                <input type="text" id="searchCustomer" class="form-control crm-input" placeholder="Search by name, phone, email">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label fw-semibold mb-2">Type</label>
                <select id="filterType" class="form-select crm-input">
                    <option value="">All Types</option>
                    <option value="Retail">Retail</option>
                    <option value="Wholesale">Wholesale</option>
                    <option value="VIP">VIP</option>
                    <option value="Walk-in">Walk-in</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label fw-semibold mb-2">Status</label>
                <select id="filterStatus" class="form-select crm-input">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Blocked">Blocked</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label fw-semibold mb-2">Balances</label>
                <select id="filterBalance" class="form-select crm-input">
                    <option value="">All</option>
                    <option value="with_balance">With Outstanding Balances</option>
                    <option value="no_balance">No Outstanding Balance</option>
                </select>
            </div>
            <div class="col-6 col-lg-1">
                <label class="form-label fw-semibold mb-2">Points</label>
                <input type="number" id="filterPointsMin" class="form-control crm-input" placeholder="0" min="0">
            </div>
            <div class="col-6 col-lg-1">
                <label class="form-label fw-semibold mb-2">From</label>
                <input type="date" id="filterFrom" class="form-control crm-input">
            </div>
            <div class="col-6 col-lg-1">
                <label class="form-label fw-semibold mb-2">To</label>
                <input type="date" id="filterTo" class="form-control crm-input">
            </div>
        </div>

        <div class="row g-3 mt-1 mobile-compact">
            <div class="col-12 col-lg-3">
                <button id="applyFilters" class="btn crm-btn w-100 py-3">
                    <i class="bi bi-funnel-fill me-2"></i>Apply Filters
                </button>
            </div>
            <div class="col-12 col-lg-3">
                <button id="resetFilters" class="btn crm-btn-outline w-100 py-3">
                    <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                </button>
            </div>
            <div class="col-12 col-lg-6 text-lg-end d-none d-lg-block">
                <span class="text-muted fw-semibold">
                    Premium customer registry with credit and loyalty intelligence
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-12">
            <div class="section-card p-0">
                <div class="p-3 p-lg-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="section-title mb-1">Add Customer</h4>
                        <div class="text-muted">Create a new customer profile and start tracking relationships immediately.</div>
                    </div>
                    <span class="soft-pill bg-light text-dark">
                        <i class="bi bi-shield-check text-success"></i>
                        SQLite PDO via $conn
                    </span>
                </div>

                <div class="p-3 p-lg-4" id="customerFormCard">
                    <div id="msg"></div>

                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input id="name" class="form-control crm-input" placeholder="Customer name">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input id="phone" class="form-control crm-input" placeholder="2547XXXXXXXX or 07XXXXXXXX">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input id="email" class="form-control crm-input" placeholder="email@domain.com">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Customer Type</label>
                            <select id="customer_type" class="form-select crm-input">
                                <option value="Walk-in">Walk-in</option>
                                <option value="Retail">Retail</option>
                                <option value="Wholesale">Wholesale</option>
                                <option value="VIP">VIP</option>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Company Name</label>
                            <input id="company_name" class="form-control crm-input" placeholder="Company or business name">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Credit Limit</label>
                            <input id="credit_limit" type="number" step="0.01" min="0" class="form-control crm-input" placeholder="0.00">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input id="date_of_birth" type="date" class="form-control crm-input">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Tax PIN</label>
                            <input id="tax_pin" class="form-control crm-input" placeholder="KRA PIN">
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <label class="form-label fw-semibold">Address</label>
                            <input id="address" class="form-control crm-input" placeholder="Customer location">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select id="status" class="form-select crm-input">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Blocked">Blocked</option>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <input id="notes" class="form-control crm-input" placeholder="Internal notes, preferences, remarks">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 flex-wrap">
                        <button id="saveCustomer" class="btn crm-btn px-4 py-3">
                            <i class="bi bi-plus-circle-fill me-2"></i>Save Customer
                        </button>
                        <button id="clearCustomerForm" class="btn crm-btn-outline px-4 py-3">
                            <i class="bi bi-eraser-fill me-2"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card mb-4">
        <div class="p-3 p-lg-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="section-title mb-1">All Customers</h4>
                <div class="text-muted">Premium CRM table with credit, loyalty and quick actions.</div>
            </div>
            <span class="soft-pill bg-light text-dark">
                <i class="bi bi-table"></i> Live filtered view
            </span>
        </div>
        <div class="p-0 p-lg-3">
            <div id="customerTable" class="crm-table-wrap"></div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-lg-4">
            <div class="section-card h-100">
                <div class="p-3 p-lg-4 border-bottom">
                    <h4 class="section-title mb-1">Top Customers by Spending</h4>
                    <div class="text-muted">Largest purchases from the sales ledger.</div>
                </div>
                <div class="p-3 p-lg-4">
                    <?php if (!$topSpending): ?>
                        <div class="text-muted">No sales data found.</div>
                    <?php else: foreach ($topSpending as $row): ?>
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <div>
                                <div class="fw-bold"><?= h($row['name']) ?></div>
                                <div class="muted-small"><?= h($row['customer_type'] ?: 'Walk-in') ?></div>
                            </div>
                            <div class="fw-bold text-success">KES <?= number_format((float)$row['total_spent'], 2) ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="section-card h-100">
                <div class="p-3 p-lg-4 border-bottom">
                    <h4 class="section-title mb-1">Top Customers by Credit</h4>
                    <div class="text-muted">Customers with the highest balances.</div>
                </div>
                <div class="p-3 p-lg-4">
                    <?php if (!$topCredit): ?>
                        <div class="text-muted">No credit data found.</div>
                    <?php else: foreach ($topCredit as $row): ?>
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <div>
                                <div class="fw-bold"><?= h($row['name']) ?></div>
                                <div class="muted-small"><?= h($row['customer_type'] ?: 'Walk-in') ?></div>
                            </div>
                            <div class="fw-bold <?= ((float)$row['balance'] > 0) ? 'text-danger' : 'text-success' ?>">
                                KES <?= number_format((float)$row['balance'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="section-card h-100">
                <div class="p-3 p-lg-4 border-bottom">
                    <h4 class="section-title mb-1">Most Loyal Customers</h4>
                    <div class="text-muted">Highest loyalty point holders.</div>
                </div>
                <div class="p-3 p-lg-4">
                    <?php if (!$mostLoyal): ?>
                        <div class="text-muted">No loyalty data found.</div>
                    <?php else: foreach ($mostLoyal as $row): ?>
                        <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <div>
                                <div class="fw-bold"><?= h($row['name']) ?></div>
                                <div class="muted-small"><?= h($row['customer_type'] ?: 'Walk-in') ?></div>
                            </div>
                            <div class="fw-bold text-primary"><?= number_format((int)$row['loyalty_points']) ?> pts</div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="section-card">
                <div class="p-3 p-lg-4 border-bottom">
                    <h4 class="section-title mb-1">Recent Customer Registrations</h4>
                    <div class="text-muted">Newest customers added to the CRM.</div>
                </div>
                <div class="p-3 p-lg-4">
                    <div class="row g-3">
                        <?php if (!$recentRegs): ?>
                            <div class="col-12 text-muted">No recent registrations found.</div>
                        <?php else: foreach ($recentRegs as $row): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="summary-card">
                                    <div class="icon-bubble sum-teal"><i class="bi bi-person-badge"></i></div>
                                    <h5><?= h($row['name']) ?></h5>
                                    <small><?= h($row['phone'] ?: 'No phone') ?></small>
                                    <div class="mt-2 d-flex gap-2 flex-wrap">
                                        <span class="badge <?= badgeCustomerType($row['customer_type']) ?>"><?= h($row['customer_type'] ?: 'Walk-in') ?></span>
                                        <span class="badge <?= badgeStatus($row['status']) ?>"><?= h($row['status'] ?: 'Inactive') ?></span>
                                    </div>
                                    <div class="mt-2 muted-small"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<button type="button" id="fabAddCustomer" class="fab" title="Add Customer">
    <i class="bi bi-plus-lg"></i>
</button>

<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content crm-modal">
            <div class="modal-header crm-modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="customerDetailsLabel">Customer Profile</h5>
                    <div class="small opacity-75">Enterprise CRM customer workspace</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body crm-modal-body" id="customerDetailsContent">
                <div class="py-5 text-center">
                    <div class="spinner-border text-primary"></div>
                    <div class="mt-3 fw-semibold">Loading customer profile...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const detailsModalEl = document.getElementById('customerDetailsModal');
    const detailsModal = new bootstrap.Modal(detailsModalEl);

    function escapeHtml(str){
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function money(v){
        const n = parseFloat(v || 0);
        return n.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function loadCustomers(){
        const params = {
            q: $('#searchCustomer').val().trim(),
            type: $('#filterType').val(),
            status: $('#filterStatus').val(),
            balance: $('#filterBalance').val(),
            points_min: $('#filterPointsMin').val(),
            from: $('#filterFrom').val(),
            to: $('#filterTo').val()
        };
        $('#customerTable').html('<div class="p-4 text-center"><div class="spinner-border text-primary"></div><div class="mt-2 fw-semibold">Loading customers...</div></div>');
        $.get('ajax/get_customers.php', params, function(html){
            $('#customerTable').html(html);
        }).fail(function(){
            $('#customerTable').html('<div class="alert alert-danger m-3">Failed to load customers.</div>');
        });
    }

    function loadCustomerDetails(customerId){
        $('#customerDetailsContent').html('<div class="py-5 text-center"><div class="spinner-border text-primary"></div><div class="mt-3 fw-semibold">Loading customer profile...</div></div>');
        $.get('ajax/get_customer_details.php', {id: customerId}, function(res){
            if(res.status !== 'ok'){
                $('#customerDetailsContent').html('<div class="alert alert-danger">'+escapeHtml(res.message || 'Failed to load customer details')+'</div>');
                return;
            }

            const c = res.customer || {};
            const s = res.summary || {};
            const txs = res.transactions || [];
            const payments = res.payments || [];
            const notesHistory = res.notes_history || [];
            const avatar = c.profile_photo ? `<img src="${escapeHtml(c.profile_photo)}" alt="">` : escapeHtml((c.initials || 'CU'));
            const custType = escapeHtml(c.customer_type || 'Walk-in');
            const status = escapeHtml(c.status || 'Inactive');

            let txRows = '';
            if (!txs.length) {
                txRows = `<tr><td colspan="5" class="text-center text-muted py-4">No transactions found</td></tr>`;
            } else {
                txs.forEach(t => {
                    txRows += `
                        <tr>
                            <td>${escapeHtml(t.date)}</td>
                            <td><span class="badge text-bg-light">${escapeHtml(t.type || '')}</span></td>
                            <td>${escapeHtml(t.sale_id || '-')}</td>
                            <td class="${(parseFloat(t.amount) < 0) ? 'text-success' : 'text-danger'} fw-bold">KES ${money(t.amount)}</td>
                            <td>${escapeHtml(t.note || '')}</td>
                        </tr>`;
                });
            }

            let paymentRows = '';
            if (!payments.length) {
                paymentRows = `<tr><td colspan="3" class="text-center text-muted py-4">No payment history</td></tr>`;
            } else {
                payments.forEach(t => {
                    paymentRows += `
                        <tr>
                            <td>${escapeHtml(t.date)}</td>
                            <td>KES ${money(Math.abs(t.amount))}</td>
                            <td>${escapeHtml(t.note || '')}</td>
                        </tr>`;
                });
            }

            let notesRows = '';
            if (!notesHistory.length && !c.notes) {
                notesRows = `<div class="text-muted">No notes yet.</div>`;
            } else {
                if (c.notes) {
                    notesRows += `<div class="mb-3 p-3 rounded-4 bg-light border"><div class="fw-bold mb-1">Current Notes</div><div>${escapeHtml(c.notes)}</div></div>`;
                }
                notesHistory.forEach(n => {
                    notesRows += `<div class="mb-2 p-3 rounded-4 bg-white border">
                        <div class="d-flex justify-content-between gap-3">
                            <div class="fw-bold">${escapeHtml(n.type || 'note')}</div>
                            <div class="text-muted small">${escapeHtml(n.date || '')}</div>
                        </div>
                        <div class="mt-2">${escapeHtml(n.note || '')}</div>
                    </div>`;
                });
            }

            const html = `
                <div class="container-fluid px-0">
                    <div class="section-card p-3 p-lg-4 mb-4">
                        <div class="row align-items-center g-3">
                            <div class="col-lg-8">
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <div class="customer-avatar" style="width:72px;height:72px;border-radius:22px;">
                                        ${avatar}
                                    </div>
                                    <div>
                                        <h2 class="fw-bold mb-1">${escapeHtml(c.name || 'Customer')}</h2>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge ${custType ? '' : ''} ${c.customer_type === 'VIP' ? 'type-vip' : (c.customer_type === 'Wholesale' ? 'type-wholesale' : (c.customer_type === 'Retail' ? 'type-retail' : 'type-walkin'))}">${custType}</span>
                                            <span class="badge ${status.toLowerCase() === 'active' ? 'status-active' : (status.toLowerCase() === 'blocked' ? 'status-blocked' : 'status-inactive')}">${status}</span>
                                            <span class="badge text-bg-light text-dark">Customer since ${escapeHtml(c.customer_since || c.created_at || '')}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 text-lg-end">
                                <div class="d-flex gap-2 flex-wrap justify-content-lg-end">
                                    <a class="btn btn-success rounded-4" href="tel:${escapeHtml(c.phone_tel || '')}"><i class="bi bi-telephone-fill me-1"></i>Call</a>
                                    <a class="btn btn-success rounded-4" target="_blank" href="https://wa.me/${escapeHtml(c.phone_wa || '')}"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
                                    <a class="btn btn-primary rounded-4" href="sms:${escapeHtml(c.phone_sms || '')}"><i class="bi bi-chat-dots-fill me-1"></i>SMS</a>
                                    <button type="button" class="btn btn-dark rounded-4" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i>Print</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 g-lg-4 mb-4">
                        <div class="col-md-6 col-xl-3"><div class="summary-card"><div class="icon-bubble sum-orange"><i class="bi bi-cash-stack"></i></div><h5>KES ${money(s.current_balance)}</h5><small>Outstanding Credit</small></div></div>
                        <div class="col-md-6 col-xl-3"><div class="summary-card"><div class="icon-bubble sum-teal"><i class="bi bi-bag-check-fill"></i></div><h5>KES ${money(s.lifetime_purchases)}</h5><small>Lifetime Purchases</small></div></div>
                        <div class="col-md-6 col-xl-3"><div class="summary-card"><div class="icon-bubble sum-purple"><i class="bi bi-stars"></i></div><h5>${escapeHtml(s.loyalty_points || 0)} pts</h5><small>Loyalty Points</small></div></div>
                        <div class="col-md-6 col-xl-3"><div class="summary-card"><div class="icon-bubble sum-green"><i class="bi bi-arrow-repeat"></i></div><h5>${escapeHtml(s.transaction_count || 0)}</h5><small>Number of Transactions</small></div></div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-7">
                            <div class="detail-section">
                                <div class="card-header p-3 p-lg-4">
                                    Customer Information
                                </div>
                                <div class="card-body p-3 p-lg-4">
                                    <div class="row g-3">
                                        <div class="col-md-6"><div class="p-3 rounded-4 bg-light"><div class="text-muted small">Phone</div><div class="fw-bold">${escapeHtml(c.phone || '-')}</div></div></div>
                                        <div class="col-md-6"><div class="p-3 rounded-4 bg-light"><div class="text-muted small">Email</div><div class="fw-bold">${escapeHtml(c.email || '-')}</div></div></div>
                                        <div class="col-md-6"><div class="p-3 rounded-4 bg-light"><div class="text-muted small">Address</div><div class="fw-bold">${escapeHtml(c.address || '-')}</div></div></div>
                                        <div class="col-md-6"><div class="p-3 rounded-4 bg-light"><div class="text-muted small">Date of Birth</div><div class="fw-bold">${escapeHtml(c.date_of_birth || '-')}</div></div></div>
                                        <div class="col-md-6"><div class="p-3 rounded-4 bg-light"><div class="text-muted small">Company Name</div><div class="fw-bold">${escapeHtml(c.company_name || '-')}</div></div></div>
                                        <div class="col-md-6"><div class="p-3 rounded-4 bg-light"><div class="text-muted small">Tax PIN</div><div class="fw-bold">${escapeHtml(c.tax_pin || '-')}</div></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="detail-section h-100">
                                <div class="card-header p-3 p-lg-4">
                                    Financial Section
                                </div>
                                <div class="card-body p-3 p-lg-4">
                                    <div class="row g-3">
                                        <div class="col-12"><div class="p-3 rounded-4 bg-light d-flex justify-content-between"><span class="text-muted">Credit Limit</span><strong>KES ${money(s.credit_limit)}</strong></div></div>
                                        <div class="col-12"><div class="p-3 rounded-4 bg-light d-flex justify-content-between"><span class="text-muted">Current Balance</span><strong class="${parseFloat(s.current_balance) > 0 ? 'text-danger' : 'text-success'}">KES ${money(s.current_balance)}</strong></div></div>
                                        <div class="col-12"><div class="p-3 rounded-4 bg-light d-flex justify-content-between"><span class="text-muted">Available Credit</span><strong>KES ${money(s.available_credit)}</strong></div></div>
                                        <div class="col-12"><div class="p-3 rounded-4 bg-light d-flex justify-content-between"><span class="text-muted">Total Payments</span><strong>KES ${money(s.total_payments)}</strong></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section mb-4">
                        <div class="card-header p-3 p-lg-4">
                            Credit Management
                        </div>
                        <div class="card-body p-3 p-lg-4">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-3">
                                    <label class="form-label fw-semibold">Amount</label>
                                    <input type="number" min="0.01" step="0.01" id="detailAmount" class="form-control crm-input" placeholder="0.00">
                                </div>
                                <div class="col-lg-9">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn crm-btn" id="detailPayBtn" data-id="${escapeHtml(c.id)}">
                                            <i class="bi bi-check-circle-fill me-1"></i>Record Payment
                                        </button>
                                        <button type="button" class="btn btn-outline-success rounded-4" id="detailAddCreditBtn" data-id="${escapeHtml(c.id)}">
                                            <i class="bi bi-plus-circle me-1"></i>Add Credit
                                        </button>
                                        <button type="button" class="btn btn-outline-danger rounded-4" id="detailReduceCreditBtn" data-id="${escapeHtml(c.id)}">
                                            <i class="bi bi-dash-circle me-1"></i>Reduce Credit
                                        </button>
                                        <button type="button" class="btn btn-outline-dark rounded-4" onclick="window.print()">
                                            <i class="bi bi-printer me-1"></i>Print Statement
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="detailCreditMsg" class="mt-3"></div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-8">
                            <div class="detail-section">
                                <div class="card-header p-3 p-lg-4">
                                    Recent Transactions
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Sale ID</th>
                                                    <th>Amount</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>${txRows}</tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="detail-section mb-4">
                                <div class="card-header p-3 p-lg-4">
                                    Payment History
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0 align-middle">
                                            <thead class="table-light">
                                                <tr><th>Date</th><th>Amount</th><th>Note</th></tr>
                                            </thead>
                                            <tbody>${paymentRows}</tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-section">
                                <div class="card-header p-3 p-lg-4">
                                    Notes
                                </div>
                                <div class="card-body p-3 p-lg-4">
                                    ${notesRows}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#customerDetailsContent').html(html);
        }, 'json').fail(function(){
            $('#customerDetailsContent').html('<div class="alert alert-danger">Failed to load data.</div>');
        });
    }

    $(document).on('click', '.customer-row', function(e){
        if ($(e.target).closest('a,button,.dropdown-menu,.dropdown-toggle').length) return;
        const id = $(this).data('customer-id');
        if (!id) return;
        $('#customerDetailsModal').data('customer-id', id);
        detailsModal.show();
        loadCustomerDetails(id);
    });

    $(document).on('click', '.viewCustomer, .recordPayment, .addCredit, .printCustomer, .editCustomer', function(e){
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;
        $('#customerDetailsModal').data('customer-id', id);
        detailsModal.show();
        loadCustomerDetails(id);
    });

    $(document).on('click', '#detailPayBtn, #detailAddCreditBtn, #detailReduceCreditBtn', function(){
        const id = $(this).data('id');
        const amount = parseFloat($('#detailAmount').val());
        let mode = 'payment';

        if ($(this).attr('id') === 'detailAddCreditBtn') mode = 'add_credit';
        if ($(this).attr('id') === 'detailReduceCreditBtn') mode = 'reduce_credit';

        if (!amount || amount <= 0) {
            $('#detailCreditMsg').html('<div class="alert alert-danger">Enter a valid amount greater than zero.</div>');
            return;
        }

        $('#detailCreditMsg').html('<div class="alert alert-info">Processing...</div>');

        $.post('ajax/pay_credit.php', { id: id, amount: amount, mode: mode }, function(res){
            if (res.status === 'ok') {
                $('#detailCreditMsg').html('<div class="alert alert-success">'+escapeHtml(res.message)+'</div>');
                setTimeout(function(){
                    loadCustomerDetails(id);
                    loadCustomers();
                }, 500);
            } else {
                $('#detailCreditMsg').html('<div class="alert alert-danger">'+escapeHtml(res.message || 'Failed')+'</div>');
            }
        }, 'json').fail(function(){
            $('#detailCreditMsg').html('<div class="alert alert-danger">Failed to process transaction.</div>');
        });
    });

    $('#saveCustomer').on('click', function(){
        const payload = {
            name: $('#name').val().trim(),
            phone: $('#phone').val().trim(),
            address: $('#address').val().trim(),
            email: $('#email').val().trim(),
            customer_type: $('#customer_type').val(),
            credit_limit: $('#credit_limit').val().trim(),
            date_of_birth: $('#date_of_birth').val(),
            company_name: $('#company_name').val().trim(),
            tax_pin: $('#tax_pin').val().trim(),
            status: $('#status').val(),
            notes: $('#notes').val().trim()
        };

        if (payload.name.length < 2) {
            $('#msg').html(`
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Enter customer name.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            return;
        }

        $('#msg').html('<div class="alert alert-info">Saving customer...</div>');

        $.post('ajax/add_customer.php', payload, function(res){
            if (res.status === 'ok') {
                $('#msg').html(`
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Customer added successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
                $('#name, #phone, #address, #email, #credit_limit, #date_of_birth, #company_name, #tax_pin, #notes').val('');
                $('#customer_type').val('Walk-in');
                $('#status').val('Active');
                loadCustomers();
            } else {
                $('#msg').html(`
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ${escapeHtml(res.message || 'Failed to save')}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `);
            }
        }, 'json').fail(function(){
            $('#msg').html('<div class="alert alert-danger">AJAX error while saving customer.</div>');
        });
    });

    $(document).on('click', '.deleteCustomer', function(e){
        e.preventDefault();
        e.stopPropagation();

        const id = $(this).data('id');
        if (!id) return;
        if (!confirm('Delete this customer?')) return;

        $.post('ajax/delete_customer.php', { id: id }, function(res){
            if (res.status === 'ok') {
                loadCustomers();
                if ($('#customerDetailsModal').hasClass('show')) {
                    detailsModal.hide();
                }
            } else {
                alert(res.message || 'Delete failed');
            }
        }, 'json');
    });

    $('#applyFilters').on('click', loadCustomers);

    $('#resetFilters').on('click', function(){
        $('#searchCustomer').val('');
        $('#filterType').val('');
        $('#filterStatus').val('');
        $('#filterBalance').val('');
        $('#filterPointsMin').val('');
        $('#filterFrom').val('');
        $('#filterTo').val('');
        loadCustomers();
    });

    let searchTimer = null;
    $('#searchCustomer').on('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadCustomers, 350);
    });

    $('#filterType, #filterStatus, #filterBalance, #filterPointsMin, #filterFrom, #filterTo').on('change', loadCustomers);

    $('#clearCustomerForm').on('click', function(){
        $('#name, #phone, #address, #email, #credit_limit, #date_of_birth, #company_name, #tax_pin, #notes').val('');
        $('#customer_type').val('Walk-in');
        $('#status').val('Active');
        $('#msg').html('');
    });

    $('#fabAddCustomer').on('click', function(){
        document.getElementById('customerFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    $(document).on('click', '.printCustomer', function(){
        const id = $(this).data('id');
        if (!id) return;
        $('#customerDetailsModal').data('customer-id', id);
        detailsModal.show();
        loadCustomerDetails(id);
        setTimeout(function(){ window.print(); }, 800);
    });

    loadCustomers();
})();
</script>
</body>
</html>
<?php require("foot.php"); ?>