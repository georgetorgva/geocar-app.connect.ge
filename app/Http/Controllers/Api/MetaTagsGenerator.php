<?php

namespace App\Http\Controllers\Api;

use App;
use App\Models\Admin\SiteMapModel;
use App\Models\Admin\OptionsModel;
use App\Models\Admin\PageModel;
use App\Models\Admin\OnlineFormsModel;
use Illuminate\Support\Str;
use App\Models\Admin\TaxonomyModel;



class MetaTagsGenerator extends App\Http\Controllers\Api\ApiController
{
    //
    public $singleSegments = ['paging', 'singleview', 'content-category', 'viewitem', 'productview'];
    public $urlParts = [];
    public $urlParams = [];
    public $menuUrl = [];
    public $templateFile = 'index.html';
    public $locale = 'ge';
    public $path;
    public $pageUrl;
    public $menus;
    public $indxData;
    public $tmp;
    public $hydration;

    function __construct($params = []){
        $single_page_keywords = config('adminpanel.single_page_keywords');

        if($single_page_keywords && is_array($single_page_keywords)) { $this->singleSegments = array_merge($single_page_keywords, $this->singleSegments); }

        $this->menus = _cv($params, 'menus')?$params['menus']:[];
        $this->path = _cv($params, 'path')?$params['path']:'';
        $this->pageUrl = _cv($params, 'pageUrl')?$params['pageUrl']:'';
        $this->indxData = _cv($params, 'indxData')?$params['indxData']:'';

    }

    public function index($params = []){

        $this->locale = _cv($params, 'lang')?$params['lang']:$this->locale;

        $path = _cv($params, 'path');
        $this->pageUrl = _cv($params, 'pageUrl');

        $this->urlParts = $params['parsedUrl'];

        $menu = $this->getCurrentMenu($params);

//        p($menu);
        /// get content type by menu primary single component
        /// check is it content, product, realestate or other
        $contentType = $this->getContentTypeByComponent($menu);

        $relatedContent = [];
        $singleContent = false;

        if (_cv($this->urlParts, 'urlParams.singleview') && $contentType=='product'){
            $singleContent = $this->getProduct($this->urlParts['urlParams']['singleview']);
        }else if (_cv($this->urlParts, 'urlParams.productview') ){
            $singleContent = $this->getProduct($this->urlParts['urlParams']['productview']);
        } else if (_cv($this->urlParts, 'urlParams.singleview')){
            $singleContent = $this->getSingleContent($this->urlParts['urlParams']['singleview']);
        } else if (_cv($this->urlParts, 'urlParams.viewitem')){
            $singleContent = $this->getViewItemContent(['viewitem'=>$this->urlParts['urlParams']['viewitem']]);
        }

        if($singleContent){
            $metaFiller = $this->getSeoParts($singleContent);
        }else{
            $metaFiller = $this->getSeoParts($menu['metaParts'], $relatedContent);
        }


        $thirdPartyScriptsAcepted = $this->getActiveCookiesData($params);
        $this->getHydrationData($params);

        $ret = [ 'placeFillers'=>['meta'=>$metaFiller['tags'], 'title'=>$metaFiller['title']],
            'htmlTag'=>$metaFiller['htmlTag'],
            'urlParts'=>$this->urlParts,
            'hydration'=>$this->hydration,
            'placeHolders' => $thirdPartyScriptsAcepted ];

        $template = $this->prepareOutputHtml($ret);

        return $template;
    }

    public function getMetaTags($params = []){

        $this->locale = _cv($params, 'lang')?$params['lang']:$this->locale;

        $path = _cv($params, 'path');
        $this->pageUrl = _cv($params, 'pageUrl');

        $this->urlParts = $params['parsedUrl'];

        $menu = $this->getCurrentMenu($params);

//        p($menu);
        /// get content type by menu primary single component
        /// check is it content, product, realestate or other
        $contentType = $this->getContentTypeByComponent($menu);

        $relatedContent = [];
        $singleContent = false;

        if (_cv($this->urlParts, 'urlParams.singleview') && $contentType=='product'){
            $singleContent = $this->getProduct($this->urlParts['urlParams']['singleview']);
        } else if (_cv($this->urlParts, 'urlParams.singleview')){
            $singleContent = $this->getSingleContent($this->urlParts['urlParams']['singleview']);
        } else if (_cv($this->urlParts, 'urlParams.viewitem')){
            $singleContent = $this->getViewItemContent(['viewitem'=>$this->urlParts['urlParams']['viewitem']]);
        }

        if($singleContent){
            $metaFiller = $this->getSeoParts($singleContent);
        }else{
            $metaFiller = $this->getSeoParts($menu['metaParts'], $relatedContent);
        }

        $thirdPartyScriptsAcepted = $this->getActiveCookiesData($params);
        $this->getHydrationData($params);

        $ret = [ 'placeFillers'=>['meta'=>$metaFiller['tagsObj'], 'title'=>$metaFiller['title']],
            'htmlTag'=>$metaFiller['htmlTag'],
            'urlParts'=>$this->urlParts,
            'headerObjects'=>$metaFiller['headerObjects'],
//            'hydration'=>$this->hydration,
//            'placeHolders' => $thirdPartyScriptsAcepted
        ];

//        $template = $this->prepareOutputHtml($ret);

        return $ret;
    }

    public function getHydrationData(){
        $Main = new Main();

        $this->hydration['indx'] = $Main->index();
        $this->hydration['indxTranslatable'] = $Main->indxTranslatable();
        $this->hydration['getTranslations'] = $Main->getTranslations();
//p($this->hydration['indxTranslatable']);
        return $this->hydration;

    }

    public function getActiveCookiesData($params = []){

        /// get thirdpPartyPlaceholder contents from db
        $OptionsModel = new OptionsModel();
        $thirdPartyScripts = $OptionsModel->getSetting( 'thirdPartyScripts', 'general' );

        /// get configs for thirdpPartyPlaceholder
        $sitePlaceHolders = config('adminpanel.cookies.types')??[];

        $thirdPartyScriptsAcepted = [];
        $params['validCookies'] = _cv($params, ['validCookies'])?$params['validCookies']:'';
//        $params['validCookies'] = 'generalpreferencesperformance'; /// for testing

        /// check if thirdpPartyPlaceholder content need cookies aceptanse;
        foreach ($sitePlaceHolders as $k=>$v){
            /// if cookie place need accept and received cookie 'validCookies' not accepted dont load script
            if(_cv($v, ['acceptable']) === 1 && strpos($params['validCookies'], $k) === false ){
                /// if received cookie 'validCookies' does no contain string from $v['cookieGroup'] continue;
                continue;
            }

            /// use thirdpPartyPlaceholder without needed to accept on cookies
            $tmp = _cv($thirdPartyScripts, ['places', $k, 'script']);

            $tmp = is_string($tmp)?base64_decode($tmp):'';

            $thirdPartyScriptsAcepted[] = $tmp;
        }


        return implode('', $thirdPartyScriptsAcepted);
    }

    public function getUrlParts($path=''){
        $locales = array_keys(config('app.locales'));

        $path = trim($path, '/');

        $ret = [ 'locale'=>$locales[0], 'urlParams'=>[], 'path'=>$path, 'menuUrl' => [] ];

        $singleSegments = array_flip($this->singleSegments);

        $segments = explode('/', $path);
        $currentSingle = false;
        $locales = array_flip($locales);


        foreach ($segments as $k=>$v){
            if($k==0 && isset($locales[$v]) ){
                $ret['locale'] = $v;

                continue;
            }

            if($currentSingle){
                $ret['urlParams'][$currentSingle] = $v;
                $currentSingle = false;
                continue;
            }

            if( isset($singleSegments[$v]) ){
                $currentSingle = $v;
                $ret['urlParams'][$v] = '';
                continue;
            }


            if(count($ret['urlParams'])>0)continue;

//            if(!empty($v))
            $ret['menuUrl'][] = $v;

        }

        return $ret;
    }

    public function getCurrentMenu($params = []){
        $locale = _cv($this->urlParts, 'locale');
        $currentMenu = false;

        if( _cv($params, 'menuData.id', 'nn') ){
            $currentMenu = $params['menuData'];

        }else if( _cv($params, 'menuId', 'nn') ){
            $menu = new SiteMapModel();
            $currentMenu = $menu->getListBy(['id'=>$params['menuId']]);
        }

        if(!$currentMenu){
            $currentMenu = $this->getHomeMenu();
        }

        if(!$currentMenu){
            return false;
        }
//p($currentMenu);
        $titles = _cv($currentMenu, ['titles', $locale]);
        $seo = _cv($currentMenu, ['seo', $locale]);

        if(_cv($seo, 'seo_title')){
            $metaParts['title'] = $seo['seo_title'];
        }elseif (_cv($seo, 'title')){
            $metaParts['title'] = $seo['title'];
        }else{
            $metaParts['title'] = _cv($titles,['title']);
        }

        if(_cv($seo, 'seo_description')){
            $metaParts['description'] = $seo['seo_description'];
        }elseif (_cv($seo, 'description')){
            $metaParts['description'] = $seo['description'];
        }else{
            $metaParts['description'] = _cv($titles,['description']);
        }

        if(_cv($seo, ['seo_image', '0', 'devices', 'desktop'])){
            $metaParts['image'] = $seo['seo_image']['0']['devices']['desktop'];
        }elseif (_cv($seo, 'image')){
            $metaParts['image'] = $seo['image']['0']['devices']['desktop'];
        }else{
            $metaParts['image'] = _cv($titles,['media', 'cover','devices', 'desktop']);
        }

        if(_cv($seo, ['seo_keywords'])){
            $metaParts['keywords'] = $seo['seo_keywords'];
        }else{
            $metaParts['keywords'] = _cv($seo, ['keywords']);
        }

//        $metaParts['title'] = _cv($currentMenu, ['seo', $locale, 'seo_title'])?_cv($currentMenu, ['seo', $locale, 'seo_title']):_cv($currentMenu, ['titles', $locale, 'title']);
//        $metaParts['description'] = _cv($currentMenu, ['seo', $locale, 'seo_description'])?_cv($currentMenu, ['seo', $locale, 'seo_description']):_cv($currentMenu, ['titles', $locale, 'teaser']);
//        $metaParts['keywords'] = _cv($currentMenu, ['seo', $locale, 'seo_keywords']);
//        $metaParts['image'] = _cv($currentMenu, ['seo', $locale, 'seo_image', '0', 'devices', 'desktop'])?_cv($currentMenu, ['seo', $locale, 'seo_image', '0', 'devices', 'desktop']):_cv($currentMenu, ['media', 'cover','devices', 'desktop']);

        $currentMenu['metaParts'] = $metaParts;
        return $currentMenu;

    }

    public function getMenuByPath($menuUrl = []){
        if(!is_array($menuUrl))$menuUrl = [];

        $menu = new SiteMapModel();
        $menus = $menu->getListBy(['slug'=>$menuUrl]);

        $menuUrlGlued = implode('/', $menuUrl);
        if(empty($menuUrlGlued))$menuUrlGlued = '/';
        $menuSlug = end($menuUrl);

        $lastPathIds = [];
        foreach ($menus as $k=>$v){
//            p($v);
            if($v['url_slug'] == $menuSlug)$lastPathIds[] = $v;
        }

        foreach ($lastPathIds as $k=>$v){
            $lastPathIds[$k]['pathes'][] = $v['url_slug'];
            $lastPid = $v['pid'];
            for ($i=0; $i<=20; $i++){

                foreach ($menus as $kk=>$vv){
                    if($vv['id'] == $lastPid){
                        $lastPathIds[$k]['pathes'][] = $vv['url_slug'];
                        $lastPid = $vv['pid'];

                        if($lastPid == 0)break;
                    }
                }

            }


            krsort($lastPathIds[$k]['pathes']);
            $tmp = implode('/', $lastPathIds[$k]['pathes']);

            if($menuUrlGlued == $tmp) {
                return $lastPathIds[$k];
            }

        }
        return false;
    }

    public function getHomeMenu(){
        $menu = new SiteMapModel();

        $menus = $menu->getListBy(['set_home'=>1]);
        if(isset($menus[0]))return $menus[0];

        return false;
    }

    public function getContentTypeByComponent($params = []){

        if(!_cv($params, 'secondary_data', 'ar'))return '';

        $component = '';
        foreach($params['secondary_data'] as $k=>$v){
            if( _cv($v, 'singleLayout') != 1 || _cv($v, 'primary') != 1 || !_cv($v, 'selectedComponent'))continue;
            $component = $v['selectedComponent'];
        }

        if(!$component)return '';

        $conf = config("adminpanel.smartComponents.{$component}.type");

        if($conf)return $conf;

        return '';
    }

    public function getSingleContent($contentId = false){


        if(!$contentId)return false;
        $contentId = intval($contentId);
        if(!is_numeric($contentId))return false;

        $locale = _cv($this->urlParts, 'locale');

        $page = new PageModel();

        $ret = $page->getOne(['id'=>$contentId, 'translate' => $locale]);
        if(!_cv($ret, 'id'))return false;
        $contentTypeConfigs = config("adminpanel.content_types.{$ret['content_type']}");
        $content = [];

        if (isset($ret['content']) && $ret['content'] != '') {
            if (is_array($ret['content'])) {
                foreach ($ret['content'] as $v) {
                    if ($v['conf']['type'] == 'editor') {
                        $content[] = $v;
                    }
                }
            } else {
                $content[] = $ret['content'];
            }
        }

        $descContent = isset($content[0]['data'])
            ? mb_substr(strip_tags($content[0]['data']), 0, 160)
            : '';
        $descEditor = isset($ret['editor'])
            ? mb_substr(strip_tags($ret['editor']), 0, 160)
            : '';

        $rett['description'] = _cv($ret, ["seo_description"]) ?: ($ret['seo']['description'] ?: ($descContent ?: $descEditor));
        $rett['keywords'] = _cv($ret, ["seo_keywords"]) ?: $ret['seo']['keywords'];
        $rett['image'] = _cv($ret, ['seo_image', 0 , 'devices', 'desktop']) ?: _cv($ret['seo'], ['image', 0 , 'devices', 'desktop']);
        $rett['date'] = _cv($ret, ['date']);

        foreach ($contentTypeConfigs['fields'] as $k=>$v){
            if( empty($rett['title']) && _cv($v, ['type']) == 'text' && _cv($v, ['useForSeo']) && _cv($ret, $k)){
                $rett['title'] =  _cv($ret, $k);
            }
            if( empty($rett['description']) && _cv($v, ['type']) == 'text' && _cv($v, ['useForSeo']) && _cv($ret, $k)){
                $rett['description'] =  _cv($ret, $k);
            }
            if( empty($rett['keywords']) && _cv($v, ['type']) == 'text' && _cv($v, ['useForSeo']) && _cv($ret, $k)){
                $rett['keywords'] =  _cv($ret, $k);
            }

            if( empty($rett['image']) && _cv($v, ['type']) == 'media' && _cv($v, ['useForSeo']) && _cv($ret, [$k, 0, 'devices', 'desktop'])){
                $rett['image'] =  _cv($ret, [$k, 0, 'devices', 'desktop']);
            }

        }

        if (_cv($ret, 'content_type') === 'projects') {
            if ($locale === 'ge') {
                $rett['title'] = 'პორტფოლიო | ' . $rett['title'] . ' | Connect.ge';
            } else {
                $rett['title'] = 'Portfolio | ' . $rett['title'] . ' | Connect.ge';
            }
        }
        elseif (_cv($ret, 'content_type') === 'services' || _cv($ret, 'content_type') === 'blog' ) {
            $rett['title'] .= ' | Connect.ge';
        }

//        p($rett);

        return $rett;
    }

    public function getProduct($contentId = false){

        if(!$contentId)return false;
        $contentId = intval($contentId);
        if(!is_numeric($contentId))return false;

        $page = new App\Models\Shop\ProductsModel();

        $ret = $page->getOne(['id'=>$contentId, 'translate'=>'ge']);
//p($ret);
        if(!_cv($ret, 'id'))return false;

        $rett['title'] = stripslashes(strip_tags(htmlspecialchars(_cv($ret, ["title"]))));
        $rett['description'] = stripslashes(strip_tags(htmlspecialchars(_cv($ret, ["description"]))));
        $rett['keywords'] = "{$rett['title']} {$rett['description']}";
        $rett['image'] = _cv($ret, ['images',0 , 'devices', 'desktop']);
        $rett['date'] = _cv($ret, ['updated_at']);

        return $rett;
    }


    public function generateUrls($url, $meta)
    {
        $locales = array_keys(config('app.locales'));
        $arr = [];
        $locale = _cv($this->urlParts, 'locale'); // Current request locale

        $url = rtrim($url, '/');

        foreach ($locales as $loc) {
            if($meta == 'alternate'){
                if ($loc !== $locale) {

                    $parsedUrl = parse_url($url);

                    $path = $parsedUrl['path'] ?? '';

                    $localizedPath = '/' . $loc . $path;
                    $localizedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $localizedPath;

                    $arr[$loc] = $localizedUrl;
                }
            }else if($meta == 'canonical'){
                if ($loc == $locale) {

                    $parsedUrl = parse_url($url);

                    $path = $parsedUrl['path'] ?? '';

                    $localizedPath = '/' . $loc . $path;
                    $localizedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $localizedPath;
                    $arr[$loc] = $localizedUrl;
                }
            }
        }
        return $arr;
    }


    public function getSeoParts($content = [], $relatedContent=[]){
        $defaultLan = config('app.locale');
        $headerObjects = [];
        $websiteUrl = env('WEBSITE_URL', env('APP_URL', '#'));
        $OptionsModel = new OptionsModel();
        $siteSettings = $OptionsModel->getKeyValListBy(['content_group'=>'site_configurations'] );
        $urlCleaning = ["'", "`", '"', "(", ")", ">", "<", "!", ",", "\\", "@", " "];
        if(!_cv($content, 'title'))$content['title'] = _cv($siteSettings, 'website_meta_title');
        if(!_cv($content, 'description'))$content['description'] = _cv($siteSettings, 'website_meta_description');
        if(!_cv($content, 'keywords'))$content['keywords'] = _cv($siteSettings, 'website_meta_keywords');

        $publishDate = _cv($content, 'date')?$content['date']:date('Y-m-d');

        $metaRobots = _cv($siteSettings, 'website_meta_robots')?"<meta name='robots' content='{$siteSettings['website_meta_robots']}'>":'';

        $relatedUrl = $url = str_replace($urlCleaning, '', strip_tags($this->pageUrl) );
        if(strpos($url, 'http')===false)$url = $websiteUrl.$url;


        $urls_arr=$this->generateUrls($url, 'alternate');
        $url_canonical = $this->generateUrls($url, 'canonical');

        $headerObjects['type'] = $type = 'website';
        $headerObjects['title'] = $title = mb_substr(strip_tags(_cv($content, 'title')), 0, 195);
        $headerObjects['description'] = $description = mb_substr( strip_tags(_cv($content, 'description')), 0,195);
        $headerObjects['keywords'] = $keywords = mb_substr( strip_tags(_cv($content, 'keywords')), 0,195);
        $htmlTag = '<html lang="'.$this->fuckKa($this->locale).'">';
        $image = str_replace(['../', 'source/'], '', strip_tags(_cv($content, 'image')));

        $headerObjects['lan'] = $this->fuckKa($this->locale);
//        print $url.'**'.$headerObjects['lan'].'--'.$relatedUrl;
        $headerObjects['canonical'] = $url_canonical;
//        $headerObjects['canonical'] = $relatedUrl==$headerObjects['lan']?$websiteUrl:$url;

        foreach ($url_canonical as $key => $value) {
            $value = ( str_replace($urlCleaning, '', strip_tags($value) ) );
            $tags = "<meta charset='UTF-8'>
<link rel='canonical' href='{$value}' />\n";
            $headerObjects['canonical'] = $value;

            $keyMod = ($key == 'ge') ? 'ka':$key;
            $headerObjects['canonical_alternate'][$keyMod] = $value;
//            if($key==$defaultLan)$headerObjects['canonical_alternate'][$keyMod.' '] = str_replace("/{$key}", '', $value);
//            if($key==$defaultLan)$headerObjects['canonical_alternate']['x-default'] = str_replace("/{$key}", '', $value);

        }

///<meta name='language' content='{$this->fuckKa($this->locale)}'>

        foreach ($urls_arr as $key => $value) {
            $value = ( str_replace($urlCleaning, '', strip_tags($value) ) );
            $tags .="<link  rel='alternate' href='{$value}' hreflang='{$this->fuckKa($key)}' />\n";

            $keyMod = ($key == 'ge') ? 'ka':$key;
            $headerObjects['canonical_alternate'][$keyMod] = $value;
//            if($key==$defaultLan)$headerObjects['canonical_alternate'][$keyMod.' '] = str_replace("/{$key}", '', $value);
//            if($key==$defaultLan)$headerObjects['canonical_alternate']['x-default'] = str_replace("/{$key}", '', $value);
        }


        $tags .= "
            <meta name='author' property='og:author' content='website' />
            <meta name='publish_date' property='og:publish_date' content='{$publishDate}' />
            <meta property='og:url'             content='{$url}' />
            <meta property='og:type'            content='{$type}' />
            <meta property='og:title'              content='{$title}' />
            <meta property='og:description'         content='{$description}' />
            <meta property='og:image'              content='{$image}' />
            <meta property='og:image:secure'    content='{$image}' />
            <meta property='og:image:alt'       content='{$title}' />
            <meta property='og:image:width'     content='500' />
            <meta property='og:image:height'    content='261' />
            <meta name='title'              content='{$title}' />
            <meta name='description'        content='{$description}' />
            <meta name='keywords'               content=''>
            <meta name='pageUrl'            content='{$url}' />
            <meta name='url'                content='{$url}' />
            <meta name='robots' content='index,follow'>
            {$metaRobots}
        ";


        $tagsObj =
            [
                [ "name"=> 'author',"property"=> 'og:author', "content"=> 'website' ],
                [ "name"=> 'publish_date',"property"=> 'og:publish_date', "content"=> $publishDate ],
                [ "property"=> 'og:url', "content"=> $url ],
                [ "property"=> 'og:type', "content"=> $type ],
                [ "property"=> 'og:title', "content"=> $title ],
                [ "property"=> 'og:description', "content"=> $description ],
                [ "property"=> 'og:image', "content"=> $image ],
                [ "property"=> 'og:image:secure', "content"=> $image ],
                [ "property"=> 'og:image:alt', "content"=> $title ],
                [ "property"=> 'og:image:width', "content"=> '500' ],
                [ "property"=> 'og:image:height', "content"=> '261' ],
                [ "name"=> 'title', "content"=> $title ],
                [ "name"=> 'description', "content"=> $description ],
                [ "name"=> 'keywords', "content"=> '' ],
                [ "name"=> 'pageUrl', "content"=> $url ],
                [ "name"=> 'url', "content"=> $url ],
                [ "name"=> 'title', "content"=> $title ],
                [ "name"=> 'robots', "content"=> 'index,follow' ],
            ];


        /**
        <meta property='url'           content='{$this->path}' />
        <meta property='menus'           content='".json_encode($this->menus)."' />
         */
        return ['tags'=>$tags, 'title'=>$title, 'htmlTag'=>$htmlTag, 'tagsObj'=>$tagsObj, 'headerObjects'=>$headerObjects];
    }

    /// this is custom method to get some data from db
    /// method should be changed depend on project
    public function getViewItemContent($parts = []){

        if(!_cv($parts, 'viewitem'))return false;
        $formData = OnlineFormsModel::getFormDataBy([ 'name'=>'questions and answers', 'search'=>$parts['viewitem']]);

        if(!_cv($formData, '0.data.title'))return false;

        $data = $formData[0]['data'];
//p($data);
        $ret['title'] = _cv($data, 'title');
        $ret['description'] = _cv($data, 'teaser');
        $ret['keywords'] = _cv($data, 'teaser');
        $ret['image'] = _cv($data, 'image');

//        p($ret);

        return $ret;

    }

    public function prepareOutputHtml($apiData = []){

        if(!is_file($this->templateFile))return '';
        $template = file_get_contents($this->templateFile);

        $indxData = isset($apiData['hydration'])?$apiData['hydration']:'';
        $parts = isset($apiData['placeFillers'])?$apiData['placeFillers']:[];
        $htmlTag = isset($apiData['htmlTag'])?$apiData['htmlTag']:'<html lang="ka">';

        /// place holders for third party scripts
//        $placeHolders = isset($apiData['placeHolders'])?str_replace(['<script ', '<script>'], ['<script type="text/partytown" ', '<script type="text/partytown">'], $apiData['placeHolders']):'';
        $placeHolders = isset($apiData['placeHolders'])?$apiData['placeHolders']:'';
        $template = str_replace('<placeholder_cookies></placeholder_cookies>', $placeHolders, $template);

        foreach ($parts as $k=>$v){
            $template = str_replace("<placeholder_{$k}></placeholder_{$k}>", $v, $template);
        }

        $template = str_replace('<html>', $htmlTag, $template);

//        $template = str_replace("let hydration = false;", "let hydration = ".json_encode($indxData, JSON_UNESCAPED_UNICODE).';', $template);

        if(isset($parts['title'])){
            $template = str_replace("<title>Home....</title>", "<title>{$parts['title']}</title>", $template);
        }

        return $template;

    }

    public function fuckKa($domain = ''){
        return $domain=='ge' && config('app.locales.ka')?'ka':$domain;
    }

    public static function getMetaTagsFromContent($ret = [], $locale = 'ka'){

        $rett['title'] = _cv($ret, ["seo_title_{$locale}"]);
        $rett['description'] = _cv($ret, ["seo_description_{$locale}"]);
        $rett['keywords'] = _cv($ret, ["seo_keywords_{$locale}"]);
        $rett['image'] = _cv($ret, ['seo_image', 0, 'devices', 'desktop']);
        $rett['date'] = _cv($ret, ['date']);

        $contentTypeConfigs = config("adminpanel.content_types.{$ret['content_type']}");

        $taxonomies = '';

        if (isset($contentTypeConfigs['taxonomy'])) {
            foreach ($contentTypeConfigs['taxonomy'] as $taxonomy) {
                if (config("adminpanel.taxonomy.{$taxonomy}.seo") == true) {
                    foreach ($ret[$taxonomy] as $id) {
                        $taxModel = new TaxonomyModel();
                        $taxonomy = $taxModel->getOne(['id' => $id]);
                        $taxonomies .= ' - ' . $taxonomy["title_{$locale}"];
                    }
                }
            }
        }

        foreach ($contentTypeConfigs['fields'] as $k => $v) {
            if (empty($rett['title']) && _cv($v, ['type']) == 'text' && _cv($v, ['useForSeo']) && _cv($ret, $k)) {
                $rett['title'] = _cv($ret, $k) . $taxonomies . ' - ' . tr('Vian');
            }
            if (empty($rett['description']) && _cv($v, ['type']) == 'text' && _cv($v, ['useForSeo']) && _cv($ret, $k)) {
                $rett['description'] = _cv($ret, $k);
            }
            if ((empty($rett['description']) || $rett['description'] != _cv($ret, ["seo_description_{$locale}"])) && _cv($v, ['type']) == 'editor' && _cv($v, ['useForSeo']) && _cv($ret, $k)) {
                $rett['description'] = Str::limit(strip_tags(_cv($ret, $k)), 128, '');
                if ($locale == 'en') {
                    $seeDetail = 'See Detail.';
                } else {
                    $seeDetail = 'გაეცანი დეტალურად.';
                }
                $desc = mb_substr($rett['description'], 0, mb_strripos($rett['description'], '.'));
                if (mb_strlen($desc) > 120) {
                    $rett['description'] = $desc . '. ' . $seeDetail;
                } else {
                    $rett['description'] .= '... ' . $seeDetail;
                }
            }
            if (empty($rett['keywords']) && _cv($v, ['type']) == 'text' && _cv($v, ['useForSeo']) && _cv($ret, $k)) {
                $rett['keywords'] = _cv($ret, $k);
            }

            if (empty($rett['image']) && _cv($v, ['type']) == 'media' && _cv($v, ['useForSeo']) && _cv($ret, [$k, 0, 'devices', 'desktop'])) {
                $rett['image'] = _cv($ret, [$k, 0, 'devices', 'desktop']);
            }

        }
        return $rett;
    }

}

