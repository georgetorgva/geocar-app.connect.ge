<?php

namespace App\Models\Shop;

use App\Models\Admin\MetaModel;
use App\Models\Admin\RelationsModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;

class ProductsModel extends Model
{
    protected $table = 'shop_products';
    protected $metaTable = 'shop_products_meta';
    protected $productAttributeRelationTable = 'shop_product_attribute_relation';
    protected $productProductRelationTable = 'shop_product_product_relation';
    protected $productStockRelationTable = 'shop_product_stock_relation';
    protected $productGroupsTable = 'shop_product_group';
    protected $userOffers = [];
    protected $userMainDiscount = 0;
    protected $userMainDiscountTyp = '';

    public $timestamps = true;
    protected $error = false;

    protected $allAttributes = [
        'id',
        'created_at',
        'updated_at',
        'sort',
        'slug',
        'status',
        'qty',
        'sku',
        'step',
        'box_count',
        'dimension',
        'dimension_id',
        'conf',
        'price',
//        'old_price',
        'group_id',
        'views',
    ];
    protected $selectAll = [
        'id',
        'sort',
        'slug',
        'status',
        'qty',
        'sku',
        'step',
        'box_count',
        'dimension',
        'dimension_id',
        'conf',
        'price',
//        'old_price',
        'group_id',
        'views',
    ];

    protected $fillable = [
        'sort',
        'slug',
        'status',
        'qty',
        'sku',
        'conf',
        'price',
//        'old_price',
        'group_id',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function getProductsGrouped($params)
    {
        $params['limit'] = 1;

        $firstProduct = $this -> getOne($params);

        if (!isset($firstProduct['id'])) return [];

        $groupId = _cv($firstProduct,['group_id'], 'nn')?$firstProduct['group_id']:'99999999';
        $listParams = (['group_id'=>[$groupId], 'excludeIds'=>[$firstProduct['id']] ]);
        $res = $this -> getList( $listParams );

        array_unshift($res['list'], $firstProduct);
        $res['listCount'] = count($res['list']);

        return $res;
    }

    public function getOne($params=[])
    {
        $params['limit'] = 1;

        $res = $this -> getList($params);

        if (isset($res['list'][0])) return $res['list'][0];

        return [];
    }

    public function upd($params=[])
    {

        $productConf = config('adminshop.products');
        $allAttributesExtra = array_merge($this -> allAttributes, ['attributes', 'relatedProductList', 'productStockList']);
        $separatedData = separateTableMetaFieldsData($params, $allAttributesExtra, $productConf['fields']);

        $product = _cv($separatedData['data'], ['id'], 'nn') ? ProductsModel::find($separatedData['data']['id']) : new ProductsModel(); // check if exists

        if(!$product->id){
            $product -> views = 1;
            if(!_cv($separatedData, ['data','sku'])) $separatedData['data']['sku'] = date('ymdHi');
        }

        $product -> slug = _cv($separatedData, ['data','slug']);
        $product -> conf = _cv($separatedData['data'], 'conf', 'ar') ? _psqlupd($separatedData['data']['conf']) : '';
        $product -> price = _cv($separatedData, ['data','price'], 'nn');
//        $product -> old_price = _cv($separatedData, ['data','old_price'], 'nn');
        $product -> status = _cv($separatedData, ['data','status']) ?? 'published'; //_cv($separatedData, ['data','status']);
        $product -> qty = _cv($separatedData, ['data','qty'], 'nn');
        $product -> sort = _cv($separatedData, ['data','sort'], 'nn');
        $product -> group_id = _cv($separatedData, ['data','group_id']);
        $product -> sku = _cv($separatedData, ['data','sku']);
        $product -> step = _cv($separatedData, ['data','step']);
        $product -> box_count = _cv($separatedData, ['data','box_count']);
        $product -> dimension = _cv($separatedData, ['data','dimension']);
        $product -> dimension_id = _cv($separatedData, ['data','dimension_id']);

        $product -> save();

        if (isset($separatedData['data']['productStockList']))
        {
            $stockRelationParams['relationTable'] = $this -> productStockRelationTable;
            $stockRelationParams['firstKeyName'] = 'products_id';
            $stockRelationParams['firstKeyValue'] = $product -> id;

            if (_cv($separatedData['data'], ['productStockList'], 'ar'))
            {
                $stockRelationParams['productStockList'] = $separatedData['data']['productStockList'];
                $stockRelationParams['formKeyName'] = 'productStockList';
                $stockRelationParams['secondKeyName'] = 'stock_id';
                $stockRelationParams['quantityKeyName'] = 'qty';

                RelationsModel::doRelations($stockRelationParams);
            }

            else RelationsModel::removeRelations($stockRelationParams);
        }

        if (_cv($separatedData['data'], ['attributes']) && _cv($separatedData['data'], ['attributes'], 'ar'))
        {
            $attributesRelationParams['attributes'] = $separatedData['data']['attributes'];
            $attributesRelationParams['formKeyName'] = 'attributes';
            $attributesRelationParams['relationTable'] = $this -> productAttributeRelationTable;
            $attributesRelationParams['firstKeyName'] = 'product_id';
            $attributesRelationParams['firstKeyValue'] = $product -> id;
            $attributesRelationParams['secondKeyName'] = 'attribute_id';

            RelationsModel::doRelations($attributesRelationParams);
        }

        if (_cv($separatedData['data'], ['relatedProductList']))
        {
            $relatedProductsRelationParams['relationTable'] = $this -> productProductRelationTable;
            $relatedProductsRelationParams['firstKeyName'] = 'product_id';
            $relatedProductsRelationParams['firstKeyValue'] = $product -> id;

            if (_cv($separatedData['data'], ['relatedProductList'], 'ar'))
            {
                $relatedProductsRelationParams['relatedProductList'] = $separatedData['data']['relatedProductList'];
                $relatedProductsRelationParams['formKeyName'] = 'relatedProductList';
                $relatedProductsRelationParams['secondKeyName'] = 'data_id';

                RelationsModel::doRelations($relatedProductsRelationParams);
            }

            else RelationsModel::removeRelations($relatedProductsRelationParams);
        }

        if (_cv($separatedData, ['meta']))
        {
            $meta = new MetaModel($this -> metaTable);

            $meta -> upd(['meta' => $separatedData['meta'], 'data_id' => $product -> id, 'table' => $this -> metaTable]);
        }

        return $product -> id;
    }

    public function getList ($params=[])
    {

        if(Auth::user()){
            $tmp = _psqlCell(Auth::user()->additional_info);
//            p(_cv($tmp, ['contragents','loyaltyDiscountPercent']));
            if(Auth::user()->status == 'master'){
                $this->userMainDiscount = _cv($tmp, ['contragents','contragentDiscountPercent']);
                $this->userMainDiscountTyp = 'contragentDiscountPercent';
            }else{
                $this->userMainDiscount = _cv($tmp, ['contragents','loyaltyDiscountPercent']);
                $this->userMainDiscountTyp = 'loyaltyDiscountPercent';

            }

            $offerModel = new OfferModel();
            $this->userOffers = $offerModel->getUserRelatedOffers(Auth::user()->id);
        }

        $productConf = config('adminshop.products');
//p($productConf);
        $returnData['listCount'] = 0;
        $returnData['attributes'] = [];
        $translate = $returnData['localisedTo'] = requestLan(_cv($params, 'translate'));
//        $translate = _cv($params, 'translate');

        $returnData['list'] = [];
        $params['limit'] = _cv($params, ['limit'], 'nn') && $params['limit'] <= _cv($productConf, ['getList','maxListLimit'], 'nn') ?$params['limit']:10;
        $params['page'] = _cv($params, 'page', 'nn')?$params['page']:1;
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;


//        p($params);
        DB::enableQueryLog();
        $qstr = [];
        $qr = DB::table($this -> table);

        //// meta table fields

        if(false && _cv($productConf,['fields'])){
            foreach ($productConf['fields'] as $k=>$v){
                if(!_cv($productConf, ['adminListFields', $k]))continue;

                $localisableKey = _cv($v, 'translate')?"{$k}_{$translate}":$k;
                $qr->leftJoin("{$this->metaTable} as meta_{$k}", function($join) use ($k, $v, $localisableKey){
                    $join->on("meta_{$k}.data_id", "=", "{$this->table}.id")->where("meta_{$k}.key", $localisableKey);
                });

                $qstr[] = "meta_{$k}.val as meta_{$k} ";

            }
        }

        $today = date('Y-m-d');
        $qstr[] = '
        (
        SELECT
            CONCAT(
                "[",
                GROUP_CONCAT(
                    CONCAT("[",r.offer_id,",",r.data_id,",\"",r.node,"\",\"",r.TABLE,"\",\"",o.slug,"\",\"",o.discount_dimension,"\",\"",o.discount_amount,"\",\"",o.gift_count,"\",\"",o.start_date,"\",\"",o.end_date,"\",\"",o.card_option,"\"]"
                    )
                ),
                "]"
            )
        FROM
            shop_offer_relations r
            JOIN shop_offers o ON o.id = r.offer_id
        WHERE
            r.data_id = shop_products.id
            AND o.status = \'published\'
            AND o.start_date <= \''.$today.' 00:00:00\'
            AND o.end_date >= \''.$today.' 23:59:59\'
    ) AS offers ';

//        $qstr[] = "JSON_ARRAYAGG (
//		json_array(
//      all_offers_rel.offer_id,
//      all_offers_rel.data_id,
//			all_offers_rel.node,
//			all_offers_rel.TABLE,
//			all_offers.slug,
//			all_offers.discount_dimension,
//			all_offers.discount_amount,
//			all_offers.gift_count
//    )
//		) AS offers,";

        /// get product related offers
//        $qr->leftJoin("shop_offer_relations as all_offers_rel", function($join){
//            $join->on("all_offers_rel.data_id", "=", "{$this->table}.id");
//
//        })->leftJoin("shop_offers as all_offers", function($join){
//            $join->on("all_offers.id", "=", "all_offers_rel.offer_id")
//                ->where("all_offers.status", 'published')
//                ->where("all_offers.start_date", '<=', date('Y-m-d 00:00:00'))
//                ->where("all_offers.end_date", '>=', date('Y-m-d 23:59:59'));
//        });


        if(_cv($params, 'fields', 'ar')){
            $allAttributes = array_flip($this->allAttributes);
            foreach ($params['fields'] as $v){
                if(!isset($allAttributes[$v]))continue;
                $qstr[] = "shop_products.{$v} ";
            }
        }else{
            $qstr[] = ' shop_products.'.implode( ', shop_products.', $this->selectAll).' '; /// 'shop_products.*,';
        }


///        CONCAT("{", GROUP_CONCAT(DISTINCT CONCAT("\"",`shop_product_stock_relation`.`stock_id`, "\":",`shop_product_stock_relation`.`qty`)), "}") AS `productStockList`,
        if(_cv($params, 'selectFields')){
            $qstr[] = $params['selectFields'];
        }

        $metaKeys = _cv($params, 'meta_keys', 'ar');

        $qstr[] = "(
        SELECT
            GROUP_CONCAT(
                CONCAT(spm.KEY, \"----GROUP_CONCATED_KEY_VAL_SEPARATOR----\", spm.val, \"----GROUP_CONCATED_FIELD_END----\") SEPARATOR \"\"
            )
        FROM
            shop_products_meta spm
        WHERE
            spm.data_id = shop_products.id
    ) AS metas ";


        if(!_cv($params, 'search') && _cv($params, 'searchText') )$params['search'] = $params['searchText'];
        if ( _cv($params, 'search') ){

            $qr -> leftJoin('shop_products_meta as meta_search', function($join) use ($metaKeys){
                $join -> on('meta_search.data_id', '=', $this -> table . '.id');
            });


            $params['search'] = trim(strip_tags($params['search']));
            $params['searchTransliterated'] = transliterate($params['search']);

            $qr->orWhere(function($q)use($params){
                $q -> orWhereRaw("LOCATE('{$params['searchTransliterated']}', {$this -> table}.slug)")
                   -> orWhereRaw("LOCATE('{$params['search']}', {$this -> table}.sku)")
                   -> orWhereRaw("LOCATE('{$params['search']}', {$this -> table}.id)")
                   -> orWhere('meta_search.val', 'like', "%{$params['search']}%");
            });

            $qr->orderByRaw("
        CASE
            WHEN shop_products.slug = ? THEN 1
            WHEN shop_products.slug LIKE ? THEN 2
            WHEN shop_products.slug LIKE ? THEN 3
            WHEN shop_products.sku LIKE ? THEN 4
            WHEN shop_products.id LIKE ? THEN 5
            WHEN meta_search.val LIKE ? THEN 6
            ELSE 7
        END ASC,
        shop_products.slug ASC,
        shop_products.id DESC
    ", [
                $params['searchTransliterated'],
                $params['searchTransliterated'] . '%',
                '%' . $params['searchTransliterated'] . '%',
                '%' . $params['search'] . '%',
                '%' . $params['search'] . '%',
                '%' . $params['search'] . '%'
            ]);

        }

        $qr -> leftJoin('shop_product_product_relation', function($join) use ($metaKeys){
            $join -> on('shop_product_product_relation.product_id', '=', $this -> table . '.id');
        });
        $qstr[] = 'CONCAT("[",GROUP_CONCAT(DISTINCT(shop_product_product_relation.data_id)),"]") as relatedProductList';


        $qr -> leftJoin($this -> productAttributeRelationTable . ' as attributes_relation', function($join) use ($params){
            $join -> on('attributes_relation.product_id', '=', $this -> table . '.id');
        }) -> leftJoin('shop_attribute', 'shop_attribute.id', '=', 'attributes_relation.attribute_id');

        $qstr[] = 'CONCAT("{", GROUP_CONCAT(DISTINCT CONCAT("\"", `shop_attribute`.`id`,"\":",`shop_attribute`.`attribute`)), "}") AS `product_attributes`';



        if (_cv($params, ['id']) && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) $qr -> whereIn($this -> table.'.id', $params['id']);

        if (_cv($params, 'group_id', 'ar')) $qr -> whereIn($this -> table.'.group_id', $params['group_id']);

        if (_cv($params, 'excludeIds', 'ar')) $qr -> whereNotIn($this -> table.'.id', $params['excludeIds']);

        if (_cv($params, ['price']) && !_cv($params, 'price', 'ar')) $params['price'] = [$params['price'], $params['price']];
        if (_cv($params, ['price'], 'ar')) {
            if(!_cv($params, ['price', 1]))$params['price'][1] = 9999999;
            $qr -> whereBetween($this -> table.'.price', [$params['price'][0], $params['price'][1]]);
        }

        if (_cv($params, ['status']) && !_cv($params, ['status'], 'ar')) $params['status'] = [$params['status']];
        if (_cv($params, 'status', 'ar')) $qr -> whereIn($this -> table.'.status', $params['status']);

        if (_cv($params, ['slug']) && !_cv($params, ['slug'], 'ar')) $params['slug'] = [$params['slug']];
        if (_cv($params, 'slug','ar')) $qr -> whereIn($this -> table.'.slug', $params['slug']);

        if (_cv($params, ['sku']) && !_cv($params, ['sku'], 'ar')) $params['sku'] = [$params['sku']];
        if (_cv($params, 'sku','ar')) $qr -> whereIn($this -> table.'.sku', $params['sku']);

        if (_cv($params, 'conf') && !is_array($params['conf'])) $qr -> where($this -> table.'.conf', 'like', "%{$params['conf']}%");

        if (_cv($params, 'qty')) $qr -> where($this -> table.'.qty', $params['qty']);
        if (_cv($params, 'qtyMore')) $qr -> where($this -> table.'.qty', '>=', $params['qtyMore']);
        if (_cv($params, 'sort')) $qr -> where($this -> table.'.sort', $params['sort']);

        /// filter by attributes
        if (_cv($params, 'attributes', 'ar'))
        {
            foreach ($params['attributes'] as $attrKey => $attrValues)
            {
                if(is_numeric($attrValues))$attrValues = [$attrValues];
                if(is_array($attrValues) && !count($attrValues))continue;

                $qr -> leftJoin("{$this -> productAttributeRelationTable} as {$this -> productAttributeRelationTable}_{$attrKey}", "{$this -> productAttributeRelationTable}_{$attrKey}.product_id", '=', $this -> table . '.id');
                $qr -> whereIn("{$this -> productAttributeRelationTable}_{$attrKey}" . '.attribute_id', $attrValues);
            }
        }

        /// filter by fields with AND logic
        if (_cv($params, 'searchBy', 'ar')){
            foreach ($params['searchBy'] as $k=>$v){
                if(!_cv($productConf, ['adminListFields', $k, 'searchable']))continue;
                $qr -> whereRaw("LOCATE('{$v}', {$productConf['adminListFields'][$k]['searchable']})");
            }
        }
        /// filter by fields with OR logic
        if (_cv($params, 'searchByOr', 'ar')){
            $qr -> where(function($q)use($params, $productConf){
                foreach ($params['searchByOr'] as $k=>$v){
                    if(!_cv($productConf, ['adminListFields', $k, 'searchable']))continue;
                    $q -> orWhereRaw("LOCATE('{$v}', {$productConf['adminListFields'][$k]['searchable']})");
                }
            });
        }

        /// select full list count
//        $listCount = $qr->count(DB::raw('DISTINCT(shop_products.id)'));

        $returnData['attributes'] = [];
//        /// find all awailable attributes
        if(_cv($params, 'availableAttributes')){
            $availableAttributes = $qr->select(DB::raw('GROUP_CONCAT(DISTINCT(attributes_relation.attribute_id)) as attributes'))->value('attributes');
            $returnData['attributes'] = json_decode(json_encode(explode(',', $availableAttributes), JSON_NUMERIC_CHECK), 1);
        }


        /// order section
        $sortfield = 'id';
        $sortDirection = 'DESC';

        if(_cv($params, ['sortDirection'])){
            $sortDirection = $params['sortDirection'];
        }else if(_cv($params, ['orderDirection'])){
            $sortDirection = $params['orderDirection'];
        }

        if(_cv($params, ['sortField']) && _cv($productConf, ['fields', $params['sortField']])){
            $sortfield = "{$params['sortField']}";
        }else if(_cv($params, ['orderField']) && _cv($productConf, ['fields', $params['orderField']])){
            $sortfield = "{$params['orderField']}";
        }else if( _cv($params, ['sortField']) && _cv($productConf, ['adminListFields', $params['sortField']]) ){
            $sortfield = "{$this -> table}.{$params['sortField']}";
        }
//        print $sortDirection;

//        $sortfield = 'id'; //// sxva velit sortirebisas query andomebs did dros. droebit ase iyos sanam moxdeba queris optimizacia
        $qr -> orderBy("{$sortfield}", $sortDirection);

        /// grouping section
        $qr -> groupBy('shop_products.id');

        /// paging section
        if (_cv($params, 'limit')) $qr -> take($params['limit']);
        if (_cv($params, 'page')) $qr -> skip(($params['page'] - 1) * $params['limit']) -> take($params['limit']);

        /// select products
        $qStr = "SQL_CALC_FOUND_ROWS " . implode(',', $qstr);
        $qr->selectRaw(DB::raw($qStr));

//        $qr -> select(DB::raw(implode(', ', $qstr )));


        $list = $qr -> get();

        /// Get total count from the previous query (eliminates second query)
        $listCount = DB::select('SELECT FOUND_ROWS() as total')[0]->total;


//        p(DB::getQueryLog());
//        $list = false;
//        p($list);

        if (!$list) return $returnData;

        $ret = _psql(_toArray($list));

//                p($ret);
        $getLocalised = _cv($params, 'translate');
        foreach ($ret as $k => $v)
        {

            if(isset($v['metas'])){
                $meta = decodeJoinedMetaData($v['metas']);
                unset($v['metas']);
                $ret[$k] = mergeToMetaData($v, $meta, $this -> allAttributes);
            }

            if(isset($v['product_attributes'])){
                $ret[$k]['attributes'] = $this -> reverseArray($v['product_attributes']);
            }

            $ret[$k] = $this->acceptDiscountPrice($ret[$k]);

            if($getLocalised){
                $ret[$k] = extractTranslated($ret[$k], $translate, $productConf['fields']);
            }

            if(_cv($params, 'fields', 'ar')){
                $tmp = [];
                foreach ($params['fields'] as $kk=>$vv){
                    $tmp[$vv] = @$ret[$k][$vv];
                }

                $ret[$k] = $tmp;
            }



        }


        $returnData['listCount'] = $listCount;
        $returnData['list'] = $ret;
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;

        return $returnData;
    }

    public function del($data = [])
    {
        if (!_cv($data, 'id', 'nn')) return false;

        \DB::table('shop_product_attribute_relation') -> where('product_id', $data['id']) -> delete();
        \DB::table('shop_product_stock_relation') -> where('products_id', $data['id']) -> delete();
        \DB::table('shop_product_product_relation') -> where('product_id', $data['id']) -> delete();
        \DB::table('shop_products_meta') -> where('data_id', $data['id']) -> delete();

        ProductsModel::where('id', $data['id']) -> delete();

        return $data['id'];
    }

    public function updStatus($data = [])
    {
        if (!_cv($data, 'id', 'nn') || !_cv($data, 'status', 'nn')) return false;

        ProductsModel::where('id', $data['id']) -> update(['status' => $data['status']]);

        return $data['id'];
    }

    public function reverseArray($sourceArray)
    {
        if (!is_array($sourceArray)) return [];

        $destArray = [];

        foreach ($sourceArray as $key => $value)
        {
            $destArray[$value][] = $key;
        }

        return $destArray;
    }

    public function getProductGroups($params)
    {

        $returnData['listCount'] = 0;
        $returnData['list'] = [];
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;

//        p($params);
        DB::enableQueryLog();

        $qStr = 'id, slug';

        $qr = DB::table($this -> productGroupsTable) -> select(DB::raw($qStr));

        $params['limit'] = $params['limit'] ?? 10;

        if (_cv($params, ['id']) && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) $qr -> whereIn('shop_products.id', $params['id']);

        if (_cv($params, ['slug']) && !_cv($params, ['slug'], 'ar')) $params['slug'] = [$params['slug']];
        if (_cv($params, 'slug','ar')) $qr -> whereIn('slug', $params['slug']);

        $listCount = $qr->count(DB::raw('DISTINCT(id)'));

        if (_cv($params, 'limit')) $qr -> take($params['limit']);
        if (_cv($params, 'page')) $qr -> skip(($params['page'] - 1) * $params['limit']) -> take($params['limit']);

        $list = $qr -> get();

//        p($list);
//        p(DB::getQueryLog());

        if (!$list) return $returnData;

        $ret = _toArray($list);

        $returnData['listCount'] = $listCount;
        $returnData['list'] = $ret;
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;

        return $returnData;
    }

    public function addProductGroup($params)
    {

        if(!_cv($params, 'groupSlug'))return false;
        $slug = sanitizeFilename($params['groupSlug']);
        DB::enableQueryLog();

        $qStr = 'id, slug';

        $ret = DB::table($this -> productGroupsTable) -> select(DB::raw($qStr)) -> where('slug', $slug)->first();

        if(isset($ret->id)) return $ret->id;

        $insertid = DB::table($this -> productGroupsTable)->insertGetId([
            'slug' => $slug,
        ]);

        return $insertid;

    }

    public function updateViewCount($params=[])
    {
        if(!_cv($params, 'single') || _cv($params, 'type') !== 'product' || !_cv($params, 'id', 'nn') )return false;

        $product = ProductsModel::find($params['id']);
        if($product)$product->increment('views');

        $this->updProductFootprint($params);

        return $params['id'];
    }

    public function updProductFootprint($params=[]){
        if(!_cv($params, 'single') || _cv($params, 'type') !== 'product' || !_cv($params, 'id', 'nn') )return false;

        $qr['user_id'] = Auth::user()?Auth::user()->id:0;
        $qr['product_id'] = $params['id'];
        $qr['ip'] = $_SERVER['REMOTE_ADDR'];
        $qr['updated_at'] = $qr['created_at'] = date('Y-m-d H:i:s');

        DB::table('shop_history_view')->insert($qr);
    }

    public function decreaseQty($params = []){
        $productId = _cv($params, 'productId', 'nn');
        $qty = _cv($params, 'qty', 'nn');
        $boxQty = _cv($params, 'boxQty', 'nn');

        if(!$productId || (!$qty && !$boxQty))return false;

        if($qty){
            ProductsModel::find($productId)->decrement('qty', $qty);
        }

//        if($qty){
//            ProductsModel::find($productId)->decrement('qty', $qty);
//        }


//
//        $product = DB::select(DB::raw("update shop_products
//         SET qty = qty-{$qty}
//         where id = {$productId}
//         limit 1 "));

    }

    public function updateStockQty($params = []){
        if(!_cv($params, 'stockData', 'ar'))return false;

        foreach ($params['stockData'] as $k=>$v){
            if(!isset($v['qty']) || !is_numeric($v['qty']))continue;
            DB::table($this->table)->where('sku', $v['sku'])->update(['qty'=>$v['qty']]);
        }
        return true;
    }

    public function acceptDiscountPrice($params = []){
//        print $this->userMainDiscount;
        if(!isset($params['price'])){
            return $params;
        }
        //// lmt = limited price
        $params['calcPrice'] = $params['price'];
        $params['boxCalcPrice'] = $params['box_price'] = ($params['box_count']?round($params['box_count']*$params['price'], 2):0);
        $params['box_sell_status'] = ($params['box_count']>0 && $params['box_price']>0)?1:0;

        if(!isset($params['offers'][0])){
            return $params;
        }

        /// discount first step
        foreach ($params['offers'] as $k=>$v){

            ///// regular discounts on retail products
            if($v[2]=='discount' && $v[3]=='product'){
                $tmp = discountCalculator($price = $params['price'], $discountAmount = $v[6], $discountType = $v[5], $v[0], $v);

                if(isset($params['discount'][0]['discountAmount']) && $params['discount'][0]['discountAmount'] >= $tmp['discountAmount']){
                    continue;
                }

//                $tmp['offerId'] = $v[0];
                $params['discount'] = [$tmp];

                $params['calcPrice'] = $tmp['calcPrice'];
//                break;

            }

            ///// regular discounts on box products
            if($v[2]=='boxDiscount' && $v[3]=='product'){
                $tmp = discountCalculator($price = $params['box_price'], $discountAmount = $v[6], $discountType = $v[5], $v[0], $v);

                if(isset($params['boxDiscount'][0]['discountAmount']) && $params['boxDiscount'][0]['discountAmount'] >= $tmp['discountAmount']){
                    continue;
                }

//                $tmp['offerId'] = $v[0];
                $params['boxDiscount'] = [$tmp];
                $params['boxCalcPrice'] = $tmp['calcPrice'];
//                break;

            }

        }

        /// if current user has some offers
        /// make discount over the exist discount
        if(isset($this->userOffers[0])){

            $tmpCach = [];
            foreach ($params['offers'] as $k=>$v){
                $tmp = [];
//                print $v[0];
//                p($this->userOffers);
                ///// regular discounts for logged in user on retail products
                if(($v[2]=='userGroupsDiscount' || $v[2]=='userDiscount') && $v[3]=='product' && array_search($v[0], $this->userOffers)!==false){
                    $tmp = discountCalculator($price = $params['calcPrice'], $discountAmount = $v[6], $discountType = $v[5], $v[0], $v);
//

                    if(isset($tmpCach['discountAmount']) && $tmpCach['discountAmount'] >= $tmp['discountAmount']){
                        continue;
                    }
                    $tmp['offerId'] = $v[0];
                    $tmpCach = $tmp;
//                    p($tmpCach);

                }

            }

            if(isset($tmpCach['offerId'])){
                $params['discount'][] = $tmpCach;
                $params['calcPrice'] = $tmpCach['calcPrice'];
            }
        }

        /// this discount will accept after user checks use card at cart checkout page
        /// member discount from service
        if(false && strpos(_cv($params, 'discount.0.loyalty'), 'discount_') !== false && $this->userMainDiscount>0 ){

            $tmp = discountCalculator($price = $params['calcPrice'], $discountAmount = $this->userMainDiscount, $discountType = $this->userMainDiscountTyp );

            $tmp['offerId'] = '-';
            $params['boxDiscount'][] = $tmp;
            $params['boxCalcPrice'] = $tmp['calcPrice'];

            $tmp['offerId'] = '-';
            $params['discount'][] = $tmp;
            $params['calcPrice'] = $tmp['calcPrice'];
        }

        foreach ($params['offers'] as $k=>$v){
            if($v[2]=='limitDiscount' && $v[3]=='product'){
                $tmp = discountCalculator($price = $params['price'], $discountAmount = $v[6], $discountType = $v[5], $v[0], $v);
                $params['lmt'] = $tmp['calcPrice'];
                if($params['lmt'] > $params['calcPrice'])$params['calcPrice'] = $tmp['calcPrice'];
            }
        }

        /// if price is under 0 set to 0
        if($params['calcPrice'] < 0)$params['calcPrice'] = 0;
        if($params['boxCalcPrice'] < 0)$params['boxCalcPrice'] = 0;

        return $params;
    }

    public function getAvailableAttributes($params = []){


//p($params);
/*** /
        if(!_cv($params, 'id', 'ar'))return [];
        $qr = "select DISTINCT(spar.attribute_id) as attributes
        from shop_product_attribute_relation as spar
        where product_id in (".implode(',', $params['id']).")";
/***/

        if(!_cv($params, 'category', 'ar'))return [];
        $params['category'] = "'".implode("','", $params['category'])."'";

        $qr = "SELECT spar.attribute_id as attributes FROM `shop_product_attribute_relation` spar
where spar.product_id in ( select spar2.product_id from shop_product_attribute_relation spar2 where spar2.attribute_id in({$params['category']}) )
group by spar.attribute_id";

        $availableAttributes = DB::select($qr);
        if(!isset($availableAttributes[0]->attributes))return [];


        $res = array_column($availableAttributes, 'attributes');

            return $res;
    }

}

