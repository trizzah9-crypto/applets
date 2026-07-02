<?php

require "permissions.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: no_access.php");
    exit;
}
require("head.php");
require("db.php");  // assume $conn is PDO connection

$biz_id = $_SESSION['business_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Users</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<style>
    /* Your CSS styles unchanged */
    body {
        background: #f5f7fa;
        font-family: "Segoe UI", sans-serif;
    }

    .page-box {
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    h3 {
        font-weight: 600;
        color: #333;
    }

    .table {
        border-radius: 10px;
        overflow: hidden;
    }

    .table thead tr {
        background: #111;
        color: #fff;
    }

    .table tbody tr {
        transition: 0.2s;
    }

    .table tbody tr:hover {
        background: #f0f4ff;
    }

    .btn {
        border-radius: 6px !important;
    }

    .btn-sm {
        padding: 3px 8px !important;
    }

    .copy-btn {
        background: #6c63ff;
        border: none;
    }
    .copy-btn:hover {
        background: #584dff;
    }

    .delete-btn:hover {
        opacity: 0.85;
    }

    .top-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 18px;
    }

    .top-actions .btn {
        font-weight: 500;
    }
</style>
</head>

<body>
    <h2 class="mb-4 text-center py-3 rounded shadow-sm"
        style="background:rgba(0,123,255,0.1);color:#004549;border-left:5px solid #025659;">
        Users
    </h2>
<div class="container py-4">
    <div class="page-box">

        <div class="top-actions">
            
            <a href="create_user.php" class="btn btn-primary btn-sm">
                + Add User
            </a>
        </div>

        <table class="table table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Password Hash</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th width="140px">Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php
            // Prepare and execute with PDO
            $stmt = $conn->prepare("
                SELECT users.id, users.name, users.email, users.password, users.created_at, business_user.role
                FROM business_user
                JOIN users ON business_user.user_id = users.id
                WHERE business_user.business_id = :biz_id
            ");
            $stmt->execute([':biz_id' => $biz_id]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                $id = $row['id'];
                $name = $row['name'];
                $email = $row['email'];
                $password = $row['password'];
                $created_at = $row['created_at'];
                $role = $row['role'];
            ?>
                <tr id="row-<?php echo $id; ?>">
                    <td><?php echo $id; ?></td>
                    <td><?php echo htmlspecialchars($name); ?></td>
                    <td><?php echo htmlspecialchars($email); ?></td>
                    <td><?php echo substr($password, 0, 30) . "..."; ?></td>
                    <td><?php echo htmlspecialchars($role); ?></td>
                    <td><?php echo $created_at; ?></td>
                    <td>
                        <button class="btn btn-sm text-white copy-btn"
                                data-email="<?php echo htmlspecialchars($email); ?>">Copy</button>

                        <a href="edit_user.php?id=<?php echo $id; ?>"
                           class="btn btn-success btn-sm">Edit</a>

                        <button class="btn btn-danger btn-sm delete-btn"
                                data-id="<?php echo $id; ?>">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

    </div>
</div>

<script>
// Copy email
document.querySelectorAll(".copy-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        navigator.clipboard.writeText(this.dataset.email);
        alert("Email copied: " + this.dataset.email);
    });
});

// Delete
document.querySelectorAll(".delete-btn").forEach(btn => {
    btn.addEventListener("click", function() {

        let id = this.dataset.id;
        if (!confirm("Delete this user?")) return;

        fetch("ajax/delete_user.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "id=" + encodeURIComponent(id)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "ok") {
                document.getElementById("row-" + id).remove();
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(err => alert("Fetch error"));
    });
});
</script>

<?php require("foot.php"); ?>
