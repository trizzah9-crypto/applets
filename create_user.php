<?php

require "permissions.php";

if (!can("")) {
    header("Location: no_access.php");
    exit;
}

require("head.php");
require("db.php");  // $conn is PDO

// Fetch owner email from database using logged-in user ID
$ownerId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $ownerId]);
$ownerEmail = $stmt->fetchColumn();

if (!$ownerEmail) {
    // Handle no email found (optional)
    $ownerEmail = "default@domain.com"; // fallback or handle error
}

// Extract name + domain safely
list($ownerNamePart, $ownerDomain) = explode("@", $ownerEmail);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.container {
    max-width: 500px;
    width: 100%;
    animation: fadeInUp 0.6s ease forwards;
}
.card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
    padding: 30px 40px;
}
h3 {
    font-weight: 700;
    font-size: 1.8rem;
    margin-bottom: 30px;
    text-align: center;
}
#msg {
    display: none;
    font-weight: 600;
    text-align: center;
    margin-bottom: 20px;
    border-radius: 10px;
    padding: 12px 15px;
}
label {
    font-weight: 600;
    margin-bottom: 8px;
    display: block;
}
input[type="text"],
input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 12px 14px;
    border: 1.8px solid #ddd;
    border-radius: 10px;
}
input[readonly] {
    background-color: #f7f7f7;
}
button.btn-primary {
    background-color: #009ba0;
    border: none;
    border-radius: 12px;
    padding: 14px 0;
    font-weight: 700;
    width: 100%;
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>

<body>

<h2 class="mb-4 text-center py-3 rounded shadow-sm"
    style="background:rgba(0,123,255,0.1);color:#004549;border-left:5px solid #025659;">
    Create New User
</h2>

<div class="container">
    <div class="card">

        <div id="msg" class="alert"></div>

        <form id="userForm">

            <input type="hidden" id="ownerNamePart" value="<?= htmlspecialchars($ownerNamePart) ?>">
            <input type="hidden" id="ownerDomain" value="<?= htmlspecialchars($ownerDomain) ?>">

            <div class="mb-3">
                <label>Full Name *</label>
                <input type="text" name="name" id="name" required autocomplete="off">
            </div>

            <div class="mb-3">
                <label>Email (auto-generated)</label>
                <input type="email" name="email" id="email" readonly>
            </div>

            <div class="mb-3">
                <label>Password *</label>
                <input type="password" name="password" id="password" required autocomplete="new-password">
            </div>

            <div class="mb-3">
                <label>Permissions</label>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="view_products" id="p1">
                    <label class="form-check-label" for="p1">View Products</label>
                </div>

                <!-- <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="add_products" id="p2">
                    <label class="form-check-label" for="p2">Add / Edit Products</label>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_users" id="p3">
                    <label class="form-check-label" for="p3">Manage Users</label>
                </div> -->

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="view_financials" id="p4">
                    <label class="form-check-label" for="p4">View financials</label>
                </div>


            </div>

            <button type="submit" class="btn btn-primary">Create User</button>
        </form>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

function showMsg(text, type='success') {
    $("#msg")
        .removeClass("alert-success alert-danger")
        .addClass("alert-" + type)
        .text(text)
        .fadeIn();
    setTimeout(() => $("#msg").fadeOut(), 3500);
}

$("#name").on("input", function() {
    let firstname = $(this).val().trim().toLowerCase().replace(/\s+/g, "");
    firstname = firstname.replace(/[^a-z0-9]/g, "");
    let owner = $("#ownerNamePart").val();
    let domain = $("#ownerDomain").val();
    $("#email").val(firstname ? firstname + "." + owner + "@" + domain : "");
});

$("#userForm").on("submit", function(e){
    e.preventDefault();
    $.post("ajax/create_user.php", $(this).serialize(), function(res){
        if(res.status === "ok"){
            showMsg("User created successfully!");
            $("#userForm")[0].reset();
            $("#email").val("");
        } else {
            showMsg(res.message || "Failed to create user", "danger");
        }
    }, "json");
});
</script>

<?php require("foot.php"); ?>
</body>
</html>
