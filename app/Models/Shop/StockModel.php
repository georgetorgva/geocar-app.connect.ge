<?php

namespace App\Models\Shop;

use App\Models\Admin\SmartTableModel;
use Illuminate\Database\Eloquent\Model;
use \Validator;
use Illuminate\Support\Facades\DB;

class StockModel extends SmartTableModel
{
    protected $table = 'shop_stock';
    protected $error = false;
    protected $meta;

    protected $taxonomyRelationTable = '';
    protected $metaTable = '';
    protected $rules = [
//        'slug' => ['required', 'min:2'],
//        'sort' => ['numeric'],
//        'status' => ['required'],
//        'conf' => ['required'],
    ];

    public $timestamps = true;
    protected $fieldConfigs = 'adminshop.stock';


    //
    protected $allAttributes = [
        'id',
        'pid',
        'title',
        'sku',
        'upc',
        'price',
        'price_old',
        'images',
        'info',
        'conf',
        'status',
        'slug',
        'sort',
        'supplier_code',
        'created_at',
        'updated_at',
    ];
    protected $fillable = [
        'title',
        'sku',
        'upc',
        'price',
        'price_old',
        'images',
        'info',
        'conf',
        'status',
        'slug',
        'supplier_code',

    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'content_type',
        'slug',
    ];

    public function updStatus($data = [])
    {
        if (!_cv($data, 'id', 'nn') || !_cv($data, 'status', 'nn')) return false;

        StockModel::where('id', $data['id']) -> update(['status' => $data['status']]);

        return $data['id'];
    }


}
