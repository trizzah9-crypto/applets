<?php

require "permissions.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: no_access.php");
    exit;
}

require("head.php");
require("db.php");

$biz_id = $_SESSION['business_id'];

/*
|--------------------------------------------------------------------------
| Fetch statistics
|--------------------------------------------------------------------------
*/

$stats = $conn->prepare("
    SELECT COUNT(*) as total_users
    FROM business_user
    WHERE business_id = :biz_id
");
$stats->execute([':biz_id' => $biz_id]);
$total_users = $stats->fetch(PDO::FETCH_ASSOC)['total_users'];

$owner_count = 0;
$manager_count = 0;
$staff_count = 0;

$roles_stmt = $conn->prepare("
    SELECT role, COUNT(*) as total
    FROM business_user
    WHERE business_id = :biz_id
    GROUP BY role
");
$roles_stmt->execute([':biz_id' => $biz_id]);

while ($role_row = $roles_stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($role_row['role'] == 'owner') $owner_count = $role_row['total'];
    elseif ($role_row['role'] == 'manager') $manager_count = $role_row['total'];
    else $staff_count += $role_row['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Users Management</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

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

    --shadow-lg:
        0 30px 60px rgba(5,73,96,.15);

    --shadow-md:
        0 15px 35px rgba(5,73,96,.10);
}

body{
    background:
        radial-gradient(circle at top left,#eaf8ff,#f5f9fc);
    min-height:100vh;
    font-family:"Segoe UI",sans-serif;
}

.page-container{
    max-width:1600px;
}

/* HERO */

.hero-card{
    background:linear-gradient(
        135deg,
        var(--primary),
        var(--primary-light),
        var(--primary-bright)
    );

    color:white;

    border-radius:var(--radius-xl);

    padding:35px;

    box-shadow:var(--shadow-lg);

    margin-bottom:30px;
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

.hero-stats{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    margin-top:18px;
}

.hero-pill{
    padding:10px 18px;
    border-radius:50px;
    background:rgba(255,255,255,.15);
    backdrop-filter:blur(12px);
}

/* ADD BUTTON */

.btn-premium{
    background:linear-gradient(
        135deg,
        var(--accent),
        var(--accent-light)
    );

    color:white !important;
    border:none;
    border-radius:18px;
    padding:14px 24px;
    font-weight:600;

    box-shadow:
        0 12px 30px rgba(255,121,0,.35);

    transition:.3s;
}

.btn-premium:hover{
    transform:translateY(-3px);
}

/* STAT CARDS */

.stat-card{
    background:var(--glass);

    backdrop-filter:blur(20px);

    border:1px solid var(--glass-border);

    border-radius:var(--radius-lg);

    padding:28px;

    box-shadow:var(--shadow-md);

    transition:.3s;
}

.stat-card:hover{
    transform:translateY(-6px);
}

.stat-title{
    color:#6b7280;
    font-size:14px;
}

.stat-value{
    font-size:34px;
    font-weight:700;
    color:var(--primary);
}

/* TABLE CARD */

.table-card{
    background:var(--glass);

    backdrop-filter:blur(20px);

    border:1px solid var(--glass-border);

    border-radius:var(--radius-xl);

    padding:30px;

    box-shadow:var(--shadow-lg);
}

/* TABLE */

.user-table{
    border-collapse:separate;
    border-spacing:0 12px;
}

.user-table thead th{
    background:linear-gradient(
        135deg,
        var(--primary),
        var(--primary-light)
    );

    color:white;

    border:none;

    padding:18px;

    font-size:14px;
    letter-spacing:.5px;
}

.user-table thead th:first-child{
    border-radius:18px 0 0 18px;
}

.user-table thead th:last-child{
    border-radius:0 18px 18px 0;
}

.user-table tbody tr{
    background:white;
    transition:.3s;
}

.user-table tbody tr:hover{
    transform:translateY(-3px);

    box-shadow:
        0 12px 30px rgba(0,0,0,.08);
}

.user-table tbody td{
    padding:18px;
    vertical-align:middle;
    border:none;
}

.user-table tbody td:first-child{
    border-radius:18px 0 0 18px;
}

.user-table tbody td:last-child{
    border-radius:0 18px 18px 0;
}

/* ROLE BADGES */

.role-badge{
    padding:8px 16px;
    border-radius:50px;
    font-size:12px;
    font-weight:600;
}

.role-owner{
    background:#fff2db;
    color:#d97706;
}

.role-manager{
    background:#e0f2fe;
    color:#0369a1;
}

.role-staff{
    background:#ecfdf5;
    color:#15803d;
}

/* BUTTONS */

.btn-action{
    border:none;
    border-radius:14px;
    padding:8px 14px;
    color:white;
    font-size:13px;
    transition:.3s;
}

.btn-action:hover{
    transform:translateY(-2px);
}

.copy-btn{
    background:#7c3aed;
}

.edit-btn{
    background:#059669;
}

.delete-btn{
    background:#dc2626;
}

/* Scrollbar */

::-webkit-scrollbar{
    width:10px;
}

::-webkit-scrollbar-thumb{
    background:var(--primary-light);
    border-radius:50px;
}

/* Mobile */

@media(max-width:768px){

    .hero-title{
        font-size:26px;
    }

    .hero-card{
        text-align:center;
    }

    .btn-premium{
        width:100%;
        margin-top:20px;
    }
}

</style>
</head>

<body>

<div class="container-fluid page-container py-4">

    <!-- HERO -->

    <div class="hero-card">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <div class="hero-title">
                    Users Management
                </div>

                <div class="hero-subtitle">
                    Manage staff accounts, permissions and access control.
                </div>

                <div class="hero-stats">

                    <div class="hero-pill">
                        <?= $total_users ?> Team Members
                    </div>

                    <div class="hero-pill">
                        <?= $owner_count ?> Owners
                    </div>

                    <div class="hero-pill">
                        <?= $manager_count ?> Managers
                    </div>

                    <div class="hero-pill">
                        <?= $staff_count ?> Staff
                    </div>

                </div>

            </div>

            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">

                <a href="create_user.php" class="btn btn-premium">
                    + Add User
                </a>

            </div>

        </div>

    </div>

    <!-- STATS -->

    <div class="row mb-4">

        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-title">Total Users</div>
                <div class="stat-value"><?= $total_users ?></div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-title">Owners</div>
                <div class="stat-value"><?= $owner_count ?></div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-title">Managers</div>
                <div class="stat-value"><?= $manager_count ?></div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-title">Staff Members</div>
                <div class="stat-value"><?= $staff_count ?></div>
            </div>
        </div>

    </div>

    <!-- TABLE -->

    <div class="table-card">

        <div class="table-responsive">

            <table class="table user-table">
                <?php $no= 1; ?>

                <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th width="220">Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php

                $stmt = $conn->prepare("
                    SELECT
                        users.id,
                        users.name,
                        users.email,
                        users.created_at,
                        business_user.role
                    FROM business_user
                    JOIN users
                        ON business_user.user_id = users.id
                    WHERE business_user.business_id = :biz_id
                    ORDER BY users.id DESC
                ");

                $stmt->execute([
                    ':biz_id' => $biz_id
                ]);

                while($row = $stmt->fetch(PDO::FETCH_ASSOC)):

                    $roleClass = "role-staff";

                    if($row['role'] == 'owner'){
                        $roleClass = "role-owner";
                    }

                    if($row['role'] == 'manager'){
                        $roleClass = "role-manager";
                    }

                ?>

                <tr id="row-<?= $row['id']; ?>">

                    <td>
                        <strong><?= $no++?></strong>
                    </td>

                    <td>
                        <strong><?= htmlspecialchars($row['name']); ?></strong>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['email']); ?>
                    </td>

                    <td>
                        <span class="role-badge <?= $roleClass ?>">
                            <?= ucfirst($row['role']); ?>
                        </span>
                    </td>

                    <td>
                        <?= date("d M Y", strtotime($row['created_at'])); ?>
                    </td>

                    <td>

                        <button
                            class="btn-action copy-btn copy-email"
                            data-email="<?= htmlspecialchars($row['email']); ?>">
                            Copy Email
                        </button>

                        <a href="edit_user.php?id=<?= $row['id']; ?>"
                           class="btn-action edit-btn text-decoration-none">
                            Edit
                        </a>

                        <button
                            class="btn-action delete-btn delete-user"
                            data-id="<?= $row['id']; ?>">
                            Delete
                        </button>

                    </td>

                </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<script>

document.querySelectorAll(".copy-email").forEach(btn => {

    btn.addEventListener("click", function(){

        navigator.clipboard.writeText(this.dataset.email);

        this.innerText = "Copied";

        setTimeout(()=>{
            this.innerText="Copy Email";
        },1500);

    });

});

document.querySelectorAll(".delete-user").forEach(btn => {

    btn.addEventListener("click", function(){

        let id = this.dataset.id;

        if(!confirm("Delete this user?")){
            return;
        }

        fetch("ajax/delete_user.php",{
            method:"POST",
            headers:{
                "Content-Type":
                    "application/x-www-form-urlencoded"
            },
            body:
                "id=" +
                encodeURIComponent(id)
        })
        .then(res=>res.json())
        .then(data=>{

            if(data.status==="ok"){

                document.getElementById(
                    "row-"+id
                ).remove();

            }else{

                alert(
                    data.message ||
                    "Unable to delete user."
                );

            }

        });

    });

});

</script>

<?php require("foot.php"); ?>

</body>
</html>