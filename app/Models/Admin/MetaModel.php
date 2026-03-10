<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use \Validator;

use Illuminate\Support\Facades\DB;

/**
 * main meta model for all standard meta tables
 */
class MetaModel extends Model
{
    //

    public $table = 'options';
    public $timestamps = true;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'key',
        'value',
        'content_group',
        'data_type',
        'revision'
    ];

    private $rules = array(
        'key' => 'required',
        'value'  => 'required',
        'content_group'  => 'required',
        'data_type'  => 'required',
    );

    protected $hidden = [

    ];

    protected $attributes = [
        'content_group'  => 'other',
        'data_type'  => 'string',
    ];

    public function __construct($relatedTable = '')
    {
        parent::__construct();
        $this->table = $relatedTable;
        $this->error = Validator::make([], []);
    }

    public function updSingelMeta($data = []){
        if(!_cv($data, 'key') && !_cv($data, 'data_id', 'nn')){
            return false;
        }


        return DB::table($data['table'])->where('key', $data['key'])->where('data_id', $data['data_id'])->update(['val' => $data['val'], 'draft' => $data['draft']]);
    }

    public function updOne($data = []){
        /// if table not defined
        if(!_cv($data, 'table'))return false;

        /// if not set meta id AND not set key or data_id
        if(!_cv($data, 'id', 'nn') && (!_cv($data, 'key') || !_cv($data, 'data_id', 'nn'))){
            return false;
        }

        if(!isset($data['val']))$data['val'] = '';

        if(is_array($data['val']))$data['val'] = json_encode($data['val'], JSON_UNESCAPED_UNICODE);

        $data['draft'] = $data['val'];
        if(_cv($data, 'id', 'nn')){
            return DB::table($data['table'])->where('id', $data['id'])->update(['val' => $data['val'], 'draft' => $data['draft']]);
        }

        return DB::table($data['table'])->insert([
            'val' => $data['val'],
            'draft' => $data['draft'],
            'key' => $data['key'],
            'data_id' => $data['data_id'],
            'created_at'  => date("Y-m-d H:i:s"),
            'updated_at'  => date("Y-m-d H:i:s")
        ]);
    }

    public function updOneDraft($data = []){
        /// if table not defined
        if(!_cv($data, 'table'))return false;

        /// if not set meta id AND not set key or data_id
        if(!_cv($data, 'id', 'nn') && (!_cv($data, 'key') || !_cv($data, 'data_id', 'nn'))){
            return false;
        }

        if(!isset($data['draft']))$data['draft'] = '';

        if(is_array($data['draft']))$data['draft'] = _psqlupd($data['draft']);

        if(_cv($data, 'id', 'nn')){
            return DB::table($data['table'])->where('id', $data['id'])->update(['draft' => $data['draft']]);
        }

        return DB::table($data['table'])->insert([
            'draft' => $data['draft'],
            'key' => $data['key'],
            'data_id' => $data['data_id'],
            'created_at'  => date("Y-m-d H:i:s"),
            'updated_at'  => date("Y-m-d H:i:s")
        ]);
    }

    public function upd($data = [])
    {
//        p($data);
//        p( $this->error->getMessageBag()->add('error', 'The first message bag message') );

        if(!isset($data['table']))
            return $this->error->getMessageBag()->add('meta', $this->validationMessages['table']['notdefined']);

        if(!isset($data['data_id']))
            return $this->error->getMessageBag()->add('data_id', $this->validationMessages['table']['data_id']);

        if(!isset($data['meta']))
            return $this->error->getMessageBag()->add('meta', $this->validationMessages['table']['meta']);

        $table = $data['table'];
        $meta = $data['meta'];
        $dataId = $data['data_id'];

        $metaData = $this->getListRaw(['table'=>$table, 'data_id'=>$dataId]);

        foreach ($meta as $k=>$v){
            $k = trim(strip_tags($k));
            if(is_array($v))$v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_NUMERIC_CHECK); /// JSON_UNESCAPED_UNICODE

            /** if meta field exists update it else insert new one */
            if(_cv($metaData, [$k,'id'])){
                DB::table($table)->where('id', $metaData[$k]['id'])->update(['val' => $v]);
            }else{
                $ss = [
                    'data_id' => $dataId,
                    'key'  => $k,
                    'val'  => $v,
                    'created_at'  => date("Y-m-d H:i:s"),
                    'updated_at'  => date("Y-m-d H:i:s")

                ];
                DB::table($table)->insert($ss);
            }

        }

        return false;
    }

    public function updDraft($data = [])
    {
//        p($data);
//        p( $this->error->getMessageBag()->add('error', 'The first message bag message') );

        if(!isset($data['table']))
            return $this->error->getMessageBag()->add('meta', $this->validationMessages['table']['notdefined']);

        if(!isset($data['data_id']))
            return $this->error->getMessageBag()->add('data_id', $this->validationMessages['table']['data_id']);

        if(!isset($data['meta']))
            return $this->error->getMessageBag()->add('meta', $this->validationMessages['table']['meta']);

        $table = $data['table'];
        $meta = $data['meta'];
        $dataId = $data['data_id'];

        $metaData = $this->getListRaw(['table'=>$table, 'data_id'=>$dataId]);

        foreach ($meta as $k=>$v){
            $k = trim(strip_tags($k));
//            if(is_array($v))$v = json_encode($v, JSON_UNESCAPED_UNICODE);
            if(is_array($v))$v = _psqlupd($v);


            /** if meta field exists update it else insert new one */
            if(_cv($metaData, [$k,'id'])){
                $tmp = ['draft' => $v];
                if(_cv($data, 'publish'))$tmp['val'] = $v;

                DB::table($table)->where('id', $metaData[$k]['id'])->update($tmp);
            }else{
                $ss = [
                    'data_id' => $dataId,
                    'key'  => $k,
                    'val'  => '',
                    'draft'  => $v,
                    'created_at'  => date("Y-m-d H:i:s"),
                    'updated_at'  => date("Y-m-d H:i:s")

                ];
                if(_cv($data, 'publish'))$ss['val'] = $v;
                DB::table($table)->insert($ss);
            }

        }

        return false;
    }

//    creates meta field entry if not exists
    public function createMetaEntry(){

    }

    /**
     * if input data id and key returns only single field value
     * if input only data id returns list key:meta_keys => value:meta_keys array
     * if input only key returns key:data_id => value:meta_value
     */
    public function getList($data=[]){
        if(!isset($data['table']))return 'Table notdefined';

        $table = $data['table'];
        $translate = isset($data['translate'])?$data['translate']:0;
        $dataId = $data['data_id'] ?? false;
        $key = $data['meta_keys'] ?? false;
        $res = [];
//        $model = new MetaModel();
//        $builder = (array) DB::table($table)->select("id","key","val")->where("data_id", $dataId)->get()->toArray();
//p($data);
//        DB::enableQueryLog();
        if($dataId && $key){

            $res = DB::table($table)->select("data_id","key","val")->whereIn("key", $key)->where('data_id', $dataId)->get();

            if($res)$res = $res->toArray();
            $res = $this->prepareMetaData($res);
            $res = $this->flattenMetaData($res);

        }elseif ($dataId){
            $res = (array) DB::table($table)->select("data_id","key","val")->where("data_id", $dataId)->get()->toArray();

            $res = $this->prepareMetaData($res);
            $res = $this->flattenMetaData($res);

        }elseif ($key){
            $res = (array) DB::table($table)->select("data_id","key","val")->whereIn("key", $key)->get()->toArray();

            $res = $this->prepareMetaData($res);
            $res = $this->flattenMetaData($res);
//            foreach ($res as $v)$tmp[$v['data_id']] = $v['val'];
//            $res = $this->prepareMetaData($tmp);
        }
//        p(DB::getQueryLog());

        return $res;

    }

    public function getListDraft($data=[]){
        if(!isset($data['table']))return 'Table notdefined';

        $table = $data['table'];
        $dataId = $data['data_id'] ?? false;
        $key = $data['meta_keys'] ?? false;
        $res = [];
//        $model = new MetaModel();
//        $builder = (array) DB::table($table)->select("id","key","val")->where("data_id", $dataId)->get()->toArray();
//p($data);

        if($dataId && $key){
            $res = DB::table($table)->select("data_id","key","draft as val")->whereIn("key", $key)->where('data_id', $dataId)->get();

            if($res)$res = $res->toArray();
            $res = $this->prepareMetaData($res);
            $res = $this->flattenMetaData($res);

        }elseif ($dataId){
            $res = (array) DB::table($table)->select("data_id","key","draft as val")->where("data_id", $dataId)->get()->toArray();

            $res = $this->prepareMetaData($res);
            $res = $this->flattenMetaData($res);

        }elseif ($key){
            $res = (array) DB::table($table)->select("data_id","key","draft as val")->whereIn("key", $key)->get()->toArray();

            $res = $this->prepareMetaData($res);
            $res = $this->flattenMetaData($res);
//            foreach ($res as $v)$tmp[$v['data_id']] = $v['val'];
//            $res = $this->prepareMetaData($tmp);
        }

        return $res;

    }


    public function getBy($data=[]){
        if(!isset($data['table']))return 'Table notdefined';

        $model = DB::table($data['table']);
        if(_cv($data, 'data_id'))$model->where('data_id', $data['data_id']);
        if(_cv($data, 'key'))$model->where('key', $data['key']);
        if(_cv($data, 'id'))$model->where('id', $data['id']);

        $ret = $model->get()->toArray();

        return json_decode(json_encode($ret), 1);

    }

    public function getListRaw($params = []){

        if( !isset($params['table']) || !isset($params['data_id']) )return [];

        $metaData = (array) DB::table($params['table'])->select("id","key","val")
            ->where("data_id", $params['data_id'])->get()->toArray();

        $ret = [];

        $metaData = $this->prepareMetaData($metaData);
//p($metaData);
        foreach ($metaData as $k=>$v){
            $ret[$v['key']] = $v;
        }

        return $ret;
    }

    private function prepareMetaData($data = [])
    {
        $data = json_decode(json_encode($data),1);
        if(!is_array($data))$data = [];

        foreach ($data as $k=>$v){
            if(is_array($v['val']))continue;
            $tmp = json_decode($v['val'], 1);
            if(is_array($tmp))$data[$k]['val'] = $tmp;
        }

        return $data;
    }

    /** generate flatten array from meta raw array */
    public function flattenMetaData($data = [], $deep = false){
        $tmp = [];

        if($deep){
            foreach ($data as $v) $tmp[$v['data_id']][$v['key']] = $v['val'];
        }else{
            foreach ($data as $v) $tmp[$v['key']] = $v['val'];
        }

        return $tmp;
    }

}
