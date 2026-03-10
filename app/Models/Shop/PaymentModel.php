<?php

namespace App\Models\Shop;

use \Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
//use MongoDB\Driver\Session;
use App\Models\Admin\OptionsModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;

class PaymentModel extends Model
{
    protected $table = 'shop_transactions';
    public $timestamps = true;
    protected $error = false;
    protected $meta;

    //
    protected $allAttributes = [
        'id','order_id','user_id','status','provider','provider_transaction_id','provider_result_code','provider_response','total_price','created_at','updated_at'
    ];
    protected $fillable = [
        'order_id','user_id','status','provider','provider_transaction_id','provider_result_code','provider_response','total_price','provider_additional_info'
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function getOne($params = [])
    {
        $params['limit'] = 1;
        $res = $this -> getList($params);
        if (isset($res['list'][0])) return $res['list'][0];
        return [];
    }

    public function getList($params = [])
    {
        DB::enableQueryLog();
//p($params);
        $returnData['listCount'] = 0;
        $returnData['list'] = [];
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;
        $returnData['limit'] = _cv($params, 'limit', 'nn')?$params['limit']:10;
        if(_cv($params, ['sortDirection']) && !_cv($params, ['orderDirection']))$params['orderDirection'] = $params['sortDirection'];
        if(_cv($params, ['sortField']) && !_cv($params, ['orderField']))$params['orderField'] = $params['sortField'];


        $qr =  DB::table($this->table)->select(DB::raw("{$this->table}.* "));

        if (_cv($params, ['id'], 'nn') && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) $qr -> whereIn($this->table.'.id', $params['id']);

        if (_cv($params, ['total_amount']) && !_cv($params, ['total_amount'], 'ar')) $params['total_amount'] = [$params['total_amount']];
        if (_cv($params, 'total_amount', 'ar')) $qr -> whereIn($this->table.'.total_amount', $params['total_amount']);

        if (_cv($params, ['order_id'], 'nn') && !_cv($params, ['order_id'], 'ar')) $params['order_id'] = [$params['order_id']];
        if (_cv($params, 'order_id', 'ar')) $qr -> whereIn($this->table.'.order_id', $params['order_id']);

        if (_cv($params, ['user_id'], 'nn') && !_cv($params, ['user_id'], 'ar')) $params['user_id'] = [$params['user_id']];
        if (_cv($params, 'user_id', 'ar')) $qr -> whereIn($this->table.'.user_id', $params['user_id']);

        if (_cv($params, ['status']) && !_cv($params, ['status'], 'ar')) $params['status'] = [$params['status']];
        if (_cv($params, 'status', 'ar')) $qr -> whereIn($this->table.'.status', $params['status']);

        if (_cv($params, ['provider']) && !_cv($params, ['provider'], 'ar')) $params['provider'] = [$params['provider']];
        if (_cv($params, 'provider', 'ar')) $qr -> whereIn($this->table.'.provider', $params['provider']);

        if (_cv($params, ['provider_transaction_id']) && !_cv($params, ['provider_transaction_id'], 'ar')) $params['provider_transaction_id'] = [$params['provider_transaction_id']];
        if (_cv($params, 'provider_transaction_id', 'ar')) $qr -> whereIn($this->table.'.provider_transaction_id', $params['provider_transaction_id']);

        if (_cv($params, ['provider_result_code']) && !_cv($params, ['provider_result_code'], 'ar')) $params['provider_result_code'] = [$params['provider_result_code']];
        if (_cv($params, 'provider_result_code', 'ar')) $qr -> whereIn($this->table.'.provider_result_code', $params['provider_result_code']);

        if (_cv($params, ['provider_response']) && !_cv($params, ['provider_response'], 'ar')) $params['provider_response'] = [$params['provider_response']];
        if (_cv($params, 'provider_response', 'ar')) $qr -> whereIn($this->table.'.provider_response', $params['provider_response']);


        if(_cv($params, 'limit')) $qr->limit($params['limit']);

        $qr->orderBy('id', 'asc');

        $list = $qr->get();

        $returnData['list'] = _psql(_toArray($list));

//p($returnData['list']);
        $query = DB::getQueryLog();

//        p($query);
//p($returnData);
        return $returnData;
    }



    /// create transaction
    public function createTransaction($params = []){

        DB::enableQueryLog();

//                p($params);
        $upd = new PaymentModel();
        $upd['order_id'] = _cv($params, 'orderId');
        $upd['user_id'] = _cv($params, 'userId', 'nn')?$params['userId']:1;
        $upd['status'] = _cv($params, 'status')?$params['status']:'pending';
        $upd['provider'] = _cv($params, 'provider');
        $upd['provider_transaction_id'] = _cv($params, 'transactionId');
        $upd['provider_result_code'] = _cv($params, 'providerStatusCode');
        $upd['provider_response'] = _cv($params, 'providerStatus');
        $upd['change_log'] = _psqlupd([date('Ymdhis')=>_cv($params, 'rawData')]);
        $upd['total_amount'] = _cv($params, 'totalAmount');
        $upd['lang'] = _cv($params, 'lang');


        $upd->save();


//        $query = DB::getQueryLog(); p($query);

        return $upd->id;
    }




    /// update transaction
    public function updateTransaction($params = []){

        $transactionId = _cv($params, 'data.transactionId');

        $transaction = PaymentModel::where('provider_transaction_id', $transactionId)->first();

        $transaction->change_log = _psqlCell($transaction->change_log);
        if(!is_array($transaction->change_log))$transaction->change_log = [];
        $rawData = $transaction->change_log;_cv($params, 'rawData');
        $rawData[date('Ymdhis')] = _cv($params, 'rawData');

        $transaction->change_log = $rawData;

        $transaction->provider_response = _cv($params, 'data.providerStatus');
        $transaction->status = _cv($params, 'data.status');
        $transaction->provider_additional_info = _cv($params, 'data.additional_info');

        $transaction->save();



        return ['transactionId'=>$transactionId, 'id'=>$transaction->id,'orderId'=>$transaction->order_id, 'status'=>$transaction->status];

    }


    static function getTransaction($transactionId = ''){

        $paymentModel = new PaymentModel();
        $trans = DB::table($paymentModel->table)->where('provider_transaction_id', $transactionId)->first();

        if(isset($trans->id)) return _psqlRow(_toArray($trans));

        return false;

    }

    static function setTransStatus( $params = [] ){
        $timestamp = date('Ymdhis');
        $paymentModel = new PaymentModel();
        $trans = self::getTransaction(_cv($params, 'trans_id'));

        $upd['change_log'] = _cv($trans, 'change_log');
        $upd['change_log'][$timestamp] = _cv($params, 'message');
        $upd['status'] = _cv($params, 'status');

        DB::table($paymentModel->table)->where('id', $trans['id'])->update($upd);

        if(_cv($trans, 'id', 'nn')) return $upd['status'];

        return false;

    }


}
