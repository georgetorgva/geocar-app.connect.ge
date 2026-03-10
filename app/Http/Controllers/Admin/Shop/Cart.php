<?php

namespace App\Http\Controllers\Admin\Shop;

use App;
use App\Http\Controllers\Admin\Shop\services\Email;
use App\Models\User\User;
use http\Env\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use App\Mail\SendBuyerEmail;
use App\Mail\SendMerchantEmail;
use App\Models\Shop\OrderModel;
use App\Models\Shop\PaymentModel;
use App\Models\Shop\WalletsModel;
use App\Models\Admin\OptionsModel;
use App\Models\Shop\ProductsModel;
use App\Models\Shop\WishlistModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Admin\User\UserController;
use App\Http\Controllers\Admin\Shop\Payments\Payment;
use App\Http\Controllers\Admin\Shop\services\LtbRequests;
use Illuminate\Support\Facades\Log;

class Cart extends App\Http\Controllers\Api\ApiController
{

    public function cartMetaInfoValidate($params = []){

        if(!Auth::user())return $params;
        $offerModel = new App\Models\Shop\OfferModel();

        $userData = Auth::user();
        $userData = _psqlRow(_toArray($userData));
//            p($userData);
//            p(Auth::user()->address);
        $params['cartMeta']['userInfo'] = [];
        $params['cartMeta']['userInfo']['email'] = _cv($userData, 'email');
        $params['cartMeta']['userInfo']['fullname'] = _cv($userData, 'fullname');
        $params['cartMeta']['userInfo']['phone'] = _cv($userData, 'phone');
        $params['cartMeta']['userInfo']['p_id'] = _cv($userData, 'p_id');
        $params['cartMeta']['userInfo']['contragent'] = _cv($userData, 'additional_info.contragents');
        $params['cartMeta']['userInfo']['offers'] = $offerModel->getUserRelatedOffers($userData['id']);

        /// check selected address exists
        /// get selected address uid
        $selectedAddressUid = _cv($params, 'cartMeta.address.uid');

        /// clear selected address
        $params['cartMeta']['address'] = '';

        /// find and set address if equals to selected address uid; else leave cleared
        if(is_array($userData['address'])){
            foreach ($userData['address'] as $k=>$v){
                if($v['uid']==$selectedAddressUid)$params['cartMeta']['address'] = $v;
            }
        }

        return $params;

    }

    public function updateCart($params = []){

        $cart = new App\Models\Shop\CartModel();
        $wishlist = new App\Models\Shop\WishlistModel();

//        p($params);
        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:'';

        if($userId){
            $params = $this->cartMetaInfoValidate($params);
        }

        if($userId && _cv($params,['postponed'], 'ar')){
            $postponed = $wishlist->upd(['list_type'=>'postponed', 'list_info'=>_cv($params,['postponed'])]);
//            p($postponed);
        }

        $res = $cart->upd($params);
//        p($res);

        $ret = $this->getCartInfoRaw(['path'=>_cv($params, ['path'])]);

        return response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE);

    }

    /// calculate cart info from api
    public function getCartInfoRaw($params = []){

        $cart = new App\Models\Shop\CartModel();
        $offerModel = new App\Models\Shop\OfferModel();
        $couponModel = new App\Models\Shop\CouponsModel();
        $serviceRequests = new LtbRequests();

        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:'';
        $userType = $userId?Auth::user()->status:'person';

        $cart->joinCarts();

        if($userId){
            $res = $cart->getOne(['user_id'=>$userId]);
//            p($res);
            /// get postponed list
            $wishlist = new App\Models\Shop\WishlistModel();
            $postponedList = $wishlist->getOne(['user_id'=>$userId]);
            $postponedList = _cv($postponedList, 'list_info', 'ar')?$postponedList['list_info']:[];
            $ret['postponed'] = $postponedList;

        }else{
            $res = $cart->getOne(['session'=>appSessionId()]);
        }

        $ret['cart'] = $cartInfo = _cv($res, 'cart_info')?$res['cart_info']:[];
        $ret['cartMeta'] = _cv($res, 'meta_info')?$res['meta_info']:[];
        $ret['cartMeta']['shippingLocation'] = _cv($ret['cartMeta'], 'address.city');
        $ret['cartMeta']['discounts'] = [];

        /// if useLoyalty == true
        if(_cv($ret, ['cartMeta','useLoyalty']) && $userId){
            $ret['cartMeta']['discounts'][] = $this->memberRelatedDiscount();
        }
//        elseif ($userType != 'person'){
//            $ret['cartMeta']['discounts'][] = $this->memberRelatedDiscount();
//        }


        /// select promo code offer
        /// if promo code exists
        if(_cv($ret, ['cartMeta','promoCode'])){
            $ret['cartMeta']['discounts'][] = $couponModel->getCouponDiscount([ 'code'=>$ret['cartMeta']['promoCode'] ]);
        }

        if($userId && _cv($params, ['path', 'route']) == 'payment'){
            $ret['cartMeta']['shipping'] = $serviceRequests->getShippingCost($res, $ret['cartMeta']['discounts']);
            $cart->upd($ret);
        }
        if(!isset($ret['cartMeta']['shipping'])) $ret['cartMeta']['shipping'] = [];


        /// accept additional discounts like promo code
        $ret['cart'] = $this->acceptAdditionalDiscounts($ret);

        if(empty($ret['cart']))return $ret;
//        $ret = $this->checkCartInfo($ret); /// check cart products prices from server
//        p($ret);
        if(_cv($ret, 'error') || !isset($ret['cart']))return $ret;

        $ret['total'] = $this->calcGrandTotal([ 'cart'=>$ret['cart'], 'shipping'=>$ret['cartMeta']['shipping'], 'cartMeta'=>$ret['cartMeta']]);

        $pointsWallet = $this->getUserPoints(['user_id'=>$userId]);

        if(_cv($ret['total'], 'subtotalPoints', 'nn') && $pointsWallet < $ret['total']['subtotalPoints'])$ret['error'] = 'not enough points';

        return $ret;
    }

    public function getCartInfo($params = []){ return response()->json($this->getCartInfoRaw($params), 200, [], JSON_UNESCAPED_UNICODE); }

    public function joinCarts(){

        $cart = new App\Models\Shop\CartModel();
        $res = $cart->joinCarts();

        return response([]);
    }

    /// wishlists
    /**
    addListItem
     * @input id, list_info
     * @output array(list)
     */
    public function addListItem($params = []){

        if(!Auth::user())return response(['error'=>'user must be authorised'], 201);

        $wishlist = new App\Models\Shop\WishlistModel();

//        if(!_cv($params, ['id'], 'nn')){
            $wishlistData = $wishlist->getOne(['list_type' => 'wishlist', 'user_id' => Auth::user()->id]);
            if(_cv($wishlistData, 'id'))$params['id'] = $wishlistData['id'];
//        }

        if(_cv($params, 'product', 'ar')){
            $listId = $wishlist->upd(['id'=>_cv($params, ['id']), 'list_info'=>($params['product']) ]);
            $list = (is_numeric($listId))?$wishlist->getOne(['id'=>$listId]):[];
        }

        return response($list, _cv($list, 'id', 'nn')?200:201);

    }

    /**
     * @input id(list id), productId(product id to remove from list)
     * @output array(list)
     */
    public function removeListItem($params = []){
        if(!Auth::user())return response(['error'=>'user must be authorised'], 201);

        $wishlist = new App\Models\Shop\WishlistModel();

        $listId = '';
        $wishlistData = $wishlist->getOne(['list_type' => 'wishlist', 'user_id' => Auth::user()->id]);
        if(_cv($wishlistData, 'id'))$listId = $wishlistData['id'];

        if(!$listId)return false;

        $newList = $wishlist->removeListItem(['id'=>$listId, 'productId'=>_cv($params, ['productId'])]);

//        $list = $wishlist->getOne(['id'=>$listId]);

        return response($newList, _cv($newList, 'id', 'nn')?200:201);

    }

    public function removePostponedItem($params = []){
        $wishlist = new App\Models\Shop\WishlistModel();
        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:'';

        if( $userId ) {
            $wishlistData = $wishlist->getOne(['list_type' => 'postponed', 'user_id' => $userId]);
        } else {
            $wishlistData = $wishlist->getOne(['list_type' => 'postponed', 'session' => appSessionId()]);
        }

        if(_cv($wishlistData, 'id'))$listId = $wishlistData['id'];
        if(!isset($listId))return false;

        $newList = $wishlist->removeListItem(['id'=>$listId, 'productId'=>_cv($params, ['productId'])]);

//        $list = $wishlist->getOne(['id'=>$listId]);

        return response($newList, _cv($newList, 'id', 'nn')?200:201);

    }

    /**
     * @input params (list items to filter)
     * @output array(list by user)
     */
    public function getLists($params = []){
        if(!Auth::user())return response(['error'=>'user must be authorised'], 201);

        $wishlist = new WishlistModel();

        if(!_cv($params, 'list_type'))$params['list_type'] = 'wishlist';

        $params['user_id'] = Auth::user()->id;
        $list = $wishlist->getOne($params);

        return response($list, _cv($list, '0.id', 'nn')?200:201);

    }

    public function shareCart($params = []){
        $params['session'] = Str::random(5) . time();
        $params['user_id'] = Auth::user()->id ?? null;
        $params['title'] = 'sharecart';
        $params['list_type'] = 'sharecart';

        $wishlist = new WishlistModel();
        $listId = $wishlist->updItem($params);

        $list = (is_numeric($listId))?$wishlist->getOne(['id'=>$listId]):[];
        return response($list, _cv($list, 'id', 'nn')?200:201);
    }

    public function sharedCart($params = []){

        $wishlist = new WishlistModel();
        $list = $wishlist->getOne(['session'=>$params['session']]);
        if(!_cv($list, ['id'], 'nn'))return response(['error'=>'shared list not exists'], 201);

        $listId = $wishlist->upd(['id'=>_cv($list, ['id']), 'list_info'=>_cv($params, ['list_info']) ]);

        $list = $wishlist->getOne(['session'=>$params['session']]);

        $list['total'] = $this->calcGrandTotal([ 'cart'=>$list['list_info'], 'shipping'=>[], 'cartMeta'=>[]]);


        return response($list);
    }

    //// addresses
    public function updateAddress($params = []){

        $session = session()->getId();
        $translate = requestLan();

        $location = new App\Models\Shop\LocationsModel();

        if(_cv($params,['cityId'], 'nn')){
            $params['city'] = $location->getOne(['id'=>$params['cityId'], 'pluck'=>"name_{$translate}"]);
        }else{
            $tmp = $location->getOne(["name_{$translate}"=>$params['cityId']]);
            if(isset($tmp['id'])){
                $params['city'] = _cv($tmp, ["name_{$translate}"]);
                $params['cityId'] = $tmp['id'];
            }
        }


        if(!$params['city'])return ['error'=>'city not found'];

        if($params['default']['default'] == 1){
            $address = json_decode(Auth::user()->address, true);

            if(isset($address)){
                foreach($address as $index => $addr){
                    $address[$index]['default']['default'] = 0;
                };
                User::where('id', Auth::user()->id)->update([
                    'address' => $address
                ]);
            }
        }

        if(!Auth::user() || !Auth::user()->id)return response(['error'=>'user not auth'], 201);
        $userId = Auth::user()->id;

        $user = new App\Models\User\User();
        $updated = $user->updUserAddress([ 'id'=>$userId, 'address'=>$params]);

        return response($updated, _cv($updated, '0.uid')?200:201);
    }
    //// addresses
    public function deleteAddress($params = []){
        if(!Auth::user() || !Auth::user()->id)return false;
        $userId = Auth::user()->id;

        $user = new App\Models\User\User();
        $updated = $user->delUserAddress([ 'id'=>$userId, 'uid'=>_cv($params, 'uid')]);

        return response($updated, $updated?200:201);
    }

    /// get shipping methods depend on cart
    public function getCartShippings($params = []){
//        p($params);
//        if(!Auth::user() || !Auth::user()->id)return false;
//        $userId = Auth::user()->id;
        $translate = requestLan();

        $model = new App\Models\Shop\ShippingModel();
        $shippings = $model->getList([ 'locations'=>_cv($params, 'cityId'), 'translate'=>$translate ]);

        return response($shippings, $shippings?200:201);
    }

    public function getCartShipping($params = []){
        if(!_cv($params,['cartAmount']))return [];

        $translate = requestLan();
        $selectedShipping = _cv($params, ['cartMeta', 'selectedShipping']);

        $model = new App\Models\Shop\ShippingModel();

        $getListParams = [ 'orderField'=>'shipping_amount','orderDirection'=>'asc', 'translate'=>$translate,
            'whereRaw'=>[
                "cart_min_amount <= {$params['cartAmount']}",
                "cart_max_amount >= {$params['cartAmount']}",
            ]
        ];

        if(_cv($params, 'locationId', 'nn'))$getListParams['whereRaw'][] = "relation_shipping_location.id_sec = "._cv($params, 'locationId');

        $shippings = $model->getList($getListParams);
        $ret['selected'] = [];
        $ret['all'] = [];

        foreach ($shippings['list'] as $k=>$v){
            $tmp = [
                'amount'=>_cv($v, 'shipping_amount'),
                'info'=>_cv($v, ['info', $translate]),
            ];

            /// get all available shippings
            $ret['all'][] = $tmp;

            /// if shipping selected use it for price calculation
            if($v['id'] == $selectedShipping)$ret['selected'] = $tmp;

            /// select default shipping for price calculation
            if(!isset($ret['selected']['amount']))$ret['selected'] = $tmp;

        }

        return $ret;
    }

    /////////////////////
    /// PAYMENT ACTIONS
    public function transactionStart($params = []){
//        p($params);
//        $this->getCartInfo();

        /// check if user is auth
        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:false;
        if(!$userId) return response(['error'=>'Auth problem'],201);

        $cart = new App\Models\Shop\CartModel();
        $orderModel = new App\Models\Shop\OrderModel();
        $serviceRequests = new LtbRequests();

        ///1. get cart and check if not empty
//        $cartData = $cart->getOne(['user_id'=>$userId]); ///p($cartData);
        $cartData = $this->getCartInfoRaw();
//        p(_cv($cartData, 'cartMeta.shipping.info.TransportServiceID'));
        if(!_cv($cartData, 'total.subTotalProducts') && !_cv($cartData, 'total.subtotalPoints'))return response(['error'=>'cart is empty'],201);
        if(_cv($cartData, 'error'))return response(['error'=>$cartData['error']],201);
        if(!_cv($cartData, 'cartMeta.shipping.info.TransportServiceID'))return response(['error'=>'shipping info not set'],201);


        /// if there is some point buy products, check if user has enough points
        if(_cv($cartData, 'total.points', 'nn') > 0){
            $ltbReq = new LtbRequests();
            $checkPointsResult = $ltbReq->getPointsFromSevice(Auth::user()->p_id);

            if(!_cv($checkPointsResult, '0.Points', 'nn') || $checkPointsResult[0]['Points']<$cartData['total']['points'])return ['error'=>'Not enough points!'];

        }

//        p($cartData['cartMeta']['shipping']);
        $orderResult['შეკვეთის_ნომერი'] = $orderResult['შეკვეთის_GUID'] = false;
        $orderResult = $serviceRequests->sendOrder($cartData);
//        print '-------';
//        p($orderResult);
//        print '-------';
        if(!$orderResult || _cv($orderResult, 'error'))return response(['error'=>_cv($orderResult, ['error']), $orderResult], 201);

//        return false;
//        $cartData['cart'] = $this->updateCartInfoFromApi($cartData['cart'], $orderResult);
//        p($cartData);

        ///2. create new order depend on current cart data
        $newOrder = $orderModel->createOrder(['cart'=>$cartData, 'user_id'=>$userId, 'remote_order_id'=>$orderResult['შეკვეთის_ნომერი'], 'remote_guid'=>$orderResult['შეკვეთის_GUID'], 'remote_response'=>$orderResult]);

/*** /
        /// email for testing
        $orderData['data'] = $orderModel->getOne(['id'=>$newOrder['orderId']]);
        $orderData['subject'] = 'Order created';
//        p($orderData);
        $this->sendEmails([_cv($cartData, ['cartMeta','userInfo','email'])], new SendBuyerEmail($orderData), $orderData);
/***/
        if(!_cv($newOrder, 'orderId', 'nn'))return response(['error'=>'Site error: can`t create order'], 201);


//return false;
        ///3. after creating order reset cart data
        // $cart->resetCart(['user_id'=>$userId]);


        $ret = [
            'status'=> '-',
            'providerStatus'=> '-',
            'transactionId'=>'-',
            'submitForm'=>'/success-order',
            'redirectUrl'=>'-',
            'orderId'=>_cv($newOrder, 'orderId')
        ];

        ///4. create new transaction depend on new order
        if(_cv($cartData, 'total.subtotalPoints')){
            if(_cv($cartData, 'total.grandTotal') == 0){
                $transactionUpd['orderId'] = $newOrder['orderId'];
                $transactionUpd['user_id'] = $userId;
                $transactionUpd['provider'] = 'points';
                $transactionUpd['transactionId'] = $newOrder['orderId'];
                $transactionUpd['totalAmount'] = _cv($cartData, 'total.subtotalPoints');

//                $payment = new PaymentModel();
//                $transaction = $payment->createTransaction($transactionUpd);
            }
            $ret['redirectUrl'] = '/'.requestLan().'/payment-status?order='.$newOrder['orderId'].'';
//            $this->pointsCharging(['id'=>$newOrder['orderId'], 'totalAmount'=>_cv($cartData, 'total.subtotalPoints'), 'userId'=>$userId]);
        }
        if(_cv($cartData, 'total.grandTotal')){

           $newOrder['totalAmount'] = $cartData['total']['grandTotal']; /// for production

//            FOR TESTING !!!!!!!!!!!! only user 762 can pay test amount
            if($userId==762){
                $newOrder['totalAmount'] = 0.02; /// for testing
            }
//            p($newOrder);
//            p($cartData);
           $payment = new Payment(['paymentMethod' => _cv($cartData, ['cartMeta','paymentProvider'])]);
           $ret = $payment->transactionStart(['orderId'=>$newOrder['orderId'], 'totalAmount'=>$newOrder['totalAmount'], 'userId'=>$userId]);
        }
//        p($ret);

//        $ret['redirectUrl'] = _cv($ret, 'url');
//        $ret['redirectUrl'] = "#";
//        $ret['params'] = ['id'=>11,'amnt'=>22];

        $this->sendOrderStatusEmail(['orderId'=>$newOrder['orderId']]);

        ///5. return transaction information and redirect to card payment
        return response($ret);
    }


    public function transactionStatus($params = []){
//        logInFile(json_encode($params));
        Log::debug($params, ['transactionStatus']);


        $paymentModel = new App\Models\Shop\PaymentModel();
        if(_cv($params, ['order'])){
            $order = $paymentModel->getOne(['order_id'=>$params['order']]);
            $params['transactionId'] = _cv($order, 'provider_transaction_id');
        }elseif(_cv($params, ['resource'])){
            // PayPal Callback
            $params['transactionId'] = $params['resource']['id'];
        }elseif(_cv($params, ['PaymentId'])){
            // TBC Callback
            $params['transactionId'] = $params['PaymentId'];
        }elseif(_cv($params, ['order_id'])){
            // BOG Callback
            $params['transactionId'] = $params['order_id'];
        }



        if(!_cv($params, ['transactionId']))return ['error'=>'transaction Id not set'];
        if(!_cv($params, ['order'])){
            $order = $paymentModel->getOne(['provider_transaction_id'=>$params['transactionId']]);
            if(!$order) return ['error'=>'transaction Id not found'];
            $params['order'] = $order['order_id'];
        }

        if(isset($order) && _cv($order, 'provider') !== 'points'){
            $payment = new Payment(['paymentMethod' => $order['provider']]);
            $ret = $payment->transactionStatus($params);

            if(_cv($ret, 'view'))$res = $ret['view'];


            // clear the shopping cart if paid

            $paymentStatus = $ret['data']['status'] ?? null;
            $userId = $order['user_id'] ?? null;

            if ($paymentStatus === 'processing' && $userId)
            {
                $cart = new App\Models\Shop\CartModel();

                $cart -> resetCart(['user_id' => $userId]);
            }

        } else {
            $ret['data']['status'] = 'processing';
            $orderModel = new OrderModel();
            $orderModel->updateStatus(['statusType' => 'order_status', 'status'=>'processing', 'id'=>$params['order']]);

            $paymentModel->setTransStatus(['status'=>'paid', 'trans_id'=>$params['order']]);
        }

        if(_cv($ret, 'data.status') == 'order-received'){
            // Check If Email Already Sented
            $orderModel = new OrderModel();
            $order = $orderModel->getOne(['id'=>$params['order']]);
            if(_cv($order, 'meta_info.cartMeta.emailSented') != true){
                // Get Merchant Emails

                $options = new OptionsModel();
                $emails = $options->getSetting('orderNotifyEmail');
                // Send Email To Merchant
                $this->sendEmails(explode(",", $emails), new SendMerchantEmail($order), $order);
//                Mail::to(explode(",", $emails))->send(new SendMerchantEmail($order));


                // Send Email To Buyer
                $emailTo[] = _cv($order, ['meta_info','cartMeta','userInfo','email']);
                $mailInfo['subject'] = 'Product Purchased';
                $mailInfo['data'] = $order;
                $this->sendOrderStatusEmail(['orderId'=>$params['order']]);
//                $this->sendEmails($emailTo, new SendBuyerEmail($mailInfo), $mailInfo);
//                Mail::to($emailTo)->send(new SendBuyerEmail($mailInfo));

                // Save Email Is Sented
                OrderModel::where('id', $params['order'])->update([
                    'meta_info->cartMeta->emailSend' => true
                ]);

            }
        }

        //p($ret);
        $res['error'] = _cv($ret, 'error');
        $res['data'] = _cv($ret, 'data');
        return response($res);
    }
    public function transactionCharge($params = []){

        if(!_cv($params, 'orderId', 'nn'))return response(['error'=>'order id not exists']);

        $payment = new Payment();
//        $params['orderId'] = 56;
//        $params['transactionId'] = '8PiTtkzUMxn22141235';
        $ret = $payment->transactionCharge($params);

        return response($ret);
    }
    public function transactionCallback($params = []){
        $request = Request();
//        p($_POST);
//        p($request->all());

        Log::debug($request->all(), ['transactionCallback']);
        $ret['redirectUrl'] = "https://tpay.tbcbank.ge/checkout/choose-payment-method/tpay-tbvqma2372015";
//        $ret['params'] = ['id'=>11,'amnt'=>22];

        return response($ret);
    }

    /// charge user points by order
    public function pointsCharging($params = []){
        $orderModel = new App\Models\Shop\OrderModel();
        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:false;
        $userPid = (Auth::user() && Auth::user()->p_id)?Auth::user()->p_id:false;

        $order = $orderModel->getOne(['id'=>$params['id'], 'user_id'=>$userId]);

        $chargePointsAmount = _cv($order, ['meta_info', 'total', 'subtotalPoints']);

        $request = new LtbRequests();
        $charging = $request->chargePoints(['userPid'=>$userPid, 'points'=>$chargePointsAmount]);

        // if(_cv($charging, 'charged') === true){
            WalletsModel::where('user_id', $userId)->where('type', 'points')->decrement('amount', $chargePointsAmount);
        // }

        return $charging;
    }

    ////// offers
    private function offerPrepare($params = []){

        if(!_cv($params, 'offer.id'))return [];

        if(_cv($params, 'offer.offer_type')=='gift'){
            return $this->offerGifts($params);
        }else{
            return $this->offerDiscount($params);
        }
    }

    private function offerDiscount($params = []){

        $locale = requestLan();
        $ret['info'] = _cv($params, ['offer','info', $locale]);
        $ret['calc_price'] = $price = _cv($params, 'price', 'nn');
        $discountAmount = _cv($params, 'offer.discount_amount', 'nn')?$params['offer']['discount_amount']:0;
        $discountDimension = _cv($params, 'offer.discount_dimension');

        if($discountDimension == 'amount'){
            $ret['calc_price'] = $price - $discountAmount;
            $ret['discountAmount'] = $discountAmount;
            $ret['description'] = "{$discountAmount} Gel.";

        }else if($discountDimension == 'percent'){
            $discountAmountCalculated = (($price * $discountAmount)/100);
            $ret['calc_price'] = $price - $discountAmountCalculated;
            $ret['discountAmount'] = round($discountAmountCalculated,2);
            $ret['description'] = "{$discountAmount} %";
        }

        $ret['calc_price'] = ($ret['calc_price'] < 0)?0:round($ret['calc_price'], 2);

        return $ret;
    }

    private function loyaltyDiscount($params = []){

        $discountPercent = $params['discountPercent'];

        $ret = $params['total'];

        $ret['discountAmount'] = (($ret['subtotal'] * $discountPercent)/100);
        $ret['subtotal'] = round($ret['subtotal'] - $ret['discountAmount'], 2);
        $ret['discountAmount'] = round($ret['discountAmount'],2);
        $ret['description'] = "Loyalty Discount {$discountPercent} %";

        return $ret;
    }

    private function offerGifts($params = []){
        $productModel = new ProductsModel();
        $locale = requestLan();
        $ret['info'] = _cv($params, ['offer','info', $locale]);
        $giftIds = _cv($params, ['offer','relation_gifts'], 'ar')?$params['offer']['relation_gifts']:[0];

        $gifts = $productModel -> getList(['id'=>$giftIds, 'limit'=>10]);

        $ret['gifts'] = _cv($gifts, 'list');


        return $ret;
    }

    private function calcGrandTotal($params = []){

        $total = [ 'discount'=>0, 'grandTotal'=>0, 'shipping'=>0, 'subtotal'=>0, 'subtotalPoints'=>0,
                    'subtotalRaw'=>0, 'subtotalBox'=>0,'subtotalBoxRaw'=>0, 'subTotalProducts'=>0, 'subTotalProductsRaw'=>0, 'subProducts'=>0, 'subProductsRaw'=>0, 'productsCount'=>0, 'points'=>0 ];


        foreach ($params['cart'] as $k=>$v){
            if(!isset($v['price']))continue;
            $total['subtotal'] += $v['subtotal'];
            $total['subtotalRaw'] += $v['subtotalRaw'];
            $total['subtotalBox'] += $v['subtotalBox'];
            $total['subtotalBoxRaw'] += $v['subtotalBoxRaw'];

            $total['productsCount'] += $v['quantityTotal'];

            if(isset($v['subProductsTotalPrice']) && is_numeric($v['subProductsTotalPrice'])) $total['subProducts'] += $v['subProductsTotalPrice'];
            if(isset($v['subProductsTotalPriceRaw']) && is_numeric($v['subProductsTotalPriceRaw'])) $total['subProductsRaw'] += $v['subProductsTotalPriceRaw'];
            if(isset($v['pointsCalcPrice']) && is_numeric($v['pointsCalcPrice']))$total['points'] += $v['pointsCalcPrice'];

        }

        $total['subtotalPoints'] = $total['points'];

        $total['subtotal'] = round($total['subtotal'], 2);
        $total['subtotalRaw'] = round($total['subtotalRaw'], 2);
        $total['subtotalBox'] = round($total['subtotalBox'], 2);
        $total['subtotalBoxRaw'] = round($total['subtotalBoxRaw'], 2);

        $total['subTotalProducts'] = round($total['subtotal']+$total['subtotalBox']+$total['subProducts'], 2);
        $total['subTotalProductsRaw'] = round($total['subtotalRaw']+$total['subtotalBoxRaw']+$total['subProductsRaw'], 2);

        $shipping = _cv($params, 'shipping.amount', 'nn')?$params['shipping']['amount']:0;
        $shippingDiscount = _cv($params, 'shipping.discount', 'nn')?$params['shipping']['discount']:0;

        $total['shipping'] = $shipping;
        $total['shippingDiscount'] = $shippingDiscount;
        $total['promoCodeDiscountAmount'] = 0;

        $total['discount'] = round(($total['subTotalProductsRaw'] - $total['subTotalProducts']) + $shippingDiscount, 2);

        /// after calculate discount of main products add sub products price
//        $total['subTotalProducts'] = round($total['subTotalProducts'] + $total['subProducts'], 2);

        $total['grandTotal'] = round(($shipping + $total['subTotalProducts']), 2);


        return $total;
    }

    private function checkStockQty($params = []){

        $cart = _cv($params, 'cart')?$params['cart']:[];
        $nomenclatureIds = [];
        foreach ($cart as $k=>$v){
            $nomenclatureIds[] = $v['sku'];
        }
//        p($nomenclatureIds);

        /// get real products quantity from service api
        $reqsModel = new LtbRequests();
        $stockData = $reqsModel->getStockInfo(['productIds'=>$nomenclatureIds]);

//        p($cart);
//        p($stockData);

        foreach ($cart as $k=>$v){
            /// set quantity Exceed (qtyExceed) to 0
            $params['cart'][$k]['qtyExceed'] = 0;
            /// if there is no info from api for this sku product do nothing
            if(!_cv($stockData, $v['sku']))continue;

            /// if selected quantity exceed stock quantity set exceed param and set quantity param to max available quantity
            if(_cv($stockData[$v['sku']], ['qty'], 'nn') && $stockData[$v['sku']]['qty'] < $v['quantity']){
                $params['cart'][$k]['qtyExceed'] = $v['quantity'] - $stockData[$v['sku']]['qty'];
                $v['quantity'] = $stockData[$v['sku']]['qty'];
            }

        }

        return $params['cart'];
    }

    private function acceptAdditionalDiscounts($params = []){
        if(!_cv($params, 'cart', 'ar'))return [];
        if(!_cv($params, ['cartMeta', 'discounts', 0, 'offerId']))return $params['cart'];

//        p($params['cartMeta']);

        foreach ($params['cartMeta']['discounts'] as $k=>$v){
            foreach ($params['cart'] as $kk=>$vv){

                /// if there is offer which has no loyalty discount, loyalty discount will not added above
                if(_cv($v, 'DiscountName') == 'contragentDiscountPercent' && _cv($vv, 'discount.0.loyalty') && strpos($vv['discount'][0]['loyalty'], 'discountNo_') !== false){
                    continue;
                }

                $vv = $this->calculateDiscount(['cartItem'=>$vv, 'discount'=>$v]);
                $params['cart'][$kk] = $vv;
            }
        }

        return $params['cart'];
    }

//    calculate discount price depend on offer information
    private function calculateDiscount($params = []){

        if(!_cv($params, ['cartItem','id'], 'nn'))return [];
        if(!_cv($params, ['discount'], 'ar'))return $params['cartItem'];


        if(!_cv($params, ['cartItem','discount'], 'ar'))$params['cartItem']['discount'] = [];
        if(!_cv($params, ['cartItem','boxDiscount'], 'ar'))$params['cartItem']['boxDiscount'] = [];

        $tmp = discountCalculator($price = $params['cartItem']['calcPrice'], $discountAmount = $params['discount']['discount_amount'], $discountType = $params['discount']['discount_dimension'], $params['discount']['offerId']);

        $tmp['data'] = $params['discount'];
        $tmpBox = discountCalculator($price = $params['cartItem']['boxCalcPrice'], $discountAmount = $params['discount']['discount_amount'], $discountType = $params['discount']['discount_dimension'], $params['discount']['offerId']);
        $tmpBox['data'] = $params['discount'];

        $params['cartItem']['calcPrice'] = $tmp['calcPrice'];
        $params['cartItem']['boxCalcPrice'] = $tmpBox['calcPrice'];

        $params['cartItem']['discount'][] = $tmp;
        $params['cartItem']['boxDiscount'][] = $tmpBox;


        $cartModel = new App\Models\Shop\CartModel();
        $params['cartItem'] = $cartModel->cartItemSubtotalCalculations($params['cartItem']);

        return $params['cartItem'];
    }

    private function memberRelatedDiscount(){
        if(!Auth::user())return [];

        $ret = [];


        $tmp = _psqlCell(Auth::user()->additional_info);
//            p($tmp);

        if((Auth::user()->status == 'person' || (Auth::user()->status == 'master' && _cv($tmp, ['master', 'physicalPerson'])==1) ) && _cv($tmp, ['contragents', 'loyaltyCard'])==1 && _cv($tmp, ['contragents', 'loyaltyDiscountPercent']) > 0){
            $ret['discount_amount'] = _cv($tmp, ['contragents','loyaltyDiscountPercent']);
            $ret['DiscountName'] = 'loyaltyDiscountPercent';
            $ret['discount_dimension'] = 'percent';
            $ret['offerId'] = '-';

        }else if( (Auth::user()->status == 'person' || Auth::user()->status == 'master') && _cv($tmp, ['contragents', 'contragentDiscountPercent']) > 0){
            $ret['discount_amount'] = _cv($tmp, ['contragents','contragentDiscountPercent']);
            $ret['DiscountName'] = 'contragentDiscountPercent';
            $ret['discount_dimension'] = 'percent';
            $ret['offerId'] = '-';

        }

        return $ret;

    }


    /// check and get cart products prices from api
    private function checkCartInfo($params = []){
/**
{
"IDNumber": "09876543210",
"ლოიალური_ბარათით":false,
"აქცია1პლიუს1":false,
"პრომოკოდი": "",
"Products": [
{
"სტრიქონის_ნომერი": 1,
"ნომენკლატურის_ID": "000014267",
"ზომის_ერთეულის_ID": "000013459",
"რაოდენობა": 4700
},
]
}

 */

        $params['cartMeta']['userInfo']['p_id'] = '45654654';
        if(!_cv($params, ['cartMeta', 'userInfo', 'p_id'])){
            $params['cartMeta']['error'] = 'pid not set';
            return $params;
        }

//        $req['IDNumber'] = '12345678910';
        $req['IDNumber'] = _cv($params, ['cartMeta', 'userInfo', 'p_id']);
        $req['ლოიალური_ბარათით'] = false;
        $req['აქცია1პლიუს1'] = false;
        $req['პრომოკოდი'] = '';

        $cart = _cv($params, 'cart')?$params['cart']:[];
        $i=1;
        foreach ($cart as $k=>$v){
            $req['Products'][] = [
                "სტრიქონის_ნომერი"=> $i++,
                "ნომენკლატურის_ID"=> sku($v['sku']),
                "ზომის_ერთეულის_ID"=> sku($v['dimension_id']),
                "რაოდენობა"=> $v['quantity']
            ];
        }
//        p($req);

        /// get real products quantity from service api
        $reqsModel = new LtbRequests();
        $cartInfo = $reqsModel->getCartInfo($req);

        if(_cv($cartInfo, 'error')){
            if(strpos($cartInfo['error'], 'ვერ მოიძებნა ბაზაში')){
                $newClient = $this->registerContragent(_cv($params, ['cartMeta', 'userInfo']));
            }

            $params['cartMeta']['error'] = $cartInfo['error'];
        }else{

            $params['cart'] = $this->updateCartInfoFromApi($cart, $cartInfo);
            $params['cartMeta']['error'] = '';

        }



//        p($cart);
//        p($stockData);

        return $params;
    }

    /// update local cartinfo with results from service api
    private function updateCartInfoFromApi($cart=[], $apiCartInfo=[]){
        if(!_cv($apiCartInfo, 'Products'))return $cart;


        $ret = [];
        foreach ($apiCartInfo['Products'] as $k=>$v){
            $ret[$v['ნომენკლატურის_ID']] = $v;
        }


        foreach ($cart as $k=>$v){
            $tmp = _cv($ret, sku($v['sku']));
            if(!$tmp)continue;

            $cart[$k]['prices'] = $tmp;
            $cart[$k]['quantity'] = $tmp['რაოდენობა'];
            $cart[$k]['price'] = $tmp['ფასი'];
            $cart[$k]['subtotal'] = $tmp['თანხა'];

            if(_cv($tmp, ['ავტომატური_ფასდაკლების_თანხა'], 'nn')){
                $cart[$k]['discount'] = [$tmp['ავტომატური_ფასდაკლების_თანხა'], ($tmp['თანხა']+$tmp['ავტომატური_ფასდაკლების_თანხა']), $tmp['ავტომატური_ფასდაკლების_პროცენტი'], 'auto'];
            }else if(_cv($tmp, ['ხელოვნური_ფასდაკლების_თანხა'], 'nn')){
                $cart[$k]['discount'] = [$tmp['ხელოვნური_ფასდაკლების_თანხა'], ($tmp['თანხა']+$tmp['ხელოვნური_ფასდაკლების_თანხა']), $tmp['ხელოვნური_ფასდაკლების_პროცენტი'], 'manual'];
            }

        }
        return $cart;
    }

    private function getUserPoints($params=[]){

        if(!_cv($params, 'user_id', 'nn'))return 0;
        $walletMdel = new App\Models\Shop\WalletsModel();
        $data = $walletMdel->getOne(['whereRaw'=>['user_id='.$params['user_id'], 'type="points"']]);

        return _cv($data, 'amount')?$data['amount']:0;
    }

    /// check if contragent exists
    /// if there is no contragent register it
    private function registerContragent($params = []){
        if(!Auth::user() || !Auth::user()->p_id)return false;
        $reqsModel = new LtbRequests();
        $info = _psqlCell(Auth::user()->additional_info);

        $userData = _cv($info, [Auth::user()->status]);

        $data = [
            "Contragent"=> _cv($userData, 'fullname'),
            "IDNumber"=> _cv($params, 'p_id')?$params['p_id']:Auth::user()->p_id,
            "კატეგორია"=> 'საცალო',
            "სამართლებრივი_ფორმა"=> 'ფიზიკური პირი',
            "უცხო_ქვეყნის_მოქალაქე"=> false,
            "ელ_ფოსტა"=> _cv($params, 'email')?$params['email']:Auth::user()->email,
            "მობილურის_ნომერი"=> _cv($params, 'phone')?$params['phone']:Auth::user()->phone
        ];

        $cartInfo = $reqsModel->registerContragent($data);
//        p($cartInfo);
        return $cartInfo;

    }


    private function sendEmails($to=[], $tpl = '', $body = ''){
//p($to);
        if(!is_array($to))return false;

        foreach ($to as $k=>$v) {
            if (!filter_var($v, FILTER_VALIDATE_EMAIL)) unset($to[$k]);
            $to[$k] = trim($v);
        }
        if(!isset($to[0]))return false;

        try {
            return Mail::to($to)->send($tpl);
        } catch (Excepion $e) {
            return response($e->getMessage());
        }

    }

    public function sendOrderStatusEmail($params=[]){
        if(!_cv($params, 'orderId', 'nn'))return ['error'=>'order id not set'];
        $orderModel = new OrderModel();
        $order = $orderModel->getOne();

        $toEmail = $order['meta_info']['cartMeta']['userInfo']['email'];

        $orderData = "";
        foreach ($order['meta_info']['cart'] as $v){
            $orderData .= "<tr><td>{$v['title']}</td> <td>x {$v['quantity']}{$v['dimension']}</td></tr>";
        }

        $orderData .= "<tr><td>".tr('shipping')."</td><td>{$order['meta_info']['total']['shipping']}₾</td></tr>";
        $orderData .= "<tr><td>".tr('grand total')."</td><td>{$order['meta_info']['total']['grandTotal']}₾</td></tr>";

        $orderData = "<table>{$orderData}</table>";

//        p($orderData);
//        p( $order );
//        p( $order['meta_info']['cart'] );

//        return false;
        $subject = tr('Order')." #".str_pad($params['orderId'], 6, 0, STR_PAD_LEFT)." - ".tr($order['order_status']);
        $res = Email::sendEmail([
            'to' => $toEmail,
            'template' => 'order',
            'subject' => $subject,
            'vars' => [
                ['name' => 'order_id', 'content' => "<h2>".tr('order').' #'.str_pad($params['orderId'], 6, 0, STR_PAD_LEFT)."</h2>"],
                ['name' => 'status', 'content' => tr($order['order_status'])],
                ['name' => 'content', 'content' => $orderData],
            ],
            'content' => [
                ['name' => 'header', 'content' => $orderData, 'order_id'=>str_pad($params['orderId'], 6, 0, STR_PAD_LEFT)]
            ]

        ]);


    }

}
