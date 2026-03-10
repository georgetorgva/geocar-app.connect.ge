<?php
namespace App\Models\Admin;

use App\Models\Media\MediaModel;
use \Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OptionsModel extends Model
{

    public $table = 'options';
    public $timestamps = false;
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

    /** clean value from regular table fields */
    protected $cleanFromFields = [
        'id',
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


    public function getListBy($params = [])
    {
        $qr = DB::table($this->table)->select(['id','key','value','data_type','content_group']);

        if (isset($params['content_group'])) {
            $qr->where('content_group', $params['content_group']);
        }
        if (isset($params['content_group_locate'])) {
            $qr->whereRaw("LOCATE ('{$params['content_group_locate']}', content_group)");
        }

        if (isset($params['key'])) {
            $qr->where('key', $params['key']);
        }
        if (isset($params['id'])) {
            $qr->where('id', $params['id']);
        }

        $list = $qr->get();

        $ret = [];
        foreach ($list as $k => $v) {
            $ret[] = $this->decodeValues($v);
        }

        return $ret;
    }

    public function getOneBy($params = [])
    {
        $res = $this->getListBy($params);

        if(!isset($res[0]))return [];

        if(_cv($params, 'return') && _cv($res[0], $params['return']))return $res[0][$params['return']];

        return $res[0];

    }

    public function getByKey($key)
    {
        $contact = OptionsModel::where('key','=', $key)->first();
        $rr= json_decode($contact);
        if($rr){
            $contactmail = $rr->value;
        }else{
            $contactmail =null;
        }

        return $contactmail;
    }

    public function getListByRaw($params = [])
    {
        $qr = DB::table($this->table)->select(['id','key','value','data_type','content_group']);

        if (isset($params['content_group'])) {
            $qr->where('content_group', $params['content_group']);
        }
        if (isset($params['key'])) {
            $qr->where('key', $params['key']);
        }
        if (isset($params['id'])) {
            $qr->where('id', $params['id']);
        }
        if (isset($params['search'])) {
            $qr->whereRaw("LOCATE('{$params['search']}', value)");
        }

        $qr->orderBy("key", 'asc');

        $list = $qr->get();
        $list = _psql(_toArray($list));

        if(_cv($params, 'return') && isset($list[0][$params['return']]))return array_column($list, $params['return']);
        return $list;
    }

    public function getKeyValListBy($params = [])
    {

        if(_cv($params, 'rawList')){
            $list = $this->getListByRaw($params);
        }else{
            $list = $this->getListBy($params);
        }

        $ret = [];

        foreach ($list as $k=>$v){
            if(!isset($v['value']))continue;
            $ret[$v['key']] = $v['value'];
        }

        return $ret;
    }

    public function getOne($params = [])
    {
        if (paramsCheckFailed($params, ['id'])) return false;

        $ret = OptionsModel::find($params['id']);
//p($ret);

//        $ret = _psql(_toArray($ret));
//        $rr = json_decode($ret->value);
//        p($rr);
        $ret = $this->decodeValues($ret);

        return $ret;
    }

    /**
     * get single setting by key
     * group name is optional; by default uses 'site_configurations'
     */
    public function getSetting($key='', $contentGroup = 'site_configurations', $params = [])
    {

        if (( !$key || !$contentGroup) && !_cv($params, 'id', 'nn')) return false;
        $ret = OptionsModel::select(['value', 'key', 'id']);

        if($key) $ret->where('key', $key);
        if($contentGroup) $ret->where('content_group', $contentGroup);
        if(_cv($params, 'id', 'nn')) $ret->where('id', $params['id']);

        $ret = $ret->first();

        if(!$ret)return false;
        $ret = _psqlRow(_toArray($ret));

        if(_cv($params, 'return')=='raw') return $ret;

        if(_cv($ret, 'value'))return $ret['value'];
        return $ret;
    }

    /**
     * custom update function
     */
    public function upd($params = [], $content_group='other')
    {

        if (!_cv($params, ['key'])) {
            return false;
        }

        $upd['key'] = _cv($params, ['key']);
        $upd['value'] = $this->cleanOptionsValue($params);

        $upd['content_group'] = $content_group;
        $upd['data_type'] = 'string';
        $upd['revision'] = '';

        /// unset tree sorting relation children nodes
        if(isset($upd['value']['children'])) unset($upd['value']['children']);

        if (is_array($upd['value'])) {
            $upd['value'] = _psqlupd($upd['value']);
            $upd['data_type'] = 'json';
        } else {
            $upd['data_type'] = 'string';
        }

        $upd['revision'] = $upd['value'];


        if (isset($params['id'])) {
            $tmp = $this::find($params['id']);
//            $upd['revision'] = $tmp['value'];
            $tmp['value'] = $upd['value'];
            $tmp['revision'] = $tmp['value'];
            $tmp['data_type'] = $upd['data_type'];
            $tmp->save();

            return $tmp->id;
        } else {
            $tmp = new OptionsModel();
            $tmp->key = $upd['key'];
            $tmp->value = $upd['value'];
            $tmp->content_group = $upd['content_group'];
            $tmp->data_type = $upd['data_type'];
            $tmp->revision = $upd['revision'];
            $tmp->save();
            return $tmp->id;
        }

        return false;
    }

    public function updSetting($params = [])
    {
//        p($params);

        if (!_cv($params, ['key']) || !_cv($params, ['content_group'])) {
            return false;
        }


        $upd['key'] = _cv($params, ['key']);
        $upd['value'] = _cv($params, ['value']);

        $upd['content_group'] = _cv($params, ['content_group']);
        $upd['data_type'] = 'string';
        $upd['revision'] = '';

        /// unset tree sorting relation children nodes
        if(isset($upd['value']['children'])) unset($upd['value']['children']);

        if (is_array($upd['value'])) {
            $upd['value'] = json_encode($upd['value'], JSON_UNESCAPED_UNICODE);
            $upd['data_type'] = 'json';
        } else {
            $upd['data_type'] = 'string';
        }

        $upd['revision'] = $upd['value'];

        $existed = false;
        if(_cv($params, 'id', 'nn')){
            $existed = $this->select(['value', 'id'])->where('id', $params['id'])->where('content_group', $upd['content_group'])->first();
        }

        if(!$existed){
            $existed = $this->select(['value', 'id'])->where('key', $upd['key'])->where('content_group', $upd['content_group'])->first();
        }

        if($existed){

            if(_cv($params, 'id', 'nn')){
                $existed['key'] = $upd['key'];
            }
//            $upd['revision'] = $tmp['value'];
            $existed['value'] = $upd['value'];
            $existed['revision'] = $existed['value'];
            $existed->save();

            return $existed->id;
        }


        $tmp = new OptionsModel();
        $tmp->key = $upd['key'];
        $tmp->value = $upd['value'];
        $tmp->content_group = $upd['content_group'];
        $tmp->data_type = $upd['data_type'];
        $tmp->revision = $upd['revision'];
        $tmp->save();
        return $tmp->id;

    }

    public function deleteOption($params = [])
    {
        if(!_cv($params, ['id'], 'nn'))return false;

        return DB::table($this->table)->where('id', $params['id'])->delete();

    }

    public function updateSortAndNesting( $params = [] ){
        if (paramsCheckFailed($params, ['id'])) return false; //, 'pid', 'sort'

        $upd = OptionsModel::find( $params['id'] );
        $value = json_decode($upd->value, 1);
//        p($value);
        if(isset($value['key'])) unset($value['key']);
        if(isset($value['id'])) unset($value['id']);
        if(isset($value['children'])) unset($value['children']);

        $revision = $value;
//        p($params);
        if(_cv($params, 'pid', 'num')){

            $value['pid'] = $params['pid'];
        }
        if(_cv($params, 'sort', 'num')){
            $value['sort'] = $params['sort'];
        }

        $upd->value = json_encode($value, JSON_UNESCAPED_UNICODE);
        $upd->revision = json_encode($revision, JSON_UNESCAPED_UNICODE);

        //        return false;
        return $upd->save();
    }


    /**
     * cleans value field from regular fields data
     * uses $fillable var and id field
     * @input array
     * @return array
     */
    private function cleanOptionsValue($params = [])
    {
        $fillables = array_flip($this->cleanFromFields);

        $ret = [];
        foreach ($params as $k => $v) {
            if ($k == 'value' || !isset($fillables[$k])) {
                $ret[$k] = $v;
            }
        }

        if (count($ret)==1 && isset($ret['value']))$ret = $ret['value'];

        return $ret;
    }

    private function decodeValues($data)
    {

        $data = _toArray($data);

        if (!isset($data['data_type']) || !isset($data['value'])) {
            return false;
        }

        if ($data['data_type'] != 'json') {
            return $data;
        }

        $tmp = json_decode($data['value'], 1);
        $tmp['id'] = $data['id'];
        $tmp['key'] = $data['key'];

        return $tmp;
    }

    public function findSecondaryContentTemplateConfiguration($params=[]){
        if (paramsCheckFailed($params, ['id'])) return false;
        $currentMenu = $this->getOne(['id'=>$params['id']]);
        if(_cv($currentMenu, 'secondary_content'))return $currentMenu;
        if(!_cv($currentMenu, 'id', 'nn'))return false;

        return $this->findSecondaryContentTemplateConfiguration(['id'=>$currentMenu['pid']]);

    }
}
