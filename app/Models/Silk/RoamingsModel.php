<?php

namespace App\Models\Silk;

use App\Models\Admin\TaxonomyRelationsModel;
use Illuminate\Database\Eloquent\Model;
use \Validator;

use Illuminate\Support\Facades\DB;

/**
 * main model for string translation
*/
class RoamingsModel extends Model
{
    //

    protected $table = 'silk_roaming';
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'sort',
        'info',
        'roaming_type',
        'form_name',
    ];

    private $rules = array(
//        'info' => 'required',
//        'logo'  => 'required',
    );

    protected $hidden = [

    ];

    public function upd($data = [])
    {

        $res = false;
        if(_cv($data, ['id'], 'nn')){
            $res = RoamingsModel::find($data['id']);
        }


        if(!$res){
            $res = new RoamingsModel();
        }

        $res->sort = _cv($data, 'sort', 'nn')?$data['sort']:1;
        $res->info = _psqlupd(_cv($data, 'info'));
        $res->roaming_type = _cv($data, 'roaming_type');
        $res->form_name = _cv($data, 'form_name');

        $res->save();

        return $res->id;
    }


    public function getOne($params = []){

        $params['limit'] = 1;
        $model = new RoamingsModel();
        $ret = $model->getBy($params);

        if(is_array($ret))return $ret[0];

        return false;

    }

    public function getBy($params = []){
        DB::enableQueryLog();


        $model = RoamingsModel::selectRaw("{$this->table}.info, {$this->table}.form_name,{$this->table}.roaming_type, {$this->table}.sort, {$this->table}.id");

        if(_cv($params, 'info'))$model->where("{$this->table}.info", 'like', "%{$params['info']}%");
        if(_cv($params, 'roaming_type'))$model->where("{$this->table}.roaming_type", $params['roaming_type']);
        if(_cv($params, 'form_name'))$model->where("{$this->table}.form_name", $params['form_name']);
        if(_cv($params, 'id'))$model->where("{$this->table}.id", $params['id']);

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

        $model = RoamingsModel::find($params['id']);

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
