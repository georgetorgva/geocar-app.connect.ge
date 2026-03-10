<?php

namespace App\Http\Controllers\Admin\Shop\services;

use App\Models\Admin\MetaModel;
use App\Models\Media\MediaModel;
use App\Models\Shop\LocationsModel;
use App\Models\Shop\WalletsModel;
use Exception;
use App;
use Illuminate\Http\Request;
use App\Models\Silk\ChanelsModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Ramsey\Uuid\Lazy\toString;

class LtbRequests extends App\Http\Controllers\Api\ApiController
{

    /// add this attributes to stock regular fields
    public $noneAttributes = [
        'property_მომწოდებლის_კოდი' => ['key' => 'supplier_code'],
        'property_რაოდენობა_ყუთში' => ['key' => 'box_count'],
    ];

    private function _get($com, $body = [])
    {
        $url = env('LTB_URL', 'localhost') . $com;
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $credentials = base64_encode(env('LTB_USER', 'user') . ':' . env('LTB_PASS', 'pass'));

        $sendParams = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Basic {$credentials}",
            ]
        ];
//p($sendParams);
        if (!empty($body)) {
            $sendParams['body'] = $body;
            if (is_array($body))
                $sendParams['body'] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        try {
//            p($url);
//            p($sendParams);
            $response = $client->get($url, $sendParams);
//            p($response->getBody()->getContents());
            return _psqlCell($response->getBody()->getContents());
        }catch (Exception $e){
//            p($e->getMessage());
            return ['error'=>"request error on {$url}", 'response'=>$e->getMessage()];
        }


        //        p($response);

    }

    private function _post($com, $body)
    {
//print json_encode($body, JSON_UNESCAPED_UNICODE);
        $url = env('LTB_URL', 'localhost') . $com;
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $credentials = base64_encode(env('LTB_USER', 'user') . ':' . env('LTB_PASS', 'pass'));

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Basic {$credentials}",

                ],
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Exception $e){
            return ['error'=>"request error on {$url}", 'response'=>$e->getMessage()];
        }
        //print_r($response->getBody()->getContents());
        return _psqlCell($response->getBody()->getContents());
    }

    public function getCatalogFromSevice()
    {

        $products = $this->_get('GetProducts');

        // Delete Non Exist Products
        $nomenclatureIds = array_column($products, 'NomenclatureID');
        DB::table('shop_products')->whereNotIn('sku', $nomenclatureIds)->update([
            'status' => 'deleted'
        ]);

        $this->insertServiceLog(['url' => 'GetProducts', 'log_name' => 'GetProducts', 'log_data' => $products]);

        return true;
    }

    public function getComponentsFromService()
    {
        $products = $this->_get('GetComponentry');
        $this->insertServiceLog(['url' => 'GetComponentry', 'log_name' => 'GetComponentry', 'log_data' => $products]);

        return $products;
    }

    public function updateComponentsDb()
    {
        $res = \DB::table('shop_service_log')->select('*')->where('log_name', 'GetComponentry')->orderBy('id', 'desc')->limit(1)->get();
        if(!isset($res[0]))return false;
        $data = _psqlCell($res[0]->log_data);
        if(!is_array($data))return false;

        $res = \DB::table('shop_products')->select(['id', 'sku'])->get()->toArray();
        $skuId = array_column($res, 'id', 'sku');

        $metaModel = new MetaModel('shop_products_meta');



        $skus = [];
        $metaData = [];
        $updateCount = 0;
        foreach ($data as $k=>$v){
//            if($v['NomenclatureID'] == '000034452') p($v);


            if(!isset($skuId[$v['NomenclatureID']]) || !isset($v['Component']) || !is_array($v['Component']))continue;
            $skus[] = $v['NomenclatureID'];

            foreach ($v['Component'] as $kk=>$vv){
                if(!isset($vv['მაკომპლექტებლისID']) || !isset($skuId[$vv['მაკომპლექტებლისID']]))continue;
                $v['Component'][$kk]['id'] = $skuId[$vv['მაკომპლექტებლისID']];
            }

            $metaData['componentry'] = $v['Component'];
            $metaModel -> upd(['meta' => ['componentry' => $v['Component']], 'data_id' => $skuId[$v['NomenclatureID']], 'table' => 'shop_products_meta']);
            $updateCount++;

            if($v['NomenclatureID'] == '000034452'){

//                p(['meta' => ['componentry' => $v['Component']], 'data_id' => $skuId[$v['NomenclatureID']]]);
            }
//            p($v);
//break;
        }




//        p($result);

        return "updated componentries for {$updateCount} products";
    }


    public function getPointsFromSevice($personalNumber)
    {
        $points = $this->_get('GetPoints/?IDNumber=' . $personalNumber . '');
        $this->insertServiceLog(['url' => 'GetPoints', 'log_name' => 'GetPoints', 'log_data' => $points]);

        return $points;
    }

    public function getContragentsFromSevice($personalNumber)
    {
        $contragents = $this->_get('GetContragents/?IDNumber=' . $personalNumber . '');
        $this->insertServiceLog(['url' => 'GetContragents', 'log_name' => 'GetContragents', 'log_data' => $contragents]);

        return $contragents[0] ?? null;
    }

    public function getMasterFromSevice($personalNumber)
    {
        $masters = $this->_get('GetMastersRating');

        $this->insertServiceLog(['url' => 'GetMastersRating', 'log_name' => 'GetMastersRating', 'log_data' => $masters]);

        foreach ($masters as $master) {
            if ($master['IDNumber'] == $personalNumber) {
                $data = $master;
            }
        }

        return $data ?? null;
    }

    public function chargePoints($params = [])
    {
        $res = $this->_get("GetContragents/?IDNumber={$params['userPid']}&points={$params['points']}");


        return $res;
    }

    // ltb api run
    public function updateCatalogDb()
    {
        $productsLog = $this->getCatalogFromLocalStorage();

        $sliceFrom = _cv($productsLog, ['info', 'status'], 'nn') ? $productsLog['info']['status'] : 0;

        if (!_cv($productsLog, ['data'], 'ar')) return false;

        $limit = 50;
        $products = $productsLog['data'];
        if (!$products) return false;
        $count = count($products);

        $products = array_slice($products, $sliceFrom, $limit);
//p($products);
        foreach ($products as $k => $product) {
//            print _cv($product, ['ფასი']);
//            print _cv($product['ბიჯი']);
//            print _cv($product['ფასი']);
//            print "{$product['ბიჯი']}--{$product['ფასი']} \n ";
//            if(!isset($product['ბიჯი']) || !floatval($product['ბიჯი']))continue;


            $attributes = $this->getResponseAttributes($product);

            $productRelateAttributes = $this->updateShopAttributes($attributes);

            $categoryData = $this->getResponseCategoryData($product);

            $productRelateCategories = $this->updateShopCategories($categoryData, $productRelateAttributes);

            $productRelateAttributes[$productRelateCategories['attributeTypeId']] = $productRelateCategories['attributes'];

            $stockId = $this->updateStockData($product);

            $productId = $this->updateProductData($product, $stockId, $productRelateAttributes);

//            p($product); break;
        }

        $newSliceFrom = (($sliceFrom + $limit) >= $count) ? $count : ($sliceFrom + $limit);

        \DB::table('shop_service_log')->where('id', $productsLog['info']['id'])->update(['status' => $newSliceFrom]);

        return ['updatedFrom' => $sliceFrom, 'updatedTo' => $newSliceFrom, 'count' => $count, 'updated' => $productsLog['info']['updated_at'], 'inserted' => $productsLog['info']['created_at']];
    }

    // api get methods
    public function getResponseAttributes($response)
    {
        $attributes = [];

        foreach ($response as $key => $value) {
            if (isset($this->noneAttributes[$key]))
                continue;

            if (strpos($key, 'property_') === 0) {
                $attrParts = explode('property_', $key);

                $attributeKey = $attrParts[1] ?? '';

                if (isset($response[$attributeKey])) {
                    $attributes[] = ['attrStatus' => $value == 1 ? 1 : 0, 'attribute' => $attributeKey, 'value' => $response[$attributeKey]];
                }
            }
        }

        return $attributes;
    }

    /// update shop attributes from service response data
    /// updates all atributes exept 'category'
    public function updateShopAttributes($responseAttributes = [])
    {
        $attributeModel = new App\Models\Shop\AttributeModel();
        $productRelatedAttributes = [];
//p($responseAttributes);
        foreach ($responseAttributes as $k => $v) {
            //            print '--------------------';
//            p($v);
            $v['value'] = trim(strip_tags($v['value']));
            $transliteratedAttributeValue = transliterate($v['value']);
            if ($transliteratedAttributeValue == '')
                continue;

            $upd = [];
            /// get attribute type id ; if not exists create new one
            $upd['attribute'] = $this->getAttributeType($v);

            /// if attribute doesnot have relation to product do nothing
            if (!_cv($v, 'attrStatus'))
                continue;

            /// get attribute from db
            $attribute = $attributeModel->getOne(['slug' => $transliteratedAttributeValue, 'attribute' => $upd['attribute']]);
            //            p($attribute);
//            p(['slug'=>$transliteratedAttributeValue, 'attribute'=>$upd['attribute']]);

            /// if attribute already exists, prepare attributes array and do nothing
            if (_cv($attribute, 'id', 'nn')) {
                $upd['id'] = $attribute['id'];
                $productRelatedAttributes[$upd['attribute']] = [$attribute['id']];


                if(strpos($v['value'], ',')!==false){
                    $v['value'] = str_replace(',', '.', $v['value']);
                }else{

                    continue;
                }

            }


            $v['value'] = str_replace(',', '.', $v['value']);
            /// create new attribute and update relation array
            $upd['pid'] = 0;
            $upd['ge']['title'] = $v['value'];
            $upd['en']['title'] = $v['value'];
            $upd['ru']['title'] = $v['value'];
//            $upd['title_ge'] = $v['value'];
//            $upd['title_en'] = $v['value'];
            $upd['slug'] = $transliteratedAttributeValue;
            $upd['count'] = 0;
            $upd['sort'] = 0;
            $upd['conf'] = '';
            $upd['id'] = $attributeModel->upd($upd);

//                        p($upd);
            $productRelatedAttributes[$upd['attribute']] = [$upd['id']];
        }

        return $productRelatedAttributes;
    }

    /// get attribute type or create one
    private function getAttributeType($attributeData = [])
    {
        $strToShuffle = 'ab3cd0efgh6ijkl1mo4pqr2st8uv7wx9yz';

        $transliteratedAttributeName = transliterate($attributeData['attribute']);

        $option = \DB::table('options')->select(['id'])->where('key', $transliteratedAttributeName)->where('content_group', 'shop_attribute_type')->first();

        $title = str_replace('_', ' ', strip_tags($attributeData['attribute']));

        $optionId = isset($option->id) ? $option->id : \DB::table('options')->insertGetId([
            'key' => $transliteratedAttributeName,
            'content_group' => 'shop_attribute_type',
            'data_type' => 'json',
            'value' => '{"fields":[{"ui":"' . str_shuffle($strToShuffle) . '","translate":1,"required":1,"name":"title","type":"text"}],"title_ge":"' . $title . '", "title_en":"' . $title . '"}'
        ]);

        return $optionId;

    }

    /// extract categories data from product object and prepare for db update
    public function getResponseCategoryData($response)
    {

        $categoryData = [];

        if (!isset($response['Category']) || !isset($response['CategoryID']))
            return false;

        $hierarchyHash = '';
        if (_cv($response, ['First_Hierarchy'])) {
            $hierarchyHash = md5(trim($response['First_Hierarchy'])) . '_';
            $categoryData[] = ['title' => trim($response['First_Hierarchy']), 'service_id' => '', 'hierarchy_hash' => $hierarchyHash];
        }
        if (_cv($response, ['Second_Hierarchy'])) {
            $hierarchyHash .= md5(trim($response['Second_Hierarchy'])) . '_';
            $categoryData[] = ['title' => trim($response['Second_Hierarchy']), 'service_id' => '', 'hierarchy_hash' => $hierarchyHash];
        }

        $hierarchyHash .= md5(trim($response['Category']));
        $categoryData[] = ['title' => trim($response['Category']), 'service_id' => trim($response['CategoryID']), 'hierarchy_hash' => $hierarchyHash];

        //        krsort($categoryData);
//p($categoryData);
        return $categoryData;

    }

    /// update shop categories
    public function updateShopCategories($productRelatedCategories = [], $productRelatedAttributes = [])
    {
        //        p($productRelatedCategories);
        $attributeModel = new App\Models\Shop\AttributeModel();

        $upd['attribute'] = $this->getAttributeType(['attribute' => 'category']);

        $upd['pid'] = 0;

        $relatedCategories = [];

        foreach ($productRelatedCategories as $k => $v) {

            $upd['id'] = '';
            $upd['hierarchy_hash'] = $v['hierarchy_hash'];
            $upd['slug'] = transliterate($v['title']);

            /// get attribute from db
            $attribute = $attributeModel->getOne(['hierarchy_hash' => $v['hierarchy_hash'], 'attribute' => $upd['attribute'], 'pid' => $upd['pid']]);
//            p($attribute);
            /// if attribute already exists, prepare attributes array and do nothing
            if (_cv($attribute, 'id', 'nn')) {
                $upd['id'] = $attribute['id'];
                //                $relatedCategories[] = $attribute['id'];
//                p($upd);
//                $upd['pid'] = $attribute['id'];
//                continue;
            }

            if (_cv($v, 'service_id')) {
                if (!isset($attribute['relatedattributes']) || !is_array($attribute['relatedattributes']))
                    $attribute['relatedattributes'] = [];
                $upd['xx']['relatedattributes'] = array_unique(array_merge($attribute['relatedattributes'], array_keys($productRelatedAttributes)));

//                p($upd['relatedattributes']);

            }

            /// create new attribute and update relation array
            $upd['ge']['title'] = $v['title'];
            $upd['en']['title'] = $v['title'];
            $upd['ru']['title'] = $v['title'];

            $upd['service_id'] = _cv($v, 'service_id');
            $upd['count'] = 0;
            $upd['sort'] = 0;
            $upd['conf'] = '';

//            p($upd);
            $upd['id'] = $attributeModel->upd($upd);

            //            p($upd);
            $relatedCategories[] = $upd['id'];
            $upd['pid'] = $upd['id'];

        }


        //p($relatedCategories);
        return ['attributeTypeId' => $upd['attribute'], 'attributes' => $relatedCategories];
    }

    public function updateStockData($response)
    {

        if (!_cv($response, ['Nomenclature']) || !_cv($response, ['NomenclatureID']))
            return false;

        //        p($response['ფასი']);

        $upd['price'] = _cv($response, 'ფასი'); /// დასაზუსტებელია ფასის ცვლადი. ეხლა არ მოდის
        $upd['price_old'] = 0; /// დასაზუსტებელია ფასის ცვლადი. ეხლა არ მოდის
        $upd['title'] = trim(addslashes($response['Nomenclature']));
        $upd['slug'] = transliterate(trim($response['Nomenclature']));
        $upd['sku'] = (trim($response['NomenclatureID']));
        //p($upd);

        foreach ($this->noneAttributes as $k => $v) {
            if (!isset($response[$k]))
                continue;
            $valueKey = str_replace('property_', '', $k);
            if (!isset($response[$valueKey]) || empty($response[$valueKey]))
                continue;

            $upd[$v['key']] = $response[$valueKey];
        }

        $stock = \DB::table('shop_stock')->select(['id'])->where('sku', $upd['sku'])->first();
        //p($stock);
        if (isset($stock->id))
            $upd['id'] = $stock->id;
        $stockModel = new App\Models\Shop\StockModel();
        $res = $stockModel->updItem($upd);
        //        p($res);
        return $res;

    }

    public function updateProductData($response = [], $stockId = '', $relatedAttributes = [])
    {

        if (!is_numeric($stockId))
            return false;

        $upd['attributes'] = $relatedAttributes;
        $upd['productStockList'] = [$stockId => 1];
        $upd['qty'] = isset($response['ნაშთი']) ? $response['ნაშთი'] : 0;
        $upd['sku'] = (trim($response['NomenclatureID']));
//        $upd['price'] = isset($response['ფასი']) ? $response['ფასი'] : 0;
        $upd['price'] = isset($response['ფასი']) && floatval($response['ფასი']) ? floatval($response['ფასი']) : 0;
        $upd['step'] = isset($response['ბიჯი']) && floatval($response['ბიჯი']) ? floatval($response['ბიჯი']) : 1;
//        $upd['box_count'] = isset($response['რაოდენობა_ყუთში']) ? $response['რაოდენობა_ყუთში'] : 1;
        $upd['dimension'] = isset($response['ზომის_ერთეული']) ? $response['ზომის_ერთეული'] : '';
        $upd['dimension_id'] = isset($response['ზომის_ერთეულის_ID']) ? $response['ზომის_ერთეულის_ID'] : '';
        $upd['old_price'] = 0;
        $upd['slug'] = transliterate(trim($response['Nomenclature']));
        $upd['title_en'] = trim(addslashes($response['Nomenclature']));
        $upd['title_ge'] = trim(addslashes($response['Nomenclature']));
        $upd['status'] = 'published';

        if (_cv($response, 'პასიური') == true) {
            $upd['status'] = 'deleted';
        }

        $upd['conf'] = [];
        if (_cv($response, ['იყიდება_ქულით']))
            $upd['conf'][] = 'sellWithPoints';
        if (_cv($response, ['არ_ვრცელდება_ფასდაკლება']))
            $upd['conf'][] = 'disableDiscount';

        $upd['group_id'] = $this->getGroupId($response);

        $productsModel = new App\Models\Shop\ProductsModel();
        $product = $productsModel->getOne(['sku' => $upd['sku']]);

        if (_cv($product, 'id'))
            $upd['id'] = $product['id'];

//                p($upd);
        $result = $productsModel->upd($upd);
        //        p($result);

        return $result;

    }

    public function insertServiceLog($params = [])
    {
        $params['created_at'] = date('Y-m-d H:i:s');
        $params['updated_at'] = date('Y-m-d H:i:s');
        $params['status'] = 'inserted';

        if (isset($params['log_data']) && is_array($params['log_data'])) {
            $params['log_data'] = json_encode(($params['log_data']), JSON_UNESCAPED_UNICODE);
        }

        if (_cv($params, 'log_name') == 'GetProducts') {

            $filename = config('filesystems.disks.public.root')."catalogLog.txt";
            file_put_contents($filename, $params['log_data']);
            $params['log_data'] = $filename;
        }

        $user = auth()->user();
        $params['user_id'] = $user->id ?? null;

        $id = \DB::table('shop_service_log')->insertGetId($params);
        return $id;
    }

    public function getCatalogFromLocalStorage()
    {
        $serviceLogReq = DB::table('shop_service_log')->select("*")->orderBy('id', 'desc')->first();
//        p($serviceLogReq);
        //        $serviceLogReq = _toArray($serviceLogReq);
        $serviceLogReq = json_decode(json_encode($serviceLogReq, JSON_UNESCAPED_UNICODE), 1);

        if (!_cv($serviceLogReq, ['log_data']) || !is_file($serviceLogReq['log_data'])) return ['error' => 'log data not found'];


        $catalogFromLocalStorage = file_get_contents($serviceLogReq['log_data']);
        $catalogFromLocalStorage = _psqlCell($catalogFromLocalStorage);

        //        p(current($catalogFromLocalStorage));

        return ['data' => $catalogFromLocalStorage, 'info' => $serviceLogReq];

    }

    /// get stock quantity info for exact products
    public function getStockInfo($params = [])
    {

        $products = "";

        foreach ($params['productIds'] as $k => $v) {
            $products .= '{ "NomenclatureID": "' . str_pad($v, 9, 0, STR_PAD_LEFT) . '" },';
            //            $products .= ["NomenclatureID" => str_pad($v, 9,0, STR_PAD_LEFT)];
        }

        if (empty($products))
            return ['error' => "products not set"];

        $str = '{"Stocks": [ ' . $products . ' ]}';

        //        $str = '{"Stocks": [ { "NomenclatureID": "000049636" } ]}';
        $stockData = $this->_get('GetProductsInStock', $str);

        if (!is_array($stockData))
            return ['error' => "can`t get stock data"];

        $res = [];
        foreach ($stockData as $k => $v) {
            if (!_cv($v, ['NomenclatureID']))
                continue;
            $res[$v['NomenclatureID']] = ['sku' => (trim($v['NomenclatureID'])), 'qty' => _cv($v, 'ნაშთი', 'num')];
        }

        $productModel = new App\Models\Shop\ProductsModel();
        $productModel->updateStockQty(['stockData' => $res]);

        //        p($stockData);

        return $res;

    }

    public function getCartInfo($params = [])
    {

        //        $req = json_encode($params, JSON_UNESCAPED_UNICODE );
        $req = $params;

        if (empty($params))
            return ['error' => "products not set"];
        //        $req['IDNumber'] = '12345678910';
//p($req);

        $cartInfo = $this->_get('GetPricesForOrder', $req);

        $tmp = '{
  "Products": [
    {
      "სტრიქონის_ნომერი": 1,
      "ნომენკლატურის_ID": "000027066",
      "ზომის_ერთეულის_ID": "000013459",
      "რაოდენობა": 4700,
      "ფასი": 2,
      "თანხა": 7520,
      "ავტომატური_ფასდაკლების_პროცენტი": 20,
      "ავტომატური_ფასდაკლების_თანხა": 1880,
      "ხელოვნური_ფასდაკლების_პროცენტი": 0,
      "ხელოვნური_ფასდაკლების_თანხა": 0
    },
    {
      "სტრიქონის_ნომერი": 1,
      "ნომენკლატურის_ID": "000027067",
      "ზომის_ერთეულის_ID": "000013459",
      "რაოდენობა": 4700,
      "ფასი": 2,
      "თანხა": 7520,
      "ავტომატური_ფასდაკლების_პროცენტი": 20,
      "ავტომატური_ფასდაკლების_თანხა": 1880,
      "ხელოვნური_ფასდაკლების_პროცენტი": 0,
      "ხელოვნური_ფასდაკლების_თანხა": 0
    }

  ]
}
';
        //        $cartInfo = json_decode($tmp, 1);


        if (!_cv($cartInfo, 'Products') && _cv($cartInfo, 'ტრანზაქციის_შეცდომა'))
            return ['error' => $cartInfo['ტრანზაქციის_შეცდომა']];
        if (!_cv($cartInfo, 'Products'))
            return ['error' => "can`t get cart prices from server"];

        //        p($cartInfo);

        return $cartInfo;

    }

    public function getGroupId($params = [])
    {
        if (!isset($params['მსგავსი_პროდუქცია']) || empty($params['მსგავსი_პროდუქცია']))
            return false;
        $slug = $output = Str::slug($params['მსგავსი_პროდუქცია']);

        $groupData = DB::table('shop_product_group')->select('id')->where('slug', $slug)->first();

        if (!isset($groupData->id))
            DB::table('shop_product_group')->insert(['slug' => $slug]);

        $groupData = DB::table('shop_product_group')->select('id')->where('slug', $slug)->first();

        if (isset($groupData->id))
            return $groupData->id;

        return '';

    }

    public function getPointTransactions($params = [])
    {
        if (!_cv($params, ['idNumber']))
            return ['error' => 'id number not set to get points transactions'];
        $startDate = _cv($params, ['from']) ? $params['from'] : date("Y-m-d H:i:s", strtotime("-1 Year"));
        $endDate = _cv($params, ['to']) ? $params['to'] : date("Y-m-d H:i:s");

        $points = $this->_get("GetTransactions/?IDNumber={$params['idNumber']}&StartDate={$startDate}&StartDate={$endDate}");

        return $points;

    }

    /// box products data update
    public function getWholesale($params = [])
    {

        $wholesale = $this->_get("/GetBoxProducts");
        DB::table('shop_products')->update(['box_sell_status' => 0]);

        foreach ($wholesale as $k => $v) {
            $skuList[$v['NomenclatureID']]['boxCount'] = (int) _cv($v, 'BoxUnits.0.რაოდენობა_ყუთში');
            $skuList[$v['NomenclatureID']]['sku'] = (int) _cv($v, 'NomenclatureID');
            $skuList[$v['NomenclatureID']]['discountPercent'] = _cv($v, 'ყუთზე_ფასდაკლების_პროცენტი');
        }

        $skuListIds = array_column($skuList, 'sku');
        $products = DB::table('shop_products')->whereIn('sku', $skuListIds)->get();

        foreach ($products as $k => $v) {
            $discountPercent = isset($skuList[$v->sku]['discountPercent']) && $skuList[$v->sku]['discountPercent'] !== false ? $skuList[$v->sku]['discountPercent'] : 0;
            $boxCount = $skuList[$v->sku]['boxCount'] ?? 0;
            DB::table('shop_products')->where('id', $v->id)->update([
                'box_count' => $boxCount,
                'box_price' => DB::raw("(price * {$boxCount}) * (100-{$discountPercent})/100"),
                'box_sell_status' => 1
            ]);
        }

        return '';

    }

    public function sendOrder($params = [], $errorMsg = 'can`t create order at service /MakeOrder')
    {
        /**
        {
        "IDNumber": "09876543210",
        "აქცია1პლიუს1":false,
        "პრომოკოდი":    "",
        "ლოიალური_ბარათით":false,
        "უცხო_ქვეყნის_მოქალაქე": false,
        "Products": [
        {
        "სტრიქონის_ნომერი": 1,
        "ნომენკლატურის_ID": "000014267",
        "ზომის_ერთეულის_ID":  "000013459",
        "საჩუქრის_ID":  "",
        "რაოდენობა": 4700,
        "ქულით": false,
        "ფასი":  2,
        "თანხა": 7520,
        "ავტომატური_ფასდაკლების_პროცენტი": 20.00,
        "ავტომატური_ფასდაკლების_თანხა":  1880,
        "ხელოვნური_ფასდაკლების_პროცენტი": 0,
        "ხელოვნური_ფასდაკლების_თანხა": 0
        },
        }
        */

        if (!_cv($params, 'cart', 'ar')) return ['error' => 'cart is empty'];
        if (!_cv($params, 'cartMeta', 'ar')) return ['error' => 'cart is empty'];

        $locationModel = new LocationsModel();
        $location = $locationModel->getOne([ 'id'=>_cv($params, 'cartMeta.address.cityId'), 'pluck'=>'name_ge' ]);

        $req = [
//            "IDNumber" => strval(1234567890), //// for testing
            "IDNumber" => (string) _cv($params, 'cartMeta.userInfo.p_id'),
            "PaymentProvider" => (string) _cv($params, 'cartMeta.paymentProvider'),
            "TransportServiceID"=> (string)_cv($params, 'cartMeta.shipping.info.TransportServiceID'),
            "TransportPrice"=> _cv($params, 'cartMeta.shipping.info.Price'),
            "აქცია1პლიუს1" => false, /// _cv($params, 'cartMeta.1plus1'),
            "პრომოკოდი" => (string)_cv($params, 'cartMeta.promoCode'),
            "ლოიალური_ბარათით" => _cv($params, 'cartMeta.useLoyalty'),
            "უცხო_ქვეყნის_მოქალაქე" => _cv($params, 'cartMeta.userInfo.legalInfo'),
//            "address2" => 'Address Type: '._cv($params, 'cartMeta.address.addressType').',
//                Phone: '._cv($params, 'cartMeta.address.phone').',
//                City: '._cv($params, 'cartMeta.address.city').',
//                District: '._cv($params, 'cartMeta.address.district').',
//                Address: '._cv($params, 'cartMeta.address.address').',
//                Floor:'._cv($params, 'cartMeta.address.floor'),
            "address" => $location.' '._cv($params, 'cartMeta.address.district').', '._cv($params, 'cartMeta.address.address').', '._cv($params, 'cartMeta.address.floor'),
            "contact" => 'Email: '._cv($params, 'cartMeta.userInfo.email').', Phone: '._cv($params, 'cartMeta.userInfo.phone').', ID: '._cv($params, 'cartMeta.userInfo.p_id'),
            'Products' => $this->generateProductsListForOrder($params['cart'])
        ];
//        p($req); exit;
        /*** /
        $i = 1;
        foreach ($params['cart'] as $k => $v) {
            //            p($v);
            $req['Products'][] = [
                "სტრიქონის_ნომერი" => $i++,
                "ნომენკლატურის_ID" => sku($v['sku']),
                "ზომის_ერთეულის_ID" => sku($v['dimension_id']),
                "საჩუქრის_ID" => "",
                "რაოდენობა" => $v['quantity'],
                "ქულით" => false,
                "ფასი" => $v['price'],
                "თანხა" => $v['subtotal'],
                "ავტომატური_ფასდაკლების_პროცენტი" => _cv($v, 'discountPercent', 'nn')?$v['discountPercent']:0,
                "ავტომატური_ფასდაკლების_თანხა" => _cv($v, 'discountAmount', 'nn')?$v['discountAmount']:0,
                "ხელოვნური_ფასდაკლების_პროცენტი" => _cv($v, 'discountPercent', 'nn')?$v['discountPercent']:0,
                "ხელოვნური_ფასდაკლების_თანხა" => _cv($v, 'discountAmount', 'nn')?$v['discountAmount']:0,
//                "ავტომატური_ფასდაკლების_პროცენტი" => _cv($v, 'prices.ავტომატური_ფასდაკლების_პროცენტი', 'nn'),
//                "ავტომატური_ფასდაკლების_თანხა" => _cv($v, 'prices.ავტომატური_ფასდაკლების_თანხა', 'nn'),
//                "ხელოვნური_ფასდაკლების_პროცენტი" => _cv($v, 'prices.ხელოვნური_ფასდაკლების_პროცენტი', 'nn'),
//                "ხელოვნური_ფასდაკლების_თანხა" => _cv($v, 'prices.ხელოვნური_ფასდაკლების_თანხა', 'nn')
            ];
        }
        /***/
//        p($req);
//        print json_encode($req, JSON_UNESCAPED_UNICODE );
//return false;
        $res = $this->_post("/MakeOrder", $req);
//p($res);
        if(_cv($res, 'Products.0.დანაკლისი')){
            $errorMsg = "დანაკლისი {$res['Products'][0]['დანაკლისი']}";
        }elseif(_cv($res, 'ტრანზაქციის_შეცდომა')){
            $errorMsg = "ტრანზაქციის_შეცდომა: {$res['ტრანზაქციის_შეცდომა']}";
        }else if(_cv($res, 'error') && _cv($res, 'response')){
            $errorMsg = "LTB 1C {$res['response']}";
        }
//                p($res['response']);
//        print '----------';
        if (!isset($res['შეკვეთის_ნომერი'])) return ['error' => $errorMsg, 'log'=>$res, 'req'=>$req];
        return $res;

    }

    /// generate products list from cart to send final order
    /// list structure meets 1C service requirements
    public function generateProductsListForOrder($cart = []){

        $ret = [];
        $i = 1;
        foreach ($cart as $k=>$v) {

            /// regular products
            $tmp = [
                'სტრიქონის_ნომერი' =>$i++,
                'ნომენკლატურის_ID' => $v['sku'],
                'ზომის_ერთეულის_ID' => $v['dimension_id'],
                'საჩუქრის_ID' => _cv($v, 'gift.0.sku')?$v['gift'][0]['sku']:'',
                'რაოდენობა' => $v['quantity'] + $v['sellWithPoints'],
                "ქულით" => (_cv($v, ['pointsCalcPrice'],'nn')>0)?$v['pointsCalcPrice']:false,
                "ფასი" => $v['calcPrice'],
                'თანხა' => $v['subtotal'],
                "ავტომატური_ფასდაკლების_პროცენტი" => 0, //_cv($v, 'discountPercent', 'nn')?$v['discountPercent']:0,
                "ავტომატური_ფასდაკლების_თანხა" => 0, //_cv($v, 'discountAmount', 'nn')?$v['discountAmount']:0,
                "ხელოვნური_ფასდაკლების_პროცენტი" => 0, //_cv($v, 'discountPercent', 'nn')?$v['discountPercent']:0,
                "ხელოვნური_ფასდაკლების_თანხა" => 0, //_cv($v, 'discountAmount', 'nn')?$v['discountAmount']:0,
            ];

            if (_cv($v, 'gift.0.sku')) {
                $tmp['საჩუქრის_ID'] = $v['gift'][0]['sku'];
            }
            $ret[] = $tmp;

            /// gifted products
            if (_cv($v, 'gift', 'ar')) {
                foreach ($v['gift'] as $kk => $vv) {
                    $ret[] = [
                        'სტრიქონის_ნომერი' =>$i++,
                        'ნომენკლატურის_ID' => $vv['sku'],
                        'ზომის_ერთეულის_ID' => $vv['dimension_id'],
                        'საჩუქრის_ID' => _cv($vv, 'gift.0.sku')?$vv['gift'][0]['sku']:'',
                        'რაოდენობა' => $vv['quantity'],
                        "ქულით" => false,
                        "ფასი" => 0,
                        'თანხა' => 0,
                        "ავტომატური_ფასდაკლების_პროცენტი" => 0, //_cv($vv, 'discountPercent', 'nn')?$vv['discountPercent']:0,
                        "ავტომატური_ფასდაკლების_თანხა" => 0, //_cv($vv, 'discountAmount', 'nn')?$vv['discountAmount']:0,
                        "ხელოვნური_ფასდაკლების_პროცენტი" => 0, //_cv($vv, 'discountPercent', 'nn')?$vv['discountPercent']:0,
                        "ხელოვნური_ფასდაკლების_თანხა" => 0, //_cv($vv, 'discountAmount', 'nn')?$vv['discountAmount']:0,
                    ];
                }
            }

            /// dependentDiscount products
            if (_cv($v, 'dependentDiscount', 'ar')) {
                foreach ($v['dependentDiscount'] as $kk => $vv) {
                    $ret[] = [
                        'სტრიქონის_ნომერი' =>$i++,
                        'ნომენკლატურის_ID' => $v['sku'],
                        'ზომის_ერთეულის_ID' => $v['dimension_id'],
                        'საჩუქრის_ID' => _cv($v, 'gift.0.sku')?$v['gift'][0]['sku']:'',
                        'რაოდენობა' => $v['quantity'],
                        "ქულით" => false,
                        "ფასი" => $v['calcPrice'],
                        'თანხა' => $v['subtotal'],
                        "ავტომატური_ფასდაკლების_პროცენტი" => 0, //_cv($v, 'discountPercent', 'nn')?$v['discountPercent']:0,
                        "ავტომატური_ფასდაკლების_თანხა" => 0, //_cv($v, 'discountAmount', 'nn')?$v['discountAmount']:0,
                        "ხელოვნური_ფასდაკლების_პროცენტი" => 0, //_cv($v, 'discountPercent', 'nn')?$v['discountPercent']:0,
                        "ხელოვნური_ფასდაკლების_თანხა" => 0, //_cv($v, 'discountAmount', 'nn')?$v['discountAmount']:0,
                    ];
                }
            }


        }
        return $ret;
    }


    public function getCatalog1Plus1($params = [])
    {

        $value = Cache::store('file')->get('Catalog1Plus1');
        if ($value)
            return $value;

        $res = $this->_get("/Catalog1Plus1");
        //        p($res);
        $ret = [];
        foreach ($res as $k => $v) {
            //            $ret[$v['ნომენკლატურის_ID']] = $v;
            $ret[intval($v['ნომენკლატურის_ID'])] = $v;
        }

        Cache::put('Catalog1Plus1', $ret, 3200);

        return $ret;

    }

    public function registerContragent($params = [])
    {

        if(!_cv($params, 'p_id'))return ['error' => 'ID number not set'];
//        p($params);
        $taxonomy = new App\Models\Admin\TaxonomyModel();

        $req = [];
        if(_cv($params, 'status')=='person'){

            $catIds = _cv($params, 'additional_info.person.memberStatus');
            $categories = $taxonomy->getOne(['slug'=>$catIds, 'returnCol'=>'title_ge', 'taxonomy'=>'user_statuses']);
    //p($categories);

    //        $req['web'] = _cv($params, 'additional_info.person');

            $req["Contragent"] = (string)_cv($params, 'additional_info.person.fullname');
            $req["IDNumber"] = (string)_cv($params, 'p_id');
            $req["კატეგორია"] = $categories;
            $req["სამართლებრივი_ფორმა"] = _cv($params, 'additional_info.person.legalName');
            $req["უცხო_ქვეყნის_მოქალაქე"] =_cv($params, 'additional_info.person.residentStatus.1');
            $req["ელ_ფოსტა"] = _cv($params, 'email');
            $req["მობილურის_ნომერი"] = _cv($params, 'phone');

        }elseif(_cv($params, 'status')=='company'){

            $req["Contragent"] = (string)_cv($params, 'additional_info.company.fullname');
            $req["IDNumber"] = (string)_cv($params, 'p_id');
            $req["კატეგორია"] = 'other';
            $req["სამართლებრივი_ფორმა"] = _cv($params, 'additional_info.company.legalName');
            $req["უცხო_ქვეყნის_მოქალაქე"] =_cv($params, 'additional_info.company.residentStatus.1');
            $req["ელ_ფოსტა"] = _cv($params, 'email');
            $req["მობილურის_ნომერი"] = _cv($params, 'phone');

        }elseif(_cv($params, 'status')=='master'){

            $req["Contragent"] = (string)_cv($params, 'additional_info.master.fullname');
            $req["IDNumber"] = (string)_cv($params, 'p_id');
            $req["კატეგორია"] = 'other';
            $req["სამართლებრივი_ფორმა"] = _cv($params, 'additional_info.master.legalName');
            $req["უცხო_ქვეყნის_მოქალაქე"] =_cv($params, 'additional_info.master.residentStatus.1');
            $req["ელ_ფოსტა"] = _cv($params, 'email');
            $req["მობილურის_ნომერი"] = _cv($params, 'phone');
        }

//        p($req);
        $res = $this->_post("/CreateClient", $req);

//        p($res);

        if (_cv($res, ['error'])) {
            return $res;
        }

        if (_cv($res, ['შეტყობინება'])) {
            return $res;
        }

        return ['error' => 'cant register new client'];

    }

    public function getShippingCost($params = [], $discounts=[])
    {

        $reqsModel = new LocationsModel();

        if(_cv($params, ['meta_info','address','cityId'], 'nn')){
            $res = $reqsModel->getOne([ 'id'=>$params['meta_info']['address']['cityId'] ]);
        }else if(_cv($params, ['meta_info','address','city'], 'nn')){
            $res = $reqsModel->getOne([ 'id'=>$params['meta_info']['address']['city'] ]);
        }else{
            return ['error'=>'location not set'];
        }



//        p($res);

        if(!_cv($res, ['name_ge'])){
            return ['error'=>'wrong location; please correct shipping address'];
        }


        $req = ['აქცია1პლიუს1'=>false, 'ქალაქი'=>$res['name_ge']];

        if(!_cv($params, ['cart_info'], 'ar'))return [];
        $req['Products'] = $this->generateProductsList($params);
//p($req);
//        foreach ($params['cart_info'] as $k=>$v){
//            $req['Products'][] = [
//                'ნომენკლატურის_ID'=>$v['sku'],
//                'რაოდენობა'=>$v['quantity'],
//                'ზომის_ერთეულის_ID'=>$v['dimension_id'],
//                'თანხა'=>$v['price'],
////                'საჩუქრის_ID'=>''
//            ];
//        }

        //p($req);
        $res = $this->_get("GetShippingCost", $req);
//p($res);
        if (_cv($res, ['error'])) {
            return ['error'=>$res['error'], 'response'=>_cv($res, ['response']), 'amount'=>11];
        }

        $shippingPrice = _cv($res, 'Price', 'nn')?$res['Price']:0;
        $discount = 0;

        foreach ($discounts as $k=>$v){
            if(_cv($v, 'DiscountName')=='contragentDiscountPercent' && _cv($v, 'discount_amount', 'nn') ){
                $discountAmount = $v['discount_amount'];
                $tmp = discountCalculator($shippingPrice, $discountAmount);
                $shippingPrice = $tmp['calcPrice'];
                $discount = $tmp['amount'];
            }
        }

        return [ 'amount'=>$shippingPrice, 'discount'=>$discount, 'info'=>$res ];

    }

    /// generate products list from cart to calculate shipping cost
    /// list structure meets 1C service requirements
    public function generateProductsList($cart = []){

        $ret = [];
        foreach ($cart['cart_info'] as $k=>$v) {

            /// regular products
            $tmp = [
                'ნომენკლატურის_ID' => $v['sku'],
                'რაოდენობა' => $v['quantity'],
                'ზომის_ერთეულის_ID' => $v['dimension_id'],
//                'თანხა' => $v['calcPrice'],
                'თანხა' => $v['calcPrice']*$v['quantity'],
            ];

            if (_cv($v, 'gift.0.sku')) {
                $tmp['საჩუქრის_ID'] = $v['gift'][0]['sku'];
            }
            $ret[] = $tmp;

            /// gifted products
            if (_cv($v, 'gift', 'ar')) {
                foreach ($v['gift'] as $kk => $vv) {
                    $ret[] = [
                        'ნომენკლატურის_ID' => $vv['sku'],
                        'რაოდენობა' => $vv['quantity'],
                        'ზომის_ერთეულის_ID' => $vv['dimension_id'],
                        'თანხა' => 0,
                    ];
                }
            }

            /// dependentDiscount products
            if (_cv($v, 'dependentDiscount', 'ar')) {
                foreach ($v['dependentDiscount'] as $kk => $vv) {
                    $ret[] = [
                        'ნომენკლატურის_ID' => $vv['sku'],
                        'რაოდენობა' => $vv['quantity'],
                        'ზომის_ერთეულის_ID' => $vv['dimension_id'],
//                        'თანხა' => $vv['calcPrice'],
                        'თანხა' => $vv['calcPrice']*$vv['quantity'],
                    ];
                }
            }


        }
        return $ret;
    }

    /////// images importing
    public function imagesImport($params = [])
    {
        if (!_cv($params, 'directory'))
            return false;
        $mediaModel = new App\Models\Media\MediaModel();

        $dir = public_path() . '/static/shop/' . $params['directory'];
        $url = url('/') . '/static/shop/' . $params['directory'] . '/';

        $filesList = scandir($dir);

        if (!is_array($filesList))
            return false;

        $images = [];
        foreach ($filesList as $k => $v) {
            $parts = explode('_', $v);
            if (strlen($parts[0]) < 3) {
                continue;
            }
            //            print $parts[0];

            $fileId = $this->imageInsertDb(['fileName' => $v, 'directory' => $params['directory']]);

            $images[$parts[0]][] = [
                'id' => $fileId,
                'descriptions' => [],
                'title' => false,
                'devices' => [
                    'desktop' => $url . $v,
                    'tablet' => $url . $v,
                    'mobile' => $url . $v,
                ]
            ];

            //            break;
//            p($parts);
        }

        $imagesFound = count($images);
        $productsFound = 0;
        //p($images);
        foreach ($images as $k => $v) {
            $sku = trim($k);

            $product = DB::select("
                SELECT shop_products.id, shop_products_meta.id AS metaId, shop_products_meta.val FROM shop_products
                LEFT JOIN shop_products_meta ON shop_products_meta.data_id = shop_products.id AND shop_products_meta.key = 'images'
                WHERE shop_products.sku = '{$sku}'
            ");


            if (!isset($product[0]))
                continue;

            $oldImages = json_decode($product[0]->val, true) ?? [];

            foreach ($oldImages as $key => $image) {
                $oldImages[$key]['file'] = preg_replace('~.*/~', '', $image['devices']['desktop']);
            }
            foreach ($v as $key => $image) {
                $v[$key]['file'] = preg_replace('~.*/~', '', $image['devices']['desktop']);
            }

            $mergedArray = array_merge($oldImages, $v);
            $v = [];
            foreach($mergedArray as $image){
                $v[$image['file']] = $image;
            }

            ksort($v);
            $v = array_values($v);

            $productsFound++;

            $upd = [];
            if ($product[0]->metaId) {
                $upd['val'] = _psqlupd($v);
                DB::table('shop_products_meta')->where('id', $product[0]->metaId)->update($upd);
            } else {
                $upd['val'] = _psqlupd($v);
                $upd['data_id'] = $product[0]->id;
                $upd['key'] = 'images';
                DB::table('shop_products_meta')->where('id', $product[0]->metaId)->insert($upd);
            }
        }


        $filesListCount = count($filesList);
        //p($images);
        return "Found {$filesListCount} files; Grouped for {$imagesFound} products; Updated {$productsFound} Products";

    }

    public function imageInsertDb($params = [])
    {
        $mediaModel = new App\Models\Media\MediaModel();

        $upd['path'] = "shop/{$params['directory']}/{$params['fileName']}";

        $fileExists = MediaModel::select('id')->where('name', $upd['path'])->first();
        if (isset($fileExists->id))
            return $fileExists->id;

        $upd['upload_form'] = 'smartShop_ss_catalog';
        $upd['path'] = "shop/{$params['directory']}/{$params['fileName']}";
        $upd['disk'] = 'public';

        return $mediaModel->upd($upd);


    }

}
