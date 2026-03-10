<?php

namespace App\Models\Admin;

use \Validator;
use Illuminate\Support\Str;
use App\Models\Admin\MetaModel;
use App\Models\Media\MediaModel;
use App\Models\Admin\OptionsModel;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\ContentLogModel;
use App\Models\Admin\SmartTableModel;
use App\Http\Controllers\Api\MetaTagsGenerator;

class PageModel extends SmartTableModel
{
    protected $table = 'pages';
    protected $metaTable = 'pages_meta';
    protected $taxonomyRelationTable = 'modules_taxonomy_relations';
    public $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs;


    //
    protected $allAttributes = [
        'id',
        'user_id',
        'pid',
        'sort',
        'created_at',
        'updated_at',
        'date',
        'status',
        'content_type',
        'slug',
        'alert',
    ];
    protected $fillable = [
        'user_id',
        'date',
        'slug',
        'content_type',
        'pid',
        'status',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'content_type',
        'slug',
    ];

    function __construct()
    {
        parent::__construct();
        $this->meta = new MetaModel($this->metaTable);
        $this->error = Validator::make([], []);
        /**
        $v->errors()->add('some_field', 'some_translated_error_key');
        $fails = $v->fails(); // false!!! why???
        $failedMessages = $v->failed(); // 0 failed messages!!! why???
        */
    }

    public function getAlerts()
    {
        $res = $this->getList(['meta_keys' => metaFieldsLocales('title'), 'page_status' => [1, 0], 'alert' => 1, 'raw' => 1]);

        return $res;
    }
    public function getPages($params = []){
        if(_cv($params, 'content_type')){ $params['contentType'] = $params['content_type']; }
        if(_cv($params, 'contentType')){
            $this->fieldConfigs = 'adminpanel.content_types.'.$params['contentType'];
            $params['whereRaw'] = [
                "content_type = '".$params['contentType']."'",
            ];
        }
        $res = $this->getList($params);
        return $res;
    }
    public function getPage($params = []){
        $this->fieldConfigs = 'adminpanel.content_types.'.$params['contentType'];

        $res = $this->getOne($params);
        return $res;
    }
    public function updPage($params = []){
        if(_cv($params, 'content_type')) $this->fieldConfigs = 'adminpanel.content_types.'.$params['content_type'];
        if(_cv($params, 'relations')){
            $params['xx']['relations'] = $params['relations'];
        }
        if(!_cv($params, 'slug')) $params['slug'] = $this->generateSlug($params);
        if(!_cv($params, 'date') || _cv($params, 'date') == '0000-00-00 00:00:00') $params['date'] = date('Y-m-d H:i:s');

        $res = $this->updItem($params);
        return $res;
    }
    public function deletePage($params = []){
        if(_cv($params, 'id')) $id = $params['id'];
        $res = DB::table($this->table)->where('id', $id)->delete();
        return $res?true:false;
    }
    public function generateSlug($data = [])
    {
        $generatedSlug = false;

        /// find contentTypeSettings
        $contentTypeSettings = config($this->fieldConfigs);
        $locales = config('app.locales');

        /// if contentTypeSettings does not exists return false
        if (!$contentTypeSettings) return false;

        //// searches and generates slug field value
        $useForslugFields = config('adminpanel.use_for_slug_fields');
        if (_cv($contentTypeSettings, 'slug_field')) array_unshift($useForslugFields, $contentTypeSettings['slug_field']);

        foreach ($useForslugFields as $v) {
            if ($generatedSlug) break;
            if (_cv($data, "xx.{$v}")) {
                $generatedSlug = $data['xx']["{$v}"];
                break;
            }

            foreach ($locales as $kk => $vv) {
                if (_cv($data, [$kk, $v])) {
                    $generatedSlug = $data[$kk][$v];
                    break;
                }
            }
        }

        return Str::slug($generatedSlug, '-');
    }
    public function getContent($params=[])
    {

        $contentTypeSettings = [];

        if(_cv($params, 'page', 'nn') || _cv($params, 'page', 'ar')){
            if(_cv($params, 'page', 'nn'))$params['page'] = [$params['page']];

            foreach ($params['page'] as $k=>$v){
                if(!is_numeric($v)) unset($params['page'][$k]);
            }
            $params['id'] = $params['page'];
            unset($params['page']);
        }

        if(_cv($params, 'contentType')){
            if(!_cv($params, 'contentType', 'ar'))$params['contentType'] = [$params['contentType']];

            $contentTypes = implode("','", $params['contentType']);
            $params['whereRaw'][] = "content_type in ('{$contentTypes}')";

            if(count($params['contentType']) > 1){
                foreach($params['contentType'] as $contentType){
                    $this->fieldConfigs[] = config("adminpanel.content_types.{$contentType}");
                }
            } else {
                $this->fieldConfigs = 'adminpanel.content_types.'.$params['contentType'][0];
                $contentTypeSettings = config("adminpanel.content_types.{$params['contentType'][0]}");
            }
        }

        /// prepare sort params
        $params['orderField'] = _cv($contentTypeSettings, 'orderField')?$contentTypeSettings['orderField']:'date';

        if(_cv($params, 'page_order')){
            $params['orderDirection'] = $params['page_order'];
        }elseif (_cv($contentTypeSettings, 'orderDirection')){
            $params['orderDirection'] = $contentTypeSettings['orderDirection'];
        }elseif ($params['orderField']=='sort'){
            $params['orderDirection'] = 'asc';
        }else{
            $params['orderDirection'] = 'desc';
        }

        /// improve terms param
        if(!_cv($params, 'term') && _cv($params, 'terms'))$params['term'] = $params['terms'];
        if(_cv($params, 'term') && !is_array($params['term']))$params['term'] = [$params['term']];

        /// search by exact date or between two dates
        if( _cv($params, 'searchDate.0') && !_cv($params, 'searchDate.1')){
            $params['whereRaw'][] = "date like '".$params['searchDate'][0]."%'";
        }else if( _cv($params, 'searchDate.0') && _cv($params, 'searchDate.1')){
            $params['whereRaw'][] = "date >= '".$params['searchDate'][0]."' and date <= '".$params['searchDate'][1]."'";
        }

        if(_cv($params, 'exclude')){
            $params['whereRaw'][] = "pages.id != ".$params['exclude']." ";
        }

        if(_cv($params, 'ids')){
            $ids = implode(", ", $params['ids']);
            $params['whereRaw'][] = "pages.id in ($ids)";
        }

        if(isset($params['contentType']) && $params['contentType'][0] == 'careers'){
            $params['start_date'] = date('Y-m-d');
            $params['end_date'] = date('Y-m-d');
        }

        $params['status'] = 'published';
        $params['relate']['taxonomy'] = true;

        /// prepare page limits and offset
        if(_cv($params, 'page_count', 'nn')) $params['limit'] = $params['page_count'];
        if(_cv($params, 'pageNumber', 'nn')) $params['page'] = $params['pageNumber'];
//p($params);
        $returnData = $this->getList($params);

        return $returnData;
    }
    public function getContentTypes($params = [])
    {
        $ret = config('adminpanel.content_types');
        $options = new OptionsModel();

        $singleRoutes = $options->getListBy(['content_group_locate' => "content_type_settings_"]);

        foreach ($singleRoutes as $k => $v) {
            $key = str_replace('content_type_settings_', '', $v['content_group']);
            $ret[$key]['settings'][$v['key']] = $v['value'];
        }



        if(_cv($params, 'exclude', 'ar')){
            /// exclude fields
            foreach ($params['exclude'] as $k=>$v)
                foreach ($ret as $kk=>$vv){
                    if(isset($ret[$kk][$v]))unset($ret[$kk][$v]);
                }
        }

        return $ret;
    }
    public function updListSort($list = [], $listParams = [])
    {
        return $this->updSort($list, $listParams);
    }
}
