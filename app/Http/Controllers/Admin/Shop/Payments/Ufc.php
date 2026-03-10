<?php

namespace App\Http\Controllers\Admin\Shop\Payments;

use App\Mail\sendCertificateMsg;
use App\Models\Services\City;
use App\Models\Services\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Validator;



class Ufc
{

    protected $error = false;
    public $cardUrl = 'https://ecommerce.ufc.ge/ecomm2/ClientHandler';

    ////// method settings
    static $configs = [];

    /** ufc payment methods */
    /** generate transaction and return transaction ID */
    public function getTransactionId($params=[]){
        dd('tbc');
        exit;

        $price = _cv($params, ['price']) * 100;
        $orderId = base64_encode(_psqlupd(_cv($params, ['orderId'])));
//        $price = 1; /// for testing

        if(empty($orderId) || !is_numeric($price))return ['status'=>'error', 'data'=>'wrong certificate offer id' ];

        $params['command'] = isset($params['command'])?$params['command']:'c';

        $post_fields = "command=v&description={$orderId}&amount={$price}";

        $res = $this->curlRequest(['post_fields'=>$post_fields]);

        if(!$res || strpos($res, 'error')!==false){
            return ['status'=>'error', 'data'=>tr('Could not start transaction!'), 'response'=>$res ];
        }

        /// save transaction id to transaction log
        $transactionId = substr($res, -28);

        $transactionIdUrlEncoded = urlencode($transactionId);
        $transactionIdUrlEncoded = $transactionId;

        $submitForm = "<form id='cardSubmitForm' name='returnform' action='https://ecommerce.ufc.ge/ecomm2/ClientHandler' method='POST'><input type='hidden' name='trans_id' value='{$transactionIdUrlEncoded}'>  <input id='cardSubmitFormButton' type='submit' name='submit' value='Submit'> </form>";

        /// return transaction id to front where js will redirect to card payment interface
///        if($ret && strlen($ret) == 28)return response()->json([ 'success'=>'true','bankUrl'=>'https://connect.ge/bankurl', 'transaction_id'=>$ret ]);
        if($transactionId && strlen($transactionId) == 28)
            return [
                'status'=>'success',
                'transaction' => $res,
                'transaction_id' => $transactionId,
                'url'=>$this->cardUrl,
                'orderId'=>$orderId,
                'price'=>$price,
                'submitForm'=>$submitForm,
            ];

        return ['status'=>'error', 'data'=>tr('something went wrong, please try again later!') ];

    }

    public function transactionResponseOk(Request $request){
        $request->trans_id;

//        print 'ok';

//        Mail::to( $city->email )->send('');

        return redirect()->route('certificate.invoice', ['locale' => Session::get('locale'), 'id'=>$request->trans_id]);
    }

    public function transactionResponseFail($params = []){

    }

    /// day close operation ///  must run once in a day
    /// runs in ufcdayclosecrone.php
    public function transactionsDayClose(){
        $post_fields = "command=b";
        $res = $this->curlRequest(['post_fields'=>$post_fields]);
        print_r($res);
        return [0];
    }

    /** get transaction status from ufc */
    public function checkStatus($params = []){

        $post_fields = "command=c&trans_id=".urlencode(_cv($params, 'trans_id'));

        $res = $this->curlRequest(['post_fields'=>$post_fields]);

//        $res = parse_url($res);

//        $res['status'] = _cv($res, 'scheme');
//        $res['message'] = _cv($res, 'path');

//        p($res);
        return $res;

    }

    public function curlRequest($params = []){
//print 2222;

        //        p($params);
//        p($_SERVER);
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

}
