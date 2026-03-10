<?php

namespace App\Models\Admin;

use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MongoDB\Driver\Session;
use \Validator;
use Illuminate\Support\Facades\DB;

class OnlineFormsModel extends Model
{

    public $table = 'forms';
    public $timestamps = true;
    protected $fillable = [
        'id',
        'name',
        'data',
        'ord'
    ];


    public function upd($data = [], $formType = false,  $status = false) {

        if(!is_array($data) || !count($data))return false;

        $upd['name'] = $formType?$formType:_cv($data, 'formType');
        $upd['status'] = $status?$status:_cv($data, 'status');
        if(!$upd['name'])$upd['name'] = 'unknow form';
        if(_cv($data, 'formType'))unset($data['formType']);
        $upd['data'] = $data;

        $upd = _sanitizeData($upd);
        $upd['data'] = _psqlupd($upd['data']);

        $ret = DB::table($this->table)->insert($upd);

        return  $ret;

    }
    public function del($formType, $data) {
        $ret = OnlineFormsModel::where('name', $formType)->where('data', 'LIKE', '%'.$data.'%')->delete();
    }
    public function checkExist($params = []) {
        $data = OnlineFormsModel::where('name', $params['formType'])->where(function($query) use ($params) {
            if($params['findType'] == 'OR'){
                foreach($params['value'] as $val){
                    $query->where('data', 'LIKE', '%'.$val.'%');
                }
            } elseif($params['findType'] == 'AND'){
                foreach($params['value'] as $val){
                    $query->orWhere('data', 'LIKE', '%'.$val.'%');
                }
            }
        })->get();

        if($data->count() > 0){
            return $data;
        } else {
            return false;
        }
    }

    public function getForm($id = ''){
        $qr =  DB::table($this->table)->find($id);
        $qr = _psqlRow(_toArray($qr), ['data']);

        $qr['data'] = $this->prepareData($qr['data']);

        return $qr;
    }

    public function getList($formType = ''){
        $qr =  DB::table($this->table)->where('name', $formType)->orderByDesc('id')->get();
        $qr = _psql(_toArray($qr), ['data']);

        return $qr;
    }

    public function getListBy($params = []){

        $defaultPerPage = 50;
        $returnData = ['listCount'=>0,'list'=>[]];

        $qr =  DB::table($this->table);

        if(_cv($params, 'formType') && !_cv($params, 'formType', 'ar') ) $params['formType'] = [$params['formType']];
        if(_cv($params, 'formType', 'ar')) $qr->whereIn('name', $params['formType']);

        if(_cv($params, 'search') && !_cv($params, 'data'))$params['data'] = $params['search'];
        if(_cv($params, 'data')) $qr->where('data', 'like', "%{$params['data']}%");

        if( _cv($params, 'searchDate.0') && !_cv($params, 'searchDate.1')){
            $qr->where('updated_at', '=', $params['searchDate'][0]);
        }else if( _cv($params, 'searchDate.0') && _cv($params, 'searchDate.1')){
            /// if there is dates range (from, to) search entries within date range
            $qr->where('updated_at', '>=', $params['searchDate'][0])
                ->where('updated_at', '<=', $params['searchDate'][1]);
        }

        $orderDirection = _cv($params, 'orderDirection')?$params['orderDirection']:'desc';
        $orderColumn = _cv($params, 'orderColumn')?$params['orderColumn']:'updated_at';

        if (strtolower($orderDirection) == 'random') {
            $qr->inRandomOrder();
        }else{
            $qr->orderBy($orderColumn, 'desc');
        }

        $returnData['listCount'] = $qr->count();

        ///
        if(_cv($params, 'perPage', 'nn'))$defaultPerPage = $params['perPage'];
            $qr->limit($defaultPerPage);

        if(_cv($params, 'pageNumber', 'nn')){
            $offset = ($params['pageNumber'] * $defaultPerPage) - $defaultPerPage > $returnData['listCount']?0:($params['pageNumber'] * $defaultPerPage)-$defaultPerPage;
            $qr->offset($offset);
        }


        $returnData['list'] = $qr->get();

        $returnData['list'] = _psql(_toArray($returnData['list']), ['data']);

        return $returnData;
    }

    public function getFormTypes(){
        $qr =  DB::table($this->table)->where('name')->groupBy('name')->get();
        $qr = _psql(_toArray($qr), ['data']);

        $ar =[];

        foreach ($qr as $item) {
            foreach ($item as $key=>$value){
//                p(is_array(json_decode($value, true)));
                $var = json_decode($value, true);
                if(is_array($var)){
                    $item->$key = $var;
                }
            }
            $ar[]= $item;
        }
        return $ar;
    }

    public function prepareData($data = ''){
        $ret = json_decode($data,1) ? json_decode($data,1) : [];
        return $ret;
    }
}
