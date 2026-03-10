<?php
namespace App\Http\Controllers\Admin\Shop;

use App\Http\Controllers\Admin\Shop\Payments\Payment;
use App\Models\Shop\OfferModel;
use App\Rules\ReCaptcha;
use Illuminate\Http\Request;
use App\Models\Shop\OrderModel;
use App\Models\Admin\OptionsModel;
use App\Models\Shop\ProductsModel;
use App\Models\Shop\ShippingModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Shop\AttributeModel;
use App\Models\Shop\LocationsModel;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\ExcelServiceProvider;
use App\Http\Controllers\Admin\Shop\services\LtbRequests;
use App\Http\Controllers\Admin\Shop\Exports\CatalogExport;


/**
 * main controller for shop
 */
class ShopSiteMain extends Controller
{


    //
    protected $mainModel;
    protected $error = false;

    ///////// get main categories
    public function getMainCategories($params = [])
    {

        return response($this->getCategories());


    }

    ///////// get main categories
    public function getCategories()
    {

        $optionsModel = new OptionsModel();

        $categoryAttributeTypeId = $optionsModel->getOneBy(['content_group'=>'shop_attribute_type', 'key'=>'category', 'return'=>'id']);
        if(!is_numeric($categoryAttributeTypeId))return response(['status'=>false]);

        $AttributeModel = new AttributeModel();
//        $res = $AttributeModel->getList(['attribute'=>$categoryAttributeTypeId, 'translate'=>requestLan(), 'limit'=>1000, 'select'=>"shop_attribute.id, shop_attribute.slug, shop_attribute.attribute, shop_attribute.conf, shop_attribute.pid "]);
        $res = $AttributeModel->getList([
            'attribute'=>$categoryAttributeTypeId,
            'translate'=>requestLan(),
            'limit'=>1000,
            'whereRaw'=>1000,
            'having' => [['products >= 1']],
            'customSelect' => "shop_attribute.id, shop_attribute.slug, shop_attribute.attribute, shop_attribute.conf, shop_attribute.pid, count(joinedTable_shop_product_attribute_relation.product_id) as products ",

        'fields'=>['id', 'slug', 'attribute', 'conf', 'pid', 'title']
        ]);
        $res = $res['list'];

        return $res;


    }

    ///////// get attributes
    public function getAttributes($params = [])
    {
        $AttributeModel = new AttributeModel();
        $params['translate'] = requestLan();
        $params['limit'] = _cv($params, 'limit', 'nn')?$params['limit']:10000;
        $params['attribute'] = _cv($params, 'attribute', 'nn')?$params['attribute']:'';
        $params['customSelect'] = "shop_attribute.id, shop_attribute.slug, shop_attribute.attribute, shop_attribute.conf, shop_attribute.pid, count(joinedTable_shop_product_attribute_relation.product_id) as products ";
        $params['fields'] = ['attribute', 'conf','id','pid','slug','title','sort', 'colors', 'color', 'squareimage', 'products'];
        $params['having'] = [['products >= 1']];
//        $params['disableEmptyAttributes'] = 1;

//        p($params);
        $cacheKey = 'getAttributes_'.md5(json_encode($params));
        $value = Cache::store('file')->get($cacheKey);
        if($value) return $value;

//p($params);
        $res = $AttributeModel->getList($params);

        $res = $res['list'];

        Cache::put($cacheKey, response()->json($res, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_INDX', 2));

        return response()->json($res, 200, [], JSON_UNESCAPED_UNICODE);
//        return response($res, 200, [], JSON_UNESCAPED_UNICODE);
    }

    ///////// get products
    public function getProducts($params = [])
    {

        $params['status'] = 'published';
        $params['qtyMore'] = 1;
        $params['limit'] = _cv($params, ['limit'], 'nn')?$params['limit']:20;
        $params['translate'] = requestLan();

        $params['sortField'] = $this->sortFieldsMaping(_cv($params, ['sortField']));
//        $params['price'] = [0,20];

        $userId = Auth::user()?Auth::user()->id:'';

        $this->historyList($params);
        $cacheKey = 'getProducts_'.md5(json_encode($params).$userId);
        $value = Cache::store('file')->get($cacheKey);
        if($value) return $value;

        $productModel = new ProductsModel();

        /// select only offer products
        if(_cv($params, ['offer_target'])){
            $params['id'] = $this->getDiscountProductIds($params);

            if(!isset($params['id'][0]))$params['id'] = [0];
//            $params['availableAttributes'] = 1;
        }else{
//            $params['availableAttributes'] = 1;
        }


        $res = $productModel -> getList($params);

        /// alternate metod to find all attributes avalable on all filtered offer products
//        if(_cv($params, ['offer_target'])){
        if(_cv($params, ['attributes', '502'], 'ar')){
            $params['category'] = $params['attributes'][502];
            $availableAttributes = $productModel -> getAvailableAttributes($params);
            $res['attributes'] = $availableAttributes;
        }

        Cache::put($cacheKey, response()->json($res, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_GET_PRODUCTS', 200));

        return response()->json($res, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function getDiscountProducts($params = [])
    {

        $offerModel = new OfferModel();
        $productModel = new ProductsModel();
        $offerParams = [];
        $dateNow = date('Y-m-d');

        $lan = requestLan();
//
//        if(isset($params['slug']))$offerParams['slug'] = $params['slug'];
//        if(_cv($params, ['id'], 'nn'))$offerParams['id'] = $params['id'];
//        if(_cv($params, ['offer_target']))$offerParams['offer_target'] = $params['offer_target'];
//
//        if(empty($offerParams))return [];
//
//        $offerParams['translate'] = $lan;
//        $offerParams['offer_type'] = 'discount';
//        $offerParams['status'] = 'published';
//
//        $offerParams['whereRaw'][] = "start_date <= '{$dateNow} 00:00:00'";
//        $offerParams['whereRaw'][] = "end_date>= '{$dateNow} 23:00:00'";
//p($offerParams);
        $cacheKey = base64_encode(json_encode($params));
        $value = Cache::store('file')->get('getDiscountProducts' . $cacheKey);
        if ($value) return $value;
//
////        p($offerParams);
//        $offers = $offerModel->getList($offerParams);
////        p($offers);
//        $mergedArray = [];
//
//        foreach ($offers['list'] as $k=>$v){
//            if(!isset($v['relation_product']) || !is_array($v['relation_product']))continue;
//            $mergedArray = array_merge($mergedArray, $v['relation_product']);
//        }
//
//        if(!isset($mergedArray[0]))return [];

        $discountProductIds = $this->getDiscountProductIds($params);
//        $products = $productModel->getList(['id'=>$mergedArray, 'translate' => $lan, 'orderField'=>'views', 'orderDirection'=>'desc', 'limit'=>_cv($params, 'limit', 'nn'), 'page'=>_cv($params, 'page', 'nn')]);
        $products = $productModel->getList(['id'=>$discountProductIds, 'translate' => $lan, 'orderField'=>'views', 'orderDirection'=>'desc', 'limit'=>_cv($params, 'limit', 'nn'), 'page'=>_cv($params, 'page', 'nn')]);

        Cache::put('getDiscountProducts' . $cacheKey, response()->json($products, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_LIST_VIEW', 2));

        return response()->json($products, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function getDiscountProductIds($params = []){
        $offerModel = new OfferModel();
        $offerParams = [];
        $dateNow = date('Y-m-d');

        $lan = requestLan();

        if(isset($params['slug']))$offerParams['slug'] = $params['slug'];
        if(_cv($params, ['id'], 'nn'))$offerParams['id'] = $params['id'];
        if(_cv($params, ['offer_target']))$offerParams['offer_target'] = $params['offer_target'];

        if(empty($offerParams))return [];

        $offerParams['translate'] = $lan;
        $offerParams['offer_type'] = 'discount';
        $offerParams['status'] = 'published';

        $offerParams['whereRaw'][] = "start_date <= '{$dateNow} 00:00:00'";
        $offerParams['whereRaw'][] = "end_date>= '{$dateNow} 23:00:00'";

        $cacheKey = base64_encode(json_encode($params).json_encode($offerParams));
        $value = Cache::store('file')->get('getDiscountProductsIds' . $cacheKey);
        if ($value) return $value;

        $offers = $offerModel->getList($offerParams);

        $mergedArray = [];

        foreach ($offers['list'] as $k=>$v){
            if(!isset($v['relation_product']) || !is_array($v['relation_product']))continue;
            $mergedArray = array_merge($mergedArray, $v['relation_product']);
        }

        Cache::put('getDiscountProductsIds' . $cacheKey, $mergedArray);

        return $mergedArray;
    }

    public function getOfferProducts($params = [])
    {

        $offerModel = new OfferModel();
        $productModel = new ProductsModel();
        $offerParams = [];

        $lan = requestLan();

        if(isset($params['slug']))$offerParams['slug'] = $params['slug'];
        if(_cv($params, ['id'], 'nn'))$offerParams['id'] = $params['id'];
        if(_cv($params, ['offer_target']))$offerParams['offer_target'] = $params['offer_target'];
        if(empty($offerParams))return [];

        $offerParams['translate'] = $lan;

        $offer = $offerModel->getOne($offerParams);
//        p($offer);

        if(!isset($offer['relation_product'][0]))return [];

        $limit = _cv($params, 'limit', 'nn')?$params['limit']:50;
        $products = $productModel->getList(['id'=>$offer['relation_product'], 'translate' => $lan, 'limit'=>$limit]);
        $products['list'] = $this->additionalDiscount($products['list'], $offer);
        return response()->json($products, 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function additionalDiscount($productsList = [], $offer = []){
        foreach ($productsList as $k=>$v){
            $tmp = discountCalculator($price = $v['calcPrice'], $offer['discount_amount'], $offer['discount_dimension'], $offer['id']);
            $v['discount'][] = $tmp;
            $v['calcPrice'] = $tmp['calcPrice'];
            $productsList[$k] = $v;
        }

        return $productsList;
    }

    public function getProductsGrouped($params = [])
    {
        $productModel = new ProductsModel();
        $res = $productModel -> getProductsGrouped($params);
        return response($res);
    }

    public function getLocations($params=[]){
        $reqsModel = new LocationsModel();
        $res = $reqsModel->getList($params);
        return response($res, $res?200:201);
    }
    public function getOrder($params = []){

        $requestData['g-recaptcha-response'] = $_COOKIE['grecaptcha'] ?? $params['g-recaptcha-response'];
        $validator = Validator::make($requestData, [
            'g-recaptcha-response' => ['required', new ReCaptcha]
            ]
        );
        if($validator->fails()){
            return response(['errors'=>$validator->errors()], 400);
        }

        $orderModel = new OrderModel();
        $order = $orderModel->getOne($params);

        return response($order);
    }

    public function updateWholesale($params = []){

        $LtbRequests = new LtbRequests();
        $res = $LtbRequests->getWholesale($params);


        return response($res);
    }

    public function exportCatalog($params = []){

        $productModel = new ProductsModel();
        $params['status'] = 'published';

        if(_cv($params, 'attributes', 'ar') || _cv($params, 'limit') == 'all')$params['limit'] = 9999;


        $params['limit'] = _cv($params, ['limit'], 'nn')?$params['limit']:20;

        $params['translate'] = requestLan();
        $params['sortField'] = 'slug';

        // cache
        $cacheKey = 'exportCatalog_'.md5(json_encode($params));
        $value = Cache::store('file')->get($cacheKey);
        if($value){
            return Excel::download(new CatalogExport($value), 'catalog.xlsx');
        }

        $res = $productModel -> getList($params);

        $list[] = [
            'sku' => 'არტიკული',
            'title' => 'ნომენკლატურა',
            'box_count' => 'რაოდენობა ყუთში',
            'dimension' => 'საზომი ერთეული',
            'price' => 'ფასი',
        ];
        foreach ($res['list'] as $k=>$v){
            $list[] = [
                'sku' => $v['sku'],
                'title' => $v['title'],
                'box_count' => $v['box_count'],
                'dimension' => $v['dimension'],
                'price' => $v['price'],
            ];
        }

        Cache::put($cacheKey, $list, env('CACHE_DAY', 20000));

//        return response($res);
        return Excel::download(new CatalogExport($list), 'catalog.xlsx');


    }

    public function importProducts(){
        $reqsModel = new LtbRequests();

        if(date('h:i') == '12:00'){
            $this->updateWholesale();
            $getFromService = $reqsModel->getCatalogFromSevice();
            if($getFromService === 1) return response(['status' => 'imported']);
        }

        $updProducts = $reqsModel->updateCatalogDb();
        return $updProducts;

    }


    public function getGiftedProducts($params = []){

        $offerModel = new OfferModel();
        $res = $offerModel->getList(['offer_type'=>'gift', 'status'=>'published']);

        $ids = [];

        foreach ($res['list'] as $k=>$v){
            if(_cv($v, 'relation_offer_type_data_ids', 'ar')){
                $ids = array_merge($ids, $v['relation_offer_type_data_ids']);
            }
        }

        if(count($ids)==0)return response([]);

        $productModel = new ProductsModel();
        $products = $productModel->getList(['id'=>$ids, 'status'=>'published']);

        return response()->json($products, 200, [], JSON_UNESCAPED_UNICODE);

    }

    public function getCatalog1Plus1Skus(){

        return response($this->getCatalog1Plus1());

    }

    public function expiredVacancies(){

        $currentDate = date('Y-m-d');
        $vacancies = DB::select(("SELECT * FROM pages left join pages_meta ON pages.id = pages_meta.data_id where pages.content_type = 'vacancy' and pages_meta.key = 'end_date' and pages_meta.val < '$currentDate'"));

        $ids = array_column($vacancies, 'data_id');
        $res = DB::table('pages')->whereIn('id', $ids)->update([
            'page_status' => 0
        ]);

        return 'Updated:'.$res;

    }

    public function getOffers($params = []){

        $offerModel = new OfferModel();
        $params['status'] = 'published';
        return $offerModel -> getList($params);

    }

    public function getAvailableAttributes(){

    }


    private function sortFieldsMaping($sortField = ''){

        if($sortField=='title')return 'slug';
        if($sortField=='price')return 'price';
        if($sortField=='popularity')return 'views';

        return 'id';

    }


    /// to view requested data to payment provider run this method and send orderId parameter
    /// before run this method uncomment tbc.php line:136;
    /// after running this method imediately comment tbc.php line:136;
    public function orderTransactionRequest($params = []){
        if(!_cv($params,['orderId'], 'nn'))return 'order id not set';
//        p($params);
        $orderModel = new OrderModel();
        $order = $orderModel->getOne(['id'=>$params['orderId']]);
//        p($order);

        $payment = new Payment(['paymentMethod' => _cv($order, ['meta_info','cartMeta','paymentMethod'])]);
        $ret = $payment->transactionStart(['orderId'=>$params['orderId'], 'totalAmount'=>$order['total_amount'], 'userId'=>1]);

        p($ret);

    }

/// collect history
    public function historyView($params = []){
        $qr['user_id'] = Auth::user()?Auth::user()->id:0;
        $qr['product_id'] = $params['id'];
        $qr['ip'] = $_SERVER['REMOTE_ADDR'];
        $qr['updated_at'] = $qr['created_at'] = date('Y-m-d H:i:s');

        DB::table('shop_history_view')->insert($qr);
    }
    public function historyList($params = []){
        $qr['user_id'] = Auth::user()?Auth::user()->id:0;
        $qr['query'] = json_encode($params);
        $qr['ip'] = $_SERVER['REMOTE_ADDR'];
        $qr['updated_at'] = $qr['created_at'] = date('Y-m-d H:i:s');

        DB::table('shop_history_list')->insert($qr);
    }


}
