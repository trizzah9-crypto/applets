<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>No Access</title>
        <link rel="icon" type="image/png" sizes="192x192" href="./Images/logo.png" />

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f8f8;
            text-align: center;
            padding-top: 100px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d9534f;
        }
        p {
            color: #555;
        }
        a {
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
            background: #0275d8;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
        }
        a:hover {
            background: #025aa5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Access Denied</h1>
        <p>You don’t have permission to view this page.<br>
        Please contact your IT administrator for assistance.</p>
        <a href="dashboard.php">Return to Dashboard</a>
    </div>
</body>
</html>


