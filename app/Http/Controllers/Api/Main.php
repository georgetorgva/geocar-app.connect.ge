<?php

namespace App\Http\Controllers\Api;

use App;
use App\Http\Controllers\Admin\Page;
use App\Models\Admin\FormBuilderModel;
use App\Models\Admin\RedirectionsModel;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Validator;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Admin\PageModel;
use App\Models\Admin\StockModel;
use App\Models\Admin\WordsModel;
use App\Models\Admin\OptionsModel;
use App\Models\Admin\SiteMapModel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\TaxonomyModel;
use App\Http\Controllers\Admin\Shop;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\Media;
use Illuminate\Support\Facades\Cache;
use App\Models\Admin\OnlineFormsModel;
use App\Http\Controllers\Admin\Widgets;

use App\Http\Controllers\Admin\OnlineForms;
use App\Http\Controllers\Admin\BankServices;
use App\Http\Transformers\AddressTransformer;
use Illuminate\Support\Facades\Cookie;
use function Symfony\Component\HttpFoundation\getCookies;
//use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;


/**
 * @OA\Info(title="Connect CMRS system API", version="0.1")
 * @OA\Server(url=L5_SWAGGER_CONST_HOST)
 */

class Main extends App\Http\Controllers\Api\ApiController
{
    //
    /**
     *    @OA\Post( path="/view/main/indx", tags={"Public website data"}, summary="Returns general data to create system environment", operationId="index",
     *    @OA\Response( response="200", description="array" )
     * )
     */


    public function index()
    {
        $request = Request();
        $locale  = requestLan();

        // One cache entry per locale — never keyed by qr
        $cacheKey = 'apiIndx_' . $locale;
        $payload  = Cache::store('file')->get($cacheKey);

        if (!$payload) {
            $payload = $this->buildIndexPayload($locale);
            Cache::put($cacheKey, $payload, env('CACHE_INDX', 2));
        }

        // Injected after cache retrieval so it always reflects actual server time
        $payload['server_time'] = date('D M d Y H:i:s O');

        // GraphQL-like field selection applied after cache — never pollutes cache keys
        $response = apiShortener($payload, $request->qr);

        return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function buildIndexPayload(string $locale): array
    {
        $menuModel = new SiteMapModel();
        $content   = new PageModel();

        // Single query replaces three separate OptionsModel calls
        $optionRows = OptionsModel::select(['key', 'value', 'content_group'])
            ->where(function ($q) {
                $q->where('content_group', 'site_configurations')
                  ->orWhere('content_group', 'general');
            })
            ->get();

        $siteSettings = [];
        $cookies      = null;
        $xrates       = null;

        foreach ($optionRows as $row) {
            $val = _cv(_psqlRow(_toArray($row)), 'value');
            if ($row->content_group === 'site_configurations') {
                $siteSettings[$row->key] = $val;
                if ($row->key === 'xrates') $xrates = $val;
            } elseif ($row->key === 'thirdPartyScripts') {
                $cookies = _cv($val, ['description', $locale]);
            }
        }

        $menus = $menuModel->generateMenuForSite();

        return [
            'locale'           => $locale,
            'locales'          => config('app.locales'),
            'siteMenus'        => config('adminpanel.site_menus'),
            'sitePlaceHolders' => config('app.sitePlaceHolders'),
            'static'           => config('filesystems.disks.public.url'),
            'smartLayouts'     => config('adminpanel.smartLayouts'),
            'contentTypes'     => $content->getContentTypes([
                'exclude' => ['title', 'route', 'slug_field', 'searchable', 'taxonomy', 'fields', 'orderBy', 'relation'],
            ]),
            'menus'            => $menus,
            'thinMenu'         => $menuModel->thinMenu($menus, [
                'id'        => 'id',
                'title'     => "titles.{$locale}.title",
                'pid'       => 'pid',
                'url'       => 'fullUrls.fullUrlRelated',
                'configs'   => 'configs',
                'menu_type' => 'menu_type',
            ]),
            'siteSettings'     => $siteSettings,
            'cookies'          => $cookies,
            'xrates'           => $xrates,
        ];
    }

    /**
     *    @OA\Post( path="/view/main/indxTranslatable", tags={"Public website data"}, summary="General data localised", operationId="indxTranslatable",
     *    @OA\Response( response="200", description="{}" )
     * )
     */
    public function indxTranslatable(){
        $options = new OptionsModel();
        $content = new PageModel();
        $widgets = new Widgets();
        $forms = new App\Models\Admin\FormBuilderModel();
//        $shopSiteMain = new Shop\ShopSiteMain();


        $ret['server_time'] = date('D M d Y H:i:s O');
        $ret['locale'] = requestLan(); //config('app.locale');

        $cacheKey = 'apiIndxTranslatable_'.$ret['locale'];
        $value = Cache::store('file')->get($cacheKey);
        if($value) return $value;

        $ret['home_content'] = $this->getHomeContent();
//        $ret['terms'] = $this->getAllTerms();
        $ret['widgets'] = $widgets->getWidgetsForSite(['locale'=>$ret['locale']]);
        $ret['generalPages'] = $content->getList(['slug'=>['privacy', 'terms-condition']]);

        $ret['smartForms'] = $forms->getListBy();
//        $ret['productsCategories'] = $shopSiteMain->getCategories();

        $cookies = $options->getSetting( 'thirdPartyScripts', 'general' );
        $ret['cookies'] = _cv($cookies, ['description', $ret['locale']]);

//        $ret['categoryAttributeTypes'] = $options->getListBy(['content_group'=>'shop_attribute_type']);

//        $ret['attributes'] = $attributes->getAllTerms();
//        $ret['attribute_configs'] = config('adminshop.attributes');
//        $ret['products_configs'] = config('adminshop.products');
//        $ret['productsCategories'] = $shopSiteMain->getCategories();

        Cache::put($cacheKey, response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_INDX', 2));

        return response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function getWidgets($params = []){
        if(!_cv($params, 'name')) return response(['error'=>'widget name not set'], 201);

        $ret['locale'] = requestLan(); //config('app.locale');


        $widgets = new Widgets();

        $widgets = $widgets->getWidgetsForSite(['locale'=>$ret['locale'], 'name'=>_cv($params, 'name')]);
        return response($widgets);
    }

    public function getTerms($params = []){

        $taxonomies = _cv($params, 'taxonomy');
        if (!is_array($taxonomies)) {
            $taxonomies = [$params['taxonomy']];
        }
        if(!$taxonomies)return response(['error'=>'taxonomy not set'], 201);
        $terms = [];
        $taxModel = new TaxonomyModel();

        foreach ($taxonomies as $taxonomy) {
            // Fetch the list for the current taxonomy
            $taxonomyList = $taxModel->getList([
                'taxonomy' => $taxonomy,
                'translate' => 1,
                'selectFields' => "taxonomy.id, taxonomy.pid, taxonomy.slug"
            ]);

            // Assign the list to the corresponding taxonomy key
            $terms[$taxonomy] = $taxonomyList;
        }

        return $terms;

    }

    /// get first data for seo meta tags generation
    public function firstLoader(Request $request){

        $locales = config('app.locales');

        $validCookie = _cv($_COOKIE, 'validCookies')?$_COOKIE['validCookies']:'-';
        $url = $request->url();
        $uri = $request->path();

        $cacheNamePart = base64_encode("{$validCookie}_".substr($uri, 0, 88));
        $value = Cache::store('file')->get('ssrImitationIndex_'.$cacheNamePart);
        if($value) return $value;


        $langFromUrl = substr($request->path(), 0,2);
        $currentLang = isset($locales[$langFromUrl])?$langFromUrl:key($locales);
        $uriNoLan = str_replace("{$currentLang}/", '', $uri );

        $request->headers->set('lang', $currentLang);

        $RedirectionsModel = new App\Models\Admin\RedirectionsModel();

        $ret['redirectableUrl'] = $RedirectionsModel->getRedirectionUrl([ 'domain'=>$request->getHttpHost(), 'path'=>$uri ]);

//        return  response([]);
        if($ret['redirectableUrl']) return redirect($ret['redirectableUrl']);

        $parsedUrl = $this->urlParser();
        $siteMapModel = new SiteMapModel();
        $selectedMenu = $siteMapModel->getByPath( $parsedUrl['menuPath'] );

//        $cacheNamePart = isset($selectedMenu['id'])?base64_encode("{$validCookie}_{$selectedMenu['id']}_{$currentLang}"):"{$validCookie}_unknowpage_{$currentLang}";



        $MetaTags = new MetaTagsGenerator();

        $ret = $MetaTags->index(['validCookies'=>$validCookie, 'path'=>$uri, 'pageUrl'=>$url, 'lang'=>$currentLang, 'menuData'=>$selectedMenu, 'parsedUrl'=>$parsedUrl, 'menuId'=> _cv($selectedMenu, 'id', 'nn')]);

        Cache::put('ssrImitationIndex_'.$cacheNamePart, response($ret), env('CACHE_INDX', 60));

        return $ret;
    }

    public function getValidCookiesData(Request $request){
//        p($request->all());
        $MetaTags = new MetaTagsGenerator();

        $ret = $MetaTags->getActiveCookiesData($_COOKIE);


        return response($ret);
    }

    public function cookies()
    {
        $value = Cache::store('file')->get('cookies_full');
        if ($value) return $value;

        DB::enableQueryLog();

        $options = new OptionsModel();
        $cookies = $options->getSetting('thirdPartyScripts', 'general');

        Cache::put('cookies_full', response()->json($cookies, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_INDX', 2));

        return response()->json($cookies, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function getHomeContent(){

        $homePageMenu = SiteMapModel::select(['id'])->where( 'set_home', 1)->first();

        if(!$homePageMenu)return [];

        $homeData = $this->getCurrentContent( $homePageMenu->id );
//        p($homeData);

        return ['home_content'=>$homeData, 'home_id'=>$homePageMenu->id];

    }

    public function getCalendar(){

        $request = Request();

        $contentType ='blog';
        if(is_array($request->taxonomy)){
             $taxonomy_val = implode(',', $request->taxonomy);
        }else{
            $taxonomy_val ='102107';
        }

        $cacheKey = base64_encode("{$taxonomy_val}-{$contentType}");
        $value = Cache::store('file')->get('getCurrentContent' . $cacheKey);
        if ($value) return $value;

        $qr = ("SELECT DISTINCT(date_format(pages.date, '%Y-%m')) as date, date_format(pages.date, '%Y') as 'year', date_format(pages.date, '%m') as month
                                    FROM pages
                                        left join modules_taxonomy_relations as tax ON tax.data_id = pages.id
                                        where pages.content_type like '{$contentType}' and
                                            pages.status = 'published'  and
                                            tax.taxonomy_id in ({$taxonomy_val})
                                        group by pages.id
                                        order by pages.date desc
                                        limit 1000
                                        ");
        $ret = DB::select( $qr );

        Cache::put('getCurrentContent' . $cacheKey, response($ret), env('CACHE_LIST_VIEW', 2));


        return $ret;

    }

    /**
     * @OA\Post( path="/view/main/getCurrentContent", tags={"Public website data"}, summary="dinamic method to get content depend on site location", operationId="getCurrentContent",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="contentid", type="int", example="14"),
     *  ),),
     *
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function getCurrentContent($menuId = false)
    {
        //
        $request = Request();
        $singleRequest = $this->checkSingleRequestData($request->all());
        //        p($request->all());
        $locale = requestLan();

        $cacheKey = base64_encode(json_encode($request->all()) . $locale);
        $cacheType = $singleRequest['single'] ? 'CACHE_SINGLE_VIEW' : 'CACHE_LIST_VIEW';
        $value = Cache::store('file')->get('getCurrentContent' . $cacheKey);
        if ($value) return $value;

        $start = microtime(true);

        /// if not set menu id ($contentid) return false
        if (!$menuId && $request->contentid) $menuId = $request->contentid;
        if (!is_numeric($menuId)) return [];

        $menuItem = new SiteMapModel();

        /// get requested menu
        $currentMenu = $menuItem->getOne(['id' => $menuId]);
        //        $rr = _toArray($currentMenu);
//        p($currentMenu);

        /// if can't find menu return false
        if (!isset($currentMenu['id'])) return [];
        $ret = [];

        /// check primary_data is array; if not set empty array
        if (!_cv($currentMenu, 'primary_data', 'ar')) $currentMenu['primary_data'] = [];

        /// select secondary content
        if (_cv($currentMenu, 'secondary_data')) {
//            p($currentMenu['secondary_data']);
            $ret['secondary'] = $this->getContentLoop($currentMenu['secondary_data'], $currentMenu['secondary_template'], $request->all(), $singleRequest);
        }


        $ret['exectime'] = microtime(true) - $start;
        Cache::put('getCurrentContent' . $cacheKey, response($ret), env($cacheType, 2));

        return response($ret);
    }

    public function getContentLoop($params = [], $template = '', $req = [], $singleRequest=[])
    {

        $smartComponentsConfig = config("adminpanel.smartComponents");

        $singleView = $singleRequest['single'];
        //        $singleView = _cv($req, 'singleview', 'nn');

        $ret = [];
        foreach ($params as $k => $v) {
            /// continue if component not enabled
            if (_cv($v, 'enabled', 'nn') != 1)
                continue;
            /// if it is singleView get only singleview components;
            /// if it is singleView and primary continue;
            /// if it is listview data else get only listview components data
            if ($singleView && _cv($v, 'singleLayout', 'nn') != 1) {
                continue;
            } else if (!$singleView && _cv($v, 'listLayout', 'nn') != 1) {
                continue;
            }


            /// additional params for primary data
            if(_cv($v, 'primary', 'nn') == 1 && $singleRequest['single'] && $singleRequest['id']){
                $v['id'] = [$singleRequest['id']];
                $v['page'] = [$singleRequest['id']];
                $v['doPrimary'] = true;
                $v['useContent'] = 1;
            }

            $v['unicId'] = isset($v['unicId']) ? $v['unicId'] : '8';
            $v['componentName'] = isset($v['selectedComponent']) ? $v['selectedComponent'] : '8';

            /// if place does not exists continue
            if (!isset($smartComponentsConfig[$v['componentName']]))continue;

            $v['componentMainConfigs'] = $smartComponentsConfig[$v['componentName']];
            $v['porc'] = _cv($v['componentMainConfigs'], 'type');
            $v['content-category'] = _cv($req, 'content-category');

            //            $ret["{$v['componentName']}_{$v['unicId']}"] = (_cv($v, 'useContent', 'nn')==1)?$this->getContentByMenuPlace($v):[];

            $ret["{$v['componentName']}_{$v['unicId']}"] = $this->getContentByMenuPlace($v);

        }

        return $ret;
    }

    /** get content or product data by menu place configuration
     * porc will be `product` or `content`
     */
    public function getContentByMenuPlace($params = [])
    {
//                p($params);
//        p(_cv( $params, 'porc' ));

        /// check component is for content or product; set content by default
        $params['porc'] = _cv($params, 'porc') ? $params['porc'] : 'content';

        $ret = [];

        $locale = requestLan();

        $params['header'] = _cv($params, 'header.' . $locale);
        $params['siteMode'] = siteMode();

        $componentIsSinglePrimary = (_cv($params, 'doPrimary') && _cv($params, 'primary') == 1 && _cv($params, 'singleLayout') == 1) ? true : false;

        //        if(_cv($v, 'primary')==1 && _cv($v, 'singleLayout')==1)continue;

        //        $taxonomy = $taxonomyModel->getTaxonomyByTermId(['id'=>$request->searchTerms]);
        /// filter secondary data (component data) by url content-category param (content-category means term id)
        /// if === filterFromContentCategoryParam checked AND url param content-category exists AND component has by taxonomy filter set some taxonomy
        if (_cv($params, 'filterFromContentCategoryParam') == 1 && _cv($params, 'content-category', 'nn') && _cv($params, 'taxonomy')) {
            $taxonomyModel = new TaxonomyModel();

            $taxonomy = $taxonomyModel->getTaxonomyByTermId(['id' => $params['content-category']]);
            if ($taxonomy === _cv($params, 'taxonomy')) {
                $params['terms'] = [$params['content-category']];
            }
            //            p($taxonomy);
//            p($params);
        }

        if (_cv($params, 'useContent', 'nn') == 1 && $params['porc'] == 'product') {
            /// !$componentIsSinglePrimary &&
            $params['selected_language'] = requestLan();

            $product = new App\Models\Shop\ProductsModel();
            $params['translate'] = $locale;
            $params['status'] = 'published';
            $params['limit'] = _cv($params, 'limit', 'nn') ? $params['limit'] : 5;

            if (_cv($params, 'doPrimary')) {
                $ret['data'] = $product->getProductsGrouped($params);
                $product->updateViewCount($params);
            } else {
                //                p($params);
                $ret['data'] = $product->getList($params);
            }
            //            $ret['data'] = $product->getList($params);
//            $ret['data'] = $product->getProductsGrouped($params);


        } else if (_cv($params, 'useContent', 'nn') == 1 && $params['porc'] == 'content' && (isset($params['contentType']) || isset($params['taxonomy']) || isset($params['page']))) {
            // || !_cv($params, 'enabled')  && !$componentIsSinglePrimary

            $page = new PageModel();
            $params['translate'] = $locale;
            $ret['data'] = $page->getContent($params);

        }

        $ret['conf'] = $params;
        return $ret;

    }

    private function checkSingleRequestData($request = []){
        $ret = ['single'=>false, 'type'=>'', 'id'=>0, 'key'=>''];
        if(isset($request['productview']) && intval($request['productview'])){
            $ret = ['single'=>true, 'type'=>'product', 'id'=>intval($request['productview']), 'key'=>'productview'];
        }elseif(isset($request['singleview']) && intval($request['singleview'])){
            $ret = ['single'=>true, 'type'=>'content', 'id'=>intval($request['singleview']), 'key'=>'singleview'];
        }
//        p($request);
        return $ret;
    }

    /**
     * @OA\Post( path="/view/main/getDataList", tags={"Public website data"}, summary="Get content list depend requested filter params", operationId="getDataList",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="id", type="int", example="14"),
     *      @OA\Property(property="componentUnicId", type="string", example="email@example.com"),
     *      @OA\Property(property="searchText", type="string", example="email@example.com"),
     *      @OA\Property(property="searchTerms", type="string", example="email@example.com"),
     *      @OA\Property(property="pageNumber", type="string", example="email@example.com"),
     *      @OA\Property(property="perPage", type="string", example="email@example.com"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function getDataList()
    {
        /** request params
        [id] => 132 /// menu id // required
        [placeName] => primary // optional
        [searchText] =>'string' // optional
        [searchDate] => Array(0=>from, 1=>to) // optional
        [searchTerms] => 93 or [93,94,...] // optional
        [pageNumber] => 1 // optional
        [perPage] => 14 // optional
        [exclude] => page id // optional
        [taxonomy] => news_category
        [taxonomies] => [news_category: [1,5,7], news_category: [1,5,7] ]
        [componentUnicId] => 'componentUnicId string'
        */

        $start = microtime(true);
        $request = Request();
        //        $request->merge(['contentType'=>'partners']); /// for testing

        if ($request->content_type == 'product') {
            return response(['error' => 'use `getProductDataList` instead']);
        }

        if (!is_numeric($request->id) && !$request->contentType) return response(['error' => 'required menu `id` or `contentType`']);
        $menuItem = new SiteMapModel();
        $page = new PageModel();
        $currentMenuPlaceParams = $request->all();
        $tmp = [];
        if ($request->componentUnicId) {
            /// get requested menu
            $currentMenu = $menuItem->getOne(['id' => $request->id]);
            foreach ($currentMenu['secondary_data'] as $k => $v) {
                if (_cv($v, 'unicId') == $request->componentUnicId) {
                    $tmp = $v;
                    break;
                }
            }
        }
        $currentMenuPlaceParams = array_merge($currentMenuPlaceParams, $tmp);
        if($request->exclude){
            $currentMenuPlaceParams['exclude'] = $request->exclude;
        }

        if($request->ids){
            $currentMenuPlaceParams['ids'] = $request->ids;
        }

        if (!$request->componentUnicId && $request->contentType) {
            $currentMenuPlaceParams['contentType'] = $request->contentType;
        }
        if (!$currentMenuPlaceParams)
            return response(['error' => 'data not found']);


        $ret = [];
        $currentMenuPlaceParams['page'] = $request->page;
        if ($request->searchText)
            $currentMenuPlaceParams['searchText'] = $request->searchText;
        if ($request->searchDate)
            $currentMenuPlaceParams['searchDate'] = $request->searchDate;
        if (is_numeric($request->pageNumber)) {
            $currentMenuPlaceParams['pageNumber'] = $request->pageNumber;
        }
        if (is_numeric($request->perPage)) $currentMenuPlaceParams['page_count'] = $request->perPage;
        if (is_numeric($request->limit)) $currentMenuPlaceParams['page_count'] = $request->limit;

        $taxonomyModel = new TaxonomyModel();


        if (is_numeric($request->searchTerms)) {
            $taxonomy = $taxonomyModel->getTaxonomyByTermId(['id' => $request->searchTerms]);
            $currentMenuPlaceParams['taxonomy'] = $taxonomy;
            $currentMenuPlaceParams['term'] = $request->searchTerms;
        } else if (!$request->searchTerms && $request->taxonomy) {
            /// if unselected all terms
            $currentMenuPlaceParams['taxonomy'] = $request->taxonomy;
            $currentMenuPlaceParams['term'] = '';
        } else if (is_array($request->searchTerms)) {

            foreach ($request->searchTerms as $v) {
                $taxonomy = $taxonomyModel->getTaxonomyByTermId(['id' => $v]);
                if (!$taxonomy)
                    continue;
                $currentMenuPlaceParams['taxonomies'][$taxonomy][] = $v;
            }
        }

        if ($request->taxonomies) {
            $currentMenuPlaceParams['taxonomies'] = $request->taxonomies;
        }

        if ($request->taxonomies_or) {
            $currentMenuPlaceParams['taxonomies_or'] = $request->taxonomies_or;
        }

        if (isset($request->contentType)) {
            $currentMenuPlaceParams['contentType'] = $request->contentType;
        }

        if(isset($request['translate'])){
            $currentMenuPlaceParams['translate'] = $request['translate'];
        } else{
            $currentMenuPlaceParams['translate'] = requestLan();
        }

        if($request->pageOrder){
            $allowedOrders = ['asc' => 0, 'desc' => 1];

            $pageOrder = (string) $request->pageOrder;
            $pageOrder = strtolower($pageOrder);

            if(isset($allowedOrders[$pageOrder])) {
                $currentMenuPlaceParams['page_order'] = $pageOrder;
            }
        }

        $ret = $page->getContent($currentMenuPlaceParams);

        $ret['exectime'] = microtime(true) - $start;
        $ret['componentUnicId'] = $request->componentUnicId;
        //        p($ret);
        return response($ret);
    }

    public function getAllTerms(){
        $terms = [];
        $taxonomy = config('adminpanel.taxonomy');
        $taxModel = new TaxonomyModel();

        foreach ($taxonomy as $k=>$v){
            $terms[$k] = $taxModel->getList(['taxonomy' => $k, 'translate'=>1, 'select'=>["taxonomy.id", "taxonomy.pid", "taxonomy.slug", "taxonomy.sort", "taxonomy.taxonomy"]]);
        }

        return $terms;

    }

    /**
     * @OA\Post( path="/view/main/search", tags={"Public website data"}, summary="get searched content", operationId="search",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="searchWord", type="string", example="some text"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function search(Request $request)
    {
        $contentTypes = config('adminpanel.content_types');
        $searchableContentTypes = [];
        $searchableContentTypesTitles = [];

        foreach ($contentTypes as $key => $content)
        {
            if (!empty($content['searchable']))
            {
                $searchableContentTypes[] = $key;

                $searchableContentTypesTitles[$key] = $content['title'];
            }
        }

        if (empty($searchableContentTypes))
        {
            return [];
        }

        $searchRules = [
            'searchText' => 'bail|nullable|string|max:1000',
            'contentType' => 'bail|nullable|array|max:100',
            'contentType.*' => 'bail|required|string|max:300',
            'contentFields' => 'bail|nullable|array|max:50',
            'contentFields.*' => 'bail|required|string|max:300',
            'limit' => 'bail|nullable|integer|min:1|max:10000',
            'page' => 'bail|nullable|integer|min:1|max:10000'
        ];

        $input = $request->only(['searchText', 'contentType', 'contentFields', 'limit', 'page']);

        $searchValidator = \Validator::make($input, $searchRules);

        if ($searchValidator->fails() || !$request->searchText)
        {
            return [];
        }

        $contentFields = $input['contentFields'] ?? [];

        if (!empty($request->contentType))
        {
            $validInputContentTypes = [];

            foreach ($request->contentType as $contentType)
            {
                if (in_array($contentType, $searchableContentTypes))
                {
                    $validInputContentTypes[] = $contentType;
                }
            }

            if (!empty($validInputContentTypes))
            {
                $searchableContentTypes = $validInputContentTypes;
            }
        }

        $pageModel = new PageModel;
        $pageController = new Page;

        $fieldConfigs['fields'] = [
            'title' => [],
            //'meta_keys' => ['file', 'pdf_files', 'xhtml_files'],
        ];

        $fieldConfigs['regularFields'] = [
            'content_type' => ['dbFilter' => 'whereIn']
        ];

        $currentPage = $request->page ?: 1;

        $listFilter = [
            'content_type' => $searchableContentTypes,
            'status' => 'published',
            'searchText' => $request['searchText'],
            'translate' => 1,
            'fieldConfigs' => $fieldConfigs,
            'limit' => $request->limit ?: 10,
            'page' => $currentPage,
            'sortField' => 'content_type',
            'sortDirection' => 'asc'
        ];

        $list = $pageModel->getList($listFilter);
        $results = [];
        $normalizedSearchText = strtolower($request['searchText']);

        $filesFieldsByContentType = [
            'financial_results' => [
                'onlyFile' => true,
                'fields' => [
                    'file' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'annual_reports' => [
                'onlyFile' => true,
                'fields' => [
                    'pdf_files' => [
                        'type' => 'simple'
                    ],
                    'xhtml_files' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'investor_day_presentations' => [
                'onlyFile' => true,
                'fields' => [
                    'file' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'financial_statements' => [
                'onlyFile' => true,
                'fields' => [
                    'file' => [
                        'type' => 'simple'
                    ]
                ]
            ],
            'credit_ratings' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ],
            'shareholder_meetings' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ],
            'page' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ],
            'news' => [
                'onlyFile' => false,
                'fields' => [
                    'file' => [
                        'type' => 'complex',
                        'parentField' => 'content'
                    ]
                ]
            ]
        ];

        foreach ($list['list'] as $item)
        {
            $filesConfigsByContentType = $filesFieldsByContentType[$item['content_type']];

            $fields = $filesConfigsByContentType['fields'];

            $matchedFiles = [];

            $contentContainsComplexField = false;
            $textFoundElsewhere = '';

            foreach ($fields as $field => $config)
            {
                if ($config['type'] === 'simple')
                {
                    if (!isset($item[$field]) || !is_array($item[$field]) || empty($item[$field])) continue;

                    foreach ($item[$field] as $fileData)
                    {
                        $normalizedFileUrl = strtolower($fileData['url']);
                        $normalizedFileTitle = strtolower($fileData['title'] ?? '');

                        if (!$normalizedFileTitle)
                        {
                            if (str_contains($normalizedFileUrl, $normalizedSearchText))
                            {
                                $matchedFiles[$field][] = [
                                    'matchedText' => $normalizedFileUrl,
                                    'url' => $fileData['url']
                                ];
                            }
                        }

                        else
                        {
                            if (str_contains($normalizedFileTitle, $normalizedSearchText))
                            {
                                $matchedFiles[] = [
                                    'matchedText' => $normalizedFileTitle,
                                    'url' => $fileData['url']
                                ];
                            }
                        }
                    }
                }

                else
                {
                    $contentContainsComplexField = true;

                    $complexFieldData = $item[$config['parentField']] ?? [];

                    if (!is_array($complexFieldData) || empty($complexFieldData)) continue;

                    foreach ($complexFieldData as $complexFieldDataItem)
                    {
                        if ($complexFieldDataItem['conf']['type'] !== 'file')
                        {
                            if ($complexFieldDataItem['conf']['type'] === 'editor' || $complexFieldDataItem['conf']['type'] === 'textarea')
                            {
                                if (str_contains(strtolower($complexFieldDataItem['data']), $normalizedSearchText))
                                {
                                    $textFoundElsewhere = $complexFieldDataItem['data'];
                                }
                            }

                            continue;
                        }

                        $complexFieldFilesData = $complexFieldDataItem['data'] ?? [];

                        if (!is_array($complexFieldFilesData) || empty($complexFieldFilesData)) continue;

                        foreach ($complexFieldFilesData as $fileData)
                        {
                            $normalizedFileUrl = strtolower($fileData['url']);
                            $normalizedFileTitle = strtolower($fileData['title'] ?? '');

                            if (!$normalizedFileTitle)
                            {
                                if (str_contains($normalizedFileUrl, $normalizedSearchText))
                                {
                                    $matchedFiles[$field][] = [
                                        'matchedText' => $normalizedFileUrl,
                                        'url' => $fileData['url']
                                    ];
                                }
                            }

                            else
                            {
                                if (str_contains($normalizedFileTitle, $normalizedSearchText))
                                {
                                    $matchedFiles[$field][] = [
                                        'matchedText' => $normalizedFileTitle,
                                        'url' => $fileData['url']
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            if ($contentContainsComplexField)
            {
                if (!empty($matchedFiles))
                {
                    foreach ($matchedFiles as $fileFieldKey => $matchedFilesData)
                    {
                        foreach ($matchedFilesData as $matchedFileData)
                        {
                            $results[] = [
                                'id' => $item['id'],
                                'title' => $matchedFileData['matchedText'],
                                'url' => [$matchedFileData['url']],
                                'content_type' => $item['content_type'] ?? '',
                                'data' => ['date' => $item['date'], 'slug' => $item['slug']],
                                'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                                'result_type' => 'file'
                            ];
                        }
                    }
                }

                if ($textFoundElsewhere)
                {
                    $results[] = [
                        'id' => $item['id'],
                        'title' => getSearchedTextPart($textFoundElsewhere, $request['searchText']),
                        'url' => $pageController->generateLinks(['content_id' => $item['id'], 'content_type' => $item['content_type'], 'slug' => $item['slug']]),
                        'content_type' => $item['content_type'] ?? '',
                        'data' => $item,
                        'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                        'result_type' => 'content'
                    ];
                }

                else
                {
                    $foundText = '';

                    $keysToLookFor = ['title', 'teaser'];

                    foreach ($keysToLookFor as $keyToLookFor)
                    {
                        if ($foundText) break;

                        if (!empty($item[$keyToLookFor]) && str_contains(strtolower($item[$keyToLookFor]), $normalizedSearchText))
                        {
                            $foundText = $item[$keyToLookFor];
                        }
                    }

                    if ($foundText)
                    {
                        $results[] = [
                            'id' => $item['id'],
                            'title' => getSearchedTextPart($foundText, $request['searchText']),
                            'url' => $pageController->generateLinks(['content_id' => $item['id'], 'content_type' => $item['content_type'], 'slug' => $item['slug']]),
                            'content_type' => $item['content_type'] ?? '',
                            'data' => $item,
                            'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                            'result_type' => 'content'
                        ];
                    }
                }
            }

            else
            {
                if (empty($matchedFiles))
                {
                    $results[] = [
                        'id' => $item['id'],
                        'title' => getSearchedTextPart($item['searchVal'], $request['searchText']),
                        'url' => $pageController->generateLinks(['content_id' => $item['id'], 'content_type' => $item['content_type'], 'slug' => $item['slug']]),
                        'content_type' => $item['content_type'] ?? '',
                        'data' => $item,
                        'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                        'result_type' => 'content'
                    ];
                }

                else
                {
                    foreach ($matchedFiles as $fieFieldKey => $matchedFilesData)
                    {
                        foreach ($matchedFilesData as $matchedFileData)
                        {
                            $results[] = [
                                'id' => $item['id'],
                                'title' => $matchedFileData['matchedText'],
                                'url' => [$matchedFileData['url']],
                                'content_type' => $item['content_type'] ?? '',
                                'data' => [],
                                'content_type_title' => $searchableContentTypesTitles[$item['content_type']] ?? '',
                                'result_type' => 'file'
                            ];
                        }
                    }
                }
            }
        }

        if (!empty($contentFields))
        {
            foreach ($results as $resultIndex => $existingResult)
            {
                $data = [];

                foreach ($contentFields as $fieldName)
                {
                    $fieldValue = $existingResult['data'][$fieldName] ?? null;

                    if ($fieldValue !== null)
                    {
                        $data[$fieldName] = $fieldValue;
                    }
                }

                $results[$resultIndex]['data'] = $data;
            }
        }

        return [
            'list' => $results,
            'listCount' => $list['listCount'] ?? count($results),
            'page' => $list['page'] ?? $currentPage,
            'pageMatchCount' => count($results)
        ];
    }

    /// content Search
    public function inContentsearch($searchWord = '')
    {
//        DB::enableQueryLog();

//        p($request->all());
        if(!$searchWord)return response([]);

        $titleField = "title_".requestLan();
        $getSearchableContentTypes = "'".implode("','", $this->getSearchableContentTypes())."'";

        $ret['results'] = DB::select( DB::raw("SELECT pages.id, pages.content_type FROM pages_meta
                                        left  pages ON pages.id = pages_meta.data_id
                                        left join pages_meta as title ON title.data_id = pages.id and title.key='{$titleField}'
                                        where pages_meta.val like '%{$searchWord}%' AND pages.page_status = 1 AND pages.content_type IN ({$getSearchableContentTypes})
                                        group by pages.id, pages.content_type
                                        order by pages.date desc
                                        limit 20
                                        ") );

//        $query = DB::getQueryLog();
//        p($query);

        $ret['results'] = _toArray($ret['results']);
        $ids = array_column($ret['results'], 'id');

        $pages = new PageModel();
        $res = count($ids)?$pages->getList(['ids'=>$ids]):[];

        return $res;
    }

    /// fullSearch
    public function inSitemapSearch($searchWord = '')
    {
//        DB::enableQueryLog();

        if(!$searchWord)return response([]);

        $results = DB::select( DB::raw("SELECT titles, id FROM sitemap
                                        where titles like '%{$searchWord}%'
                                        limit 20
                                        ") );

        $results = _psql(_toArray($results));

        $lan = requestLan();

        $res = [];
        foreach ($results as $k=>$v){
//            $results[$k]['titles'] = _cv($v['titles'], $lan);
            $res[] = [ 'id'=>$v['id'], 'title'=>_cv($v['titles'], [$lan, 'title']), 'teaser'=>_cv($v['titles'], [$lan, 'teaser'])?$v['titles'][$lan]['teaser']:'' ];
        }

        return $res;
    }

    public function smallSearch($searchWord)
    {
// dd($searchWord);
        //        p($request->all());
        if(!$searchWord)return response([]);

        $titleField = "title_".requestLan();
        $contentField = "content_".requestLan();
        $getSearchableContentTypes = "'".implode("','", $this->getSearchableContentTypes())."'";

        $ret['results'] = DB::select( DB::raw("SELECT pages.slug,pages.id, pages.content_type, pages_meta.key, pages_meta.val, title.val as title FROM pages_meta
                                        left join pages ON pages.id = pages_meta.data_id
                                        left join pages_meta as title ON title.data_id = pages.id and title.key='{$titleField}'
                                        where (pages_meta.key='{$contentField}' or pages_meta.key='{$titleField}' ) and pages_meta.val like '%{$searchWord}%' AND pages.page_status = 1 AND pages.content_type IN ({$getSearchableContentTypes})
                                        group by pages.id
                                        limit 20
                                        ") ); //->keyBy('content_type');
//           p(_toArray($ret['results']));
        $ret['results'] = _psql(_toArray($ret['results']));
//p($ret['results']);
//        count($ret);

        $results = [];
        foreach ($ret['results'] as $k=>$v){

            $tmp = $v['val'];

            $tmp = getSearchedTextPart($tmp, $searchWord);


            if( $tmp == '' ) continue;

            $res = [];

            $res['content_type'] = $v['content_type'];
            $res['id'] = $v['id'];
            $res['url'] = '#';
            $res['key'] = $v['key'];
            $res['title'] = $v['title'];
            $res['val'] = $tmp;
            $res['slug'] = $v['slug'];

            $results[] = $res;
        }

        return $results;
    }

    public function getSearchableContentTypes(){
        $contentTypes = config('adminpanel.content_types');
        $ret = [];
        foreach ($contentTypes as $k=>$v){
            if(isset($v['searchable']) && $v['searchable']==1) $ret[] = $k;
        }
        return $ret;
    }


    /**
     *    @OA\Post( path="/view/main/getTranslations", tags={"Public website data"}, summary="Get static word translations", operationId="getTranslations",
     *    @OA\Response( response="200", description="{}" )
     * )
     */
    public function getTranslations(){
        $value = Cache::store('file')->get('wordtranslations');
        if($value) return $value;

        $words = new WordsModel();
        $translation = $words->wordsByLan();

        Cache::put('wordtranslations', response($translation), env('CACHE_STRINGS', 2));

        return response($translation);
    }

    /**
     * @OA\Post( path="/view/main/adwrd", tags={"Public website data"}, summary="add new word to db", operationId="adwrd",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="wrd", type="string", example="some text"),
     *  ),),
     *
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function adwrd(Request $request){
//        p($request->wrd);
        if(!$request->wrd)return [];

        $words = new WordsModel();
        $word = $words->getOne(['key'=>$request->wrd]);
        if(isset($word['id']))return response($word);

        $translation = $words->upd(['key'=>$request->wrd]);
        return response($translation);
    }


    /**
     *    @OA\Post( path="/view/main/getServiceCenters", tags={"Public website data"}, summary="Get service centers list", operationId="getServiceCenters",
     *    @OA\Response( response="200", description="{}" )
     * )
     */
    public function getServiceCenters(Request $request){
        $data = new PageModel();
        $ret = $data->getList(['content_type'=>'serviceCenters']);

        return response($ret);
    }

    /**
     * @OA\Post( path="/view/main/saveSubmitedForm", tags={"Public website data"}, summary="save submited form to server db", operationId="saveSubmitedForm",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="formType", type="string", example="form-name"),
     *      @OA\Property(property="anyOtherVariables", type="string", example="any value"),
     *  ),),
     *
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function saveSubmitedForm(Request $request){

        $onlineForms = config('adminpanel.onlineForms');
        $options = new OptionsModel();
        $configEmail = $options->getByKey('contact_email_to');
        $data = $request -> all();
        $ret = [];
        $sendInfo = [];
        $validations = isset($onlineForms[$request->formType]['validate']) ? $onlineForms[$request->formType]['validate'] : null;

        $recaptchaValidation = OnlineForms::validateReCaptcha($data['g-recaptcha-response'] ?? '');

        unset($data['pac']);
        unset($data['g-recaptcha-response']);

        if(_cv($onlineForms, [$request->formType]) && $validations != null){
            $form = new OnlineForms();
            return $form->validateForm($request->all(), $validations);
        }
        if(!_cv($onlineForms, [$request->formType]) || _cv($onlineForms, [$request->formType,'disableSave'])){
            $ret = $recaptchaValidation['success'] ? OnlineForms::saveForm($data) : $recaptchaValidation;
        }
        if(_cv($onlineForms, [$request->formType, 'function'])){
            $aggregator = new App\Http\Controllers\Admin\FormAggregators\MainAggregator();
            $sendInfo = $aggregator->index($data);
        }

        $formBuilder = new FormBuilderModel();
        $form = $formBuilder->getOne(['form_name'=>$request->formType]);

        if(_cv($form, 'id', 'nn') && ( _cv($form, 'form_settings.toEmails') || $configEmail)){
            $aggregator = new App\Http\Controllers\Admin\FormAggregators\MainAggregator();
            $sendInfo = $recaptchaValidation['success'] ? $aggregator->formBuilderSendMail($data, $form) : false;
        }

        return response(['sendInfo'=>$sendInfo, 'saveInfo'=>$ret]);
    }

    /**
     * @OA\Post( path="/view/main/getBulkData", tags={"Public website data"}, summary="main method to get and response api for website, get specific data by 'node' key", operationId="getBulkData",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="node", type="string", example="serviceMap"),
     *  ),),
     *
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function getBulkData(Request $request){

        if($request->node == 'servicesMap'){

            $data = new PageModel();
            $ret = $data->getList(['content_type'=>'serviceMap']);


        }else if($request->node == 'getServiceCenters'){
            $data = new PageModel();
            $ret = $data->getList(['content_type'=>'serviceCenters']);
        }

        return response($ret);
    }

    /**
     * @OA\Post( path="/view/main/uploadfile", tags={"Public website data"}, summary="upload files from website", operationId="uploadfile",
     *   @OA\Parameter( name="request params", description="request params", required=true, in="path",
     *     @OA\Schema(
     *      @OA\Property(property="file", type="string", example="serviceMap"),
     *      @OA\Property(property="type", type="string", example="image"),
     *  ),),
     *   @OA\Response( response="200", description="{}"),
     * )
     */
    public function uploadfile(Request $request){
        $media = new Media();

        $request->type = 'private';
        $request->validate = 'mimes:jpeg,jpg,png,pdf,docx|required|max:10000';
        $res = $media->justUploadFileToServer($request);

        return response($res);

    }

    /** generate website dinamic sitemap */
    public function getSitemap($params = []){

        $cacheNamePart = 'getSitemap_'.base64_encode(json_encode($params));

        $value = Cache::store('file')->get($cacheNamePart);
        if ($value) return $value;

        $locales = config('app.locales');
        $host = env('WEBSITE_URL', env('APP_URL', '#'));

        $singleRoutes = '';
        if( _cv($params, 'return')=='routes' ){
            $singleRoutes = $this->singlePageSitemapRoutes(['return'=>'routes']);

        }else{
            $singleRoutes = $this->singlePageSitemapRoutes(['return'=>'xml']);

        }

        $menuItem = new SiteMapModel();
        $menus = $menuItem->getMenuRouted();

        $tmpp = [];
        $tmp = "";

        foreach ($locales as $kk=>$vv){
            foreach ($menus as $v){
                $configs = _cv($v,['configs'], 'ar')?$v['configs']:[];

                /// disable menu from sitemap
                if(array_search( 'hide_from_sitemap_xml', $configs)!==false)continue;

                /// if there is redirection
                if (strpos($v['redirect_url'], '://')!==false)continue;

                $hst = strpos($v['route'], 'http')===false?$host:'';
                if(isset($tmpp["{$hst}/{$kk}/{$v['route']}"]))continue;

                $tmpp[] = "{$hst}/{$kk}/{$v['route']}";

                $datetime = new DateTime($v['updated_at']);
                $last_mode=$datetime->format(DateTime::ATOM);

                $tmp .= "<url>
                            <loc>{$hst}/{$kk}/{$v['route']}</loc>
                            <lastmod>{$last_mode}</lastmod>
                            <changefreq>weekly</changefreq>
                            <priority>1.0</priority>
                          </url>";
            }
        }



        if( _cv($params, 'return')=='routes' ){
            Cache::put($cacheNamePart, response()->json(['menu' => $tmpp, 'singleRoutes' => $singleRoutes], 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_INDX', 60));

            return response(['menu' => $tmpp, 'singleRoutes' => $singleRoutes]);

        }else{ /// return xml version
            $tmp = '<?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
                  '.$tmp.'
                  '.$singleRoutes.'
                </urlset>';

            Cache::put($cacheNamePart, response($tmp, 200)->header('Content-Type', 'application/xml'), env('CACHE_INDX', 60));
            return response($tmp, 200)->header('Content-Type', 'application/xml');
        }



    }

    public function singlePageSitemapRoutes($params = []){

        $locales = config('app.locales');
        $host = env('WEBSITE_URL', env('APP_URL', '#'));



        $options = new OptionsModel();
        $sitemapModel = new SiteMapModel();


        $pagesModel = new PageModel();
        $singleRoutes = $options->getListByRaw(['key'=>'defaultSingleRoute']);
        $siteMapIds = [];
        $pageIds = [];
        $pageIdsLocalized = [];
        $pagesXml = "";

/**
<image:image>
    <image:loc>https://example.com/image.jpg</image:loc>
</image:image>
 */
        foreach ($singleRoutes as $k=>$v){
            if(!_cv($v,['value'], 'nn'))continue;

            $contentType = str_replace(['content_type_settings_'], '', $v['content_group']);

            $menuItem = $sitemapModel->getOne(['id'=>$v['value']]);
            if(!_cv($menuItem, ['id'], 'nn'))continue;

            $pages = $pagesModel->getPages(['content_type'=>$contentType, 'limit'=>1000, 'status'=>'published']);


            foreach ($pages['list'] as $kk=>$vv){
                $imagesForSitemap = $this->getImagesForSitemap($vv, $locales);
//                p($vv);
                foreach ($locales as $kkk=>$vvv){

                    $datetime = new DateTime($vv['updated_at']);
                    $last_mode=$datetime->format(DateTime::ATOM);

                    $url = "{$host}/{$kkk}/{$menuItem['fullpath']}/{$vv['id']}-{$vv['slug']}";
                    $pageIds[] = ['path'=>$url, 'media'=>$imagesForSitemap['media']];
                    $pageIdsLocalized[$kkk][] = ['path'=>$url, 'media'=>$imagesForSitemap['media']];

                    $pagesXml .= "<url>
                            <loc>{$url}</loc>
                            {$imagesForSitemap['xml']}
                            <lastmod>{$last_mode}</lastmod>
                            <changefreq>weekly</changefreq>
                            <priority>0.5</priority>
                          </url>";

                }

            }

            $siteMapIds[$contentType] = $v['value'];

        }


             if( _cv($params, 'return')=='routes' ){
                return $pageIds;
        }elseif ( _cv($params, 'return')=='routesLocalized' ){
                 return $pageIdsLocalized;
        }elseif ( _cv($params, 'return')=='xml' ){
                 return $pagesXml;
        }

    }

    private function getImagesForSitemap($data=[], $locales=[]){
        $tmp = ['xml'=>[], 'media'=>[]];
        $locales['xx'] = 'xx';
            foreach ($locales as $kk=>$vv) {
                if(!isset($data[$kk]))continue;
                foreach ($data[$kk] as $kkk=>$vvv){
                    if(!isset($vvv[0]['url']))continue;
                    foreach ($vvv as $kkkk=>$vvvv){

//                        $tmp[$vvvv['url']] = $vvvv['url'];
                        $tmp['media'][$vvvv['url']] = $vvvv['url'];
                        $tmp['xml'][$vvvv['url']] =
"<image:image>
    <image:loc>{$vvvv['url']}</image:loc>
</image:image> \n";
                    }

//                    p($vvv);
                }
//                $ret[]
            }
//p($tmp);

        return ['xml'=>implode('',array_values($tmp['xml'])), 'media'=>array_values($tmp['media'])];
    }


    public function oldTaxonomiesToNew(){

        $taxonomies = config('adminpanel.taxonomy');
        $taxonomies = array_keys($taxonomies);
        print "'".implode("','", $taxonomies)."'";

        $taxMetas = DB::select("select * from pages_meta where `key` in ('product_category','tag','management_category','investors_management_category','news_category','financial_highlights_category','smart_link_category','contact_form_selects','developers','developers_project_status','developers_location')");
//        p($taxMetas);
        foreach ($taxMetas as $v){
            $tmp = [];
            $val = _psqlCell($v->val);

            $tmp['data_id'] = $v->table_id;
            $tmp['table'] = 'pages';
            if(!isset($val[0]))continue;
            foreach ($val as $kk=>$vv){
                $tmp['taxonomy_id'] = $vv;
//            DB::table('modules_taxonomy_relationsxxx')->insert($tmp);
                p($tmp);
            }

        }



        return response([]);
    }

    public function getVacancyTaxonomies(){

        $taxModel = new TaxonomyModel();

        $list = $taxModel->getContentCounts('vacancy_category', 'vacancy');

        return $list;

    }

    private function setAppSessionId(){
        $sessId = 'ssid'.md5(date('YmdHis'));
        if(!isset($_COOKIE['appSessionId'])){
            setcookie( 'appSessionId', $sessId, strtotime( '+1 year' ) , '/' );
        }
        return $sessId;
    }

    public function getFormBuilderForm($params=[])
    {
        if(!_cv($params, 'form_name'))return response(['error'=>'Form name not set']);
        $model = new FormBuilderModel();

        $formData = $model->getOne(['form_name'=>$params['form_name']]);

        return response()->json($formData, 200, [], JSON_UNESCAPED_UNICODE);

    }

    public function getMetaTags($params=[])
    {
        $request = Request();

        $locales = config('app.locales');

        $validCookie = _cv($_COOKIE, 'validCookies') ? $_COOKIE['validCookies'] : '-';
        //        print $url = $request->url();
        $url = $params['url'];
        $cacheNamePart = base64_encode(json_encode($params));

        $value = Cache::store('file')->get('seoObject_' . $cacheNamePart);
        if ($value) return $value;


        $parseUrl = parse_url($url);

        $uri = isset($parseUrl['path']) ? $parseUrl['path'] : '';
        $langFromUrl = substr($uri, 0, 2);

        $currentLang = _cv($params, ['lang'])  ?$params['lang'] : key($locales);

        $uriNoLan = str_replace("{$currentLang}/", '', $uri);

        $request->headers->set('lang', $currentLang);
        //        $RedirectionsModel = new App\Models\Admin\RedirectionsModel();

        //        $ret['redirectableUrl'] = $RedirectionsModel->getRedirectionUrl([ 'domain'=>$request->getHttpHost(), 'path'=>$uri ]);
//        if($ret['redirectableUrl']) return redirect($ret['redirectableUrl']);

        $parsedUrl = $this->urlParser($uri, $currentLang);

        $siteMapModel = new SiteMapModel();
        $selectedMenu = $siteMapModel->getByPath($parsedUrl['menuPath']);

        if (!isset($selectedMenu['id'])) {
            $selectedMenu = $siteMapModel->getByPath('personal');
            $uri = "{$currentLang}/personal";
        }

        $MetaTags = new MetaTagsGenerator();

        $ret = $MetaTags->getMetaTags(['validCookies' => $validCookie, 'path' => $uri, 'pageUrl' => $url, 'lang' => $currentLang, 'menuData' => $selectedMenu, 'parsedUrl' => $parsedUrl, 'menuId' => _cv($selectedMenu, 'id', 'nn')]);

        Cache::put('seoObject_' . $cacheNamePart, response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE), env('CACHE_INDX', 60));

        return response()->json($ret, 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function urlParser($path = false, $lang = null)
    {
        $path = trim($path, '/');
        $request = Request();
        $singleParts = ['singleview', 'product'];

        $locales = config('app.locales');

        $ret['uri'] = $path ? $path : $request->path();
        $ret['uri'] = urldecode($ret['uri']);
        $langFromUrl = trim(substr($ret['uri'], 0, 3), '/');
        $ret['currentLang'] = isset($locales[$langFromUrl]) ? $langFromUrl : ($lang ?: key($locales));
        $ret['menuPath'] = $ret['uriNoLan'] = ($ret['currentLang'] == $langFromUrl) ? str_replace(
            "{$ret['currentLang']}/",
            '',
            $ret['uri']
        ) : $ret['uri'];
        $ret['singleId'] = '';
        foreach ($singleParts as $v) {
            if (!strpos($ret['uriNoLan'], $v))
                continue;

            $ret['singleType'] = $v;
            $ret['singleSplit'] = explode($v, $ret['uriNoLan']);
            $ret['menuPath'] = trim($ret['singleSplit'][0], '/');

            foreach (explode('/', $ret['singleSplit'][1]) as $vv) {
                $ret['singleId'] = intval($vv);
                if (is_numeric(intval($vv)) && intval($vv) > 0)
                    break;
            }

            break;

        }

        if ($ret['singleId'] == '') {
            $tmp = explode('/', $ret['uriNoLan']);
            $tmpp = [];
            foreach ($tmp as $v) {
                if (is_numeric(intval($v)) && intval($v) > 0) {
                    $ret['singleId'] = intval($v);
                    break;
                }
                $tmpp[] = $v;
            }

            $ret['menuPath'] = implode('/', $tmpp);
        }


        /// variables for old versions
        $ret['urlParams']['singleview'] = $ret['singleId'];
        $ret['urlParams']['viewitem'] = $ret['singleId'];

        $ret['locale'] = $ret['currentLang'];
        // p($ret);
        return $ret;

    }

    public function getRedirections($params = [])
    {
        $res = RedirectionsModel::get()->toArray();

        if(_cv($params, 'return')=='raw'){
            $output = [];
            foreach ($res as $v) {
                $output[$v['from_url']] = ['to_url'=>$v['to_url'],'virtual'=>$v['virtual']];
            }

            return response($output);
        }else{

            $res = array_column($res, 'to_url', 'from_url');
        }
        return response($res);
    }


    //// testing method
    public function anytest(){


print 11111;



        return response([]);
    }

    public function getAccessibilityOptions(){
        $optionData = OptionsModel::select('value')
            ->where('key', '=', 'accessibilityOptions')
            ->first();

        if ($optionData && $optionData->value) {
            return json_decode($optionData->value, true);
        }

        return response()->json(['status' => false, 'message' => 'No accessibility options found']);
    }

    public function getAttachedTaxonomies(Request $request)
    {
        $input = $request->only(['taxonomies', 'content_type']);

        $rules = [
            'taxonomies' => 'bail|required|array|max:10',
            'taxonomies.*' => 'bail|required|string|max:200',
            'content_type' => 'bail|required|string|max:200'
        ];

        $validator = \Validator::make($input, $rules);

        if ($validator->fails())
        {
            return response(['error' => $validator->errors()->first()], 400);
        }

        $response = [];

        foreach ($input['taxonomies'] as $taxonomy)
        {
            $cacheKey = 'attached_taxonomies_' . $input['content_type'] . '_' . $taxonomy;

            if (Cache::has($cacheKey))
            {
                $response[$taxonomy] = Cache::store('file')->get($cacheKey);
            }

            else
            {
                $result = \DB::table('pages')->select('taxonomy.id')
                                             ->join('modules_taxonomy_relations', 'modules_taxonomy_relations.data_id', '=', 'pages.id')
                                             ->join('taxonomy', 'taxonomy.id', '=', 'modules_taxonomy_relations.taxonomy_id')
                                             ->where('pages.status', 'published')
                                             ->where('taxonomy', $taxonomy)
                                             ->where('pages.content_type', $input['content_type'])
                                             ->groupBy('taxonomy.id')
                                             ->get()
                                             ->pluck('id')
                                             ->toArray();

                if (!empty($result))
                {
                    Cache::store('file')->put($cacheKey, $result, 3600);

                    $response[$taxonomy] = $result;
                }
            }
        }

        return $response;
    }

    // xrates

    public function get_x_rates($currencies = [])
    {
        $currencies = _cv($currencies, [0, 'nbg_code'])?$currencies:config('app.currency');

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/ka/json',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Cookie: PHPSESSID=3matiarut0dqu39sqvegri61u4'
            ),
        ));

        $response = curl_exec($curl);


        if (curl_errno($curl)) {
            echo 'curl error:' . curl_error($curl);
            dd(curl_error($curl));
            return false;
        }

        $res = json_decode($response, 1);
        curl_close($curl);

        if(!_cv($res, '0.currencies.0.code'))return false;

        $allCurrencies = array_column($res['0']['currencies'], 'rate','code');

        foreach ($currencies as $k=>$v){
            $currencies[$k]['rate'] = ( isset($v['nbg_code']) && isset($allCurrencies[$v['nbg_code']]) )?$allCurrencies[$v['nbg_code']]:'';
        }

        $this->updateXratesApi($currencies);

        return $currencies;
    }

    public function updateXratesApi($xrateData = []){

        $date_now = date("Y-m-d");
        $tmp = OptionsModel::Where('key', 'xrates')->first();

        if (isset($tmp)) {
            if ($date_now != $tmp->conf) {
                $currencyRates = _psqlupd($xrateData);

                $tmp->key = 'xrates';
                $tmp->value = $currencyRates;
                $tmp->conf = date("Y-m-d");
                $tmp->content_group = 'site_configurations';
                if ($tmp->value) {
                    $tmp->save();
                }

                return 'updated';
            }

            return 'no need to update';
        } else {
            $currencyRates = _psqlupd($xrateData);

            $tmp = new OptionsModel();
            $tmp->key = 'xrates';
            $tmp->value = $currencyRates;
            $tmp->conf = date("Y-m-d");
            $tmp->content_group = 'site_configurations';
            if ($tmp->value) {
                $tmp->save();
            }
            return 'saved';
        }
    }
}
