<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use App\Models\Admin\PageModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use App\Models\Media\MediaModel;
use App\Models\Admin\ContentLogModel;

/**
 * main controller for all the content types
 */
class Page extends Controller
{

    //
    protected $mainModel;
    protected $fieldConfigs;
    protected $error = false;

    public function __construct()
    {
        $this->mainModel = new PageModel();
    }

    public function getPagesTranslated(Request $request)
    {

        $res = $this->mainModel->getOne(['id'=>1]);

        if ($res) {
            return response($res);
        } else {
            return response('Error', 201);
        }
    }

    public function getContentLog(Request $request){
        $logModel = new ContentLogModel();
        $res = $logModel->getList($request->all());

        if ($res) {
            return response( $res );
        } else {
            return response('Error', 201);
        }
    }


    public function getPageTitles(Request $request)
    {
        $locale = requestLan();

        $params = [
            'translate' => $locale,
            'limit' => 550,
            'orderBy' => 'date',
            'status' => 'published',
            'orderByDirection' => 'desc',
            'fields'=>['slug', 'id', 'content_type', 'title']
        ];

        if($request->content_type){
            $params['whereRaw'][] = " pages.content_type in ('{$request->content_type}')";
            $params['fieldConfigs'] = config("adminpanel.content_types.{$request->content_type}");
        }

        $list = $this->mainModel->getList($params);

        $ret = _cv($list, 'list.0.id')?$list['list']:[];

        if (is_array($ret)) {
            return response($ret);
        } else {
            return response('Error', 201);
        }
    }

    public function getPages(Request $request)
    {

        if(!$request->contentType)return response('Content Type not set', 201);

        $locale = requestLan();
        $titleFields = listViewFields($request->contentType, $locale);
//        p($titleFields);

        $res = $this->mainModel->getPages([
            'meta_keys'=>$titleFields,
            'contentType' => $request->contentType,
            'limit'=>10000,
            'translate'=>$locale,
        ]);

        if (!empty($res['list'])) {
            return response($res['list']);
        } else {
            return response('Error', 204);
        }
    }

    public function updPage(Request $request)
    {
        $updateId = $this->mainModel->updPage($request->all());

        if(!$updateId) return response($this->error, 201);

        return response($this->mainModel->getOne(['id'=>$updateId]));
    }

    public function deletePageMedia()
    {
        $request = Request();

//        p($request->all());
        $tmp = new PageModel();
//        $tmp->deleteMediaFromPage(['key'=>$request->key, 'file_id'=>$request->file_id, 'data_id'=>$request->data_id]);

//        $tmpp = new MediaModel();
//        $tmpp->deleteMediaFile($request->file_id);

        return response([]);

    }

    public function deletePage(Request $request)
    {
        $updateId = $this->mainModel->deletePage(['id'=>$request->id]);
        return response($updateId);
    }

    public function setPageStatus(Request $request)
    {
        $updateId = $this->mainModel->updPage($request->all());
        $ret = $this->mainModel->getPage(['id'=>$updateId, 'contentType'=>$request->contentType]);
        return response($ret);
    }

    public function getPage(Request $request){

        if(!$request->id)return response('Page id not set', 201);

        $content = $this->mainModel->getPage($request->all());

        return response($content);
    }

    public function updListSort(Request $request){

        if(!$request->contentType)return response('Content Type not set', 201);
        $this->mainModel->updListSort($request->sortedList, $request->listParams);
        $res = $this->mainModel->getList(['content_type'=>$request->contentType, 'page_status'=>'all', 'raw'=>1 ]);

        if ($res) {
            return response( $res );
        } else {
            return response('Error', 201);
        }
    }

    public function setSingleSort(Request $request){
        DB::table('pages')->where('id', $request->id)->update(['sort'=>$request->sort]);
        return response($request->id, 200);
    }

    /**
     * validates request form meta fields
     * if validation fails this->error will return exact error
     */
    public function validateContentTypeRequest($request = [], $contentType = '')
    {
        $request = $request->all();
        $contentTypeSettings = config('adminpanel.content_types.'.$contentType);
        $locales = config('app.locales');
        if(!$contentTypeSettings)return false;

        foreach ($contentTypeSettings['fields'] as $k=>$v)
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

    public function generateLinks($params = [])
    {
        $list = DB::table('sitemap')->select('id', 'fullpath', 'secondary_data')->get();

        foreach ($list as $item) {
            $item->secondary_data = _psqlCell(_toArray($item->secondary_data));
            if (!empty($item->secondary_data) && is_array($item->secondary_data)) {
                foreach ($item->secondary_data as $data) {
                    if (!empty($data['page']) && is_array($data['page'])) {
                        if (count($data['page']) === 1 && $data['page'][0] == $params['content_id']) {
                            return [$item->fullpath];
                        }
                    }
                }
            }
        }

        $sitemapPath = DB::table('options')
            ->leftJoin('sitemap', 'options.value', '=', 'sitemap.id')
            ->where('options.content_group', 'content_type_settings_' . $params['content_type'])
            ->get()
            ->groupBy('key')
            ->map->first();

        if (!$sitemapPath->isEmpty() && isset($sitemapPath['defaultSingleRoute'])) {

            $notSingleView = (bool) ($sitemapPath['NoSingleView']->value ?? 0);

            return $notSingleView ? [$sitemapPath['defaultSingleRoute']->fullpath] : [$sitemapPath['defaultSingleRoute']->fullpath . '/' . $params['content_id'] . '-' . $params['slug']];
        }else{
            $noSingleView = DB::table('options')
                ->where('options.content_group', 'content_type_settings_' . $params['content_type'])
                ->where('options.key', 'NoSingleView')
                ->where('options.value', '=', 1)
                ->pluck('options.value')
                ->first();
            $itemPath = [];
            foreach ($list as $item) {
                $secondaryData = $item->secondary_data;

                if (!empty($secondaryData) && is_array($secondaryData)) {
                    foreach ($secondaryData as $data) {
                        if (!empty($data['contentType']) && $data['contentType'] == $params['content_type'] && $noSingleView == 1) {
                            $itemPath[] = $item->fullpath;
                        }
                    }
                }
            }
            return $itemPath;
        }
    }
}
