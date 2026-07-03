<?php
require("head.php");
require_once("db.php"); // optional, keep if your system needs DB/session

$businessName = $_SESSION['business_name'] ?? 'Your Business';
$userName     = $_SESSION['user_name'] ?? ($_SESSION['admin'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Support & Help</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

    :root{
        --primary:#054960;
        --primary-light:#0a5d78;
        --primary-accent:#0f7c9d;

        --orange:#ff7900;
        --orange-light:#ff9d3f;

        --bg:#f4f8fb;
        --glass:rgba(255,255,255,.72);

        --radius-lg:18px;
        --radius-xl:24px;
        --radius-2xl:28px;

        --shadow-sm:0 10px 30px rgba(5,73,96,.08);
        --shadow-md:0 20px 45px rgba(5,73,96,.12);
        --shadow-lg:0 30px 80px rgba(5,73,96,.18);

        --transition:.3s ease;
    }

    *{
        box-sizing:border-box;
    }

    body{
        font-family: "Segoe UI", sans-serif;
        min-height:100vh;

        background:
            radial-gradient(circle at top left, rgba(15,124,157,.08), transparent 30%),
            radial-gradient(circle at top right, rgba(255,157,63,.08), transparent 30%),
            linear-gradient(180deg,#f8fbfd,#f4f8fb);

        color:#334155;
    }

    .container{
        max-width:1200px;
        margin:auto;
        padding:30px 20px;
    }

    .hero{
        background:linear-gradient(
            135deg,
            var(--primary),
            var(--primary-light),
            var(--primary-accent)
        );

        color:white;

        padding:45px;
        border-radius:var(--radius-2xl);

        box-shadow:var(--shadow-lg);

        margin-bottom:35px;
    }

    .hero h1{
        font-size:2.4rem;
        font-weight:800;
        margin-bottom:10px;
        color: #ff9d3f;;
    }

    .hero p{
        font-size:1.05rem;
        opacity:.92;
        margin:0;
    }

    .grid{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(350px,1fr));
        gap:25px;
    }

    .glass-card{
        background:var(--glass);

        backdrop-filter:blur(18px);
        -webkit-backdrop-filter:blur(18px);

        border:1px solid rgba(255,255,255,.5);

        border-radius:var(--radius-xl);

        padding:30px;

        box-shadow:var(--shadow-md);

        transition:var(--transition);
    }

    .glass-card:hover{
        transform:translateY(-5px);
        box-shadow:var(--shadow-lg);
    }

    .card-title{
        display:flex;
        align-items:center;
        gap:12px;

        margin-bottom:20px;

        font-size:1.3rem;
        font-weight:800;

        color:var(--primary);
    }

    .icon-box{
        width:52px;
        height:52px;

        display:flex;
        align-items:center;
        justify-content:center;

        border-radius:18px;

        background:linear-gradient(
            135deg,
            var(--primary-accent),
            var(--primary)
        );

        color:white;
        font-size:1.4rem;

        box-shadow:0 12px 30px rgba(5,73,96,.25);
    }

    ul{
        list-style:none;
        padding:0;
        margin:0;
    }

    ul li{
        padding:12px 0;
        border-bottom:1px solid rgba(5,73,96,.08);

        position:relative;
        padding-left:28px;
    }

    ul li:last-child{
        border-bottom:none;
    }

    ul li::before{
        content:"";
        width:10px;
        height:10px;

        border-radius:50%;

        background:var(--orange);

        position:absolute;
        left:0;
        top:18px;
    }

    
    .support-card{
        margin-top:30px;

        background:linear-gradient(
            135deg,
            rgba(255,121,0,.08),
            rgba(255,157,63,.15)
        );

        border-left:5px solid var(--orange);

        border-radius:var(--radius-xl);

        padding:30px;

        box-shadow:var(--shadow-sm);
    }

    .support-card h3{
        color:var(--orange);
        font-weight:800;
        margin-bottom:20px;
    }

    .contact-item{
        display:flex;
        align-items:center;
        gap:12px;
        margin-bottom:15px;
    }

    .contact-icon{
        width:42px;
        height:42px;

        display:flex;
        align-items:center;
        justify-content:center;

        border-radius:14px;

        background:white;

        box-shadow:var(--shadow-sm);

        font-size:18px;
    }

    .footer-text{
        margin-top:40px;
        text-align:center;
        color:#64748b;
        font-size:.95rem;
    }

    ::-webkit-scrollbar{
        width:10px;
    }

    ::-webkit-scrollbar-thumb{
        background:linear-gradient(
            var(--primary),
            var(--primary-accent)
        );

        border-radius:20px;
    }

    ::-webkit-scrollbar-track{
        background:#edf2f7;
    }

    @media(max-width:768px){

        .hero{
            padding:30px;
        }

        .hero h1{
            font-size:1.8rem;
        }

        .grid{
            grid-template-columns:1fr;
        }

    }

</style>
</head>

<body>
<div class="container">

    <div class="hero">
        <h1>Support & Help Center</h1>

        <p>
            Welcome back <strong><?= htmlspecialchars($userName) ?></strong>.
            Get assistance, learn features, and solve issues quickly for
            <strong><?= htmlspecialchars($businessName) ?></strong>.
        </p>
    </div>

    <div class="grid">

        <div class="glass-card">
            <div class="card-title">
                <div class="icon-box"><i class="fas fa-rocket text-orange"></i></div>
                Getting Started
            </div>

            <ul>
                <li>Add products to inventory.</li>
                <li>Create your first sale.</li>
                <li>Generate and print receipts.</li>
                <li>Review reports and analytics.</li>
                <li>Manage customers and accounts.</li>
            </ul>
        </div>

        <div class="glass-card">
            <div class="card-title">
                <div class="icon-box"><i class="fas fa-credit-card text-orange"></i></div>
                Sales & Receipts
            </div>

            <ul>
                <li>Every sale automatically creates a receipt.</li>
                <li>VAT is calculated automatically.</li>
                <li>Receipts can be reprinted anytime.</li>
                <li>Supports cash, M-PESA, card and credit sales.</li>
            </ul>
        </div>

        <div class="glass-card">
            <div class="card-title">
                <div class="icon-box"><i class="fas fa-chart-column text-orange"></i></div>
                VAT & Totals
            </div>

            <ul>
                <li>VAT calculations are automatic.</li>
                <li>Shows VAT exclusive totals.</li>
                <li>Shows VAT inclusive totals.</li>
                <li>Supports discounts and promotions.</li>
            </ul>
        </div>

        <div class="glass-card">
            <div class="card-title">
                <div class="icon-box"><i class="fas fa-triangle-exclamation text-orange"></i></div>
                Common Issues
            </div>

            <ul>
                <li>Receipt missing? Verify the sale was saved.</li>
                <li>Wrong totals? Review prices and VAT settings.</li>
                <li>No access to a module? Check permissions.</li>
                <li>Printing issues? Allow browser popups.</li>
            </ul>
        </div>

    </div>

    <div class="support-card">

        <h3>Contact Support</h3>

        <div class="contact-item">
            <div class="contact-icon"><i class="fas fa-envelope"></i></div>
            <div>
                <strong>Email</strong><br>
                bkongoine@gmail.com
            </div>
        </div>

        <div class="contact-item">
            <div class="contact-icon"><i class="fas fa-phone"></i></div>
            <div>
                <strong>Phone</strong><br>
                +254 741 822 719<br>
                +254 112 904 923<br>
                +254 111 423 969
            </div>
        </div>

        <div class="contact-item">
            <div class="contact-icon"><i class="fas fa-clock"></i></div>
            <div>
                <strong>Availability</strong><br>
                24/7 Premium Support
            </div>
        </div>

    </div>

    <div class="footer-text">
        © <?= date('Y') ?> <?= htmlspecialchars($businessName) ?>
        · Enterprise Support Center
    </div>

</div>
<?php
require("foot.php");
?>