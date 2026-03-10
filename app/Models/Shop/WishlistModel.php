<?php

namespace App\Models\Shop;

use \Validator;
use Illuminate\Support\Str;
use App\Models\Admin\MetaModel;
use App\Models\Media\MediaModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\SmartTableModel;
use Illuminate\Database\Eloquent\Model;

class WishlistModel extends SmartTableModel
{
    //
    protected $table = 'shop_wishlist';
    public $timestamps = true;
    public $error = false;
    protected $meta;
    protected $locale;
    protected $locales;
    protected $fieldConfigs = 'adminshop.wishlist';


    //

    protected $allAttributes = [
        'id',
        'session',
        'list_info',
        'list_type',
        'title',
        'user_id',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'session',
        'list_info',
        'list_type',
        'title',
        'user_id',
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

        if (_cv($params, ['user_id']) && !_cv($params, ['user_id'], 'ar')) $params['user_id'] = [$params['user_id']];
        if (_cv($params, 'user_id', 'ar')) $qr -> whereIn($this->table.'.user_id', $params['user_id']);

        if (_cv($params, ['session']) && !_cv($params, ['session'], 'ar')) $params['session'] = [$params['session']];
        if (_cv($params, 'session', 'ar')) $qr -> whereIn($this->table.'.session', $params['session']);

        if (_cv($params, ['list_type']) && !_cv($params, ['list_type'], 'ar')) $params['list_type'] = [$params['list_type']];
        if (_cv($params, 'list_type', 'ar')) $qr -> whereIn($this->table.'.list_type', $params['list_type']);

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


    public function upd($data = [])
    {
//        p($data);
        $wishlistTypes = config('adminshop.generalConfigs.wishlistTypes');
//        p($wishlistTypes);
        DB::enableQueryLog();

        if(!_cv($data, 'list_type') || array_search($data['list_type'], $wishlistTypes) === false ) $data['list_type'] = $wishlistTypes[0];
        if(!_cv($data, 'title'))$data['title'] = $data['list_type'];
        /// validate page table regular data
        $validator = Validator::make($data,
            [
                'list_type' => 'required|string',
                'list_info' => 'required|array',
                'title' => 'string',
            ]
        );

        if ($validator->fails()){
            return $validator->messages()->all();
        }

        $session = appSessionId();

        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:'';

        // .... update
        if(_cv($data, 'id', 'nn')){
            $upd = WishlistModel::where('id', $data['id'])->first();
        }else if($userId){
            $upd = WishlistModel::where('user_id', $userId)->where('list_type', $data['list_type'])->first();
        }else if($session){
            $upd = WishlistModel::where('session', $session)->where('list_type', $data['list_type'])->first();
        }

        // .... or create
        if(!isset($upd->id)){
            $upd = new WishlistModel();
            $upd['session'] = "{$session}_{$userId}";
            $upd['list_type'] = $data['list_type'];
            $upd['title'] = $data['title'];
            $upd['user_id'] = $userId ?? null;
        }

        $listInfo = isset($upd->list_info)?_toArray(_psqlCell($upd->list_info)):[];

        $listInfoUpdated = [];
        foreach ($listInfo as $k=>$v){
            $listInfoUpdated[$v['id']] = $v;
        }

        if(_cv($data, ['list_info'], 'ar')){
            foreach ($data['list_info'] as $k=>$v){
                $listInfoUpdated[$v['id']] = $v;
            }
        }
        $updatedProducts = $this->updateWishlistProductsInfo($listInfoUpdated, $upd->id);

        $upd->list_info = _psqlupd($updatedProducts);

        $upd->save();

//        p(DB::getQueryLog());

        return $upd->id;
    }

    public function joinCarts()
    {
        if(!Auth::user())return false;
//        DB::enableQueryLog();
        $userCart = $this->getOne(['user_id'=>Auth::user()->id]);
        $carts = $this->getList(['session'=>appSessionId(), 'orderField'=>'user_id', 'orderDirection'=>'desc']);

        $userCartCleaned = [];
        foreach ($userCart as $k=>$v){
            $userCartCleaned[$v['id']] = $v;
        }

        if(_cv($carts, 'listCount')<=1)return false;

        $mainCart = [];
        $otherCarts = [];

        /// loop for current session carts
        foreach ($carts['list'] as $k=>$v){
            /// loop for every single product in cart
            foreach ($v as $kk=>$vv){
                /// if some product already exists in users cart session cart item will be ignored
                if(isset($userCartCleaned[$vv['id']]))continue;
                $userCartCleaned[$vv['id']] = $vv;
            }
        }
        $cartInfo = _psqlupd($userCartCleaned);

        $this->delCart();
        $this->upd(['id'=>$userCart['id'], 'cart_info'=>$cartInfo, 'session'=>appSessionId()]);

        return true;
    }

    public function delList($params = []){
        $userId = (Auth::user() && Auth::user()->id)?Auth::user()->id:'';

        WishlistModel::where('id', _cv($params, 'id'))->where('user_id', $userId)->delete();
    }

    public function removeListItem($params = []){

        $productId = (_cv($params, 'productId', 'nn'))?$params['productId']:'';
        $list = $this->getOne(['id'=>_cv($params, 'id')]);
        if(!_cv($list, ['id'], 'nn'))return false;

        if(_cv($list, ['list_info'], 'ar')){
//            $key = array_search($productId, array_column($list['list_info'],'id'));
//            if(is_numeric($key)) unset($list['list_info'][$key]);
            foreach ($list['list_info'] as $k=>$v) { if($v['id']==$productId) unset($list['list_info'][$k]); }
        }

        WishlistModel::where('id', $params['id'])->update(['list_info'=>_psqlupd(_cv($list, ['list_info']))] );

        return $list;
    }

    public function updateWishlistProductsInfo($list_info = [], $listId = ''){
        if(!is_numeric($listId))return [];

        $ids = array_column($list_info, 'id');
        $products = new ProductsModel();
        $productsList = $products->getList(['id'=>$ids, 'translate'=>1]);

        if(!_cv($productsList, 'list'))return [];

        foreach ($productsList['list'] as $k=>$v){
            if(!_cv($list_info, $v['id'])) continue;

            $list_info[$v['id']]['title'] = _cv($v,['title']);
            $list_info[$v['id']]['product_attributes'] = _cv($v,['product_attributes']);
            $list_info[$v['id']]['slug'] = _cv($v,['slug']);
            $list_info[$v['id']]['price'] = _cv($v,['price']);
            $list_info[$v['id']]['images'] = _cv($v,['images']);

        }
//        WishlistModel::where('id', $listId)->update(['list_info'=>_psqlupd($list_info)]);
        return $list_info;

    }


}


