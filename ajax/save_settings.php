<?php
header('Content-Type: application/json');

session_start();
require_once("../db.php");

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Sanitize inputs
|--------------------------------------------------------------------------
*/
$name    = trim($_POST['business_name'] ?? '');
$email   = trim($_POST['business_email'] ?? '');
$phone   = trim($_POST['business_phone'] ?? '');
$address = trim($_POST['business_address'] ?? '');

if ($name === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Business name is required.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch existing logo
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT receipt_logo
    FROM businesses
    WHERE owner_user_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);

$currentBusiness = $stmt->fetch(PDO::FETCH_ASSOC);
$currentLogo = $currentBusiness['receipt_logo'] ?? null;

$logoPath = null;

/*
|--------------------------------------------------------------------------
| Handle logo upload
|--------------------------------------------------------------------------
*/
if (
    isset($_FILES['receipt_logo']) &&
    $_FILES['receipt_logo']['error'] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES['receipt_logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Logo upload failed.'
        ]);
        exit;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    $extension = strtolower(
        pathinfo($_FILES['receipt_logo']['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($extension, $allowedExtensions)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Only JPG, JPEG, PNG and WEBP files are allowed.'
        ]);
        exit;
    }

    // 1MB limit
    if ($_FILES['receipt_logo']['size'] > 1024 * 1024) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Logo size must not exceed 1MB.'
        ]);
        exit;
    }

    $uploadDir = __DIR__ . "/../uploads/logos/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName =
        "business_" .
        $userId .
        "_" .
        time() .
        "." .
        $extension;

    $destination = $uploadDir . $newFileName;

    if (!move_uploaded_file(
        $_FILES['receipt_logo']['tmp_name'],
        $destination
    )) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save uploaded logo.'
        ]);
        exit;
    }

    $logoPath = "uploads/logos/" . $newFileName;

    /*
    |--------------------------------------------------------------------------
    | Delete previous logo
    |--------------------------------------------------------------------------
    */
    if (
        !empty($currentLogo) &&
        file_exists(__DIR__ . "/../" . $currentLogo)
    ) {
        @unlink(__DIR__ . "/../" . $currentLogo);
    }
}

/*
|--------------------------------------------------------------------------
| Update business
|--------------------------------------------------------------------------
*/
try {

    if ($logoPath !== null) {

        $sql = "
            UPDATE businesses
            SET
                business_name = ?,
                business_email = ?,
                business_phone = ?,
                business_address = ?,
                receipt_logo = ?
            WHERE owner_user_id = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $name,
            $email,
            $phone,
            $address,
            $logoPath,
            $userId
        ]);

    } else {

        $sql = "
            UPDATE businesses
            SET
                business_name = ?,
                business_email = ?,
                business_phone = ?,
                business_address = ?
            WHERE owner_user_id = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $name,
            $email,
            $phone,
            $address,
            $userId
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Business settings updated successfully.'
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}