<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Models\Admin\OptionsModel;
use Illuminate\Routing\Controller;
use App\Models\Shop\AttributeModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;



class Attributes extends Controller
{
    //


    protected $mainModel;
    protected $error = false;

    public function __construct()
    {
//        parent::__construct();
        $this->mainModel = new AttributeModel();
    }

    public function getAttributes(Request $request){
        if(!$request->attributeType)return response('Error', 201);

        $request->request->add(['attribute'=>$request->attributeType]);
//p( $request->all() );
        $res = $this->mainModel->getList($request->all());

        if (is_array($res)) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function getAttribute(Request $request){

        $res = $this->mainModel->getOne($request->all());

        if (is_array($res)) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function updTerm(Request $request){

//        $this->validateAttributeRequest(['attributeTypeId'=>$request->attribute], $request->all());

//        if($this->error)return response($this->error, 201);

        $updateId = $this->mainModel->upd($request->all());
        $ret['raw'] = $this->mainModel->getOne( ['id'=>$updateId] );
        $ret['localized'] = $this->mainModel->getOne( ['id'=>$updateId, 'translate'=>1] );

        return response( $ret );
    }

    public function deleteAttribute(Request $request){
//        p( $request->all() );

        $updateId = $this->mainModel->deleteTerm(['id'=>$request->id]);

        if ( !$updateId ) {
            return response('Error while deleting term', 201);
        } else {
            return response(['id'=>$updateId]);
        }
    }

    public function updSort(Request $request){

        $status = $this->mainModel->updSort($request->sordData);
        $attributeId = _cv($request->all(), 'routeQuery.attributeType');

        $rr = [];
        if($attributeId){
            $rr = $this->mainModel->getList(['attribute'=>_cv($request->all(), 'routeQuery.attributeType')]);
        }

//p($rr);
        if ( !$status ) {

            return response('Error while update attribute sort', 201);
        } else {
            return response( $rr );
        }
    }

    public function getAttributeTypes(Request $request){

        $options = new OptionsModel();
        $request->merge(['content_group' => 'shop_attribute_type']);
        $res = $options->getListByRaw($request->all());
        return response( $res );

    }



    /**
     * validates request form meta fields
     * if validation fails this->error will return exact error
     */
    public function validateAttributeRequest($params = [], $request=[])
    {

        $options = new OptionsModel();
        $attrType = $options->getOne(['id'=>$params['attributeTypeId']]);
//        p($attrType);

        $attributeFields = $attrType['fields'];
        $locales = config('app.locales');

        foreach ($attributeFields as $k=>$v)
        {
            if( !$v['required'] )continue;

            if( $v['translate'] ){
                foreach ($locales as $kk=>$vv){

                    if( !_cv($request, "{$v['name']}_{$kk}") ) {
                        $this->error["{$v['name']}_{$kk}"] = "{$v['name']}".tr(' field is required!');
                    }
                }

            }else if( !_cv($request, $v['name']) ){
                $this->error[$v['name']] = $v['name'].tr(' field is required!');
            }
        }

        return false;

    }

}
