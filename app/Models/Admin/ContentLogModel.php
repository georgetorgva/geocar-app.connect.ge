<?php

namespace App\Models\Admin;

use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Admin\OptionsModel;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\CrudHelperModel;

class ContentLogModel extends Model
{
    protected $table = 'content_log';
    public $timestamps = true;
    protected $error = false;
    protected $meta;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = false;

    use CrudHelperModel;

    //
    protected $allAttributes = [
        'id',
        'data_id',
        'user_id',
        'loged_data',
//        'created_at',
    ];
    protected $fillable = [
        'data_id',
        'user_id',
        'loged_data',
    ];
//    protected $guarded = [
////        'id',
////        'created_at',
////        'data_id',
//    ];

    function __construct()
    {
        if (env('DB_CONNECTION_PRIVATE', '')) $this->connection = env('DB_CONNECTION_PRIVATE', '');


        parent::__construct();
    }

    public function getOne($params = [])
    {
        return false;
    }

    public function getList($params = [])
    {
        $params['table'] = $this->table;
        $params = $this->getListParamsChecker($params, ['whereIn'=>['data_id','id','user_id', 'ip_address'], ], ['sortField'=>'id', 'orderDirection'=>'desc']);
//p($params);

        DB::enableQueryLog();


        $qr = $this->select(['content_log.data_id','content_log.loged_data','content_log.created_at','content_log.user_id','content_log.ip_address','users.fullname']);

        $qr->leftJoin('users', 'users.id', '=', 'content_log.user_id');

        if(_cv($params, 'id', 'ar'))$qr->whereIn('content_log.id', $params['id']);
        if(_cv($params, 'data_id', 'ar'))$qr->whereIn('content_log.data_id', $params['data_id']);
        if(_cv($params, 'user_id', 'ar'))$qr->whereIn('content_log.user_id', $params['user_id']);
        if(_cv($params, 'ip_address', 'ar'))$qr->whereIn('content_log.ip_address', $params['ip_address']);

        if(_cv($params, 'filterWord'))$qr->where('content_log.loged_data', 'like', "%{$params['filterWord']}%");

        if( _cv($params, 'date.0') && !_cv($params, 'date.1')){
            $qr->where("{$this->table}.created_at", '=', $params['date'][0]);

        }else if( _cv($params, 'date.0') && _cv($params, 'date.1')){
            /// if there is dates range (from, to) search entries within date range
            $qr->where("{$this->table}.created_at", '>=', $params['date'][0])
                ->where("{$this->table}.created_at", '<=', $params['date'][1]);
        }

        $params['listCount'] = $ret['listCount'] = $qr->count(DB::raw('DISTINCT(content_log.id)'));

        $params = $this->getListParamsChecker($params, ['whereIn'=>['data_id','id','user_id', 'ip_address'], ], ['sortField'=>'id', 'orderDirection'=>'desc']);

        $list = $qr->orderByRaw($params['orderByRaw'])->offset($params['offset'])->limit($params['limit'])->get();

//        p($params);

        $query = DB::getQueryLog();
//            p($query);

        $ret['list'] = _toArray($list);

        return $ret;
    }



    public function insertLog($data = [])
    {
        if(!_cv($data,'id', 'nn'))return false;

        $upd = new ContentLogModel();

        $upd->user_id = auth()->user()->id;
        $upd->data_id = $data['id'];
        $upd->loged_data = _psqlupd(_cv($data, ['content']));
        $upd->created_at = date('Y-m-d H:i:s');


        $upp['user_id'] = auth()->user()->id;
        $upp['data_id'] = $data['id'];
        $upp['loged_data'] = _psqlupd(_cv($data, ['content']));
        $upp['created_at'] = date('Y-m-d H:i:s');
        $upp['ip_address'] = request()->ip();
//        ContentLogModel::insert($upp);

//        $upd->save();
        $this->insert($upp);

        return $data['id'];
    }


}
