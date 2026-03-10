<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class SmartTableModel extends Model
{
    protected $table = '';
    protected $metaTable = '';
    protected $taxonomyRelationTable = '';
    protected $rules = [];
    public  $timestamps = true;
    protected $error = false;
    protected $meta;
    protected $fieldConfigs;

    ///
///   protected $allAttributes = [];
    protected $fillable = [];
    protected $guarded = [];

    public function getOne($params = [])
    {
        $params['limit'] = 1;

        $res = $this -> getList($params);

        if (isset($res['list'][0])) return $res['list'][0];

        return [];
    }

    public function getList($params = [])
    {
//        p($params);
//        DB::select("SET group_concat_max_len = 100000000");
        if(_cv($params, 'fieldConfigs'))$this->fieldConfigs = $params['fieldConfigs'];

        if(is_array($this->fieldConfigs) && isset($this->fieldConfigs[0])){
            $fields = $this->fieldConfigs[0];
            foreach($this->fieldConfigs as $value){
                foreach($value['fields'] as $key => $field){
                    $fields['fields'][$key] = $field;
                }
            }
        }else if(_cv($this->fieldConfigs, 'fields')){
            $fields = $this->fieldConfigs;
        } else {
            $fields = config($this->fieldConfigs);
        }

        $translate = requestLan(_cv($params, 'translate'));
        $enableOrdering = true;
        $selectPart = [];

        /// join with tables
        $tableJoinSelect = [];
        $tableJoinSelectFilters = [];
        $tableJoinOn = [];

        if(_cv($fields, ['join'], 'ar')) {
            foreach ($fields['join'] as $k=>$v){
                if(_cv($v, 'tablePrefix')){
                    $tableName = $v['tablePrefix'];
                } else {
                    $tableName = $v['joinTable'];
                }

                if(isset($v['select'])){
                    foreach ($v['select'] as $kk=>$vv){

                        $whereQuery = isset($vv['where'])?$vv['where']:$vv;

                        $selectQuery = isset($vv['select'])?$vv['select']:"joinedTable_{$tableName}.{$kk}";
                        $selectPart[] = "{$selectQuery} as {$tableName}_{$kk}";
                        $tableJoinSelectFilters["{$v['joinTable']}_{$kk}"] = ['filterType'=>$whereQuery, 'table'=>"joinedTable_{$tableName}", 'fieldAs'=>"{$v['joinTable']}_{$kk}", 'field'=>$kk];
                    }
                }
                $tableJoinOn[] = [ 'joinTable'=>$v['joinTable'], 'joinField'=>$v['joinField'], 'joinOn'=>$v['joinOn'], 'tablePrefix'=>$tableName, 'sameStatus'=>$v['sameStatus'] ?? true, 'whereRaw'=>_cv($v, 'whereRaw')];
            }
        }

/// start time tracking
        $time_start = microtime(true);

        $returnData['listCount'] = 0;
        $returnData['list'] = [];
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;
        if(_cv($params, ['sortDirection']) && !_cv($params, ['orderDirection']))$params['orderDirection'] = $params['sortDirection'];
        if(_cv($params, ['sortField']) && !_cv($params, ['orderField']))$params['orderField'] = $params['sortField'];
        ///sortDirection
        DB::enableQueryLog();

        $qr = DB::table($this -> table);


        //// meta table fields
        if($this->metaTable){
            /// if filter by some meta field
            if(_cv($fields,['fields']) ){

                foreach ($fields['fields'] as $k=>$v){
                    if(!isset($params[$k]) && !isset($v['whereRaw']))continue;

                    $metaTableName = "meta_{$k}";
                    $qr->leftJoin("{$this->metaTable} as {$metaTableName}", function($join) use ($k, $v, $translate, $metaTableName){
                        if(isset($v['translate']) && $v['translate']==1){
                            $join->on("{$metaTableName}.table_id", "=", "{$this->table}.id")->where("{$metaTableName}.key", $k)->where("{$metaTableName}.lan", $translate)->where("{$metaTableName}.table", $this->table);
                        }else{
                            $join->on("{$metaTableName}.table_id", "=", "{$this->table}.id")->where("{$metaTableName}.key", $k)->where("{$metaTableName}.lan", 'xx')->where("{$metaTableName}.table", $this->table);
                        }
                    });

                    if(isset($v['whereRaw'])){
                        $qr->whereRaw($v['whereRaw']);
                    }

                    if(isset($v['dbFilter']) && isset($params[$k])){
                        if($v['dbFilter'] == 'whereIn'){
                            if(!is_array($params[$k]))$params[$k] = [$params[$k]];
                            $qr -> whereIn("{$metaTableName}.val", $params[$k]);

                        }elseif ($v['dbFilter'] == 'like'){
                            $qr -> where("{$metaTableName}.val",  'like', "%{$params[$k]}%");

                        }elseif ($v['dbFilter'] == 'where'){
                            $qr -> where("{$metaTableName}.val",  $params[$k]);

                        }elseif ($v['dbFilter'] == 'range'){
                            if(!is_array($params[$k]))$params[$k] = [$params[$k]];

                            if(count($params[$k])>=2){
                                $qr -> whereBetween("{$metaTableName}.val",  $params[$k]);
                            }else{
                                $qr -> where("{$metaTableName}.val",  $params[$k]);
                            }
                        }elseif ($v['dbFilter'] == '>'){
                            $qr -> where("{$metaTableName}.val", '>', $params[$k]);
                        }elseif ($v['dbFilter'] == '<'){
                            $qr -> where("{$metaTableName}.val", '<', $params[$k]);
                        }
                    }

                }
            }


//            $selectPart[] = 'GROUP_CONCAT(CONCAT('.$this->metaTable.'.key, "----GROUP_CONCATED_KEY_VAL_SEPARATOR----", '.$this->metaTable.'.val, "----GROUP_CONCATED_KEY_VAL_SEPARATOR----", '.$this->metaTable.'.lan , "----GROUP_CONCATED_FIELD_END----" ) SEPARATOR "") as metas';

            /// get only exact meta fields if needed
            $metaKeys = _cv($params, 'meta_keys', 'ar');

            $ttmp = '';
            if (is_array($metaKeys)) {
                $ttmp = "'".implode("','", $metaKeys)."'";
                $ttmp = " and pm.`key` in ({$ttmp}) ";
            }

            //// sub query for meta content
//            $selectPart[] = '
//            (select
//            GROUP_CONCAT(CONCAT(pm.key, "----GROUP_CONCATED_KEY_VAL_SEPARATOR----", pm.val, "----GROUP_CONCATED_KEY_VAL_SEPARATOR----", pm.lan , "----GROUP_CONCATED_FIELD_END----" ) SEPARATOR "")
//            FROM '.$this->metaTable.' pm
//            WHERE pm.table_id = '.$this -> table.'.id '.$ttmp.' and pm.table = "'.$this->table.'"
//            ) AS metas
//            ';

            $metasQuery = DB::table($this->metaTable . ' as pm')
                ->selectRaw('pm.table_id, GROUP_CONCAT(CONCAT(pm.key, "----GROUP_CONCATED_KEY_VAL_SEPARATOR----", pm.val, "----GROUP_CONCATED_KEY_VAL_SEPARATOR----", pm.lan, "----GROUP_CONCATED_FIELD_END----") SEPARATOR "") AS metas')
                ->where('pm.table', $this->table)
                ->groupBy('pm.table_id');

            $qr->leftJoinSub($metasQuery, 'metas', function ($join) {
                $join->on('metas.table_id', '=', $this->table . '.id');
            });

            $selectPart[] = 'metas.metas';

        }

//        p($params);
        /// get related taxonomies
        if(_cv($fields, 'taxonomy', 'ar') || _cv($params, 'relate.taxonomy')){

            $qr -> leftJoin("{$this -> taxonomyRelationTable} as taxonomy_relation", function($join) use ($params){
                $join -> on("taxonomy_relation.data_id", '=', $this -> table . '.id')->where("taxonomy_relation.table", $this -> table);
            })->leftJoin("taxonomy", "taxonomy.id", '=', "taxonomy_relation.taxonomy_id");

            /// for older versions; make taxonomies = taxonomy
            if(!_cv($params, 'taxonomy', 'ar') && _cv($params, 'taxonomies', 'ar'))$params['taxonomy'] = $params['taxonomies'];
            /// filter by related taxonomies
            if (_cv($params, 'taxonomy', 'ar'))
            {
                foreach ($params['taxonomy'] as $attrKey => $attrValues)
                {
                    if(count($params['taxonomy'][$attrKey])==0)continue;

                    $qr -> leftJoin("{$this -> taxonomyRelationTable} as {$this -> taxonomyRelationTable}_{$attrKey}", function($join) use ($params, $attrKey){
                        $join -> on("{$this -> taxonomyRelationTable}_{$attrKey}.data_id", '=', "{$this -> table}.id")->where( "{$this -> taxonomyRelationTable}_{$attrKey}.table", $this -> table );
                    }) -> whereIn("{$this -> taxonomyRelationTable}_{$attrKey}.taxonomy_id", $attrValues);

                }
            }

            if (_cv($params, 'taxonomies_or', 'ar'))
            {

                $tmpTaxonomiesOrIds = [];
                foreach ($params['taxonomies_or'] as $attrKey => $attrValues)
                {
                    if(count($params['taxonomies_or'][$attrKey])==0)continue;

                    $tmpTaxonomiesOrIds = array_merge($tmpTaxonomiesOrIds, $attrValues);
                }
                $qr -> leftJoin("{$this -> taxonomyRelationTable} as taxonomy_relation_or", function($join) use ($params){
                    $join -> on("taxonomy_relation_or.data_id", '=', $this -> table . '.id')->where("taxonomy_relation_or.table", $this -> table);
                }) -> whereIn("taxonomy_relation_or.taxonomy_id", $tmpTaxonomiesOrIds);

            }

            $selectPart[] = 'CONCAT("{", GROUP_CONCAT(DISTINCT CONCAT("\"", `taxonomy`.`id`,"\":","\"",`taxonomy`.`taxonomy`,"\"")), "}") AS `taxonomy`';
        }

        if(_cv($fields, 'relations', 'ar')){
            foreach ($fields['relations'] as $k=>$v){

                $qr -> leftJoin(DB::raw( "
                        ( SELECT {$v['id']}, CONCAT(\"[\", GROUP_CONCAT(distinct relation_{$v['module']}.{$v['data_id']}), \"]\") AS `relation_{$v['module']}`
                            FROM {$v['table']} as relation_{$v['module']} WHERE `table` = '{$v['module']}' GROUP BY {$v['id']} ) as relation_{$v['module']}" ),
                    "relation_{$v['module']}.{$v['id']}",
                    "=",
                    "{$this -> table}.id");

                $selectPart[] = "relation_{$v['module']}";

            }
        }

        if(_cv($fields,['customSelectFields'], 'ar')){
//            customSelectFields
            foreach ($fields['customSelectFields'] as $k=>$v){
                $selectPart[] = $v;
            }

        }

        $selectPart[] = _cv($params, ['regularFieldsSelect'], 'ar')?"{$this->table}.".implode(", {$this->table}.", $params['regularFieldsSelect']):"{$this->table}.*";
        $selectPart[] = "modules_sitemap_relations.sitemap_id as relatedSitemap";

        if(_cv($params, 'customSelect')){
            $selectPart[] = $params['customSelect'];
        }
//p($selectPart);

        if (_cv($params, ['id']) && !_cv($params, ['id'], 'ar')) $params['id'] = [$params['id']];
        if (_cv($params, 'id', 'ar')) {
            $enableOrdering = false;
            $qr -> whereIn("{$this->table}.id", $params['id'])->orderByRaw("FIELD({$this->table}.id, ".implode(',',$params['id']).")");
        }

        if (_cv($params, ['slug']) && !_cv($params, ['slug'], 'ar')) $params['slug'] = [$params['slug']];
        if (_cv($params, 'slug','ar')) $qr -> whereIn("{$this->table}.slug", $params['slug']);

        if (_cv($params, ['status']) && !_cv($params, ['status'], 'ar')) $params['status'] = [$params['status']];
        if (_cv($params, 'status','ar')) $qr -> whereIn("{$this->table}.status", $params['status']);

        if (_cv($params, ['searchText'])){

            $params['searchText'] = strip_tags(addslashes($params['searchText']));
            $whereTmps = [];
            if(_cv($fields, ['regularFields'])){
                $tmp = '"-"';
                foreach ($fields['regularFields'] as $k=>$v){
                    if(isset($v['searchable'])){
                        $tmp .= ",IFNULL({$this->table}.{$k}, '-')";
                    }
                }
//                p($tmp);
//                p($fields['regularFields']);
//                $qr->whereRaw("LOCATE( '{$params['searchText']}', concat({$tmp}) )");
//                $qr->whereRaw(" concat({$tmp}) like '%{$params['searchText']}%' ");
                $whereTmps[] = " concat({$tmp}) like '%{$params['searchText']}%' ";
            }


            /// main search into all meta data
            if($this->metaTable){
                $qr -> leftJoin($this->metaTable, function($join) use ($translate){
                    $join -> on("{$this->metaTable}.table_id", '=', $this -> table . '.id') -> where("{$this->metaTable}.table", $this->table)->whereIn("{$this->metaTable}.lan", [$translate, 'xx']);
                });

                $whereTmps[] = " {$this->metaTable}.val like '%{$params['searchText']}%' ";
                $selectPart[] = "{$this->metaTable}.val as searchVal";
            }

            if(count($whereTmps)>=1){
                $qr->whereRaw("(".implode(' or ', $whereTmps).")");
            }

        }

        /// search by list filters
        if(_cv($params, 'searchBy', 'ar') && _cv($fields, ['adminListFields'])){
            foreach ($fields['adminListFields'] as $k=>$v){
                /// tableKey to identify search by field
                if(!_cv($v, ['searchable']) || !isset($v['tableKey']) || !_cv($params['searchBy'], [$v['tableKey']]) )continue;
                if(_cv($v, ['searchType'])=='where'){
                    $qr -> where($params['searchBy'][$v['tableKey']], $v['searchable']);
                }else{
                    $qr -> whereRaw("LOCATE('{$params['searchBy'][$v['tableKey']]}', {$v['searchable']})");
                }
            }
        }

        /// filter by regular fields
        if(isset($fields['regularFields'])){

            foreach ($fields['regularFields'] as $k=>$v){
                if(!isset($v['dbFilter']) || !isset($params[$k]) || empty($params[$k]))continue;

                if($v['dbFilter'] == 'whereIn' && isset($params[$k])){
                    if(!is_array($params[$k]))$params[$k] = [$params[$k]];
                    $qr -> whereIn("{$this->table}.{$k}", $params[$k]);

                }elseif ($v['dbFilter'] == 'like' && isset($params[$k])){
                    if(is_array($params[$k]))$params[$k] = implode('',$params[$k]);
                    $qr -> where("{$this->table}.{$k}",  'like', "%{$params[$k]}%");

                }elseif ($v['dbFilter'] == 'where' && isset($params[$k])){
                    if(is_array($params[$k]))$params[$k] = implode('',$params[$k]);
                    $qr -> where("{$this->table}.{$k}",  $params[$k]);

                }elseif ($v['dbFilter'] == 'range' && isset($params[$k])){
                    if(!is_array($params[$k]))$params[$k] = [$params[$k]];

                    if(count($params[$k])>=2){
                        $qr -> whereBetween("{$this->table}.{$k}",  $params[$k]);
                    }else{
                        $qr -> where("{$this->table}.{$k}",  $params[$k]);
                    }
                }elseif ($v['dbFilter'] == '>' && isset($params[$k])){
                    if(is_array($params[$k]))$params[$k] = implode('',$params[$k]);
                    $qr -> where("{$this->table}.{$k}", '>', $params[$k]);
                }elseif ($v['dbFilter'] == '<' && isset($params[$k])){
                    if(is_array($params[$k]))$params[$k] = implode('',$params[$k]);
                    $qr -> where("{$this->table}.{$k}", '<', $params[$k]);
                }

            }
        }


        ///// where raw parts
        if(_cv($params, ['whereRaw'], 'ar')){
            foreach ($params['whereRaw'] as $k=>$v){
                $qr->whereRaw($v);
            }
        }

        ///// or where raw parts
        if(_cv($params, ['orWhereRaw'], 'ar')){
            $qr -> where(function($q)use($params){
                foreach ($params['orWhereRaw'] as $k=>$v){
                    $q -> orWhereRaw($v);
                }
            });
        }

        if (_cv($params, 'sort')) $qr -> where('sort', $params['sort']);


        /// join on some table from configurations
        foreach ($tableJoinOn as $k=>$v){
            // dd($tableJoinOn);

            if(_cv($v, 'tablePrefix')){
                $tableName = $v['tablePrefix'];
            } else {
                $tableName = $v['joinTable'];
            }

            $qr->leftJoin("{$v['joinTable']} as joinedTable_{$tableName}", function($join) use ($tableName, $v, $params){
                $join->on("joinedTable_{$tableName}.{$v['joinField']}", "=", "{$this->table}.{$v['joinOn']}");
                if(_cv($v, 'sameStatus') === true){
                    if (_cv($params, 'status','ar')) $join -> whereIn("joinedTable_{$tableName}.status", $params['status']);
                }

                if(_cv($v, 'whereRaw', 'ar')){
                    foreach ($v['whereRaw'] as $vv){
                        $join -> whereRaw($vv);
                    }
                }


            });

            //    $qr -> leftJoin("{$v['joinTable']} as joinedTable_{$v['joinTable']}", "joinedTable_{$v['joinTable']}.{$v['joinField']}", '=', "{$this->table}.{$v['joinOn']}");
        }

        $qr->leftJoin("modules_sitemap_relations", "modules_sitemap_relations.table_id", "{$this->table}.id");


        /// filter by related table fields
        if(count($tableJoinSelectFilters) >= 1){
//p($tableJoinSelectFilters);
            foreach ($tableJoinSelectFilters as $k=>$v){
                if(!isset($params[$k]) || empty($params[$k]))continue;

                if($v['filterType'] == 'whereIn'){
                    if(!is_array($params[$k]))$params[$k] = [$params[$k]];
                    $qr -> whereIn("{$v['table']}.{$v['field']}", $params[$k]);

                }elseif ($v['filterType'] == 'like'){
                    $qr -> where("{$v['table']}.{$v['field']}",  'like', "%{$params[$k]}%");

                }elseif ($v['filterType'] == 'where'){
                    $qr -> where("{$v['table']}.{$v['field']}",  $params[$k]);

                }elseif ($v['filterType'] == 'range'){
                    if(!is_array($params[$k]))$params[$k] = [$params[$k]];

                    if(count($params[$k])>=2){
                        $qr -> whereBetween("{$v['table']}.{$v['field']}",  $params[$k]);
                    }else{
                        $qr -> where("{$v['table']}.{$v['field']}",  $params[$k]);
                    }
                }
            }
        }

        if(_cv($params, 'customJsonFilter')){
            foreach($params['customJsonFilter'] as $k => $column){
                // dd($params['toFilterArray'][$k]);
                foreach($params['toFilterArray'][$k] as $kk => $value){
                    if($kk === 0){
                        $qr->whereJsonContains($column, $value);
                        continue;
                    }
                    $qr->orWhereJsonContains($column, $value);
                }
            }
        }

        /// full list count
        $listCount = $qr->count(DB::raw("DISTINCT({$this->table}.id)"));

        $groupBy = ["{$this->table}.id"];
        if(_cv($params, 'group_by'))$groupBy = $params['group_by'];
        $qr -> groupBy($groupBy);

        // having parts
        if(_cv($params, ['having'], 'ar')){
            foreach ($params['having'] as $k=>$v){
                foreach($v as $vv){
                    $qr->havingRaw($k. '=' .$vv);
                }
            }
        }

        if(_cv($params, ['orHaving'], 'ar')){
            foreach ($params['orHaving'] as $k=>$v){
                foreach($v as $vv){
                    $qr->orhavingRaw($k. '=' .$vv);
                }
            }
        }

        $params['limit'] = $params['limit'] ?? 10;

        if (_cv($params, 'limit')) $qr -> take($params['limit']);
        if (_cv($params, 'page')) $qr -> skip(($params['page'] - 1) * $params['limit']) -> take($params['limit']);

        $defaultOrderField = _cv($fields, 'orderField')?$fields['orderField']:'id';
        $defaultOrderDirection = _cv($fields, 'orderDirection')?$fields['orderDirection']:'desc';

        $orderField = _cv($params, 'orderField')?$params['orderField']:$defaultOrderField;
        $orderDirection = _cv($params, 'orderDirection')?$params['orderDirection']:$defaultOrderDirection;



        if($enableOrdering){
            if($orderDirection == 'RANDOM'){
                $qr->orderByRaw("RAND()");
            }
            else if(_cv($fields, ['regularFields', $orderField, 'orderCast']) || _cv($fields, ['fields', $orderField, 'orderCast'])){
                if($orderField) $orderField = $this->table .'.'. $orderField;
                $qr->orderByRaw("CONVERT({$orderField}, SIGNED) {$orderDirection}");
            } else {
                $qr->orderByRaw("{$orderField} {$orderDirection}");
            }

        }

        $qStr = implode(',', $selectPart);
        $qr->selectRaw(DB::raw($qStr));

        $list = $qr -> get();

//        if(_cv($params, 'searchBy', 'ar'))
// p(DB::getQueryLog());


        if (!$list) return $returnData;

        $ret = _psql(_toArray($list));
//        p($ret);

        $metaFields = _cv($fields, 'fields');

        foreach ($ret as $k => $v)
        {

            if(isset($v['metas'])){
                $meta = decodeJoinedMetaData($v['metas'], 1);
                unset($v['metas']);
                $ret[$k] = mergeToMetaData($v, $meta, $this -> allAttributes);
            }

            if(isset($v['taxonomy'])){
                $ret[$k]['taxonomy'] = $this -> reverseArray($v['taxonomy']);
            }

            if(_cv($params, 'translate')){
                $ret[$k] = $this->extractOnlyTranslated($ret[$k], $translate, $metaFields);
            }

            /// leave only desired fields
            if(_cv($params, 'fields', 'ar')){
                $tmp = [];
                foreach ($params['fields'] as $kk=>$vv){
                    $tmp[$vv] = isset($ret[$k][$vv])?$ret[$k][$vv]:'';
                }

                $ret[$k] = $tmp;
            }

        }


        $returnData['listCount'] = $listCount;
        $returnData['list'] = $ret;
        $returnData['page'] = _cv($params, 'page', 'nn')?$params['page']:1;

//p($returnData);
        return $returnData;
    }

    public function updItem($data = [])
    {
//        p($data);
        $locales = config('app.locales');

        if(_cv($data, 'status') !== 'deleted'){
            /** validate table regular data depend on child class rules */
            $validator = Validator::make($data, $this->rules);
            if ($validator->fails()) { return ['success'=>false,'message'=>$validator->errors()->first()]; }
        }

        $id = _cv($data, 'id', 'nn');

        if(!$id){
            if(!_cv($data, ['user_id']))$data['user_id'] = Auth::user()->id ?? null;
            if(!_cv($data, ['status']))$data['status'] = 'published';
        }

        /** get or create table entry */
        $upd = $this->firstOrNew( ['id'=>$id] );

//        p($this->fillable);
        /** updatetable regular fields data */
        foreach ($this->fillable as $k=>$v){

            /// if editing does not change user
            if($v == 'user_id' && $id) continue;

            if(!isset($data[$v]))continue;

            $upd[$v] = is_array($data[$v])?_psqlupd($data[$v]):$data[$v];

        }

        /// if change user_id from admin panel allow user changing
        if(Auth::user() && Auth::user()->status=='admin' && array_search('user_id', $this->attributes)!==false && _cv($data, 'user_id', 'nn')){
            $upd['user_id'] = $data['user_id'];
        }

//        p($upd);
        $upd->save();

        $upd->id;

        $fields = (_cv($this->fieldConfigs, 'fields'))?$this->fieldConfigs:config($this->fieldConfigs);
//p($fields);
        /** update table meta fields */
        if($this->metaTable){
            $SmartTableMetaModel = new SmartTableMetaModel();


            $metaParams['fields'] = _cv($fields, ['fields']);
            $metaParams['table'] = $this->metaTable;
            $metaParams['table_id'] = $upd->id;
            $metaParams['parentTable'] = $this->table;

            foreach ($locales as $k=>$v){
                if(!isset($data[$k]))continue;
                $metaParams['lan'] = $k;
                $metaParams['meta'] = $data[$k];
                $rr = $SmartTableMetaModel->upd($metaParams);
            }
            /// xx for not translatable fields
            $metaParams['meta'] = _cv($data, ['xx']);
            $metaParams['lan'] = 'xx';
            $rr = $SmartTableMetaModel->upd($metaParams);
        }
        /** update table related taxonomies */
        $taxonomies = _cv($data, 'taxonomy', 'ar');

        if ($taxonomies)
        {
            $attributesRelationParams['dataList'] = $taxonomies;
            $attributesRelationParams['relationTable'] = $this->taxonomyRelationTable;
            $attributesRelationParams['relationTableName'] = $this->table;
            $attributesRelationParams['firstKeyName'] = 'data_id';
            $attributesRelationParams['firstKeyValue'] = $upd->id;
            $attributesRelationParams['secondKeyName'] = 'taxonomy_id';
            $attributesRelationParams['mainExtraFields'] = ['table'=>$this->table];
            $attributesRelationParams['formKeyName'] = 'dataList';


            RelationsModel::doRelations($attributesRelationParams);
        }

        if(_cv($fields, 'relations', 'ar')){

            foreach ($fields['relations'] as $k=>$v){
                $relationsClearOptions['relationTable'] = $v['table'];
                $relationsClearOptions['firstKeyName'] = $v['id'];
                $relationsClearOptions['firstKeyValue'] = $upd->id;
                RelationsModel::removeRelations($relationsClearOptions);
            }

            foreach ($fields['relations'] as $k=>$v){
//                p("relation_{$v['module']}");
                if(!isset($data["relation_{$v['module']}"]))continue;

                $relatedData = [];
                $relatedData['dataList'] = $data["relation_{$v['module']}"];
                $relatedData['relationTable'] = $v['table'];
                $relatedData['relationTableName'] = $v['module'];
                $relatedData['firstKeyName'] = $v['id'];
                $relatedData['firstKeyValue'] = $upd->id;
                $relatedData['secondKeyName'] = $v['data_id'];
//                $relatedData['mainExtraFields'] = ['table'=>$this->table];
                $relatedData['formKeyName'] = 'dataList';

                if( _cv($data, 'relationNode') ){
                    $relatedData['relationNode'] = $data['relationNode'];
                }

                RelationsModel::doRelations($relatedData);
            }
        }


        if(_cv($data, 'relatedSitemap')){
            SiteMapModel::doRelation([ 'sitemap_id'=>$data['relatedSitemap'], 'table_id'=>$upd->id, 'table'=>$this->table ]);
        }


        return $upd->id;
    }

    public function updField($data = [])
    {

        $locales = config('app.locales');

        /** validate table regular data depend on child class rules */
        $rules['id'] = ['required','numeric'];
        $rules['field'] = ['required'];
        // $rules['value'] = ['required'];

        $validator = \Validator::make($data, $rules);
//        p($validator);
        if ($validator->fails()) { return ['error'=>$validator->errors()->first()]; }

        $id = _cv($data, 'id', 'nn');

        if(!$id){
            if(!_cv($data, ['user_id']))$data['user_id'] = Auth::user()->id;
            if(!_cv($data, ['status']))$data['status'] = 'published';
        }

        /** get or create table entry */
        $upd = $this->find( $data['id'] );

        if(!isset($upd->id) || !$upd->id)return false;

        $upd[$data['field']] = $data['value'];
        p($upd);

        $upd->save();

        return $upd->id;

    }

    public function deleteItem($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;

        $deleteStatus = DB::table($this->table)->where('id', $data['id'])->update(['status' => 'deleted']);

        return $deleteStatus?$data['id']:0;

    }

    public function hardDeleteItem($data = [])
    {
        if(!_cv($data, 'id', 'nn'))return false;

        $deleteStatus = DB::table($this->table)->where('id', $data['id'])->limit(1)->delete();

        return $deleteStatus?$data['id']:0;

    }

    public function importData($data = [])
    {
//        p($data);

        $headers = _cv($data, 'data.header');
        $importData = _cv($data, 'data.results');

        $moduleConf = config($this->fieldConfigs);

        $headersPrepared = [];

        foreach ($headers as $k=>$v){
            $fieldNameCleaned = $this->cleanFieldName($v);

            if(!_cv($moduleConf, ['regularFields', $fieldNameCleaned]) && !_cv($moduleConf, ['fields', $fieldNameCleaned]) && $fieldNameCleaned !== 'taxonomy')continue;
            $headersPrepared[$v] = $fieldNameCleaned;
        }

        $requiredFields = [];
        $existCheckFields = [];
        /// get all required fields from regular fields
        if(_cv($moduleConf, ['regularFields'])){
            foreach ($moduleConf['regularFields'] as $k=>$v){
                /// collect required fields
                if(_cv($v, ['required'])==1)$requiredFields[$k] = $k;

                /// collect fields in which depend update or create new
                if(_cv($v, ['updateExist'])==1) $existCheckFields[$k] = $k;
            }
        }
        /// get all required fields from meta fields
        if(_cv($moduleConf, ['fields'])){
            foreach ($moduleConf['fields'] as $k=>$v){
                if(_cv($v, ['required'])==1)$requiredFields[$k] = $k;
            }
        }

        /// check if some required field not exists
        foreach ($requiredFields as $k=>$v){
            if(!array_search($v, $headersPrepared)) return ['success'=>false,'message'=>"field `{$v}` is required"];
//            if(!isset($headersPrepared[$v])) return ['success'=>false,'message'=>"field `{$v}` is required"];
        }

        $preparedData = [];
        foreach ($importData as $k=>$v){

            $tmp = [];
            foreach ($headersPrepared as $kk=>$vv){
                $tmp[$vv] = isset($v[$kk])?$v[$kk]:'';
            }

            if(isset($v['status']))$tmp['status'] = $v['status'];

            $preparedData[] = $tmp;
        }
//        p($preparedData);
        foreach ($preparedData as $k=>$v){

            $tmp = $this->prepareDataFromFlatArray($v);

            if(!isset($tmp['status']))$tmp['status'] = 'published';

            $tmp['id'] = $this->findExistItemId($existCheckFields, $tmp);

//            p($tmp);
//            continue;
            $res = $this->updItem($tmp);
            if(!is_numeric($res))return $res;
        }

        return true;

    }

    public function prepareDataFromFlatArray($flatData = []){
        $ret = [];
        $moduleConf = config($this->fieldConfigs);
//        p($moduleConf);
        $locale = requestLan();

        if(_cv($flatData, 'taxonomy')){
            $ret['taxonomy'] = $this->prepareImportableTaxonomyValues($flatData['taxonomy']);
        }

        if(isset($flatData['status']))$ret['status'] = strip_tags($flatData['status']);

        if(!_cv($moduleConf,['regularFields'], 'ar'))$moduleConf['regularFields'] = [];
        foreach ($moduleConf['regularFields'] as $k=>$v){
            if(!isset($flatData[$k]))continue;
            $ret[$k] = $this->prepareImportableValues($flatData[$k], $v);
        }

        if(!_cv($moduleConf,['fields'], 'ar'))$moduleConf['fields'] = [];
        foreach ($moduleConf['fields'] as $k=>$v){
            if(!isset($flatData[$k]))continue;

            if(_cv($v, ['translate'])){
                $ret[$locale][$k] = $this->prepareImportableValues($flatData[$k], $v);
            }else{
                $ret['xx'][$k] = $this->prepareImportableValues($flatData[$k], $v);
            }

        }

        return $ret;
    }

    public function prepareImportableTaxonomyValues($data = ''){
//        p($data);
        $data = explode(';', $data);
        $res = [];
        foreach ($data as $k=>$v){
            $v = explode(':', $v);
            if(!_cv($v, 1))continue;

            $v[0] = trim($v[0]);
            $v[1] = explode(',', trim($v[1]));

            $res[$v[0]] = [];
            foreach ($v[1] as $kk=>$vv){
                $vv = trim($vv);
                if(!is_numeric($vv))continue;
                $res[$v[0]][] = trim($vv);
            }

        }

        return $res;
    }

    public function reverseArray($sourceArray)
    {
        if (!is_array($sourceArray)) return [];

        $destArray = [];

        foreach ($sourceArray as $key => $value)
        {
            $destArray[$value][] = $key;
        }

        return $destArray;
    }

    public function extractOnlyTranslated($data = [], $translate = false, $fields = []){
//p($data);
//p($fields);
        $locales = config('app.locales');
        $locale = (isset($locales[$translate]))?$translate:array_key_first($locales);

        $notTranslatableData = _cv($data, 'xx', 'ar')?$data['xx']:[];
        $translatedData = _cv($data, $locale, 'ar')?$data[$locale]:[];

        /// if exists field configs
        /// check field is translatable or not; leave translatable fields; unset non translatable fields from translated object
        if(is_array($fields) && count($fields)>1){
            foreach ($fields as $k=>$v){
                if(isset($v['translate']) && $v['translate']==1)continue;
                if(isset($translatedData[$k]))unset($translatedData[$k]);
            }
        }


        /// unset translatable objects
        if(isset($data["xx"])) unset($data["xx"]);
        foreach ($locales as $kk=>$vv){
            if(isset($data[$kk])) unset($data[$kk]);
        }

        $data = array_merge($data, $notTranslatableData, $translatedData);

        $data['localisedto'] = $locale;

        return $data;
    }

    public function cleanFieldName($name = ''){
        return str_replace([' ', '-'], '_', $name);
    }

    public function prepareImportableValues($value='', $fieldConfig=[]){
        if(_cv($fieldConfig, 'type')=='select'){
            $tmp = _psqlCell($value);
            if(is_array($tmp)){
                $value = $tmp;
            }else if(strpos($value, ',')){
                $value = explode(',', $value);
            }else if(empty($value)){
                $value = [];
            }else{
                $value = [$value];
            }

        }

        return $value;
    }

    public function findExistItemId($checkFields=[], $data=[]){
        if(!count($checkFields))return '';

        $find['status'] = ['published', 'hidden'];
        foreach ($checkFields as $k=>$v){
            $find[$k] = _cv($data, $k);
        }

        $tmp = $this->getOne($find);

        if(_cv($tmp, 'id', 'nn'))return $tmp['id'];

        return '';
    }

    public function checkUser($id){
        $check = DB::table($this->table)->where('id', $id)->where('user_id', Auth::user()->id)->first();
        if(!$check){
            return response(['status'=>'error', 'message'=>'User not belongs to this record!']);
        }
    }

    public function updSort($list = [], $listParams = [])
    {
        $startFrom = 0;

        if (_cv($listParams, 'currentPage', 'nn') && _cv($listParams, 'sliceseparator', 'nn')) {
            $startFrom = ($listParams['currentPage'] * $listParams['sliceseparator']) - $listParams['sliceseparator'];
        }

        foreach ($list as $k => $v) {
            if (!is_numeric($v)) continue;
            DB::table($this->table)->where('id', $v)->update(['sort' => ($k + $startFrom)]);
        }

        return false;
    }

}
