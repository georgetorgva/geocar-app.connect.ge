<?php

namespace App\Models\Shop;

use App\Models\Admin\SmartTableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Media\MediaModel;
use App\Models\Admin\MetaModel;

class LocationsModel extends SmartTableModel
{
    //
    protected $table = 'shop_locations';
    public $timestamps = false;
    public $error = false;
    protected $meta;
    protected $locale;
    protected $locales;
    protected $fieldConfigs = 'adminshop.locations';
    protected $rules =
        [
            'id' => 'integer',
            'domain' => 'required|string',
            'name_en' => 'required|string',
            'name_ge' => 'required|string',
            'location_type' => 'required|string',
        ];

    //

    protected $allAttributes = [
        'id',
        'domain',
        'pid',
        'name_en',
        'name_ge',
        'location_type',
        'sort',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'domain',
        'pid',
        'name_en',
        'name_ge',
        'sort',
        'status',
        'location_type',
        ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

}


