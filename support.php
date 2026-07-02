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
    body {
        font-family: Arial, Helvetica, sans-serif;
        background: #f6f7f9;
        margin: 0;
        padding: 0;
        color: #333;
    }

    .container {
        max-width: 900px;
        margin: 30px auto;
        background: #fff;
        padding: 25px;
        border-radius: 6px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    h1 {
        margin-top: 0;
        font-size: 26px;
        text-align: center;
    }

    h2 {
        margin-top: 30px;
        font-size: 20px;
        border-bottom: 2px solid #eee;
        padding-bottom: 6px;
    }

    p {
        line-height: 1.6;
        font-size: 14px;
    }

    ul {
        padding-left: 18px;
        font-size: 14px;
    }

    li {
        margin-bottom: 8px;
    }

    .box {
        background: #f9fafb;
        border-left: 4px solid #007bff;
        padding: 15px;
        margin-top: 15px;
        font-size: 14px;
    }

    .contact {
        background: #eef6ff;
        padding: 15px;
        border-radius: 5px;
        margin-top: 20px;
        font-size: 14px;
    }

    .footer {
        text-align: center;
        margin-top: 30px;
        font-size: 12px;
        color: #777;
    }

    a {
        color: #007bff;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
</style>
</head>

<body>

<div class="container">

    <h1>Support & Help</h1>
    <p style="text-align:center;">
        Welcome <strong><?php echo htmlspecialchars($userName); ?></strong> 👋  
        Need help using <strong><?php echo htmlspecialchars($businessName); ?></strong>? You’re in the right place.
    </p>

    <h2>Getting Started</h2>
    <p>
        This system is designed to help you manage sales, receipts, customers, products,
        and reports efficiently. If you are new, start with:
    </p>
    <ul>
        <li>Adding products to your inventory</li>
        <li>Making a sale</li>
        <li>Printing or viewing receipts</li>
        <li>Checking daily sales reports</li>
    </ul>

    <h2>Sales & Receipts</h2>
    <div class="box">
        <ul>
            <li>Every sale automatically generates a receipt.</li>
            <li>Receipts can include VAT if enabled.</li>
            <li>You can reprint receipts from the sales history.</li>
            <li>Payment methods supported include Cash, Credit, and others depending on setup.</li>
        </ul>
    </div>

    <h2>VAT & Totals</h2>
    <p>
        If VAT is enabled in your system:
    </p>
    <ul>
        <li>VAT is calculated at 16%</li>
        <li>Total (Excl. VAT) is shown</li>
        <li>Total (Incl. VAT) is shown</li>
    </ul>

    <h2>Common Issues</h2>
    <div class="box">
        <strong>Receipt not showing?</strong>
        <ul>
            <li>Ensure the sale was saved successfully</li>
            <li>Check that your browser allows popups/printing</li>
        </ul>

        <strong>Wrong totals?</strong>
        <ul>
            <li>Confirm product prices</li>
            <li>Check VAT and discount settings</li>
        </ul>

        <strong>Cannot access a page?</strong>
        <ul>
            <li>You may not have permission</li>
            <li>Contact your system administrator</li>
        </ul>
    </div>

    <h2>Contact Support</h2>
    <div class="contact">
        <p>If you need further assistance, contact support:</p>
        <ul>
            <li>Email: <strong>bkongoine@gmail.com</strong></li>
            <li>Phone: <strong>+254 741 822 719 / +254 112 904 923 / +254 111 423 969</strong></li>
            <li>Office Hours: 24/7HR Support</li>
        </ul>
    </div>

  

</div>
<?php
require("foot.php");
?>