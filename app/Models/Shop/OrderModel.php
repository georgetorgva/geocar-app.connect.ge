<?php

namespace App\Models\Shop;

use App\Http\Controllers\Admin\Shop\services\Email;
use App\Models\Admin\SmartTableModel;
use \Validator;
use App\Models\User\User;
use Illuminate\Support\Str;
use App\Mail\SendBuyerEmail;
use Illuminate\Http\Request;
use App\Models\Admin\OptionsModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendBuyerEmailComponent;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Admin\Shop\Cart;
use function Symfony\Component\String\padStart;

class OrderModel extends SmartTableModel
{
    protected $table = 'shop_orders';
    public $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs = 'adminshop.order';


    //
    protected $allAttributes = [
        'id',
        'created_at',
        'updated_at',
        'total_amount',
        'order_status',
        'shipping_status',
        'user_id',
        'logistic_type',
        'shipping_price',
        'meta_info',
        'cart_info',
        'remote_order_id',
        'remote_guid',
        'remote_response'
    ];
    protected $fillable = [
        'id',
        'created_at',
        'updated_at',
        'total_amount',
        'order_status',
        'shipping_status',
        'user_id',
        'logistic_type',
        'shipping_price',
        'meta_info',
        'cart_info',
        'remote_order_id',
        'remote_guid',
        'remote_response'
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];


    public function _getOne($params = [])
    {
        $params['limit'] = 1;
        $res = $this -> getList($params);
        if (isset($res['list'][0])) return $res['list'][0];
        return [];
    }

    public function _getList($params = [])
    {
        DB::enableQueryLog();
        $conf = config('adminshop.order');

        $returnData['listCount'] = 0;
        $returnData['list'] = [];
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;
        $params['limit'] = _cv($params, ['limit'], 'nn') && $params['limit'] <= _cv($conf, ['getList','maxListLimit'], 'nn') ?$params['limit']:10;

        if(_cv($params, ['sortDirection']) && !_cv($params, ['orderDirection']))$params['orderDirection'] = $params['sortDirection'];
        if(_cv($params, ['sortField']) && !_cv($params, ['orderField']))$params['orderField'] = $params['sortField'];

        if(!_cv($params, ['status']))$params['status'] = 'published';

        $selectPart[] = "{$this->table}.* ";
        $selectPart[] = "users.fullname";

        $selectPart[] = "shop_transactions.status as payment_status";
        $selectPart[] = "shop_transactions.provider_response as payment_provider_status";
        $selectPart[] = "shop_transactions.provider as payment_provider";
        $selectPart[] = "shop_transactions.provider_transaction_id as transaction_id";

        $selectPart = implode(',', $selectPart);
        $qr =  DB::table($this->table)->select(DB::raw($selectPart));

        if (_cv($params, ['id'], 'nn') && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) $qr -> whereIn($this->table.'.id', $params['id']);

        if (_cv($params, ['total_amount']) && !_cv($params, ['total_amount'], 'ar')) $params['total_amount'] = [$params['total_amount']];
        if (_cv($params, 'total_amount', 'ar')) $qr -> whereIn($this->table.'.total_amount', $params['total_amount']);

        if (_cv($params, ['order_status']) && !_cv($params, ['order_status'], 'ar')) $params['order_status'] = [$params['order_status']];
        if (_cv($params, 'order_status', 'ar')) $qr -> whereIn($this->table.'.order_status', $params['order_status']);

        if (_cv($params, ['shipping_status']) && !_cv($params, ['shipping_status'], 'ar')) $params['shipping_status'] = [$params['shipping_status']];
        if (_cv($params, 'shipping_status', 'ar')) $qr -> whereIn($this->table.'.shipping_status', $params['shipping_status']);

        if (_cv($params, ['logistic_type']) && !_cv($params, ['logistic_type'], 'ar')) $params['logistic_type'] = [$params['logistic_type']];
        if (_cv($params, 'logistic_type', 'ar')) $qr -> whereIn($this->table.'.logistic_type', $params['logistic_type']);

        if (_cv($params, ['shipping_price']) && !_cv($params, ['shipping_price'], 'ar')) $params['shipping_price'] = [$params['shipping_price']];
        if (_cv($params, 'shipping_price', 'ar')) $qr -> whereIn($this->table.'.shipping_price', $params['shipping_price']);

        if (_cv($params, ['status']) && !_cv($params, ['status'], 'ar')) $params['status'] = [$params['status']];
        if (_cv($params, 'status', 'ar')) $qr -> whereIn($this->table.'.status', $params['status']);

        if (_cv($params, ['user_id'], 'nn') && !_cv($params, ['user_id'], 'ar')) $params['user_id'] = [$params['user_id']];
        if (_cv($params, 'user_id', 'ar')) $qr -> whereIn($this->table.'.user_id', $params['user_id']);

        if (_cv($params, 'cart_info'))$qr->whereRaw("LOCATE( '{$params['cart_info']}', {$this->table}.cart_info )");
        if (_cv($params, 'meta_info'))$qr->whereRaw("LOCATE( '{$params['meta_info']}', {$this->table}.meta_info )");

        if (_cv($params, 'whereBetween')) $qr -> whereBetween($this->table.'.created_at', $params['whereBetween']);

        /// join with users
        $qr -> leftJoin('users', function($join) use ($conf){
            $join -> on("users.id", '=', "{$this -> table}.user_id");
        });

        $qr -> leftJoin('shop_transactions', function($join) use ($conf){
            $join -> on("shop_transactions.order_id", '=', "{$this -> table}.id");
        });

        /// filter by single search word. searches in any searchable field /// search logic OR
        if(_cv($params, 'searchText') && _cv($conf, ['adminListFields'])){
            $searchText = trim(strip_tags($params['searchText']));

            $qr -> where(function($q)use($searchText, $conf){
                foreach ($conf['adminListFields'] as $k=>$v){
                    if(!_cv($v, ['searchable']) || !isset($v['tableKey']) )continue;
                    $fieldName = $v['searchable']==1?$v['tableKey']:$v['searchable'];
                    $q -> orWhereRaw("LOCATE('{$searchText}', {$fieldName})");
                }
            });

        }

        /// filter by exact field. searches in exact searchable field /// search logic AND
        if(_cv($params, 'searchBy', 'ar') && _cv($conf, ['adminListFields'])){
            foreach ($conf['adminListFields'] as $k=>$v){
                if(!_cv($v, ['searchable']) || !isset($v['tableKey']) || !_cv($params['searchBy'], [$v['tableKey']]) )continue;
                $fieldName = $v['searchable']==1?$v['tableKey']:$v['searchable'];
                $qr -> whereRaw("LOCATE('{$params['searchBy'][$v['tableKey']]}', {$fieldName})");
            }
        }



        $returnData['listCount'] = $qr->count(DB::raw("DISTINCT({$this->table}.id)"));


        /// order section
        $sortfield = 'id';
        $sortDirection = _cv($params, ['sortDirection'])?$params['sortDirection']:'DESC';
        $sortField = _cv($params, ['sortField'])?$params['sortField']:'created_at';
        $qr -> orderBy("{$sortField}", $sortDirection);

        /// paging section
        if (_cv($params, 'limit')) $qr -> take($params['limit']);
        if (_cv($params, 'page')) $qr -> skip(($params['page'] - 1) * $params['limit']) -> take($params['limit']);



        $list = $qr->get();

        $returnData['list'] = _psql(_toArray($list));


//p($returnData['list']);
        $query = DB::getQueryLog();
//        p($query);

        return $returnData;
    }

    public function updItem($params = [])
    {
//        DB::enableQueryLog();

        if(!_cv($params, 'id', 'nn'))return ['error'=>'order id not set'];

        $upd = OrderModel::find($params['id']);
        $oldStatus = $upd['order_status'];

        if(!$upd)return ['error'=>"order #{$params['id']} not found"];
        foreach ($this->fillable as $k=>$v){
            if(!isset($params[$v]))continue;
            $upd[$v] = $params[$v];
        }

        $upd->save();

        if($oldStatus !== $upd['order_status']){
            $orderCtrl = new Cart();
            $orderCtrl->sendOrderStatusEmail(['orderId'=>$params['id']]);

//            $userInfo = _psqlCell($upd->meta_info);
////            p($userInfo);
//            $html = "Order #".str_pad($params['id'], 6, 0, STR_PAD_LEFT)." status changed to {$upd['order_status']}";
//            $res = Email::sendEmail([
//                'to' => $userInfo['cartMeta']['userInfo']['email'],
//                'template' => 'order',
//                'subject' => $html,
//                'vars' => [
//                    ['name' => 'order_id', 'content' => "<h2>".tr('order').' #'.str_pad($params['id'], 6, 0, STR_PAD_LEFT)." ".tr('status changed')." </h2>"],
//                    ['name' => 'status', 'content' => $upd['order_status']],
//                    ['name' => 'content', 'content' => '-'],
//                ],
//                'content' => [
//                    ['name' => 'header', 'content' => "<h2>{$html}</h2>", 'order_id'=>str_pad($params['id'], 6, 0, STR_PAD_LEFT)]
//                ]
//
//            ]);
//


//            p($res);
        }

        // p(DB::getQueryLog());

        return $upd->id;
    }

    public function upd($data = [])
    {
        return $this->updItem($data);
    }

    public function updateStatus($params = [])
    {
        if(!_cv($params, ['statusType']))return ['error'=>'status type not set'];
//p($params);
        $order = OrderModel::find($params['id']);

//        p($order);
        $order[$params['statusType']] = $params['status'];

        $order->save();

        $orderCtrl = new Cart();
        $orderCtrl->sendOrderStatusEmail(['orderId'=>$params['id']]);

//        p(DB::getQueryLog());

        return $order->id;
    }

    public function updateStatusFromService($params = [])
    {

        if(!_cv($params, ['remote_guid']))return ['error'=>'order GUID not set'];
        if(!_cv($params, ['order_status']))return ['error'=>'status not set'];

        $OrderModel = new OrderModel();
        $order = $OrderModel->getOne(['remote_guid'=>$params['remote_guid']]);

        if(!_cv($order, 'id'))return ['error'=>'order not exists'];

        $OrderStatus = $params['order_status'];
//        p($order);

        $tmp = $OrderModel->updField(['field'=>'order_status', 'value'=>$OrderStatus, 'id'=>$order['id']]);

        //        p($tmp);
//        p($order);
//        $order[$params['statusType']] = $params['status'];

//        $order->save();

//        p(DB::getQueryLog());

        return ['order'=>$order['id'], 'order_status'=>$OrderStatus];
    }


    public function createOrder($params = [])
    {

        if(!_cv($params, 'user_id'))return ['error'=>'user auth error'];
        if(!_cv($params, 'cart'))return ['error'=>'cart data error'];
        if(!_cv($params, 'cart.cart'))return ['error'=>'cart is empty'];
        if(!_cv($params, 'cart.total.subTotalProducts', 'nn')){
            if(!_cv($params, 'cart.total.subtotalPoints', 'nn')) return ['error'=>'cart error'];
        }

        $conf = config('adminshop.order');



        $cartInfo = _cv($params, 'cart');

        if(_cv($cartInfo, ['cartMeta','address','city'], 'nn')){
            $cartInfo['cartMeta']['address']['cityTitle'] = $this->freezeCityTitle(['id'=>$cartInfo['cartMeta']['address']['city']]);
        }

        $grandTotal = $cartInfo['total']['grandTotal'];

        $cartInfoPrepared = _cv($cartInfo, 'cart');
        $cartInfoPrepared = $this->freezeProductAttributes(['cart'=>$cartInfoPrepared]);
//        p($cartInfoPrepared);

        $upd = new OrderModel();
        $upd['total_amount'] = $grandTotal;
        $upd['order_status'] = $conf['order_status'][0];
        $upd['shipping_status'] = 'pending';
        $upd['user_id'] = $params['user_id'];
        $upd['logistic_type'] = _cv($cartInfo, 'cartMeta.logistic_type');
        $upd['shipping_price'] = '';
        $upd['meta_info'] = _psqlupd($cartInfo);
        $upd['cart_info'] = _psqlupd($cartInfoPrepared);
        $upd['status'] = 'published';

        if(_cv($params, 'remote_order_id'))$upd['remote_order_id'] = $params['remote_order_id'];
        if(_cv($params, 'remote_guid'))$upd['remote_guid'] = $params['remote_guid'];
        if(_cv($params, 'remote_response'))$upd['remote_response'] = _psqlupd($params['remote_response']);

        $upd->save();

        $this->decreaseProductQty(['cart'=>$cartInfoPrepared]);
        $this->checkAndUseCoupon($cartInfo);

        return ['orderId'=>$upd->id, 'totalAmount'=>$grandTotal];
    }


    public function freezeProductAttributes($params = []){
        $cart = _cv($params, 'cart', 'ar');
        $selectFields = "shop_attribute.id, shop_attribute.slug, shop_attribute.attribute, shop_attribute.conf, shop_attribute.pid ";

        if(!is_array($cart))return $cart;

        $attrModel = new AttributeModel();

        foreach ($cart as $k=>$v){
            if(!isset($v['product_attributes']) || !is_array($v['product_attributes']))continue;
//            p($v['product_attributes']);
            $attrs = array_keys($v['product_attributes']);
            $attributes = $attrModel->getList(['id'=>$attrs, 'select'=>$selectFields, 'attrType'=>1]);

            $cart[$k]['attributes'] = $attributes;
        }

        return $cart;

    }
    public function freezeCityTitle($params = []){
        if(!_cv($params, 'id', 'nn'))return '';
        $cityModel = new LocationsModel();
        $cityData = $cityModel->getOne(['id'=>$params['id']]);

        $locale = requestLan();
        return _cv($cityData, "name_{$locale}")?$cityData["name_{$locale}"]:$cityData["domain"];

    }
    public function checkAndUseCoupon($params = []){
        if(!_cv($params, 'cartMeta.promoCode') || !_cv($params, 'total.discount'))return '';

        DB::table("shop_coupons")->where('code', $params['cartMeta']['promoCode'])->increment('used');

        return true;

    }

    public function decreaseProductQty($params=[]){
        $cart = _cv($params, 'cart', 'ar');

        if(!is_array($cart))return $cart;

        $productModel = new ProductsModel();

        foreach ($cart as $k=>$v){
//            p($v);
            if(!isset($v['product_attributes']))continue;
//            p($v['product_attributes']);
            $productModel->decreaseQty(['productId'=>$v['id'], 'qty'=>$v['quantity'], 'boxQty'=>$v['boxQuantity']]);
        }

        return true;

    }

    public function checkProductStockQty($params=[]){

    }



}
