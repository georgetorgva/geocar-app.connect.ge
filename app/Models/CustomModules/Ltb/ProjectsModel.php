<?php

namespace App\Models\CustomModules\Ltb;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User\User;

class ProjectsModel extends SmartTableModel
{
    protected $table = 'ltb_projects';
    protected $metaTable = 'modules_meta';
    protected $taxonomyRelationTable = 'modules_taxonomy_relations';
    protected $rules = [];

    public $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs = 'adminltb.projects';

    //
    protected $allAttributes = [
        'id',
        'created_at',
        'updated_at',
        'status',
        'user_id',
        'sort',
        'date',
        'slug',
    ];
    protected $fillable = [
        'user_id',
        'status',
        'sort',
        'date',
        'slug',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function __construct($params = [])
    {
        $this->rules = config('adminltb.projects.validation');
//        parent::__construct($params);
//        $this->table;
    }

    public function comments(){
        return $this->hasMany(CommentsModel::class, 'master_id', 'user_id');
    }
    public function user(){
        return $this->belongsTo(User::class);
    }

}
