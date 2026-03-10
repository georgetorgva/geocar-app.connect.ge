<?php

namespace App\Models\BuildingDevelopment;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;

class FlatModel extends SmartTableModel
{
    protected $table = 'development_flats';
    protected $metaTable = 'development_meta';
    protected $taxonomyRelationTable = 'development_taxonomy_relations';
    protected $rules = [
//        'slug' => ['required', 'min:2'],
        'sort' => ['numeric'],
        'status' => ['required'],
//        'conf' => ['required'],
    ];

    public $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs = 'adminBuildingDevelopment.flats';

    //
    protected $allAttributes = [
        'id',
        'created_at',
        'updated_at',
        'status',
        'sort',
        'conf',
        'project_id',
        'flat_number',
        'block_id',
        'type_id',
        'floor_number',
        'area_m2',
        'living_area_m2',
        'condition',
        'price_m2',
        'price',
        'discount_price_m2',
    ];
    protected $fillable = [
        'id',
        'created_at',
        'updated_at',
        'status',
        'sort',
        'conf',
        'project_id',
        'flat_number',
        'block_id',
        'type_id',
        'floor_number',
        'area_m2',
        'living_area_m2',
        'condition',
        'price_m2',
        'price',
        'discount_price_m2',
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
