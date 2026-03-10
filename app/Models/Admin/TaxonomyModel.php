<?php

namespace App\Models\Admin;

use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;

class TaxonomyModel extends Model
{
    //
    protected $table = 'taxonomy';
    protected $metaTable = 'taxonomy_meta';
    public $timestamps = true;
    protected $error = false;
    protected $meta;

    //
    protected $allAttributes = [
        'id',
        'pid',
        'taxonomy',
        'count',
        'sort',
        'slug',
        'created_at',
        'updated_at',
    ];
    protected $fillable = [
        'pid',
    ];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
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

    public function getOne($params = [])
    {
        $params['limit'] = 1;
        $res = $this->getList($params);

        if(isset($res[0]))return $res[0];

        return false;

    }

    public function getList($params = [])
    {
        if(isset($params['id']) && !isset($params['taxonomy']))$params['taxonomy'] = $this->getTaxonomyById(_cv($params,['id']));

        DB::enableQueryLog();
//p($params);
        $conf = config('adminpanel.taxonomy.'._cv($params, ['taxonomy']));
        $locales = config('app.locales');

        $locale = requestLan();
        $translate = _cv($params, 'translate')?$locale:false;
//        $translate = $locale; /// for testing


        if(!$conf)return [];

        if(_cv($params, 'select')){
            $selectPart = $params['select'];
        }else{
            $selectPart[] = "{$this->table}.*";
        }

        $qr =  DB::table($this->table);
//p($conf);


        //// meta table fields
        if(_cv($conf,['fields'])){

            foreach ($conf['fields'] as $k=>$v){

                if(_cv($v, 'translate')){
                    foreach ($locales as $kk=>$vv){
                        if($translate && $translate!=$kk)continue;

                        $metaTable = "{$this->metaTable}_{$k}_{$kk}";
                        $selectKey = $metaKey = "{$k}_{$kk}";

                        if($translate)$selectKey = $k;


                        $qr->leftJoin("{$this->metaTable} as {$metaTable}", function($join) use ($k, $v, $metaKey, $metaTable){
                            $join->on("{$metaTable}.data_id", "=", "{$this->table}.id")->where("{$metaTable}.key", $metaKey);
                        });

                        $selectPart[] = "{$metaTable}.val as {$selectKey}";
                    }
                }else{
                    $metaTable = "{$this->metaTable}_{$k}";
                    $selectKey = $metaKey = "{$k}";

                    $qr->leftJoin("{$this->metaTable} as {$metaTable}", function($join) use ($k, $v, $metaKey, $metaTable){
                        $join->on("{$metaTable}.data_id", "=", "{$this->table}.id")->where("{$metaTable}.key", $metaKey);
                    });

                    $selectPart[] = "{$metaTable}.val as {$selectKey}";
                }
            }

        }


        $qr->select($selectPart);

        if(_cv($params, 'taxonomy')) $qr->where('taxonomy', $params['taxonomy']);
        if(_cv($params, 'pid', 'nn')) $qr->where('pid', $params['pid']);

        if(_cv($params, 'id', 'nn'))$params['id'] = [$params['id']];
        if(_cv($params, 'id', 'ar')) $qr->whereIn($this->table.'.id', $params['id']);

        if(_cv($params, 'sort')) $qr->where('sort', $params['sort']);
        if(_cv($params, 'count')) $qr->where('count', $params['count']);

        if(_cv($params, 'searchLike')){
            $qr->leftJoin("taxonomy_meta", "taxonomy_meta.data_id", '=', $this->table.'.id');
            $qr->where("taxonomy_meta.val", 'like', '%'.$params['searchLike'].'%');
        }

        if(!_cv($params, 'id', 'nn') && _cv($params, 'getByMeta')){

            foreach ($params['getByMeta'] as $meta_k => $meta_v){
                if(empty($meta_v))continue;
                if(!is_array($meta_v))$meta_v = [$meta_v];

                $qr->leftJoin("taxonomy_meta as taxonomy_meta_{$meta_k}", "taxonomy_meta_{$meta_k}.data_id", '=', $this->table.'.id');
                $qr->where("taxonomy_meta_{$meta_k}.key", '=', $meta_k);
                $qr->whereIn("taxonomy_meta_{$meta_k}.val", $meta_v);

            }

        }

        if(_cv($params, 'limit')) $qr->limit($params['limit']);

        $qr->orderBy('sort', 'asc');



        $list = $qr->get();
        $list = _psql(_toArray($list));

//        p(DB::getQueryLog());

        foreach ($list as $k => $v) {
            $list[$k] = $this->getMediaData($v, $v['taxonomy'], $translate);
        }

        /// return only some column
        if(_cv($params, 'returnCol')){
            if(!isset($list[0][$params['returnCol']]))return [];
            return array_column($list, $params['returnCol']);
        }

        return $list;
    }

    public function getRelatedContentCounts($params = [])
    {
        DB::enableQueryLog();
        if(!_cv($params, 'taxonomy'))return [];

        if(!is_array($params['taxonomy'])){
            $params['taxonomy'] = [$params['taxonomy']];
        }

        $contentType = false;
        if(_cv($params, 'contentType')){


            if(!is_array($params['contentType'])){
                $contentType = [$params['contentType']];
            }
        }

        $qr =  DB::table($this->table)->selectRaw("taxonomy.id,
        taxonomy.slug,
        count(pages_meta.id) as count,
        concat('[',GROUP_CONCAT(DISTINCT concat('\"', pages.content_type, '\"')), ']') as content_types");

        $qr->leftJoin("pages_meta", function($join) use ($params){
            $join->whereIn("pages_meta.key", $params['taxonomy']);
            $join->whereRaw("pages_meta.val like concat('%', taxonomy.id, '%')");
        });

        $qr->leftJoin("pages", function($join) use ($params){
            $join->on("pages.id", 'pages_meta.data_id');
            $join->where("pages.page_status", 1);
        });

        if($contentType){
            $qr->whereIn('pages.content_type', $contentType);
        }

        $qr->whereIn('taxonomy.taxonomy', $params['taxonomy']);

        $qr->orderBy("{$this->table}.sort", 'asc');
        $qr->groupBy(['taxonomy.id']);



        $list = $qr->get();
        $list = _psql(_toArray($list));

        $query = DB::getQueryLog();

        $ret = [];

//        p($query);

        foreach ($list as $v)$ret[$v['id']] = $v;

        return $ret;
    }

    public function upd($data = [])
    {
        $separatedData = separateTableMetaFieldsData($data, $this->allAttributes);

        if ($this->error->getMessageBag()->count()) return false;

        /// validate page table regular data
        $validator = Validator::make($separatedData['data'],
            [
                'pid' => 'integer',
                'taxonomy' => 'required|string|min:2',
                'sort' => 'integer',
                'count' => 'integer',
                'slug' => 'required',
            ]
        );

        if ($validator->fails()){
            return $validator->messages()->all();
        }

        /// update or ....
        if(_cv($separatedData, 'data.id', 'num')){
            $upd = TaxonomyModel::find( _cv($separatedData, 'data.id', 'num') );
        }

        // .... or create
        if(!isset($upd->id)){
            $upd = new TaxonomyModel();
        }

        /// defaults
        $upd['sort'] = 0;
        $upd['pid'] = 0;
        $upd['count'] = 0;

        foreach($this->allAttributes as $v){
            if(_cv($separatedData, ['data', $v])) $upd->{$v} = $separatedData['data'][$v];
        }

        $upd->save();


        if ($this->error->getMessageBag()->count()) return false;

        if (!isset($data['id'])) $data['id'] = $upd->id;

        /** update meta fields */
        if (isset($separatedData['meta']))
        {
            $separatedData['meta'] = $this->prepareMediaData($separatedData['meta'], $data['taxonomy']);

            $this->meta->upd(['meta' => $separatedData['meta'], 'data_id' => $data['id'], 'table' => 'taxonomy_meta']);
        }

        return $data['id'];
    }

    public function updSort($data = [])
    {

        foreach ($data as $v){
//            $upd = TaxonomyModel::find( _cv($data, 'id', 'num') );
            if(!_cv($v,'id', 'nn'))continue;
            $upd = [];

            if(_cv($v,'pid', 'num')>=0){
                $upd['pid'] = $v['pid'];
            }

            if(_cv($v,'sort', 'num')>=0){
                $upd['sort'] = $v['sort'];
            }

            DB::table($this->table)
                ->where('id', $v['id'])
                ->update($upd);
        }

        if ($this->error->getMessageBag()->count()) return false;

        return true;
    }

    public function deleteTerm($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;
        $upd['pid'] = 0;
        TaxonomyModel::where('pid', $data['id'])->update($upd);
        TaxonomyModel::where('id', $data['id'])->delete();
        return $data['id'];
    }

    public function extractTranslated($data = [], $taxonomy = ''){

        /// if $taxonomy not set return data
        if(!$taxonomy)return $data;

        /// if content type not found in config
        $taxonomySettings = config('adminpanel.taxonomy.'.$taxonomy);
        if(!$taxonomySettings)return $data;

        //        $locale = getLocales(1);
        $locale = requestLan(); //config('app.locale');
        $locales = config('app.locales');

        foreach ($taxonomySettings['fields'] as $k=>$v){
            if(!$v['translate'])continue;

            /// define empty translation
            $tmp = '';
            foreach ($locales as $kk=>$vv){
                /// if translation exists assign to data array without locale suffix
                if($locale == $kk && isset($data["{$k}_{$kk}"]))$data[$k] = $data["{$k}_{$kk}"];

                /// if found translations with locale suffix unset them
                if(isset($data["{$k}_{$kk}"])){
                    /// cache translation
                    $tmp = $data["{$k}_{$kk}"];
                    unset($data["{$k}_{$kk}"]);
                }
            }
            /// if locale translation not set set chached translation
            if(!isset($data[$k]))$data[$k] = $tmp;

        }

        return $data;
    }

    /** prepares media ids for inserting
     * get only ids array from media data array
     */
    public function prepareMediaData($metaData = [], $taxonomy = ''){
        $s = "adminpanel.taxonomy.{$taxonomy}";
        $r = config($s);

        $locales = config('app.locales');


        foreach ($r['fields'] as $k=>$v){

            if($v['type'] != 'media' && $v['type'] != 'file' )continue;

            if($v['translate'] == 1){

                foreach ($locales as $kk=>$vv){

                    if(isset($metaData["{$k}_{$kk}"])){
//
                        $metaData["{$k}_{$kk}"] = is_array($metaData["{$k}_{$kk}"])?array_column($metaData["{$k}_{$kk}"], 'id'):[];
//                        $metaData["{$k}_{$kk}"] = array_keys($metaData["{$k}_{$kk}"]);
                    }
                }

            }else if(isset($metaData[$k])){
                /// if field is not translatable use field key
                $metaData[$k] = is_array($metaData[$k])?array_column($metaData[$k], 'id'):[];
            }

        }

        return $metaData;
    }

    public function getMediaData($metaData = [], $taxonomy = '', $translate=''){
        $s = "adminpanel.taxonomy.{$taxonomy}";
        $r = config($s);
//        p($r);

        if(!isset($r['fields']))return $metaData;
        $locales = config('app.locales');
        $mediaModel = new MediaModel();
        foreach ($r['fields'] as $k=>$v){
            if($v['type'] != 'media' && $v['type'] != 'file')continue;

            if($translate && isset($metaData[$k])){
                $metaData[$k] = $mediaModel->getList([ 'ids'=>$metaData[$k] ]);
            }else if($v['translate'] == 1){
                foreach ($locales as $kk=>$vv){

                    if(isset($metaData["{$k}_{$kk}"]) && is_array($metaData["{$k}_{$kk}"]) && count($metaData["{$k}_{$kk}"])) {
                        $metaData["{$k}_{$kk}"] = $mediaModel->getList(['ids'=>$metaData["{$k}_{$kk}"]]);
                    }
                }
            }else if(isset($metaData[$k]) && is_array($metaData[$k]) && count($metaData[$k])){
                /// if field is not translatable use field key
                $metaData[$k] = $mediaModel->getList([ 'ids'=>$metaData[$k] ]);
            }
        }

        return json_decode(json_encode($metaData),1);
    }

    public function getTaxonomyByTermId($params = []){
        $ret = DB::table($this->table)->select('taxonomy')->find($params['id']);

        if(!$ret)return false;
        return $ret->taxonomy;

    }

    public function getTaxonomyById($id = []){
        $idd = false;
        if(is_numeric($id)){
            $idd = $id;
        }elseif (isset($id[0]) && is_numeric($id[0])){
            $idd = $id[0];
        }

        if(!$idd)return false;

        $res = DB::select("select taxonomy from {$this->table} where id = {$idd} limit 1");

        if(isset($res[0]->taxonomy))return $res[0]->taxonomy;

        return false;

    }

    public function getContentCounts($taxonomy = '', $contentType = ''){
        if(empty($contentType) || empty($taxonomy) )return [];

        $qr = "
        SELECT
	taxonomy.id, taxonomy.slug,
	count( modules_taxonomy_relations.id ) AS `count`
FROM
	taxonomy
	LEFT JOIN modules_taxonomy_relations ON modules_taxonomy_relations.taxonomy_id = taxonomy.id
	AND modules_taxonomy_relations.`table` = 'pages'
	LEFT JOIN pages ON pages.id = modules_taxonomy_relations.data_id
	AND pages.content_type = '{$contentType}'
WHERE
	taxonomy.taxonomy = '{$taxonomy}'
GROUP BY
	taxonomy.id
";
        $res = DB::select($qr);
        $list = _psql(_toArray($res));

        $list = array_combine(array_column($list, 'id'), $list);


        return $list;

    }

}
