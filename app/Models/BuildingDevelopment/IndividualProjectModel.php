<?php

namespace App\Models\BuildingDevelopment;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;

class IndividualProjectModel extends SmartTableModel
{
    protected $table = 'development_individual_projects';
    protected $metaTable = 'development_meta';
    protected $taxonomyRelationTable = 'development_taxonomy_relations';
    protected $rules = [
        'slug' => ['required', 'min:2'],
        'sort' => ['numeric'],
        'status' => ['required'],
//        'conf' => ['required'],
    ];


    public $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs = 'adminBuildingDevelopment.individualProjects';

    //
    protected $allAttributes = [
        'id',
        'created_at',
        'updated_at',
        'status',
        'slug',
        'area_m2',
        'sort',
        'conf',
    ];
    protected $fillable = [
        'id',
        'created_at',
        'updated_at',
        'status',
        'slug',
        'area_m2',
        'sort',
        'conf',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'content_type',
        'slug',
    ];

    public function __construct($params = [])
    {
//        parent::__construct($params);
//        $this->table;
    }


}
