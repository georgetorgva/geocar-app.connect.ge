<?php

namespace App\Models\Shop;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;

class WalletsModel extends SmartTableModel
{
    protected $table = 'shop_wallets';
    protected $rules = [
        'status' => ['required'],
    ];

    public $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs = 'adminshop.wallets';

    //
    protected $allAttributes = [
        'id',
        'user_id',
        'type',
        'amount',
        'currency',
        'account_number',
        'created_at',
        'updated_at',
    ];
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'status',
        'currency',
        'account_number',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function __construct($params = [])
    {
//        parent::__construct($params);
//        $this->table;
    }
}
