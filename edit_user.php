<?php
require("head.php");
require("db.php"); // $conn is PDO connection

if (!isset($_GET['id'])) {
    die("User ID missing");
}

$userId = (int)$_GET['id'];
$businessId = $_SESSION['business_id'] ?? 0;

if (!$businessId) {
    die("Business ID missing in session");
}

// Fetch user (must belong to same business)
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email
    FROM users u
    JOIN business_user bu ON bu.user_id = u.id
    WHERE u.id = :user_id AND bu.business_id = :business_id
");
$stmt->execute([':user_id' => $userId, ':business_id' => $businessId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found or not allowed");
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<h2 class="mb-4 text-center py-3 rounded shadow-sm"
    style="background:rgba(0,123,255,0.1);color:#004549;border-left:5px solid #025659;">
    Edit User
</h2>

<div class="container" style="max-width:500px;">
    <div class="card p-4 shadow-sm">

        <div id="msg" class="alert d-none"></div>

        <form id="editUserForm">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">

            <div class="mb-3">
                <label>Full Name *</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" class="form-control"
                       value="<?= htmlspecialchars($user['email']) ?>" readonly>
            </div>

            <div class="mb-3">
                <label>New Password (optional)</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Leave blank to keep current password">
            </div>

            <button class="btn btn-primary w-100">Update User</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$("#editUserForm").on("submit", function(e){
    e.preventDefault();

    $.post("ajax/update_user.php", $(this).serialize(), function(res){
        if(res.status === "ok"){
            showMsg("User updated successfully", "success");
        } else {
            showMsg(res.message || "Update failed", "danger");
        }
    }, "json");
});

function showMsg(text, type){
    $("#msg")
        .removeClass("d-none alert-success alert-danger")
        .addClass("alert-" + type)
        .text(text);
}
</script>

</body>
</html>

<?php require("foot.php"); ?>
