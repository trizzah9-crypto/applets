<?php
require "permissions.php";

if (!can('manage_settings')) {
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
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$_SESSION['business_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $settings = [
        'business_name' => '',
        'business_email' => '',
        'business_phone' => '',
        'business_address' => '',
        'receipt_logo' => ''
    ];
}

$businessName = htmlspecialchars($settings['business_name'] ?? '');
$businessEmail = htmlspecialchars($settings['business_email'] ?? '');
$businessPhone = htmlspecialchars($settings['business_phone'] ?? '');
$businessAddress = htmlspecialchars($settings['business_address'] ?? '');
$receiptLogo = htmlspecialchars($settings['receipt_logo'] ?? '');
?>

<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">


<style>
    :root {
        --primary: #054960;
        --primary-light: #0a5d78;
        --primary-accent: #0f7c9d;
        --orange: #ff7900;
        --orange-light: #ff9d3f;
        --bg: #f4f8fb;
        --glass: rgba(255, 255, 255, 0.72);
        --radius-lg: 18px;
        --radius-xl: 24px;
        --radius-2xl: 28px;
        --shadow-sm: 0 10px 30px rgba(5, 73, 96, 0.08);
        --shadow-md: 0 20px 45px rgba(5, 73, 96, 0.12);
        --shadow-lg: 0 30px 80px rgba(5, 73, 96, 0.18);
        --text-dark: #0f172a;
        --text-muted: #64748b;
    }

    * {
        box-sizing: border-box;
    }

    body {
        min-height: 100vh;
        margin: 0;
        background:
            radial-gradient(circle at top left, rgba(15, 124, 157, 0.08), transparent 30%),
            radial-gradient(circle at top right, rgba(255, 157, 63, 0.08), transparent 30%),
            linear-gradient(180deg, #f8fbfd, #f4f8fb);
        color: var(--text-dark);
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .page-wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px 16px 40px;
    }

    .hero {
        background: linear-gradient(135deg, var(--primary), var(--primary-light), var(--primary-accent));
        color: #fff;
        border-radius: 30px;
        padding: 28px 30px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
    }

    .hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 15% 20%, rgba(255, 157, 63, 0.20), transparent 22%),
            radial-gradient(circle at 85% 10%, rgba(255, 255, 255, 0.12), transparent 18%);
        pointer-events: none;
    }

    .hero-inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        justify-content: space-between;
        align-items: flex-start;
    }

    .hero h2 {
        margin: 0;
        font-size: clamp(1.5rem, 2.5vw, 2.2rem);
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .hero p {
        margin: 8px 0 0;
        opacity: 0.88;
        max-width: 700px;
    }

    .hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.14);
        font-size: 0.92rem;
        white-space: nowrap;
    }

    .settings-shell {
        background: var(--glass);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border: 1px solid rgba(255, 255, 255, 0.42);
        border-radius: 30px;
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 0;
    }

    .settings-main {
        padding: 34px;
    }

    .settings-side {
        padding: 34px;
        border-left: 1px solid rgba(5, 73, 96, 0.08);
        background: linear-gradient(180deg, rgba(5, 73, 96, 0.03), rgba(15, 124, 157, 0.04));
    }

    .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
    }

    .section-title h5 {
        margin: 0;
        font-weight: 800;
        color: var(--text-dark);
    }

    .section-title small {
        display: block;
        color: var(--text-muted);
        margin-top: 4px;
    }

    .form-label {
        font-weight: 700;
        color: #334155;
        margin-bottom: 8px;
    }

    .form-control,
    .form-control:focus,
    textarea.form-control {
        border-radius: 16px;
        min-height: 54px;
        border: 1px solid rgba(5, 73, 96, 0.15);
        transition: all 0.25s ease;
        background: #fff;
        padding: 12px 14px;
        box-shadow: none;
    }

    textarea.form-control {
        min-height: 140px;
        resize: vertical;
    }

    .form-control:focus,
    textarea.form-control:focus {
        border-color: var(--primary-accent);
        box-shadow: 0 0 0 0.25rem rgba(15, 124, 157, 0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-light), var(--primary-accent));
        border: none;
        border-radius: 18px;
        min-height: 56px;
        font-weight: 700;
        box-shadow: 0 15px 35px rgba(15, 124, 157, 0.25);
        transition: 0.25s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 40px rgba(15, 124, 157, 0.30);
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
    }

    .btn-outline-secondary {
        border-radius: 18px;
        min-height: 56px;
        font-weight: 700;
    }

    #message {
        border-radius: 16px;
        font-weight: 700;
        display: none;
        margin-bottom: 18px;
    }

    .preview-card {
        border-radius: 24px;
        padding: 18px;
        background: linear-gradient(135deg, rgba(5, 73, 96, 0.06), rgba(15, 124, 157, 0.08));
        border: 1px solid rgba(5, 73, 96, 0.10);
        box-shadow: var(--shadow-sm);
        margin-bottom: 18px;
    }

    .preview-label {
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-muted);
        font-weight: 700;
        margin-bottom: 8px;
    }

    .preview-value {
        font-size: 1rem;
        font-weight: 800;
        color: var(--primary);
        word-break: break-word;
    }

    .logo-preview {
        border-radius: 22px;
        padding: 18px;
        background: #fff;
        border: 1px solid rgba(5, 73, 96, 0.08);
        box-shadow: var(--shadow-sm);
    }

    .logo-preview img {
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        max-width: 100%;
        height: auto;
    }

    .info-box {
        border-radius: 22px;
        padding: 18px;
        background: linear-gradient(135deg, rgba(255, 121, 0, 0.10), rgba(255, 157, 63, 0.14));
        border: 1px solid rgba(255, 121, 0, 0.14);
        color: #7c2d12;
    }

    .info-box h6 {
        font-weight: 800;
        margin-bottom: 8px;
    }

    .info-box p {
        margin: 0;
        color: #9a3412;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 22px;
    }

    .form-actions .btn {
        flex: 1 1 220px;
    }

    .upload-note {
        font-size: 0.88rem;
        color: var(--text-muted);
        margin-top: 6px;
    }

    @media (max-width: 992px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }

        .settings-side {
            border-left: 0;
            border-top: 1px solid rgba(5, 73, 96, 0.08);
        }
    }

    @media (max-width: 768px) {
        .page-wrap {
            padding: 14px 12px 28px;
        }

        .hero {
            padding: 22px 18px;
            border-radius: 24px;
        }

        .settings-main,
        .settings-side {
            padding: 20px 16px;
        }

        .settings-shell {
            border-radius: 24px;
        }

        .form-actions .btn {
            flex: 1 1 100%;
        }
    }
</style>


</head>

<body>
<div class="page-wrap">


<div class="hero">
    <div class="hero-inner">
        <div>
            <h2>Business Settings</h2>
            <p>Update your company identity, contact details, and receipt branding from one premium admin panel.</p>
        </div>
        <!-- <div class="hero-pill">
            <span>Owner ID</span>
            <strong><?= htmlspecialchars((string)$userId) ?></strong>
        </div> -->
    </div>
</div>

<div id="message"></div>

<div class="settings-shell">
    <div class="settings-grid">
        <div class="settings-main">
            <div class="section-title">
                <div>
                    <h5>Profile Details</h5>
                    <small>Core business information used across invoices and receipts.</small>
                </div>
            </div>

            <form id="settingsForm" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Business Name</label>
                        <input type="text" name="business_name" class="form-control" value="<?= $businessName ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Business Email</label>
                        <input type="email" name="business_email" class="form-control" value="<?= $businessEmail ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Business Phone</label>
                        <input type="text" name="business_phone" class="form-control" value="<?= $businessPhone ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Business Address</label>
                        <textarea name="business_address" class="form-control" rows="4"><?= $businessAddress ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <button type="reset" class="btn btn-outline-secondary">Reset</button>
                </div>
            </form>
        </div>

        <div class="settings-side">
            <div class="section-title">
                <div>
                    <h5>Receipt Branding</h5>
                    <small>Manage the logo that appears on printed receipts.</small>
                </div>
            </div>

            <div class="preview-card">
                <div class="preview-label">Current Business Name</div>
                <div class="preview-value"><?= $businessName !== '' ? $businessName : 'Not set yet' ?></div>
            </div>

            <div class="preview-card">
                <div class="preview-label">Current Contact Email</div>
                <div class="preview-value"><?= $businessEmail !== '' ? $businessEmail : 'Not set yet' ?></div>
            </div>

            <div class="info-box mb-3">
                <h6>Upload guidance</h6>
                <p>Use PNG or JPG. Keep the file under 1MB for best receipt printing results.</p>
            </div>

            <?php if (!empty($receiptLogo)) { ?>
                <div class="logo-preview mb-3">
                    <div class="preview-label">Current Receipt Logo</div>
                    <img src="<?= $receiptLogo ?>" alt="Receipt Logo">
                </div>
            <?php } else { ?>
                <div class="logo-preview mb-3">
                    <div class="preview-label">Current Receipt Logo</div>
                    <div class="text-muted">No logo uploaded yet.</div>
                </div>
            <?php } ?>

            <form id="logoForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Upload New Logo (optional)</label>
                    <input type="file" name="receipt_logo" accept="image/*" class="form-control">
                    <div class="upload-note">This will replace the current receipt logo if a file is selected.</div>
                </div>
            </form>
        </div>
    </div>
</div>


</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$("#settingsForm").on("submit", function(e){
    e.preventDefault();

    let formData = new FormData(this);

    const fileInput = $("input[name='receipt_logo']")[0];
    if (fileInput && fileInput.files && fileInput.files.length > 0) {
        formData.append("receipt_logo", fileInput.files[0]);
    }

    $.ajax({
        url: "ajax/save_settings.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(res){
            const ok = res.status === 'success' || res.status === 'ok';

            $("#message").html(
                `<div class="alert alert-${ok ? 'success' : 'danger'} mt-2 mb-0">${res.message || 'Done'}</div>`
            ).fadeIn();

            if (ok) {
                setTimeout(function(){
                    location.reload();
                }, 1200);
            }
        },
        error: function(){
            $("#message").html(`<div class="alert alert-danger mt-2 mb-0">Request failed.</div>`).fadeIn();
        }
    });
});
</script>

<?php require("foot.php"); ?>

</body>
</html>
