<?php

namespace App\Http\Controllers\BuildingDevelopment;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use App\Models\BuildingDevelopment\IndividualProjectModel;
use App\Models\BuildingDevelopment\ProjectModel;
use App\Models\BuildingDevelopment\BlockModel;
use App\Models\BuildingDevelopment\FloorModel;
use App\Models\BuildingDevelopment\FlatModel;
use App\Models\BuildingDevelopment\TypeModel;

/**
 * main controller for all the content types
 */
class BdAdminMain extends Controller
{


    //
    protected $mainModel;
    protected $error = false;

    /** development module index */
    public function getIndx(Request $request)
    {
        $ret['conf'] = config('adminBuildingDevelopment');
        return response($ret);
    }

    public function getMenuData(Request $request)
    {
//p($request->all());
        $locales = config('app.locales');

        $params['translate'] = array_key_first($locales);
        $params['status'] = ['published'];
        $params['limit'] = 100;
        $params['orderField'] = 'updated_at';

        $projectModel = new IndividualProjectModel();
        $ret['individualProjects'] = $projectModel->getList($params);

        $projectModel = new ProjectModel();
        $ret['projects'] = $projectModel->getList($params);

        if($request->projects){
            $params['project_id'] = $request->projects;
        }

        $blockModel = new BlockModel();
        $ret['blocks'] = $blockModel->getList($params);

//        $floorModel = new FloorModel();
//        $ret['floors'] = $floorModel->getList($params);

        if($request->blocks){
            $params['block_id'] = $request->blocks;
        }

        $params['limit'] = 500;
        $flatModel = new FlatModel();
        $ret['flats'] = $flatModel->getList($params);

        return response($ret);
    }

    public function getCurrentContentData($params = [])
    {
        $ret = [];
        if(!isset($params['translate'])){
            $locales = config('app.locales');
            $params['translate'] = array_key_first($locales);
        }

        $params['status'] = ['published'];

        if(_cv($params, 'individualProjects', 'ar')){
            $individualProjectModel = new IndividualProjectModel();
            $params['id'] = $params['individualProjects'];
            $ret['individualProjects'] = $individualProjectModel->getList($params);
        }

        if(_cv($params, 'projects', 'ar')){
            $projectModel = new ProjectModel();
            $params['id'] = $params['projects'];
            $ret['projects'] = $projectModel->getList($params);
        }

        if(_cv($params, 'blocks', 'ar')){
            $blockModel = new BlockModel();
            $params['id'] = $params['blocks'];
            $ret['blocks'] = $blockModel->getList($params);
        }

        if(_cv($params, 'flats', 'ar')){
            $flatModel = new FlatModel();
            $params['id'] = $params['flats'];
            $ret['flats'] = $flatModel->getList($params);
        }

        return $ret;
    }

    /** IndividualProject */
    public function getIndividualProjects(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $request->merge(['translate'=>$locale, 'limit'=>1000, 'status'=>['published', 'hidden']]);

        $projectModel = new IndividualProjectModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }
    public function getIndividualProject(Request $request)
    {
        $projectModel = new IndividualProjectModel();
        $ret = $projectModel->getOne($request->all());
        return response($ret);
    }
    /** update/create IndividualProject */
    public function updateIndividualProject(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $projectModel = new IndividualProjectModel();
        $res = $projectModel->updItem($request->all());
        if(!is_numeric($res))return response($res, 201);

        $ret = $projectModel->getOne(['id'=>$res, 'translate'=>$locale]);
        $itemRaw = $projectModel->getOne(['id'=>$res]);

        return response(['listable'=>$ret, 'rawItem'=>$itemRaw]);
    }
    public function deleteIndividualProject(Request $request)
    {

        $projectModel = new IndividualProjectModel();
        $res = $projectModel->deleteItem($request->all());

        if($res){
            return response(['id'=>$res]);
        }else{
            return response(['status'=>false]);
        }

    }



    /** projects */
    /** projects */
    /** projects */
    /** projects */
    public function getProjects(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $request->merge(['translate'=>$locale, 'limit'=>1000, 'status'=>['published', 'hidden']]);

        $projectModel = new ProjectModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }
    public function getProject(Request $request)
    {
        $projectModel = new ProjectModel();
        $ret = $projectModel->getOne($request->all());
        return response($ret);
    }
    /** update/create projects */
    public function updateProject(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $projectModel = new ProjectModel();
        $res = $projectModel->updItem($request->all());
        if(!is_numeric($res))return response($res, 201);

        $ret = $projectModel->getOne(['id'=>$res, 'translate'=>$locale]);
        $itemRaw = $projectModel->getOne(['id'=>$res]);

        return response(['listable'=>$ret, 'rawItem'=>$itemRaw]);
    }
    public function deleteProject(Request $request)
    {

        $projectModel = new ProjectModel();
        $res = $projectModel->deleteItem($request->all());

        if($res){
            return response(['id'=>$res]);
        }else{
            return response(['status'=>false]);
        }

    }



     /** blocks */
    public function getBlocks(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $request->merge(['translate'=>$locale, 'limit'=>1000, 'status'=>['published', 'hidden']]);

        $projectModel = new BlockModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }
    public function getBlock(Request $request)
    {

//        p($request->all());
        $projectModel = new BlockModel();
        $ret = $projectModel->getOne($request->all());
        return response($ret);
    }
    public function updateBlock(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);


        $projectModel = new BlockModel();
        $res = $projectModel->updItem($request->all());
        if(!is_numeric($res))return response($res, 201);
        $ret = $projectModel->getOne(['id'=>$res, 'translate'=>$locale]);
        $itemRaw = $projectModel->getOne(['id'=>$res]);


        return response(['listable'=>$ret, 'rawItem'=>$itemRaw]);
    }
    public function deleteBlock(Request $request)
    {

        $projectModel = new BlockModel();
        $res = $projectModel->deleteItem($request->all());

        if($res){
            return response(['id'=>$res]);
        }else{
            return response(['status'=>false]);
        }

    }


     /** floors */
    public function getFloors(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $request->merge(['translate'=>$locale, 'limit'=>1000, 'status'=>['published', 'hidden']]);

        $projectModel = new FloorModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }
    public function getFloor(Request $request)
    {

//        p($request->all());
        $projectModel = new FloorModel();
        $ret = $projectModel->getOne($request->all());
        return response($ret);
    }
    public function updateFloor(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);


        $projectModel = new FloorModel();
        $res = $projectModel->updItem($request->all());
        if(!is_numeric($res))return response($res, 201);
        $ret = $projectModel->getOne(['id'=>$res, 'translate'=>$locale]);
        $itemRaw = $projectModel->getOne(['id'=>$res]);


        return response(['listable'=>$ret, 'rawItem'=>$itemRaw]);
    }
    public function deleteFloor(Request $request)
    {

        $projectModel = new FloorModel();
        $res = $projectModel->deleteItem($request->all());

        if($res){
            return response(['id'=>$res]);
        }else{
            return response(['status'=>false]);
        }

    }

     /** flats */
    public function getFlats(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $request->merge(['translate'=>$locale, 'limit'=>1000, 'status'=>['published', 'hidden']]);

        $projectModel = new FlatModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }
    public function getFlat(Request $request)
    {

//        p($request->all());
        $projectModel = new FlatModel();
        $ret = $projectModel->getOne($request->all());
        return response($ret);
    }
    public function updateFlat(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);


        $projectModel = new FlatModel();
        $res = $projectModel->updItem($request->all());
        if(!is_numeric($res))return response($res, 201);
        $ret = $projectModel->getOne(['id'=>$res, 'translate'=>$locale]);
        $itemRaw = $projectModel->getOne(['id'=>$res]);


        return response(['listable'=>$ret, 'rawItem'=>$itemRaw]);
    }
    public function deleteFlat(Request $request)
    {

        $projectModel = new FlatModel();
        $res = $projectModel->deleteItem($request->all());

        if($res){
            return response(['id'=>$res]);
        }else{
            return response(['status'=>false]);
        }

    }

     /** types */
    public function getTypes(Request $request)
    {

        $locales = config('app.locales');
        $locale = array_key_first($locales);

        $request->merge(['translate'=>$locale, 'limit'=>1000, 'status'=>['published', 'hidden']]);

        $projectModel = new TypeModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }
    public function getType(Request $request)
    {

//        p($request->all());
        $projectModel = new TypeModel();
        $ret = $projectModel->getOne($request->all());
        return response($ret);
    }
    public function updateType(Request $request)
    {
        $locales = config('app.locales');
        $locale = array_key_first($locales);


        $projectModel = new TypeModel();
        $res = $projectModel->updItem($request->all());
        if(!is_numeric($res))return response($res, 201);
        $ret = $projectModel->getOne(['id'=>$res, 'translate'=>$locale]);
        $itemRaw = $projectModel->getOne(['id'=>$res]);


        return response(['listable'=>$ret, 'rawItem'=>$itemRaw]);
    }
    public function deleteType(Request $request)
    {

        $projectModel = new TypeModel();
        $res = $projectModel->deleteItem($request->all());

        if($res){
            return response(['id'=>$res]);
        }else{
            return response(['status'=>false]);
        }

    }

    public function importData(Request $request)
    {
        $res = false;
        if($request->importInto === 'projects'){
            $projectModel = new ProjectModel();
            $res = $projectModel->importData($request->all());

        }elseif ($request->importInto === 'blocks'){
            $projectModel = new BlockModel();
            $res = $projectModel->importData($request->all());

        }elseif ($request->importInto === 'floors'){
            $projectModel = new FloorModel();
            $res = $projectModel->importData($request->all());

        }elseif ($request->importInto === 'flats'){
            $projectModel = new FlatModel();
            $res = $projectModel->importData($request->all());

        }elseif ($request->importInto === 'types'){
            $projectModel = new TypeModel();
            $res = $projectModel->importData($request->all());
        }

//        p($res);
        if($res && !_cv($res, 'message')){
            return response($res);
        }else{
            return response(['status'=>false, 'message'=>_cv($res, 'message')], 201);
        }

    }






}
