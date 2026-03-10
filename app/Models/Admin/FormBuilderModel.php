<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Driver\Session;
use \Validator;
use Illuminate\Support\Facades\DB;

class FormBuilderModel extends Model
{

    public $table = 'form_builder';
    public $timestamps = true;
    protected $fillable = [
        'id',
        'form_config',
        'form_name',
        'form_settings',
    ];

    public function upd($data = []) {

        if(!_cv($data, ['form_name']))return false;

        if(_cv($data,['id'], 'nn')){
            $qr = DB::table($this->table)->select('id')->where('id', $data['id'])->first();
        }else{
            $qr = DB::table($this->table)->select('id')->where('form_name', $data['form_name'])->first();
        }

        $qr = _psqlRow(_toArray($qr));

        if(_cv($qr, 'id', 'nn')){
            $upd = FormBuilderModel::find($qr['id']);
            $upd['form_name'] = $data['form_name'];

        }else{
            $upd = new FormBuilderModel();
            $upd['form_name'] = $data['form_name'];
        }

        $upd['form_config'] = _psqlupd($data['form_config']);
        if(array_search( 'form_settings', $this->fillable)!==false){
            $upd['form_settings'] = _psqlupd($data['form_settings']);
        }

        $upd->save();

        return  $upd->id;

    }

    public function getOne($params = []){
        $params['limit'] = 1;
        $qr = $this->getListBy($params);

        $ret = (_cv($qr, 'list', 'ar'))?current($qr['list']):[];

        return $ret;
    }

    public function getListBy($params = []){
        $defaultPerPage = 50;
        $returnData = ['listCount'=>0,'list'=>[]];

        $qr =  DB::table($this->table);

        if(_cv($params, 'id') && !_cv($params, 'id', 'ar') ) $params['id'] = [$params['id']];
        if(_cv($params, 'id', 'ar')) $qr->whereIn('id', $params['id']);

        if(_cv($params, 'form_name') && !_cv($params, 'form_name', 'ar') ) $params['form_name'] = [$params['form_name']];
        if(_cv($params, 'form_name', 'ar')) $qr->whereIn('form_name', $params['form_name']);

        if(_cv($params, 'form_config')) $qr->where('form_config', 'like', "%{$params['form_config']}%");


        if( _cv($params, 'searchDate.0') && !_cv($params, 'searchDate.1')){
            $qr->where('updated_at', '=', $params['searchDate'][0]);
        }else if( _cv($params, 'searchDate.0') && _cv($params, 'searchDate.1')){
            /// if there is dates range (from, to) search entries within date range
            $qr->where('create_date', '>=', $params['searchDate'][0])
                ->where('create_date', '<=', $params['searchDate'][1]);
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

    public function getFormsBy($params = []){
        $data = $this->getListBy($params);


        $forms = [];
        foreach ($data['list'] as $k=>$v){
            $forms[$v['form_name']] = $v['form_config'];
        }

        return $forms;
    }
    public function getFormNames(){
        $qr =  DB::table($this->table)->select('form_name', 'id')->orderBy('form_name')->groupBy('form_name')->get();
        $qr = _psql(_toArray($qr));
        $qr = array_column($qr, 'form_name', 'id');

        return $qr;
    }

    public function deleteForm($params = []){
        if(!_cv($params, ['id'], 'nn'))return ['error'=>'form id not set'];
        $qr =  DB::table($this->table)->where('id', $params['id'])->delete();
        return ['success'=>'form deleted'];
    }




}
