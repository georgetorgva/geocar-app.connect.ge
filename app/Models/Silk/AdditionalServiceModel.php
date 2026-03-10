<?php

namespace App\Models\Silk;

use App\Models\Admin\TaxonomyRelationsModel;
use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;


use Illuminate\Support\Facades\DB;

/**
 * main model for string translation
*/
class AdditionalServiceModel extends Model
{
    //
    protected $table = 'silk_additional_services';
    public $timestamps = false;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'info',
        'price',
        'create_date',
        'service_type',
        'confs',
        'module',
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
            $res = AdditionalServiceModel::find($data['id']);
        }

        if(!$res) $res = new AdditionalServiceModel();

        $res->info = _psqlupd(_cv($data, 'info'));
        $res->confs = _psqlupd(_cv($data, 'confs'));
        $res->price = _cv($data, 'price');
        $res->service_type = _cv($data, 'service_type');
        $res->module = _cv($data, 'module')?$data['module']:'unknow';

        $res->save();

        return $res->id;
    }


    public function getOne($params = []){

        $params['limit'] = 1;
        $model = new AdditionalServiceModel();
        $ret = $model->getBy($params);

        if(is_array($ret))return $ret[0];

        return false;

    }

    public function getBy($params = []){
        DB::enableQueryLog();

        $model = AdditionalServiceModel::selectRaw("{$this->table}.info,
        {$this->table}.price,
        {$this->table}.create_date,
        {$this->table}.service_type,
        {$this->table}.id,
        {$this->table}.confs,
        {$this->table}.module
");

        if(_cv($params, 'info'))$model->where("{$this->table}.info", 'like', "%{$params['info']}%");
        if(_cv($params, 'confs'))$model->where("{$this->table}.confs", 'like', "%{$params['confs']}%");
        if(_cv($params, 'price'))$model->where("{$this->table}.price", $params['price']);

        if(_cv($params, 'service_type') && !_cv($params, 'service_type', 'ar'))$params['service_type'] = [$params['service_type']];
        if(_cv($params, 'service_type', 'ar'))$model->whereIn("{$this->table}.service_type", $params['service_type']);

        if(_cv($params, 'module') && !_cv($params, 'module', 'ar'))$params['module'] = [$params['module']];
        if(_cv($params, 'module', 'ar'))$model->whereIn("{$this->table}.module", $params['module']);

        if(_cv($params, 'id'))$model->where("{$this->table}.id", $params['id']);

        $model->groupBy("{$this->table}.id");

        if(_cv($params, 'limit', 'nn'))$model->limit($params['limit']);
        $ret = $model->get();

//        $query = DB::getQueryLog();
//        p($query);

        $ret = _psql(_toArray($ret), ['taxonomy']);

        return $ret;

    }

    public function deleteOne($params = []){
        if(!_cv($params, 'id', 'nn'))return false;

        $chanels = AdditionalServiceModel::find($params['id']);

        $ret = [];
        if($chanels) {
//            p($chanels);
            $ret = _psqlRow(_toArray($chanels));
            $chanels->delete();
        }
//p($ret);
        return $ret;

    }

    public function getMediaData($data = [], $contentType = ''){
        if(!is_array($data) || count($data) == 0)return [];

        $mediaModel = new MediaModel();
        $medias = $mediaModel->getList(['ids'=>$data ]);

        return $medias;
    }

    public function extractMediaIds($data=[]){
        if(!is_array($data))return [];

        $ret = array_column($data, 'id');
        return $ret;

    }



}
