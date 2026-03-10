<?php
namespace App\Http\Controllers\Admin;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use App;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Admin\OptionsModel;
use App\Models\Admin\PageModel;


class Options extends Controller
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
        $content_group = "site_configurations";

        if ($request -> content_group)
        {
            $content_group = $request -> content_group;
        }

        $ret = $this->updOptions($content_group, $request->settings);
        return response($ret);
    }



    /**
    update single option
     * delete old option and set new one as updated
     */
    public function updOption(Request $request)
    {

        $validator = \Validator::make($request->all(), [
            'key' => ['required'],
            'value' => ['required'],
        ]);

        if ($validator->fails()) {
            return response(['success'=>false,'message'=>$validator->errors()->first()]);
        }

        $content_group = $request->contentGroup?$request->contentGroup:'general';
        $options = new OptionsModel();
//        p($request->all());
        $upd = $options->updSetting(['content_group'=>$content_group, 'key'=>$request->key, 'id'=>$request->id, 'value'=>$request->value]);


        $ret = $options->getSetting(false, $content_group, ['id'=>$upd, 'return'=>'raw']);

        return response($ret);
    }

    public function getOptions(Request $request)
    {
        $options = new OptionsModel();

        $res = $options->getListByRaw($request->all());


        return $res;
    }

    public function getOption(Request $request)
    {

        $validator = \Validator::make($request->all(), [
            'key' => ['required_without:contentGroup','string'],
            'contentGroup' => ['required_without:key','string'],
        ]);

        if ($validator->fails()) {
            return response(['success'=>false,'message'=>$validator->errors()->first()]);
        }

        $content_group = $request->contentGroup?$request->contentGroup:'general';
        $options = new OptionsModel();
        $upd = $options->getSetting($request->key, $content_group, $request->all());

        return $upd;
    }

    public function updOptions($contentGroup = '', $settings = [])
    {

        $content_group = $contentGroup;
        $options = new OptionsModel();
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

    public function deleteOption(Request $request)
    {

        $validator = \Validator::make($request->all(), [
            'key' => ['required'],
            'id' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response(['success'=>false,'message'=>$validator->errors()->first()]);
        }

        $options = new OptionsModel();

        $updatedSettings = $options->deleteOption($request->all());

        return response($updatedSettings);
    }



    public function updOptionsRaw($contentGroup = '', $settings = [])
    {
        if(!$contentGroup)return false;
        $content_group = $contentGroup;
        $options = new OptionsModel();

        foreach ($settings as $k=>$v){
            $upd = [ 'content_group'=>$content_group, 'key'=>$k ];

            $upd['value'] = $v;
            $options->updSetting($upd, $content_group);

        }

        $updatedSettings = $options->getKeyValListBy(['content_group'=>$content_group, 'rawList'=>1]);

        return $updatedSettings;
    }

    public function updOptionRaw($contentGroup = '', $settings = [])
    {
        if(!$contentGroup)return false;
        $content_group = $contentGroup;
        $options = new OptionsModel();

        foreach ($settings as $k=>$v){
            $upd = [ 'content_group'=>$content_group, 'key'=>$k ];

            $upd['value'] = $v;
            $options->updSetting($upd, $content_group);

        }

        $updatedSettings = $options->getKeyValListBy(['content_group'=>$content_group, 'rawList'=>1]);

        return $updatedSettings;
    }

}
