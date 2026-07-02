<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require("db.php"); // This file should create a PDO $conn to SQLite

// Check if user is logged in (adjust session key as needed)
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if business is selected, except on business_select.php
if (!isset($_SESSION['business_id']) && basename($_SERVER['PHP_SELF']) !== 'business_select.php') {
    header("Location: business_select.php");
    exit;
}

// Function to check privileges easily
function hasPrivilege($priv) {
    return isset($_SESSION['privileges']) && in_array($priv, $_SESSION['privileges']);
}

//require("check_subscription.php");

$businessName = "MAMBA POS MADE EASY"; // Default fallback
if (isset($_SESSION['business_id'])) {
    $businessId = (int)$_SESSION['business_id'];

    try {
        $stmt = $conn->prepare(
            "SELECT business_name 
             FROM businesses 
             WHERE id = :id 
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $businessId
        ]);

        $fetchedName = $stmt->fetchColumn();

        if (!empty($fetchedName)) {
            $businessName = $fetchedName;
        }

    } catch (PDOException $e) {
        // error_log($e->getMessage()); // optional
    }
}


// Prepare notifications if you have any logic (currently empty)
$notifications = [];
$notification_count = count($notifications);

function canIt(string $permission): bool
{
    // owner overrides everything
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner') {
        return true;
    }

    if (empty($_SESSION['permissions'])) {
        return false;
    }

    return in_array($permission, $_SESSION['permissions'], true);
}

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>B2B</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="./Images/logo.png" />

    <!-- CSS -->
    <link rel="stylesheet" href="vendor/owl-carousel/css/owl.carousel.min.css" />
    <link rel="stylesheet" href="vendor/owl-carousel/css/owl.theme.default.min.css" />
    <link href="vendor/jqvmap/css/jqvmap.min.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="./vendor/datatables/css/jquery.dataTables.min.css" rel="stylesheet" />

    <style>
        .b2b_head {
            background: rgba(5, 73, 96, 0.9);
            padding: 15px;
            color: #ff7900;
        }

        .quixnav {
            background: rgba(5, 73, 96, 0.9);
            margin: 0;
        }
    </style>
</head>

<body>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <!-- Main wrapper start -->
    <div id="main-wrapper">

        <!-- Nav header start -->
        <div class="nav-header">
            <a href="dashboard.php" class="brand-logo" style="background: #d3f0f5ff;;">
                <img class="brand-title" src="./Images/logo.png" style="margin: auto; min-width: 150px;" alt="b2b" />
            </a>

            <div class="nav-control">
                <div class="hamburger">
                    <span class="line"></span><span class="line"></span><span class="line"></span>
                </div>
            </div>
        </div>
        <!-- Nav header end -->

        <!-- Header start -->
        <div class="header">
            <div class="header-content">
                <nav class="navbar navbar-expand">
                    <div class="collapse navbar-collapse justify-content-between">
                        <h3 class="b2b_head"><?= htmlspecialchars($businessName) ?></h3>

                        <div class="header-left">
                            <div class="search_bar dropdown">
                                <span class="search_icon p-3 c-pointer" data-toggle="dropdown">
                                    <div class="dropdown-menu p-0 m-0"></div>
                                </span>
                            </div>
                        </div>

                        <ul class="navbar-nav header-right">
                            <!-- Notifications -->
                            <li class="nav-item dropdown notification_dropdown">
                                <a class="nav-link" href="#" role="button" data-toggle="dropdown">
                                    <i class="mdi mdi-bell"></i>
                                    <?php if ($notification_count > 0) : ?>
                                        <div class="pulse-css"></div>
                                    <?php endif; ?>
                                </a>

                                <div class="dropdown-menu dropdown-menu-right">
                                    <ul class="list-unstyled">
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
                                                    <p>No new notifications 🎉</p>
                                                </div>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                    <a class="all-notification" href="#">See all notifications <i class="ti-arrow-right"></i></a>
                                </div>
                            </li>

                            <!-- Profile dropdown -->
                            <li class="nav-item dropdown header-profile">
                                <a class="nav-link" href="#" role="button" data-toggle="dropdown">
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
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
        <!-- Header end -->

        <!-- Sidebar start -->
        <div class="quixnav">
            <div class="quixnav-scroll">
                <ul class="metismenu" id="menu">
                    <li class="nav-label first" style="color: white;">Main Menu</li>

                    <li>
                        <a href="dashboard.php" aria-expanded="false"><i class="icon icon-single-04"></i><span class="nav-text">Dashboard</span></a>
                    </li>

                    <li class="nav-label first" style="color: white;">Actions</li>

                    <li class="activea">
                        <a href="pos.php" aria-expanded="false"><i class="fas fa-cash-register"></i><span class="nav-text">Make Sales</span></a>
                    </li>

                    <li class="activeb">
                        <a href="products.php" aria-expanded="false"><i class="fas fa-clipboard-list"></i><span class="nav-text">Inventory</span></a>
                    </li>

                    <li class="activem">
                        <a href="business_select.php" aria-expanded="false"><i class="fas fa-store"></i><span class="nav-text">Select Business</span></a>
                    </li>


                    <?php if(canIt("")){ ?>

                    <li class="activem">
                        <a href="create_business.html" aria-expanded="false"><i class="fas fa-plus-circle"></i><span class="nav-text">Create New Business</span></a>
                    </li>


                    <?php } ?>

                    <li class="activem">
                        <a href="sales_report.php" aria-expanded="false"><i class="fas fa-sign-in-alt"></i><span class="nav-text">Sale Receipts</span></a>
                    </li>

                    <!-- Add more items as needed -->
                    <li class="nav-label first" style="color: white;">Support</li>

                    <li><a href="payment.php" aria-expanded="false"><i class="fas fa-money-bill-wave"></i><span class="nav-text">Payment</span></a></li>
                    
                    <?php if(canIt("")){ ?>
                    <li><a href="settings.php" aria-expanded="false"><i class="fas fa-cogs"></i><span class="nav-text">Settings</span></a></li>
                    <li><a href="create_user.php" aria-expanded="false"><i class="fas fa-user-plus"></i><span class="nav-text">Create New User</span></a></li>
                    <li><a href="users.php" aria-expanded="false"><i class="fas fa-user"></i><span class="nav-text">Users</span></a></li>
                   
                      <?php } ?> 
                   
                    <li><a href="customers.php" aria-expanded="false"><i class="fas fa-address-book"></i><span class="nav-text">Customer Management</span></a></li>

                    <li>
                        <a href="logout.php" aria-expanded="false"><i class="fas fa-sign-out-alt"></i><span class="nav-text">Log Out</span></a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- Sidebar end -->

        <!-- Content body start -->
        <div class="content-body">
            <div class="container-fluid">
