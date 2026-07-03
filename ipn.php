<?php

require_once("config.php");

/*
|--------------------------------------------------------------------------
| Pesapal IPN Handler
|--------------------------------------------------------------------------
|
| This page is called directly by Pesapal.
| It verifies the transaction and activates the subscription.
|
*/

try {

    if (!isset($_GET['OrderTrackingId'])) {
        http_response_code(400);
        exit("Missing OrderTrackingId");
    }

    $orderTrackingId = trim($_GET['OrderTrackingId']);

    /*
    -----------------------------------------
    Get Access Token
    -----------------------------------------
    */

    $token = getPesapalToken();

    /*
    -----------------------------------------
    Verify Payment
    -----------------------------------------
    */

    $url = PESAPAL_BASE_URL .
        "/api/Transactions/GetTransactionStatus?orderTrackingId=" .
        urlencode($orderTrackingId);

    $curl = curl_init($url);

    curl_setopt_array($curl, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [

            "Authorization: Bearer ".$token,

            "Accept: application/json"

        ]

    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new Exception(curl_error($curl));
    }

    curl_close($curl);

    $payment = json_decode($response, true);

    if (!$payment) {
        throw new Exception("Invalid response from Pesapal.");
    }

    $status = strtoupper($payment['payment_status_description'] ?? 'PENDING');

    /*
    -----------------------------------------
    Update payment record
    -----------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE payments
        SET
            status=?,
            payment_method=?,
            confirmation_code=?
        WHERE order_tracking_id=?
    ");

    $stmt->execute([

        $status,

        $payment['payment_method'] ?? '',

        $payment['confirmation_code'] ?? '',

        $orderTrackingId

    ]);

    /*
    -----------------------------------------
    Only continue if COMPLETED
    -----------------------------------------
    */

    if ($status != "COMPLETED") {

        http_response_code(200);

        exit("Payment Pending");

    }

    /*
    -----------------------------------------
    Load Payment
    -----------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT *
        FROM payments
        WHERE order_tracking_id=?
        LIMIT 1
    ");

    $stmt->execute([$orderTrackingId]);

    $pay = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pay) {

        throw new Exception("Payment not found.");

    }

    /*
    -----------------------------------------
    Prevent Double Processing
    -----------------------------------------
    */

    if ($pay['status'] === 'ACTIVATED') {

        http_response_code(200);

        exit("Already Processed");

    }

    /*
    -----------------------------------------
    Find Business
    -----------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT *
        FROM businesses
        WHERE id=?
        LIMIT 1
    ");

    $stmt->execute([$pay['business_id']]);

    $business = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$business) {

        throw new Exception("Business not found.");

    }

    /*
    -----------------------------------------
    Determine Subscription
    -----------------------------------------
    */

    switch ($pay['amount']) {

        case 3000:
            $days = 365;
            $plan = "yearly";
            break;

        case 300:
            $days = 30;
            $plan = "monthly";
            break;

        default:
            $days = 7;
            $plan = "weekly";

    }

    /*
    -----------------------------------------
    Calculate Expiry
    -----------------------------------------
    */

    $now = new DateTime();

    if (
        !empty($business['subscription_expires_at']) &&
        strtotime($business['subscription_expires_at']) > time()
    ) {

        $expiry = new DateTime($business['subscription_expires_at']);

    } else {

        $expiry = clone $now;

    }

    $expiry->modify("+{$days} days");

    /*
    -----------------------------------------
    Activate Subscription
    -----------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE businesses
        SET
            subscription_plan=?,
            subscription_expires_at=?
        WHERE id=?
    ");

    $stmt->execute([

        $plan,

        $expiry->format("Y-m-d H:i:s"),

        $business['id']

    ]);

    /*
    -----------------------------------------
    Mark Payment Activated
    -----------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE payments
        SET status='ACTIVATED'
        WHERE id=?
    ");

    $stmt->execute([$pay['id']]);

    http_response_code(200);

    echo "OK";

} catch(Exception $e){

    http_response_code(500);

    echo $e->getMessage();

}