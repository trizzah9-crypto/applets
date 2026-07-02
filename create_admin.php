<?php

require('dbconnect.php');
require 'head.php'; // runs session_start() and general login check

if(!in_array("users", $_SESSION['privileges'])){

    header("Location: no_access.php");

    exit;

}

function flashMessage($message, $type = 'info') {
    switch ($type) {
        case 'success':
            $class = 'alert alert-success solid alert-right-icon';
            break;
        case 'warning':
            $class = 'alert alert-danger solid outline alert-right-icon';
            break;
        case 'error':
            $class = 'alert alert-danger solid alert-left-icon';
            break;
        default:
            $class = 'alert alert-info';
    }
    return "<div class=\"$class\">$message</div>";
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $department = $_POST['department'];
    $rank = $_POST['rank'];
    $privileges = isset($_POST['privileges']) ? implode(",", $_POST['privileges']) : ""; // array → string

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $message = flashMessage("⚠️ All fields are required.", 'warning');
    } elseif ($password !== $confirm_password) {
        $message = flashMessage("⚠️ Passwords do not match.", 'error');
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $mysqli->prepare("INSERT INTO admins (username, password, department, rank, privileges) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssss", $username, $hashedPassword, $department, $rank, $privileges);
                $stmt->execute();
                $message = flashMessage("✅ Admin user '$username' created successfully.", 'success');
                $stmt->close();
            } else {
                $message = flashMessage("❌ Database error: " . $mysqli->error, 'error');
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $message = flashMessage("⚠️ Username '$username' already exists. Please choose another.", 'warning');
            } else {
                $message = flashMessage("❌ Database error: " . $e->getMessage(), 'error');
            }
        }
    }
}
?>

<h2 style="text-align:center;">Create New User</h2>
<div class="message">
   <?php 
if ($message) {
    echo $message; // already styled with Focus theme
}
?>
</div>
<div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Create New User</h4>
                            </div>
                            <div class="card-body">
                                <div class="basic-form">
                                    <form method="POST">

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>UserName</label>
                                                <input name="username" required type="text" class="form-control" placeholder="Enter username:">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Department</label>
                                                <input type="text" class="form-control" name="department" required placeholder="Department">
                                            </div>
                                             <div class="form-group col-md-6">
                                                <label>Password</label>
                                                <input type="password" class="form-control" id="password" name="password" required>
                                            </div>                                         
                                            <div class="form-group col-md-6">
                                                <label>Rank</label>
                                                <input type="text" class="form-control"  name="rank" required placeholder="Position of User">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>Confirm Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                             <div style="display: none;" class="form-group col-md-6">
                                                <label>Rank</label>
                                                <input type="text" class="form-control" placeholder="Position of User">
                                            </div>
                                        </div>
                                         <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"  onclick="togglePassword()">
                                                <label class="form-check-label">
                                                   Show Passwords
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group col-md-12">
    <label>Privileges</label><br>
    <label><input type="checkbox" name="privileges[]" value="basic"> Basic</label><br>
    <label><input type="checkbox" name="privileges[]" value="users"> Delete</label><br>
    <label><input type="checkbox" name="privileges[]" value="users"> Manage Users</label><br>
    <label><input type="checkbox" name="privileges[]" value="approve"> Approve Requests</label><br>
    <label><input type="checkbox" name="privileges[]" value="delete"> Delete Records</label><br>
    <label><input type="checkbox" name="privileges[]" value="booking"> booking</label><br>

    
</div>

                                        <button type="submit" class="btn btn-primary">Create Admin</button>

                                    </form>
                                </div>
                            </div>
                        </div>

<?php require("foot.php");?>

<script>
function togglePassword() {
    let pass = document.getElementById("password");
    let confirm = document.getElementById("confirm_password");
    if (pass.type === "password") {
        pass.type = "text";
        confirm.type = "text";
    } else {
        pass.type = "password";
        confirm.type = "password";
    }
}

</script>

