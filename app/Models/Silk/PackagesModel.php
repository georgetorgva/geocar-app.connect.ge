<?php

namespace App\Models\Silk;

use App\Models\Admin\TaxonomyRelationsModel;
use App\Models\Media\MediaModel;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;

/**
 * main model for string translation
*/
class PackagesModel extends Model
{
    //

    protected $table = 'silk_packages';
    public $timestamps = false;
    protected $error = false;
//    protected $transformFields = [
//        'content_group' => 'contentGroup',
//    ];
    protected $fillable = [
        'id',
        'create_date',
        'title',
        'slug',
        'conf',
        'sort',
        'info',
        'status',
        'package_type',
        'package_type_uid',
        'service_id',
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
            $res = PackagesModel::find($data['id']);
        }

        if(!$res) {
            $res = new PackagesModel();
            $res->sort = 1;
        }

        $res->slug = _cv($data, 'slug');
        $res->title = _psqlupd(_cv($data, 'title'));
        $res->conf = _psqlupd(_cv($data, 'conf'));
        $res->info = _psqlupd(_cv($data, 'info'));
//        $res->sort = _cv($data, 'sort', 'nn')?$data['sort']:1;
        $res->status = _cv($data, 'status')?$data['status']:'inactive';
        $res->package_type = _cv($data, 'package_type');
        $res->package_type_uid = _cv($data, 'package_type_uid');
        $res->service_id = _cv($data, 'service_id');

        $res->save();

        $relatedTaxonomies = config('adminpanel.admin_menu.silkServicesMain.children.silkServicesPackages.taxonomy');

        if(is_array($relatedTaxonomies) && $res->id) {
            foreach ($relatedTaxonomies as $k=>$v){
                if(!_cv($data, $v, 'ar'))continue;
                TaxonomyRelationsModel::doRelations(['data_id'=>$res->id, 'taxonomy_id'=>$data[$v], 'related_table'=>$this->table, 'relation_slug'=>$v]);
            }
        }

        return $res->id;
    }

    public function getOne($params = []){

        $params['limit'] = 1;
        $model = new PackagesModel();
        $ret = $model->getBy($params);

        if(isset($ret[0]))return $ret[0];

        return false;

    }

    public function getBy($params = []){
        DB::enableQueryLog();

        $relatedTaxonomies = config('adminpanel.admin_menu.silkServicesMain.children.silkServicesPackages.taxonomy');

        $relatedTaxonomieSelects = "";

        if(is_array($relatedTaxonomies)){
            foreach ($relatedTaxonomies as $v) {
                $relatedTaxonomieSelects .= ", group_concat( DISTINCT {$v}.taxonomy_id ) as {$v}";
            }
        }

        $model = PackagesModel::selectRaw("
        {$this->table}.info,
        {$this->table}.title,
        {$this->table}.slug,
        {$this->table}.conf,
        {$this->table}.create_date,
        {$this->table}.sort,
        {$this->table}.package_type,
        {$this->table}.package_type_uid,
        {$this->table}.service_id,
        {$this->table}.status,
        {$this->table}.id {$relatedTaxonomieSelects}");

        if(_cv($params, 'title'))$model->where("{$this->table}.conf", 'like', "%{$params['title']}%");
        if(_cv($params, 'slug'))$model->where("{$this->table}.slug", $params['slug']);
        if(_cv($params, 'status'))$model->where("{$this->table}.status", $params['status']);
        if(_cv($params, 'conf'))$model->where("{$this->table}.conf", 'like', "%{$params['conf']}%");
        if(_cv($params, 'info'))$model->where("{$this->table}.info", 'like', "%{$params['info']}%");
        if(_cv($params, 'package_type'))$model->where("{$this->table}.info", $params['package_type']);
        if(_cv($params, 'package_type_uid'))$model->where("{$this->table}.info", $params['package_type_uid']);
        if(_cv($params, 'service_id'))$model->where("{$this->table}.service_id", $params['service_id']);
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
        $model->orderBy("sort", 'asc')->orderBy("id", 'desc');

        if(_cv($params, 'limit', 'nn'))$model->limit($params['limit']);
        $ret = $model->get();
        $ret = _psql(_toArray($ret), $relatedTaxonomies);

        foreach ($ret as $k=>$v){
            $ret[$k]['price'] = $this->getPackagePrice($v['info']);
        }
//        $query = DB::getQueryLog();
//        p($query);

        return $ret;

    }

    public function deleteOne($params = []){
        if(!_cv($params, 'id', 'nn'))return false;

        $model = PackagesModel::find($params['id']);

        $ret = [];
        if($model) {
//            p($model);
            $ret = _psqlRow(_toArray($model));
            $model->delete();
        }

        return $ret;

    }

    public function getPackagePrice($info = []){

        if(!is_array($info))return false;

        foreach ($info as $lan){
            foreach ($lan as $uids){
                if(_cv($uids, ['price', 'price'], 'nn'))return $uids['price']['price'];
            }
        }

        return 0;

    }






}
