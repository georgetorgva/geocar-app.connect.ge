<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use \Validator;

use Illuminate\Support\Facades\DB;

/**
 * main model for string translation
 */
class WordsModel extends Model
{
    //

    protected $table = 'words';
    public $timestamps = false;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'key',
        'value',
        'changed',
    ];

    private $rules = array(
        'key' => 'required',
        'value'  => 'required',
    );

    protected $hidden = [

    ];

    protected $attributes = [
        'key'  => 'string',
        'value'  => 'string',
    ];

    public function __construct($relatedTable = '')
    {
        parent::__construct();
        $this->error = Validator::make([], []);
    }

    public function upd($data = [])
    {

        $res = false;

        if(!isset($data['key']))return false;

        $data['key'] = $this->cleanKey($data['key']);

        if(isset($data['key'])){
            $res = WordsModel::where('key', $data['key'] )->first();
        }

        if(!$res) $res = new WordsModel();

        $existValues = (isset($res->value) && _psqlCell($res->value))?_psqlCell($res->value):[];
        $locales = config('app.locales');
        $value = [];
//        p($data);
        foreach ($locales as $k=>$v){
            if(!isset($res->id) || !isset($data['value'][$k]) ){
                $value[$k] = $data['key'];
            }else{
                $value[$k] = $data['value'][$k];
            }

//
//            $value[$k] = isset($data['value'][$k])?$data['value'][$k]:$data['key'];
////            $value[$k] = $data['value'][$k];



            }

        if(!isset($res->id))$res->key = $data['key'];

        if(isset($res->changed)){
            $res->changed = $res->changed+1;
        }else{
            $res->changed = 1;
        }
        $res->value = _psqlupd($value);
        $res->save();

        return $res->id;
    }


    public function getOne($data=[]){


        $model = WordsModel::select(['id', 'key', 'value']);
        if(_cv($data, 'value'))$model->where('value', 'like', "%{$data['value']}%");
        if(_cv($data, 'key'))$model->where('key', $this->cleanKey($data['key']));

        if(_cv($data, 'id'))$model->where('id', $data['id']);

        $ret = $model->get();

        if(!$ret)return [];
        $ret = current(_psql(_toArray($ret)));

        if(!isset($ret['id']))return false;

        return $ret;


    }

    public function getBy($data=[]){


        $model = WordsModel::select(['id', 'key', 'value']);
        if(_cv($data, 'value'))$model->where('value', 'like', "%{$data['value']}%");
        if(_cv($data, 'key'))$model->where('key', $this->cleanKey($data['key']));
        if(_cv($data, 'id'))$model->where('id', $data['id']);
        $model->orderBy('key', 'asc');
        $ret = $model->get()->toArray();

        foreach ($ret as $k=>$v){
            $ret[$k]['value'] = json_decode($v['value'], 1);
        }

        return $ret;

    }

    public function deleteWord($params = []){
        if(!_cv($params, 'id', 'nn'))return false;

        return WordsModel::where('id', $params['id'])->delete();

    }

    public function cleanKey($key = 'null', $shorten = 200){
        return strtolower(mb_substr(trim(strip_tags($key)), 0, $shorten));
    }

    public function wordsByLan($locale = ''){
        $words = $this->getBy();
        $res = [];
        $locales = config('app.locales');
        foreach ($words as $v){
            foreach ($locales as $kk=>$vv){
                $res[$kk][$v['key']] = isset($v['value'][$kk])?$v['value'][$kk]:$v['key'];
            }

        }
        if(isset($res[$locale])) return $res[$locale];
        return $res;
    }


}
