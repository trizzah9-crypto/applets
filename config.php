<?php

require_once 'db.php';

/*
|--------------------------------------------------------------------------
| Pesapal Configuration
|--------------------------------------------------------------------------
*/

define('PESAPAL_CONSUMER_KEY', 'YiOeStLbTn8vuaVClU2GHqKZf0TwfDnA');
define('PESAPAL_CONSUMER_SECRET', 'ROjEfku+YjGv3uzbmhnx+zBHHV4=');

define('PESAPAL_BASE_URL', 'https://pay.pesapal.com/v3');

define('PESAPAL_IPN_ID', '7b12ce52-5741-4162-b49c-da30238c41fe');

define('CALLBACK_URL', 'https://mamba.francobridgeinterpretations.com/callback.php');

define('IPN_URL', 'https://mamba.francobridgeinterpretations.com/ipn.php');

define('BUSINESS_NAME', 'MAMBA SYSTEMS');

define('CURRENCY', 'KES');


/**
 * Get Pesapal Access Token
 *
 * @return string
 * @throws Exception
 */
function getPesapalToken()
{
    $url = PESAPAL_BASE_URL . '/api/Auth/RequestToken';

    $payload = [
        'consumer_key'    => PESAPAL_CONSUMER_KEY,
        'consumer_secret' => PESAPAL_CONSUMER_SECRET
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30

    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode != 200) {
        throw new Exception($response);
    }

    if (!isset($result['token'])) {
        throw new Exception('Failed to obtain Pesapal access token.');
    }

    return $result['token'];
}