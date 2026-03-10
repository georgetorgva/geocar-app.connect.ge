<?php

namespace App\Models\Shop;

use App\Models\Admin\SmartTableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ShippingModel extends SmartTableModel
{
    protected $table = 'shop_shipping';
    public $timestamps = true;
    protected $fieldConfigs = 'adminshop.shippings';

    protected $allAttributes = [
        'id',
        'created_at',
        'updated_at',
        'conf',
        'status',
        'sort',
        'slug',
        'shipping_type',
        'info',
        'locations',
        'shipping_amount',
        'cart_min_amount',
        'cart_max_amount',

    ];

    protected $fillable = [
        'conf',
        'status',
        'sort',
        'slug',
        'shipping_type',
        'info',
        'locations',
        'shipping_amount',
        'cart_min_amount',
        'cart_max_amount',
    ];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'sort',
    ];

}
