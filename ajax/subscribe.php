<?php

header('Content-Type: application/json');
session_start();

require_once("../db.php"); // your PDO SQLite connection in $conn
require_once("../config.php");

// SECRET KEY
$SECRET_KEY = "0700000000";  // change to your own secret key

$plans = [
    'yearly'  => ['label' => 'Yearly Plan', 'price' => 3000, 'days' => 365, 'interval' => 'year'],
    'monthly' => ['label' => 'Monthly Plan', 'price' => 300,  'days' => 30,  'interval' => 'month'],
    'weekly'  => ['label' => 'Weekly Plan', 'price' => 80,   'days' => 7,   'interval' => 'week'],
];

$planKey = $_POST['plan'] ?? '';
$phone   = trim($_POST['phone'] ?? '');
$userId  = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (!isset($plans[$planKey])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid plan']);
    exit;
}

if (!$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Phone required']);
    exit;
}

if (!preg_match('/^\d{9,12}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
    exit;
}

// ---------------------------------------
// STEP 1: SECRET KEY CHECK
// ---------------------------------------
if ($phone === $SECRET_KEY) {

    $daysToAdd = $plans[$planKey]['days'];

    // Fetch current expiry
    $stmt = $conn->prepare("SELECT subscription_expires_at FROM businesses WHERE owner_user_id = :userId");
    $stmt->execute(['userId' => $userId]);
    $currentExpiry = $stmt->fetchColumn();

    $now = new DateTime();

    if ($currentExpiry && $currentExpiry !== "0000-00-00 00:00:00") {
        $expiryDate = new DateTime($currentExpiry);

        if ($expiryDate > $now) {
            // Extend from current expiry
            $expiryDate->modify("+{$daysToAdd} days");
        } else {
            // Expired → start from now
            $expiryDate = (clone $now)->modify("+{$daysToAdd} days");
        }
    } else {
        // No subscription → start now
        $expiryDate = (clone $now)->modify("+{$daysToAdd} days");
    }

    $newExpiry = $expiryDate->format("Y-m-d H:i:s");

    // UPDATE
    $updateStmt = $conn->prepare("UPDATE businesses SET subscription_plan = :planKey, subscription_expires_at = :newExpiry WHERE owner_user_id = :userId");
    $updateStmt->execute([
        'planKey' => $planKey,
        'newExpiry' => $newExpiry,
        'userId' => $userId
    ]);

    echo json_encode([
        'status' => 'success',
        'plan_label' => $plans[$planKey]['label'],
        'price' => $plans[$planKey]['price'],
        'interval' => $plans[$planKey]['interval'],
        'expiry' => $expiryDate->format("F j, Y")
    ]);
    exit;
}

// ---------------------------------------
// SECRET KEY DID NOT MATCH → GO TO PAYMENT
// ---------------------------------------
// ---------------------------------------
// CREATE PESAPAL PAYMENT
// ---------------------------------------

require_once("../config.php");

try {

    // Get logged in business
    $stmt = $conn->prepare("
        SELECT id,business_name,email
        FROM businesses
        WHERE owner_user_id=?
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $business = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$business){
        throw new Exception("Business not found.");
    }

    $merchant_reference = "SUB-" . uniqid();

    // Save payment
    $stmt = $conn->prepare("
    INSERT INTO payments
    (
        business_id,
        merchant_reference,
        customer_name,
        customer_email,
        customer_phone,
        amount,
        currency,
        status
    )
    VALUES
    (
        ?,?,?,?,?,?,?,
        'PENDING'
    )
    ");

    $stmt->execute([

        $business['id'],

        $merchant_reference,

        $business['business_name'],

        $business['email'],

        $phone,

        $plans[$planKey]['price'],

        CURRENCY

    ]);

    // Get Token

    $token = getPesapalToken();

    $payload = [

        "id"=>$merchant_reference,

        "currency"=>CURRENCY,

        "amount"=>$plans[$planKey]['price'],

        "description"=>$plans[$planKey]['label']." Subscription",

        "callback_url"=>CALLBACK_URL,

        "notification_id"=>PESAPAL_IPN_ID,

        "billing_address"=>[

            "email_address"=>$business['email'],

            "phone_number"=>$phone,

            "country_code"=>"KE",

            "first_name"=>$business['business_name'],

            "last_name"=>""

        ]

    ];

    $ch = curl_init(PESAPAL_BASE_URL."/api/Transactions/SubmitOrderRequest");

    curl_setopt_array($ch,[

        CURLOPT_RETURNTRANSFER=>true,

        CURLOPT_POST=>true,

        CURLOPT_HTTPHEADER=>[

            "Authorization: Bearer ".$token,

            "Content-Type: application/json"

        ],

        CURLOPT_POSTFIELDS=>json_encode($payload)

    ]);

    $response = curl_exec($ch);

    if(curl_errno($ch)){
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $response = json_decode($response,true);

    if(isset($response['order_tracking_id'])){

        $stmt = $conn->prepare("
            UPDATE payments
            SET order_tracking_id=?
            WHERE merchant_reference=?
        ");

        $stmt->execute([

            $response['order_tracking_id'],

            $merchant_reference

        ]);

        echo json_encode([

            "status"=>"payment_required",

            "redirect_url"=>$response['redirect_url']

        ]);

        exit;

    }

    echo json_encode([

        "status"=>"error",

        "message"=>$response

    ]);

} catch(Exception $e){

    echo json_encode([

        "status"=>"error",

        "message"=>$e->getMessage()

    ]);

}

exit;
