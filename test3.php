<?php
require 'permissions.php';
require 'head.php';
// $conn (PDO/SQLite) and $businessName are already available here — set inside head.php

$business_id = (int)($_SESSION['business_id'] ?? 0);

/* ---------- default period: last 30 days ---------- */
$end_date   = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-29 days'));

/* ---------- helpers ---------- */

// Totals for a single period
function mdash_period_totals(PDO $conn, int $bid, string $start, string $end): array
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS tx_count, COALESCE(SUM(total_amount),0) AS revenue
        FROM sales
        WHERE business_id = :bid AND DATE(created_at) BETWEEN :start AND :end
    ");
    $stmt->execute(['bid' => $bid, 'start' => $start, 'end' => $end]);
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(si.quantity),0) AS items,
            COALESCE(SUM((si.price - si.cost_price) * si.quantity),0) AS profit
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.business_id = :bid AND DATE(s.created_at) BETWEEN :start AND :end
    ");
    $stmt->execute(['bid' => $bid, 'start' => $start, 'end' => $end]);
    $items = $stmt->fetch(PDO::FETCH_ASSOC);

    $revenue = (float)$sales['revenue'];
    $tx      = (int)$sales['tx_count'];

    return [
        'revenue'  => $revenue,
        'tx_count' => $tx,
        'items'    => (float)$items['items'],
        'profit'   => (float)$items['profit'],
        'avg_sale' => $tx > 0 ? $revenue / $tx : 0,
    ];
}

// Daily series for a period (fills gaps with 0 so charts never break)
function mdash_period_series(PDO $conn, int $bid, string $start, string $end): array
{
    $stmt = $conn->prepare("
        SELECT DATE(created_at) AS d, COALESCE(SUM(total_amount),0) AS revenue, COUNT(*) AS tx_count
        FROM sales
        WHERE business_id = :bid AND DATE(created_at) BETWEEN :start AND :end
        GROUP BY DATE(created_at)
    ");
    $stmt->execute(['bid' => $bid, 'start' => $start, 'end' => $end]);
    $rev = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rev[$r['d']] = $r;

    $stmt = $conn->prepare("
        SELECT DATE(s.created_at) AS d,
               COALESCE(SUM(si.quantity),0) AS items,
               COALESCE(SUM((si.price - si.cost_price) * si.quantity),0) AS profit
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.business_id = :bid AND DATE(s.created_at) BETWEEN :start AND :end
        GROUP BY DATE(s.created_at)
    ");
    $stmt->execute(['bid' => $bid, 'start' => $start, 'end' => $end]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $items[$r['d']] = $r;

    $dates = $revenue = $profit = $itemsQty = $avg = [];
    $cursor = strtotime($start);
    $endTs  = strtotime($end);
    while ($cursor <= $endTs) {
        $d = date('Y-m-d', $cursor);
        $dates[]    = date('M j', $cursor);
        $r          = (float)($rev[$d]['revenue'] ?? 0);
        $tx         = (int)($rev[$d]['tx_count'] ?? 0);
        $revenue[]  = $r;
        $profit[]   = (float)($items[$d]['profit'] ?? 0);
        $itemsQty[] = (float)($items[$d]['items'] ?? 0);
        $avg[]      = $tx > 0 ? round($r / $tx, 2) : 0;
        $cursor = strtotime('+1 day', $cursor);
    }

    return ['labels' => $dates, 'revenue' => $revenue, 'profit' => $profit, 'items' => $itemsQty, 'avg' => $avg];
}

function mdash_pct(float $curr, float $prev): float
{
    if (abs($prev) < 0.0001) return $curr > 0 ? 100.0 : 0.0;
    return round((($curr - $prev) / abs($prev)) * 100, 1);
}

/* ---------- current + previous period ---------- */
$current = mdash_period_totals($conn, $business_id, $start_date, $end_date);

$daySpan    = (int)round((strtotime($end_date) - strtotime($start_date)) / 86400) + 1;
$prev_end   = date('Y-m-d', strtotime($start_date . ' -1 day'));
$prev_start = date('Y-m-d', strtotime($prev_end . ' -' . ($daySpan - 1) . ' days'));
$previous   = mdash_period_totals($conn, $business_id, $prev_start, $prev_end);

$series = mdash_period_series($conn, $business_id, $start_date, $end_date);

$revenue_change = mdash_pct($current['revenue'], $previous['revenue']);
$profit_change  = mdash_pct($current['profit'], $previous['profit']);
$items_change   = mdash_pct($current['items'], $previous['items']);
$avg_change     = mdash_pct($current['avg_sale'], $previous['avg_sale']);
$margin_pct     = $current['revenue'] > 0 ? round(($current['profit'] / $current['revenue']) * 100, 1) : 0;

/* ---------- payment methods (current period) ---------- */
$stmt = $conn->prepare("
    SELECT COALESCE(NULLIF(payment_type,''), 'unspecified') AS payment_type, COALESCE(SUM(total_amount),0) AS total
    FROM sales
    WHERE business_id = :bid AND DATE(created_at) BETWEEN :start AND :end
    GROUP BY payment_type
    ORDER BY total DESC
");
$stmt->execute(['bid' => $business_id, 'start' => $start_date, 'end' => $end_date]);
$payment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- best sellers (current period) ---------- */
$stmt = $conn->prepare("
    SELECT p.name, COALESCE(SUM(si.quantity),0) AS qty, COALESCE(SUM(si.subtotal),0) AS revenue
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    JOIN sales s ON s.id = si.sale_id
    WHERE s.business_id = :bid AND DATE(s.created_at) BETWEEN :start AND :end
    GROUP BY si.product_id
    ORDER BY qty DESC
    LIMIT 6
");
$stmt->execute(['bid' => $business_id, 'start' => $start_date, 'end' => $end_date]);
$best_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- stock health (business-wide, not period-filtered) ---------- */
$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(stock_qty),0) AS total_stock,
        COALESCE(SUM(stock_qty * cost_price),0) AS stock_cost_value,
        COALESCE(SUM(stock_qty * selling_price),0) AS stock_selling_value,
        COUNT(*) AS product_count
    FROM products
    WHERE business_id = :bid AND deleted_at IS NULL
");
$stmt->execute(['bid' => $business_id]);
$stock = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT id, name, stock_qty, unit, selling_price
    FROM products
    WHERE business_id = :bid AND deleted_at IS NULL AND stock_qty <= 5
    ORDER BY stock_qty ASC
    LIMIT 6
");
$stmt->execute(['bid' => $business_id]);
$low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- recent transactions ---------- */
$stmt = $conn->prepare("
    SELECT sale_number, total_amount, payment_type, customer_name, user_name, created_at
    FROM sales
    WHERE business_id = :bid
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->execute(['bid' => $business_id]);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- receivables / payables ---------- */
$stmt = $conn->prepare("
    SELECT c.name, ca.balance
    FROM customer_accounts ca
    JOIN customers c ON c.id = ca.customer_id
    WHERE c.business_id = :bid AND ca.balance > 0
    ORDER BY ca.balance DESC
    LIMIT 5
");
$stmt->execute(['bid' => $business_id]);
$receivables = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_receivable = array_sum(array_column($receivables, 'balance'));

$stmt = $conn->prepare("SELECT COALESCE(SUM(balance),0) FROM suppliers WHERE business_id = :bid");
$stmt->execute(['bid' => $business_id]);
$total_payable = (float)$stmt->fetchColumn();

/* ---------- greeting ---------- */
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$userLabel = $_SESSION['user_name'] ?? $_SESSION['admin'] ?? 'there';

$canFinance = function_exists('canIt') ? canIt('view_financials') : true;

/* ---------- payload for JS (charts + KPIs) ---------- */
$chartPayload = [
    'revenue'         => $current['revenue'],
    'profit'          => $current['profit'],
    'items'           => $current['items'],
    'avg_sale'        => $current['avg_sale'],
    'tx_count'        => $current['tx_count'],
    'revenue_change'  => $revenue_change,
    'profit_change'   => $profit_change,
    'items_change'    => $items_change,
    'avg_change'      => $avg_change,
    'margin_pct'      => $margin_pct,
    'series'          => $series,
    'payment_data'    => $payment_data,
    'best_products'   => $best_products,
    'start'           => $start_date,
    'end'             => $end_date,
];
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  body{
    background:
      radial-gradient(900px 500px at 0% 0%, rgba(211,240,245,.95), transparent 55%),
      linear-gradient(180deg, #f7fcfd 0%, #eaf7f9 100%);
    color:#004549;
  }
  .mdash{
    --bg-deep:#f4fbfc; --bg-deep-2:#d3f0f5;
    --surface:rgba(255,255,255,.86); --surface-2:rgba(255,255,255,.95);
    --border:rgba(5,73,96,.12); --border-soft:rgba(5,73,96,.08);
    --orange:#ff7900; --orange-soft:rgba(255,121,0,.14);
    --teal:#025659; --teal-soft:rgba(2,86,89,.12);
    --text:#004549; --text-mid:#336b72; --text-dim:#5a7a80;
    --up:#025659; --down:#ff5c72; --warn:#ffb000;
    --r-lg:22px; --r-md:16px; --r-sm:11px;
    --shadow:0 20px 50px -24px rgba(5,73,96,.28);
    font-family:'Inter',system-ui,sans-serif;
    color:#004549;
    background:
      radial-gradient(1100px 500px at 12% -10%, rgba(255,121,0,.12), transparent 60%),
      radial-gradient(900px 500px at 100% 0%, rgba(2,86,89,.10), transparent 55%),
      linear-gradient(180deg,var(--bg-deep-2),var(--bg-deep));
    border-radius:26px;
    padding:28px;
    margin:10px 0 34px;
    border:1px solid var(--border-soft);
    box-shadow:var(--shadow);
    position:relative;
    overflow:hidden;
  }
  .mdash *{box-sizing:border-box;}
  .mdash h1,.mdash h2,.mdash h3,.mdash h4,.mdash h5{font-family:'Plus Jakarta Sans',sans-serif;margin:0;}
  .mdash a{text-decoration:none;}

  /* ---------- header ---------- */
  .mdash-top{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:18px;margin-bottom:26px;}
  .mdash-eyebrow{display:flex;align-items:center;gap:8px;font-size:12.5px;letter-spacing:.08em;text-transform:uppercase;color:#5a7a80;font-weight:600;margin-bottom:6px;}
  .mdash-dot{width:8px;height:8px;border-radius:50%;background:var(--up);box-shadow:0 0 0 0 rgba(2,86,89,.35);animation:mdash-pulse 2.2s infinite;}
  @keyframes mdash-pulse{
    0%{box-shadow:0 0 0 0 rgba(2,86,89,.35);}
    70%{box-shadow:0 0 0 8px rgba(2,86,89,0);}
    100%{box-shadow:0 0 0 0 rgba(2,86,89,0);}
  }
  .mdash-greeting{font-size:26px;font-weight:800;letter-spacing:-.01em;}
  .mdash-greeting span{color:var(--orange);}
  .mdash-sub{color:#336b72;font-size:14px;margin-top:4px;}

  .mdash-controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .mdash-pillgroup{display:flex;background:rgba(255,255,255,.84);border:1px solid var(--border);border-radius:999px;padding:4px;gap:2px;}
  .mdash-pill{border:none;background:transparent;color:#336b72;font-size:12.5px;font-weight:600;padding:7px 13px;border-radius:999px;cursor:pointer;transition:.2s;white-space:nowrap;}
  .mdash-pill:hover{color:#004549;}
  .mdash-pill.active{background:linear-gradient(135deg,var(--orange),#ff7900);color:#ffffff;box-shadow:0 4px 14px rgba(255,121,0,.35);}

  .mdash-datewrap{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.9);border:1px solid var(--border);border-radius:999px;padding:6px 8px 6px 14px;}
  .mdash-datewrap input[type=date]{background:transparent;border:none;color:#004549;font-size:12.5px;font-family:'Inter',sans-serif;font-weight:600;colorScheme:dark;}
  .mdash-datewrap input[type=date]::-webkit-calendar-picker-indicator{filter:invert(.8);cursor:pointer;}
  .mdash-arrow{color:#5a7a80;font-size:11px;}
  .mdash-apply{border:none;background:linear-gradient(135deg,var(--teal),#025659);color:#ffffff;font-weight:700;font-size:12.5px;padding:8px 16px;border-radius:999px;cursor:pointer;transition:transform .15s;}
  .mdash-apply:hover{transform:translateY(-1px);}
  .mdash-apply:active{transform:translateY(0);}

  /* ---------- KPI grid ---------- */
  .mdash-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px;}
  .mdash-card{
    background:rgba(255,255,255,.92);border:1px solid var(--border);border-radius:var(--r-lg);
    padding:18px 18px 16px;position:relative;overflow:hidden;
    transition:transform .2s ease, border-color .2s ease, background .2s ease;
    animation:mdash-rise .5s ease both;
  }
  .mdash-card:hover{transform:translateY(-3px);border-color:rgba(5,73,96,.10);background:var(--surface-2);}
  @keyframes mdash-rise{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
  .mdash-kpis .mdash-card:nth-child(1){animation-delay:.02s;}
  .mdash-kpis .mdash-card:nth-child(2){animation-delay:.08s;}
  .mdash-kpis .mdash-card:nth-child(3){animation-delay:.14s;}
  .mdash-kpis .mdash-card:nth-child(4){animation-delay:.20s;}
  .mdash-kpis .mdash-card:nth-child(5){animation-delay:.26s;}

  .mdash-kpi-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
  .mdash-kpi-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;}
  .mdash-ic-orange{background:var(--orange-soft);color:var(--orange);}
  .mdash-ic-teal{background:var(--teal-soft);color:var(--teal);}
  .mdash-ic-mint{background:rgba(51,214,166,.16);color:var(--up);}
  .mdash-ic-warn{background:rgba(255,176,0,.14);color:var(--warn);}

  .mdash-label{font-size:12px;color:#336b72;font-weight:600;letter-spacing:.01em;}
  .mdash-value{font-family:'Plus Jakarta Sans',sans-serif;font-size:23px;font-weight:800;margin-top:2px;letter-spacing:-.01em;}
  .mdash-value small{font-size:12px;font-weight:600;color:#5a7a80;margin-left:2px;}

  .mdash-chip{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;padding:3px 8px;border-radius:999px;margin-top:8px;}
  .mdash-chip.up{color:var(--up);background:rgba(51,214,166,.12);}
  .mdash-chip.down{color:var(--down);background:rgba(255,92,114,.12);}
  .mdash-chip.flat{color:#5a7a80;background:rgba(255,255,255,.06);}
  .mdash-spark{position:absolute;right:14px;bottom:12px;width:76px;height:30px;opacity:.9;}
  .mdash-card-sub{font-size:11px;color:#5a7a80;margin-top:6px;}

  /* ---------- panels ---------- */
  .mdash-row{display:grid;gap:14px;margin-bottom:14px;}
  .mdash-row.cols-3-1{grid-template-columns:2fr 1fr;}
  .mdash-row.cols-1-1{grid-template-columns:1fr 1fr;}
  .mdash-row.cols-2-1{grid-template-columns:2fr 1fr;}

  .mdash-panel{background:rgba(255,255,255,.92);border:1px solid var(--border);border-radius:var(--r-lg);padding:20px;}
  .mdash-panel-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:10px;flex-wrap:wrap;}
  .mdash-panel-title{font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px;}
  .mdash-panel-title i{color:var(--orange);font-size:13px;}
  .mdash-panel-note{font-size:11.5px;color:#5a7a80;}
  .mdash-chart-wrap{position:relative;height:260px;}
  .mdash-empty{display:flex;align-items:center;justify-content:center;height:100%;color:#5a7a80;font-size:13px;text-align:center;flex-direction:column;gap:6px;}
  .mdash-empty i{font-size:22px;opacity:.5;}

  /* ---------- legend for donut ---------- */
  .mdash-legend{display:flex;flex-direction:column;gap:9px;margin-top:14px;}
  .mdash-legend-item{display:flex;align-items:center;justify-content:space-between;font-size:12.5px;}
  .mdash-legend-left{display:flex;align-items:center;gap:8px;color:#336b72;text-transform:capitalize;}
  .mdash-legend-dot{width:9px;height:9px;border-radius:3px;}
  .mdash-legend-val{font-weight:700;color:#004549;}

  /* ---------- tables ---------- */
  .mdash-table{width:100%;border-collapse:collapse;font-size:12.8px;}
  .mdash-table th{text-align:left;color:#5a7a80;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.04em;padding:0 10px 10px;border-bottom:1px solid var(--border-soft);}
  .mdash-table td{padding:11px 10px;border-bottom:1px solid var(--border-soft);color:#336b72;vertical-align:middle;}
  .mdash-table tr:last-child td{border-bottom:none;}
  .mdash-table td.strong{color:#004549;font-weight:700;}
  .mdash-table-scroll{overflow-x:auto;}

  .mdash-badge{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;padding:3px 8px;border-radius:999px;text-transform:capitalize;white-space:nowrap;}
  .mdash-badge.mpesa{background:rgba(51,214,166,.14);color:var(--up);}
  .mdash-badge.cash{background:var(--orange-soft);color:var(--orange);}
  .mdash-badge.card{background:var(--teal-soft);color:var(--teal);}
  .mdash-badge.credit{background:rgba(255,201,77,.14);color:var(--warn);}
  .mdash-badge.other{background:rgba(5,73,96,.06);color:#336b72;}
  .mdash-badge.critical{background:rgba(255,92,114,.16);color:var(--down);}
  .mdash-badge.low{background:rgba(255,176,0,.14);color:var(--warn);}

  .mdash-stockbar{width:60px;height:5px;border-radius:4px;background:rgba(5,73,96,.06);overflow:hidden;display:inline-block;vertical-align:middle;margin-left:8px;}
  .mdash-stockbar-fill{height:100%;border-radius:4px;}

  .mdash-barrow{display:flex;align-items:center;gap:10px;margin-bottom:13px;}
  .mdash-barrow:last-child{margin-bottom:0;}
  .mdash-barname{width:110px;flex-shrink:0;font-size:12.3px;color:#336b72;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .mdash-bartrack{flex:1;height:9px;border-radius:5px;background:rgba(255,255,255,.06);overflow:hidden;}
  .mdash-barfill{height:100%;border-radius:5px;background:linear-gradient(90deg,var(--orange),#ffb35c);}
  .mdash-barval{width:44px;text-align:right;font-size:11.5px;font-weight:700;color:#004549;flex-shrink:0;}

  .mdash-mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;}
  .mdash-mini{background:rgba(255,255,255,.98);border:1px solid var(--border-soft);border-radius:var(--r-sm);padding:12px;}
  .mdash-mini-label{font-size:10.5px;color:#5a7a80;font-weight:600;text-transform:uppercase;letter-spacing:.03em;}
  .mdash-mini-val{font-size:15px;font-weight:800;margin-top:3px;font-family:'Plus Jakarta Sans',sans-serif;}

  .mdash-receiv-item{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px dashed var(--border-soft);font-size:12.8px;}
  .mdash-receiv-item:last-child{border-bottom:none;}
  .mdash-avatar{width:26px;height:26px;border-radius:50%;background:var(--teal-soft);color:var(--teal);display:inline-flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:800;margin-right:8px;}

  .mdash-foot-actions{display:flex;justify-content:flex-end;margin-top:6px;}
  .mdash-btn-ghost{border:1px solid var(--border);background:rgba(255,255,255,.92);color:#336b72;font-size:12.5px;font-weight:600;padding:9px 16px;border-radius:11px;display:inline-flex;align-items:center;gap:7px;transition:.2s;}
  .mdash-btn-ghost:hover{color:#004549;border-color:rgba(5,73,96,.12);}
/* =========================================================
   RESPONSIVE DESIGN
========================================================= */

@media (max-width:1200px){
    .mdash-row.cols-3-1,
    .mdash-row.cols-2-1{
        grid-template-columns:1fr;
    }

    .mdash-row.cols-1-1{
        grid-template-columns:1fr;
    }
}

/* Tablets */
@media (max-width:992px){

    .mdash{
        padding:22px;
    }

    .mdash-top{
        flex-direction:column;
        align-items:stretch;
    }

    .mdash-controls{
        width:100%;
        justify-content:space-between;
    }

    .mdash-kpis{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .mdash-chart-wrap{
        height:230px;
    }

    .mdash-greeting{
        font-size:22px;
    }
}

/* Large phones */
@media (max-width:768px){

    .mdash{
        padding:18px;
        border-radius:18px;
    }

    .mdash-top{
        gap:15px;
    }

    .mdash-controls{
        flex-direction:column;
        align-items:stretch;
        gap:12px;
    }

    .mdash-pillgroup{
        width:100%;
        overflow-x:auto;
        scrollbar-width:none;
        justify-content:flex-start;
    }

    .mdash-pillgroup::-webkit-scrollbar{
        display:none;
    }

    .mdash-pill{
        flex-shrink:0;
    }

    .mdash-datewrap{
        width:100%;
        border-radius:16px;
        padding:12px;
        display:flex;
        flex-wrap:wrap;
        justify-content:center;
    }

    .mdash-datewrap input[type=date]{
        width:100%;
        padding:8px 0;
        text-align:center;
    }

    .mdash-arrow{
        display:none;
    }

    .mdash-apply{
        width:100%;
        margin-top:8px;
    }

    .mdash-kpis{
        grid-template-columns:repeat(2,1fr);
        gap:12px;
    }

    .mdash-card{
        padding:15px;
    }

    .mdash-value{
        font-size:20px;
    }

    .mdash-greeting{
        font-size:20px;
    }

    .mdash-sub{
        font-size:13px;
    }

    .mdash-panel{
        padding:16px;
    }

    .mdash-panel-head{
        flex-direction:column;
        align-items:flex-start;
        gap:8px;
    }

    .mdash-chart-wrap{
        height:220px;
    }

    .mdash-mini-stats{
        grid-template-columns:1fr;
    }

    .mdash-table{
        min-width:650px;
    }

    .mdash-table-scroll{
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
    }
}

/* Small phones */
@media (max-width:480px){

    .mdash{
        padding:14px;
        margin:0;
        border-radius:16px;
    }

    .mdash-eyebrow{
        font-size:11px;
    }

    .mdash-greeting{
        font-size:18px;
        line-height:1.3;
    }

    .mdash-sub{
        font-size:12px;
    }

    .mdash-kpis{
        grid-template-columns:1fr;
        gap:10px;
    }

    .mdash-card{
        padding:14px;
    }

    .mdash-value{
        font-size:18px;
    }

    .mdash-value small{
        display:block;
        margin-top:4px;
        margin-left:0;
        font-size:11px;
    }

    .mdash-kpi-icon{
        width:32px;
        height:32px;
        font-size:13px;
    }

    .mdash-label{
        font-size:11px;
    }

    .mdash-spark{
        display:none;
    }

    .mdash-panel{
        padding:14px;
        border-radius:16px;
    }

    .mdash-panel-title{
        font-size:14px;
    }

    .mdash-panel-note{
        font-size:11px;
    }

    .mdash-chart-wrap{
        height:190px;
    }

    #chartPayments{
        max-height:160px !important;
    }

    .mdash-barname{
        width:80px;
        font-size:11px;
    }

    .mdash-barval{
        width:40px;
        font-size:11px;
    }

    .mdash-legend-item{
        font-size:11px;
    }

    .mdash-receiv-item{
        flex-direction:column;
        align-items:flex-start;
        gap:5px;
    }

    .mdash-btn-ghost{
        width:100%;
        justify-content:center;
    }

    .mdash-foot-actions{
        justify-content:stretch;
    }
}

/* Extra small devices (320px phones) */
@media (max-width:360px){

    .mdash{
        padding:12px;
    }

    .mdash-greeting{
        font-size:16px;
    }

    .mdash-value{
        font-size:16px;
    }

    .mdash-chart-wrap{
        height:170px;
    }

    .mdash-panel{
        padding:12px;
    }

    .mdash-card{
        padding:12px;
    }

    .mdash-barname{
        width:65px;
    }

    .mdash-datewrap{
        padding:10px;
    }

    .mdash-pill{
        padding:6px 10px;
        font-size:11px;
    }
}

/* Reduce animations for slower mobile devices */
@media (prefers-reduced-motion: reduce){

    .mdash-card,
    .mdash-dot{
        animation:none !important;
    }

    .mdash-card:hover{
        transform:none;
    }

    .mdash-apply:hover{
        transform:none;
    }
}
</style>

<div class="mdash">

  <!-- ================= HEADER ================= -->
  <div class="mdash-top">
    <div>
      <div class="mdash-eyebrow"><span class="mdash-dot"></span> LIVE &middot; <?= htmlspecialchars($businessName) ?></div>
      <div class="mdash-greeting"><?= $greeting ?>, <span><?= htmlspecialchars($userLabel) ?></span></div>
      <div class="mdash-sub" id="mdashPeriodLabel">Showing performance for the last 30 days</div>
    </div>

    <div class="mdash-controls">
      <div class="mdash-pillgroup" id="mdashQuickRanges">
        <button class="mdash-pill" data-days="0">Today</button>
        <button class="mdash-pill" data-days="6">7 Days</button>
        <button class="mdash-pill active" data-days="29">30 Days</button>
        <button class="mdash-pill" data-days="89">90 Days</button>
      </div>
      <div class="mdash-datewrap">
        <input type="date" id="mdashStart" value="<?= htmlspecialchars($start_date) ?>">
        <span class="mdash-arrow"><i class="fa-solid fa-arrow-right"></i></span>
        <input type="date" id="mdashEnd" value="<?= htmlspecialchars($end_date) ?>">
        <button class="mdash-apply" id="mdashApply"><i class="fa-solid fa-magnifying-glass-chart"></i> Apply</button>
      </div>
    </div>
  </div>

  <!-- ================= KPI CARDS ================= -->
  <div class="mdash-kpis">

    <div class="mdash-card">
      <div class="mdash-kpi-top">
        <div class="mdash-kpi-icon mdash-ic-orange"><i class="fa-solid fa-sack-dollar"></i></div>
      </div>
      <div class="mdash-label">Total Revenue</div>
      <div class="mdash-value" id="kpiRevenue">KES <?= number_format($current['revenue'], 0) ?></div>
      <span class="mdash-chip" id="kpiRevenueChip"></span>
      <canvas class="mdash-spark" id="sparkRevenue" width="140" height="60"></canvas>
    </div>

    <?php if ($canFinance): ?>
    <div class="mdash-card">
      <div class="mdash-kpi-top">
        <div class="mdash-kpi-icon mdash-ic-mint"><i class="fa-solid fa-chart-line"></i></div>
      </div>
      <div class="mdash-label">Gross Profit</div>
      <div class="mdash-value" id="kpiProfit">KES <?= number_format($current['profit'], 0) ?><small id="kpiMargin"><?= $margin_pct ?>% margin</small></div>
      <span class="mdash-chip" id="kpiProfitChip"></span>
      <canvas class="mdash-spark" id="sparkProfit" width="140" height="60"></canvas>
    </div>
    <?php endif; ?>

    <div class="mdash-card">
      <div class="mdash-kpi-top">
        <div class="mdash-kpi-icon mdash-ic-teal"><i class="fa-solid fa-boxes-stacked"></i></div>
      </div>
      <div class="mdash-label">Items Sold</div>
      <div class="mdash-value" id="kpiItems"><?= number_format($current['items'], 0) ?></div>
      <span class="mdash-chip" id="kpiItemsChip"></span>
      <canvas class="mdash-spark" id="sparkItems" width="140" height="60"></canvas>
    </div>

    <div class="mdash-card">
      <div class="mdash-kpi-top">
        <div class="mdash-kpi-icon mdash-ic-warn"><i class="fa-solid fa-receipt"></i></div>
      </div>
      <div class="mdash-label">Avg. Sale Value</div>
      <div class="mdash-value" id="kpiAvg">KES <?= number_format($current['avg_sale'], 0) ?></div>
      <span class="mdash-chip" id="kpiAvgChip"></span>
      <canvas class="mdash-spark" id="sparkAvg" width="140" height="60"></canvas>
    </div>

    <?php if ($canFinance): ?>
    <div class="mdash-card">
      <div class="mdash-kpi-top">
        <div class="mdash-kpi-icon mdash-ic-orange"><i class="fa-solid fa-warehouse"></i></div>
      </div>
      <div class="mdash-label">Stock Worth (Retail)</div>
      <div class="mdash-value">KES <?= number_format($stock['stock_selling_value'], 0) ?></div>
      <div class="mdash-card-sub"><?= (int)$stock['product_count'] ?> products &middot; cost KES <?= number_format($stock['stock_cost_value'], 0) ?></div>
    </div>
    <?php endif; ?>

  </div>

  <!-- ================= REVENUE TREND + PAYMENT METHODS ================= -->
  <div class="mdash-row cols-3-1">
    <div class="mdash-panel">
      <div class="mdash-panel-head">
        <div class="mdash-panel-title"><i class="fa-solid fa-chart-area"></i> Revenue Trend</div>
        <div class="mdash-panel-note" id="mdashTrendNote"><?= (int)$current['tx_count'] ?> transactions this period</div>
      </div>
      <div class="mdash-chart-wrap">
        <canvas id="chartRevenue"></canvas>
        <div class="mdash-empty" id="chartRevenueEmpty" style="display:none;">
          <i class="fa-solid fa-chart-line"></i>
          <span>No sales recorded in this period yet</span>
        </div>
      </div>
    </div>

    <div class="mdash-panel">
      <div class="mdash-panel-head">
        <div class="mdash-panel-title"><i class="fa-solid fa-wallet"></i> Payment Methods</div>
      </div>
      <div class="mdash-chart-wrap" style="height:170px;">
        <canvas id="chartPayments"></canvas>
        <div class="mdash-empty" id="chartPaymentsEmpty" style="display:none;">
          <i class="fa-solid fa-wallet"></i><span>No payments yet</span>
        </div>
      </div>
      <div class="mdash-legend" id="paymentLegend"></div>
    </div>
  </div>

  <!-- ================= BEST SELLERS + LOW STOCK ================= -->
  <div class="mdash-row cols-1-1">
    <div class="mdash-panel">
      <div class="mdash-panel-head">
        <div class="mdash-panel-title"><i class="fa-solid fa-fire"></i> Best-Selling Products</div>
        <div class="mdash-panel-note">by quantity</div>
      </div>
      <div id="bestSellersWrap">
        <?php if (empty($best_products)): ?>
          <div class="mdash-empty" style="height:150px;"><i class="fa-solid fa-fire"></i><span>No product sales in this period yet</span></div>
        <?php else:
          $maxQty = max(array_column($best_products, 'qty')) ?: 1;
          foreach ($best_products as $p): $pct = max(6, round(((float)$p['qty'] / $maxQty) * 100)); ?>
          <div class="mdash-barrow">
            <div class="mdash-barname" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></div>
            <div class="mdash-bartrack"><div class="mdash-barfill" style="width:<?= $pct ?>%;"></div></div>
            <div class="mdash-barval"><?= number_format($p['qty'], 0) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="mdash-panel">
      <div class="mdash-panel-head">
        <div class="mdash-panel-title"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</div>
        <div class="mdash-panel-note">&le; 5 units</div>
      </div>
      <?php if (empty($low_stock)): ?>
        <div class="mdash-empty" style="height:150px;"><i class="fa-solid fa-circle-check"></i><span>All stock levels look healthy</span></div>
      <?php else: ?>
      <div class="mdash-table-scroll">
        <table class="mdash-table">
          <thead><tr><th>Product</th><th>Stock</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($low_stock as $p):
              $q = (float)$p['stock_qty'];
              $status = $q <= 0 ? ['Out of stock','critical',0] : ($q <= 2 ? ['Critical','critical',(int)round($q/5*100)] : ['Low','low',(int)round($q/5*100)]);
              $barColor = $status[1] === 'critical' ? 'var(--down)' : 'var(--warn)';
            ?>
            <tr>
              <td class="strong"><?= htmlspecialchars($p['name']) ?></td>
              <td><?= number_format($q, 1) ?> <?= htmlspecialchars($p['unit']) ?>
                <span class="mdash-stockbar"><span class="mdash-stockbar-fill" style="width:<?= $status[2] ?>%;background:<?= $barColor ?>;"></span></span>
              </td>
              <td><span class="mdash-badge <?= $status[1] ?>"><?= $status[0] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= RECENT SALES + RECEIVABLES ================= -->
  <div class="mdash-row cols-2-1">
    <div class="mdash-panel">
      <div class="mdash-panel-head">
        <div class="mdash-panel-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Transactions</div>
        <a href="sales_report.php" class="mdash-panel-note">View all &rarr;</a>
      </div>
      <?php if (empty($recent_sales)): ?>
        <div class="mdash-empty" style="height:150px;"><i class="fa-solid fa-receipt"></i><span>No transactions recorded yet</span></div>
      <?php else: ?>
      <div class="mdash-table-scroll">
        <table class="mdash-table">
          <thead><tr><th>Sale #</th><th>Customer</th><th>Cashier</th><th>Payment</th><th>Amount</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach ($recent_sales as $s):
              $pt = strtolower(trim((string)($s['payment_type'] ?? '')));
              $badgeClass = in_array($pt, ['mpesa','cash','card','credit']) ? $pt : 'other';
            ?>
            <tr>
              <td class="strong"><?= htmlspecialchars($s['sale_number']) ?></td>
              <td><?= htmlspecialchars($s['customer_name'] ?: 'Walk-in') ?></td>
              <td><?= htmlspecialchars($s['user_name'] ?: '—') ?></td>
              <td><span class="mdash-badge <?= $badgeClass ?>"><?= htmlspecialchars($pt ?: 'n/a') ?></span></td>
              <td class="strong">KES <?= number_format((float)$s['total_amount'], 0) ?></td>
              <td><?= date('M j, g:i a', strtotime($s['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($canFinance): ?>
    <div class="mdash-panel">
      <div class="mdash-panel-head">
        <div class="mdash-panel-title"><i class="fa-solid fa-people-arrows"></i> Receivables</div>
      </div>
      <?php if (empty($receivables)): ?>
        <div class="mdash-empty" style="height:100px;"><i class="fa-solid fa-handshake"></i><span>No outstanding customer balances</span></div>
      <?php else: foreach ($receivables as $r):
        $initials = strtoupper(substr(trim($r['name']), 0, 1) . substr(strrchr(trim($r['name']), ' ') ?: '', 1, 1));
      ?>
      <div class="mdash-receiv-item">
        <span><span class="mdash-avatar"><?= htmlspecialchars($initials ?: '?') ?></span><?= htmlspecialchars($r['name']) ?></span>
        <span class="strong">KES <?= number_format($r['balance'], 0) ?></span>
      </div>
      <?php endforeach; endif; ?>

      <div class="mdash-mini-stats">
        <div class="mdash-mini">
          <div class="mdash-mini-label">Owed by customers</div>
          <div class="mdash-mini-val" style="color:var(--up);">KES <?= number_format($total_receivable, 0) ?></div>
        </div>
        <div class="mdash-mini">
          <div class="mdash-mini-label">Owed to suppliers</div>
          <div class="mdash-mini-val" style="color:var(--down);">KES <?= number_format($total_payable, 0) ?></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="mdash-foot-actions">
    <a href="pos.php" class="mdash-btn-ghost"><i class="fa-solid fa-cash-register"></i> Go to POS</a>
  </div>

</div>

<!-- Chart.js v4 — captured into a local reference immediately so older bundled Chart.js loaded later (in foot.php) can't overwrite it -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function(){
  const MC = window.Chart; // freeze reference to Chart.js v4 before anything else can touch window.Chart

  const money = n => 'KES ' + Number(n||0).toLocaleString(undefined,{maximumFractionDigits:0});
  const num   = n => Number(n||0).toLocaleString(undefined,{maximumFractionDigits:0});

  function styleAxis(){
    return {
      grid:{ color:'rgba(5,73,96,.05)', drawTicks:false },
      ticks:{ color:'#5a7a80', font:{ size:10, family:'Inter' } },
      border:{ display:false }
    };
  }

  function changeChip(el, val){
    if(!el) return;
    const cls = val > 0 ? 'up' : (val < 0 ? 'down' : 'flat');
    const icon = val > 0 ? 'fa-arrow-trend-up' : (val < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
    el.className = 'mdash-chip ' + cls;
    el.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + (val > 0 ? '+' : '') + val + '% vs prev. period';
  }

  function sparkline(canvas, data, color){
    if(!canvas) return null;
    const ctx = canvas.getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,60);
    grad.addColorStop(0, color + '55');
    grad.addColorStop(1, color + '00');
    return new MC(ctx, {
      type:'line',
      data:{ labels:data.map((_,i)=>i), datasets:[{ data, borderColor:color, backgroundColor:grad, borderWidth:2, fill:true, tension:.4, pointRadius:0 }]},
      options:{
        responsive:false, animation:{duration:600},
        plugins:{ legend:{display:false}, tooltip:{enabled:false} },
        scales:{ x:{display:false}, y:{display:false} }
      }
    });
  }

  let charts = { revenue:null, payments:null, sparkR:null, sparkP:null, sparkI:null, sparkA:null };
  const PALETTE = ['#025659','#ff7900','#025659','#ffb000','#ff5c72','#ff7900'];

  function renderAll(data){
    // ---- KPI numbers ----
    document.getElementById('kpiRevenue').innerHTML = money(data.revenue);
    document.getElementById('kpiItems').innerHTML = num(data.items);
    document.getElementById('kpiAvg').innerHTML = money(data.avg_sale);
    changeChip(document.getElementById('kpiRevenueChip'), data.revenue_change);
    changeChip(document.getElementById('kpiItemsChip'), data.items_change);
    changeChip(document.getElementById('kpiAvgChip'), data.avg_change);

    const profitEl = document.getElementById('kpiProfit');
    if(profitEl){
      profitEl.innerHTML = money(data.profit) + '<small id="kpiMargin">' + data.margin_pct + '% margin</small>';
      changeChip(document.getElementById('kpiProfitChip'), data.profit_change);
    }

    const trendNote = document.getElementById('mdashTrendNote');
    if(trendNote) trendNote.textContent = data.tx_count + ' transaction' + (data.tx_count === 1 ? '' : 's') + ' this period';

    const periodLabel = document.getElementById('mdashPeriodLabel');
    if(periodLabel) periodLabel.textContent = 'Showing performance from ' + data.start + ' to ' + data.end;

    // ---- sparklines ----
    if(charts.sparkR) charts.sparkR.destroy();
    if(charts.sparkP) charts.sparkP.destroy();
    if(charts.sparkI) charts.sparkI.destroy();
    if(charts.sparkA) charts.sparkA.destroy();
    charts.sparkR = sparkline(document.getElementById('sparkRevenue'), data.series.revenue, '#ff7900');
    charts.sparkP = sparkline(document.getElementById('sparkProfit'), data.series.profit, '#025659');
    charts.sparkI = sparkline(document.getElementById('sparkItems'), data.series.items, '#025659');
    charts.sparkA = sparkline(document.getElementById('sparkAvg'), data.series.avg, '#ffb000');

    // ---- revenue trend ----
    const revEmpty = document.getElementById('chartRevenueEmpty');
    const revCanvas = document.getElementById('chartRevenue');
    const hasRevenue = data.series.revenue.some(v => v > 0);
    revEmpty.style.display = hasRevenue ? 'none' : 'flex';
    revCanvas.style.display = hasRevenue ? 'block' : 'none';
    if(charts.revenue) charts.revenue.destroy();
    if(hasRevenue){
      const ctx = revCanvas.getContext('2d');
      const grad = ctx.createLinearGradient(0,0,0,260);
      grad.addColorStop(0,'rgba(255,121,0,.35)');
      grad.addColorStop(1,'rgba(255,121,0,0)');
      charts.revenue = new MC(ctx,{
        type:'line',
        data:{ labels:data.series.labels, datasets:[{
          label:'Revenue', data:data.series.revenue,
          borderColor:'#ff7900', backgroundColor:grad, borderWidth:2.5,
          fill:true, tension:.35, pointRadius:0, pointHoverRadius:5,
          pointBackgroundColor:'#ff7900', pointBorderColor:'#004549', pointBorderWidth:2
        }]},
        options:{
          responsive:true, maintainAspectRatio:false, animation:{duration:700},
          interaction:{ mode:'index', intersect:false },
          plugins:{
            legend:{display:false},
            tooltip:{
                    backgroundColor:'#004549',
                    borderColor:'#ff7900',
                    borderWidth:1,

                    titleColor:'#ffffff',
                    bodyColor:'#ffffff',

                    padding:12,
                    displayColors:false,

                    callbacks:{
                        label: c => 'Revenue: KES ' + c.parsed.y.toLocaleString()
                    }
                }
          },
          scales:{ x:styleAxis(), y:{ ...styleAxis(), beginAtZero:true, ticks:{...styleAxis().ticks, callback:v=>(v>=1000?(v/1000)+'k':v)} } }
        }
      });
    }

    // ---- payment methods donut ----
    const payEmpty = document.getElementById('chartPaymentsEmpty');
    const payCanvas = document.getElementById('chartPayments');
    const hasPayments = data.payment_data.length > 0;
    payEmpty.style.display = hasPayments ? 'none' : 'flex';
    payCanvas.style.display = hasPayments ? 'block' : 'none';
    if(charts.payments) charts.payments.destroy();

    const legend = document.getElementById('paymentLegend');
    legend.innerHTML = '';
    if(hasPayments){
      charts.payments = new MC(payCanvas.getContext('2d'),{
        type:'doughnut',
        data:{
          labels:data.payment_data.map(d=>d.payment_type),
          datasets:[{ data:data.payment_data.map(d=>d.total), backgroundColor:PALETTE, borderColor:'#004549', borderWidth:3, hoverOffset:6 }]
        },
        options:{
          responsive:true, maintainAspectRatio:false, cutout:'68%', animation:{duration:700},
          plugins:{
            legend:{display:false},
            tooltip:{
                backgroundColor:'#004549',
                borderColor:'#ff7900',
                borderWidth:1,

                titleColor:'#ffffff',
                bodyColor:'#ffffff',

                padding:12,

                callbacks:{
                    label: c => c.label + ': KES ' + c.parsed.toLocaleString()
                }
            }
          }
        }
      });
      const total = data.payment_data.reduce((s,d)=>s+Number(d.total),0) || 1;
      data.payment_data.forEach((d,i)=>{
        const pct = Math.round((d.total/total)*100);
        legend.innerHTML += '<div class="mdash-legend-item"><span class="mdash-legend-left"><span class="mdash-legend-dot" style="background:'+PALETTE[i%PALETTE.length]+'"></span>'+d.payment_type+'</span><span class="mdash-legend-val">'+pct+'%</span></div>';
      });
    }
  }

  // initial paint using server-rendered data (no network round trip)
  const initialData = <?= json_encode($chartPayload) ?>;
  renderAll(initialData);

  // ---- filter controls ----
  const startInput = document.getElementById('mdashStart');
  const endInput = document.getElementById('mdashEnd');
  const applyBtn = document.getElementById('mdashApply');
  const pills = document.querySelectorAll('#mdashQuickRanges .mdash-pill');

  function fetchRange(start, end){
    applyBtn.disabled = true;
    applyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading';
    fetch('ajax/sales_analytic_data.php?start=' + start + '&end=' + end)
      .then(r => r.json())
      .then(data => {
        if(data.error){ throw new Error(data.error); }
        renderAll(data);
      })
      .catch(() => { alert('Could not load analytics for that range. Please try again.'); })
      .finally(() => {
        applyBtn.disabled = false;
        applyBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass-chart"></i> Apply';
      });
  }

  applyBtn.addEventListener('click', () => {
    const s = startInput.value, e = endInput.value;
    if(!s || !e){ return; }
    if(s > e){ alert('Start date must be before the end date.'); return; }
    pills.forEach(p => p.classList.remove('active'));
    fetchRange(s, e);
  });

  pills.forEach(btn => {
    btn.addEventListener('click', () => {
      pills.forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      const days = parseInt(btn.dataset.days, 10);
      const end = new Date();
      const start = new Date();
      start.setDate(end.getDate() - days);
      const fmt = d => d.toISOString().slice(0,10);
      startInput.value = fmt(start);
      endInput.value = fmt(end);
      fetchRange(fmt(start), fmt(end));
    });
  });
})();
</script>
<?php
require 'foot.php';