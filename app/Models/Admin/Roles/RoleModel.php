<?php

namespace App\Models\Admin\Roles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Media\MediaModel;

class RoleModel extends Model
{
    //
    protected $table = 'roles';
    protected $tablePermissions = 'role_has_permissions';
//    protected $metaTable = 'attribute_meta';
    public $timestamps = true;
    protected $error = false;
    protected $meta;

    //
    protected $allRoles = [
        'id',
        'name',
        'guard_name',
        'protected',
        'has_backend_access',
        'for_registration',
        'created_at',
        'updated_at',
    ];
    protected $fillable = [
        'id',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    function __construct()
    {
        parent::__construct();
        $this->error = Validator::make([], []);
        /**
        $v->errors()->add('some_field', 'some_translated_error_key');
        $fails = $v->fails(); // false!!! why???
        $failedMessages = $v->failed(); // 0 failed messages!!! why???
         */
    }

    public function getOne($params = [])
    {

        if (paramsCheckFailed($params, ['id'])) return [];

        $ret = $this->getList(['id'=>$params['id']]);
        if(isset($ret[0]))return $ret[0];

        return [];
    }

    public function getList($params = [])
    {

        $query ="
            roles.*,

CONCAT('{',
    GROUP_CONCAT( distinct
CONCAT('\"', role_has_permissions.permission_name, '\":{',
    '\"view\":', IF(role_has_permissions.`view` =1, 'true', 'false'),
    ',\"create\":', IF(role_has_permissions.`create` =1, 'true', 'false'),
    ',\"update\":', IF(role_has_permissions.`update` =1, 'true', 'false'),
    ',\"delete\":', IF(role_has_permissions.`delete` =1, 'true', 'false'),  '}'
)
)
, '}')
as permitions,

CONCAT('{\"contentTypes\":{
			\"permitions\":{',
    GROUP_CONCAT(
        DISTINCT
CONCAT('\"', content_permissions.permission_name, '\":{',
    '\"view\":', IF(content_permissions.`view` =1, 'true', 'false'),
    ',\"create\":', IF(content_permissions.`create` =1, 'true', 'false'),
    ',\"update\":', IF(content_permissions.`update` =1, 'true', 'false'),
    ',\"delete\":', IF(content_permissions.`delete` =1, 'true', 'false'),  '}'
)
)
, '}}}')
as content
";
        DB::enableQueryLog();


        $qr =  DB::table($this->table)->select(DB::raw($query));
            if(_cv($params, "id", 'nn'))$params['id'] = [$params['id']];
        if(_cv($params, "id", 'ar')) $qr->whereIn("{$this->table}.id", $params['id']);
        if(_cv($params, "name")) $qr->where("{$this->table}.name", $params['name']);

        $qr->leftJoin("role_has_permissions", function($join){
            $join->on("role_has_permissions.role_id", "=", "{$this->table}.id");
            $join->WhereNull("role_has_permissions.permission_values");
            $join->oRwhere("role_has_permissions.permission_values", '=', '');
        });

        $qr->leftJoin("role_has_permissions as content_permissions", function($join){
            $join->on("content_permissions.role_id", "=", "{$this->table}.id");
            $join->where("content_permissions.permission_values", '!=', '');
        });

        $qr = $qr->groupBy("{$this->table}.id")->orderBy("{$this->table}.name", 'asc')->get();
//        p(DB::getQueryLog());

        $qr = _psql(_toArray($qr));
//        p($qr);

        return $qr;

        /*** /
        $qr =  DB::table($this->table)->select("*");

        if(_cv($params, 'id')) $qr->where('id', $params['id']);
        $list = $qr->orderBy('name', 'asc')->get();
        $list = _toArray($list);

        foreach ($list as $key=>$val){

            $permitions = DB::table('role_has_permissions')->where('role_id',$val['id'])->get();
            $permitions = _toArray($permitions);
//            p($permitions);

            if($permitions){
                foreach ($permitions as $permition) {
                    if(_cv($permition, 'permission_values')){
                        $list[$key]['content'][$permition['permission_values']]['permitions'][$permition['permission_name']]['view'] = _cv($permition, 'view')?true:false;
                        $list[$key]['content'][$permition['permission_values']]['permitions'][$permition['permission_name']]['create'] =_cv($permition, 'create')?true:false;
                        $list[$key]['content'][$permition['permission_values']]['permitions'][$permition['permission_name']]['update'] =_cv($permition, 'update')?true:false;
                        $list[$key]['content'][$permition['permission_values']]['permitions'][$permition['permission_name']]['delete'] =_cv($permition, 'delete')?true:false;
                    }else{
                        $list[$key]['permitions'][$permition['permission_name']]['view'] = _cv($permition, 'view')?true:false;
                        $list[$key]['permitions'][$permition['permission_name']]['create'] = _cv($permition, 'create')?true:false;
                        $list[$key]['permitions'][$permition['permission_name']]['update'] = _cv($permition, 'update')?true:false;
                        $list[$key]['permitions'][$permition['permission_name']]['delete'] = _cv($permition, 'delete')?true:false;
                    }
                }
//                p($list[$key]);
            }
        }
//        p($list);
        return $list;

        /***/
    }

    public function upd($data = [])
    {
        if(!isset($data['id'])){
            $upd = new RoleModel();
        }else{
            $upd = RoleModel::find($data['id']);
        }
        $upd->name = $data['name'];
        $upd->save();

        DB::table('role_has_permissions')->where('role_id', $upd->id)->delete();
//        return $upd->id;


        foreach ($data['permitions'] as $k=>$v){
            DB::table('role_has_permissions')->insert([
                'role_id'=>$upd->id,
                'permission_name'=>$k,
                'view'=>   _cv($data['permitions'][$k], 'view')?1:0,
                'create'=> _cv($data['permitions'][$k], 'create')?1:0,
                'update'=> _cv($data['permitions'][$k], 'update')?1:0,
                'delete'=> _cv($data['permitions'][$k], 'delete')?1:0,
            ]);
        }

        if(!isset($data['content'])){
            return $upd->id;
        }

        foreach ($data['content'] as $kcontent=>$content){
            foreach ($content['permitions'] as $pk=>$premitions){

                DB::table('role_has_permissions')->insert([
                    'role_id'=>$upd->id,
                    'permission_name'=>$pk,
                    'permission_values'=>$kcontent,
                    'view'=>_cv($content['permitions'][$pk], 'view')?1:0,
                    'create'=>_cv($content['permitions'][$pk], 'create')?1:0,
                    'update'=>_cv($content['permitions'][$pk], 'update')?1:0,
                    'delete'=>_cv($content['permitions'][$pk], 'delete')?1:0,
                ]);

            }
        }

        return $upd->id;
    }

    public function deleteData($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;
        RoleModel::where('id', $data['id'])->delete();
        return $data['id'];
    }

    public function getPermissionByRole($params = []){

        $qr =  DB::table($this->tablePermissions)->select(['id','view','create','update','delete']);

        if(_cv($params, 'permission_name'))$qr->where('permission_name', $params['permission_name']);
        if(_cv($params, 'role_id', 'nn'))$qr->where('role_id', $params['role_id']);
        if(_cv($params, 'permission_values'))$qr->where('permission_values', $params['permission_values']);

        $qr = $qr->first();

        return $qr;

    }

}
