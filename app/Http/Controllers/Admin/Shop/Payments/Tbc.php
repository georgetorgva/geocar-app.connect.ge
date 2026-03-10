<?php

namespace App\Http\Controllers\Admin\Shop\Payments;

use App\Mail\sendCertificateMsg;
use App\Models\Services\City;
use App\Models\Services\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Validator;



class Tbc
{
    use PaymentTrait;
    //


    protected $error = false;
    public $accessTokenUrl;
    public $paymentUrl;
    public $clientId;
    public $clientSecret;
    public $apiKey;
    public $tokenData;
    public $merchantPaymentId;
    public $statuses = [
        'Created' => 0,
        'Processing' => 0,
        'Succeeded' => 2,
        'Failed' => 3,
        'Expired' => 3,
        'WaitingConfirm' => 1,
        'CancelPaymentProcessing' => 3,
        'PaymentCompletionProcessing' => 1,
        'approved' => 4,
        'PartialReturned' => 2
    ];

    public function __construct()
    {
        $this->accessTokenUrl = env('TBC_ACCESS_TOKEN_URL', 'https://api.tbcbank.ge/v1/tpay/access-token');
        $this->paymentUrl = env('TBC_PAYMENT_URL', 'https://api.tbcbank.ge/v1/tpay/payments');
        $this->clientId = env('TBC_CLIENT_ID', '7001015');
        $this->clientSecret = env('TBC_SECRET', 'akeQOfEszvJ6mmUO');
        $this->apiKey = env('TBC_API_KEY', 'kMMxwRT96sNfuH55KiPrqMCW8KUq4hAD');
        $this->merchantPaymentId = env('TBC_MERCHANT_PAYMENT_ID', 'P123123');

        ///// for testing /// jujuna account
//        $this->accessTokenUrl = env('TBC_ACCESS_TOKEN_URL', 'https://api.tbcbank.ge/v1/tpay/access-token');
//        $this->paymentUrl = env('TBC_PAYMENT_URL', 'https://api.tbcbank.ge/v1/tpay/payments');
//        $this->clientId = env('TBC_CLIENT_ID', '7000308');
//        $this->clientSecret = env('TBC_SECRET', 'e315f5');
//        $this->apiKey = env('TBC_API_KEY', 'LHPDAPAaYDD3KW1f0uLVGaZmG4AeKGNw');
//        $this->merchantPaymentId = env('TBC_MERCHANT_PAYMENT_ID', 'P123123');
    }

    public function getRefreshToken()
    {

        /// if token exists use it
        $value = Cache::store('file')->get('tbcRefreshToken');
        if ($value) {
            // return $this->tokenData = $value;
        }

        /// if token does not exists get it and use it
        $request = [
            'url' => $this->accessTokenUrl,
            'postFields' => http_build_query(['client_id' => $this->clientId, 'client_secret' => $this->clientSecret]),
            'header' => ["apikey: {$this->apiKey}", 'Content-Type: application/x-www-form-urlencoded']
        ];
        $response = $this->curlRequest($request);

        // p($response);

        // response sample /// for testing
        // $response = '{"access_token": "2YotnFZFEjr1zCsicMWpAA","token_type": "Bearer","expires_in": 3600}';

        if ($response) {
            Cache::put('tbcRefreshToken', $response, 3000);
            $this->tokenData = $response;
        }

        return false;
    }

    /** ufc payment methods */
    /** generate transaction and return transaction ID */
    public function getTransactionId($params = []){

        $this->getRefreshToken();

         $postData['totalAmount'] = _cv($params, 'totalAmount', 'nn');
//        $postData['totalAmount'] = 0.01;

        $postData['currency'] = _cv($params, 'currency') ? $params['currency'] : 'GEL';

        $postData['total'] = _cv($params, 'total') ? $params['total'] : 0;
        $postData['subtotal'] = _cv($params, 'subtotal') ? $params['subtotal'] : 0;
        $postData['tax'] = _cv($params, 'tax') ? $params['tax'] : 0;
        $postData['shipping'] = _cv($params, 'shipping') ? $params['shipping'] : 0;

        $postData['returnUrl'] = _cv($params, 'returnUrl') ? $params['returnUrl'] : '/';
        $postData['callbackUrl'] = _cv($params, 'callbackUrl') ? $params['callbackUrl'] : '/';

        $postData['extra'] = _cv($params, 'extra') ? $params['extra'] : '';
        $postData['expirationMinutes'] = _cv($params, 'expirationMinutes') ? $params['expirationMinutes'] : 10;
        $postData['methods'] = _cv($params, 'methods') ? $params['methods'] : 5;
        $postData['preAuth'] = _cv($params, 'preAuth') ? $params['preAuth'] : true;
        $postData['language'] = _cv($params, 'language') ? $params['language'] : 'EN';
        $postData['merchantPaymentId'] = _cv($params, 'merchantPaymentId');
        $postData['skipInfoMessage'] = _cv($params, 'skipInfoMessage') ? true : false;

        $postData = '{
            "amount": {
                "currency":"' . $postData['currency'] . '",
                "total": ' . $postData['totalAmount'] . ',
                "subTotal": ' . $postData['totalAmount'] . ',
                "tax": ' . $postData['tax'] . ',
                "shipping": ' . $postData['shipping'] . '
            },
            "returnurl":"' . $postData['returnUrl'] . '",
            "expirationMinutes" : "' . $postData['expirationMinutes'] . '",
            "methods" : [' . $postData['methods'] . '],
            "callbackUrl":"' . $postData['callbackUrl'] . '",
            "preAuth":true,
            "language":"' . $postData['language'] . '",
            "merchantPaymentId": "' . $this->merchantPaymentId . '"
        }';

//        p($postData); exit;

        $header = ["apikey: {$this->apiKey}", 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => $this->paymentUrl, 'postFields' => $postData, 'header' => $header]);

//p($response);
        $ret['redirectUrl'] = _cv($response, 'links.1.uri');
        $ret['transactionId'] = _cv($response, 'payId');
        $ret['statusCode'] = _cv($response, 'httpStatusCode');
        $ret['amount'] = _cv($response, 'amount');
        $ret['providerStatus'] = _cv($response, 'status');
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));
        $ret['language'] = _cv($postData, 'language');

        return ['error' => '', 'data' => $ret, 'rawData' => $response];

    }

    public function getStatus($params = []){

        $this->getRefreshToken();
        $error = '';
        $postData['payId'] = _cv($params, 'transactionId');

        $header = ["apikey: {$this->apiKey}", 'Content-Type: application/x-www-form-urlencoded', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $header = ["apikey: {$this->apiKey}", "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => "{$this->paymentUrl}/{$postData['payId']}", 'postFields' => $postData, 'header' => $header, 'method' => 'GET']);

        if (!_cv($response, 'payId'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = _cv($response, 'payId');
        $ret['amount'] = _cv($response, 'amount');
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));
        $ret['providerStatus'] = _cv($response, 'status');
        $ret['language'] = _cv($postData, 'language');

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];

    }

    public function charge($params = []){
        $this->getRefreshToken();

        $error = '';
        $payId = _cv($params, 'transactionId');
         $postData['amount'] = _cv($params, 'amount', 'nn');
//        $postData['amount'] = 0.01;

        $postData = '{
            "amount":'.$postData['amount'].'
        }';

        $header = ["apikey: {$this->apiKey}", 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => "{$this->paymentUrl}/{$payId}/completion", 'postFields' => $postData, 'header' => $header]);

        if (!_cv($response, 'payId'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = $payId;
        $ret['amount'] = _cv($response, 'amount');
        $ret['providerStatus'] = _cv($response, 'status');
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }

    public function refund($params = []){
        $this->getRefreshToken();

        $error = '';
        $payId = _cv($params, 'transactionId');
         $postData['amount'] = _cv($params, 'amount', 'nn');
//        $postData['amount'] = 0.01;

        $postData = '{
            "amount":'.$postData['amount'].'
        }';

        $header = ["apikey: {$this->apiKey}", 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];
        $response = $this->curlRequest(['url' => "{$this->paymentUrl}/{$payId}/cancel", 'postFields' => $postData, 'header' => $header]);

        if (!_cv($response, 'payId'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = $payId;
        $ret['amount'] = _cv($response, 'amount');
        $ret['providerStatus'] = _cv($response, 'resultCode');
        $ret['status'] = $this->getStatusType(_cv($response, 'resultCode'));

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }

    public function getStatusType($status = ''){

        return isset($this->statuses[$status]) ? $this->statuses[$status] : 0;
    }

    public function curlRequest($params = []){
        //        p($params);
        /// 'client_id=7000308&client_secret=e315f5',
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
//        p($curlOptions);
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);
        curl_close($curl);
//p($response);

        if (json_decode($response))
            $response = json_decode($response, 1);

        return $response;

    }

}
