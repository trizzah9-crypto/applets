<?php

if (file_exists('permissions.php')) {
    require ('permissions.php');
}

require ('head.php'); // brings in dbconnect.php, session/auth checks, layout start, and $conn

$business_id = (int)($_SESSION['business_id'] ?? 0);

function money($value): string
{
    return number_format((float)$value, 2);
}

function intv($value): int
{
    return (int)round((float)$value);
}

function safeDate(string $value, string $fallback): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : $fallback;
}

function fetchOne(PDO $conn, string $sql, array $params = [], $default = 0)
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();
    return ($result !== false && $result !== null && $result !== '') ? $result : $default;
}

function fetchAllRows(PDO $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$defaultStart = date('Y-m-d', strtotime('-29 days'));
$defaultEnd   = date('Y-m-d');

$startDate = safeDate($_GET['startDate'] ?? $defaultStart, $defaultStart);
$endDate   = safeDate($_GET['endDate'] ?? $defaultEnd, $defaultEnd);

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$startObj = new DateTime($startDate);
$endObj   = new DateTime($endDate);
$rangeDays = (int)$startObj->diff($endObj)->days + 1;

$prevEndObj   = (clone $startObj)->modify('-1 day');
$prevStartObj = (clone $prevEndObj)->modify('-' . max($rangeDays - 1, 0) . ' days');

$prevStartDate = $prevStartObj->format('Y-m-d');
$prevEndDate   = $prevEndObj->format('Y-m-d');

$business = fetchAllRows($conn, "
    SELECT id, owner_user_id, name, business_name, business_email, business_phone, business_address,
           subscription_plan, subscription_expires_at, created_at
    FROM businesses
    WHERE id = ?
    LIMIT 1
", [$business_id]);

$businessRow = $business[0] ?? [];

$businessDisplayName = trim((string)($businessRow['business_name'] ?? '')) ?: trim((string)($businessRow['name'] ?? 'MAMBA POS MADE EASY'));
$subscriptionPlan     = trim((string)($businessRow['subscription_plan'] ?? ''));
$subscriptionExpires  = trim((string)($businessRow['subscription_expires_at'] ?? ''));

$daysLeft = null;
$subscriptionStatus = 'Active';
if ($subscriptionExpires !== '') {
    try {
        $expiry = new DateTime($subscriptionExpires);
        $todayDt = new DateTime(date('Y-m-d'));
        $daysLeft = (int)$todayDt->diff($expiry)->format('%r%a');
        if ($daysLeft < 0) {
            $subscriptionStatus = 'Expired';
        } elseif ($daysLeft <= 30) {
            $subscriptionStatus = 'Expiring Soon';
        }
    } catch (Exception $e) {
        $daysLeft = null;
    }
}

$totalRevenue = (float)fetchOne($conn, "
    SELECT COALESCE(SUM(COALESCE(NULLIF(total_including_vat, 0), total_amount)), 0)
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
", [$business_id, $startDate, $endDate], 0);

$totalOrders = (int)fetchOne($conn, "
    SELECT COUNT(*)
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
", [$business_id, $startDate, $endDate], 0);

$totalItemsSold = (float)fetchOne($conn, "
    SELECT COALESCE(SUM(si.quantity), 0)
    FROM sale_items si
    INNER JOIN sales s ON s.id = si.sale_id
    WHERE s.business_id = ?
      AND date(s.created_at) BETWEEN ? AND ?
", [$business_id, $startDate, $endDate], 0);

$grossProfit = (float)fetchOne($conn, "
    SELECT COALESCE(SUM((si.price - si.cost_price) * si.quantity), 0)
    FROM sale_items si
    INNER JOIN sales s ON s.id = si.sale_id
    WHERE s.business_id = ?
      AND date(s.created_at) BETWEEN ? AND ?
", [$business_id, $startDate, $endDate], 0);

$vatCollected = (float)fetchOne($conn, "
    SELECT COALESCE(SUM(vat_amount), 0)
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
", [$business_id, $startDate, $endDate], 0);

$totalCustomers = (int)fetchOne($conn, "
    SELECT COUNT(*)
    FROM customers
    WHERE business_id = ?
", [$business_id], 0);

$totalProducts = (int)fetchOne($conn, "
    SELECT COUNT(*)
    FROM products
    WHERE business_id = ?
      AND deleted_at IS NULL
", [$business_id], 0);

$lowStockCount = (int)fetchOne($conn, "
    SELECT COUNT(*)
    FROM products
    WHERE business_id = ?
      AND deleted_at IS NULL
      AND stock_qty <= 5
", [$business_id], 0);

$outOfStockCount = (int)fetchOne($conn, "
    SELECT COUNT(*)
    FROM products
    WHERE business_id = ?
      AND deleted_at IS NULL
      AND stock_qty <= 0
", [$business_id], 0);

$stockSummary = fetchAllRows($conn, "
    SELECT
        COALESCE(SUM(stock_qty), 0) AS total_stock,
        COALESCE(SUM(stock_qty * cost_price), 0) AS stock_cost_value,
        COALESCE(SUM(stock_qty * selling_price), 0) AS stock_selling_value
    FROM products
    WHERE business_id = ?
      AND deleted_at IS NULL
", [$business_id]);

$stockRow = $stockSummary[0] ?? [];
$totalStock         = (float)($stockRow['total_stock'] ?? 0);
$stockCostValue     = (float)($stockRow['stock_cost_value'] ?? 0);
$stockSellingValue  = (float)($stockRow['stock_selling_value'] ?? 0);
$expectedProfit     = $stockSellingValue - $stockCostValue;
$stockMargin        = $stockCostValue > 0 ? ($expectedProfit / $stockCostValue) * 100 : 0;
$avgOrderValue      = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

$customerCreditBalance = (float)fetchOne($conn, "
    SELECT COALESCE(SUM(balance), 0)
    FROM customer_accounts ca
    INNER JOIN customers c ON c.id = ca.customer_id
    WHERE c.business_id = ?
", [$business_id], 0);

$supplierBalance = (float)fetchOne($conn, "
    SELECT COALESCE(SUM(balance), 0)
    FROM suppliers
    WHERE business_id = ?
", [$business_id], 0);

$prevRevenue = (float)fetchOne($conn, "
    SELECT COALESCE(SUM(COALESCE(NULLIF(total_including_vat, 0), total_amount)), 0)
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
", [$business_id, $prevStartDate, $prevEndDate], 0);

$prevOrders = (int)fetchOne($conn, "
    SELECT COUNT(*)
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
", [$business_id, $prevStartDate, $prevEndDate], 0);

$revenueGrowth = $prevRevenue > 0 ? (($totalRevenue - $prevRevenue) / $prevRevenue) * 100 : null;
$orderGrowth   = $prevOrders > 0 ? (($totalOrders - $prevOrders) / $prevOrders) * 100 : null;

$salesRows = fetchAllRows($conn, "
    SELECT date(created_at) AS day,
           COALESCE(SUM(COALESCE(NULLIF(total_including_vat, 0), total_amount)), 0) AS total,
           COUNT(*) AS orders
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
    GROUP BY date(created_at)
    ORDER BY day ASC
", [$business_id, $startDate, $endDate]);

$paymentRows = fetchAllRows($conn, "
    SELECT COALESCE(NULLIF(payment_type, ''), 'Unknown') AS payment_type,
           COALESCE(SUM(COALESCE(NULLIF(total_including_vat, 0), total_amount)), 0) AS total
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
    GROUP BY COALESCE(NULLIF(payment_type, ''), 'Unknown')
    ORDER BY total DESC
", [$business_id, $startDate, $endDate]);

$bestProductRows = fetchAllRows($conn, "
    SELECT p.name,
           COALESCE(SUM(si.quantity), 0) AS qty,
           COALESCE(SUM(si.subtotal), 0) AS revenue
    FROM sale_items si
    INNER JOIN sales s ON s.id = si.sale_id
    INNER JOIN products p ON p.id = si.product_id
    WHERE s.business_id = ?
      AND date(s.created_at) BETWEEN ? AND ?
    GROUP BY p.id, p.name
    ORDER BY qty DESC
    LIMIT 7
", [$business_id, $startDate, $endDate]);

$recentSalesRows = fetchAllRows($conn, "
    SELECT sale_number,
           COALESCE(NULLIF(customer_name, ''), 'Walk-in Customer') AS customer_name,
           COALESCE(NULLIF(payment_type, ''), 'Unknown') AS payment_type,
           COALESCE(COALESCE(NULLIF(total_including_vat, 0), total_amount), 0) AS total_amount,
           created_at
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
    ORDER BY datetime(created_at) DESC
    LIMIT 8
", [$business_id, $startDate, $endDate]);

$lowStockRows = fetchAllRows($conn, "
    SELECT name, category, stock_qty, unit, selling_price
    FROM products
    WHERE business_id = ?
      AND deleted_at IS NULL
      AND stock_qty <= 5
    ORDER BY stock_qty ASC, name ASC
    LIMIT 8
", [$business_id]);

$topCustomersRows = fetchAllRows($conn, "
    SELECT COALESCE(NULLIF(customer_name, ''), 'Walk-in Customer') AS customer_name,
           COUNT(*) AS orders,
           COALESCE(SUM(COALESCE(NULLIF(total_including_vat, 0), total_amount)), 0) AS spent
    FROM sales
    WHERE business_id = ?
      AND date(created_at) BETWEEN ? AND ?
    GROUP BY COALESCE(NULLIF(customer_name, ''), 'Walk-in Customer')
    ORDER BY spent DESC
    LIMIT 5
", [$business_id, $startDate, $endDate]);

$days = [];
$revenueByDayMap = [];
$orderByDayMap = [];
foreach ($salesRows as $row) {
    $revenueByDayMap[$row['day']] = (float)$row['total'];
    $orderByDayMap[$row['day']]   = (int)$row['orders'];
}

$periodStart = new DateTime($startDate);
$periodEnd   = new DateTime($endDate);
$periodEnd->modify('+1 day');

$interval = new DateInterval('P1D');
$period = new DatePeriod($periodStart, $interval, $periodEnd);
foreach ($period as $dateObj) {
    $day = $dateObj->format('Y-m-d');
    $days[] = $day;
}

$revenueSeries = [];
$orderSeries   = [];
foreach ($days as $day) {
    $revenueSeries[] = $revenueByDayMap[$day] ?? 0;
    $orderSeries[]   = $orderByDayMap[$day] ?? 0;
}

$paymentLabels = [];
$paymentTotals = [];
foreach ($paymentRows as $row) {
    $paymentLabels[] = $row['payment_type'];
    $paymentTotals[] = (float)$row['total'];
}

$productLabels = [];
$productQtys   = [];
$productRevenue = [];
foreach ($bestProductRows as $row) {
    $productLabels[]  = $row['name'];
    $productQtys[]    = (float)$row['qty'];
    $productRevenue[] = (float)$row['revenue'];
}

$chartDaysPretty = array_map(function ($d) {
    return date('M d', strtotime($d));
}, $days);

$trendDirection = ($revenueGrowth !== null && $revenueGrowth >= 0) ? 'up' : 'down';
$revenueGrowthDisplay = $revenueGrowth === null ? '—' : number_format(abs($revenueGrowth), 1) . '%';
$orderGrowthDisplay    = $orderGrowth === null ? '—' : number_format(abs($orderGrowth), 1) . '%';

?>

<style>
    .premium-dashboard {
        margin-top: 6px;
        padding-bottom: 20px;
    }

    .dashboard-hero {
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 28%),
            linear-gradient(135deg, #054960 0%, #0a5d78 40%, #0f7c9d 100%);
        color: #fff;
        border-radius: 28px;
        padding: 28px 28px 22px;
        box-shadow: 0 18px 45px rgba(0, 0, 0, .16);
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 22px;
    }

    .dashboard-hero::after {
        content: "";
        position: absolute;
        inset: auto -80px -120px auto;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
        filter: blur(4px);
    }

    .hero-copy h1 {
        font-size: clamp(1.5rem, 2vw, 2.25rem);
        font-weight: 800;
        margin: 0;
        line-height: 1.2;
    }

    .hero-copy p {
        margin: 10px 0 0;
        opacity: .93;
        font-size: 1rem;
        max-width: 760px;
    }

    .hero-badge {
        min-width: 170px;
        border-radius: 22px;
        padding: 18px 20px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .14);
        backdrop-filter: blur(10px);
        text-align: center;
        z-index: 1;
    }

    .hero-badge .big {
        font-size: 1.9rem;
        font-weight: 800;
        line-height: 1;
    }

    .hero-badge .small {
        margin-top: 6px;
        font-size: .9rem;
        opacity: .92;
    }

    .hero-pill-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }

    .hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 14px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .12);
        font-weight: 600;
        font-size: .9rem;
    }

    .filter-panel,
    .analytics-panel,
    .mini-panel {
        background: rgba(255, 255, 255, .88);
        backdrop-filter: blur(18px);
        border: 1px solid rgba(9, 73, 96, .08);
        border-radius: 24px;
        box-shadow: 0 12px 32px rgba(13, 37, 62, .08);
    }

    .filter-panel {
        padding: 16px;
        margin-bottom: 20px;
    }

    .filter-panel form {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: 12px;
    }

    .filter-label {
        display: block;
        font-size: .82rem;
        font-weight: 700;
        color: #054960;
        margin-bottom: 6px;
        letter-spacing: .2px;
    }

    .filter-input {
        min-width: 160px;
        border-radius: 14px !important;
        border: 1px solid rgba(5, 73, 96, .18) !important;
        padding: 11px 13px !important;
        height: 46px;
        box-shadow: none !important;
    }

    .filter-btn {
        height: 46px;
        border-radius: 14px !important;
        padding: 0 18px !important;
        font-weight: 700 !important;
        background: linear-gradient(135deg, #ff7900, #ff9d3f) !important;
        border: none !important;
        box-shadow: 0 10px 22px rgba(255, 121, 0, .25);
    }

    .stat-card {
        border: 0;
        border-radius: 22px;
        color: #fff;
        overflow: hidden;
        position: relative;
        min-height: 160px;
        box-shadow: 0 14px 36px rgba(15, 45, 70, .12);
        transition: transform .25s ease, box-shadow .25s ease;
    }

    .stat-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 42px rgba(15, 45, 70, .17);
    }

    .stat-card::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(255, 255, 255, .14), transparent 60%);
        pointer-events: none;
    }

    .stat-card .card-body {
        position: relative;
        z-index: 1;
        padding: 22px;
    }

    .stat-icon {
        position: absolute;
        right: 18px;
        top: 18px;
        font-size: 2.7rem;
        opacity: .18;
    }

    .stat-label {
        font-size: .92rem;
        font-weight: 700;
        letter-spacing: .2px;
        opacity: .93;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: clamp(1.5rem, 2vw, 2.2rem);
        font-weight: 800;
        line-height: 1.1;
        margin: 0;
    }

    .stat-sub {
        margin-top: 10px;
        font-size: .88rem;
        opacity: .9;
    }

    .stat-revenue {
        background: linear-gradient(135deg, #ff7900 0%, #ffb14d 100%);
    }

    .stat-profit {
        background: linear-gradient(135deg, #0e9b59 0%, #3fd08a 100%);
    }

    .stat-orders {
        background: linear-gradient(135deg, #054960 0%, #0f7c9d 100%);
    }

    .stat-customers {
        background: linear-gradient(135deg, #6f42c1 0%, #9d6cff 100%);
    }

    .stat-products {
        background: linear-gradient(135deg, #0d6efd 0%, #5c95ff 100%);
    }

    .stat-stock {
        background: linear-gradient(135deg, #14a6a6 0%, #5dd5d5 100%);
    }

    .panel-card {
        border: 0;
        border-radius: 24px;
        background: rgba(255, 255, 255, .92);
        box-shadow: 0 12px 34px rgba(13, 37, 62, .08);
        overflow: hidden;
        height: 100%;
    }

    .panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid rgba(5, 73, 96, .08);
    }

    .panel-head h5 {
        margin: 0;
        font-weight: 800;
        color: #083a4a;
        font-size: 1rem;
    }

    .panel-head small {
        color: #7c8b93;
        font-weight: 600;
    }

    .panel-body {
        padding: 18px 20px 20px;
    }

    .chart-box {
        position: relative;
        height: 360px;
    }

    .chart-box.tall {
        height: 390px;
    }

    .kpi-list {
        display: grid;
        gap: 12px;
    }

    .kpi-item {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        padding: 14px 15px;
        background: linear-gradient(180deg, rgba(5, 73, 96, .04), rgba(255, 121, 0, .03));
        border: 1px solid rgba(5, 73, 96, .07);
        border-radius: 18px;
    }

    .kpi-item span {
        color: #65757c;
        font-weight: 700;
        font-size: .92rem;
    }

    .kpi-item strong {
        color: #083a4a;
        font-size: 1rem;
    }

    .table-modern {
        margin: 0;
    }

    .table-modern thead th {
        border-top: 0;
        border-bottom: 1px solid rgba(5, 73, 96, .08);
        color: #083a4a;
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .4px;
        font-weight: 800;
        padding-top: 14px;
        padding-bottom: 14px;
    }

    .table-modern tbody td {
        vertical-align: middle;
        padding-top: 14px;
        padding-bottom: 14px;
        border-color: rgba(5, 73, 96, .06);
        color: #33424a;
        font-weight: 600;
    }

    .badge-soft {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: .82rem;
        font-weight: 800;
        letter-spacing: .2px;
    }

    .badge-orange {
        background: rgba(255, 121, 0, .12);
        color: #c95f00;
    }

    .badge-blue {
        background: rgba(5, 73, 96, .10);
        color: #054960;
    }

    .badge-green {
        background: rgba(14, 155, 89, .12);
        color: #0c8a4f;
    }

    .badge-red {
        background: rgba(220, 53, 69, .12);
        color: #c82333;
    }

    .section-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 0 0 14px;
        gap: 12px;
    }

    .section-title h4 {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 800;
        color: #083a4a;
    }

    .section-title p {
        margin: 0;
        color: #7c8b93;
        font-weight: 600;
        font-size: .9rem;
    }

    .subtle-note {
        font-size: .9rem;
        color: #6c7b82;
        font-weight: 600;
    }
    
    .hero-copy h1 {
        font-size: clamp(1.5rem, 2vw, 2.25rem);
        font-weight: 800;
        margin: 0;
        line-height: 1.2;
        color: #ffffff !important;
    }

    .welcome-hand {
        transform-origin: 70% 70%;
        animation: wave 2s infinite;
    }

    

    @keyframes wave {
        0%,100% { transform: rotate(0deg); }
        10% { transform: rotate(14deg); }
        20% { transform: rotate(-8deg); }
        30% { transform: rotate(14deg); }
        40% { transform: rotate(-4deg); }
        50% { transform: rotate(10deg); }
        60% { transform: rotate(0deg); }
    }
    .jina{--orange:#ff7900;}

    .jina span{color:var(--orange);}
    
    .dashboard-pillgroup{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:16px;
    }

    .dashboard-pill{
        border:none;
        padding:10px 18px;
        border-radius:999px;
        background:#eef3f5;
        color:#054960;
        font-weight:700;
        transition:.25s;
        cursor:pointer;
    }

    .dashboard-pill:hover{
        background:#dce9ee;
    }

    .dashboard-pill.active{
        background:linear-gradient(135deg,#ff7900,#ff9d3f);
        color:#fff;
        box-shadow:0 8px 20px rgba(255,121,0,.25);
    }

    .dashboard-filter-wrapper{
    display: flex;
    flex-direction: column;
    align-items: center;
            gap: 10px;
        }

       .dashboard-range-note{
            font-size: 12px;
            color: #7b8794;
            background: rgba(5,73,96,.05);
            border: 1px solid rgba(5,73,96,.08);
            border-radius: 999px;
            padding: 6px 14px;
            backdrop-filter: blur(10px);
        }
            .dashboard-range-wrapper{
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            margin-left: auto;
        }

        .dashboard-pillgroup{
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .dashboard-range-note{
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

    @media (max-width: 991px) {
        .dashboard-hero {
            flex-direction: column;
            align-items: flex-start;
        }

        .hero-badge {
            width: 100%;
        }

        .chart-box,
        .chart-box.tall {
            height: 320px;
        }
    }

   @media (max-width:768px){

    /* =========================
       GLOBAL MOBILE DENSITY
    ==========================*/

    html,
    body{
        overflow-x:hidden;
    }

    .container,
    .container-fluid,
    .premium-dashboard{
        width:100% !important;
        min-width:107% !important;
        padding-left:10px !important;
        padding-right:10px !important;
        margin-left:0 !important;
        margin-right:0 !important;
        transform: translateX(-12px);
    }

    .row{
        margin-left:-5px !important;
        margin-right:-5px !important;
    }

    .row > *{
        padding-left:5px !important;
        padding-right:5px !important;
    }

    .mb-4{
        margin-bottom:10px !important;
    }

    .premium-dashboard{
        padding-bottom:8px !important;
    }


    /* =========================
       HERO SECTION
    ==========================*/

    .dashboard-hero{
        padding:16px !important;
        border-radius:22px !important;
        gap:14px !important;
        margin-bottom:12px !important;
        min-height:auto;
        box-shadow:
            0 10px 35px rgba(0,0,0,.10),
            inset 0 1px 0 rgba(255,255,255,.12);
    }

    .hero-copy{
        width:100%;
    }

    .hero-copy h1{
        font-size:1.15rem !important;
        line-height:1.3;
        margin-bottom:6px !important;
    }

    .hero-copy p{
        font-size:.82rem !important;
        margin-top:6px !important;
        line-height:1.45;
    }

    .hero-pill-row{
        margin-top:10px !important;
        gap:6px !important;
    }

    .hero-pill{
        width:auto !important;
        flex:1 1 calc(50% - 3px);
        justify-content:center;
        padding:8px 10px !important;
        font-size:.72rem !important;
        border-radius:14px !important;
        min-height:38px;
        backdrop-filter:blur(18px);
    }

    .hero-badge{
        width:100%;
        min-width:unset !important;
        padding:12px !important;
        border-radius:18px !important;
    }

    .hero-badge .big{
        font-size:1.3rem !important;
    }

    .hero-badge .small{
        font-size:.75rem !important;
    }


    /* =========================
       FILTERS
    ==========================*/

    .filter-panel{
        padding:12px !important;
        border-radius:20px !important;
        margin-bottom:12px !important;
    }

    .filter-panel form{
        gap:10px !important;
    }

    .filter-input{
        height:42px !important;
        padding:8px 12px !important;
        font-size:.86rem !important;
    }

    .filter-label{
        margin-bottom:4px !important;
        font-size:.74rem !important;
    }

    .filter-btn{
        width:100%;
        height:44px !important;
        border-radius:14px !important;
        font-size:.88rem !important;
    }

    .dashboard-range-wrapper{
        width:100%;
        margin-left:0 !important;
        align-items:center !important;
    }

    .dashboard-pillgroup{
        width:100%;
        display:grid !important;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px !important;
    }

    .dashboard-pill{
        width:100%;
        padding:10px 8px !important;
        font-size:.78rem !important;
    }

    .dashboard-range-note{
        margin-top:4px;
        font-size:.72rem !important;
    }


    /* =========================
       STATISTICS GRID
    ==========================*/

    .col-xl-3.col-lg-6.col-md-6{
        width:50% !important;
        flex:0 0 50% !important;
        max-width:50% !important;
    }

    .stat-card{
          min-height:90% !important;
        height:70%;
        border-radius:20px !important;
        box-shadow:
            0 8px 25px rgba(14,37,62,.10),
            inset 0 1px 0 rgba(255,255,255,.12);
    }

    .stat-card .card-body{
         padding:10px !important;
        display:flex;
        flex-direction:column;
        justify-content:space-between;
        height:100%;
    }

    .stat-label{
         font-size:.62rem !important;
          margin-top:4px !important;
    }

    .stat-value{
        font-size:1rem !important;
        line-height:1.15 !important;
        word-break:break-word;
    }

    .stat-sub{
        margin-top:8px !important;
        font-size:.68rem !important;
        line-height:1.35;
    }

    .stat-icon{
        right:12px !important;
        top:12px !important;
          font-size:1.5rem !important;
        opacity:.14;
    }


    /* =========================
       PANELS
    ==========================*/

    .panel-card{
        border-radius:20px !important;
        overflow:hidden;
        background:rgba(255,255,255,.84);
        backdrop-filter:blur(18px);
    }

    .panel-head{
        padding:12px 14px !important;
        gap:10px !important;
    }

    .panel-head h5{
        font-size:.88rem !important;
    }

    .panel-head small{
        font-size:.7rem !important;
    }

    .panel-body{
        padding:12px !important;
    }

    .badge-soft{
        font-size:.68rem !important;
        padding:6px 10px !important;
    }


    /* =========================
       CHARTS
    ==========================*/

    .chart-box{
        height:250px !important;
    }

    .chart-box.tall{
        height:280px !important;
    }

    canvas{
        max-width:100% !important;
    }


    /* =========================
       KPI BLOCKS
    ==========================*/

    .kpi-list{
        gap:8px !important;
    }

    .kpi-item{
        padding:10px 12px !important;
        border-radius:14px !important;
    }

    .kpi-item span{
        font-size:.75rem !important;
    }

    .kpi-item strong{
        font-size:.82rem !important;
    }


    /* =========================
       TABLES
    ==========================*/

    .table-responsive{
        margin:0 -4px;
        border-radius:16px;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
    }

    .table-modern{
        min-width:560px;
    }

    .table-modern thead th{
        font-size:.68rem !important;
        padding:10px 8px !important;
        white-space:nowrap;
    }

    .table-modern tbody td{
        font-size:.74rem !important;
        padding:10px 8px !important;
        white-space:nowrap;
    }


    /* =========================
       BUTTONS
    ==========================*/

    .btn,
    .btn-lg{
        min-height:44px;
        font-size:.85rem !important;
        border-radius:14px !important;
    }

    .btn-lg{
        width:100%;
    }


    /* =========================
       TYPOGRAPHY
    ==========================*/

    .section-title{
        margin-bottom:8px !important;
    }

    .section-title h4{
        font-size:.9rem !important;
    }

    .section-title p,
    .subtle-note{
        font-size:.74rem !important;
    }


    /* =========================
       SAFETY FOR SMALL DEVICES
    ==========================*/

    @media (max-width:420px){

        .stat-value{
            font-size:.92rem !important;
        }

        .stat-sub{
            font-size:.64rem !important;
        }

        .hero-pill{
            flex:1 1 100%;
        }

        .hero-copy h1{
            font-size:1rem !important;
        }
    }
}
</style>

<div class="premium-dashboard">

    <div class="dashboard-hero">
        <div class="hero-copy">
           <div class="jina">
            <h1><?= $greeting ?>, <span><?= htmlspecialchars($_SESSION['admin'] ?? $_SESSION['user_name'] ?? 'User') ?> <i class="fas fa-hand-paper welcome-hand"></i></span></h1></div>
            <p>
                <?= htmlspecialchars($businessDisplayName) ?> is performing across
                <strong><?= date('M d, Y', strtotime($startDate)) ?></strong>
                to
                <strong><?= date('M d, Y', strtotime($endDate)) ?></strong>.
                This is your live business snapshot for fast, confident decisions.
            </p>

            <div class="hero-pill-row">
                <div class="hero-pill"><i class="fas fa-calendar-alt"></i> <?= date('D, M j, Y') ?></div>
                <div class="hero-pill"><i class="fas fa-store"></i> <?= htmlspecialchars($businessDisplayName) ?></div>
                <div class="hero-pill"><i class="fas fa-bolt"></i> <?= htmlspecialchars($subscriptionPlan ?: 'Standard Plan') ?></div>
                <div class="hero-pill"><i class="fas fa-shield-alt"></i> <?= htmlspecialchars($subscriptionStatus) ?><?= $daysLeft !== null ? ' • ' . $daysLeft . ' days left' : '' ?></div>
            </div>
        </div>

        <div class="hero-badge">
            <div class="big">KES <?= money($totalRevenue) ?></div>
            <div class="small">Revenue in selected period</div>
        </div>
    </div>

    <?php if ($subscriptionStatus !== 'Active'): ?>
        <div class="alert alert-warning border-0 shadow-sm" style="border-radius: 18px; color: #054960;">
            <strong>Subscription status:</strong>
            <?= htmlspecialchars($subscriptionStatus) ?>
            <?= $daysLeft !== null ? ' • ' . abs($daysLeft) . ' day(s)' : '' ?>.
        </div>
    <?php endif; ?>

    <div class="filter-panel">
 
        <form method="get" action="" id="dashboardFilterForm">
            <div>
                <label class="filter-label" for="startDate">Start date</label>
                <input type="date" id="startDate" name="startDate" value="<?= htmlspecialchars($startDate) ?>" class="form-control filter-input">
            </div>

            <div>
                <label class="filter-label" for="endDate">End date</label>
                <input type="date" id="endDate" name="endDate" value="<?= htmlspecialchars($endDate) ?>" class="form-control filter-input">
            </div>

            <div>
                <button type="submit" class="btn btn-primary filter-btn">
                    <i class="fas fa-filter mr-1"></i> Refresh Analytics
                </button>
            </div>

            <div class="dashboard-range-wrapper">
                <div class="dashboard-pillgroup" id="dashboardQuickRanges"> 
                    <div class="dashboard-range-note">
                    Showing <?= (int)$rangeDays ?> day<?= $rangeDays === 1 ? '' : 's' ?> of performance data
                </div>
                    <button type="button" class="dashboard-pill <?= $rangeDays == 1 ? 'active' : '' ?>" data-days="0">
                        Today
                    </button>

                    <button type="button" class="dashboard-pill <?= $rangeDays == 7 ? 'active' : '' ?>" data-days="6">
                        7 Days
                    </button>

                    <button type="button" class="dashboard-pill <?= $rangeDays == 30 ? 'active' : '' ?>" data-days="29">
                        30 Days
                    </button>

                    <button type="button" class="dashboard-pill <?= $rangeDays == 90 ? 'active' : '' ?>" data-days="89">
                        90 Days
                    </button>
                </div>

               
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-revenue">
                <i class="fas fa-wallet stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Revenue</div>
                    <div class="stat-value">KES <?= money($totalRevenue) ?></div>
                    <div class="stat-sub">
                        <?= $revenueGrowth !== null ? (($revenueGrowth >= 0) ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>') . ' ' . $revenueGrowthDisplay . ' vs previous period' : 'No previous comparison' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-profit">
                <i class="fas fa-coins stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Gross Profit</div>
                    <div class="stat-value">KES <?= money($grossProfit) ?></div>
                    <div class="stat-sub">Stock margin <?= money($stockMargin) ?>%</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-orders">
                <i class="fas fa-receipt stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Orders</div>
                    <div class="stat-value"><?= intv($totalOrders) ?></div>
                    <div class="stat-sub">
                        <?= $orderGrowth !== null ? (($orderGrowth >= 0) ? '<i class="fas fa-arrow-up"></i>' : '<i class="fas fa-arrow-down"></i>') . ' ' . $orderGrowthDisplay . ' vs previous period' : 'No previous comparison' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-customers">
                <i class="fas fa-users stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Customers</div>
                    <div class="stat-value"><?= intv($totalCustomers) ?></div>
                    <div class="stat-sub"><?= money($customerCreditBalance) ?> credit balance</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-products">
                <i class="fas fa-boxes stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Products</div>
                    <div class="stat-value"><?= intv($totalProducts) ?></div>
                    <div class="stat-sub"><?= intv($lowStockCount) ?> low stock • <?= intv($outOfStockCount) ?> out</div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-stock">
                <i class="fas fa-warehouse stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Inventory Value</div>
                    <div class="stat-value">KES <?= money($stockSellingValue) ?></div>
                    <div class="stat-sub">Cost <?= money($stockCostValue) ?> • Profit <?= money($expectedProfit) ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-orders" style="background: linear-gradient(135deg, #0d6efd 0%, #47b2ff 100%);">
                <i class="fas fa-file-invoice-dollar stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Avg Order Value</div>
                    <div class="stat-value">KES <?= money($avgOrderValue) ?></div>
                    <div class="stat-sub">VAT collected: KES <?= money($vatCollected) ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6 mb-4">
            <div class="card stat-card stat-profit" style="background: linear-gradient(135deg, #dc3545 0%, #ff6b7a 100%);">
                <i class="fas fa-truck-loading stat-icon"></i>
                <div class="card-body">
                    <div class="stat-label">Supplier Balances</div>
                    <div class="stat-value">KES <?= money($supplierBalance) ?></div>
                    <div class="stat-sub">Payables tracked in real time</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Revenue Trend</h5>
                        <small>Daily sales across the selected range</small>
                    </div>
                    <span class="badge-soft badge-blue">Live analytics</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box tall">
                        <canvas id="salesOverTimeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Payment Mix</h5>
                        <small>How customers are paying</small>
                    </div>
                    <span class="badge-soft badge-orange">Distribution</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box">
                        <canvas id="paymentMethodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Best Selling Products</h5>
                        <small>Top movers in the selected period</small>
                    </div>
                    <span class="badge-soft badge-green">Top products</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box tall">
                        <canvas id="bestProductsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Business Health</h5>
                        <small>Fast operational snapshot</small>
                    </div>
                    <span class="badge-soft badge-red">Health check</span>
                </div>
                <div class="panel-body">
                    <div class="kpi-list">
                        <div class="kpi-item">
                            <span>Stock Cost</span>
                            <strong>KES <?= money($stockCostValue) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Stock Worth</span>
                            <strong>KES <?= money($stockSellingValue) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Expected Profit in Stock</span>
                            <strong>KES <?= money($expectedProfit) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Low Stock Products</span>
                            <strong><?= intv($lowStockCount) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Out of Stock Products</span>
                            <strong><?= intv($outOfStockCount) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Items Sold</span>
                            <strong><?= number_format($totalItemsSold, 0) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Recent Sales</h5>
                        <small>Latest transactions in the selected range</small>
                    </div>
                    <span class="badge-soft badge-blue">Activity</span>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Sale</th>
                                    <th>Customer</th>
                                    <th>Method</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentSalesRows)): ?>
                                    <?php foreach ($recentSalesRows as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['sale_number']) ?></td>
                                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                            <td><span class="badge-soft badge-orange"><?= htmlspecialchars($row['payment_type']) ?></span></td>
                                            <td class="text-right">KES <?= money($row['total_amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No sales found for this period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Low Stock Alerts</h5>
                        <small>Products that need restocking now</small>
                    </div>
                    <span class="badge-soft badge-red">Urgent</span>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th class="text-right">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lowStockRows)): ?>
                                    <?php foreach ($lowStockRows as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td><?= htmlspecialchars($row['category'] ?: 'Uncategorized') ?></td>
                                            <td>
                                                <span class="badge-soft <?= ((float)$row['stock_qty'] <= 0) ? 'badge-red' : 'badge-orange' ?>">
                                                    <?= number_format((float)$row['stock_qty'], 0) ?> <?= htmlspecialchars($row['unit']) ?>
                                                </span>
                                            </td>
                                            <td class="text-right">KES <?= money($row['selling_price']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No low stock alerts right now.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Top Customers</h5>
                        <small>Biggest buyers in this period</small>
                    </div>
                    <span class="badge-soft badge-green">Loyalty</span>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Orders</th>
                                    <th class="text-right">Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($topCustomersRows)): ?>
                                    <?php foreach ($topCustomersRows as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                            <td><?= intv($row['orders']) ?></td>
                                            <td class="text-right">KES <?= money($row['spent']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No customer sales data available in this range.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="panel-card">
                <div class="panel-head">
                    <div>
                        <h5>Performance Summary</h5>
                        <small>Decision-friendly business snapshot</small>
                    </div>
                    <span class="badge-soft badge-blue">Summary</span>
                </div>
                <div class="panel-body">
                    <div class="kpi-list">
                        <div class="kpi-item">
                            <span>Total Revenue</span>
                            <strong>KES <?= money($totalRevenue) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Gross Profit</span>
                            <strong>KES <?= money($grossProfit) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Average Order Value</span>
                            <strong>KES <?= money($avgOrderValue) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>VAT Collected</span>
                            <strong>KES <?= money($vatCollected) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Customer Credit</span>
                            <strong>KES <?= money($customerCreditBalance) ?></strong>
                        </div>
                        <div class="kpi-item">
                            <span>Suppliers Owed</span>
                            <strong>KES <?= money($supplierBalance) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-2 mb-3">
        <a href="pos.php" class="btn btn-lg px-4" style="background: linear-gradient(135deg, #054960, #0f7c9d); color: #fff; border-radius: 16px; box-shadow: 0 12px 26px rgba(5,73,96,.22);">
            <i class="fas fa-cash-register mr-1"></i> Back to POS
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
const pills = document.querySelectorAll('#dashboardQuickRanges .dashboard-pill');
const startInput = document.getElementById('startDate');
const endInput = document.getElementById('endDate');
const filterForm = document.getElementById('dashboardFilterForm');

pills.forEach(btn => {
    btn.addEventListener('click', () => {

        pills.forEach(p => p.classList.remove('active'));
        btn.classList.add('active');

        const days = parseInt(btn.dataset.days, 10);

        const end = new Date();
        const start = new Date();

        start.setDate(end.getDate() - days);

        const formatDate = d => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        startInput.value = formatDate(start);
        endInput.value = formatDate(end);

        filterForm.submit();
    });
});
    const labels = <?= json_encode($chartDaysPretty, JSON_UNESCAPED_UNICODE) ?>;
    const revenueSeries = <?= json_encode(array_map('floatval', $revenueSeries), JSON_UNESCAPED_UNICODE) ?>;
    const orderSeries = <?= json_encode(array_map('floatval', $orderSeries), JSON_UNESCAPED_UNICODE) ?>;
    const paymentLabels = <?= json_encode($paymentLabels, JSON_UNESCAPED_UNICODE) ?>;
    const paymentTotals = <?= json_encode(array_map('floatval', $paymentTotals), JSON_UNESCAPED_UNICODE) ?>;
    const productLabels = <?= json_encode($productLabels, JSON_UNESCAPED_UNICODE) ?>;
    const productQtys = <?= json_encode(array_map('floatval', $productQtys), JSON_UNESCAPED_UNICODE) ?>;
    const productRevenue = <?= json_encode(array_map('floatval', $productRevenue), JSON_UNESCAPED_UNICODE) ?>;

    const commonGrid = 'rgba(5,73,96,0.08)';
    const commonTick = '#60707a';
    const commonFont = "'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif";

    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = commonFont;
        Chart.defaults.color = commonTick;

        const revenueCtx = document.getElementById('salesOverTimeChart');
        const paymentCtx = document.getElementById('paymentMethodChart');
        const productsCtx = document.getElementById('bestProductsChart');

        if (revenueCtx) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue (KES)',
                            data: revenueSeries,
                            borderColor: '#ff7900',
                            backgroundColor: 'rgba(255, 121, 0, 0.16)',
                            fill: true,
                            tension: 0.38,
                            borderWidth: 3,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ff7900'
                        },
                        {
                            label: 'Orders',
                            data: orderSeries,
                            borderColor: '#054960',
                            backgroundColor: 'rgba(5, 73, 96, 0.10)',
                            fill: false,
                            tension: 0.38,
                            borderWidth: 3,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#054960'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y ?? 0;
                                    return label.includes('Revenue')
                                        ? `${label}: KES ${Number(value).toLocaleString()}`
                                        : `${label}: ${Number(value).toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: commonGrid },
                            ticks: {
                                callback: function (value) {
                                    return Number(value).toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        if (paymentCtx) {
            new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentLabels.length ? paymentLabels : ['No data'],
                    datasets: [{
                        data: paymentTotals.length ? paymentTotals : [1],
                        backgroundColor: [
                            '#ff7900',
                            '#054960',
                            '#0e9b59',
                            '#6f42c1',
                            '#0d6efd',
                            '#dc3545',
                            '#14a6a6'
                        ],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return `${label}: KES ${Number(value).toLocaleString()}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        if (productsCtx) {
            new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: productLabels.length ? productLabels : ['No data'],
                    datasets: [{
                        label: 'Qty Sold',
                        data: productQtys.length ? productQtys : [0],
                        backgroundColor: 'rgba(5, 73, 96, 0.88)',
                        borderRadius: 14,
                        barThickness: 26
                    }, {
                        label: 'Revenue (KES)',
                        data: productRevenue.length ? productRevenue : [0],
                        backgroundColor: 'rgba(255, 121, 0, 0.72)',
                        borderRadius: 14,
                        barThickness: 26
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.x ?? 0;
                                    return label.includes('Revenue')
                                        ? `${label}: KES ${Number(value).toLocaleString()}`
                                        : `${label}: ${Number(value).toLocaleString()}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: commonGrid },
                            ticks: {
                                callback: function (value) {
                                    return Number(value).toLocaleString();
                                }
                            }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }
});
</script>

</div>

<?php require 'foot.php'; ?>