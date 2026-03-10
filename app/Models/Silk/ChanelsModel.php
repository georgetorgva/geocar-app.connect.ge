<?php

namespace App\Models\Silk;

use App\Models\Admin\TaxonomyRelationsModel;
use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;
use \Validator;

use Illuminate\Support\Facades\DB;

/**
 * main model for string translation
*/
class ChanelsModel extends Model
{
    //

    protected $table = 'silk_chanels';
    public $timestamps = false;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'info',
        'logo',
        'create_date',
        'category',
        'serviceId',
        'channelName',
        'offerId',
        'offerNumber',
        'offerName',
        'is_deleted'
    ];

    private $rules = array(
//        'info' => 'required',
//        'logo'  => 'required',
    );

    protected $hidden = [

    ];

    public function upd($data = [])
    {
//p($data);
        $res = false;
        if(_cv($data, ['id'], 'nn')){
            $res = ChanelsModel::find($data['id']);
        }

        if(!$res) $res = new ChanelsModel();

        $res->info = _psqlupd(_cv($data, 'info'));
        $res->serviceId = _cv($data, 'serviceId');
        $res->category = _cv($data, 'category');
        $res->logo = _psqlupd($this->extractMediaIds(_cv($data, 'logo')));

        $res->save();

        $relatedTaxonomies = config('adminpanel.admin_menu.silkServicesMain.children.silkServicesChannels.taxonomy');

        if(is_array($relatedTaxonomies) && $res->id) {
            foreach ($relatedTaxonomies as $k=>$v){

                if(!isset($data[$v]) || !is_array($data[$v]))continue;

                TaxonomyRelationsModel::doRelations(['data_id'=>$res->id, 'taxonomy_id'=>$data[$v], 'related_table'=>'silk_chanels', 'relation_slug'=>$v]);
            }
        }

        return $res->id;
    }


    public function getOne($params = []){

        $params['limit'] = 1;
        $model = new ChanelsModel();
        $ret = $model->getBy($params);

        if(is_array($ret))return $ret[0];

        return false;

    }

    public function getBy($params = []){
        DB::enableQueryLog();

//        $model = new ChanelsModel;

        $relatedTaxonomies = config('adminpanel.admin_menu.silkServicesMain.children.silkServicesChannels.taxonomy');

        $relatedTaxonomieSelects = "";

        if(is_array($relatedTaxonomies)){
            foreach ($relatedTaxonomies as $v) {
                $relatedTaxonomieSelects .= ", group_concat( DISTINCT {$v}.taxonomy_id ) as {$v}";

            }
        }

        $model = ChanelsModel::selectRaw("
        {$this->table}.info,
        {$this->table}.logo,
        {$this->table}.create_date,
        {$this->table}.category,
        {$this->table}.serviceId,
        {$this->table}.channelId,
        {$this->table}.channelName,
        {$this->table}.offerId,
        {$this->table}.offerNumber,
        {$this->table}.offerName,
        {$this->table}.is_deleted,
        {$this->table}.id
        {$relatedTaxonomieSelects} ");

        if(_cv($params, 'info'))$model->where("{$this->table}.info", 'like', "%{$params['info']}%");
        if(_cv($params, 'category'))$model->where("{$this->table}.category", $params['category']);
        if(_cv($params, 'id'))$model->where("{$this->table}.id", $params['id']);


        if(is_array($relatedTaxonomies)){
            foreach ($relatedTaxonomies as $v) {

                $model->leftJoin("taxonomy_relations as {$v}", function($join) use ($v){
                    $join->on("{$v}.data_id", "=", $this->table.'.id');
                    $join->where("{$v}.related_table", '=', $this->table);
                    $join->where("{$v}.relation_slug", '=', $v);
                });

            }
        }
        $model->groupBy("{$this->table}.id");
        $model->orderBy("sort", 'asc');

        if(_cv($params, 'limit', 'nn'))$model->limit($params['limit']);
        $ret = $model->get();

        $query = DB::getQueryLog();
//        p($query);


        $ret = _psql(_toArray($ret), $relatedTaxonomies);
//        p($ret);
        foreach ($ret as $k=>$v){
            $ret[$k]['logo'] = $this->getMediaData($v['logo']);
        }
//p($ret);
        return $ret;

    }

    public function deleteOne($params = []){
        if(!_cv($params, 'id', 'nn'))return false;

        $chanels = ChanelsModel::find($params['id']);

        $ret = [];
        if($chanels) {
//            p($chanels);
            $ret = _psqlRow(_toArray($chanels));
            $chanels->delete();
        }
//p($ret);
        return $ret;

    }


    public function getMediaData($data = [], $contentType = ''){
        if(!is_array($data) || count($data) == 0)return [];

        $mediaModel = new MediaModel();
        $medias = $mediaModel->getList(['ids'=>$data ]);

        return $medias;
    }

    public function extractMediaIds($data=[]){
        if(!is_array($data))return [];

        $ret = array_column($data, 'id');
        return $ret;

    }


    public static function Sync(){

        $url=env('SILK_URL', 'localhost').'/rest/connect/channels';
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $credentials = base64_encode(env('SILK_USER', 'user').':'.env('SILK_PASS', 'pass'));
        $response = $client->get($url,['headers' => ['Authorization' => 'Basic ' . $credentials,]]);
        $data = json_decode ($response->getBody()->getContents());

        $Ids=[];
        foreach ($data->channels as $c){
            $r=ChanelsModel::where('channelId','=',$c->channelId)->first();
            if(!$r){
                $r=new ChanelsModel();
                $r->category = 0;
                $r->info = _psqlupd( ['ge'=>['title'=>$c->channelName, 'teaser'=>$c->offerName], 'en'=>['title'=>$c->channelName, 'teaser'=>$c->offerName] ] );
                $r->logo = '[]';
            }
            $r->serviceId=$c->serviceId;
            $r->channelId=$c->channelId;
            $r->channelName=$c->channelName;
            $r->offerId=$c->offerId;
            $r->offerNumber=$c->offerNumber;
            $r->offerName=$c->offerName;
            $r->is_deleted=0;
            $r->sort=$c->channelPosition;
            $r->Save();
            $Ids[]=$r->id;
        }
        ChanelsModel::where('is_deleted','=',false)->whereNotIn('id',$Ids)->update(['is_deleted'=>true]);
    }

}
