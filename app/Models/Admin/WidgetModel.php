<?php

namespace App\Models\Admin;

use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MongoDB\Driver\Session;
use \Validator;
use Illuminate\Support\Facades\DB;

class WidgetModel extends Model
{
    protected $table = 'widgets';
    public $timestamps = true;
    protected $error = false;

    //
    protected $allAttributes = [
        'id',
        'name',
        'form_structure',
        'content',
        'settings',
        'created_at',
        'updated_at',
    ];
    protected $fillable = [
        'name',
        'form_structure',
        'content',
        'settings',
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
    }

    public function getWidgetTitlesList(){
        $widgets = DB::table($this->table)->select(['id', 'name'])->orderBy('name')->get();

        return _toArray($widgets);
    }

    public function getProduction($params = []) {
        $ret = $this->getList($params);
        $rett = [];
    
        foreach ($ret as $k => $v) {
            $widgetName = $v['name'];
            
            foreach ($v['form_structure'] as $kkk => $formField) {
                $fieldName = $formField['name'];
                if (!$fieldName) continue;
    
                foreach ($v['content'] as $locale => $content) {
                    if (isset($content[$kkk])) {
                        $rett[$widgetName][$locale][$fieldName] = $content[$kkk];
                    }
                }
            }

            if (_cv($params, 'locale')) {
                $rett[$widgetName] = _cv($rett, [$widgetName, $params['locale']]);
            }
        }
    
        return $rett;
    }

    public function getOne($params = [])
    {

        $params['limit'] = 1;
        $ret = $this->getList($params);

        return _cv($ret, '0');
    }

    public function getList($params = [])
    {


//        DB::enableQueryLog();

        $qr =  DB::table($this->table)->select(['id', 'name', 'form_structure', 'content', 'settings', 'updated_at']);

        if(_cv($params, 'id')) $qr->where('id', $params['id']);
        if(_cv($params, 'ids')) $qr->whereIn('id', $params['ids']);
        if(_cv($params, 'name')) $qr->whereIn('name', $params['name']);
        if(_cv($params, 'content_type')) $qr->where('content_type', $params['content_type']);

        $qr->limit(200);
        $qr->orderBy('name', 'asc');
        $qr->orderBy('id', 'asc');

        $list = $qr->get()->toArray();
        $list = _psql(_toArray($list));

//        p(DB::getQueryLog());

        foreach ($list as $k => $v){

            $list[$k] = $this->getMediaData($v);

//            $list[$k] = $this->extractTranslated($list[$k]);
        }

        return $list;
    }

    public function upd($data = [])
    {

        if ($this->error->getMessageBag()->count()) return false;

        /// validate page table regular data
//        p($separatedData['data']);
        $validator = Validator::make($data,
            [
                'name' => 'required|string',
                'form_structure' => 'array',
                'content' => 'array',
                'settings' => 'array',
            ]
        );

        if ($validator->fails()) {
            return $validator->messages()->all();
        }

        $upd = false;
        /// update or ....
        if(_cv($data, 'id', 'num')){
            $upd = WidgetModel::find( $data['id'] );
        }

        // .... or create
        if(!isset($upd->id)){
            $upd = new WidgetModel();
        }

        $data = $this->extractMediaIds($data);

        $upd->name = _cv($data, 'name');
        $upd->form_structure = _psqlupd( _cv($data, 'form_structure') );
        $upd->content = _psqlupd( _cv($data, 'content') );
        $upd->settings = _psqlupd( _cv($data, 'settings') );

        $upd->save();

        if ($this->error->getMessageBag()->count()) return false;

        return $upd->id;
    }

    public function deleteOne($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;

        /// delete widget from db
        DB::table($this->table)->where('id', $data['id'])->delete();

        return $data['id'];

    }

    public function getMediaData($data = [], $contentType = ''){
//        p($data);

        if(!_cv($data, 'settings.allImages', 'ar'))return $data;

        $mediaModel = new MediaModel();
        $medias = $mediaModel->getList(['ids'=>$data['settings']['allImages'], 'idAsKey'=>1 ]);

        foreach ($data['content'] as $k=>$v){
            foreach ($v as $kk=>$vv){
                if(!is_array($vv))continue;
                foreach ($vv as $kkk=>$vvv){
//                    if(is_numeric($vvv) && _cv($medias, [$vvv]))$tmp[] = $medias[$vvv];
                    if(is_numeric($vvv) && _cv($medias, [$vvv]))$data['content'][$k][$kk][$kkk] = $medias[$vvv];
                }
            }
        }

        return $data;
    }

    public function extractMediaIds($data=[]){
        if(!_cv($data, ['content'], 'ar'))return $data;

//        p($data['content']);

        $allImages = [];
        foreach ($data['content'] as $k=>$v){
            foreach ($v as $kk=>$vv){
                if( _cv($data, ['form_structure', $kk, 'type']) != 'image' ) continue;

                /// if images not uploaded yet return empty array to variable
                if(!_cv($data['content'][$k], [$kk], 'ar')){
                    $data['content'][$k][$kk] = [];
                    break;
                }

                /// extract image ids
                /// if image is not numeric unset variable
                $tmp = [];
                foreach ($data['content'][$k][$kk] as $kkk=>$vvv){

                    if ( _cv($vvv, ['id'], 'nn') ){
                        $tmp[] = $vvv['id'];
                        $allImages[] = $vvv['id'];
                    }
                }
                $data['content'][$k][$kk] = $tmp;
            }
        }

        $data['settings']['allImages'] = $allImages;

        return $data;

    }


    public function getWidgetByName($params=[]){

        $rett=[];
        $widget_content = $this->getOne(['name' => [$params['name']]]);

        foreach ($widget_content['content'] as $kk=>$vv){
            foreach ($vv as $kkk=>$vvv){
                foreach ($widget_content['form_structure'] as $key=>$val) {
                    if($kkk == $val['unicId']){
                        $fieldName = $val['name'];
                        if(!$fieldName)continue;
                    }
                }

                $rett[$kk][$fieldName] = $vvv;
            }
        }

        $ret = [];
        foreach ($widget_content['form_structure'] as $k=>$v){
            foreach ($widget_content['content'] as $kk=>$vv){
                if(!isset($vv[$k]))continue;

                $ret[$kk][$v['name']] = $vv[$k];

            }
        }

        if(isset($params['lang'])){
            return $ret[$params['lang']];
        }

        return $ret;
    }


}
