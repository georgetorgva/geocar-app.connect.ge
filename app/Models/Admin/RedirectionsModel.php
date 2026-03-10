<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use \Validator;


/**
 * main model for string translation
 */
class RedirectionsModel extends Model
{
    //

    protected $table = 'redirections';
    public $timestamps = false;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'from_url',
        'to_url',
    ];

    private $rules = array(
//        'from_url' => 'required',
//        'to_url'  => 'required',
    );

    protected $hidden = [

    ];

    protected $attributes = [
        'from_url'  => 'string',
        'to_url'  => 'string',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->error = Validator::make([], []);
    }

    public function upd($data = [])
    {

        $res = false;
        if(_cv($data, ['id'])){
            $res = self::where('id', $data['id'] )->first();
        }

        if(!$res) $res = new RedirectionsModel();

        $res->from_url = _cv($data, ['from_url']);
        $res->to_url = _cv($data, ['to_url']);
        $res->virtual = _cv($data, ['virtual'])?1:0;

        $res->save();

        return $res->id;
    }


    public function getOne($data=[]){


        $data['limit'] = 1;
        $ret = $this->getBy($data);

        if(_cv($ret, 0))return current($ret);

        return [];

    }

    public function getBy($data=[]){


        $model = self::select('*');
        if(_cv($data, 'id', 'nn'))$model->where('id', $data['id']);
        if(_cv($data, 'from_url'))$model->where('from_url', 'like', "%{$data['from_url']}%");
        if(_cv($data, 'to_url'))$model->where('to_url', 'like', "%{$data['to_url']}%");

        if(_cv($data, 'limit', 'nn'))$model->limit($data['limit']);

        $ret = $model->get();
        $ret = _toArray($ret);

        return $ret;

    }

    public function deleteItem($params = []){
        if(!_cv($params, 'id', 'nn'))return false;

        return self::where('id', $params['id'])->delete();

    }

    public function getRedirectionUrl($params = []){

        if(!_cv($params, 'path') || strlen($params['path']) < 2)return false;
        if(substr($params['path'], 0,1) != '/')$params['path'] = "/{$params['path']}";

        $model = self::select(['id', 'from_url', 'to_url']);
        $res = $model->where('from_url', "like", "%{$params['path']}")->orWhere('from_url', "like", "%{$params['path']}/")->limit(1)->pluck('to_url');

        if(isset($res[0])) return $res[0];
        return false;

    }



}
