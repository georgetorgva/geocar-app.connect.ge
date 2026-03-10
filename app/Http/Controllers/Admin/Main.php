<?php

namespace App\Http\Controllers\Admin;

//use http\Exception;
use App\Http\Controllers\Shippings\Shipping;
use App\Models\Admin\AttributeModel;
use App\Models\Admin\OrderModel;
use App\Models\Admin\SiteMapModel;
use App\Models\Admin\StockModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use App;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Admin\OptionsModel;
use App\Models\Admin\PageModel;
use Illuminate\Support\Facades\DB;
use App\Http;
use App\Models\Admin\ProductsModel;
use App\Models\Admin\FormBuilderModel;
use App\Models\Admin\TaxonomyModel;
use Illuminate\Support\Facades\Cache;

class Main extends App\Http\Controllers\Api\ApiController
{
    //
    public function index(Request $request){

        $menuItem = new SiteMapModel();
        $pages = new PageModel();
        $options = new OptionsModel();
        $taxonomy = new TaxonomyModel();
        $FormBuilderModel = new FormBuilderModel();



        $ret['alerts'] = []; //$pages->getAlerts();

        $ret['adminMenu'] = config('adminpanel.admin_menu');
        $ret['sitePlaceHolders'] = config('app.sitePlaceHolders');
        $ret['siteMenus'] = config('adminpanel.site_menus'); /// site menu places
//        $ret['shipping'] = $shipping->getShippingConfigs();
        $ret['website_menu_custom_configs'] = config('adminpanel.website_menu_custom_configs');
        $ret['locale'] = config('app.locale');
        $ret['locales'] = config('app.locales');
//        $ret['contentTypes'] = config('adminpanel.content_types');
        $ret['contentTypes'] = $pages->getContentTypes();
        $ret['taxonomy'] = config('adminpanel.taxonomy');
        $ret['terms'] = $this->getAllTerms();

        $ret['contentSeoFields'] = config('adminpanel.content_seo_fields');
        $ret['layouts'] = config('adminpanel.layouts');
        $ret['smartLayouts'] = config('adminpanel.smartLayouts');
        $ret['smartComponents'] = config('adminpanel.smartComponents');

        $ret['currency'] = config('app.currency');

        $ret['menus'] = $menuItem->getAllMenus();
        $ret['site_configurations'] = $options->getListBy(['content_group'=>'site_configurations']);
        $ret['static'] = config('filesystems.disks.public.url');
        $ret['site_configs'] = config('siteconfigs');
        $ret['adminpanel'] = config('adminpanel');

        $ret['formNames'] = $FormBuilderModel->getFormNames();

        $ret['currency'] = config('app.currency');

        /// auth apis
        $ret['preview_token'] = md5(date('Ymd').env('APP_KEY'));

        $user = auth()->user();
        $userPermissions= DB::table('role_has_permissions')->where('role_id', $user->role)->get();
        $ret['userInfo'] = [ 'fullname'=>$user->fullname, 'phone'=>$user->phone, 'email'=>$user->email , 'permissions'=>$userPermissions];

        return response($ret);
    }

    public function indx(){
        $value = Cache::store('file')->get('AdminPanelConfig');
        if($value) return $value;

        $option = new OptionsModel;

        $captchaKey = $option->getSetting('website_captcha_key');
        $captchaStatus = $option->getSetting('captcha_status');

        $res = ['captchaKey'=> $captchaKey, 'captchaStatus'=>_cv($captchaStatus, 'value')];

        Cache::put('AdminPanelConfig', $res, 9999999);

        return response($res);
    }
    public function mainSearch(Request $request)
    {
        p($request->all());
        if(!$request->searchWord)return response([]);

            $ret['results'] = DB::select( DB::raw("SELECT pages.id, pages.content_type, pages_meta.key, pages_meta.val, title.val as title FROM pages_meta
                                        left join pages ON pages.id = pages_meta.data_id
                                        left join pages_meta as title ON title.data_id = pages.id and title.key='title_ge'
                                        where pages_meta.val like '%{$request->searchWord}%'
                                        group by pages.id
                                        limit 20
                                        ") ); //->keyBy('content_type');
        $ret['results'] = json_decode(json_encode($ret['results']), 1);

//p( $ret['results'] );
        foreach ($ret['results'] as $k=>$v){
            $tmp = json_decode($v['val'], 1);
//p($tmp);
            if(!$tmp)continue;
            $ret['results'][$k]['val'] = serchRecursive($tmp, $request->searchWord);

        }

        return response($ret);
    }

    public function dashboard()
    {


//        $ret['entryCounts'] = DB::select( DB::raw("SELECT content_type, count(*) as entry_counts FROM `pages` group by content_type") ); //->keyBy('content_type');
        $ret['entryCounts'] = DB::table('pages')->select( DB::raw("content_type, count(*) as entry_counts") )->groupBy('content_type')->get()->keyBy('content_type');

//p($ret);

        return response($ret);
    }


    /** form builder */
    public function updFormBuilderForm(Request $request)
    {

        $model = new FormBuilderModel();
        $formId = $model->upd($request->all());
        $formData = $model->getOne(['id'=>$formId]);

        return response($formData);
    }
    public function getFormBuilderForm(Request $request)
    {
        $model = new FormBuilderModel();
        if(is_numeric($request->id)){
            $formData = $model->getOne(['id'=>$request->id]);
        }else{
            $formData = $model->getOne(['form_name'=>$request->form_name]);
        }
        return response($formData);
    }
    public function getFormBuilderForms(Request $request)
    {
        $model = new FormBuilderModel();

        $formData = $model->getFormNames();
        return response($formData);
    }



    /** redirections */
    public function getRedirections(Request $request){
        $model = new App\Models\Admin\RedirectionsModel();

        $formData = $model->getBy();
        return response($formData);
    }
    public function updRedirections(Request $request){
        $model = new App\Models\Admin\RedirectionsModel();

        $formData = $model->upd($request->all());

        $ret = $model->getOne(['id'=>$formData]);
        return response($ret);
    }
    public function deleteRedirections(Request $request){
        $model = new App\Models\Admin\RedirectionsModel();

        $formData = $model->deleteItem($request->all());

        return response($formData);
    }

    public function getAllTerms(){
        $terms = [];
        $taxonomy = config('adminpanel.taxonomy');
        $taxModel = new TaxonomyModel();

        foreach ($taxonomy as $k=>$v){
            $terms[$k] = $taxModel->getList(['taxonomy' => $k, 'translate'=>1]);
        }

        return $terms;

    }



}
