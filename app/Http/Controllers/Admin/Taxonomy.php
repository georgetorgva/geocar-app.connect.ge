<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\TaxonomyModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;



class Taxonomy extends Model
{
    //


    protected $mainModel;
    protected $error = false;

    public function __construct()
    {
        parent::__construct();
        $this->mainModel = new TaxonomyModel();
    }

    public function getTerms(Request $request){

        $res = $this->mainModel->getList(['taxonomy' => $request->taxonomy]);

        if ($res) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function updTerm(Request $request){
//        print_r( $request->all() );

        $this->validateTaxonomyRequest($request, $request->taxonomy);
//        print_r($this->error);
        if($this->error)return response($this->error, 201);

        $updateId = $this->mainModel->upd($request->all());

        if ( $this->mainModel->error ) {
            return $this->fail($this->mainModel->validation->getErrors(), 201);
        } else {
            return response( $this->mainModel->getOne( ['id'=>$updateId] ) );
        }
    }

    public function deleteTerm(Request $request){
//        p( $request->all() );

        $taxonomy = $this->mainModel->getTaxonomyById($request->itemId);
        $updateId = $this->mainModel->deleteTerm(['id'=>$request->itemId]);

        if ( !$updateId ) {
            return response('Error while deleting term', 201);
        } else {
            return $this->mainModel->getList([ 'taxonomy' => $taxonomy ]);
        }
    }

    public function updSort(Request $request){

        $this->mainModel->updSort($request->sordData);

        $rr = $this->mainModel->getList();


        if ( $this->mainModel->error ) {

            return response('Error while update sort term', 201);
        } else {
            return response( $rr );
        }
    }

    /**
     * validates request form meta fields
     * if validation fails this->error will return exact error
     */
    public function validateTaxonomyRequest($request = [], $taxonomyType = '')
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
