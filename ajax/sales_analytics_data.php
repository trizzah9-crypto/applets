<?php
/**
 * ajax/sales_analytics_data.php
 * Returns JSON analytics for the dashboard's date-range filter.
 * Mirrors the same queries used for the initial server-rendered paint in dashboard.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['business_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No business selected']);
    exit;
}

require '../dbconnect.php'; // provides $conn (PDO / SQLite)

$business_id = (int)$_SESSION['business_id'];

/* ---------- validate inputs ---------- */
$start = $_GET['start'] ?? date('Y-m-d', strtotime('-29 days'));
$end   = $_GET['end'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = date('Y-m-d', strtotime('-29 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = date('Y-m-d');
}
if ($start > $end) {
    [$start, $end] = [$end, $start];
}

// cap range to 366 days to avoid runaway loops
if ((strtotime($end) - strtotime($start)) / 86400 > 366) {
    $start = date('Y-m-d', strtotime($end . ' -366 days'));
}

/* ---------- helpers (same logic as dashboard.php) ---------- */

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

try {
    $current = mdash_period_totals($conn, $business_id, $start, $end);

    $daySpan    = (int)round((strtotime($end) - strtotime($start)) / 86400) + 1;
    $prev_end   = date('Y-m-d', strtotime($start . ' -1 day'));
    $prev_start = date('Y-m-d', strtotime($prev_end . ' -' . ($daySpan - 1) . ' days'));
    $previous   = mdash_period_totals($conn, $business_id, $prev_start, $prev_end);

    $series = mdash_period_series($conn, $business_id, $start, $end);

    $stmt = $conn->prepare("
        SELECT COALESCE(NULLIF(payment_type,''), 'unspecified') AS payment_type, COALESCE(SUM(total_amount),0) AS total
        FROM sales
        WHERE business_id = :bid AND DATE(created_at) BETWEEN :start AND :end
        GROUP BY payment_type
        ORDER BY total DESC
    ");
    $stmt->execute(['bid' => $business_id, 'start' => $start, 'end' => $end]);
    $payment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $stmt->execute(['bid' => $business_id, 'start' => $start, 'end' => $end]);
    $best_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $revenue = (float)$current['revenue'];
    $profit  = (float)$current['profit'];

    echo json_encode([
        'revenue'        => $revenue,
        'profit'         => $profit,
        'items'          => $current['items'],
        'avg_sale'       => $current['avg_sale'],
        'tx_count'       => $current['tx_count'],
        'revenue_change' => mdash_pct($revenue, (float)$previous['revenue']),
        'profit_change'  => mdash_pct($profit, (float)$previous['profit']),
        'items_change'   => mdash_pct($current['items'], (float)$previous['items']),
        'avg_change'     => mdash_pct($current['avg_sale'], (float)$previous['avg_sale']),
        'margin_pct'     => $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0,
        'series'         => $series,
        'payment_data'   => $payment_data,
        'best_products'  => $best_products,
        'start'          => $start,
        'end'            => $end,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load analytics data']);
}
