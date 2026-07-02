<?php
require "permissions.php";

if (!can('')) {
    header("Location: no_access.php");
    exit;
}
require("head.php");
require_once("db.php");

// Ensure user is logged in
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: login.php");
    exit;
}

// Fetch current business settings
$stmt = $conn->prepare("
    SELECT business_name, business_email, business_phone, business_address, receipt_logo 
    FROM businesses 
    WHERE owner_user_id = ?
");
$stmt->execute([$userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html>
<head>
    <title>Business Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <style>
        body {
            background: #f5f7fa;
            animation: fadeIn 0.6s ease-in-out;
        }
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(10px);} 
            to { opacity: 1; transform: translateY(0);} 
        }
        .settings-card {
            max-width: 900px;
            margin: auto;
            border-radius: 15px;
            transition: 0.3s ease;
            animation: slideUp 0.6s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity:0; }
            to { transform: translateY(0); opacity:1; }
        }
        .settings-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.14);
        }

        .btn-primary {
            background: #009ba0ff;
            border-radius: 10px;
            padding: 12px;
            border: none;
        }
        .btn-primary:hover {
            background: #025659ff;
            transform: scale(1.02);
        }

        .form-control {
            border-radius: 10px;
            padding: 10px;
        }

        .logo-preview img {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        label { font-weight: 500; }
    </style>
</head>

<body>

<div class="container py-4">

    <h2 class="text-center mb-4 py-3 px-4 rounded shadow-sm" 
        style="
            background: rgba(0, 123, 255, 0.1); 
            color: #004549ff; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            border-left: 5px solid #025659ff;
            width: max-content;
            margin-left: 50%;
            transform: translateX(-50%);
        ">
        Business Settings
    </h2>

    <div id="message"></div>

    <form id="settingsForm" enctype="multipart/form-data"
          class="card p-4 shadow-sm settings-card bg-white">

        <div class="row">

            <!-- LEFT COLUMN -->
            <div class="col-md-6 mb-3">
                <label class="form-label">Business Name</label>
                <input type="text" name="business_name" class="form-control"
                       value="<?php echo $settings['business_name']; ?>" required>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label">Business Email</label>
                <input type="email" name="business_email" class="form-control"
                       value="<?php echo $settings['business_email']; ?>">
            </div>

            <!-- SECOND ROW -->
            <div class="col-md-6 mb-3">
                <label class="form-label">Business Phone</label>
                <input type="text" name="business_phone" class="form-control"
                       value="<?php echo $settings['business_phone']; ?>">
            </div>

            <div class="col-md-12 mb-3">
                <label class="form-label">Business Address</label>
                <textarea name="business_address" class="form-control" rows="3"><?php echo $settings['business_address']; ?></textarea>
            </div>

            <!-- Logo Preview Full Row -->
            <?php if ($settings['receipt_logo']) { ?>
                <div class="col-md-12 mb-3 logo-preview">
                    <label>Current Receipt Logo:</label><br>
                    <img src="<?php echo $settings['receipt_logo']; ?>" height="100">
                </div>
            <?php } ?>

            <!-- Upload Logo Full Row -->
            <div class="col-md-12 mb-3">
                <label class="form-label">Upload New Logo (optional)</label>
                <input type="file" name="receipt_logo" accept="image/*" class="form-control">
                <small class="text-muted">PNG, JPG — Max 1MB</small>
            </div>

        </div>

        <button class="btn btn-primary w-100 mt-2">Save Settings</button>

    </form>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$("#settingsForm").on("submit", function(e){
    e.preventDefault();

    let formData = new FormData(this);

    $.ajax({
        url: "ajax/save_settings.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            $("#message").html(
                `<div class="alert alert-${res.status === 'success' ? 'success' : 'danger'} mt-2">${res.message}</div>`
            );
        },
        error: function(){
            $("#message").html(`<div class="alert alert-danger mt-2">Request failed.</div>`);
        }
    });
});
</script>

<?php
require("foot.php");
?>
