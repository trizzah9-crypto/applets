<?php
require("../db.php");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');

$business_id = (int)($_SESSION['business_id'] ?? 0);

if (!$business_id) {
    echo '<div class="alert alert-danger">No business selected.</div>';
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

function phoneToWa($phone) {
    return normalizePhoneDigits($phone);
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

$q = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$status = trim($_GET['status'] ?? '');
$balance = trim($_GET['balance'] ?? '');
$pointsMin = trim($_GET['points_min'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$where = ["c.business_id = :business_id"];
$params = [':business_id' => $business_id];

if ($q !== '') {
    $where[] = "(c.name LIKE :q OR c.phone LIKE :q OR c.email LIKE :q OR c.company_name LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($type !== '') {
    $where[] = "LOWER(COALESCE(c.customer_type,'')) = LOWER(:type)";
    $params[':type'] = $type;
}
if ($status !== '') {
    $where[] = "LOWER(COALESCE(c.status,'')) = LOWER(:status)";
    $params[':status'] = $status;
}
if ($pointsMin !== '' && is_numeric($pointsMin)) {
    $where[] = "COALESCE(c.loyalty_points,0) >= :points_min";
    $params[':points_min'] = (int)$pointsMin;
}
if ($from !== '') {
    $where[] = "date(c.created_at) >= date(:from_date)";
    $params[':from_date'] = $from;
}
if ($to !== '') {
    $where[] = "date(c.created_at) <= date(:to_date)";
    $params[':to_date'] = $to;
}

$sql = "
    SELECT c.*,
           COALESCE((SELECT SUM(amount) FROM customer_account_transactions t WHERE t.customer_id = c.id),0) AS balance,
           COALESCE((SELECT MAX(created_at) FROM customer_account_transactions t WHERE t.customer_id = c.id), c.created_at) AS last_activity,
           COALESCE((SELECT COUNT(*) FROM customer_account_transactions t WHERE t.customer_id = c.id),0) AS tx_count
    FROM customers c
    WHERE " . implode(" AND ", $where) . "
    ORDER BY datetime(c.created_at) DESC, c.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$customers) {
    echo '<div class="alert alert-info m-3">No customers found.</div>';
    exit;
}

$filtered = [];
foreach ($customers as $c) {
    $balanceValue = (float)($c['balance'] ?? 0);

    if ($balance === 'with_balance' && $balanceValue <= 0) continue;
    if ($balance === 'no_balance' && $balanceValue > 0) continue;

    $filtered[] = $c;
}

if (!$filtered) {
    echo '<div class="alert alert-info m-3">No customers matched the selected filters.</div>';
    exit;
}

echo '<div class="table-responsive crm-table-wrap">';
echo '<table class="table crm-table align-middle">
        <thead>
            <tr>
                <th>Profile Avatar</th>
                <th>Customer Name</th>
                <th>Phone Number</th>
                <th>Customer Type</th>
                <th>Credit Balance</th>
                <th>Credit Limit</th>
                <th>Loyalty Points</th>
                <th>Company Name</th>
                <th>Last Activity</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>';

foreach ($filtered as $c) {
    $id = (int)$c['id'];
    $phoneDisplay = h($c['phone'] ?: '-');
    $phoneTel = phoneToWa($c['phone']);
    $balanceValue = (float)$c['balance'];
    $creditLimit = (float)($c['credit_limit'] ?? 0);
    $lastActivity = !empty($c['last_activity']) ? date('d M Y, H:i', strtotime($c['last_activity'])) : '-';
    $typeClass = badgeCustomerType($c['customer_type'] ?? '');
    $statusClass = badgeStatus($c['status'] ?? '');
    $initials = strtoupper(substr(trim($c['name'] ?? 'C'), 0, 1) . substr(trim($c['name'] ?? ''), 1, 1));
    $photo = trim((string)($c['profile_photo'] ?? ''));

    echo '<tr class="customer-row" data-customer-id="'.$id.'">';

    echo '<td>
            <div class="d-flex align-items-center gap-3">
                <div class="customer-avatar">';
    if ($photo !== '') {
        echo '<img src="'.h($photo).'" alt="'.h($c['name']).'">';
    } else {
        echo h($initials ?: 'CU');
    }
    echo    '</div>
                <div>
                    <div class="customer-name">'.h($c['name']).'</div>
                    <div class="muted-small">ID: '.$id.'</div>
                </div>
            </div>
          </td>';

    echo '<td>
            <div class="fw-bold">'.h($c['name']).'</div>
            <div class="muted-small">'.h($c['email'] ?? '').'</div>
          </td>';

    echo '<td>
            <div class="fw-semibold mb-2">'.$phoneDisplay.'</div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="tel:'.$phoneTel.'" class="btn btn-sm btn-success btn-action-mini" title="Call">
                    <i class="bi bi-telephone-fill"></i>
                </a>
                <a href="https://wa.me/'.$phoneTel.'" target="_blank" class="btn btn-sm btn-success btn-action-mini" title="WhatsApp">
                    <i class="bi bi-whatsapp"></i>
                </a>
                <a href="sms:'.$phoneTel.'" class="btn btn-sm btn-primary btn-action-mini" title="SMS">
                    <i class="bi bi-chat-dots-fill"></i>
                </a>
            </div>
          </td>';

    echo '<td><span class="badge soft-pill '.$typeClass.'">'.h($c['customer_type'] ?: 'Walk-in').'</span></td>';

    echo '<td class="'.($balanceValue > 0 ? 'text-danger' : 'text-success').' fw-bold">
            KES '.number_format($balanceValue, 2).'
          </td>';

    echo '<td>KES '.number_format($creditLimit, 2).'</td>';
    echo '<td>'.number_format((int)($c['loyalty_points'] ?? 0)).'</td>';
    echo '<td>'.h($c['company_name'] ?? '-').'</td>';
    echo '<td>'.h($lastActivity).'</td>';
    echo '<td><span class="badge soft-pill '.$statusClass.'">'.h($c['status'] ?: 'Inactive').'</span></td>';

    echo '<td>
            <div class="dropdown">
                <button class="btn btn-light btn-sm rounded-4 dropdown-toggle" data-bs-toggle="dropdown">
                    Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a href="#" class="dropdown-item viewCustomer" data-id="'.$id.'"><i class="bi bi-person-lines-fill"></i>View Statement</a></li>
                    <li><a href="#" class="dropdown-item printCustomer" data-id="'.$id.'"><i class="bi bi-printer"></i>Print Statement</a></li>
                    <li><a href="#" class="dropdown-item recordPayment" data-id="'.$id.'"><i class="bi bi-cash-coin"></i>Record Payment</a></li>
                    <li><a href="#" class="dropdown-item addCredit" data-id="'.$id.'"><i class="bi bi-plus-circle"></i>Add Credit</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a href="#" class="dropdown-item editCustomer" data-id="'.$id.'"><i class="bi bi-pencil-square"></i>Edit Customer</a></li>
                    <li><a href="#" class="dropdown-item text-danger deleteCustomer" data-id="'.$id.'"><i class="bi bi-trash3"></i>Delete Customer</a></li>
                </ul>
            </div>
          </td>';

    echo '</tr>';
}

echo '</tbody></table></div>';