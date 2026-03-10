<?php

namespace App\Http\Controllers\Admin\Shop\Payments;

use Illuminate\Http\Request;
use App\Models\Services\City;
use App\Mail\sendCertificateMsg;
use App\Models\Services\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;


class PayPal
{
    use PaymentTrait;

    protected $error = false;
    public $accessTokenUrl;
    public $orderUrl;
    public $paymentUrl;
    public $clientId;
    public $clientSecret;
    public $requestId;
    public $tokenData;
    public $statuses = [
        'CREATED' => 0,
        'APPROVED' => 1,
        'Authorized' => 1,
        'COMPLETED' => 2,
        'Returned' => 4,
        'PAYER_ACTION_REQUIRED' => 0,
    ];


    public function __construct()
    {
        if (env('PAYPAL_MODE') == 'live') {
            $this->accessTokenUrl = 'https://api-m.paypal.com/v1/oauth2/token';
            $this->orderUrl = 'https://api-m.paypal.com/v2/checkout/orders';
            $this->paymentUrl = 'https://api-m.paypal.com/v2/payments';

            $this->clientId = env('PAYPAL_LIVE_CLIENT_ID', '');
            $this->clientSecret = env('PAYPAL_LIVE_CLIENT_SECRET', '');
        } else {
            $this->accessTokenUrl = 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
            $this->orderUrl = 'https://api-m.sandbox.paypal.com/v2/checkout/orders';
            $this->paymentUrl = 'https://api-m.sandbox.paypal.com/v2/payments';

            $this->clientId = env('PAYPAL_SANDBOX_CLIENT_ID', '');
            $this->clientSecret = env('PAYPAL_SANDBOX_CLIENT_SECRET', '');
        }

        $this->requestId = bin2hex(random_bytes(4)) . "-" . bin2hex(random_bytes(2)) . "-" . bin2hex(random_bytes(2)) . "-" . bin2hex(random_bytes(2)) . "-" . bin2hex(random_bytes(6));

    }

    public function getRefreshToken()
    {
        /// if token exists use it
        $value = Cache::store('file')->get('paypalRefreshToken');
        if ($value) {
            // return $this->tokenData = $value;
        }

        /// if token does not exists get it and use it
        $request = [
            'url' => $this->accessTokenUrl,
            'postFields' => http_build_query(['grant_type' => 'client_credentials']),
            'header' => ['Content-Type: application/x-www-form-urlencoded', 'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret) . '']
        ];
        $response = $this->curlRequest($request);

        if ($response) {
            Cache::put('paypalRefreshToken', $response, 3000);
            $this->tokenData = $response;
        }
        return false;
    }

    public function getTransactionId($params = [])
    {
        $this->getRefreshToken();

        $postData = '{
            "intent": "AUTHORIZE",
            "purchase_units": [
              {
                "amount": {
                    "currency_code": "USD",
                    "value": "100.00"
                }
              }
            ],
            "payment_source": {
                "paypal": {
                    "experience_context": {
                        "payment_method_preference": "IMMEDIATE_PAYMENT_REQUIRED",
                        "payment_method_selected": "PAYPAL",
                        "brand_name": "LTB",
                        "locale": "en-US",
                        "landing_page": "LOGIN",
                        "shipping_preference": "NO_SHIPPING",
                        "user_action": "PAY_NOW",
                        "return_url": "' . $params['returnUrl'] . '",
                        "cancel_url": "' . $params['returnUrl'] . '"
                    }
                }
            }
        }';

        $header = ["PayPal-Request-Id: " . $this->requestId, 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => $this->orderUrl, 'postFields' => $postData, 'header' => $header]);

        if (_cv($response, 'links')) {
            foreach ($response['links'] as $link) {
                if ($link['rel'] == 'payer-action') {
                    $approve_link = $link['href'];
                    break;
                }
            }
        }
        $ret['token'] = $this->tokenData;
        $ret['redirectUrl'] = $approve_link ?? null;
        $ret['transactionId'] = _cv($response, 'id');
        $ret['statusCode'] = _cv($response, 'httpStatusCode');
        $ret['amount'] = _cv($response, 'amount');
        $ret['providerStatus'] = _cv($response, 'status');
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));
        $ret['language'] = _cv($postData, 'language');

        return ['error' => '', 'data' => $ret, 'rawData' => $response];

    }
    public function getStatusType($status = '')
    {
        return isset($this->statuses[$status]) ? $this->statuses[$status] : 0;
    }
    public function getStatus($params = [])
    {
        $this->getRefreshToken();

        $error = '';
        $transactionId = _cv($params, 'transactionId');

        $header = ['Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => "{$this->orderUrl}/{$transactionId}", 'header' => $header, 'method' => 'GET']);

        if (_cv($response, 'status') == 'APPROVED') {
            // Authorize Order
            $header = ["PayPal-Request-Id: " . $this->requestId, 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
            $response = $this->curlRequest(['url' => "{$this->orderUrl}/{$transactionId}/authorize", 'header' => $header, 'method' => 'POST']);
        }

        if (!_cv($response, 'id'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = _cv($response, 'id');
        $ret['amount'] = 6;
        $ret['providerStatus'] = _cv($response, 'status');
        $ret['additional_info'] = _cv($response, 'purchase_units.0.payments.authorizations.0.id');
        if (_cv($response, 'status') == 'COMPLETED') {
            $response['status'] = 'Authorized';
        }
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));
        $ret['statusCode'] = _cv($response, 'httpStatusCode');
        $ret['language'] = '';

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }
    public function charge($params = [])
    {
        $this->getRefreshToken();

        $error = '';
        $payId = _cv($params, 'transactionId');
        $authorizationId = _cv($params, 'authorizationId');

        // $amount = _cv($params, 'amount', 'nn');
        $amount = 0.01;

        $postData = '{
            "amount": {
              "value": "' . $amount . '",
              "currency_code": "USD"
            },
            "invoice_id": "' . $payId . '",
            "final_capture": true,
            "note_to_payer": "Order is Confirmed",
            "soft_descriptor": "Bobs Custom Sweaters"
        }';

        $header = ["PayPal-Request-Id: " . $this->requestId, 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => "{$this->paymentUrl}/authorizations/{$authorizationId}/capture", 'postFields' => $postData, 'header' => $header]);

        if (!_cv($response, 'id'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = _cv($response, 'id');
        $ret['amount'] = 6;
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));
        $ret['statusCode'] = _cv($response, 'httpStatusCode');
        $ret['providerStatus'] = _cv($response, 'status');
        $ret['additional_info'] = _cv($response, 'id');
        $ret['language'] = '';

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }

    public function refund($params = [])
    {
        $this->getRefreshToken();

        $error = '';
        $payId = _cv($params, 'transactionId');
        $authorizationId = _cv($params, 'authorizationId');

        // $amount = _cv($params, 'amount', 'nn');
        $amount = 0.01;

        $postData = '{
            "amount": {
              "value": "' . $amount . '",
              "currency_code": "USD"
            },
            "invoice_id": "' . $payId . '",
            "note_to_payer": "Order is Refunded.",
        }';

        $header = ["PayPal-Request-Id: " . $this->requestId, 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => "{$this->paymentUrl}/authorizations/{$authorizationId}/void", 'postFields' => $postData, 'header' => $header]);

        if (!_cv($response, 'id'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = $payId;
        if (_cv($response, 'httpStatusCode') == 204) {
            $response['status'] = 'Returned';
        }
        $ret['statusCode'] = _cv($response, 'httpStatusCode');
        $ret['providerStatus'] = _cv($response, 'status');
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }
    public function curlRequest($params = [])
    {
        $submitUrl = _cv($params, 'url');
        $postFields = _cv($params, 'postFields');
        $header = (_cv($params, 'header', 'ar')) ? $params['header'] : [];
        $method = _cv($params, 'method') ? $params['method'] : 'POST';

        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_URL => $submitUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $header,
        );
        //p($curlOptions);
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (json_decode($response))
            $response = json_decode($response, true);
        if (!$response) {
            $response = [];
        }
        $response['httpStatusCode'] = $statusCode;

        return $response;

    }
}