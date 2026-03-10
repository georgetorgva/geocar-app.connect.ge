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

class OfferModel extends SmartTableModel
{
    protected $table = 'shop_offers';

    protected $relationTable = 'shop_offer_relations';
    protected $couponsTable = 'shop_coupons';
    protected $fieldConfigs = 'adminshop.offers';


    public $timestamps = true;

    protected $allAttributes = [
        'id',
        'slug',
        'info',
        'conf',
        'offer_type',
        'offer_type_rule',
        'offer_type_group',
        'offer_type_products',
        'offer_type_attributes',
        'offer_target',
        'offer_target_attributes',
        'gift_count',
        'sort',
        'shipping_plans',
        'discount_dimension',
        'discount_amount',
        'start_date',
        'end_date',
        'updated_at',
        'created_at',
        'status' ];

    protected $fillable = [
        'slug',
        'info',
        'conf',
        'offer_type',
        'offer_type_rule',
        'offer_type_group',
        'offer_type_products',
        'offer_type_attributes',
        'offer_target',
        'offer_target_attributes',
        'gift_count',
        'sort',
        'shipping_plans',
        'discount_dimension',
        'discount_amount',
        'start_date',
        'end_date',
        'card_option',
        'status'
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];



    public function getUserRelatedOffers($userId = 0){
        if(!$userId)return [];

        $now = date("Y-m-d");
        DB::enableQueryLog();

        /// user group offers
        $qr = DB::table('users')
            ->select(DB::raw("{$this->table}.id"))
            ->crossJoin($this->table)
            ->where("{$this->table}.offer_type_rule", 'like', DB::raw("CONCAT('%\"', users.member_group, '\"%')"))

            ->where("{$this->table}.start_date", "<=", $now)
            ->where("{$this->table}.end_date", ">=", $now)
            ->where("{$this->table}.status", 'published')
            ->where("users.id", $userId)
            ->pluck("{$this->table}.id")->toArray();

        /// user individual offers
        $qrr = DB::table($this->relationTable)
            ->select(DB::raw("shop_offer_relations.offer_id"))
            ->crossJoin($this->table)
            ->whereRaw("{$this->table}.id = {$this->relationTable}.offer_id")

            ->where("{$this->table}.start_date", "<=", $now)
            ->where("{$this->table}.end_date", ">=", $now)
            ->where("{$this->table}.status", 'published')
            ->where("{$this->relationTable}.data_id", $userId)
            ->where("{$this->relationTable}.node", 'userDiscount')
            ->where("{$this->relationTable}.table", 'offer_type_data_ids')
            ->pluck('shop_offer_relations.offer_id')->toArray();

        /// merge user offer ids
        $res = array_merge(is_array($qr)?$qr:[], is_array($qrr)?$qrr:[]);

// p(DB::getQueryLog());

        return $res;
    }

}

