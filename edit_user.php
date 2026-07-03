<?php
require("head.php");
require("db.php");

if (!isset($_GET['id'])) {
    die("User ID missing");
}

$userId = (int)$_GET['id'];
$businessId = $_SESSION['business_id'] ?? 0;

if (!$businessId) {
    die("Business ID missing in session");
}

$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email
    FROM users u
    JOIN business_user bu ON bu.user_id = u.id
    WHERE u.id = :user_id
    AND bu.business_id = :business_id
");

$stmt->execute([
    ':user_id' => $userId,
    ':business_id' => $businessId
]);

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

<style>

        :root{
            --primary:#054960;
            --primary-light:#0a5d78;
            --primary-bright:#0f7c9d;

            --accent:#ff7900;
            --accent-light:#ff9d3f;

            --radius-xl:28px;
            --radius-lg:22px;
            --radius-md:18px;

            --glass:rgba(255,255,255,.78);
            --glass-border:rgba(255,255,255,.45);

            --shadow-xl:
                0 30px 60px rgba(5,73,96,.15);

            --shadow-lg:
                0 20px 45px rgba(5,73,96,.12);

            --shadow-md:
                0 10px 30px rgba(5,73,96,.08);
        }

        body{
            min-height:100vh;

            background:
                radial-gradient(circle at top left,#dff5ff,#f4f8fb);

            font-family:"Segoe UI",sans-serif;
        }

        /* HERO */

        .hero-section{
            background:linear-gradient(
                135deg,
                var(--primary),
                var(--primary-light),
                var(--primary-bright)
            );

            border-radius:var(--radius-xl);

            padding:35px;

            color:white;

            box-shadow:var(--shadow-xl);

            margin-bottom:35px;
        }

        .hero-title{
            font-size:34px;
            font-weight:700;
            margin-bottom:8px;
        }

        .hero-subtitle{
            opacity:.9;
            font-size:15px;
        }

        .hero-badge{
            display:inline-block;

            padding:10px 18px;

            background:rgba(255,255,255,.15);

            border-radius:50px;

            backdrop-filter:blur(15px);

            margin-top:18px;
        }

        /* FORM CARD */

        .form-card{
            background:var(--glass);

            backdrop-filter:blur(20px);

            border:1px solid var(--glass-border);

            border-radius:var(--radius-xl);

            padding:40px;

            box-shadow:var(--shadow-lg);
        }

        /* LABELS */

        .form-label{
            font-weight:600;
            color:var(--primary);
            margin-bottom:10px;
        }

        /* INPUTS */

        .form-control{
            height:58px;

            border-radius:18px;

            border:1px solid rgba(5,73,96,.12);

            background:rgba(255,255,255,.7);

            padding-left:20px;

            transition:.3s;
        }

        .form-control:focus{
            border-color:var(--primary-bright);

            box-shadow:
                0 0 0 4px rgba(15,124,157,.12);

            background:white;
        }

        .form-control[readonly]{
            background:#f8fafc;
        }

        /* BUTTON */

        .btn-update{
            background:linear-gradient(
                135deg,
                var(--accent),
                var(--accent-light)
            );

            border:none;

            color:white;

            height:60px;

            border-radius:18px;

            font-weight:700;

            font-size:16px;

            box-shadow:
                0 12px 30px rgba(255,121,0,.35);

            transition:.3s;
        }

        .btn-update:hover{
            transform:translateY(-3px);

            box-shadow:
                0 20px 40px rgba(255,121,0,.4);
        }

        /* ALERTS */

        .alert{
            border:none;

            border-radius:18px;

            padding:18px;

            font-weight:600;

            backdrop-filter:blur(10px);
        }

        .alert-success{
            background:rgba(22,163,74,.12);
            color:#166534;
        }

        .alert-danger{
            background:rgba(220,38,38,.12);
            color:#991b1b;
        }

        /* USER INFO */

        .info-box{
            background:rgba(5,73,96,.05);

            border-radius:18px;

            padding:18px;

            margin-bottom:25px;
        }

        .info-title{
            color:#6b7280;
            font-size:13px;
        }

        .info-value{
            color:var(--primary);
            font-weight:700;
            font-size:20px;
        }

        /* PASSWORD HINT */

        .password-hint{
            color:#6b7280;
            font-size:13px;
            margin-top:8px;
        }

        /* PREMIUM SCROLLBAR */

        ::-webkit-scrollbar{
            width:10px;
        }

        ::-webkit-scrollbar-thumb{
            background:var(--primary-light);
            border-radius:50px;
        }

        /* MOBILE */

        @media(max-width:768px){

            .hero-title{
                font-size:28px;
            }

            .form-card{
                padding:25px;
            }

            .hero-section{
                padding:25px;
            }
        }

</style>

</head>

<body>

<div class="container py-5" style="max-width:850px;">

    <div class="hero-section">

        <div class="hero-title">
            Edit User
        </div>

        <div class="hero-subtitle">
            Update team member details and account credentials.
        </div>

        <!-- <div class="hero-badge">
            User ID #<?= $user['id']; ?>
        </div> -->

    </div>

    <div class="form-card">

        <div id="msg" class="alert d-none"></div>

        <div class="info-box">
            <div class="info-title">
                Currently Editing
            </div>

            <div class="info-value">
                <?= htmlspecialchars($user['name']) ?>
            </div>
        </div>

        <form id="editUserForm">

            <input type="hidden"
                   name="user_id"
                   value="<?= htmlspecialchars($user['id']) ?>">

            <div class="mb-4">

                <label class="form-label">
                    Full Name *
                </label>

                <input type="text"
                       name="name"
                       class="form-control"
                       value="<?= htmlspecialchars($user['name']) ?>"
                       required>

            </div>

            <div class="mb-4">

                <label class="form-label">
                    Email Address
                </label>

                <input type="email"
                       class="form-control"
                       value="<?= htmlspecialchars($user['email']) ?>"
                       readonly>

            </div>

            <div class="mb-4">

                <label class="form-label">
                    New Password
                </label>

                <input type="password"
                       name="password"
                       class="form-control"
                       placeholder="Leave blank to keep current password">

                <div class="password-hint">
                    Password is only updated if a new one is entered.
                </div>

            </div>

            <button class="btn btn-update w-100">
                Update User
            </button>

        </form>

    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>

$("#editUserForm").on("submit", function(e){

    e.preventDefault();

    let btn = $(".btn-update");

    btn.prop("disabled", true);
    btn.html("Updating User...");

    $.post(
        "ajax/update_user.php",
        $(this).serialize(),
        function(res){

            if(res.status === "ok"){

                showMsg(
                    "User updated successfully.",
                    "success"
                );

            }else{

                showMsg(
                    res.message || "Update failed.",
                    "danger"
                );

            }

            btn.prop("disabled", false);
            btn.html("Update User");

        },
        "json"
    )
    .fail(function(){

        showMsg(
            "Server connection error.",
            "danger"
        );

        btn.prop("disabled", false);
        btn.html("Update User");

    });

});

function showMsg(text, type){

    $("#msg")
        .removeClass(
            "d-none alert-success alert-danger"
        )
        .addClass(
            "alert-" + type
        )
        .html(text);

    window.scrollTo({
        top:0,
        behavior:"smooth"
    });
}

</script>

</body>
</html>

<?php require("foot.php"); ?>