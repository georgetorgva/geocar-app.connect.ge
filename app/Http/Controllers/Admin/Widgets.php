<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\WidgetModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;



class Widgets extends Model
{
    //
    protected $mainModel;
    protected $error = false;

    public function __construct()
    {
        parent::__construct();
        $this->mainModel = new WidgetModel();
    }

    public function getWidgetsForSite($params = []){
        return $this->mainModel->getProduction($params);
    }

    public function getWidget(Request $request){

        $res = $this->mainModel->getBy();

        if ($res) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function getWidgets(Request $request){

        $res = $this->mainModel->getList( $request->all() );

        if ($res) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function getAllWidgetTitles(Request $request){

        $res = $this->mainModel->getWidgetTitlesList();

        if ($res) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function getWidgetTitles(Request $request){

        $res = $this->mainModel->getWidgetTitlesList();

        if ($res) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function updWidget(Request $request){

        $updateId = $this->mainModel->upd($request->all());

        if ( is_numeric($updateId) ) {
            return response( $this->mainModel->getOne( ['id'=>$updateId] ) );
        } else {
            return response($updateId, 201);
        }
    }

    public function deleteWidget(Request $request){
//        p( $request->all() );

        $updateId = $this->mainModel->deleteOne(['id'=>$request->id]);

        if ( !$updateId ) {
            return response('Error', 201);
        } else {
            return response(['id'=>$request->id], 200);
        }
    }

    /**
     * validates request form meta fields
     * if validation fails this->error will return exact error
     */
    public function validateRequest($request = [], $taxonomyType = '')
    {
        $request = $request->all();
        $taxonomySettings = config('adminpanel.taxonomy.'.$taxonomyType);
        $locales = config('app.locales');
        if(!$taxonomySettings)return false;

        foreach ($taxonomySettings['fields'] as $k=>$v)
        {
            if( !$v['required'] )continue;

            if( $v['translate'] ){
                foreach ($locales as $kk=>$vv){

                    if( !_cv($request, "{$k}_{$kk}") ) {
                        $this->error["{$k}_{$kk}"] = "{$k}".tr(' field is required!');
                    }
                }

            }else if( !_cv($request, $k) ){
                $this->error[$k] = $k.tr(' field is required!');
            }
        }

        return false;

    }

}
