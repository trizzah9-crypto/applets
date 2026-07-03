<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnect.php");

if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['business_id']) && basename($_SERVER['PHP_SELF']) !== 'business_select.php') {
    header("Location: business_select.php");
    exit;
}

function hasPrivilege($priv) {
    return isset($_SESSION['privileges']) && in_array($priv, $_SESSION['privileges']);
}

function canIt(string $permission): bool
{
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner') {
        return true;
    }

    if (empty($_SESSION['permissions'])) {
        return false;
    }

    return in_array($permission, $_SESSION['permissions'], true);
}

require("check_subscription.php");

$businessName = "MAMBA POS MADE EASY";

if (isset($_SESSION['business_id'])) {
    $businessId = intval($_SESSION['business_id']);
    $stmt = $conn->prepare("SELECT business_name FROM businesses WHERE id = ?");
    if ($stmt) {
        $stmt->execute([$businessId]);
        $stmt->bindColumn(1, $fetchedName);
        if ($stmt->fetch(PDO::FETCH_BOUND)) {
            if (!empty($fetchedName)) {
                $businessName = $fetchedName;
            }
        }
        $stmt->closeCursor();
    }
}

$notifications = [];
$notification_count = count($notifications);
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>B2B</title>

 
<link rel="icon" type="image/png" sizes="192x192" href="./Images/logo.png" />

<link rel="stylesheet" href="vendor/owl-carousel/css/owl.carousel.min.css" />
<link rel="stylesheet" href="vendor/owl-carousel/css/owl.theme.default.min.css" />
<link href="vendor/jqvmap/css/jqvmap.min.css" rel="stylesheet" />
<link href="css/style.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<link href="./vendor/datatables/css/jquery.dataTables.min.css" rel="stylesheet" />

<style>
    :root{
        --primary:#054960;
        --primary-light:#0c617f;
        --accent:#ff7900;
        --glass:rgba(255,255,255,.82);
        --glass-dark:rgba(5,73,96,.96);
        --border:rgba(255,255,255,.10);
        --shadow:0 15px 45px rgba(0,0,0,.12);
        --radius:22px;
        --sidebar-width:260px;
    }

    *{
        box-sizing:border-box;
    }

    body{
        background:
            radial-gradient(circle at top left,#0d617e22,transparent 40%),
            radial-gradient(circle at bottom right,#ff790015,transparent 35%),
            #f5f9fc;
    }

    .app-shell{
        min-height:100vh;
        display:flex;
        align-items:stretch;
    }

    .content-body{
        overflow-x:hidden;
    }

    .container-fluid{
        width:100%;
        max-width:100%;
        overflow-x:hidden;
    }

    .sidebar{
        width:var(--sidebar-width);
        background:linear-gradient(180deg, rgba(5,73,96,.98), rgba(3,52,69,.98));
        border-right:1px solid rgba(255,255,255,.06);
        padding:1.25rem;
        position:sticky;
        top:0;
        height:100vh;
        z-index:1150;
        overflow-y:auto;
        overflow-x:hidden;
        padding-bottom:2rem;
        flex:0 0 var(--sidebar-width);
    }

    .brand-box{
        display:flex;
        align-items:center;
        gap:.85rem;
        padding:1rem 0 1rem;
        margin-bottom:1rem;
        border-bottom:1px solid rgba(255,255,255,.08);
    }

    .brand-box img{
        width:58px;
        height:auto;
        display:block;
        flex:0 0 auto;
    }

    .brand-title{
        font-weight:800;
        color:#fff;
        line-height:1.1;
        letter-spacing:.3px;
        font-size:1rem;
    }

    .brand-subtitle{
        color:rgba(255,255,255,.68);
        letter-spacing:1px;
        font-size:.74rem;
        margin-top:.2rem;
    }

    .sidebar-menu .nav-link{
        color:#e5e7eb;
        padding:10px 14px;
        border-radius:10px;
        margin-bottom:4px;
        transition:.2s ease;
        display:flex;
        align-items:center;
        gap:.55rem;
    }

    .sidebar-menu .nav-link:hover,
    .sidebar-menu .nav-link.active{
        background:#ff9f45;
        color:#fff;
    }

    .sidebar-menu .nav-link i{
        width:18px;
        text-align:center;
        flex:0 0 18px;
    }

    .menu-group-title{
        font-size:12px;
        font-weight:700;
        letter-spacing:.08em;
        color:#9ca3af;
        margin:16px 0 8px;
        padding-left:6px;
    }

    .content{
        flex:1;
        min-width:0;
        padding: 20px;
    }

    .topbar{
        background:rgba(255,255,255,.78);
        backdrop-filter:blur(22px);
        border:1px solid rgba(255,255,255,.5);
        border-radius:24px;
        box-shadow:0 10px 35px rgba(0,0,0,.08);
        padding:1rem 1.25rem;
        margin-bottom:1.25rem;
    }

    .topbar-header{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:.75rem;
    }

    .b2b_head{
        background:linear-gradient(135deg, var(--primary), var(--primary-light));
        color:#fff;
        padding:14px 25px;
        border-radius:16px;
        font-size:18px;
        font-weight:700;
        letter-spacing:.5px;
        box-shadow:0 8px 20px rgba(5,73,96,.2);
        margin:0;
        max-width:100%;
    }

    .header-profile .nav-link{
        background:rgba(5,73,96,.08);
        border-radius:16px;
        padding:12px 18px !important;
        transition:.3s;
        color:#1f2937;
        white-space:nowrap;
    }

    .header-profile .nav-link:hover{
        background:rgba(255,121,0,.15);
        transform:translateY(-2px);
    }

    .notification_dropdown .nav-link{
        width:50px;
        height:50px;
        border-radius:50%;
        background:white;
        display:flex;
        align-items:center;
        justify-content:center;
        box-shadow:0 8px 20px rgba(0,0,0,.08);
        position:relative;
    }

    .notification_dropdown i{
        color:var(--primary);
        font-size:20px;
    }

    .dropdown-menu{
        border:none;
        border-radius:18px;
        box-shadow:0 20px 40px rgba(0,0,0,.12);
        backdrop-filter:blur(20px);
        overflow:hidden;
        z-index:2000;
    }

    .dropdown-item{
        padding:14px 18px;
        transition:.3s;
    }

    .dropdown-item:hover{
        background:rgba(255,121,0,.10);
    }

    .content-body{
        background:transparent;
    }

    .container-fluid{
        padding-top:25px;
        padding: 0;
    }

    .overlay{
        position:fixed;
        inset:0;
        background:rgba(0,0,0,.5);
        display:none;
        z-index:1100;
    }

    .overlay.active{
        display:block;
    }

    #menuToggle{
        position:fixed;
        top:15px;
        left:15px;
        z-index:1300;
        border-radius:12px;
        padding:8px 12px;
        backdrop-filter:blur(10px);
        transition:all .3s ease;
        display:none;
        background-color: rgba(5,73,96,.96);
    }

    #menuToggle:hover{
        background:#ff9f45;
        color:#fff;
        transform:translateY(-2px);
    }

    @media (max-width: 991px){
        .app-shell{
            display:block;
        }

        .sidebar{
            position:fixed;
            top:0;
            left:0;
            height:100vh;
            width:260px;
            transform:translateX(-100%);
            transition:transform .3s ease;
            box-shadow:10px 0 40px rgba(0,0,0,.18);
        }

        .sidebar.active{
            transform:translateX(0);
        }

        .content{
            width:100%;
            padding:1rem;
            padding-top:76px;
        }

        #menuToggle{
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }

        .topbar{
            margin-bottom:1rem;
            border-radius:20px;
        }
    }

    @media (max-width: 768px){
        .content{
            padding:1rem;
            padding-top:78px;
        }
  
           .content{
        padding-top: 65px;
    }

     #menuToggle{
        min-height: 50px;
        min-width: 14%;
     }


    .topbar{
        margin-bottom: .5rem;
        padding: .45rem .65rem;
        border-radius: 14px;
        min-height: 50px;
        width: calc(100% - 62px);
        margin-left: 62px;
    }

    .topbar-header{
        align-items: center;
        gap: .3rem;
    }

    .b2b_head{
        font-size: 10px;
        padding: 6px 10px;
        border-radius: 10px;
        letter-spacing: .2px;
        margin: 0;
    }

    .header-profile .nav-link{
        padding: 6px 10px !important;
        font-size: 11px;
        border-radius: 10px;
        min-height: 34px;
    }

    .header-profile .nav-link i{
        font-size: 14px;
        margin-right: 4px;
    }

    .notification_dropdown .nav-link{
        width: 34px;
        height: 34px;
        min-width: 34px;
    }
    .content{
        padding-top: 15px !important;
    }

    .content-body{
        margin-top: -100px !important;
    }

    .notification_dropdown i{
        font-size: 14px;
    }

    .dropdown-menu{
        min-width: 220px;
        font-size: 12px;
    }

        
    }
</style>


</head>

<body>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>


<div class="overlay" id="overlay"></div>

<button class="btn btn-outline-light d-lg-none" id="menuToggle" type="button" aria-label="Open sidebar">
    <i class="fas fa-bars text-white"></i>
</button>

<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" style="text-decoration:none;">
            <div class="brand-box">
                <img src="./Images/logo.png" alt="logo">
                <div>
                    <div class="brand-title">MAMBA POS</div>
                    <div class="brand-subtitle"><?= htmlspecialchars($businessName) ?></div>
                </div>
            </div>
        </a>

        <nav class="nav flex-column sidebar-menu">
            <div class="menu-group-title">OPERATIONS</div>

            <a class="nav-link <?= $current_page=='dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="fas fa-chart-line"></i><span>Dashboard</span>
            </a>
            <a class="nav-link <?= $current_page=='pos.php' ? 'active' : '' ?>" href="pos.php">
                <i class="fas fa-cash-register"></i><span>Make Sales</span>
            </a>
            <a class="nav-link <?= $current_page=='pending_orders.php' ? 'active' : '' ?>" href="pending_orders.php">
                <i class="fas fa-hourglass-half"></i><span>Pending Orders</span>
            </a>
            <a class="nav-link <?= $current_page=='sales_report.php' ? 'active' : '' ?>" href="sales_report.php">
                <i class="fas fa-file-invoice"></i><span>Sale Receipts</span>
            </a>

            <div class="menu-group-title">MANAGEMENTS</div>

            <a class="nav-link <?= $current_page=='products.php' ? 'active' : '' ?>" href="products.php">
                <i class="fas fa-clipboard-list"></i><span>Inventory</span>
            </a>

            <?php if (canIt("")) { ?>
                <a class="nav-link <?= $current_page=='create_user.php' ? 'active' : '' ?>" href="create_user.php">
                    <i class="fas fa-user-plus"></i><span>Create New User</span>
                </a>
                <a class="nav-link <?= $current_page=='users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="fas fa-users"></i><span>Users</span>
                </a>
            <?php } ?>

            <a class="nav-link <?= $current_page=='customers.php' ? 'active' : '' ?>" href="customers.php">
                <i class="fas fa-address-book"></i><span>Customer Management</span>
            </a>

            <div class="menu-group-title">ADMINISTRATION</div>

            <a class="nav-link <?= $current_page=='business_select.php' ? 'active' : '' ?>" href="business_select.php">
                <i class="fas fa-store"></i><span>Select Business</span>
            </a>

            <?php if (canIt("")) { ?>
                <a class="nav-link <?= $current_page=='create_business.php' ? 'active' : '' ?>" href="create_business.php">
                    <i class="fas fa-plus-circle"></i><span>Create New Business</span>
                </a>
            <?php } ?>

            <a class="nav-link <?= $current_page=='payment.php' ? 'active' : '' ?>" href="payment.php">
                <i class="fas fa-money-bill-wave"></i><span>Payment</span>
            </a>

            <?php if (canIt("")) { ?>
                <a class="nav-link <?= $current_page=='settings.php' ? 'active' : '' ?>" href="settings.php">
                    <i class="fas fa-cogs"></i><span>Settings</span>
                </a>
            <?php } ?>

            <a class="nav-link <?= $current_page=='support.php' ? 'active' : '' ?>" href="support.php">
                <i class="fas fa-question-circle"></i><span>Help?</span>
            </a>

            <a class="nav-link text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Log Out</span>
            </a>
        </nav>
    </aside>

    <main class="content" style="">
        <div class="topbar" style="margin-bottom: 0;">
            <div class="topbar-header">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="topbar-text">
                        <h3 class="b2b_head"><?= htmlspecialchars($businessName) ?></h3>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <div class="dropdown notification_dropdown">
                        <a class="nav-link" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="mdi mdi-bell"></i>
                            <?php if ($notification_count > 0) : ?>
                                <div class="pulse-css"></div>
                            <?php endif; ?>
                        </a>

                        <div class="dropdown-menu dropdown-menu-right">
                            <ul class="list-unstyled mb-0">
                                <?php if ($notification_count > 0) : ?>
                                    <?php foreach ($notifications as $n) : ?>
                                        <a href="events.php?event_id=<?= htmlspecialchars($n['event_id']) ?>">
                                            <li class="media dropdown-item">
                                                <span class="danger"><i class="ti-alert"></i></span>
                                                <div class="media-body">
                                                    <p>
                                                        Event <strong><?= htmlspecialchars($n['event_name']) ?></strong>
                                                        still has <strong><?= (int)$n['still_out'] ?></strong> items not returned.
                                                    </p>
                                                </div>
                                                <span class="notify-time"><?= (int)$n['days_ago'] ?> days ago</span>
                                            </li>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <li class="media dropdown-item">
                                        <span class="success"><i class="ti-check"></i></span>
                                        <div class="media-body">
                                            <p>No new notifications</p>
                                        </div>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <a class="all-notification" href="#">See all notifications <i class="ti-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="dropdown header-profile">
                        <a class="nav-link" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="mdi mdi-account"></i>
                            <?= htmlspecialchars($_SESSION['admin'] ?? $_SESSION['user_name'] ?? 'User') ?>
                        </a>

                        <div class="dropdown-menu dropdown-menu-right">
                            <?php if (hasPrivilege("users")) { ?>
                                <a href="create_admin.php" class="dropdown-item">
                                    <i class="fas fa-user-plus"></i>
                                    <span class="ml-2">Add Users</span>
                                </a>
                                <a href="users.php" class="dropdown-item">
                                    <i class="fas fa-users"></i>
                                    <span class="ml-2">Users</span>
                                </a>
                            <?php } ?>
                            <a href="logout.php" class="dropdown-item">
                                <i class="icon-key"></i>
                                <span class="ml-2">Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-body" style=" margin-left: 0; margin-top: -95px;">
            <div class="container-fluid">
 