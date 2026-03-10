<?php

namespace App\Http\Controllers\Admin\Shop\services;

use App;
use App\Http\Controllers\Admin\Shop\Payments\Payment;
use App\Models\Shop\AttributeModel;
use App\Models\Shop\OrderModel;
use App\Models\Shop\ProductsModel;
use Exception;
use App\Models\User\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Admin\User\UserController;


/// route: /api/view/services/buildy/{method-name}
/// request auth: basic auth: $username == 'web_user' && $password == 'Ltb@2022'
class Buildy extends App\Http\Controllers\Api\ApiController
{

    /// route: /api/view/services/buildy/getProducts
    public function getProducts($params = [])
    {

        $params['status'] = 'published';
        $params['qtyMore'] = 1;
        $params['limit'] = _cv($params, ['limit'], 'nn')?$params['limit']:200;
        $params['translate'] = requestLan();

        $params['sortField'] = $this->sortFieldsMaping(_cv($params, ['sortField']));

        $cacheKey = cacheKey(['getProducts_buildy_', $params]);
        $value = Cache::store('file')->get($cacheKey);
        if($value) return $value;

        $attrs = $this->getAllAttrs();
//        p($attrs);


        $productModel = new ProductsModel();

        $res = $productModel -> getList($params);

        $res = $this->prepareBuildyList($res['list'], $attrs);

        Cache::put($cacheKey, response()->json($res, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_GET_PRODUCTS', 200));

        return response()->json($res, 200, [], JSON_UNESCAPED_UNICODE);
    }


    private function sortFieldsMaping($sortField = ''){

        if($sortField=='title')return 'slug';
        if($sortField=='price')return 'price';
        if($sortField=='popularity')return 'views';

        return 'id';

    }

    private function prepareBuildyList($data = [], $allAttrs = []){

        $ret = [];
        $tmp = [];
        foreach ($data as $k=>$v){
            $tmp = [];
//            p($v['attributes']);
            $tmp['name'] = $v['title'];
            $tmp['sku'] = $v['sku'];
            $tmp['image'] = [];
            $tmp['category'] = $v['sku'];
            $tmp['description'] = $v['description'];
            $tmp['price'] = $v['calcPrice'];
            $tmp['old_price'] = $v['price'];
            $tmp['quantity'] = $v['qty'];
            $tmp['attributes'] = [];
            $tmp['weight'] = 0;

            if(_cv($v, 'images.0')){
                $tmp['image'] = array_column($v['images'], 'url');
            }

            foreach ($v['attributes'] as $kk=>$vv){
                foreach ($vv as $kkk=>$vvv){
                    if(!isset($allAttrs[$kk]['data'][$vvv]))continue;
                    $tmp['attributes'][$allAttrs[$kk]['title']][] = $allAttrs[$kk]['data'][$vvv];
                }

                if(isset($tmp['attributes'][$allAttrs[$kk]['title']]) && count($tmp['attributes'][$allAttrs[$kk]['title']]) == 1)$tmp['attributes'][$allAttrs[$kk]['title']] = $tmp['attributes'][$allAttrs[$kk]['title']][0];

            }

            if(isset($tmp['attributes']['category'])){
                $tmp['category'] = $tmp['attributes']['category'];
                unset($tmp['attributes']['category']);
            }

            if(isset($tmp['attributes']['წონა'])){
                $tmp['weight'] = $tmp['attributes']['წონა'];
            }


            $ret[] = $tmp;
        }

        return $ret;

    }

       private function getAllAttrs(){
           $cacheKey = cacheKey(['getAllAttrs_buildy']);
           $cacheData = Cache::store('file')->get($cacheKey);
           if($cacheData) return $cacheData;


           $optionsModel = new App\Models\Admin\OptionsModel();
           $attrModel = new AttributeModel();
           $categoryAttributeTypes = $optionsModel->getListBy(['content_group'=>'shop_attribute_type', 'ssreturn'=>'id']);

           $attrTypes = [];
           foreach ($categoryAttributeTypes as $v){
               $attrTypes[$v['id']]['title'] = _cv($v, 'title_ge');
               $attrTypes[$v['id']]['data'] = $this->getAttrsSimpleList(['attribute'=>$v['id'], 'translate'=>'ge', 'limit'=>1000]);
           }


           Cache::put($cacheKey, $attrTypes, env('CACHE_MONTH', 200));
           return $attrTypes;


       }


    public function getAttrsSimpleList($params = []){

        $cacheKey = cacheKey(['getAttrsSimpleList_buildy',$params]);
        $cacheData = Cache::store('file')->get($cacheKey);
        if($cacheData) return $cacheData;



        $attrModel = new AttributeModel();

        $tmp = $attrModel->getList($params);
        if(!$tmp)return [];

        $ret = array_column($tmp['list'], 'title','id');

        Cache::put($cacheKey, $ret, env('CACHE_MONTH', 200));


        return $ret;


    }



}
