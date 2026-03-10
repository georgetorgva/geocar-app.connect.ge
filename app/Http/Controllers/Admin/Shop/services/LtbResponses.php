<?php

namespace App\Http\Controllers\Admin\Shop\services;

use App;
use App\Http\Controllers\Admin\Shop\Payments\Payment;
use App\Models\Shop\OrderModel;
use Exception;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Silk\ChanelsModel;
use App\Http\Controllers\Admin\User\UserController;


/// https://ltb.connect.ge/api/view/services/ltb/{method-name}
/// request auth: basic auth: $username == 'web_user' && $password == 'Ltb@2022'
class LtbResponses extends App\Http\Controllers\Api\ApiController
{

    private function getImageUrl($imageId = 0, $return = 'raw')
    {
        $mediaModel = new App\Models\Media\MediaModel();
        $ret = $mediaModel->getOne($imageId);

        if ($return == 'raw')
            return $ret;
        if (_cv($ret, $return))
            return $ret[$return];
        return _cv($ret, 'id') ? _cv($ret, 'id') : '';
    }

    public function updateUserInfo($params = [])
    {
        $user = User::where('p_id', $params['IDNumber'])->first();

        if (!$user) {
            return response(['status' => 'error', 'message' => 'User Not Found.']);
        }

        $request = new UserController();
        $request->updPoints($user->id);
        $request->updContragents($user->id);

        return response(['status' => 'success', 'message' => 'Successfuly Updated.']);
    }

    public function updateProducts($products = [])
    {
        if(!_cv($products, '0', 'ar')) return response(['status'=> 'error', 'message' => 'Products Must be in array']);

        $controller = new LtbRequests;

        foreach ($products as $product) {
            $attributes = $controller->getResponseAttributes($product);
            $productRelateAttributes = $controller->updateShopAttributes($attributes);
            $categoryData = $controller->getResponseCategoryData($product);
            $productRelateCategories = $controller->updateShopCategories($categoryData, $productRelateAttributes);
            $productRelateAttributes[$productRelateCategories['attributeTypeId']] = $productRelateCategories['attributes'];
            $stockId = $controller->updateStockData($product);
            $productId = $controller->updateProductData($product, $stockId, $productRelateAttributes);
            if(!is_int($productId)) return $productId;
        }

        return response(['status' => 'success', 'message' => 'Products Updated!']);
    }

    public function updateOrderStatus($params = [])
    {
//        $statuses = ['მიღებულია'=>'order-received', 'მზადდება'=>'processing', 'რეალიზებული'=>'realized'];
        $statuses = ['მიღებულია'=>'order-received', 'მუშავდება'=>'processing', 'გზაშია'=>'shipped', 'დასრულებულია'=>'finished', 'დაბრუნებულია'=>'returned', 'გაუქმებულია'=>'canceled', 'რედაქტირებულია'=>'changed', 'dasrulebuliaredaqtir'=>'finished', 'დასრულებულიარედაქტირ'=>'finished' ];
//        p($params);
        if(!_cv($params, 'order_status'))return ['error'=>'order status not exists'];
        if(!_cv($params, 'order_guid'))return ['error'=>'order GUID not exists'];

        if($params['order_status'] =='მუშავდება' || $params['order_status'] =='გაუქმებულია'){

            $paymentParams['action'] = ($params['order_status'] =='გაუქმებულია')?'refund':'charge';
            $paymentParams['order_guid'] = $params['order_guid'];

            /// if there is amount for partialy charge
            if(_cv($params,['amount'], 'nn')) $paymentParams['amount'] = $params['amount'];

            $status = $this->paymentCharge($paymentParams);
            if(_cv($status, 'error'))response(['error' => $status['error']]);
        }

        $reqParams['remote_guid'] = $params['order_guid'];
        $reqParams['order_status'] = _cv($statuses, $params['order_status'])?$statuses[$params['order_status']]:transliterate($params['order_status']);

        $OrderModel = new App\Models\Shop\OrderModel();

        $res = $OrderModel->updateStatusFromService($reqParams);
//        p($res);

        return response(['status' => 'success', 'message' => 'Products Updated!']);
    }

    public function checkOrderPaymentStatus($params = [])
    {
        $transaction = false;
            if(_cv($params, ['order_id'], 'nn')){
                $transaction = DB::table("shop_transactions")->where("order_id", $params['order_id'])->first();
        }elseif (_cv($params, ['order_guid'])){

                $transaction = DB::table("shop_orders")->
                select('shop_transactions.*')->
                join('shop_transactions', 'shop_transactions.order_id', '=', 'shop_orders.id')->
                where("remote_guid", $params['order_guid'])->first();
        }

        if(!_cv($transaction, 'id', 'nn'))return ['error'=>'order not exists'];


        return response(['status' => $transaction->status]);
    }

    public function paymentCharge($params = [])
    {


        if(!_cv($params, 'action', ['refund', 'charge']) )return ['error'=>'action not set'];

            if(_cv($params, ['order_id'], 'nn')){
                //// continue
        }elseif (_cv($params, ['order_guid'])){
                $order = DB::table("shop_orders")->where("remote_guid", $params['order_guid'])->first();
                $params['order_id'] = _cv($order, 'id', 'nn');
        }

        if(!_cv($params, 'order_id', 'nn'))return ['error'=>'order not exists'];


        $model = new Payment();

        $order = [];
        $orderModel = new OrderModel();
        $order['id'] = $params['id'] = $params['order_id'];

        $conf = config('adminshop.order.order_status');

        $transactionResponse = [];
        if($params['action'] == 'refund'){
            $transactionResponse = $model->transactionRefund($params);
            if(_cv($transactionResponse, 'data.status') == 'returned'){
                $order['order_status'] = $conf[20]; /// refund
            }
        } elseif($params['action'] == 'charge'){
            $transactionResponse = $model->transactionCharge($params);
            if (_cv($transactionResponse, 'data.status') == 'paid') {
                $order['order_status'] = $conf[1]; ///'processing';
            }
        }

        if(!isset($order['order_status']))return ['error'=>'payment status error'];

        $id = $orderModel->updItem($order);
//        p($transactionResponse);
        $res['status'] = _cv($transactionResponse, 'error')?'error':'success';
        $res['transactionId'] = _cv($transactionResponse, 'data.transactionId');
        $res['order_id'] = $params['id'];
        return response($res);
    }

    public function updateConsignmentRate($params = []){
            return true;
    }

}
