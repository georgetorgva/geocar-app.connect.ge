<?php

namespace App\Models\BuildingDevelopment;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;

class BlockModel extends SmartTableModel
{
    protected $table = 'development_blocks';
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
    protected $fieldConfigs = 'adminBuildingDevelopment.blocks';

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
        'project_id',
        'slug',
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
        'project_id',
        'slug',
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
