<?php

namespace App\Http\Controllers\Admin\Shop\Payments;

use Illuminate\Http\Request;
use App\Models\Shop\OrderModel;
use App\Models\Shop\PaymentModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use Illuminate\Database\Eloquent\Model;

use App\Http\Controllers\Admin\Shop\Payments\Bog;
use App\Http\Controllers\Admin\Shop\Payments\Tbc;


class Payment extends Controller
{
    //
    protected $error = false;
    protected $mainModel;
    protected $provider;
    protected $providerState;
    // static protected $providers = ['tbc'=>'Tbc', 'paypal' => 'paypal', 'bog'=>'Bog']; /// key=>className

    ////// shipping method settings
    public function __construct($params = []){

        $paymentMethod = $params['paymentMethod'] ?? null;

        $this->getProvider($paymentMethod);
        $this->callProoviderClass();

        $this->mainModel = new PaymentModel();

    }

    /**
     * start transaction with provider
     * return transaction id
     */
    public function transactionStart($params = []){

        $transactionUpd['orderId'] = _cv($params, 'orderId');

        $config = config('adminshop.order');
        $params['callbackUrl'] = env('APP_URL')."api/view/cart/transactionStatus?order={$transactionUpd['orderId']}";
        $params['returnUrl'] = _cv($config, 'returnUrl')."?order={$transactionUpd['orderId']}";

        $res = $this->providerState->getTransactionId($params);

        if(!_cv($res, 'data'))return [];
        // p($params);
//         p($res);

        $transactionUpd['provider'] = $this->provider;
        $transactionUpd['userId'] = _cv($params, 'userId');

        $transactionUpd['status'] = isset($config['payment_status'][$res['data']['status']])?$config['payment_status'][$res['data']['status']]:'pending';
        $transactionUpd['transactionId'] = _cv($res, 'data.transactionId');
        $transactionUpd['providerStatusCode'] = _cv($res, 'data.statusCode');
        $transactionUpd['providerStatus'] = _cv($res, 'data.providerStatus');
        $transactionUpd['rawData'] = _cv($res, 'rawData');
        $transactionUpd['totalAmount'] = _cv($res, 'data.amount');
        $transactionUpd['lang'] = _cv($res, 'data.language');
        //p($transactionUpd);

        $payment = new PaymentModel();
        $transaction = $payment->createTransaction($transactionUpd);

        return [
            'status'=> $transactionUpd['status'],
            'providerStatus'=> $transactionUpd['providerStatus'],
            'transactionId'=>$transactionUpd['transactionId'],
            'submitForm'=>_cv($res, 'data.redirectUrl'),
            'redirectUrl'=>_cv($res, 'data.redirectUrl'),
            'orderId'=>_cv($transactionUpd, 'orderId'),
            'error'=> _cv($res, 'data.redirectUrl')?'':'transaction start error'
        ];
    }

    public function transactionStatus($params = []){
        $config = config('adminshop.order');
//        p($config['order_status'][25]);

//        file_put_contents("static/callbackdata".date('Ymdis').".txt", json_encode($params));
        $params['transactionId'] = _cv($params, 'transactionId');
        $res = $this->providerState->getStatus($params);

        if(!_cv($res, 'data'))return [];

        $res['data']['status'] = isset($config['payment_status'][$res['data']['status']])?$config['payment_status'][$res['data']['status']]:'pending';

        $payment = new PaymentModel();
        $transaction = $payment->updateTransaction($res);

        /// if something goes wrong while payment set order status to canceled
        if(array_search($transaction['status'], ['failed']) !== false){
            $orderModel = new OrderModel();
            $orderModel->updateStatus(['statusType' => 'order_status', 'status'=>$config['order_status'][30], 'id'=>$transaction['orderId']]);
        }

        return $res;
    }

    public function transactionCharge($params = []){
        $config = config('adminshop.order');

        $transactionModel = new PaymentModel();
        // $transaction = $transactionModel->getOne(['order_id'=>$params['id'], 'status'=>['pending','processing','paid']]);
        $transaction = $transactionModel->getOne(['order_id'=>$params['id']]);

        $this->provider = $transaction['provider'];
        $this->callProoviderClass();

        if(!_cv($transaction, 'id'))return ['error'=>'processing transaction not exists'];

        $params['transactionId'] = $transaction['provider_transaction_id'];
        $params['authorizationId'] = $transaction['provider_additional_info'];

        /// if there is not requested amount, get full amount from transaction
        if(!_cv($params, ['amount'], 'nn')) $params['amount'] = $transaction['total_amount'];
        $res = $this->providerState->charge($params);

        $res['data']['status'] = isset($config['payment_status'][$res['data']['status']])?$config['payment_status'][$res['data']['status']]:'pending';
        $res['data']['transactionId'] = $transaction['provider_transaction_id'];

        $payment = new PaymentModel();
        $transaction = $payment->updateTransaction($res);

        return $res;
    }

    public function transactionRefund($params = []){
        $config = config('adminshop.order');

        $transactionModel = new PaymentModel();
        $transaction = $transactionModel->getOne(['order_id'=>$params['id']]);

        $this->provider = $transaction['provider'];
        $this->callProoviderClass();

        if(!_cv($transaction, 'id'))return ['error'=>'processing transaction not exists'];

        $params['transactionId'] = $transaction['provider_transaction_id'];
        $params['authorizationId'] = $transaction['provider_additional_info'];
        $params['amount'] = $transaction['total_amount'];
        $res = $this->providerState->refund($params);

        $res['data']['status'] = isset($config['payment_status'][$res['data']['status']])?$config['payment_status'][$res['data']['status']]:'pending';

        $payment = new PaymentModel();
        $transaction = $payment->updateTransaction($res);

        return $res;
    }

    public function transactionReversal($params = []){

    }

    static function transactionResponse($params = []){
        if(!_cv($params, 'trans_id'))return false;
        $transaction = PaymentModel::getTransaction( _cv($params, 'trans_id') );

        if(_cv($params, 'error') || !_cv($transaction, 'id')){
            $upd = [ 'status'=>'fail',      'message'=>$params['error'],    'trans_id'=>$params['trans_id'],    'order_id'=>_cv($transaction, 'order_id') ];
        } else {
            $upd = [ 'status'=>isset($params['status'])?:'no status',   'message'=>isset($params['status'])?:'no status',           'trans_id'=>$params['trans_id'],    'order_id'=>_cv($transaction, 'order_id') ];
        }

        if( _cv($transaction, 'provider') ){
            $providerClass = self::callProoviderClass($transaction['provider']);
            $transactionStatus = $providerClass->checkStatus(['trans_id'=>$transaction['provider_transaction_id']]);
            $transactionStatuses = self::responseToArray($transactionStatus);
//            p($transactionStatuses);
//            $upd['status'] = _cv($transactionStatuses, 'error')?'fail':_cv($transactionStatuses, 'result');
            $upd['status'] = _cv($transactionStatuses, 'result')=='ok'?'success':_cv($transactionStatuses, 'result');
            $upd['message'] = $transactionStatus;
        }

        $upd['status'] = PaymentModel::setTransStatus( $upd );


        $upd['status'] = $upd['status']=='success'?'success':'fail';

        //        p($statuses);
//        p($params);
        return $upd;
    }

    /// for cronJob
    static function transactionsDayClose($params = []){
        if(!_cv($params,['provider']))return false;
        $providerClass = self::callProoviderClass($params['provider']);
        if(!$providerClass)return false;
        return $providerClass->transactionsDayClose();
    }

    private function getProvider($paymentMethod){
        $providers = config('adminshop.generalConfigs.paymentProviders');
        if(in_array($paymentMethod, $providers)){
            $this->provider = $paymentMethod;
        } else {
            $this->provider = $providers[0];
        }
        return $this->provider;
    }

    /// include required provider class
    private function callProoviderClass(){
        if($this->provider=='tbc'){
            $this->providerState = new Tbc();
        }else if($this->provider=='ufc'){
            $this->providerState = new Tbc();
        }else if($this->provider=='bog'){
            $this->providerState = new Bog();
        }else if($this->provider=='paypal'){
            $this->providerState = new PayPal();
        }

        return $this->providerState;
    }


}
