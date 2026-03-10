<?php

namespace App\Models\Shop;

use App\Models\Admin\SmartTableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Models\Admin\RelationsModel;

class CouponsModel extends SmartTableModel
{
    protected $table = 'shop_coupons';

    protected $relationTable = '';
    protected $fieldConfigs = 'adminshop.coupons';


    public $timestamps = true;

    protected $allAttributes = [
        'id',
        'can_be_used',
        'used',
        'code',
        'offer_id',
        'updated_at',
        'created_at',
        'status'
    ];

    protected $fillable = [
        'can_be_used',
        'used',
        'code',
        'offer_id',
        'status'
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    // generate and insert coupons
    public function updCoupons($params = [])
    {
        if(!_cv($params, 'offer_id'))return ['error'=>'can`t create coupons; offer ID not set'];
        if(_cv($params, 'offer_type')!=='coupons')return ['error'=>'offer is not coupons'];

//        p($params);

//        print _cv($params, 'offer_type_rule.regenerate');

        $insertionArray = [];

//        p($params['offer_type']);
//        p($params['offer_type_rule']);
//        $coupons = $this->generateCoupons($params['offer_type_rule']);

        $this->deleteUnusedCoupons($params['offer_id']);
        foreach ($params['list'] as $k=>$v){
            if(isset($v['id']))continue;
            $v['offer_id'] = $params['offer_id'];
            $v['status'] = 'active';
            $v['can_be_used'] = _cv($params, 'coupon_use_limit', 'nn')?$params['coupon_use_limit']:1;
            DB::table($this -> table) -> insert($v);

        }

//        DB::table($this -> couponsTable) -> insert($insertionArray);
    }


    public function getCouponDiscount($params = []){
        $code = _cv($params, 'code');
        if(!$code)return [];
        $now = date("Y-m-d");
        DB::enableQueryLog();


        $qr = DB::table($this->table)
            ->select(DB::raw("{$this->table}.code, {$this->table}.id as couponId, shop_offers.discount_dimension, shop_offers.discount_amount, shop_offers.id as offerId, shop_offers.offer_type as discount_type "))
            ->leftJoin('shop_offers', "shop_offers.id", '=', "{$this->table}.offer_id")
            ->where("shop_offers.start_date", "<=", $now)
            ->where("shop_offers.end_date", ">=", $now)
            ->where("{$this->table}.code", $code)
            ->where("{$this->table}.status", 'active')
            ->where("shop_offers.offer_type", 'coupons')
            ->whereRaw("{$this->table}.can_be_used > {$this->table}.used")
            ->get();

// p(DB::getQueryLog());
        $qr = _toArray($qr);
//        p($qr);

        return isset($qr[0])?$qr[0]:[];

    }

    private function deleteUnusedCoupons($offerId=0){
        if(!is_numeric($offerId))return false;
        DB::table($this -> table) -> where('offer_id', $offerId)->where('used', '>','0')->update(['status'=>'closed']);
        DB::table($this -> table) -> where('offer_id', $offerId)->where('used', '0')->delete();

        return true;
    }
    private function generateCoupons($params = [], $offerId=0){

        $coupons_count = _cv($params, 'coupons_count', 'nn')?$params ['coupons_count']:1;
        $coupon_use_limit = _cv($params, 'coupon_use_limit', 'nn')?$params ['coupon_use_limit']:1;
        $coupon_prefix = _cv($params, 'coupon_prefix')?$params ['coupon_prefix']:'';
        $coupon_length = _cv($params, 'coupon_length', 'nn') && $params ['coupon_length']>4 && $params ['coupon_length']< 16 ? $params ['coupon_length'] : 4;

        $coupons = [];
        for ($i=1; $i <= $coupons_count; $i++){
            $coupons[] = [
                'code'=>$this->generateCouponCode($coupon_prefix, $coupon_length),
                'can_be_used'=>$coupon_use_limit,
                'used'=>0,
            ];
        }

        return $coupons;
    }

    private function generateCouponCode($prefix='', $length=4)
    {
        $prefix = empty($prefix)?'':$prefix;
        return $prefix.substr(str_shuffle('23456789ABCDEFGHJKMNPQRSTUVWXYZ'), 0, $length);

    }
}

