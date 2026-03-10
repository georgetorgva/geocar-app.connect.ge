<?php

namespace App\Http\Controllers\BuildingDevelopment;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use App\Models\BuildingDevelopment\ProjectModel;
use App\Models\BuildingDevelopment\BlockModel;
use App\Models\BuildingDevelopment\FloorModel;
use App\Models\BuildingDevelopment\FlatModel;
use App\Models\BuildingDevelopment\TypeModel;

/**
 * main controller for all the content types
 */
class BdViewMain extends Controller
{


    //
    protected $mainModel;
    protected $error = false;

    public function getCurrentContentData($params = [])
    {

        if(!isset($params['translate'])){
            $params['translate'] = requestLan();
        }

        $params['status'] = ['published'];

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

    /** projects */
    public function getProjects(Request $request)
    {
        $locale = requestLan();

        $request->merge(['translate'=>$locale, 'status'=>['published']]);

        $projectModel = new ProjectModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }

     /** blocks */
    public function getBlocks(Request $request)
    {
        $locale = requestLan();

        $request->merge(['translate'=>$locale, 'status'=>['published']]);

        $projectModel = new BlockModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }

     /** floors */
    public function getFloors(Request $request)
    {
        $locale = requestLan();

        $request->merge(['translate'=>$locale, 'status'=>['published']]);

        $projectModel = new FloorModel();
//        p($request->all());

        $ret = $projectModel->getList($request->all());
        return response($ret);
    }

     /** flats */
    public function getFlats(Request $request)
    {
        $locale = requestLan();

        $limit = $request->limit && is_numeric($request->limit)?$request->limit:5000;
        $request->merge(['translate'=>$locale, 'limit'=>$limit, 'status'=>['published']]);

        $projectModel = new FlatModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }

     /** types */
    public function getTypes(Request $request)
    {
        $locale = requestLan();

        $request->merge(['translate'=>$locale, 'status'=>['published']]);

        $projectModel = new TypeModel();
        $ret = $projectModel->getList($request->all());
        return response($ret);
    }

}
