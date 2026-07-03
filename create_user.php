<?php
require "permissions.php";

if (!can("")) {
    header("Location: no_access.php");
    exit;
}

require("head.php");
require("db.php"); // $conn is PDO

// Fetch owner email from database using logged-in user ID
$ownerId = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $ownerId]);
$ownerEmail = $stmt->fetchColumn();

if (!$ownerEmail || strpos($ownerEmail, '@') === false) {
    $ownerEmail = "default@domain.com";
}

// Extract name + domain safely
[$ownerNamePart, $ownerDomain] = array_pad(explode("@", $ownerEmail, 2), 2, 'domain.com');
$ownerNamePart = preg_replace('/[^a-zA-Z0-9._-]/', '', $ownerNamePart);
$ownerDomain   = preg_replace('/[^a-zA-Z0-9.-]/', '', $ownerDomain);
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

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
        }

        *{
            box-sizing:border-box;
        }

        body{
            min-height:100vh;
            margin:0;
            background:
                radial-gradient(circle at top left, rgba(15,124,157,.08), transparent 30%),
                radial-gradient(circle at top right, rgba(255,157,63,.08), transparent 30%),
                linear-gradient(180deg,#f8fbfd,#f4f8fb);
            color:#0f172a;
        }

        .page-wrap{
            max-width:860px;
            margin:0 auto;
            padding:24px 16px 40px;
        }

        .page-title{
            background:linear-gradient(135deg,var(--primary),var(--primary-light),var(--primary-accent));
            color:#fff;
            border-radius:28px;
            padding:28px 30px;
            margin-bottom:24px;
            box-shadow:var(--shadow-lg);
            position:relative;
            overflow:hidden;
        }

        .page-title::before{
            content:"";
            position:absolute;
            inset:0;
            background:
                radial-gradient(circle at 15% 20%, rgba(255,157,63,.20), transparent 22%),
                radial-gradient(circle at 85% 10%, rgba(255,255,255,.12), transparent 18%);
            pointer-events:none;
        }

        .page-title-inner{
            position:relative;
            z-index:1;
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:16px;
            flex-wrap:wrap;
        }

        .page-title h2{
            font-weight:800;
            margin:0;
            letter-spacing:-.02em;
            font-size:clamp(1.5rem, 2.5vw, 2.2rem);
        }

        .page-title p{
            margin:8px 0 0;
            opacity:.88;
        }

        .hero-pill{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 12px;
            border-radius:999px;
            background:rgba(255,255,255,.14);
            backdrop-filter:blur(10px);
            -webkit-backdrop-filter:blur(10px);
            border:1px solid rgba(255,255,255,.14);
            font-size:.92rem;
            white-space:nowrap;
        }

        .container-shell{
            max-width:860px;
            margin:0 auto;
        }

        .card-shell{
            background:var(--glass);
            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);
            border:1px solid rgba(255,255,255,.42);
            border-radius:28px;
            box-shadow:var(--shadow-md);
            padding:34px;
        }

        .form-label{
            font-weight:700;
            color:#334155;
            margin-bottom:8px;
        }

        .form-control{
            border-radius:16px;
            min-height:54px;
            border:1px solid rgba(5,73,96,.15);
            transition:all .25s ease;
            padding:12px 14px;
            background:#fff;
        }

        .form-control:focus{
            border-color:var(--primary-accent);
            box-shadow:0 0 0 .25rem rgba(15,124,157,.15);
        }

        .form-control[readonly]{
            background:rgba(5,73,96,.05);
        }

        .input-group .btn{
            border-radius:16px;
        }

        .field-helper{
            font-size:.88rem;
            color:#64748b;
            margin-top:6px;
        }

        .preview-box{
            border-radius:22px;
            padding:18px;
            background:linear-gradient(135deg, rgba(5,73,96,.06), rgba(15,124,157,.08));
            border:1px solid rgba(5,73,96,.10);
            box-shadow:var(--shadow-sm);
        }

        .preview-label{
            font-size:.82rem;
            text-transform:uppercase;
            letter-spacing:.08em;
            color:#64748b;
            font-weight:700;
            margin-bottom:8px;
        }

        .preview-email{
            font-size:1rem;
            font-weight:800;
            color:var(--primary);
            word-break:break-word;
        }

        .avatar-circle{
            width:58px;
            height:58px;
            border-radius:50%;
            display:grid;
            place-items:center;
            font-weight:800;
            color:#fff;
            background:linear-gradient(135deg, var(--orange), var(--orange-light));
            box-shadow:0 14px 30px rgba(255,121,0,.22);
            flex:0 0 auto;
        }

        .permission-section{
            margin-top:6px;
        }

        .permissions-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
            gap:14px;
        }

        .permission-card{
            border:1px solid rgba(5,73,96,.10);
            border-radius:18px;
            padding:16px 16px 14px;
            transition:.25s ease;
            cursor:pointer;
            background:#fff;
            box-shadow:0 8px 18px rgba(5,73,96,.05);
            display:flex;
            gap:12px;
            align-items:flex-start;
        }

        .permission-card:hover{
            transform:translateY(-2px);
            box-shadow:var(--shadow-sm);
        }

        .permission-card input{
            transform:scale(1.15);
            margin-top:3px;
            flex:0 0 auto;
        }

        .permission-card strong{
            display:block;
            font-size:1rem;
            color:#0f172a;
            margin-bottom:4px;
        }

        .permission-card span{
            display:block;
            font-size:.88rem;
            color:#64748b;
            line-height:1.35;
        }

        .permission-card:has(input:checked){
            border-color:var(--primary-accent);
            background:rgba(15,124,157,.08);
        }

        .btn-primary{
            background:linear-gradient(135deg,var(--primary-light),var(--primary-accent));
            border:none;
            border-radius:18px;
            min-height:56px;
            font-weight:700;
            box-shadow:0 15px 35px rgba(15,124,157,.25);
            transition:.25s ease;
        }

        .btn-primary:hover{
            transform:translateY(-2px);
            box-shadow:0 18px 40px rgba(15,124,157,.30);
        }

        .btn-outline-secondary{
            border-radius:18px;
            min-height:56px;
            font-weight:700;
        }

        #msg{
            border-radius:16px;
            font-weight:700;
            display:none;
            margin-bottom:18px;
        }

        .form-footer{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            margin-top:22px;
        }

        .form-footer .btn{
            flex:1 1 220px;
        }

        .section-title{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-bottom:14px;
        }

        .section-title h5{
            margin:0;
            font-weight:800;
            color:#0f172a;
        }

        .section-title small{
            color:#64748b;
        }

        @keyframes fadeInUp{
            from{ opacity:0; transform:translateY(25px); }
            to{ opacity:1; transform:translateY(0); }
        }

        .fade-in-up{
            animation:fadeInUp .6s ease;
        }

        @media (max-width: 768px){
            .page-wrap{
                padding:14px 12px 28px;
            }

            .page-title{
                padding:22px 18px;
                border-radius:24px;
            }

            .card-shell{
                padding:20px 16px;
                border-radius:24px;
            }

            .permissions-grid{
                grid-template-columns:1fr;
            }

            .form-footer .btn{
                flex:1 1 100%;
            }
        }
</style>

</head>

<body>
<div class="page-wrap">
    <div class="page-title fade-in-up">
        <div class="page-title-inner">
            <div>
                <h2>Create New User</h2>
                <p>Create staff accounts, auto-generate emails, and assign permissions securely.</p>
            </div>

 
        <!-- <div class="hero-pill">
            <span>Signed in as</span>
            <strong><?= htmlspecialchars($_SESSION['user_name'] ?? ($_SESSION['admin'] ?? 'Owner')) ?></strong>
        </div> -->
    </div>
</div>

<div class="container-shell">
    <div class="card-shell fade-in-up">
        <div id="msg" class="alert"></div>

        <form id="userForm" autocomplete="off">
            <input type="hidden" id="ownerNamePart" value="<?= htmlspecialchars($ownerNamePart) ?>">
            <input type="hidden" id="ownerDomain" value="<?= htmlspecialchars($ownerDomain) ?>">

            <div class="row g-3 mb-3 align-items-center">
                <div class="col-md-2 d-none d-md-flex">
                    <div class="avatar-circle" id="avatarCircle">U</div>
                </div>
                <div class="col-md-10">
                    <div class="preview-box">
                        <div class="preview-label">Generated Email Preview</div>
                        <div class="preview-email" id="emailPreview">Enter a full name to generate the email</div>
                        <div class="field-helper">The email uses the owner domain and the entered full name.</div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" name="name" id="name" class="form-control" required autocomplete="off" placeholder="e.g. Jane Mwangi">
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email (auto-generated)</label>
                <input type="email" name="email" id="email" class="form-control" readonly>
                <div class="field-helper">This value is created automatically from the staff name and company domain.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password *</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password" placeholder="Create a secure password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">Show</button>
                </div>
            </div>

            <div class="permission-section mb-2">
                <div class="section-title">
                    <div>
                        <h5>Permissions</h5>
                        <small>Select the access level for this user</small>
                    </div>
                </div>

                <div class="permissions-grid">
                    <label class="permission-card">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="view_products" id="p1">
                        <div>
                            <strong>View Products</strong>
                            <span>Browse stock, product records, and catalog data.</span>
                        </div>
                    </label>

                    <label class="permission-card">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="view_financials" id="p2">
                        <div>
                            <strong>View Financials</strong>
                            <span>Access revenue, sales, reports, and financial summaries.</span>
                        </div>
                    </label>

                    <label class="permission-card">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="view_sales" id="p3">
                        <div>
                            <strong>View Sales</strong>
                            <span>Open sales pages, receipts, and transaction history.</span>
                        </div>
                    </label>

                    <label class="permission-card">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_customers" id="p4">
                        <div>
                            <strong>Manage Customers</strong>
                            <span>Create, edit, and review customer records.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-footer">
                <button type="button" class="btn btn-outline-secondary" id="resetForm">Reset</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>


</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function showMsg(text, type = 'success') {
    $("#msg")
        .removeClass("alert-success alert-danger alert-warning alert-info")
        .addClass("alert-" + type)
        .text(text)
        .fadeIn();

    setTimeout(() => $("#msg").fadeOut(), 3500);
}

function buildEmailFromName(name) {
    let firstname = String(name || '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, "")
        .replace(/[^a-z0-9]/g, "");

    const owner = $("#ownerNamePart").val() || "owner";
    const domain = $("#ownerDomain").val() || "domain.com";

    return firstname ? `${firstname}.${owner}@${domain}` : "";
}

function updatePreview() {
    const name = $("#name").val().trim();
    const email = buildEmailFromName(name);

    $("#email").val(email);
    $("#emailPreview").text(email || "Enter a full name to generate the email");

    const initials = name
        ? name.split(" ").map(part => part[0]).join("").slice(0, 2).toUpperCase()
        : "U";

    $("#avatarCircle").text(initials || "U");
}

$("#name").on("input", updatePreview);

$("#togglePassword").on("click", function () {
    const $pwd = $("#password");
    const isHidden = $pwd.attr("type") === "password";
    $pwd.attr("type", isHidden ? "text" : "password");
    $(this).text(isHidden ? "Hide" : "Show");
});

$("#resetForm").on("click", function () {
    $("#userForm")[0].reset();
    $("#msg").hide();
    $("#togglePassword").text("Show");
    updatePreview();
});

$("#userForm").on("submit", function(e){
    e.preventDefault();

    $.post("ajax/create_user.php", $(this).serialize(), function(res){
        if(res.status === "ok"){
            showMsg("User created successfully!", "success");
            $("#userForm")[0].reset();
            $("#togglePassword").text("Show");
            $("#msg").removeClass("alert-danger").addClass("alert-success");
            updatePreview();
        } else {
            showMsg(res.message || "Failed to create user", "danger");
        }
    }, "json").fail(function () {
        showMsg("Request failed. Please try again.", "danger");
    });
});

updatePreview();
</script>

<?php require("foot.php"); ?>

</body>
</html>
