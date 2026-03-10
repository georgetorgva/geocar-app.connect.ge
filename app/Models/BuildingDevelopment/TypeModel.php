<?php

namespace App\Models\BuildingDevelopment;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;

class TypeModel extends SmartTableModel
{
    protected $table = 'development_types';
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
    protected $fieldConfigs = 'adminBuildingDevelopment.types';

    //
    protected $allAttributes = [
        'id',
        'sort',
        'status',
        'rooms',
        'area_m2',
        'created_at',
        'updated_at',
        'project_id',
        'block_id',
        'conf',
        'type_name',
    ];
    protected $fillable = [
        'id',
        'sort',
        'status',
        'rooms',
        'area_m2',
        'created_at',
        'updated_at',
        'project_id',
        'block_id',
        'conf',
        'type_name',
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
