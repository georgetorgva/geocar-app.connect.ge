<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Media\MediaModel;
use App\Models\Admin\MetaModel;

class CartModel extends Model
{
    //
    protected $table = 'shop_cart';
    public $timestamps = true;
    public $error = false;
    protected $meta;
    protected $locale;
    protected $locales;


    //

    protected $allAttributes = [
        'id',
        'session',
        'cart_info',
        'user_id',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'session',
        'cart_info',
        'user_id',
        ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function getOne($params = [])
    {
//        p($params);
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
        if(_cv($params, ['sortDirection']) && !_cv($params, ['orderDirection']))$params['orderDirection'] = $params['sortDirection'];
        if(_cv($params, ['sortField']) && !_cv($params, ['orderField']))$params['orderField'] = $params['sortField'];


        $qr =  DB::table($this->table)->select(DB::raw("{$this->table}.* "));

        if (_cv($params, ['id'], 'nn') && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) $qr -> whereIn($this->table.'.id', $params['id']);

        if (_cv($params, ['session']) && !_cv($params, ['session'], 'ar')) $params['session'] = [$params['session']];
        if (_cv($params, 'session', 'ar')) $qr -> whereIn($this->table.'.session', $params['session']);

        if (_cv($params, ['user_id'], 'nn') && !_cv($params, ['user_id'], 'ar')) $params['user_id'] = [$params['user_id']];
        if (_cv($params, 'user_id', 'ar')) $qr -> whereIn($this->table.'.user_id', $params['user_id']);

        if (_cv($params, 'cart_info'))$qr->whereRaw("LOCATE( '{$params['cart_info']}', {$this->table}.cart_info )");


        $returnData['listCount'] = $qr->count(DB::raw("DISTINCT({$this->table}.id)"));


        if(_cv($params, 'limit')) $qr->limit($params['limit']);

        $qr->orderBy('id', 'asc');

        $list = $qr->get();

        $returnData['list'] = _psql(_toArray($list));

        foreach ($returnData['list'] as $k=>$v){
            $returnData['list'][$k]['cart_info'] = $this->updateCartProductsInfo($v['cart_info'], $v['id']);
        }

//p($returnData['list']);
        $query = DB::getQueryLog();

//        p($query);
//p($returnData);
        return $returnData;
    }

    public function upd($data = [])
    {
//        DB::enableQueryLog();

        if(!is_array($data))$data = [];

        $session = appSessionId();

        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:'';

        // .... update
        if($userId){
            $upd = CartModel::where('user_id', $userId)->first();
        }else{
            $upd = CartModel::where('session', $session)->first();
        }
//p($upd);
        // .... or create
        if(!isset($upd->id)){
            $upd = new CartModel();
//            $upd['session'] = $session;
            if($userId) $upd['user_id'] = $userId;
        }

        if($userId){
            $upd['session'] = "{$session}_{$userId}";
        }else{
            $upd['session'] = $session;
        }



        /// defaults
        if(isset($data['cart_info'])){
            $upd['cart_info'] = _psqlupd($data['cart_info']);

        }else if(isset($data['cart'])){
            $upd['cart_info'] = _psqlupd($data['cart']);
        }
        if(_cv($data,['cartMeta']))$upd['meta_info'] = _psqlupd($data['cartMeta']);

        $upd->save();


        if($upd->id) $this->updateCartProductsInfo($data, $upd->id);
//        p(DB::getQueryLog());

        return $upd->id;
    }

    public function joinCarts()
    {

        if(!Auth::user())return false;
        $sessionId = appSessionId();

        $cartsCountToSession = DB::table($this->table)
            ->select("*")
            ->where('session', '=', $sessionId)
            ->orWhere('user_id', '=', Auth::user()->id)
            ->orderByDesc('user_id')
            ->get();

        /// if there is no cart do nothing
        if(!isset($cartsCountToSession[0]->id))return false;

        /// if there is only one cart attached to user do nothing
        if(count($cartsCountToSession)==1 && $cartsCountToSession[0]->user_id==Auth::user()->id)return false;

        $cartsCountToSession = _psql(_toArray($cartsCountToSession));

        /// define user cart
        $userCart = $cartsCountToSession[0];

        $toBeDeletedCarts = [];

        /// loop for current session carts
        foreach ($cartsCountToSession as $k=>$v){
            /// if cart is users cart
            if($v['user_id']===$userCart['user_id'])continue;

            /// carts to be deleted after join
            $toBeDeletedCarts[] = $v['id'];

            /// if cart is empty
            if(!_cv($v, ['cart_info'], 'ar'))continue;
//            p($userCart);
            /// loop for every single product in cart
            foreach ($v['cart_info'] as $kk=>$vv){
                /// if some product already exists in users cart session cart item will be ignored
                if(_cv($userCart, ['cart_info', $kk]))continue;

                $userCart['cart_info'][$kk] = $vv;
            }
        }
//return false;
        $res = $this->upd(['id'=>$userCart['id'], 'cart_info'=>_cv($userCart, ['cart_info'])]);
        if(!$res)return false;
        $this->delCart($toBeDeletedCarts);

        return true;
    }

    public function delCart($ids = []){
//        if(empty($ids))return false;
//        p($ids);
//        CartModel::where('session', appSessionId())->where('user_id', null)->delete();
        $session = appSessionId();
        CartModel::where('session', $session)->delete();
    }

    public function resetCart($params = []){
        if(!_cv($params, 'user_id', 'nn'))return ['error'=>'user not set'];

        $res = CartModel::where('user_id', $params['user_id'])->update(['cart_info'=>'','meta_info'=>'', 'total_amount'=>0]);

        return $res;

    }

    public function updateCartProductsInfo($cart_info = [], $cartId = ''){

        if(!is_numeric($cartId))return false;
        if(!is_array($cart_info) || !isset(current($cart_info)['id']))return false;
//        p($cart_info);
        $ids = array_column($cart_info, 'id');
        $products = new ProductsModel();
        $productsList = $products->getList(['id'=>$ids, 'translate'=>1, 'limit'=>500]);

        $productIds = array_column($productsList['list'], 'id');

        if(!_cv($productsList, 'list'))return false;
        $totalAmount = 0;

        $newCartInfo = [];

        foreach ($productsList['list'] as $k=>$v){
//            p($cart_info[$v['id']]);

            if(!_cv($cart_info, $v['id'])) continue;
            $cartItem = $cart_info[$v['id']];

            $cartItem['sellWithPoints'] = $this->checkSellWithPoints($cartItem, _cv($v,['conf']), 'sellWithPoints');


            if(!_cv($cartItem, ['boxQuantity'], 'nn') && !_cv($cartItem, ['quantity'], 'nn') && !_cv($cartItem, ['sellWithPoints'], 'nn') ){
                unset($cart_info[$v['id']]);
                continue;

//                $cartItem['quantity'] = 1;
            }


            $cartItem['error'] = [];
//            $cartItem['boxQuantity'] = _cv($cartItem,['boxQuantity'], 'nn')?$cartItem['boxQuantity']:0;

            /// box quantity checker
            if(_cv($cartItem,['boxQuantity'], 'nn') && $cartItem['boxQuantity']>$v['box_count']){
                $cartItem['boxQuantity'] = $v['box_count'];
//                $cartItem['error'][] = 'not enough products in stock';
            }elseif (_cv($cartItem,['boxQuantity'], 'nn') && $cartItem['boxQuantity']<=$v['box_count']){
                $cartItem['boxQuantity'] = $cartItem['boxQuantity'];
            }else{
                $cartItem['boxQuantity'] = 0;
//                $cartItem['error'][] = 'product quantity not selected';
            }

            /// quantity checker
            if(_cv($cartItem,['quantity'], 'nn') && $cartItem['quantity']>$v['qty']){
                $cartItem['quantity'] = $v['qty'];
                $cartItem['error'][] = 'not enough products in stock';
            }elseif (_cv($cartItem,['quantity'], 'nn') && $cartItem['quantity']<=$v['qty']){
                $cartItem['quantity'] = $cartItem['quantity'];
            }else{
                $cartItem['quantity'] = 0;
                $cartItem['error'][] = 'product quantity not selected';
            }


            $cartItem['quantityTotal'] = $cartItem['boxQuantity']+$cartItem['quantity']+$cartItem['sellWithPoints'];

            $cartItem['price'] = $v['price'];
            $cartItem['calcPrice'] = $v['calcPrice'];

            $cartItem['box_price'] = $v['box_price'];
            $cartItem['boxCalcPrice'] = $v['boxCalcPrice'];

            $cartItem['pointsCalcPrice'] = ($cartItem['sellWithPoints']>0)?($cartItem['price']*$cartItem['sellWithPoints']):0;

            $cartItem['title'] = _cv($v,['title']);
            $cartItem['teaser'] = _cv($v,['teaser'])?$v['teaser']:'';
            $cartItem['product_attributes'] = _cv($v,['product_attributes']);
            $cartItem['slug'] = _cv($v,['slug']);
//            $cartItem['old_price'] = _cv($v,['old_price']);
            $cartItem['images'] = [_cv($v,['images', 0])];
            $cartItem['stock_qty'] = _cv($v,['qty']);
            $cartItem['step'] = _cv($v,['step']);
            $cartItem['offers'] = _cv($v,['offers']);
            $cartItem['sku'] = _cv($v,['sku']);
            $cartItem['dimension_id'] = _cv($v,['dimension_id']);
            $cartItem['box_count'] = _cv($v,['box_count']);
            $cartItem['box_sell_status'] = _cv($v,['box_sell_status']);
            $cartItem['discount'] = _cv($v,['discount']);
            $cartItem['boxDiscount'] = _cv($v,['boxDiscount']);
            $cartItem['subProductsTotalPrice'] = 0;
            $cartItem['subProductsTotalPriceRaw'] = 0;



            /// check gifts
            $cartItem = $this->checkGifts($cartItem);
            if(_cv($cartItem, ['gift'], 'ar')){
                $cartItem['quantityTotal'] += array_sum(array_column($cartItem['gift'], 'quantity'));
            }


            $cartItem = $this->checkDependenceDiscount($cartItem);
            if(_cv($cartItem, ['dependentDiscount'], 'ar')){
                $cartItem['quantityTotal'] += array_sum(array_column($cartItem['dependentDiscount'], 'quantity'));
            }

            /// cart item subtotal calculations
            $cartItem = $this->cartItemSubtotalCalculations($cartItem);

            $totalAmount += $cartItem['grandTotal'];

            $newCartInfo[$v['id']] = $cartItem;

        }

        //p(array_column($cart_info, 'calcPrice'));
        CartModel::where('id', $cartId)->update([ 'cart_info'=>_psqlupd($newCartInfo), 'total_amount'=>$totalAmount ]);
//        p($cart_info);
        return $newCartInfo;

    }


    public function cartItemSubtotalCalculations($cartItem = []){
        /// calculate retail subtotals
        $cartItem['subtotal'] = $cartItem['subtotalRaw'] = 0;
        if(_cv($cartItem, ['quantity'], 'nn')){
            $cartItem['subtotal'] = round($cartItem['quantity'] * $cartItem['calcPrice'], 2);
            $cartItem['subtotalRaw'] = round($cartItem['quantity'] * $cartItem['price'], 2);
        }

        /// calculate box subtotals
        $cartItem['subtotalBox'] = $cartItem['subtotalBoxRaw'] = 0;
        if(_cv($cartItem, ['boxQuantity'], 'nn')){
            $cartItem['subtotalBox'] = round($cartItem['boxQuantity'] * $cartItem['boxCalcPrice'], 2);
            $cartItem['subtotalBoxRaw'] = round($cartItem['boxQuantity'] * $cartItem['box_price'], 2);
        }

        $cartItem['grandTotal'] = $cartItem['subtotalBox'] + $cartItem['subtotal'];
        $cartItem['grandTotalRaw'] = $cartItem['subtotalBoxRaw'] + $cartItem['subtotalRaw'];

        $cartItem['discountAmount'] = round($cartItem['grandTotalRaw'] - $cartItem['grandTotal'], 2);
        $cartItem['discountPercent'] = $cartItem['grandTotal']!=$cartItem['grandTotalRaw']?round( ($cartItem['grandTotal'] * 100) / $cartItem['grandTotalRaw'], 2):0;

        return $cartItem;
    }

    private function checkGifts($product = []){
//p($product);
        /// if there are not gifts do nothing
        $gifts = _cv($product, 'gift', 'ar')?$product['gift']:[];
        if(count($gifts)==0){
            $product['gift'] = [];
            return $product;
        }

        $parentOffers = _cv($product, 'offers', 'ar')?$product['offers']:[];
        $parentOfferIds = [];
        $parentOffer = [];

        /// select all parent product offer ids
        foreach ($parentOffers as $v){
            if(_cv($v, 2)!='gift' || _cv($v, 3)!='offer_type_data_ids')continue;
            $parentOfferIds[] = $v[0];
            $parentOffer = $v;
            break;
        }


        $subProductMaxCount = _cv($parentOffer, [7], 'nn')>0?$parentOffer[7]:10;

        /// if gifts count reached available limit, remove all gifts
        if($subProductMaxCount && count($gifts)>$subProductMaxCount){
            $product['gift'] = [];
            return $product;
        }


        /// if there is not gift offers disable gift from parent product
        if(!isset($parentOfferIds[0])){
            $product['gift'] = [];
            return $product;
        }

//        p($parentOfferIds);
        $giftOffers = _cv($gifts);

        /// if gift hasnot same offer id remove gift
        foreach ($gifts as $k=>$v){
            /// set sub products quantities
            $gifts[$k]['boxQuantity'] = 0;
            $gifts[$k]['quantity'] = _cv($v,['quantity'], 'nn')?$v['quantity']:1;

            foreach ($v['offers'] as $vv){
                if(_cv($vv, 2)!='gift' || _cv($vv, 3)!='product')continue;
                if(array_search($vv[0], $parentOfferIds) === false )continue;

                $gifts[$k]['calcPrice'] = 0;
                break;
            }
            if($gifts[$k]['calcPrice']!=0){
                unset($gifts[$k]);
                continue;
            }

            $product['subProductsTotalPriceRaw'] += $gifts[$k]['price'];

//            p($v['offers']);
        }

        $product['gift'] = (count($gifts)>0)?$gifts:[];


        return $product;
    }

    private function checkDependenceDiscount($product = []){
//p($product);
        /// if there are not gifts do nothing
        $subProducts = _cv($product, 'dependentDiscount', 'ar')?$product['dependentDiscount']:[];
        if(count($subProducts)==0){
            $product['dependentDiscount'] = [];
            return $product;
        }

        $parentOffers = _cv($product, 'offers', 'ar')?$product['offers']:[];
        $parentOfferIds = [];
        $parentOffer = [];
//        p($parentOfferIds);
//        p($subProducts);
        /// select all parent product offer ids
        foreach ($parentOffers as $v){
            if(_cv($v, 2)!='dependentDiscount' || _cv($v, 3)!='offer_type_data_ids')continue;
            $parentOfferIds[] = $v[0];
            $parentOffer = $v;
            break;
        }

        $subProductMaxCount = _cv($parentOffer, [7], 'nn')>0?$parentOffer[7]:10;

        /// if subproducts count reached available limit, remove all sub products
        if($subProductMaxCount && count($subProducts)>$subProductMaxCount){
            $product['dependentDiscount'] = [];
            return $product;
        }

        /// if there is not dependable offers disable product from parent product
        if(!isset($parentOfferIds[0])){
            $product['dependentDiscount'] = [];
            return $product;
        }


//        $product['subProductsTotalPrice'] = 0;

//        p($parentOfferIds);
        $productModel = new ProductsModel();
        /// if gift hasnot same offer id remove gift
        foreach ($subProducts as $k=>$v){

            /// check cart subproduct if has offers; if no remove subproduct
            if(!_cv($v, 'offers', 'ar')){
                unset($subProducts[$k]);
                continue;
            }

            /// select real product from subproduct id
            $tmp = $productModel->getOne(['id'=>$v['id'], 'translate'=>1]);
            /// set sub products quantities
            $tmp['boxQuantity'] = 0;
            $tmp['quantity'] = _cv($v,['quantity'], 'nn')?$v['quantity']:1;

            /// check if real product has offers; if no remove subproduct
            if(!_cv($tmp, 'offers', 'ar')){
                unset($subProducts[$k]);
                continue;
            }

//            p($tmp);
            /// check if sub product is accept on parent product
            foreach ($tmp['offers'] as $vv){
//                p($vv);
                if(_cv($vv, 2)!='dependentDiscount' || _cv($vv, 3)!='product')continue;
                if(array_search($vv[0], $parentOfferIds) === false )continue;
                $tmpp = discountCalculator($price = $tmp['calcPrice'], $vv[6], $vv[5], $vv[0], $vv);
//                p($tmpp);
                $tmp['discount'][] = $tmpp;
                $tmp['calcPrice'] = $tmpp['calcPrice'];
                $subProducts[$k] = $tmp;

                break;
            }

            $subProducts[$k] = $this->cartItemSubtotalCalculations($subProducts[$k]);
            $product['subProductsTotalPrice'] += $subProducts[$k]['subtotal'];
            $product['subProductsTotalPriceRaw'] += $subProducts[$k]['subtotalRaw'];

        }

        $product['subProductsTotalPrice'] = round($product['subProductsTotalPrice'], 2);
        $product['dependentDiscount'] = count($subProducts)>0 ? $subProducts:[];


        return $product;
    }

    /// check if allowed sell with points for product
    /// if allowed add products count
    private function checkSellWithPoints($cartAddedProductInfo=[], $productConfs=[], $targetConfigName=''){
        if(_cv($cartAddedProductInfo, $targetConfigName, 'nn') && _cv($cartAddedProductInfo, $targetConfigName) && is_array($productConfs) && array_search($targetConfigName, $productConfs) !== false){
            return $cartAddedProductInfo[$targetConfigName]>=1?$cartAddedProductInfo[$targetConfigName]:1;
        }

        return 0;
    }

}


