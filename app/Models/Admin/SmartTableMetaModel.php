<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use \Validator;
use Illuminate\Support\Facades\DB;

class SmartTableMetaModel extends Model
{
    protected $table = '';
    public $timestamps = false;
    protected $error = false;


    public function __construct(array $attributes = [])
    {
        $this->table = _cv($attributes, 'table');
    }

    //
    protected $allAttributes = [
        'id',
        'table',
        'key',
        'val',
        'lan',
        'table_id',
    ];
    protected $fillable = [
        'id',
        'key',
        'lan',
        'table',
        'table_id',
        'created_at',
        'updated_at',
    ];
    protected $guarded = [
        'id',
        'key',
        'lan',
        'table',
        'table_id',
    ];

    public function upd($data = [])
    {
//p($data);
        $validator = \Validator::make($data, [
            'table' => ['required'],
            'parentTable' => ['required'],
            'table_id' => ['required', 'numeric'],
            'meta' => ['required'],
            'lan'=>['required', 'size:2']
        ]);

        if ($validator->fails()) { return ['success'=>false,'message'=>$validator->errors()->first()]; }

//        $metaFields = _cv($data, 'metaFields')?$data['metaFields']:$data['meta'];
        $metaFields = $data['meta'];
        if(!is_array($metaFields))return false;

        $table = $data['table'];
        $meta = $data['meta'];
        $tableId = $data['table_id'];
        $parentTable = $data['parentTable'];
        $lan = $data['lan'];

        $metaData = $this->getListRaw(['table'=>$table, 'table_id'=>$tableId, 'parentTable'=>$parentTable, 'lan'=>$lan]);

        $status = false;

        foreach ($metaFields as $k=>$v){

            if(isset($data['fields']) && !isset($data['fields'][$k]))continue;

            /// if field is translatable and content comes from untranslatable fields do nothing
            if(_cv($data['fields'][$k], ['translate']) == 1 && $lan == 'xx')continue;
            if(_cv($data['fields'][$k], ['translate']) != 1 && $lan != 'xx')continue;

//            p($data['fields'][$k]);

            $k = trim(strip_tags($k));

            $value = isset($meta[$k])?$meta[$k]:'';
            if(is_array($value))$value = _psqlupd($value);

            /** if meta field exists update, else insert new one */
            if(_cv($metaData, [$k,'id'])){
//                print "id: {$metaData[$k]['id']}; val: {$value}";
                $status = DB::table($table)->where('id', $metaData[$k]['id'])->update(['val' => $value]);
            }else{
                $ss = [
                    'table' => $parentTable,
                    'table_id' => $tableId,
                    'lan' => $lan,
                    'key'  => $k,
                    'val'  => $value,
                    'created_at'  => date("Y-m-d H:i:s"),
                    'updated_at'  => date("Y-m-d H:i:s")
                ];
                $status = DB::table($table)->insert($ss);
            }

        }

        return $status;
    }


    public function getListRaw($params = []){

        if( !isset($params['table']) || !isset($params['table_id']) )return [];

        $metaData = DB::table($params['table'])->select("id","key","val")
            ->where("table_id", $params['table_id'])
            ->where("lan", $params['lan'])
            ->where("table", $params['parentTable'])
            ->get();

        $ret = [];

        $metaData = _psql(_toArray($metaData));

        foreach ($metaData as $k=>$v){
            $ret[$v['key']] = $v;
        }

        return $ret;
    }


}
