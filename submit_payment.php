<?php

require_once 'config.php';

/*
|--------------------------------------------------------------------------
| Demo Data
|--------------------------------------------------------------------------
| Replace these values with data from your POS checkout.
*/

$customer_name  = "John Doe";
$customer_email = "john@example.com";
$customer_phone = "254712345678";
$amount         = 1000.00;

$merchant_reference = "MAMBA-" . time();

/*
|--------------------------------------------------------------------------
| Save Pending Payment
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        INSERT INTO payments
        (
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
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'PENDING'
        )
    ");

    $stmt->execute([
        $merchant_reference,
        $customer_name,
        $customer_email,
        $customer_phone,
        $amount,
        CURRENCY
    ]);

} catch (PDOException $e) {

    die("Database Error: " . $e->getMessage());

}


/*
|--------------------------------------------------------------------------
| Get Access Token
|--------------------------------------------------------------------------
*/

$token = getPesapalToken();


/*
|--------------------------------------------------------------------------
| Submit Order
|--------------------------------------------------------------------------
*/

$url = PESAPAL_BASE_URL . "/api/Transactions/SubmitOrderRequest";

$data = [

    "id" => $merchant_reference,

    "currency" => CURRENCY,

    "amount" => $amount,

    "description" => "Payment for Order " . $merchant_reference,

    "callback_url" => CALLBACK_URL,

    "notification_id" => PESAPAL_IPN_ID,

    "billing_address" => [

        "email_address" => $customer_email,

        "phone_number" => $customer_phone,

        "country_code" => "KE",

        "first_name" => $customer_name,

        "last_name" => ""

    ]

];

$curl = curl_init($url);

curl_setopt_array($curl, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [

        "Authorization: Bearer " . $token,

        "Content-Type: application/json"

    ],

    CURLOPT_POSTFIELDS => json_encode($data)

]);

$response = curl_exec($curl);

if (curl_errno($curl)) {

    die("cURL Error: " . curl_error($curl));

}

curl_close($curl);

$result = json_decode($response, true);


/*
|--------------------------------------------------------------------------
| Check Response
|--------------------------------------------------------------------------
*/

if (isset($result['order_tracking_id'])) {

    $update = $conn->prepare("
        UPDATE payments
        SET order_tracking_id = ?
        WHERE merchant_reference = ?
    ");

    $update->execute([

        $result['order_tracking_id'],

        $merchant_reference

    ]);

    header("Location: " . $result['redirect_url']);
    exit;

}

echo "<h3>Pesapal Response</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";

?>