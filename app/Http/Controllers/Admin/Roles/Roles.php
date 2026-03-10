<?php

namespace App\Http\Controllers\Admin\Roles;

use App\Models\Admin\Roles\RoleModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;



class Roles extends Model
{
    //


    protected $mainModel;
    protected $error = false;

    public function __construct()
    {
        parent::__construct();
        $this->mainModel = new RoleModel();
    }

    public function GetRoles(Request $request){

        $res = $this->mainModel->getList();
//        return $res;
        if ($res || sizeof($res)!=-1) {
            return response(['roles'=>$res]);
        } else {
            return response('Error', 201);
        }
    }
//    public function getAttrList(Request $request){
//        $slug = $request->slug;
//        $rr = config('adminshop.attributes');
//        $arr =[];
//        foreach ($rr as $k=>$item) {
//            if($item['sub_attribute'] && $k!=$slug){
//                $arr[]=$k;
//            }
//        }
//        return response()->json(['data'=>$arr]);
////        dd($arr);
//    }
    public function UpdateData(Request $request){
        $updateId = $this->mainModel->upd($request->all());
        return response( $this->mainModel->getOne( ['id'=>$updateId] ) );
    }

    public function DeleteData(Request $request){
        $updateId = $this->mainModel->deleteData(['id'=>$request->id]);
//        return response($request);
        if ( !$updateId ) {
            return response('Error while deleting role', 201);
        } else {
            return $this->mainModel->getList();
        }
    }

}
