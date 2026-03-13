<?php
namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use App;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Admin\SiteMapModel;
use App\Models\Admin\PageModel;


class SiteMap extends Controller
{
    //

    public function getOne($id)
    {


        return response([]);
    }

    public function index($id)
    {

        return response([]);
    }

    /**
     * Insert or update a sitemap menu node.
     *
     * On insert (no 'id' in request), immediately sets the new node's sort
     * position to its own id, then regenerates the menu cache once after both
     * writes complete. On update, upd() regenerates the cache internally.
     * Response is served from the cache — no extra DB query.
     *
     * @param  Request $request  All sitemap node fields; see SiteMapModel::upd().
     * @return \Illuminate\Http\Response  Full menu list from cache.
     */
    public function updMenu(Request $request)
    {
//        p($request->all());
        $content_group = 'menu';
        $menuItem = new SiteMapModel();
        $idItem = $menuItem->upd($request->all());

        if(!$request->id && $idItem){
            $menuItem->updateSortAndNesting([ 'id'=>$idItem, 'sort'=>$idItem ]);
        }

//        p($idItem);

//        $rr = $menuItem->getOne([ 'id' => $idItem ]);
        $rr = $menuItem->getListBy();

        return response($rr);
    }

    public function sortMenu(Request $request)
    {

        $menuItem = new SiteMapModel();
        $menuItem->sortMenu($request->all());

//        $rr = $menuItem->getOne([ 'id' => $request->id ]);
        $tmp = $menuItem->getListBy();

        return response($tmp);
    }

    public function setHomePage(Request $request)
    {
        $menuItem = new SiteMapModel();
        $menuItem->setMenuHomePage( $request->all() );

        $rr = $menuItem->getListBy();
//        return response([]);
        return response($rr);
    }

    public function deleteMenu(Request $request)
    {
        $menuItem = new SiteMapModel();
//        dd($request);
        $menuItem->deleteMenuNode( $request->menuId );
//dd('asd');
        $rr = $menuItem->getListBy();
        return response($rr);
    }

    public function deleteMenuImage(Request $request)
    {
        $menuItem = new SiteMapModel();
        $ret = $menuItem->deleteMenuMedia( ['menu_id'=>$request->menuId, 'field'=>$request->field] );

        return response($ret);
    }

    public function updContentTypeSettings(Request $request)
    {
        $pages = new PageModel();
        $content_group = "content_type_settings_".$request->contentType;

        $this->updOptions($content_group, $request->settings);
        $ret = $pages->getContentTypes();
        return response($ret);
    }

    public function updSiteConfigurations(Request $request)
    {
        $pages = new PageModel();
        $content_group = "site_configurations";
        $ret = $this->updOptions($content_group, $request->settings);
        return response($ret);
    }



    public function updOptions($contentGroup = '', $settings = [])
    {

        $content_group = $contentGroup;
        $options = new SiteMapModel();
        $oldSettings = $options->getListBy(['content_group'=>$content_group]);

        foreach ($settings as $k=>$v){
            $upd = [];

            foreach ($oldSettings as $kk=>$vv){
                if($vv['key'] != $k) continue;
                $upd = $vv;
                break;
            }

            $upd['key'] = $k;
            $upd['value'] = $v;
            $options->upd($upd, $content_group);

        }

        $updatedSettings = $options->getListBy(['content_group'=>$content_group]);

        return $updatedSettings;
    }

}
