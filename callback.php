<?php

require_once("config.php");

try {

    if (!isset($_GET['OrderTrackingId'])) {
        die("Missing OrderTrackingId");
    }

    $orderTrackingId = trim($_GET['OrderTrackingId']);

    // Get Access Token
    $token = getPesapalToken();

    // Verify payment with Pesapal
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
        die(curl_error($curl));
    }

    curl_close($curl);

    $payment = json_decode($response, true);

    if (!$payment) {
        die("Invalid response from Pesapal.");
    }

    /*
    -----------------------------------------
    Update payment table
    -----------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE payments
        SET
            status = ?,
            payment_method = ?,
            confirmation_code = ?
        WHERE order_tracking_id = ?
    ");

    $stmt->execute([

        $payment['payment_status_description'] ?? 'UNKNOWN',

        $payment['payment_method'] ?? '',

        $payment['confirmation_code'] ?? '',

        $orderTrackingId

    ]);

    /*
    -----------------------------------------
    If payment succeeded
    -----------------------------------------
    */

    if (
        isset($payment['payment_status_description']) &&
        strtoupper($payment['payment_status_description']) == "COMPLETED"
    ) {

        /*
        Find payment
        */

        $stmt = $conn->prepare("
            SELECT *
            FROM payments
            WHERE order_tracking_id=?
            LIMIT 1
        ");

        $stmt->execute([$orderTrackingId]);

        $pay = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pay) {

            /*
            Get business
            */

            $stmt = $conn->prepare("
                SELECT *
                FROM businesses
                WHERE id=?
                LIMIT 1
            ");

            $stmt->execute([$pay['business_id']]);

            $business = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($business) {

                /*
                Determine subscription days
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

                $now = new DateTime();

                if (
                    !empty($business['subscription_expires_at']) &&
                    $business['subscription_expires_at'] > date("Y-m-d H:i:s")
                ) {

                    $expiry = new DateTime(
                        $business['subscription_expires_at']
                    );

                } else {

                    $expiry = clone $now;

                }

                $expiry->modify("+{$days} days");

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

            }

        }

    }

    header("Location: subscription.php?payment=success");

    exit;

} catch(Exception $e){

    die($e->getMessage());

}