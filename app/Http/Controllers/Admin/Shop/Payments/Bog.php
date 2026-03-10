<?php

namespace App\Http\Controllers\Admin\Shop\Payments;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Validator;




class Bog
{
    //


    protected $error = false;
    public $cardUrl = 'https://api.bog.ge/payments/v1/';
    public $clientId = '10000340';
    public $secretKey = 'sOAM4IDLx313';

    ////// method settings
    static $configs = [];



    public $accessTokenUrl;
    public $paymentUrl;
    public $merchantPaymentId;
    public $statuses = [
        'created' => 0,
        'processing' => 0,
        'blocked' => 1,
        'Succeeded' => 2,
        'confirmed' => 2,
        'completed' => 2,
        'Failed' => 3,
        'Expired' => 3,
        'rejected' => 3,
        'WaitingConfirm' => 1,
        'CancelPaymentProcessing' => 3,
        'refund_requested' => 3,
        'refunded' => 3,
        'PaymentCompletionProcessing' => 1,
        'approved' => 4,
        'PartialReturned' => 2,
        'partial_completed' => 2,
        'refunded_partially' => 2
    ];

/**
created - გადახდის მოთხოვნა შექმნილია
processing - გადახდა მუშავდება
completed - გადახდის პროცესი დასრულდა წარმატებით
rejected - გადახდის პროცესი დასრულდა წარუმატებლად
refund_requested - მოთხოვნილია თანხის დაბრუნება
refunded - გადახდის თანხა დაბრუნებულია
refunded_partially - გადახდის თანხა ნაწილობრივ დაბრუნებულია
auth_requested - პრეავტორიზირებული გადახდა მოთხოვნილია
blocked - პრეავტორიზირებული გადახდის პროცესი დასრულდა წარმატებით, თუმცა თანხა ჯერ დაბლოკილია და ელოდება დადასტურებას
partial_completed - პრეავტორიზირებული გადახდა ნაწილობრივ თანხაზე წარმატებით დადასტურდა
 */


    /** generate transaction and return transaction ID */
    public function getTransactionId($params=[]){
//        p($params);
        $accessToken = $this->getAccessToken();
        if(!$accessToken) return false;

        $curl = curl_init();
        curl_setopt_array($curl, array(CURLOPT_URL => "https://api.bog.ge/payments/v1/ecommerce/orders",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization:Bearer $accessToken",
            ),
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                'callback_url' => "{$params['callbackUrl']}",
                'external_order_id' => $params['orderId'],
                'capture' => 'manual',
                'purchase_units' => [
                    'currency' => 'GEL',
                    'total_amount' => $params['totalAmount'],
                    'basket' => [
                        [
                            'quantity' => 1,
                            'unit_price' => $params['totalAmount'],
                            'product_id' => $params['orderId'],
                        ]
                    ]
                ],
                'redirect_urls' => [
                    'fail' => "{$params['returnUrl']}&provider=BOG",
                    'success' => "{$params['returnUrl']}&provider=BOG",
                ]
            ])
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
//        p($response);
        curl_close($curl);


        $ret['redirectUrl'] = _cv($response, '_links.redirect.href');
        $ret['transactionId'] = _cv($response, 'id');
        $ret['statusCode'] = $ret['transactionId']?'pending':'error';
        $ret['amount'] = $params['totalAmount'];
        $ret['providerStatus'] = $ret['statusCode'];
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));
        $ret['language'] = 'ge';

        return ['error' => '', 'data' => $ret, 'rawData' => $response];


        return $response;




    }

    public function getStatus($params = []){
        $accessToken = $this->getAccessToken();
        if(!$accessToken) return false;

        $curl = curl_init();
        curl_setopt_array($curl, array(CURLOPT_URL => "https://api.bog.ge/payments/v1/receipt/{$params['transactionId']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => array(
                "Authorization:Bearer $accessToken",
            ),
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);
//        p($response);

        if (!_cv($response, 'payId'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = _cv($response, 'order_id');
        $ret['amount'] = _cv($response, 'purchase_units.request_amount');
        $ret['status'] = $this->getStatusType(_cv($response, 'order_status.key'));
        $ret['providerStatus'] = _cv($response, 'order_status.key');
        $ret['language'] = _cv($response, 'lang');

        $view['event'] = 'order_payment';
        $view['zoned_request_time'] = date('Y-m-d\TH:i:s.u\Z');
        $view['body']['order_id'] = $ret['transactionId'];

        return ['error' => $error, 'data' => $ret, 'rawData' => $response, 'view' => $view];

    }

    public function chargeaaaaaaaa($params = []){
        $accessToken = $this->getAccessToken();
        if(!$accessToken) return false;


        $error = '';
        $payId = _cv($params, 'transactionId');
        $postData['amount'] = _cv($params, 'amount', 'nn');
//        $postData['amount'] = 0.01;

        $postData = '{
            "amount":'.$postData['amount'].',
            "description": "Ecommerce Order"
        }';

//        $header = ["apikey: {$this->apiKey}", 'Content-Type: application/json', "Authorization: Bearer " . _cv($this->tokenData, 'access_token')];

        $curl = curl_init();
        curl_setopt_array($curl, array(CURLOPT_URL => "https://api.bog.ge/payments/v1/payment/authorization/approve/{$params['transactionId']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => array(
                "Authorization:Bearer $accessToken",
            ),
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POSTFIELDS => $postData

        ));

        $response = curl_exec($curl);
        $response = json_decode($response);
        curl_close($curl);


        if (!_cv($response, 'payId'))
            $error = _cv($response, 'title');

        $ret['transactionId'] = _cv($response, 'id');
        $ret['amount'] = _cv($params,['totalAmount']);
        $ret['providerStatus'] = _cv($response, ['statusCode']);
        $ret['status'] = $this->getStatusType(_cv($response, 'status'));



        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }

    public function charge($params = []){
        $accessToken = $this->getAccessToken();
        if(!$accessToken) return false;


        $error = '';
        $payId = _cv($params, 'transactionId');
        $postData['amount'] = _cv($params, 'amount', 'nn');
//        $postData['amount'] = 0.01;

        $postData = [
            "amount" => $postData['amount'],
            "description" => "Ecommerce Order"
        ];


        $curl = curl_init();

        curl_setopt_array($curl, array(CURLOPT_URL => "https://api.bog.ge/payments/v1/payment/authorization/approve/{$payId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization:Bearer $accessToken",
            ),
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postData)
        ));



        $response = curl_exec($curl);
        $response = json_decode($response, 1);
        curl_close($curl);

        if (!_cv($response, 'action_id'))
            $error = _cv($response, 'message');

        $ret['transactionId'] = _cv($response, 'transactionId');
        $ret['amount'] = _cv($params,['totalAmount']);
        $ret['providerStatus'] = _cv($response, ['key']);
        $ret['status'] = $this->getStatusType('confirmed');

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }
    public function refund($params = []){
        $accessToken = $this->getAccessToken();
        if(!$accessToken) return false;


        $error = '';
        $payId = _cv($params, 'transactionId');
        $postData['amount'] = _cv($params, 'amount', 'nn');
//        $postData['amount'] = 0.01;

        $postData = [
            "description"=>"Ecommerce Order Canceled"
        ];


        $curl = curl_init();

        curl_setopt_array($curl, array(CURLOPT_URL => "https://api.bog.ge/payments/v1/payment/authorization/cancel/{$payId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization:Bearer $accessToken",
            ),
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postData)
        ));



        $response = curl_exec($curl);
        $response = json_decode($response, 1);
        curl_close($curl);

        if (!_cv($response, 'action_id'))
            $error = _cv($response, 'message');

        $ret['transactionId'] = _cv($response, 'transactionId');
        $ret['amount'] = _cv($params,['totalAmount']);
        $ret['providerStatus'] = _cv($response, ['key']);
        $ret['status'] = $this->getStatusType('refund_requested');

        return ['error' => $error, 'data' => $ret, 'rawData' => $response];
    }

    /// day close operation ///  must run once in a day
    /// runs in ufcdayclosecrone.php
    public function transactionsDayClose(){
        $post_fields = "command=b";
        $res = $this->curlRequest(['post_fields'=>$post_fields]);
        print_r($res);
        return [0];
    }

    public function getStatusType($status = ''){

        return isset($this->statuses[$status]) ? $this->statuses[$status] : 0;
    }


    public function curlRequest($params = []){
//print 2222;

        //        p($params);
//        p($_SERVER

        /// find transaction by transaction id
        /// find provider class and load
        /// check if payment is available
        /// return status




        $params['post_fields'] = isset($params['post_fields'])?$params['post_fields']:'xx';

        $curl = curl_init();
        $post_fields = "client_ip_addr=212.72.155.15&language=EN&msg_type=SMS&currency=981&{$params['post_fields']}";
//        $post_fields = "command=a&amount=100&currency=981&client_ip_addr=212.72.155.71&description=UFCTEST&msg_type=SMS";

//        $post_fields = "command=c&trans_id=". $TRX_ID . "&client_ip_addr=212.72.155.71";

        $submit_url = "https://ecommerce.ufc.ge:18443/ecomm2/MerchantHandler";

        $certFile = base_path().'/cert/tbc/ecommerce.ufc.ge_5303037_merchant_wp.pem';
        $certKeyFile = base_path().'/cert/tbc/ecommerce.ufc.ge_5303037_merchant_wp.pem';



        curl_setopt($curl, CURLOPT_SSLVERSION, 1); //0
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($curl, CURLOPT_VERBOSE, '1');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);

        curl_setopt($curl, CURLOPT_SSLCERT,         $certFile );
        curl_setopt($curl, CURLOPT_SSLKEYPASSWD,   'y7tDaJvTyXGeMYwv');
        curl_setopt($curl, CURLOPT_SSLKEY,        $certKeyFile);

        curl_setopt($curl, CURLOPT_URL, $submit_url);
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
//        p($info);

        if(curl_errno($curl))
        {
            echo 'curl error: ' . curl_error($curl);
            return false;
        }

        curl_close($curl);
        $curl = curl_init();

//        p($result);
        return $result;

    }

    protected function getAccessToken()
    {
//        print "{$this->clientId}:{$this->secretKey}";
        $curl = curl_init();
        curl_setopt_array($curl, array(
                CURLOPT_URL => "https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type:application/x-www-form-urlencoded"
                ),
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "{$this->clientId}:{$this->secretKey}",
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
            )
        );

        $accessTokenResponse = curl_exec($curl);
//        p($accessTokenResponse);
        $accessTokenResponse = json_decode($accessTokenResponse, true);
        curl_close($curl);


        if(_cv($accessTokenResponse,['access_token'])){
            return $accessTokenResponse['access_token'];
        }else{
            p($accessTokenResponse);
            return false;
        }

    }

}
