<?php

namespace App\Models\BuildingDevelopment;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;

class FloorModel extends SmartTableModel
{
    protected $table = 'development_floors';
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
    protected $fieldConfigs = 'adminBuildingDevelopment.floors';

    //
    protected $allAttributes = [
        'id',
        'created_at',
        'updated_at',
        'status',
        'price_m2',
        'sort',
        'conf',
        'ending_date',
        'date',
        'block_id',
        'floor_number',
    ];
    protected $fillable = [
        'id',
        'created_at',
        'updated_at',
        'status',
        'price_m2',
        'sort',
        'conf',
        'ending_date',
        'date',
        'block_id',
        'floor_number',
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
