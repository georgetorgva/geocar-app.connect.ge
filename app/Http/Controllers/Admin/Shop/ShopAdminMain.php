<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Http\Controllers\Admin\Shop\Payments\Payment;
use App\Http\Controllers\Admin\Shop\services\LtbRequests;
use App\Models\Admin\OptionsModel;

use App\Models\Shop\CouponsModel;
use App\Models\Shop\LocationsModel;
use App\Models\Shop\OrderModel;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

use App\Models\Shop\ShippingModel;
use App\Models\Shop\ProductsModel;
use App\Models\Shop\StockModel;
use App\Models\Shop\AttributeModel;
use App\Models\Shop\OfferModel;
use function Nette\Utils\isEmpty;

/**
 * main controller for shop
 */

class ShopAdminMain extends Controller
{

    public function shopIndex(){
        $ret['attributeTypes'] = [];
        $ret['conf'] = config('adminshop');
        $ret['userGroupsList'] = config('adminltb.masters.regularFields.member_group.values');

        return response($ret);
    }

    public function getAttributesTermsList($params = []){
        $optionsModel = new OptionsModel();
        $fields = _cv($params, 'fields')?$params['fields']:['id', 'slug', 'title'];
        $attributeType = _cv($params, 'attribute');
        $ret = [];

        $attributeTypeId = $optionsModel->getOneBy(['content_group'=>'shop_attribute_type', 'key'=>$attributeType, 'return'=>'id']);
        if(is_numeric($attributeTypeId)){
            $AttributeModel = new AttributeModel();
            $attributeTerms = $AttributeModel->getList(['attribute'=>$attributeTypeId, 'translate'=>requestLan(), 'limit'=>1000, 'fields'=>$fields ]);
            $ret = _cv($attributeTerms, 'list');
        }

        return $ret;
    }

    // orders control methods
    public function updOrder($params = [])
    {
        $model = new OrderModel();
//p($params);
        $id = $model -> updItem($params);
        $res = $model -> getOne(['id' => $id]);

        return response($res);
    }
    public function getOrders($params = [])
    {
        $model = new OrderModel();

        $res = $model -> getList($params);

        return response($res);
    }
    public function getOrder($params = [])
    {
        $model = new OrderModel();

        $res = $model -> getOne($params);

        return response($res);
    }
    public function updOrderStatus($params = [])
    {
        $model = new OrderModel();

        $deleteId = $model -> updateStatus(['id' => _cv($params, 'id')]);

        if (!$deleteId) return response('Error while deleting product', 201);

        return response(['id' => $deleteId]);
    }


    // stock control methods
    public function updStock($params = [])
    {
        $StockModel = new StockModel();

        $id = $StockModel -> updItem($params);
        $res = $StockModel -> getOne(['id' => $id]);

        return response($res);
    }
    public function updStockStatus($params = [])
    {
        $StockModel = new StockModel();

        $statusUpdId = $StockModel -> updStatus(['id'=>_cv($params, 'id'),'status'=>_cv($params, 'status')]);

        return !$statusUpdId ? response('Error while updating stock status', 201) : response(['id' => $statusUpdId]);
    }
    public function getStockList($params = [])
    {
        $StockModel = new StockModel();

        $res = $StockModel -> getList($params);

        return response($res);
    }
    public function getStockItem($params = [])
    {
        $StockModel = new StockModel();

        $res = $StockModel -> getOne($params);

        return response($res);
    }
    public function delStockItem($params = [])
    {
        $stockModel = new StockModel();

        $deleteId = $stockModel -> del(['id' => _cv($params, 'id')]);

        if (!$deleteId) return response('Error while deleting product', 201);

        return response(['id' => $deleteId]);
    }

    public function getAllAttributes($params = [])
    {
        $attributesModel = new AttributeModel();
        $params['translate'] = 1;
        $params['limit'] = 10000;

        $attributes = $attributesModel -> getList($params);
        if(!_cv($attributes, 'list'))return [];
        return $attributes;
//
//        $attributeTypes = $optionsModel -> getListByRaw(['content_group' => 'shop_attribute_type']);
//
//        foreach ($attributeTypes as $key => $value)
//        {
//            $attributeTypes[$key]['list'] = $attributesModel -> getList(['attribute' => $value['id']]);
//        }
//
//        return $attributeTypes;
    }

    public function getAttributesByType($params = [])
    {
        $optionsModel = new OptionsModel();
        $categoryAttributeTypeId = $optionsModel -> getOneBy(['content_group' => 'shop_attribute_type', 'key'=> _cv($params, 'attributeType'), 'return' => 'id']);

        if (!is_numeric($categoryAttributeTypeId)) return response(['status' => false]);

        $AttributeModel = new AttributeModel();

        $res = $AttributeModel -> getList(['attribute' => $categoryAttributeTypeId]);

        return response($res);
    }

    // product catalog methods
    public function getProduct($params = [])
    {
        $productModel = new ProductsModel();

        $res = $productModel -> getOne($params);

        return $res;
    }
    public function updProduct($params = [])
    {
        $productModel = new ProductsModel();

        $id = $productModel -> upd($params);

        if(!is_numeric($id))return response($id, 201);

        $res = $productModel -> getOne(['id' => $id]);

        return is_array($res) ? response($res) : response('Error', 201);

    }
    public function getProductsList($params = [])
    {

        if(_cv($params, 'sku', 'ar')){
            $params['limit'] = 10000;
        }

        if(_cv($params, 'attributes', 'ar')){
            foreach ($params['attributes'] as $k=>$v){
                if(!is_array($params['attributes'][$k]))unset($params['attributes'][$k]);
            }
        }

        $productModel = new ProductsModel();
        return $productModel -> getList($params);
    }
    public function deleteProduct($params = [])
    {
        $productModel = new ProductsModel();

        $deleteId = $productModel -> del(['id' => _cv($params, 'id')]);

        if (!$deleteId) return response('Error while deleting product', 201);

        return response(['id' => $deleteId]);
    }
    public function updProductStatus($params = [])
    {
        $productModel = new ProductsModel();

        $statusUpdId = $productModel -> updStatus(['id'=>_cv($params, 'id'),'status'=>_cv($params, 'status')]);

        if (!$statusUpdId) return response('Error while updating product status', 201);

        return response(['id' => $statusUpdId]);
    }
    public function getProductGroups($params = [])
    {
        $productModel = new ProductsModel();
        $params['limit'] = 1000;
        $res = $productModel -> getProductGroups($params);

        if (!$res) return response('Error selecting groups data', 201);
        return response($res);
    }
    public function addProductGroup($params = [])
    {

//        p($params);

        $productModel = new ProductsModel();
        $res = $productModel -> addProductGroup($params);

        if (!$res) return response('Error saving groups data', 201);

        return $this->getProductGroups();
        return false;
    }

    // shipping methods
    public function getShipping($params = [])
    {
        $shipping = new ShippingModel();
        $res = $shipping -> getOne($params);
        return $res;
    }
    public function updateShippings($params = [])
    {
        $shipping = new ShippingModel();

        $id = $shipping -> updItem($params);

        if(!is_numeric($id))return response($id, 201);

        $ret = $shipping -> getOne(['id' => $id]);

        return response($ret, _cv($ret, 'id', 'nn')?200:201);

    }
    public function getShippings($params = [])
    {
        $params['status'] = ['published', 'hidden'];
        $shipping = new ShippingModel();
        $res = $shipping -> getList($params);
        return $res;
    }
    public function updShippingStatus($params = [])
    {
        $productModel = new ShippingModel();

        $statusUpdId = $productModel -> updStatus(['id'=>_cv($params, 'id'),'status'=>_cv($params, 'status')]);

        if (!$statusUpdId) return response('Error while updating status', 201);

        return response(['id' => $statusUpdId]);
    }

    public function getCountries()
    {
        $res = DB::table('country') -> select(['domain', 'name', 'region']) -> orderBy('domain') -> get() -> toArray();

        return is_array($res) ? response($res) : response('Error', 201);
    }

    // shop offers methods
    public function getOffersList($params = [])
    {
        $offerModel = new OfferModel();
        $params['status'] = 'published';
//        p($params);
        return $offerModel -> getList($params);
        return [];
    }
    public function getOffer($params = [])
    {
        $offerModel = new OfferModel();

        $res = $offerModel -> getOne(['id' => _cv($params, 'id')]);

        return $res;
    }
    public function updOffer($params = [])
    {
//        p($params);

        /// exclude unnecessary data
        if(_cv($params, 'offer_type') == 'coupons' || _cv($params, 'offer_target') == 'cart'){
            $params['relation_attributes'] = [];
            $params['relation_dependentProducts'] = [];
            $params['relation_gifts'] = [];
            $params['relation_offer_type_data_ids'] = [];
            $params['relation_products'] = [];
        }else if(_cv($params, 'offer_type') == 'gift'){
            $params['relation_attributes'] = [];
        }

        $params['relationNode'] = _cv($params, 'offer_type');


        $offerModel = new OfferModel();
//p($params);
        $id = $offerModel -> updItem($params);

        if(!is_numeric($id)) return response($id, 201);

        $params['id'] = $id;

        $res = $offerModel -> getOne(['id' => $id]);

        return $res;
    }
    public function deleteOffer($params = [])
    {
        $offerModel = new OfferModel();

        $deleteId = $offerModel -> deleteItem(['id' => _cv($params, 'id')]);

        if (!$deleteId) return response('Error while deleting offer', 201);

        return response(['id' => $deleteId]);
    }

    // shop coupons methods
    public function getCouponsList($params = [])
    {
        $couponsModel = new CouponsModel();
//        $params['status'] = 'published';
        return $couponsModel -> getList($params);
    }
    public function updCouponsList($params = [])
    {
//        p($params);
        $couponsModel = new CouponsModel();
        $res = $couponsModel -> updCoupons($params);

//        $params['status'] = 'published';
        return $couponsModel -> getList($params);
    }


    public function productsForSmartMenu($params = []){
        $productsModel = new ProductsModel();
        $params['selectFields'] = "shop_products.id, shop_products.slug, shop_products.sku";
        $params['page_status'] = 1;
        $params['translate'] = config('app.locale');

        if(_cv($params, 'searchedWord')){
            $params['searchByOr']['slug'] = $params['searchedWord'];
            $params['searchByOr']['sku'] = $params['searchedWord'];
        }

        $res['products'] = $productsModel->getList($params);

        return response($res);
    }

    ///// locations
    public function getLocations($params=[]){
        $reqsModel = new LocationsModel();
        $params['limit'] = 200;
        $res = $reqsModel->getList($params);
        return response($res, $res?200:201);
    }
    public function getLocation($params=[]){
        $reqsModel = new LocationsModel();
        $list = $reqsModel->getOne($params);
        return response($list, _cv($list, 'id', 'nn')?200:201);
    }
    public function updateLocations($params=[]){
        $reqsModel = new LocationsModel();

        $ret = $reqsModel->updItem($params);

        if(is_numeric($ret))$ret = $reqsModel->getOne(['id'=>$ret]);

        return response($ret, _cv($ret, 'id', 'nn')?200:201);
    }
    public function deleteLocations($params = [])
    {
        $offerModel = new LocationsModel();

        $deleteId = $offerModel -> hardDeleteItem(['id' => _cv($params, 'id')]);

        if (!$deleteId) return response('Error while deleting', 201);

        return response(['id' => $deleteId]);
    }

    //// IMPORT DATA
    /// import products catalog from ltb service into local storage file
    public function importCatalogFromService(){
        $reqsModel = new LtbRequests();
        $res = $reqsModel->getCatalogFromSevice();

        return response($res, $res?200:201);
    }

    /// update db from locale storage file
    public function updateShopFromLocalStorage(){

        $reqsModel = new LtbRequests();
        $res = $reqsModel->updateCatalogDb();
//        p($res);
        return response($res, $res?200:201);
    }

    /// import Components from ltb service into local storage file
    public function importComponentsFromService(){
        $reqsModel = new LtbRequests();
        $res = $reqsModel->getComponentsFromService();
        return response($res, $res?200:201);
    }

    /// update db from locale storage file
    public function updateComponentsFromLocalStorage(){

        $reqsModel = new LtbRequests();
        $res = $reqsModel->updateComponentsDb();

        return response($res, $res?200:201);
    }

    public function importImages($params = []){

        $reqsModel = new LtbRequests();
        $res = $reqsModel->imagesImport($params);

        return response($res, $res?200:201);
    }

    ///////// get main categories
    public function getCategories()
    {

        $optionsModel = new OptionsModel();

        $categoryAttributeTypeId = $optionsModel->getOneBy(['content_group'=>'shop_attribute_type', 'key'=>'category', 'return'=>'id']);
        if(!is_numeric($categoryAttributeTypeId))return response(['status'=>false]);

        $AttributeModel = new AttributeModel();
        $res = $AttributeModel->getList(['attribute'=>$categoryAttributeTypeId, 'translate'=>requestLan(), 'limit'=>1000, 'select'=>"shop_attribute.id, shop_attribute.slug, shop_attribute.attribute, shop_attribute.conf, shop_attribute.pid "]);

        return $res;

    }

    public function getShippingList($params=[]){
        $reqsModel = new ShippingModel();
        $res = $reqsModel->getList(['sssselectFields'=>"shop_shipping.id, shop_shipping.slug"]);
        return response($res, $res?200:201);
    }

    // update payment status (refund/charge)
    public function transactionAction($params = []){
        $model = new Payment();

        $order = [];
        $orderModel = new OrderModel();
        $order['id'] = $params['id'];
        $conf = config('adminshop.order.order_status');

        if($params['action'] == 'refund'){
            $response = $model->transactionRefund($params);
            if(_cv($response, 'data.status') == 'returned'){
                $order['order_status'] = $conf[20]; /// refund;
            }
        } elseif($params['action'] == 'charge'){
            $response = $model->transactionCharge($params);
            if (_cv($response, 'data.status') == 'paid') {
                $order['order_status'] = $conf[1]; ///'processing';
            }
        }

        $id = $orderModel->updItem($order);
        $res = $orderModel->getOne(['id' => $id]);

        return response($res);
    }


    public function getActiveUsers($params = []){

        $req['status'] = ['master', 'person'];
        $req['limit'] = _cv($params, 'limit', 'nn')?$params['limit']:10;
        $req['fields'] = ['id','email', 'username', 'fullname', 'phone', 'p_id', 'status'];

        if(_cv($params, 'id', 'ar')){
            $req['id'] = $params['id'];

        }
        if(_cv($params, 'search')){
//            $req['address'] = $params['search'];
//            $req['additional_info'] = $params['search'];
            $req['searchByOr']['address'] = $params['search'];
            $req['searchByOr']['additional_info'] = $params['search'];
            $req['searchByOr']['email'] = $params['search'];
            $req['searchByOr']['fullname'] = $params['search'];
            $req['searchByOr']['p_id'] = $params['search'];
        }

        $userModel = new User();
        $list = $userModel->getList($req);

        return $list;

    }

    /**
    run url to reduce categories: https://ltb.ge/api/view/smartShop/reduceCategories
    run url to reduce categories: https://ltb.ge/api/view/smartShop/reduceCategories
     */
    public function reduceCategories($params = []){
        $categoryAttrId = 502;
        $currentCategories = DB::select("select service_id, hierarchy_hash from shop_attribute where attribute = 502");
        $currentCategorieIds = array_values( array_unique( array_column($currentCategories, 'service_id') ) );
        $currentCategorieHashes = array_values( array_unique( array_column($currentCategories, 'hierarchy_hash') ) );
        $currentCategorieIds[] = 0;

//        p($currentCategorieIds);

        $ltbReqs = new LtbRequests();
        $log = $ltbReqs->getCatalogFromLocalStorage();

        if(!isset($log['data']))return false;

        $newCategorieIds = array_values( array_unique( array_column($log['data'], 'CategoryID') ));
        $newCategorieIds[] = 0;

//        p($log['data'][0]);
        $tmp = [];
        $tmp2 = [];
        foreach ($log['data'] as $v){
            $hash_1 = md5(trim($v['First_Hierarchy'])) . '_';
            $hash_2 = md5(trim($v['First_Hierarchy'])) . '_'.md5(trim($v['Second_Hierarchy'])).'_';
            $hash_3 = md5(trim($v['First_Hierarchy'])) . '_'.md5(trim($v['Second_Hierarchy'])).'_'.md5(trim($v['Category']));
            $tmp2[$hash_1] = trim($v['First_Hierarchy']);
            $tmp2[$hash_2] = trim($v['Second_Hierarchy']);
            $tmp2[$hash_3] = trim($v['Category']);

            $tmp[$v['CategoryID']] = ['first'=>$v['First_Hierarchy'],'sec'=>$v['Second_Hierarchy'], 'CategoryID'=>$v['CategoryID'], 'Category'=>$v['Category'], 'parent_hash'=>md5(trim($v['First_Hierarchy'])) . '_'.md5(trim($v['Second_Hierarchy'])).'_'];
        }

        $unusedCategories = array_values(array_diff($currentCategorieHashes, array_keys($tmp2), ));
//        p($currentCategorieHashes);
//        p(array_keys($tmp2));

        print "all cats: ".count($tmp2)." ; current cats ".count($currentCategorieHashes);
//        p($unusedCategories);

        if(count($unusedCategories)>0){
            print "delete unused categories: ".count($unusedCategories)." items";
            p($unusedCategories);
            $unusedCategoriesImplode = "'".implode("','", $unusedCategories )."'";
            DB::table('shop_attribute')->whereRaw("attribute = 502 and hierarchy_hash in ({$unusedCategoriesImplode})")->delete();
        }

    }

    //// import box counts
    public function importBoxCounts($params = []){
        if(!_cv($params, ['list'], 'ar') || empty(current($params['list'])) ){
            return response(['message'=>'Nothing updated. Products list not set'], 201);
        }

        foreach ($params['list'] as $k=>$v){
            if (empty($k))continue;
            if(!is_numeric($v))continue;

            DB::select("update shop_products set box_count = {$v} where sku='{$k}'");
        }

        return response(['message'=>'Products box count updated successfully'], 200);

    }

    //// reset all box counts
    public function resetBoxCounts($params = []){
        DB::select('update shop_products set box_count = 0');

        $res['message'] = 'Reset box counts successfully ';
        return response($res, 200);
    }


}
