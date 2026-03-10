<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

/**
 * main model for taxonomy relations
*/
class TaxonomyRelationsModel extends Model
{
    //

    protected $table = 'taxonomy_relations';
    public static $tablee = 'taxonomy_relations';
    public $timestamps = false;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'relation_slug',
        'data_id',
        'taxonomy_id',
        'related_table',
    ];

    private $rules = array(
//        'relation_slug' => 'required',
        'data_id'  => 'required',
        'taxonomy_id'  => 'required',
        'related_table'  => 'required',
    );

    protected $hidden = [

    ];

//    protected $attributes = [
//        'key'  => 'string',
//        'value'  => 'string',
//    ];

    public static function doRelations($params = [])
    {

        if(!_cv($params, 'data_id') || !isset($params['taxonomy_id']) || !_cv($params, 'related_table') )return false;

        if(!is_array($params['data_id']))$params['data_id'] = [$params['data_id']];
        if(!is_array($params['taxonomy_id']))$params['taxonomy_id'] = [$params['taxonomy_id']];

        foreach ($params['data_id'] as $k=>$v){
            if(!is_numeric($v))continue;

            /// remove all relations for data_id
            self::removeRelations(['data_id'=>$v, 'related_table'=>$params['related_table'], 'relation_slug'=>_cv($params, 'relation_slug') ]);

            foreach ($params['taxonomy_id'] as $kk=>$vv){
                if(!is_numeric($vv))continue;
                self::doRelation(['data_id'=>$v, 'taxonomy_id'=>$vv, 'related_table'=>$params['related_table'], 'relation_slug'=>_cv($params, 'relation_slug') ]);

            }
        }

        return true;
    }

    public static function doRelation($params = [])
    {
        if(!_cv($params, 'data_id', 'nn') || !_cv($params, 'taxonomy_id', 'nn') || !_cv($params, 'related_table') )return false;

        self::removeRelations(['data_id'=>$params['data_id'], 'taxonomy_id'=>$params['taxonomy_id'], 'related_table'=>$params['related_table'] ]);
        $relation_slug = _cv($params, 'relation_slug')?$params['relation_slug']:'main';

        DB::enableQueryLog();
        $ret = DB::table(self::$tablee)->insert(
            [
                'relation_slug'=>$relation_slug,
                'data_id'=>$params['data_id'],
                'taxonomy_id'=>$params['taxonomy_id'],
                'related_table'=>$params['related_table'],
            ]
        );

        $query = DB::getQueryLog();

//            p($query);
        return $ret;
    }



    public static function removeRelations($params = []){
//        p($params);
        if(!_cv($params, 'data_id', 'nn') || !_cv($params, 'related_table') )return false;
        DB::enableQueryLog();
        $remove = DB::table(self::$tablee)
            ->where('data_id', $params['data_id'])
            ->where('related_table', $params['related_table']);

            if(_cv($params, 'taxonomy_id', 'nn')){
                $remove->where('taxonomy_id', $params['taxonomy_id']);
            }

            if(_cv($params, 'relation_slug')){
                $remove->where('relation_slug', $params['relation_slug']);
            }

        $remove->delete();

        $query = DB::getQueryLog();

//        p($query);

        return false;

    }





}
