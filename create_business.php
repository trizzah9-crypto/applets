<?php

require "permissions.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: no_access.php");
    exit;
}


?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Business</title>
        <link rel="icon" type="image/png" sizes="192x192" href="./Images/logo.png" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            width: 100%;
            min-height: 100vh;
            background: linear-gradient(to bottom right, #ffffff 0%, #f7f7f7 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .wrapper {
            width: 95%;
            max-width: 1100px;
            background: #ffffff;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.10);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            animation: fadeIn 0.8s ease-out;
        }

        /* LEFT FORM SECTION */
        .left-panel {
            width: 50%;
            padding: 50px 40px;
            animation: slideInLeft 0.8s ease forwards;
        }

        .left-panel h3 {
            font-weight: 700;
            color: #0b3d91;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        label {
            font-weight: 600;
            color: #333;
        }

        .form-control {
            border-radius: 10px;
            padding: 13px;
            border: 1px solid #ddd;
            background: #fafafa;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #0b3d91;
            box-shadow: 0 0 6px rgba(11, 61, 145, 0.3);
            background: #fff;
        }

        .btn-primary {
            background: #0b3d91;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .btn-primary:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 15px rgba(11, 61, 145, 0.3);
        }

        .btn-primary:active {
            transform: scale(0.97);
        }

        #msg {
            font-weight: 600;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            text-align: center;
            display: none;
        }

        /* RIGHT IMAGE SECTION */
        .right-panel {
            width: 50%;
            background: url("images/background.png") no-repeat center center/cover;
            position: relative;
            animation: slideInRight 0.8s ease forwards;
        }

    .confidence-text {
    position: absolute;
    top: 40px;
    left: 40px;
    color: white;
    z-index: 3;
    animation: fadeWords 1s ease-out forwards;
    max-width: 70%;
}

.confidence-text h2 {
    font-size: 2.3rem;
    font-weight: 800;
    margin-bottom: 8px;
    text-shadow: 0 4px 14px rgba(0,0,0);
    letter-spacing: 1.2px;
}

.confidence-text p {
    font-size: 1.25rem;
    font-weight: 500;
    margin-bottom: 12px;
    text-shadow: 0 3px 12px rgba(0,0,0,0.35);
}

.confidence-text h4 {
    font-size: 1.15rem;
    font-weight: 700;
    text-shadow: 0 3px 12px rgba(0,0,0,0.35);
}


@keyframes fadeWords {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}


        /* White soft overlay with shape */
        .right-panel::after {
            content: "";
            position: absolute;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to left, rgba(255,255,255,0.6), rgba(255,255,255,0.1));
            backdrop-filter: blur(3px);
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .right-panel { display: none; }
            .left-panel { width: 100%; }
        }

        /* ANIMATIONS */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>

</head>

<body>

<div class="wrapper">

    <div class="left-panel">
      <img style="width: 150px; margin-left: 50%; transform: translateX(-50%); margin-bottom: 10px;;" src="Images/logo.png" alt="Logo" class="logo" />


        <h3 style="text-align: center;">Register Your Business</h3>

        <div id="msg"></div>

        <form id="bizForm" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label" >Business Name</label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="e.g. B2B Hardware" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Business Type</label>
                <input type="text" id="type" name="type" class="form-control"
                       placeholder="e.g. Hardware, Pharmacy" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description (optional)</label>
                <textarea id="description" name="description" class="form-control" rows="2"
                          placeholder="Short description..."></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Business Logo</label>
                <input
                    type="file"
                    id="receipt_logo"
                    name="receipt_logo"
                    class="form-control"
                    accept="image/png,image/jpeg,image/jpg,image/webp"
                >
                <small class="text-muted">
                    Accepted formats: JPG, PNG, WEBP
                </small>
            </div>

            <button type="submit" class="btn btn-primary">Create Business</button>
            <div class="text-center mt-4">
                <a href="dashboard.php" class="btn btn-secondary" style="background: rgba(11, 61, 145);">⬅ Back to dashboard</a>
            </div>
        </form>
    </div>

    <div class="right-panel" style="position: relative;">
      <div class="confidence-text" style="position:absolute; left: 15%;top: 30%; transform: translateX(-50%); transform: translateY(-50%); text-align: center;">
        <h2>Grow Your Business</h2>
        <p>Simple. Fast. Professional.</p>
        <h4>Secure and Reliable Setup</h4>
      </div>

    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function showMessage(text, type="success") {
    let box = $("#msg");
    box.removeClass().addClass(type === "success" ? "alert alert-success" : "alert alert-danger");
    box.text(text).fadeIn(200);

    setTimeout(() => box.fadeOut(300), 2500);
}

$("#bizForm").on("submit", function(e) {
    e.preventDefault();

    let formData = new FormData(this);

    $.ajax({
        url: "ajax/create_business.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",

        success: function(res) {
            if (res.status === "ok") {
                showMessage("Business created successfully!", "success");

                setTimeout(() => {
                    window.location.href = "business_select.php";
                }, 800);
            } else {
                showMessage(res.message || "Failed to create business", "danger");
            }
        },

        error: function() {
            showMessage("Network error", "danger");
        }
    });
});

</script>

</body>
</html>
