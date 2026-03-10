<?php

namespace App\Models\CustomModules\Ltb;

use App\Models\Admin\SmartTableModel;
use \Validator;
use Illuminate\Support\Facades\DB;

class CommentsModel extends SmartTableModel
{
    protected $table = 'ltb_comments';
    protected $taxonomyRelationTable = 'modules_taxonomy_relations';
    protected $metaTable = 'modules_meta';
    protected $rules = [];
    public $timestamps = true;
    protected $error = false;
    protected $fieldConfigs = 'adminltb.comments';

    protected $allAttributes = [
        'id',
        'master_id',
        'author_id',
        'sort',
        'created_at',
        'updated_at',
        'status',
        'slug',
        'date',
        'name',
        'rating',
        'commentary',
        'image',
        'video',
    ];
    protected $fillable = [
        'master_id',
        'author_id',
        'status',
        'sort',
        'date',
        'slug',
        'name',
        'rating',
        'commentary',
        'image',
        'video',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function __construct($params = []) {
        $this->rules = config('adminltb.comments.validation');
     }

//    public function getList($params = [])
//    {
//        $selectFields = ['id','user_id','sort','created_at','updated_at','status','slug','date','name','commentary','rating'];
//        $selectFields = "{$this -> table}.".implode(", {$this -> table}.", $selectFields);
//
//        $qStr = "{$selectFields}, IFNULL(users.fullname, 'No Name') AS fullName";
//        $qr = DB::table($this -> table) -> select(DB::raw($qStr));
//
//        $qr -> leftJoin('users', function($join){
//            $join -> on('users.id', '=', $this -> table . '.user_id');
//        });
//
//        $list = _psql(_toArray($qr -> get()));
//        $returnData['list'] = $list;
//
//        return $returnData;
//    }

}
