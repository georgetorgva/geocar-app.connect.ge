<?php

namespace App\Models\Silk;

use App\Models\Admin\TaxonomyRelationsModel;
use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;
use \Validator;

use Illuminate\Support\Facades\DB;

/**
 * main model for string translation
*/
class PackageTypesModel extends Model
{
    //

    protected $table = 'silk_package_types';
    public $timestamps = false;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'create_date',
        'field_config',
        'package_type',
        'package_type_uid',
        'sort',
        'confs',
        'info',
    ];

    private $rules = array(
//        'info' => 'required',
//        'logo'  => 'required',
    );

    protected $hidden = [

    ];

    public function upd($data = [])
    {
//p($data);
        $res = false;
        if(_cv($data, ['id'], 'nn')){
            $res = PackageTypesModel::find($data['id']);
        }

        if(!$res) $res = new PackageTypesModel();

        $res->field_config = _psqlupd(_cv($data, 'field_config'));
        $res->info = _psqlupd(_cv($data, 'info'));
        $res->package_type = _cv($data, 'package_type');
        $res->package_type_uid = _cv($data, 'package_type_uid');
        $res->sort = _cv($data, 'sort');
        $res->confs = _cv($data, 'confs');

        $res->save();

        return $res->id;
    }


    public function getOne($params = []){

        $params['limit'] = 1;
        $model = new PackageTypesModel();
        $ret = $model->getBy($params);

        if(is_array($ret))return $ret[0];

        return false;

    }

    public function getBy($params = []){
        DB::enableQueryLog();


        $model = PackageTypesModel::selectRaw("{$this->table}.info, {$this->table}.field_config, {$this->table}.create_date, {$this->table}.package_type, {$this->table}.package_type_uid, {$this->table}.sort, {$this->table}.id");

        if(_cv($params, 'field_config'))$model->where("{$this->table}.field_config", 'like', "%{$params['field_config']}%");
        if(_cv($params, 'info'))$model->where("{$this->table}.info", 'like', "%{$params['info']}%");
        if(_cv($params, 'form_title'))$model->where("{$this->table}.form_title", $params['form_title']);
        if(_cv($params, 'confs'))$model->where("{$this->table}.confs", $params['confs']);
        if(_cv($params, 'id'))$model->where("{$this->table}.id", $params['id']);

        $model->orderBy("sort", 'asc');
        $model->orderBy("id", 'asc');
        $model->groupBy("{$this->table}.id");

        if(_cv($params, 'limit', 'nn'))$model->limit($params['limit']);
        $ret = $model->get();
        $ret = _psql(_toArray($ret));

        $query = DB::getQueryLog();
//        p($query);

        return $ret;

    }

    public function deleteOne($params = []){
        if(!_cv($params, 'id', 'nn'))return false;

        $model = PackageTypesModel::find($params['id']);

        $ret = [];
        if($model) {
//            p($model);
            $ret = _psqlRow(_toArray($model));
            $model->delete();
        }
//p($ret);
        return $ret;

    }





}
